@extends('layouts.app')

@section('eyebrow', 'إدارة طرق الدفع')
@section('title', 'طرق الدفع')
@section('subtitle', 'أضف طرق الدفع التي تستخدمها في البيع، واربط كل طريقة بلينك أو رقم أو تعليمات يمكن مشاركتها مع العميل.')

@section('content')
    <div class="grid grid-2">
        <section class="card">
            <div class="section-head">
                <div>
                    <h3>{{ $editing ? 'تعديل طريقة دفع' : 'إضافة طريقة دفع' }}</h3>
                    <p>اللينك هنا هو الوجهة النهائية التي سيُحوَّل لها العميل بعد فتح رابط الدفع العام.</p>
                </div>

                @if ($editing)
                    <a class="btn btn-light" href="{{ route('payment-methods.index') }}">إلغاء التعديل</a>
                @endif
            </div>

            <form method="POST" action="{{ $editing ? route('payment-methods.update', $editing) : route('payment-methods.store') }}" class="stack">
                @csrf
                @if ($editing)
                    @method('PUT')
                @endif

                <div class="form-grid">
                    <div class="field">
                        <label for="name">اسم الطريقة</label>
                        <input id="name" type="text" name="name" value="{{ old('name', $editing->name ?? '') }}" placeholder="مثال: InstaPay" required>
                    </div>

                    <div class="field">
                        <label for="slug">Slug اختياري</label>
                        <input id="slug" type="text" name="slug" value="{{ old('slug', $editing->slug ?? '') }}" placeholder="يُنشأ تلقائيًا إذا تركته فارغًا">
                    </div>

                    <div class="field">
                        <label for="payment_type">نوع الدفع</label>
                        @php
                            $types = [
                                'wallet' => 'Wallet / Cash App',
                                'bank-transfer' => 'Bank Transfer',
                                'manual' => 'Manual Confirmation',
                                'link' => 'Hosted Payment Link',
                            ];
                        @endphp
                        <select id="payment_type" name="payment_type" required>
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected(old('payment_type', $editing->payment_type ?? 'wallet') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label for="sort_order">ترتيب الظهور</label>
                        <input id="sort_order" type="number" min="0" name="sort_order" value="{{ old('sort_order', $editing->sort_order ?? 0) }}">
                    </div>

                    <div class="field">
                        <label for="account_label">عنوان الحقل</label>
                        <input id="account_label" type="text" name="account_label" value="{{ old('account_label', $editing->account_label ?? '') }}" placeholder="مثال: رقم المحفظة">
                    </div>

                    <div class="field">
                        <label for="account_identifier">بيان الحساب</label>
                        <input id="account_identifier" type="text" name="account_identifier" value="{{ old('account_identifier', $editing->account_identifier ?? '') }}" placeholder="مثال: 01000000000">
                    </div>

                    <div class="field full">
                        <label for="payment_url">لينك التحويل أو الدفع</label>
                        <input id="payment_url" type="text" name="payment_url" value="{{ old('payment_url', $editing->payment_url ?? '') }}" placeholder="https://... أو deep-link أو short link">
                    </div>

                    <div class="field full">
                        <label for="instructions">تعليمات الدفع</label>
                        <textarea id="instructions" name="instructions" placeholder="مثال: بعد التحويل أرسل لقطة شاشة على واتساب">{{ old('instructions', $editing->instructions ?? '') }}</textarea>
                    </div>
                </div>

                <label class="inline">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing->is_active ?? true)) style="width:auto;">
                    <span>مفعلة ويمكن استخدامها في الروابط الجديدة</span>
                </label>

                <div class="inline">
                    <button class="btn btn-primary" type="submit">{{ $editing ? 'حفظ التعديلات' : 'إضافة الطريقة' }}</button>
                    <span class="hint">يمكنك تعديل الرقم أو الرابط في أي وقت بدون فقدان السجلات السابقة.</span>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="section-head">
                <div>
                    <h3>الطرق الحالية</h3>
                    <p>كل طريقة تعرض بياناتها الأساسية وعدد الروابط المنشأة من خلالها.</p>
                </div>
            </div>

            @if ($paymentMethods->isEmpty())
                <div class="empty">لا توجد طرق دفع بعد. يمكنك البدء بـ InstaPay وVodafone Cash وOrange Cash.</div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>الاسم</th>
                                <th>النوع</th>
                                <th>البيان</th>
                                <th>الروابط</th>
                                <th>الحالة</th>
                                <th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($paymentMethods as $method)
                                <tr>
                                    <td>
                                        <strong>{{ $method->name }}</strong>
                                        <div class="muted mono">{{ $method->slug }}</div>
                                    </td>
                                    <td>{{ $method->payment_type }}</td>
                                    <td>
                                        <div>{{ $method->account_label ?: 'بدون عنوان' }}</div>
                                        <div class="muted">{{ $method->account_identifier ?: ($method->payment_url ?: '-') }}</div>
                                    </td>
                                    <td>{{ number_format($method->payment_links_count) }}</td>
                                    <td>
                                        <span class="badge {{ $method->is_active ? 'badge-success' : 'badge-muted' }}">
                                            {{ $method->is_active ? 'active' : 'inactive' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <a class="btn btn-light" href="{{ route('payment-methods.edit', $method) }}">تعديل</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @include('partials.pagination', ['paginator' => $paymentMethods])
            @endif
        </section>
    </div>
@endsection
