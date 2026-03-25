<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\License;
use App\Models\PaymentLink;
use App\Models\PaymentLinkVisit;
use App\Models\PaymentMethod;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = Carbon::today();

        $stats = [
            'customers' => Customer::count(),
            'licenses' => License::count(),
            'copies' => License::sum('copies_count'),
            'active_licenses' => License::where('expires_at', '>=', $today)->where('status', '!=', 'expired')->count(),
            'expired_licenses' => License::where('expires_at', '<', $today)->count(),
            'pending_payments' => License::where('payment_status', '!=', 'paid')->count(),
            'paid_revenue' => License::where('payment_status', 'paid')->sum('amount'),
            'payment_methods' => PaymentMethod::where('is_active', true)->count(),
            'payment_link_clicks' => PaymentLinkVisit::count(),
        ];

        $recentLicenses = License::with('customer')->latest()->limit(8)->get();
        $recentLinks = PaymentLink::with(['license.customer', 'paymentMethod'])->latest()->limit(8)->get();

        return view('dashboard.index', compact('stats', 'recentLicenses', 'recentLinks'));
    }
}
