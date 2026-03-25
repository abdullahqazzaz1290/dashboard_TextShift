@extends('layouts.app')

@section('eyebrow', 'نظرة سريعة')
@section('title', 'لوحة التحكم')
@section('subtitle', 'ملخص سريع لحجم العملاء، الاشتراكات، المدفوعات وروابط الدفع التي تمت مشاركتها.')

@section('actions')
    <a class="btn btn-primary" href="{{ route('licenses.create') }}">اشتراك جديد</a>
    <a class="btn btn-secondary" href="{{ route('reports.index') }}">التقارير</a>
@endsection

@section('content')
    <section class="stats">
        <article class="stat-card">
            <span>إجمالي العملاء</span>
            <strong>{{ number_format($stats['customers']) }}</strong>
            <small>عدد العملاء المسجلين داخل النظام.</small>
        </article>
        <article class="stat-card">
            <span>إجمالي الاشتراكات</span>
            <strong>{{ number_format($stats['licenses']) }}</strong>
            <small>كل الاشتراكات التي تم إنشاؤها.</small>
        </article>
        <article class="stat-card">
            <span>إجمالي النسخ</span>
            <strong>{{ number_format($stats['copies']) }}</strong>
            <small>عدد النسخ المباعة عبر كل الاشتراكات.</small>
        </article>
        <article class="stat-card">
            <span>اشتراكات نشطة</span>
            <strong>{{ number_format($stats['active_licenses']) }}</strong>
            <small>ما زالت سارية حتى اليوم.</small>
        </article>
        <article class="stat-card">
            <span>اشتراكات منتهية</span>
            <strong>{{ number_format($stats['expired_licenses']) }}</strong>
            <small>انتهت وتحتاج تجديدًا أو متابعة.</small>
        </article>
        <article class="stat-card">
            <span>مدفوعات معلقة</span>
            <strong>{{ number_format($stats['pending_payments']) }}</strong>
            <small>اشتراكات لم يتم تحصيلها بالكامل بعد.</small>
        </article>
        <article class="stat-card">
            <span>إيراد مدفوع</span>
            <strong>{{ number_format((float) $stats['paid_revenue'], 2) }}</strong>
            <small>إجمالي المبالغ المؤكدة الدفع.</small>
        </article>
        <article class="stat-card">
            <span>طرق الدفع المفعلة</span>
            <strong>{{ number_format($stats['payment_methods']) }}</strong>
            <small>الطرق المتاحة للروابط الجديدة.</small>
        </article>
        <article class="stat-card">
            <span>ضغطات الروابط</span>
            <strong>{{ number_format($stats['payment_link_clicks']) }}</strong>
            <small>كل الزيارات المسجلة على روابط الدفع.</small>
        </article>
    </section>

    <div class="grid grid-2" style="margin-top: 22px;">
        <section class="card">
            <div class="section-head">
                <div>
                    <h3>أحدث الاشتراكات</h3>
                    <p>آخر الاشتراكات التي أضفتها مع تواريخ الانتهاء.</p>
                </div>
                <a class="btn btn-light" href="{{ route('licenses.index') }}">عرض الكل</a>
            </div>

            @if ($recentLicenses->isEmpty())
                <div class="empty">لم يتم إنشاء أي اشتراك بعد. ابدأ بإنشاء عميل ثم أضف أول اشتراك.</div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>الكود</th>
                                <th>العميل</th>
                                <th>النسخ</th>
                                <th>المدة</th>
                                <th>سعر النسخة</th>
                                <th>الإجمالي</th>
                                <th>الانتهاء</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentLicenses as $license)
                                @php
                                    $statusClass = match ($license->status) {
                                        'active' => 'badge-success',
                                        'pending' => 'badge-warning',
                                        'expired', 'suspended' => 'badge-danger',
                                        default => 'badge-muted',
                                    };
                                @endphp
                                <tr>
                                    <td><a class="mono" href="{{ route('licenses.show', $license) }}">{{ $license->license_code }}</a></td>
                                    <td>{{ $license->customer?->name ?? '-' }}</td>
                                    <td>{{ number_format((int) ($license->copies_count ?: 1)) }}</td>
                                    <td>{{ $license->plan_months }} شهر</td>
                                    <td>{{ number_format((float) ($license->unit_price ?? 0), 2) }} {{ $license->currency }}</td>
                                    <td>{{ number_format((float) $license->amount, 2) }} {{ $license->currency }}</td>
                                    <td>{{ $license->expires_at?->format('Y-m-d') }}</td>
                                    <td><span class="badge {{ $statusClass }}">{{ $license->status }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card">
            <div class="section-head">
                <div>
                    <h3>أحدث روابط الدفع</h3>
                    <p>آخر الروابط المرسلة للعملاء وحالة التفاعل معها.</p>
                </div>
            </div>

            @if ($recentLinks->isEmpty())
                <div class="empty">لا توجد روابط دفع حتى الآن. افتح أي اشتراك وأنشئ منه أول رابط دفع.</div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>العنوان</th>
                                <th>العميل</th>
                                <th>الطريقة</th>
                                <th>المبلغ</th>
                                <th>الضغطات</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentLinks as $link)
                                @php
                                    $statusClass = match ($link->status) {
                                        'paid' => 'badge-success',
                                        'pending' => 'badge-warning',
                                        'cancelled', 'expired' => 'badge-danger',
                                        default => 'badge-muted',
                                    };
                                @endphp
                                <tr>
                                    <td>
                                        <div>{{ $link->title }}</div>
                                        <div class="muted mono">{{ $link->slug }}</div>
                                    </td>
                                    <td>{{ $link->license?->customer?->name ?? '-' }}</td>
                                    <td>{{ $link->paymentMethod?->name ?? '-' }}</td>
                                    <td>{{ number_format((float) $link->amount, 2) }} {{ $link->currency }}</td>
                                    <td>{{ number_format($link->clicked_count) }}</td>
                                    <td><span class="badge {{ $statusClass }}">{{ $link->status }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
