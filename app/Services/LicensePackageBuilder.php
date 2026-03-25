<?php

namespace App\Services;

use App\Models\License;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class LicensePackageBuilder
{
    public function buildFor(License $license): array
    {
        $license->loadMissing('customer');

        if (!$license->customer) {
            throw new RuntimeException('Cannot build delivery package without a customer.');
        }

        $disk = Storage::disk('local');
        $deliveryRootRelative = 'deliveries';
        $deliveryRootAbsolute = $disk->path($deliveryRootRelative);
        $inputRelative = $deliveryRootRelative . '/.tmp/build-' . $license->id . '-' . Str::random(10) . '.json';
        $inputAbsolute = $disk->path($inputRelative);

        $disk->makeDirectory($deliveryRootRelative . '/.tmp');
        $disk->put($inputRelative, json_encode([
            'projectRoot' => base_path('..'),
            'deliveryRoot' => $deliveryRootAbsolute,
            'customerName' => $license->customer->name,
            'licenseCode' => $license->license_code,
            'activationSeed' => hash('sha256', (string) config('app.key') . '|' . $license->license_code),
            'handshakeUrl' => route('licenses.handshake'),
            'copiesCount' => (int) ($license->copies_count ?: 1),
            'unitPrice' => (float) ($license->unit_price ?? $license->amount ?? 0),
            'totalAmount' => (float) ($license->amount ?? 0),
            'expiry' => $license->expires_at?->format('Y-m-d'),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        try {
            $process = Process::path(base_path())
                ->timeout(300)
                ->run([
                    'node',
                    base_path('scripts/build-license-package.cjs'),
                    $inputAbsolute,
                ]);
        } finally {
            $disk->delete($inputRelative);
        }

        if ($process->failed()) {
            throw new RuntimeException(trim($process->errorOutput() ?: $process->output() ?: 'Package build failed.'));
        }

        $result = json_decode(trim($process->output()), true);
        if (!is_array($result) || empty($result['archive_relative']) || empty($result['package_relative'])) {
            throw new RuntimeException('Package builder returned an invalid response.');
        }

        $metadata = $license->metadata ?? [];
        $copies = collect($result['copies'] ?? [])->map(function (array $copy): array {
            return array_merge($copy, [
                'download_token' => Str::random(48),
                'download_status' => 'ready',
                'downloaded_at' => null,
                'downloaded_ip' => null,
                'downloaded_user_agent' => null,
            ]);
        })->values()->all();

        $metadata['delivery'] = [
            'status' => 'ready',
            'built_at' => now()->toIso8601String(),
            'customer_name' => $license->customer->name,
            'license_code' => $license->license_code,
            'expiry' => $license->expires_at?->format('Y-m-d'),
            'copies_count' => (int) ($license->copies_count ?: 1),
            'unit_price' => (float) ($license->unit_price ?? $license->amount ?? 0),
            'total_amount' => (float) ($license->amount ?? 0),
            'package_relative' => $result['package_relative'],
            'archive_relative' => $result['archive_relative'],
            'package_name' => $result['package_name'] ?? basename($result['package_relative']),
            'archive_name' => $result['archive_name'] ?? basename($result['archive_relative']),
            'compiled_files' => $result['compiled_files'] ?? 0,
            'source_files_removed' => $result['source_files_removed'] ?? 0,
            'copies' => $copies,
            'error' => null,
        ];

        $license->forceFill([
            'jsx_package_path' => $result['package_relative'],
            'jsxbin_package_path' => $result['archive_relative'],
            'metadata' => $metadata,
        ])->save();

        return $result;
    }

    public function markFailure(License $license, string $message): void
    {
        $metadata = $license->metadata ?? [];
        $deliveryMeta = $metadata['delivery'] ?? [];

        $metadata['delivery'] = array_merge($deliveryMeta, [
            'status' => 'failed',
            'failed_at' => now()->toIso8601String(),
            'error' => $message,
        ]);

        $license->forceFill([
            'metadata' => $metadata,
        ])->save();
    }
}
