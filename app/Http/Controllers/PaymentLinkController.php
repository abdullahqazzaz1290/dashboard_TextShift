<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Models\PaymentLink;
use App\Services\LicensePackageBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class PaymentLinkController extends Controller
{
    public function store(Request $request, License $license): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'target_url' => ['required', 'string', 'max:2048'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:8'],
            'notes' => ['nullable', 'string'],
        ]);

        $paymentLink = $license->paymentLinks()->create([
            ...$validated,
            'slug' => $this->generateSlug(),
            'title' => $validated['title'] ?: 'Payment for ' . $license->license_code,
            'status' => 'pending',
        ]);

        return redirect()->route('licenses.show', $license)->with('status', 'Payment link created successfully: ' . route('payment-links.public', $paymentLink->slug));
    }

    public function update(Request $request, PaymentLink $paymentLink, LicensePackageBuilder $builder): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'target_url' => ['required', 'string', 'max:2048'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:8'],
            'status' => ['required', 'string', 'in:pending,paid,expired,cancelled'],
            'notes' => ['nullable', 'string'],
        ]);

        $paymentLink->update($validated);
        $this->syncLicensePaymentStatus($paymentLink->license);

        return redirect()
            ->route('licenses.show', $paymentLink->license)
            ->with('status', 'Payment link updated successfully.' . $this->packageBuildStatusMessage($paymentLink->fresh('license')->license, $builder, $validated['status'] === 'paid'));
    }

    public function markPaid(Request $request, PaymentLink $paymentLink, LicensePackageBuilder $builder): RedirectResponse
    {
        $validated = $request->validate([
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $paymentLink->update([
            'status' => 'paid',
            'paid_at' => now(),
            'paid_amount' => $validated['paid_amount'] ?? $paymentLink->amount,
        ]);

        $paymentLink->license->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        return redirect()
            ->route('licenses.show', $paymentLink->license)
            ->with('status', 'Payment marked as paid.' . $this->packageBuildStatusMessage($paymentLink->license, $builder, true));
    }

    public function markPending(PaymentLink $paymentLink): RedirectResponse
    {
        $paymentLink->update([
            'status' => 'pending',
            'paid_at' => null,
            'paid_amount' => null,
        ]);

        $this->syncLicensePaymentStatus($paymentLink->license);

        return redirect()->route('licenses.show', $paymentLink->license)->with('status', 'Payment returned to pending.');
    }

    protected function syncLicensePaymentStatus(License $license): void
    {
        $hasPaidLink = $license->paymentLinks()->where('status', 'paid')->exists();

        $license->update([
            'payment_status' => $hasPaidLink ? 'paid' : 'unpaid',
            'paid_at' => $hasPaidLink ? $license->paymentLinks()->where('status', 'paid')->latest('paid_at')->value('paid_at') : null,
        ]);
    }

    protected function generateSlug(): string
    {
        do {
            $slug = 'pay-' . Str::lower(Str::random(10));
        } while (PaymentLink::where('slug', $slug)->exists());

        return $slug;
    }

    protected function packageBuildStatusMessage(License $license, LicensePackageBuilder $builder, bool $shouldBuild): string
    {
        if (!$shouldBuild) {
            return '';
        }

        try {
            $builder->buildFor($license->fresh('customer'));

            return ' Delivery package generated successfully.';
        } catch (Throwable $e) {
            $builder->markFailure($license, $e->getMessage());

            return ' Payment is saved, but package generation failed: ' . $e->getMessage();
        }
    }
}
