@extends('layouts.app')

@php
    $title = $isEdit ? 'تعديل الاشتراك' : 'إنشاء اشتراك جديد';
    $subtitle = $isEdit
        ? 'حدّث بيانات الاشتراك، وسيتم إعادة حساب تاريخ الانتهاء تلقائيًا وفق المدة المختارة.'
        : 'اكتب اسم العميل، حدّد مدة الاشتراك، ثم أدخل عدد النسخ وسعر النسخة ليتم حساب الإجمالي تلقائيًا.';
    $startValue = old('starts_at', $license->starts_at?->format('Y-m-d') ?? now()->format('Y-m-d'));
    $expiryValue = old('expires_at', $license->expires_at?->format('Y-m-d') ?? '');
    $copiesCountValue = (int) old('copies_count', $license->copies_count ?? 1);
    $unitPriceValue = old('unit_price', $license->unit_price ?? $license->amount ?? 0);
    $totalAmountValue = number_format($copiesCountValue * (float) $unitPriceValue, 2, '.', '');
    $planOptions = [
        1 => 'شهر واحد',
        3 => '3 شهور',
        6 => '6 شهور',
        12 => 'سنة',
    ];
@endphp

@section('eyebrow', 'بيانات الاشتراك')
@section('title', $title)
@section('subtitle', $subtitle)

@section('actions')
    <a class="btn btn-light" href="{{ $isEdit ? route('licenses.show', $license) : route('licenses.index') }}">رجوع</a>
@endsection

