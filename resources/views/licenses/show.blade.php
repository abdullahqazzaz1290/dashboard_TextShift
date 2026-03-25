@extends('layouts.app')

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

    $recentVisits = $license->paymentLinks
        ->flatMap(function ($paymentLink) {
            return $paymentLink->visits->map(function ($visit) use ($paymentLink) {
                return ['visit' => $visit, 'paymentLink' => $paymentLink];
            });
        })
        ->sortByDesc(fn ($item) => $item['visit']->visited_at?->timestamp ?? 0)
        ->take(15);
    $deliveryMeta = $license->metadata['delivery'] ?? [];
    $deliveryCopies = collect($deliveryMeta['copies'] ?? []);
@endphp

@section('eyebrow', 'صفحة الاشتراك')
@section('title', $license->license_code)
@section('subtitle', 'من هنا تتابع حالة الترخيص، تنشئ روابط الدفع، وتراقب هل العميل فتح الرابط أو تم السداد أم لا.')

@section('actions')
    <a class="btn btn-light" href="{{ route('licenses.index') }}">كل الاشتراكات</a>
    <a class="btn btn-secondary" href="{{ route('licenses.edit', $license) }}">تعديل الاشتراك</a>
@endsection

@section('content')
    <section class="stats">
        <article class="stat-card">
            <span>العميل</span>
            <strong>{{ $license->customer?->name ?? '-' }}</strong>
            <small>{{ $license->customer?->company ?: 'بدون شركة' }}</small>
        </article>
        <article class="stat-card">
            <span>مدة الاشتراك</span>
            <strong>{{ $license->plan_months }} شهر</strong>
            <small>{{ $license->starts_at?->format('Y-m-d') }} حتى {{ $license->expires_at?->format('Y-m-d') }}</small>
        </article>
        <article class="stat-card">
            <span>النسخ</span>
            <strong>{{ number_format((int) ($license->copies_count ?: 1)) }}</strong>
            <small>{{ number_format((float) ($license->unit_price ?? 0), 2) }} {{ $license->currency }} لكل نسخة</small>
        </article>
        <article class="stat-card">
            <span>إجمالي البيع</span>
            <strong>{{ number_format((float) $license->amount, 2) }}</strong>
            <small>{{ $license->currency }} | <span class="badge {{ $paymentStatusClass }}">{{ $license->payment_status }}</span></small>
        </article>
        <article class="stat-card">
            <span>روابط الدفع</span>
            <strong>{{ number_format($license->paymentLinks->count()) }}</strong>
            <small>إجمالي الروابط المنشأة لهذا الاشتراك.</small>
        </article>
    </section>

    <section class="card" style="margin-top: 22px;">
        <div class="section-head">
            <div>
                <h3>نسخة العميل الجاهزة للتسليم</h3>
                <p>بعد الدفع يتم إنشاء ملف مضغوط واحد يحتوي كل النسخ المطلوبة، وكل نسخة تحمل كودها الخاص وملفات `jsxbin` فقط. عند أول تشغيل على جهاز العميل ينشئ السكربت ملفي `.license.dat` و`.license.lock` تلقائيًا، ثم يربط تلك النسخة بهذا الجهاز ويستخدم الملفين معًا للتحقق المحلي.</p>
            </div>
            <div class="card-actions">
                @if ($license->payment_status === 'paid')
                    <form method="POST" action="{{ route('licenses.delivery.rebuild', $license) }}">
                        @csrf
                        <button class="btn btn-light" type="submit">إعادة بناء النسخة</button>
                    </form>
                @endif

                @if ($license->jsxbin_package_path)
                    <a class="btn btn-primary" href="{{ route('licenses.delivery.download', $license) }}">تنزيل الملف المضغوط</a>
                @endif
            </div>
        </div>

        <div class="meta-grid">
            <div class="meta-item">
                <span>حالة الحزمة</span>
                <strong>{{ $deliveryMeta['status'] ?? 'not-built' }}</strong>
            </div>
            <div class="meta-item">
                <span>آخر بناء</span>
                <strong>{{ !empty($deliveryMeta['built_at']) ? \Illuminate\Support\Carbon::parse($deliveryMeta['built_at'])->format('Y-m-d H:i') : 'لم يتم بعد' }}</strong>
            </div>
            <div class="meta-item">
                <span>الملفات المحولة</span>
                <strong>{{ $deliveryMeta['compiled_files'] ?? 0 }}</strong>
            </div>
            <div class="meta-item">
                <span>اسم الأرشيف</span>
                <strong class="mono">{{ $deliveryMeta['archive_name'] ?? 'غير متاح بعد' }}</strong>
            </div>
            <div class="meta-item">
                <span>عدد النسخ داخل الأرشيف</span>
                <strong>{{ number_format((int) ($deliveryMeta['copies_count'] ?? $license->copies_count ?? 1)) }}</strong>
            </div>
        </div>

        @if (!empty($deliveryMeta['error']))
            <div class="flash flash-error" style="margin-top: 18px;">
                <strong>آخر خطأ في البناء:</strong>
                <div>{{ $deliveryMeta['error'] }}</div>
            </div>
        @elseif ($license->payment_status !== 'paid')
            <div class="empty" style="margin-top: 18px;">سيبدأ إنشاء النسخة تلقائيًا بمجرد تأكيد الدفع.</div>
        @elseif (!$license->jsxbin_package_path)
            <div class="empty" style="margin-top: 18px;">تم تأكيد الدفع لكن النسخة لم تُبن بعد. استخدم زر "إعادة بناء النسخة".</div>
        @endif

        @if ($deliveryCopies->isNotEmpty())
            <div class="table-wrap" style="margin-top: 18px;">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>المجلد داخل الـ zip</th>
                            <th>كود النسخة</th>
                            <th>ملف النسخة</th>
                            <th>رابط التنزيل</th>
                            <th>الانتهاء</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deliveryCopies as $copy)
                            @php
                                $copyDownloadUrl = !empty($copy['download_token'])
                                    ? route('licenses.delivery.public-copy', [
                                        'license' => $license,
                                        'copyNumber' => $copy['copy_number'],
                                        'token' => $copy['download_token'],
                                    ])
                                    : null;
                                $downloadStatus = $copy['download_status'] ?? 'ready';
                                $downloadedAt = !empty($copy['downloaded_at'])
                                    ? \Illuminate\Support\Carbon::parse($copy['downloaded_at'])->format('Y-m-d H:i')
                                    : null;
                            @endphp
                            <tr>
                                <td>{{ $copy['copy_number'] }}</td>
                                <td class="mono">{{ $copy['folder_name'] }}</td>
                                <td class="mono">{{ $copy['license_code'] }}</td>
                                <td class="mono">{{ $copy['archive_name'] ?? '-' }}</td>
                                <td>
                                    @if ($copyDownloadUrl)
                                        <div class="stack" style="gap: 8px;">
                                            <span class="badge {{ $downloadStatus === 'downloaded' ? 'badge-warning' : 'badge-success' }}">
                                                {{ $downloadStatus === 'downloaded' ? 'اُستخدم الرابط' : 'جاهز لمرة واحدة' }}
                                            </span>
                                            <button class="btn btn-secondary" type="button" onclick='copyText(@json($copyDownloadUrl))'>نسخ الرابط</button>
                                            <div class="mono" style="font-size: 12px; line-height: 1.6;">{{ $copyDownloadUrl }}</div>
                                            @if ($downloadedAt)
                                                <div style="font-size: 12px; color: var(--muted);">
                                                    تم التنزيل: {{ $downloadedAt }} | IP: {{ $copy['downloaded_ip'] ?: '-' }}
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        <span class="empty">غير متاح</span>
                                    @endif
                                </td>
                                <td>{{ $copy['expiry'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <div class="grid grid-2" style="margin-top: 22px;">
        <section class="card stack">
            <div class="section-head">
                <div>
                    <h3>بيانات الاشتراك</h3>
                    <p>تفاصيل أساسية تساعدك أثناء المتابعة والتسليم.</p>
                </div>
            </div>

            <div class="meta-grid">
                <div class="meta-item">
                    <span>كود الاشتراك</span>
                    <strong class="mono">{{ $license->license_code }}</strong>
                </div>
                <div class="meta-item">
                    <span>عدد النسخ</span>
                    <strong>{{ number_format((int) ($license->copies_count ?: 1)) }}</strong>
                </div>
                <div class="meta-item">
                    <span>سعر النسخة</span>
                    <strong>{{ number_format((float) ($license->unit_price ?? 0), 2) }} {{ $license->currency }}</strong>
                </div>
                <div class="meta-item">
                    <span>Device ID</span>
                    <strong>{{ ($license->copies_count ?? 1) > 1 ? 'سيُسجل لكل نسخة على حدة داخل .license.dat مع إنشاء ملف قفل .license.lock' : ($license->device_id ?: 'سيُسجل تلقائيًا داخل .license.dat مع إنشاء ملف قفل .license.lock عند أول تشغيل') }}</strong>
                </div>
                <div class="meta-item">
                    <span>حالة الاشتراك</span>
                    <strong><span class="badge {{ $licenseStatusClass }}">{{ $license->status }}</span></strong>
                </div>
                <div class="meta-item">
                    <span>تاريخ السداد</span>
                    <strong>{{ $license->paid_at?->format('Y-m-d H:i') ?: 'لم يتم التأكيد بعد' }}</strong>
                </div>
            </div>

            <div class="field">
                <label>ملاحظات داخلية</label>
                <div class="empty">{{ $license->notes ?: 'لا توجد ملاحظات.' }}</div>
            </div>

            <div class="field">
                <label>ملاحظات التسليم</label>
                <div class="empty">{{ $license->delivery_notes ?: 'لا توجد ملاحظات تسليم حتى الآن.' }}</div>
            </div>
        </section>

        <section class="card">
            <div class="section-head">
                <div>
                    <h3>إنشاء رابط دفع جديد</h3>
                    <p>الرابط العام الذي سترسله للعميل سيسجل كل ضغطة ثم يحوّل للينك النهائي الذي تختاره.</p>
                </div>
            </div>

            @if ($paymentMethods->isEmpty())
                <div class="empty">
                    لا توجد أي طريقة دفع مفعلة حاليًا.
                    <a href="{{ route('payment-methods.index') }}">أضف طريقة دفع أولًا</a>.
                </div>
            @else
                <form method="POST" action="{{ route('payment-links.store', $license) }}" class="stack">
                    @csrf

                    <div class="form-grid">
                        <div class="field">
                            <label for="payment_method_id">طريقة الدفع</label>
                            <select id="payment_method_id" name="payment_method_id" required>
                                <option value="">اختر الطريقة</option>
                                @foreach ($paymentMethods as $method)
                                    <option value="{{ $method->id }}">{{ $method->name }}{{ $method->account_identifier ? ' - ' . $method->account_identifier : '' }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="field">
                            <label for="title">عنوان الرابط</label>
                            <input id="title" type="text" name="title" value="{{ old('title') }}" placeholder="مثال: دفعة اشتراك شهر 3">
                        </div>

                        <div class="field full">
                            <label for="target_url">اللينك النهائي</label>
                            <input id="target_url" type="text" name="target_url" value="{{ old('target_url') }}" placeholder="لينك InstaPay أو رابط تحويل أو short link" required>
                        </div>

                        <div class="field">
                            <label for="amount">المبلغ</label>
                            <input id="amount" type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $license->amount) }}" required>
                        </div>

                        <div class="field">
                            <label for="currency">العملة</label>
                            <input id="currency" type="text" name="currency" value="{{ old('currency', $license->currency) }}" required>
                        </div>

                        <div class="field full">
                            <label for="notes">ملاحظات للرابط</label>
                            <textarea id="notes" name="notes" placeholder="مثال: أرسلنا الرابط عبر واتساب">{{ old('notes') }}</textarea>
                        </div>
                    </div>

                    <div class="inline">
                        <button class="btn btn-primary" type="submit">إنشاء الرابط</button>
                        <span class="hint">بعد الإنشاء سيظهر لك الرابط العام الجاهز للمشاركة والنسخ.</span>
                    </div>
                </form>
            @endif
        </section>
    </div>

    <section class="card" style="margin-top: 22px;">
        <div class="section-head">
            <div>
                <h3>روابط الدفع الحالية</h3>
                <p>تابع كل رابط: هل تم فتحه؟ وهل تم دفعه؟ وكم كان المبلغ المؤكد؟</p>
            </div>
        </div>

        @if ($license->paymentLinks->isEmpty())
            <div class="empty">لا توجد روابط دفع على هذا الاشتراك بعد.</div>
        @else
            <div class="stack">
                @foreach ($license->paymentLinks as $paymentLink)
                    @php
                        $linkStatusClass = match ($paymentLink->status) {
                            'paid' => 'badge-success',
                            'pending' => 'badge-warning',
                            'cancelled', 'expired' => 'badge-danger',
                            default => 'badge-muted',
                        };
                        $publicUrl = route('payment-links.public', $paymentLink->slug);
                    @endphp
                    <article class="card" style="padding: 18px;">
                        <div class="section-head">
                            <div>
                                <h4>{{ $paymentLink->title }}</h4>
                                <p>{{ $paymentLink->paymentMethod?->name ?? '-' }} | <span class="badge {{ $linkStatusClass }}">{{ $paymentLink->status }}</span></p>
                            </div>
                            <div class="card-actions">
                                <a class="btn btn-light" target="_blank" href="{{ $publicUrl }}">فتح الرابط العام</a>
                                <button class="btn btn-secondary" type="button" onclick='copyText(@json($publicUrl))'>نسخ الرابط</button>
                            </div>
                        </div>

                        <div class="link-box">
                            <strong>الرابط العام:</strong>
                            <span class="mono">{{ $publicUrl }}</span>
                        </div>

                        <div class="meta-grid" style="margin-top: 16px;">
                            <div class="meta-item">
                                <span>المبلغ المطلوب</span>
                                <strong>{{ number_format((float) $paymentLink->amount, 2) }} {{ $paymentLink->currency }}</strong>
                            </div>
                            <div class="meta-item">
                                <span>المبلغ المؤكد</span>
                                <strong>{{ $paymentLink->paid_amount !== null ? number_format((float) $paymentLink->paid_amount, 2) . ' ' . $paymentLink->currency : 'غير مؤكد' }}</strong>
                            </div>
                            <div class="meta-item">
                                <span>عدد الضغطات</span>
                                <strong>{{ number_format($paymentLink->clicked_count) }}</strong>
                            </div>
                            <div class="meta-item">
                                <span>آخر زيارة</span>
                                <strong>{{ $paymentLink->last_clicked_at?->diffForHumans() ?: 'لا توجد زيارات بعد' }}</strong>
                            </div>
                        </div>

                        <div class="grid grid-2" style="margin-top: 16px;">
                            <form method="POST" action="{{ route('payment-links.mark-paid', $paymentLink) }}" class="card" style="padding: 16px;">
                                @csrf
                                <div class="field">
                                    <label for="paid_amount_{{ $paymentLink->id }}">تأكيد الدفع</label>
                                    <input id="paid_amount_{{ $paymentLink->id }}" type="number" step="0.01" min="0" name="paid_amount" value="{{ $paymentLink->paid_amount ?? $paymentLink->amount }}">
                                </div>
                                <div class="inline" style="margin-top: 12px;">
                                    <button class="btn btn-success" type="submit">تحديد كمدفوع</button>
                                    @if ($paymentLink->status === 'paid')
                                        <span class="hint">تم تأكيده بالفعل بتاريخ {{ $paymentLink->paid_at?->format('Y-m-d H:i') }}</span>
                                    @endif
                                </div>
                            </form>

                            <form method="POST" action="{{ route('payment-links.mark-pending', $paymentLink) }}" class="card" style="padding: 16px;">
                                @csrf
                                <div class="field">
                                    <label>إرجاع للحالة المعلقة</label>
                                    <div class="empty">استخدم هذا الزر إذا كان الرابط ما زال قيد الانتظار أو تريد إعادة التحقق من التحويل.</div>
                                </div>
                                <div class="inline" style="margin-top: 12px;">
                                    <button class="btn btn-light" type="submit">إرجاع إلى pending</button>
                                </div>
                            </form>
                        </div>

                        <details style="margin-top: 16px;">
                            <summary>تعديل بيانات هذا الرابط</summary>

                            <form method="POST" action="{{ route('payment-links.update', $paymentLink) }}" class="stack" style="margin-top: 14px;">
                                @csrf
                                @method('PUT')

                                <div class="form-grid">
                                    <div class="field">
                                        <label for="title_{{ $paymentLink->id }}">العنوان</label>
                                        <input id="title_{{ $paymentLink->id }}" type="text" name="title" value="{{ $paymentLink->title }}" required>
                                    </div>

                                    <div class="field">
                                        <label for="status_{{ $paymentLink->id }}">الحالة</label>
                                        <select id="status_{{ $paymentLink->id }}" name="status" required>
                                            @foreach (['pending', 'paid', 'expired', 'cancelled'] as $status)
                                                <option value="{{ $status }}" @selected($paymentLink->status === $status)>{{ $status }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="field full">
                                        <label for="target_url_{{ $paymentLink->id }}">اللينك النهائي</label>
                                        <input id="target_url_{{ $paymentLink->id }}" type="text" name="target_url" value="{{ $paymentLink->target_url }}" required>
                                    </div>

                                    <div class="field">
                                        <label for="amount_{{ $paymentLink->id }}">المبلغ</label>
                                        <input id="amount_{{ $paymentLink->id }}" type="number" step="0.01" min="0" name="amount" value="{{ $paymentLink->amount }}" required>
                                    </div>

                                    <div class="field">
                                        <label for="currency_{{ $paymentLink->id }}">العملة</label>
                                        <input id="currency_{{ $paymentLink->id }}" type="text" name="currency" value="{{ $paymentLink->currency }}" required>
                                    </div>

                                    <div class="field full">
                                        <label for="notes_{{ $paymentLink->id }}">الملاحظات</label>
                                        <textarea id="notes_{{ $paymentLink->id }}" name="notes">{{ $paymentLink->notes }}</textarea>
                                    </div>
                                </div>

                                <div class="inline">
                                    <button class="btn btn-primary" type="submit">حفظ الرابط</button>
                                </div>
                            </form>
                        </details>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="card" style="margin-top: 22px;">
        <div class="section-head">
            <div>
                <h3>آخر زيارات روابط الدفع</h3>
                <p>تتبّع من فتح الرابط ومتى تم الوصول إليه. حالة الدفع نفسها تظل يدوية ما لم تربط مزود دفع فعلي.</p>
            </div>
        </div>

        @if ($recentVisits->isEmpty())
            <div class="empty">لا توجد زيارات مسجلة لهذا الاشتراك حتى الآن.</div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>الوقت</th>
                            <th>الرابط</th>
                            <th>الطريقة</th>
                            <th>IP</th>
                            <th>المرجع</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentVisits as $item)
                            <tr>
                                <td>{{ $item['visit']->visited_at?->format('Y-m-d H:i') }}</td>
                                <td>{{ $item['paymentLink']->title }}</td>
                                <td>{{ $item['paymentLink']->paymentMethod?->name ?? '-' }}</td>
                                <td class="mono">{{ $item['visit']->ip_address ?: '-' }}</td>
                                <td>{{ $item['visit']->referrer ?: 'Direct' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
