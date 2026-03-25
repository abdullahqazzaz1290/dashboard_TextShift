<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentMethodController extends Controller
{
    public function index(Request $request): View
    {
        $paymentMethods = PaymentMethod::withCount('paymentLinks')->orderBy('sort_order')->orderBy('name')->paginate(15);
        $editing = $request->filled('edit') ? PaymentMethod::findOrFail($request->integer('edit')) : null;

        return view('payment-methods.index', compact('paymentMethods', 'editing'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRequest($request);

        if (blank($validated['slug'] ?? null)) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        PaymentMethod::create($validated);

        return redirect()->route('payment-methods.index')->with('status', 'Payment method created successfully.');
    }

    public function edit(PaymentMethod $paymentMethod): View
    {
        $paymentMethods = PaymentMethod::withCount('paymentLinks')->orderBy('sort_order')->orderBy('name')->paginate(15);
        $editing = $paymentMethod;

        return view('payment-methods.index', compact('paymentMethods', 'editing'));
    }

    public function update(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $validated = $this->validateRequest($request, $paymentMethod->id);

        if (blank($validated['slug'] ?? null)) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $paymentMethod->update($validated);

        return redirect()->route('payment-methods.index')->with('status', 'Payment method updated successfully.');
    }

    protected function validateRequest(Request $request, ?int $ignoreId = null): array
    {
        $uniqueSlug = 'unique:payment_methods,slug';
        if ($ignoreId) {
            $uniqueSlug .= ',' . $ignoreId;
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', $uniqueSlug],
            'payment_type' => ['required', 'string', 'max:255'],
            'account_label' => ['nullable', 'string', 'max:255'],
            'account_identifier' => ['nullable', 'string', 'max:255'],
            'payment_url' => ['nullable', 'string', 'max:2048'],
            'instructions' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]) + [
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) $request->input('sort_order', 0),
        ];
    }
}