@section('content')
    <div class="grid grid-2">
        <section class="card">
            <div class="section-head">
                <div>
                    <h3>{{ $title }}</h3>
                    <p>سيتم توليد كود الاشتراك تلقائيًا عند الإنشاء.</p>
                </div>
            </div>

            <form method="POST" action="{{ $isEdit ? route('licenses.update', $license) : route('licenses.store') }}" class="stack">
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif

                <div class="form-grid">
                    <div class="field">
                        <label for="customer_name">اسم العميل</label>
                        <input id="customer_name" type="text" name="customer_name" value="{{ old('customer_name', $customerName ?? '') }}" placeholder="مثال: أحمد علي" required>
                    </div>

                    <div class="field">
                        <label>Device ID</label>
                        <div class="empty">
                            {{ $license->device_id ?: 'سيُربط تلقائيًا من جهاز العميل عند أول تشغيل للسكريبت.' }}
                        </div>
                    </div>

                    <div class="field">
                        <label for="plan_months">مدة الاشتراك</label>
                        <select id="plan_months" name="plan_months" required>
                            @foreach ($planOptions as $value => $label)
                                <option value="{{ $value }}" @selected((string) old('plan_months', $license->plan_months) === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label for="starts_at">تاريخ البداية</label>
                        <input id="starts_at" type="date" name="starts_at" value="{{ $startValue }}" required>
                    </div>

                    <div class="field">
                        <label for="copies_count">عدد النسخ</label>
                        <input id="copies_count" type="number" min="1" step="1" name="copies_count" value="{{ $copiesCountValue }}" required>
                    </div>

                    <div class="field">
                        <label for="unit_price">سعر النسخة</label>
                        <input id="unit_price" type="number" step="0.01" min="0" name="unit_price" value="{{ $unitPriceValue }}" required>
                    </div>

                    <div class="field">
                        <label>إجمالي البيع</label>
                        <div class="empty">
                            <strong id="preview-total-amount">{{ $totalAmountValue }}</strong>
                            <span id="preview-currency-inline">{{ old('currency', $license->currency ?? 'EGP') }}</span>
                        </div>
                    </div>

                    <div class="field">
                        <label for="currency">العملة</label>
                        <input id="currency" type="text" name="currency" value="{{ old('currency', $license->currency ?? 'EGP') }}" required>
                    </div>

                    <div class="field">
                        <label for="status">حالة الاشتراك</label>
                        @php
                            $licenseStatuses = ['draft', 'pending', 'active', 'expired', 'suspended'];
                        @endphp
                        <select id="status" name="status" required>
                            @foreach ($licenseStatuses as $status)
                                <option value="{{ $status }}" @selected(old('status', $license->status ?? 'active') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label for="payment_status">حالة السداد</label>
                        @php
                            $paymentStatuses = ['unpaid', 'pending', 'paid', 'partial', 'failed'];
                        @endphp
                        <select id="payment_status" name="payment_status" required>
                            @foreach ($paymentStatuses as $status)
                                <option value="{{ $status }}" @selected(old('payment_status', $license->payment_status ?? 'unpaid') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field full">
                        <label for="notes">ملاحظات داخلية</label>
                        <textarea id="notes" name="notes" placeholder="أي ملاحظات عن التفاوض أو البيانات التي تحتاجها لاحقًا">{{ old('notes', $license->notes ?? '') }}</textarea>
                    </div>

                    <div class="field full">
                        <label for="delivery_notes">ملاحظات التسليم</label>
                        <textarea id="delivery_notes" name="delivery_notes" placeholder="مثال: أرسلنا النسخة على الإيميل أو ننتظر Device ID النهائي">{{ old('delivery_notes', $license->delivery_notes ?? '') }}</textarea>
                    </div>
                </div>

                <div class="inline">
                    <button class="btn btn-primary" type="submit">{{ $isEdit ? 'حفظ التعديلات' : 'إنشاء الاشتراك' }}</button>
                    <span class="hint">سيتم احتساب تاريخ الانتهاء والإجمالي تلقائيًا عند الحفظ، وDevice ID لا يحتاج إدخال يدوي.</span>
                </div>
            </form>
        </section>

        <aside class="stack">
            <section class="card">
                <div class="section-head">
                    <div>
                        <h3>ملخص سريع</h3>
                        <p>تقدير فوري لتاريخ الانتهاء حسب البداية والمدة.</p>
                    </div>
                </div>

                <div class="meta-grid">
                    <div class="meta-item">
                        <span>تاريخ البداية</span>
                        <strong id="preview-start">{{ $startValue }}</strong>
                    </div>
                    <div class="meta-item">
                        <span>تاريخ الانتهاء المتوقع</span>
                        <strong id="preview-expiry">{{ $expiryValue ?: 'سيُحسب تلقائيًا' }}</strong>
                    </div>
                    <div class="meta-item">
                        <span>مدة الخطة</span>
                        <strong id="preview-plan">{{ $planOptions[(int) old('plan_months', $license->plan_months)] ?? 'شهر واحد' }}</strong>
                    </div>
                    <div class="meta-item">
                        <span>عدد النسخ</span>
                        <strong id="preview-copies">{{ number_format($copiesCountValue) }}</strong>
                    </div>
                    <div class="meta-item">
                        <span>سعر النسخة</span>
                        <strong><span id="preview-unit-price">{{ number_format((float) $unitPriceValue, 2, '.', '') }}</span> <span id="preview-currency">{{ old('currency', $license->currency ?? 'EGP') }}</span></strong>
                    </div>
                    <div class="meta-item">
                        <span>إجمالي البيع</span>
                        <strong><span id="preview-total">{{ $totalAmountValue }}</span> <span id="preview-currency-total">{{ old('currency', $license->currency ?? 'EGP') }}</span></strong>
                    </div>
                    <div class="meta-item">
                        <span>ربط الجهاز</span>
                        <strong>{{ $license->device_id ?: 'تلقائي عند أول تشغيل' }}</strong>
                    </div>
                </div>
            </section>

            @if ($isEdit)
                <section class="card">
                    <div class="section-head">
                        <div>
                            <h3>بيانات حالية</h3>
                            <p>تفاصيل محفوظة بالفعل على هذا الاشتراك.</p>
                        </div>
                    </div>

                    <div class="meta-grid">
                        <div class="meta-item">
                            <span>كود الاشتراك</span>
                            <strong class="mono">{{ $license->license_code }}</strong>
                        </div>
                        <div class="meta-item">
                            <span>تاريخ الإنشاء</span>
                            <strong>{{ $license->created_at?->format('Y-m-d H:i') }}</strong>
                        </div>
                        <div class="meta-item">
                            <span>آخر تعديل</span>
                            <strong>{{ $license->updated_at?->diffForHumans() }}</strong>
                        </div>
                    </div>
                </section>
            @endif
        </aside>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var startInput = document.getElementById('starts_at');
            var planInput = document.getElementById('plan_months');
            var copiesInput = document.getElementById('copies_count');
            var unitPriceInput = document.getElementById('unit_price');
            var currencyInput = document.getElementById('currency');
            var previewStart = document.getElementById('preview-start');
            var previewExpiry = document.getElementById('preview-expiry');
            var previewPlan = document.getElementById('preview-plan');
            var previewCopies = document.getElementById('preview-copies');
            var previewUnitPrice = document.getElementById('preview-unit-price');
            var previewTotal = document.getElementById('preview-total');
            var previewCurrency = document.getElementById('preview-currency');
            var previewCurrencyTotal = document.getElementById('preview-currency-total');
            var previewCurrencyInline = document.getElementById('preview-currency-inline');
            var previewTotalAmount = document.getElementById('preview-total-amount');
            var planLabels = {
                1: 'شهر واحد',
                3: '3 شهور',
                6: '6 شهور',
                12: 'سنة'
            };

            function formatMoney(value) {
                var numeric = parseFloat(value || '0');
                if (Number.isNaN(numeric)) {
                    numeric = 0;
                }

                return numeric.toFixed(2);
            }

            function formatDate(date) {
                if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
                    return '';
                }

                var year = date.getFullYear();
                var month = String(date.getMonth() + 1).padStart(2, '0');
                var day = String(date.getDate()).padStart(2, '0');
                return year + '-' + month + '-' + day;
            }

            function calculateExpiry() {
                if (!startInput.value) {
                    previewStart.textContent = 'غير محدد';
                    previewExpiry.textContent = 'سيُحسب تلقائيًا';
                    return;
                }

                var start = new Date(startInput.value + 'T00:00:00');
                var months = parseInt(planInput.value || '1', 10);
                var expiry = new Date(start.getTime());

                expiry.setMonth(expiry.getMonth() + months);
                expiry.setDate(expiry.getDate() - 1);

                previewStart.textContent = formatDate(start);
                previewExpiry.textContent = formatDate(expiry);
                previewPlan.textContent = planLabels[months] || (months + ' شهر');
            }

            function updatePricingPreview() {
                var copies = parseInt(copiesInput.value || '1', 10);
                var unitPrice = parseFloat(unitPriceInput.value || '0');
                var currency = currencyInput.value || 'EGP';

                if (Number.isNaN(copies) || copies < 1) {
                    copies = 1;
                }

                if (Number.isNaN(unitPrice) || unitPrice < 0) {
                    unitPrice = 0;
                }

                var total = copies * unitPrice;

                previewCopies.textContent = String(copies);
                previewUnitPrice.textContent = formatMoney(unitPrice);
                previewTotal.textContent = formatMoney(total);
                previewTotalAmount.textContent = formatMoney(total);
                previewCurrency.textContent = currency;
                previewCurrencyTotal.textContent = currency;
                previewCurrencyInline.textContent = currency;
            }

            startInput.addEventListener('change', calculateExpiry);
            planInput.addEventListener('change', calculateExpiry);
            copiesInput.addEventListener('input', updatePricingPreview);
            unitPriceInput.addEventListener('input', updatePricingPreview);
            currencyInput.addEventListener('input', updatePricingPreview);
            calculateExpiry();
            updatePricingPreview();
        })();
    </script>
@endpush
