import { escapeHtml } from '../lib/formatters.js';

export function renderTopbar({ title, description, pluginVersion, loading, notice }) {
    return `
        <header class="rounded-[32px] border border-white/60 bg-white/70 px-6 py-5 shadow-[0_24px_80px_rgba(15,23,42,0.08)] backdrop-blur">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">Operations Console</p>
                    <h2 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">${escapeHtml(title)}</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">${escapeHtml(description)}</p>
                </div>
                <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center">
                    <div class="rounded-2xl border border-slate-200 bg-slate-950 px-4 py-3 text-white">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-300">Plugin Version</p>
                        <p class="mt-1 text-lg font-semibold">${escapeHtml(pluginVersion)}</p>
                    </div>
                    <button
                        type="button"
                        data-action="refresh-data"
                        class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-950 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg"
                    >
                        ${loading ? 'Refreshing…' : 'Refresh Data'}
                    </button>
                </div>
            </div>
            ${notice ? `
                <div class="mt-4 rounded-2xl border px-4 py-3 text-sm ${
                    notice.type === 'error'
                        ? 'border-rose-200 bg-rose-50 text-rose-700'
                        : 'border-emerald-200 bg-emerald-50 text-emerald-700'
                }">
                    ${escapeHtml(notice.message)}
                </div>
            ` : ''}
        </header>
    `;
}
