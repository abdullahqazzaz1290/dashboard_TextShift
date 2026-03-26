import { escapeHtml } from '../lib/formatters.js';

const toneClasses = {
    slate: 'from-slate-950 to-slate-800 text-white',
    mint: 'from-emerald-500 to-teal-500 text-white',
    amber: 'from-amber-400 to-orange-500 text-slate-950',
    sky: 'from-sky-500 to-cyan-500 text-white',
    rose: 'from-rose-500 to-pink-500 text-white',
};

export function renderCard({ label, value, meta = '', tone = 'slate' }) {
    const toneClass = toneClasses[tone] || toneClasses.slate;

    return `
        <article class="rounded-[28px] border border-white/60 bg-white/75 p-5 shadow-[0_24px_80px_rgba(15,23,42,0.08)] backdrop-blur">
            <div class="inline-flex rounded-2xl bg-gradient-to-br px-3 py-2 text-xs font-semibold uppercase tracking-[0.22em] ${toneClass}">
                ${escapeHtml(label)}
            </div>
            <div class="mt-5 text-4xl font-semibold tracking-tight text-slate-950">
                ${escapeHtml(value)}
            </div>
            <p class="mt-3 text-sm leading-6 text-slate-500">
                ${escapeHtml(meta)}
            </p>
        </article>
    `;
}
