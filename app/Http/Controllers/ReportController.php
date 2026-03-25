<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Models\PaymentLink;
use App\Models\PaymentLinkVisit;
use App\Models\PaymentMethod;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(): View
    {
        $revenueByMethod = PaymentLink::query()
            ->select('payment_method_id', DB::raw('SUM(COALESCE(paid_amount, amount)) as total_revenue'))
            ->where('status', 'paid')
            ->groupBy('payment_method_id')
            ->with('paymentMethod')
            ->get();

        $licenseStatusCounts = License::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $paymentStatusCounts = License::query()
            ->select('payment_status', DB::raw('COUNT(*) as total'))
            ->groupBy('payment_status')
            ->pluck('total', 'payment_status');

        $clicksByMethod = PaymentLinkVisit::query()
            ->join('payment_links', 'payment_links.id', '=', 'payment_link_visits.payment_link_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'payment_links.payment_method_id')
            ->select('payment_methods.name', DB::raw('COUNT(payment_link_visits.id) as total_clicks'))
            ->groupBy('payment_methods.id', 'payment_methods.name')
            ->orderByDesc('total_clicks')
            ->get();

        $recentVisits = PaymentLinkVisit::with(['paymentLink.paymentMethod', 'paymentLink.license.customer'])
            ->latest('visited_at')
            ->limit(20)
            ->get();

        $topMethods = PaymentMethod::withCount('paymentLinks')->orderByDesc('payment_links_count')->get();
        $copiesSold = (int) License::sum('copies_count');
        $averageCopyPrice = $copiesSold > 0
            ? (float) License::sum('amount') / $copiesSold
            : 0.0;

        return view('reports.index', compact(
            'revenueByMethod',
            'licenseStatusCounts',
            'paymentStatusCounts',
            'clicksByMethod',
            'recentVisits',
            'topMethods',
            'copiesSold',
            'averageCopyPrice',
        ));
    }
}
