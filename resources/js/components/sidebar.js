import { escapeHtml } from '../lib/formatters.js';

export function renderSidebar({ currentPage, items, stats }) {
    return `
        <aside class="rounded-[32px] border border-white/60 bg-slate-950 px-5 py-6 text-slate-100 shadow-[0_40px_120px_rgba(15,23,42,0.24)]">
            <div class="rounded-[28px] border border-white/10 bg-white/5 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">TextShift</p>
                <h1 class="mt-3 text-2xl font-semibold tracking-tight">SaaS Licensing</h1>
                <p class="mt-2 text-sm leading-6 text-slate-400">MySQL-based licensing system for subscriptions, payments, and plugin updates.</p>
            </div>

            <nav class="mt-6 space-y-2">
                ${items.map((item) => `
                    <button
                        type="button"
                        data-page="${escapeHtml(item.id)}"
                        class="flex w-full items-center gap-3 rounded-2xl px-4 py-3 text-left transition ${
                            currentPage === item.id
                                ? 'bg-white text-slate-950 shadow-lg'
                                : 'bg-transparent text-slate-200 hover:bg-white/10'
                        }"
                    >
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl ${
                            currentPage === item.id ? 'bg-slate-950 text-white' : 'bg-white/10 text-slate-200'
                        }">${escapeHtml(item.short)}</span>
                        <span>
                            <span class="block text-sm font-semibold">${escapeHtml(item.label)}</span>
                            <span class="block text-xs text-slate-400">${escapeHtml(item.hint)}</span>
                        </span>
                    </button>
                `).join('')}
            </nav>

            <div class="mt-8 rounded-[28px] border border-white/10 bg-white/5 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Live Totals</p>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-400">Licenses</dt>
                        <dd class="font-semibold text-white">${escapeHtml(stats.totalLicenses)}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-400">Devices</dt>
                        <dd class="font-semibold text-white">${escapeHtml(stats.totalDevices)}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-400">Plans</dt>
                        <dd class="font-semibold text-white">${escapeHtml(stats.totalPlans)}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-400">Payments</dt>
                        <dd class="font-semibold text-white">${escapeHtml(stats.totalPayments)}</dd>
                    </div>
                </dl>
            </div>
        </aside>
    `;
}
