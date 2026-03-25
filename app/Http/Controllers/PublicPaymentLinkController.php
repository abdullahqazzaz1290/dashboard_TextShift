<?php

namespace App\Http\Controllers;

use App\Models\PaymentLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PublicPaymentLinkController extends Controller
{
    public function show(Request $request, string $slug): RedirectResponse
    {
        $paymentLink = PaymentLink::where('slug', $slug)->firstOrFail();

        $paymentLink->visits()->create([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer' => $request->headers->get('referer'),
            'query_string' => $request->getQueryString(),
            'visited_at' => now(),
        ]);

        $paymentLink->increment('clicked_count');
        $paymentLink->update([
            'last_clicked_at' => now(),
        ]);

        return redirect()->away($paymentLink->target_url);
    }
}
