@extends('layouts.app')

@php
    $totalRevenue = (float) $revenueByMethod->sum('total_revenue');
    $totalClicks = (int) $clicksByMethod->sum('total_clicks');
    $licenseTotal = max(1, (int) $licenseStatusCounts->sum());
    $paymentTotal = max(1, (int) $paymentStatusCounts->sum());
    $topMethod = $topMethods->first();
@endphp

@section('eyebrow', 'تقارير المبيعات')
@section('title', 'التقارير')
@section('subtitle', 'متابعة سريعة للإيراد، توزيع الحالات، أكثر طرق الدفع استخدامًا، وآخر الزيارات المسجلة على روابط الدفع.')

@section('actions')
    <a class="btn btn-primary" href="{{ route('licenses.create') }}">اشتراك جديد</a>
@endsection

@section('content')
    <section class="stats">
        <article class="stat-card">
            <span>إجمالي الإيراد المؤكد</span>
            <strong>{{ number_format($totalRevenue, 2) }}</strong>
            <small>من الروابط التي تم تعليمها كمدفوعة.</small>
        </article>
        <article class="stat-card">
            <span>إجمالي الضغطات</span>
            <strong>{{ number_format($totalClicks) }}</strong>
            <small>كل الزيارات المسجلة على روابط الدفع.</small>
        </article>
        <article class="stat-card">
            <span>إجمالي النسخ المباعة</span>
            <strong>{{ number_format($copiesSold) }}</strong>
            <small>عدد النسخ عبر كل الاشتراكات المسجلة.</small>
        </article>
        <article class="stat-card">
            <span>متوسط سعر النسخة</span>
            <strong>{{ number_format($averageCopyPrice, 2) }}</strong>
            <small>متوسط سعر النسخة المحسوب من إجمالي الاشتراكات الحالية.</small>
        </article>
        <article class="stat-card">
            <span>أكثر طريقة مستخدمة</span>
            <strong>{{ $topMethod?->name ?? 'لا توجد بيانات' }}</strong>
            <small>{{ $topMethod ? number_format($topMethod->payment_links_count) . ' رابط' : 'ابدأ بإنشاء روابط دفع' }}</small>
        </article>
        <article class="stat-card">
            <span>آخر تحديث</span>
            <strong>{{ now()->format('Y-m-d') }}</strong>
            <small>التقرير حي ويقرأ البيانات الحالية مباشرة من قاعدة البيانات.</small>
        </article>
    </section>

    <div class="grid grid-2" style="margin-top: 22px;">
        <section class="card">
            <div class="section-head">
                <div>
                    <h3>الإيراد حسب طريقة الدفع</h3>
                    <p>المبالغ المؤكدة لكل وسيلة دفع.</p>
                </div>
            </div>

            @if ($revenueByMethod->isEmpty())
                <div class="empty">لا توجد أي دفعات مؤكدة حتى الآن.</div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>طريقة الدفع</th>
                                <th>الإيراد</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($revenueByMethod as $row)
                                <tr>
                                    <td>{{ $row->paymentMethod?->name ?? 'Unknown' }}</td>
                                    <td>{{ number_format((float) $row->total_revenue, 2) }}</td>
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
                    <h3>ضغطات الروابط حسب الطريقة</h3>
                    <p>تعرف أي وسيلة دفع يتم التفاعل معها أكثر.</p>
                </div>
            </div>

            @if ($clicksByMethod->isEmpty())
                <div class="empty">لا توجد ضغطات مسجلة حتى الآن.</div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>طريقة الدفع</th>
                                <th>الضغطات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($clicksByMethod as $row)
                                <tr>
                                    <td>{{ $row->name }}</td>
                                    <td>{{ number_format($row->total_clicks) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    <div class="grid grid-2" style="margin-top: 22px;">
        <section class="card">
            <div class="section-head">
                <div>
                    <h3>توزيع حالات الاشتراكات</h3>
                    <p>صورة سريعة للاشتراكات النشطة والمنتهية والمعلقة.</p>
                </div>
            </div>

            @if ($licenseStatusCounts->isEmpty())
                <div class="empty">لا توجد بيانات حالات بعد.</div>
            @else
                <div class="mini-bars">
                    @foreach ($licenseStatusCounts as $status => $count)
                        <div class="mini-bar">
                            <div class="inline" style="justify-content: space-between;">
                                <strong>{{ $status }}</strong>
                                <span class="muted">{{ $count }}</span>
                            </div>
                            <div class="track">
                                <div class="fill" style="width: {{ round(($count / $licenseTotal) * 100, 2) }}%;"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="card">
            <div class="section-head">
                <div>
                    <h3>توزيع حالات السداد</h3>
                    <p>تعرّف أين تقف التحصيلات الآن.</p>
                </div>
            </div>

            @if ($paymentStatusCounts->isEmpty())
                <div class="empty">لا توجد بيانات سداد بعد.</div>
            @else
                <div class="mini-bars">
                    @foreach ($paymentStatusCounts as $status => $count)
                        <div class="mini-bar">
                            <div class="inline" style="justify-content: space-between;">
                                <strong>{{ $status }}</strong>
                                <span class="muted">{{ $count }}</span>
                            </div>
                            <div class="track">
                                <div class="fill" style="width: {{ round(($count / $paymentTotal) * 100, 2) }}%;"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    <div class="grid grid-2" style="margin-top: 22px;">
        <section class="card">
            <div class="section-head">
                <div>
                    <h3>أكثر الطرق استخدامًا</h3>
                    <p>عدد الروابط التي تم بناؤها على كل وسيلة دفع.</p>
                </div>
            </div>

            @if ($topMethods->isEmpty())
                <div class="empty">لا توجد طرق دفع أو روابط كافية لإظهار ترتيب الاستخدام.</div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>الطريقة</th>
                                <th>عدد الروابط</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($topMethods as $method)
                                <tr>
                                    <td>{{ $method->name }}</td>
                                    <td>{{ number_format($method->payment_links_count) }}</td>
                                    <td>
                                        <span class="badge {{ $method->is_active ? 'badge-success' : 'badge-muted' }}">
                                            {{ $method->is_active ? 'active' : 'inactive' }}
                                        </span>
                                    </td>
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
                    <h3>آخر زيارات الروابط</h3>
                    <p>من دخل إلى اللينك العام ومتى.</p>
                </div>
            </div>

            @if ($recentVisits->isEmpty())
                <div class="empty">لا توجد زيارات مسجلة حتى الآن.</div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>الوقت</th>
                                <th>العميل</th>
                                <th>الطريقة</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentVisits as $visit)
                                <tr>
                                    <td>{{ $visit->visited_at?->format('Y-m-d H:i') }}</td>
                                    <td>{{ $visit->paymentLink?->license?->customer?->name ?? '-' }}</td>
                                    <td>{{ $visit->paymentLink?->paymentMethod?->name ?? '-' }}</td>
                                    <td class="mono">{{ $visit->ip_address ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
