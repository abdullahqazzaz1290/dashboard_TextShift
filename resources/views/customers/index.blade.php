@extends('layouts.app')

@section('eyebrow', 'إدارة العملاء')
@section('title', 'العملاء')
@section('subtitle', 'سجل بيانات العميل مرة واحدة ثم اربط بها الاشتراكات وروابط الدفع والتجديدات القادمة.')

@section('actions')
    <a class="btn btn-primary" href="{{ route('licenses.create') }}">اشتراك جديد</a>
@endsection

@section('content')
    <div class="grid grid-2">
        <section class="card">
            <div class="section-head">
                <div>
                    <h3>{{ $editing ? 'تعديل بيانات العميل' : 'إضافة عميل جديد' }}</h3>
                    <p>يمكنك ترك الحقول غير الأساسية فارغة واستكمالها لاحقًا.</p>
                </div>

                @if ($editing)
                    <a class="btn btn-light" href="{{ route('customers.index') }}">إلغاء التعديل</a>
                @endif
            </div>

            <form method="POST" action="{{ $editing ? route('customers.update', $editing) : route('customers.store') }}" class="stack">
                @csrf
                @if ($editing)
                    @method('PUT')
                @endif

                <div class="form-grid">
                    <div class="field">
                        <label for="name">اسم العميل</label>
                        <input id="name" type="text" name="name" value="{{ old('name', $editing->name ?? '') }}" required>
                    </div>

                    <div class="field">
                        <label for="company">الشركة أو النشاط</label>
                        <input id="company" type="text" name="company" value="{{ old('company', $editing->company ?? '') }}">
                    </div>

                    <div class="field">
                        <label for="email">البريد الإلكتروني</label>
                        <input id="email" type="email" name="email" value="{{ old('email', $editing->email ?? '') }}">
                    </div>

                    <div class="field">
                        <label for="phone">رقم الهاتف</label>
                        <input id="phone" type="text" name="phone" value="{{ old('phone', $editing->phone ?? '') }}">
                    </div>

                    <div class="field">
                        <label for="country">الدولة</label>
                        <input id="country" type="text" name="country" value="{{ old('country', $editing->country ?? 'Egypt') }}">
                    </div>

                    <div class="field full">
                        <label for="notes">ملاحظات</label>
                        <textarea id="notes" name="notes">{{ old('notes', $editing->notes ?? '') }}</textarea>
                    </div>
                </div>

                <div class="inline">
                    <button class="btn btn-primary" type="submit">{{ $editing ? 'حفظ التعديلات' : 'إضافة العميل' }}</button>
                    <span class="hint">بعد الحفظ يمكنك إنشاء اشتراك لهذا العميل مباشرة.</span>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="section-head">
                <div>
                    <h3>قاعدة العملاء</h3>
                    <p>عرض سريع لكل العملاء مع عدد الاشتراكات المسجلة لكل واحد.</p>
                </div>
            </div>

            @if ($customers->isEmpty())
                <div class="empty">لا يوجد عملاء حتى الآن.</div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>العميل</th>
                                <th>التواصل</th>
                                <th>الاشتراكات</th>
                                <th>آخر تحديث</th>
                                <th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($customers as $customer)
                                <tr>
                                    <td>
                                        <strong>{{ $customer->name }}</strong>
                                        <div class="muted">{{ $customer->company ?: 'بدون شركة' }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $customer->phone ?: '-' }}</div>
                                        <div class="muted">{{ $customer->email ?: '-' }}</div>
                                    </td>
                                    <td>{{ number_format($customer->licenses_count) }}</td>
                                    <td>{{ $customer->updated_at?->diffForHumans() }}</td>
                                    <td>
                                        <div class="table-actions">
                                            <a class="btn btn-light" href="{{ route('customers.edit', $customer) }}">تعديل</a>
                                            <a class="btn btn-secondary" href="{{ route('licenses.create', ['customer_id' => $customer->id]) }}">اشتراك جديد</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @include('partials.pagination', ['paginator' => $customers])
            @endif
        </section>
    </div>
@endsection
