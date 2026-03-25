@extends('layouts.app')

@section('eyebrow', 'إدارة الاشتراكات')
@section('title', 'الاشتراكات والتراخيص')
@section('subtitle', 'أنشئ اشتراكًا لكل عميل مع مدة ثابتة، وحدد عدد النسخ وسعر النسخة، وتابع الدفع والتسليم من صفحة الاشتراك نفسها.')

@section('actions')
    <a class="btn btn-primary" href="{{ route('licenses.create') }}">إنشاء اشتراك</a>
    <a class="btn btn-light" href="{{ route('customers.index') }}">إدارة العملاء</a>
@endsection

@section('content')
    <section class="card">
        <div class="section-head">
            <div>
                <h3>كل الاشتراكات</h3>
                <p>عرض سريع للكود، العميل، مدة الخطة، تاريخ الانتهاء، حالة الاشتراك وحالة السداد.</p>
            </div>
        </div>

        @if ($licenses->isEmpty())
            <div class="empty">لا توجد اشتراكات بعد. ابدأ من زر "إنشاء اشتراك".</div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>الكود</th>
                            <th>العميل</th>
                            <th>النسخ</th>
                            <th>الخطة</th>
                            <th>سعر النسخة</th>
                            <th>الإجمالي</th>
                            <th>الانتهاء</th>
                            <th>حالة الاشتراك</th>
                            <th>السداد</th>
                            <th>روابط الدفع</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($licenses as $license)
                            @php
                                $licenseStatusClass = match ($license->status) {
                                    'active' => 'badge-success',
                                    'pending' => 'badge-warning',
                                    'expired', 'suspended' => 'badge-danger',
                                    default => 'badge-muted',
                                };

                                $paymentStatusClass = match ($license->payment_status) {
                                    'paid' => 'badge-success',
                                    'pending', 'partial' => 'badge-warning',
                                    'failed' => 'badge-danger',
                                    default => 'badge-muted',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <a class="mono" href="{{ route('licenses.show', $license) }}">{{ $license->license_code }}</a>
                                </td>
                                <td>{{ $license->customer?->name ?? '-' }}</td>
                                <td>{{ number_format((int) ($license->copies_count ?: 1)) }}</td>
                                <td>{{ $license->plan_months }} شهر</td>
                                <td>{{ number_format((float) ($license->unit_price ?? 0), 2) }} {{ $license->currency }}</td>
                                <td>{{ number_format((float) $license->amount, 2) }} {{ $license->currency }}</td>
                                <td>{{ $license->expires_at?->format('Y-m-d') }}</td>
                                <td><span class="badge {{ $licenseStatusClass }}">{{ $license->status }}</span></td>
                                <td><span class="badge {{ $paymentStatusClass }}">{{ $license->payment_status }}</span></td>
                                <td>{{ number_format($license->payment_links_count) }}</td>
                                <td>
                                    <div class="table-actions">
                                        <a class="btn btn-light" href="{{ route('licenses.show', $license) }}">عرض</a>
                                        <a class="btn btn-secondary" href="{{ route('licenses.edit', $license) }}">تعديل</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('partials.pagination', ['paginator' => $licenses])
        @endif
    </section>
@endsection
