<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'لوحة التحكم') - Certificate Generator</title>
    <style>
        :root {
            --bg: #f3efe6;
            --bg-soft: #fbf8f1;
            --card: #fffdf9;
            --ink: #1b2a2f;
            --muted: #607276;
            --line: #ddd5c7;
            --brand: #0f7d7e;
            --brand-deep: #0a5c5d;
            --accent: #d48a39;
            --accent-soft: #f7e3cc;
            --success: #21714f;
            --success-soft: #dff3e8;
            --warning: #9b641f;
            --warning-soft: #fbedd7;
            --danger: #a43d3d;
            --danger-soft: #f9dddd;
            --shadow: 0 18px 45px rgba(24, 39, 35, 0.08);
            --radius: 22px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: var(--ink);
            font-family: "IBM Plex Sans Arabic", "Segoe UI", Tahoma, sans-serif;
            background:
                radial-gradient(circle at top right, rgba(15, 125, 126, 0.11), transparent 32%),
                radial-gradient(circle at bottom left, rgba(212, 138, 57, 0.14), transparent 28%),
                linear-gradient(180deg, #f6f1e8 0%, #f2f0eb 100%);
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .shell {
            max-width: 1320px;
            margin: 0 auto;
            padding: 28px 18px 72px;
        }

        .masthead {
            background: linear-gradient(135deg, rgba(15, 125, 126, 0.95), rgba(11, 92, 93, 0.95));
            color: #f8fbfb;
            padding: 28px;
            border-radius: 30px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .masthead::after {
            content: "";
            position: absolute;
            inset: auto -20px -35px auto;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            pointer-events: none;
        }

        .masthead > * {
            position: relative;
            z-index: 1;
        }

        .eyebrow {
            margin: 0 0 8px;
            color: rgba(248, 251, 251, 0.78);
            font-size: 14px;
        }

        .masthead h1 {
            margin: 0;
            font-size: clamp(28px, 4vw, 44px);
            line-height: 1.1;
        }

        .masthead p {
            margin: 12px 0 0;
            max-width: 760px;
            color: rgba(248, 251, 251, 0.86);
            line-height: 1.7;
        }

        .nav {
            margin-top: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .nav a {
            padding: 11px 16px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.1);
            color: rgba(248, 251, 251, 0.92);
            transition: 0.2s ease;
        }

        .nav a:hover,
        .nav a.active {
            background: #fff8f0;
            color: var(--brand-deep);
        }

        .page-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
            margin: 28px 0 18px;
        }

        .page-head h2 {
            margin: 0;
            font-size: clamp(24px, 3vw, 34px);
        }

        .page-head p {
            margin: 8px 0 0;
            color: var(--muted);
            line-height: 1.7;
        }

        .page-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .card {
            background: rgba(255, 253, 249, 0.9);
            border: 1px solid rgba(221, 213, 199, 0.9);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 22px;
            backdrop-filter: blur(5px);
        }

        .stack {
            display: grid;
            gap: 18px;
        }

        .grid {
            display: grid;
            gap: 18px;
        }

        .grid-2 {
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        }

        .grid-3 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
        }

        .stat-card {
            padding: 18px;
            border-radius: 20px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(248, 245, 238, 0.96));
            border: 1px solid rgba(221, 213, 199, 0.85);
        }

        .stat-card span {
            display: block;
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .stat-card strong {
            display: block;
            font-size: 30px;
            line-height: 1;
            margin-bottom: 6px;
        }

        .stat-card small {
            color: var(--muted);
        }

        .btn {
            border: 0;
            border-radius: 14px;
            padding: 11px 16px;
            font: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            background: var(--brand);
            color: #fff;
        }

        .btn-secondary {
            background: var(--accent-soft);
            color: #704114;
        }

        .btn-light {
            background: #f4efe6;
            color: var(--ink);
            border: 1px solid var(--line);
        }

        .btn-success {
            background: var(--success);
            color: #fff;
        }

        .btn-danger {
            background: var(--danger);
            color: #fff;
        }

        .btn-link {
            color: var(--brand-deep);
            padding: 0;
            background: transparent;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            text-align: right;
            padding: 14px 12px;
            border-bottom: 1px solid rgba(221, 213, 199, 0.75);
            vertical-align: top;
        }

        th {
            font-size: 13px;
            color: var(--muted);
            font-weight: 700;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .badge-muted {
            background: #ece8df;
            color: #526064;
        }

        .badge-success {
            background: var(--success-soft);
            color: var(--success);
        }

        .badge-warning {
            background: var(--warning-soft);
            color: var(--warning);
        }

        .badge-danger {
            background: var(--danger-soft);
            color: var(--danger);
        }

        .kicker {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 8px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .form-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .field {
            display: grid;
            gap: 8px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        label {
            font-weight: 700;
            font-size: 14px;
        }

        input,
        select,
        textarea {
            width: 100%;
            border: 1px solid var(--line);
            background: #fffdf8;
            color: var(--ink);
            border-radius: 14px;
            padding: 12px 14px;
            font: inherit;
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: 2px solid rgba(15, 125, 126, 0.18);
            border-color: rgba(15, 125, 126, 0.7);
        }

        .inline {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .section-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }

        .section-head h3,
        .section-head h4 {
            margin: 0;
        }

        .section-head p {
            margin: 6px 0 0;
            color: var(--muted);
        }

        .hint,
        .muted {
            color: var(--muted);
        }

        .empty {
            padding: 20px;
            border-radius: 18px;
            background: #f8f3eb;
            color: var(--muted);
            border: 1px dashed var(--line);
        }

        .flash {
            margin-top: 18px;
            padding: 16px 18px;
            border-radius: 18px;
            border: 1px solid transparent;
        }

        .flash-success {
            background: var(--success-soft);
            color: var(--success);
            border-color: rgba(33, 113, 79, 0.18);
        }

        .flash-error {
            background: var(--danger-soft);
            color: var(--danger);
            border-color: rgba(164, 61, 61, 0.18);
        }

        .error-list {
            margin: 10px 0 0;
            padding: 0 18px 0 0;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
        }

        .meta-item {
            padding: 16px;
            border-radius: 18px;
            background: #fbf8f1;
            border: 1px solid rgba(221, 213, 199, 0.75);
        }

        .meta-item span {
            display: block;
            color: var(--muted);
            margin-bottom: 6px;
            font-size: 13px;
        }

        .meta-item strong {
            display: block;
            font-size: 18px;
        }

        .link-box {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            padding: 12px 14px;
            border-radius: 16px;
            background: #f8f4eb;
            border: 1px solid var(--line);
            word-break: break-all;
        }

        .mini-bars {
            display: grid;
            gap: 12px;
        }

        .mini-bar {
            display: grid;
            gap: 8px;
        }

        .mini-bar .track {
            height: 10px;
            border-radius: 999px;
            background: #ece8df;
            overflow: hidden;
        }

        .mini-bar .fill {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--brand), var(--accent));
        }

        details {
            border: 1px solid rgba(221, 213, 199, 0.85);
            border-radius: 18px;
            padding: 16px;
            background: #fffaf2;
        }

        summary {
            cursor: pointer;
            font-weight: 700;
        }

        .table-actions,
        .card-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 18px;
        }

        .pagination .page-indicator {
            color: var(--muted);
        }

        .mono {
            font-family: "SFMono-Regular", Consolas, monospace;
        }

        @media (max-width: 980px) {
            .grid-2,
            .grid-3,
            .form-grid,
            .form-grid-3 {
                grid-template-columns: 1fr;
            }

            .page-head {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="shell">
        <header class="masthead">
            <p class="eyebrow">لوحة تشغيل ومبيعات للاشتراكات والتراخيص</p>
            <h1>Certificate Generator Sales Hub</h1>
            <p>أنشئ اشتراكًا جديدًا، حدّد مدة الانتهاء، اصنع رابط الدفع المناسب، وتابع الضغطات والسداد والتقارير من مكان واحد.</p>

            <nav class="nav">
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">الرئيسية</a>
                <a href="{{ route('licenses.index') }}" class="{{ request()->routeIs('licenses.*') ? 'active' : '' }}">الاشتراكات</a>
                <a href="{{ route('customers.index') }}" class="{{ request()->routeIs('customers.*') ? 'active' : '' }}">العملاء</a>
                <a href="{{ route('payment-methods.index') }}" class="{{ request()->routeIs('payment-methods.*') ? 'active' : '' }}">طرق الدفع</a>
                <a href="{{ route('reports.index') }}" class="{{ request()->routeIs('reports.*') ? 'active' : '' }}">التقارير</a>
            </nav>
        </header>

        @if (session('status'))
            <div class="flash flash-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="flash flash-error">
                <strong>فيه بيانات محتاجة مراجعة قبل الحفظ.</strong>
                <ul class="error-list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="page-head">
            <div>
                <p class="kicker">@yield('eyebrow', 'لوحة المبيعات')</p>
                <h2>@yield('title', 'لوحة التحكم')</h2>
                @hasSection('subtitle')
                    <p>@yield('subtitle')</p>
                @endif
            </div>

            <div class="page-actions">
                @yield('actions')
            </div>
        </div>

        @yield('content')
    </div>

    <script>
        function copyText(value) {
            if (!value) return;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value);
                return;
            }

            var input = document.createElement('textarea');
            input.value = value;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
        }
    </script>
    @stack('scripts')
</body>
</html>
