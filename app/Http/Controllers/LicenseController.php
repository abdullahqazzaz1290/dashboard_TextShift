<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\License;
use App\Models\PaymentMethod;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class LicenseController extends Controller
{
    public function index(): View
    {
        $licenses = License::with('customer')->withCount('paymentLinks')->latest()->paginate(15);

        return view('licenses.index', compact('licenses'));
    }

    public function create(): View
    {
        $selectedCustomer = request()->filled('customer_id')
            ? Customer::find(request()->integer('customer_id'))
            : null;
        $defaults = [
            'copies_count' => 1,
            'plan_months' => 1,
            'starts_at' => Carbon::today(),
            'status' => 'active',
            'payment_status' => 'unpaid',
            'unit_price' => 0,
            'amount' => 0,
            'currency' => 'EGP',
        ];

        return view('licenses.form', [
            'license' => new License($defaults),
            'customerName' => old('customer_name', $selectedCustomer?->name ?? ''),
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRequest($request);
        $dates = $this->resolveDates($validated['starts_at'], (int) $validated['plan_months']);
        $customer = $this->resolveCustomer($validated['customer_name']);

        $license = License::create([
            ...$this->payloadForPersistence($validated),
            'customer_id' => $customer->id,
            'license_code' => $this->generateLicenseCode(),
            'starts_at' => $dates['starts_at'],
            'expires_at' => $dates['expires_at'],
        ]);

        return redirect()->route('licenses.show', $license)->with('status', 'License created successfully.');
    }

    public function show(License $license): View
    {
        $license->load(['customer', 'paymentLinks.paymentMethod', 'paymentLinks.visits']);
        $paymentMethods = PaymentMethod::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return view('licenses.show', compact('license', 'paymentMethods'));
    }

    public function edit(License $license): View
    {
        return view('licenses.form', [
            'license' => $license,
            'customerName' => old('customer_name', $license->customer?->name ?? ''),
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, License $license): RedirectResponse
    {
        $validated = $this->validateRequest($request);
        $dates = $this->resolveDates($validated['starts_at'], (int) $validated['plan_months']);
        $customer = $this->resolveCustomer($validated['customer_name']);

        $license->update([
            ...$this->payloadForPersistence($validated),
            'customer_id' => $customer->id,
            'starts_at' => $dates['starts_at'],
            'expires_at' => $dates['expires_at'],
        ]);

        return redirect()->route('licenses.show', $license)->with('status', 'License updated successfully.');
    }

    protected function validateRequest(Request $request): array
    {
        return $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'copies_count' => ['required', 'integer', 'min:1', 'max:500'],
            'plan_months' => ['required', 'integer', 'in:1,3,6,12'],
            'starts_at' => ['required', 'date'],
            'status' => ['required', 'string', 'in:draft,pending,active,expired,suspended'],
            'payment_status' => ['required', 'string', 'in:unpaid,pending,paid,partial,failed'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:8'],
            'notes' => ['nullable', 'string'],
            'delivery_notes' => ['nullable', 'string'],
        ]);
    }

    protected function resolveCustomer(string $customerName): Customer
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $customerName) ?? '');

        $customer = Customer::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($normalized)])
            ->first();

        if ($customer) {
            return $customer;
        }

        return Customer::create([
            'name' => $normalized,
            'country' => 'Egypt',
        ]);
    }

    protected function payloadForPersistence(array $validated): array
    {
        unset($validated['customer_name']);

        $validated['amount'] = round(
            ((int) $validated['copies_count']) * ((float) $validated['unit_price']),
            2
        );

        return $validated;
    }

    protected function resolveDates(string $startsAt, int $planMonths): array
    {
        $start = Carbon::parse($startsAt)->startOfDay();
        $end = $start->copy()->addMonthsNoOverflow($planMonths)->subDay();

        return [
            'starts_at' => $start,
            'expires_at' => $end,
        ];
    }

    protected function generateLicenseCode(): string
    {
        do {
            $code = 'SUB-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (License::where('license_code', $code)->exists());

        return $code;
    }
}
