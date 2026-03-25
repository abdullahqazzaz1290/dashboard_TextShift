<?php

namespace App\Http\Controllers;

use App\Models\License;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LicenseHandshakeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'license_code' => ['required', 'string', 'max:255'],
            'activation_secret' => ['required', 'string', 'min:32', 'max:255'],
            'device_id' => ['required', 'string', 'max:255'],
            'machine_fingerprint' => ['required', 'string', 'max:255'],
            'local_ip' => ['nullable', 'string', 'max:255'],
            'local_ips' => ['nullable', 'string', 'max:1000'],
        ]);

        $licenseCode = trim($payload['license_code']);
        $license = $this->resolveLicense($licenseCode);

        if (!$license) {
            return $this->errorResponse('تعذر العثور على الترخيص المطلوب.', 'LICENSE_NOT_FOUND', 404);
        }

        $license->loadMissing('customer');
        $copy = $this->resolveCopyPayload($license, $licenseCode);

        if (!$copy) {
            return $this->errorResponse('هذه النسخة غير معروفة داخل الحزمة الحالية.', 'LICENSE_COPY_NOT_FOUND', 404);
        }

        $expectedSecretHash = (string) ($copy['activation_secret_hash'] ?? '');
        $receivedSecretHash = hash('sha256', $payload['activation_secret']);

        if (!$expectedSecretHash || !hash_equals($expectedSecretHash, $receivedSecretHash)) {
            return $this->errorResponse('رمز التفعيل غير صالح لهذه النسخة.', 'LICENSE_SECRET_INVALID', 403);
        }

        if ($license->payment_status !== 'paid') {
            return $this->errorResponse('لا يمكن تفعيل الترخيص قبل تأكيد الدفع.', 'LICENSE_PAYMENT_REQUIRED', 403);
        }

        if (in_array($license->status, ['draft', 'pending', 'suspended'], true)) {
            return $this->errorResponse('حالة الترخيص الحالية لا تسمح بالتفعيل.', 'LICENSE_STATUS_INVALID', 403);
        }

        $expiresAt = $license->expires_at?->copy()->endOfDay();
        if (!$expiresAt || now()->greaterThan($expiresAt)) {
            return $this->errorResponse('انتهت صلاحية هذا الترخيص.', 'LICENSE_EXPIRED', 403);
        }

        $metadata = $license->metadata ?? [];
        $activations = is_array($metadata['activations'] ?? null) ? $metadata['activations'] : [];
        $existingActivation = is_array($activations[$licenseCode] ?? null) ? $activations[$licenseCode] : null;
        $isFirstActivation = !$existingActivation;

        if ($existingActivation && !$this->matchesExistingActivation($existingActivation, $payload)) {
            return $this->errorResponse(
                'تم تفعيل هذه النسخة بالفعل على جهاز مختلف.',
                'LICENSE_DEVICE_MISMATCH',
                409
            );
        }

        $now = now();
        $activation = array_merge($existingActivation ?? [], [
            'license_code' => $licenseCode,
            'device_id' => $payload['device_id'],
            'machine_fingerprint' => $payload['machine_fingerprint'],
            'activation_ip' => $existingActivation['activation_ip'] ?? $request->ip(),
            'last_seen_ip' => $request->ip(),
            'local_ip' => $payload['local_ip'] ?? '',
            'local_ips' => $payload['local_ips'] ?? '',
            'activated_at' => $existingActivation['activated_at'] ?? $now->toIso8601String(),
            'last_check_in_at' => $now->toIso8601String(),
            'copy_number' => $copy['copy_number'] ?? null,
            'folder_name' => $copy['folder_name'] ?? null,
        ]);

        $activations[$licenseCode] = $activation;
        $metadata['activations'] = $activations;

        $license->forceFill([
            'device_id' => (int) ($license->copies_count ?: 1) === 1 ? $payload['device_id'] : $license->device_id,
            'metadata' => $metadata,
        ])->save();

        return response()->json([
            'ok' => true,
            'message' => $isFirstActivation ? 'تم تفعيل الترخيص بنجاح.' : 'تم التحقق من الترخيص بنجاح.',
            'license' => [
                'customer' => $license->customer?->name ?? '',
                'license_code' => $licenseCode,
                'device_id' => $payload['device_id'],
                'machine_fingerprint' => $payload['machine_fingerprint'],
                'local_ip' => $payload['local_ip'] ?? '',
                'activation_ip' => $activation['activation_ip'] ?? '',
                'status' => 'active',
                'expiry' => $license->expires_at?->format('Y-m-d') ?? '',
                'activated_at' => $activation['activated_at'],
                'validated_at' => $activation['last_check_in_at'],
            ],
        ]);
    }

    protected function resolveLicense(string $licenseCode): ?License
    {
        $baseLicenseCode = preg_replace('/-\d{2}$/', '', $licenseCode) ?: $licenseCode;

        return License::query()
            ->where('license_code', $baseLicenseCode)
            ->orWhere('license_code', $licenseCode)
            ->first();
    }

    protected function resolveCopyPayload(License $license, string $licenseCode): ?array
    {
        $copies = data_get($license->metadata, 'delivery.copies', []);
        if (!is_array($copies)) {
            return null;
        }

        foreach ($copies as $copy) {
            if (($copy['license_code'] ?? null) === $licenseCode) {
                return $copy;
            }
        }

        return null;
    }

    protected function matchesExistingActivation(array $existingActivation, array $payload): bool
    {
        return ($existingActivation['device_id'] ?? null) === $payload['device_id']
            && ($existingActivation['machine_fingerprint'] ?? null) === $payload['machine_fingerprint'];
    }

    protected function errorResponse(string $message, string $code, int $status): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => $message,
            'code' => $code,
            'server_time' => Carbon::now()->toIso8601String(),
        ], $status);
    }
}
