import { escapeHtml } from '../lib/formatters.js';

export function renderTable({ columns, rows, emptyMessage = 'No data available right now.' }) {
    return `
        <div class="overflow-hidden rounded-[28px] border border-white/60 bg-white/75 shadow-[0_24px_80px_rgba(15,23,42,0.08)] backdrop-blur">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200/80">
                    <thead class="bg-slate-950 text-left text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">
                        <tr>
                            ${columns.map((column) => `
                                <th class="px-5 py-4">${escapeHtml(column.label)}</th>
                            `).join('')}
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/70 text-sm text-slate-700">
                        ${rows.length > 0
                            ? rows.map((row) => `
                                <tr class="align-top transition hover:bg-slate-50/80">
                                    ${columns.map((column) => `
                                        <td class="px-5 py-4">
                                            ${column.render ? column.render(row) : escapeHtml(row[column.key])}
                                        </td>
                                    `).join('')}
                                </tr>
                            `).join('')
                            : `
                                <tr>
                                    <td colspan="${columns.length}" class="px-5 py-10 text-center text-sm text-slate-500">
                                        ${escapeHtml(emptyMessage)}
                                    </td>
                                </tr>
                            `}
                    </tbody>
                </table>
            </div>
        </div>
    `;
}
