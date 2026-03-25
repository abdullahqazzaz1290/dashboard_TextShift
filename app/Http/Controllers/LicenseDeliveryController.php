<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Services\LicensePackageBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class LicenseDeliveryController extends Controller
{
    public function rebuild(License $license, LicensePackageBuilder $builder): RedirectResponse
    {
        try {
            $builder->buildFor($license);

            return redirect()
                ->route('licenses.show', $license)
                ->with('status', 'Customer package rebuilt successfully.');
        } catch (Throwable $e) {
            $builder->markFailure($license, $e->getMessage());

            return redirect()
                ->route('licenses.show', $license)
                ->withErrors(['delivery' => 'Package build failed: ' . $e->getMessage()]);
        }
    }

    public function download(License $license): BinaryFileResponse|RedirectResponse
    {
        if (!$license->jsxbin_package_path || !Storage::disk('local')->exists($license->jsxbin_package_path)) {
            return redirect()
                ->route('licenses.show', $license)
                ->withErrors(['delivery' => 'Package archive is not ready yet.']);
        }

        return response()->download(
            Storage::disk('local')->path($license->jsxbin_package_path),
            basename($license->jsxbin_package_path),
        );
    }

    public function publicCopyDownload(Request $request, License $license, int $copyNumber, string $token): BinaryFileResponse
    {
        $copy = $this->resolveCopyForDownload($license, $copyNumber, $token);
        $archiveRelative = $copy['archive_relative'] ?? $license->jsxbin_package_path;

        if (!$archiveRelative || !Storage::disk('local')->exists($archiveRelative)) {
            abort(404, 'ملف النسخة غير متاح الآن.');
        }

        $this->markCopyAsDownloaded($license, $copyNumber, $request);

        return response()->download(
            Storage::disk('local')->path($archiveRelative),
            $copy['archive_name'] ?? basename($archiveRelative),
        );
    }

    protected function resolveCopyForDownload(License $license, int $copyNumber, string $token): array
    {
        if ($license->payment_status !== 'paid') {
            abort(403, 'لا يمكن تنزيل النسخة قبل تأكيد السداد.');
        }

        $copies = data_get($license->metadata, 'delivery.copies', []);
        if (!is_array($copies)) {
            abort(404, 'بيانات التسليم غير متاحة.');
        }

        foreach ($copies as $copy) {
            if ((int) ($copy['copy_number'] ?? 0) !== $copyNumber) {
                continue;
            }

            if (($copy['download_token'] ?? '') !== $token) {
                abort(404, 'رابط التنزيل غير صالح.');
            }

            if (($copy['download_status'] ?? 'ready') === 'downloaded') {
                abort(410, 'تم استخدام رابط التنزيل لهذه النسخة بالفعل.');
            }

            return $copy;
        }

        abort(404, 'تعذر العثور على النسخة المطلوبة.');
    }

    protected function markCopyAsDownloaded(License $license, int $copyNumber, Request $request): void
    {
        $metadata = $license->metadata ?? [];
        $copies = data_get($metadata, 'delivery.copies', []);

        if (!is_array($copies)) {
            return;
        }

        foreach ($copies as $index => $copy) {
            if ((int) ($copy['copy_number'] ?? 0) !== $copyNumber) {
                continue;
            }

            $copies[$index] = array_merge($copy, [
                'download_status' => 'downloaded',
                'downloaded_at' => now()->toIso8601String(),
                'downloaded_ip' => $request->ip(),
                'downloaded_user_agent' => (string) ($request->userAgent() ?: ''),
            ]);
            break;
        }

        $metadata['delivery'] = array_merge($metadata['delivery'] ?? [], [
            'copies' => $copies,
        ]);

        $license->forceFill([
            'metadata' => $metadata,
        ])->save();
    }
}
