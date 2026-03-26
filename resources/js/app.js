import '../css/app.css';
import { renderCard } from './components/card.js';
import { renderSidebar } from './components/sidebar.js';
import { renderTable } from './components/table.js';
import { renderTopbar } from './components/topbar.js';
import { escapeHtml, formatCurrency, formatDate, formatDateTime, titleCase } from './lib/formatters.js';
import { api, getErrorMessage } from './services/api.js';

const app = document.querySelector('#app');

if (!app) {
    throw new Error('Missing #app container.');
}

const navigationItems = [
    { id: 'dashboard', label: 'Dashboard', short: 'DB', hint: 'KPIs and recent activity' },
    { id: 'licenses', label: 'Licenses', short: 'LC', hint: 'Provision, revoke, extend' },
    { id: 'subscriptions', label: 'Subscriptions', short: 'PL', hint: 'Plans and entitlements' },
    { id: 'payments', label: 'Payments', short: 'PM', hint: 'Revenue and payment history' },
];

const pageCopy = {
    dashboard: {
        title: 'SaaS Control Room',
        description: 'Track license health, payment momentum, and plugin delivery from one modern Tailwind dashboard.',
    },
    licenses: {
        title: 'Licenses',
        description: 'Create new subscriptions, revoke compromised keys, and extend customer access without leaving the dashboard.',
    },
    subscriptions: {
        title: 'Subscriptions',
        description: 'Define pricing, billing cadence, and device limits for each SaaS plan.',
    },
    payments: {
        title: 'Payments',
        description: 'Review payment history and register new charges against active licenses.',
    },
};

const state = {
    currentPage: 'dashboard',
    loading: true,
    notice: null,
    plugin: {
        version: '—',
        url: '#',
    },
    licenses: {
        items: [],
        stats: {
            totalLicenses: 0,
            activeLicenses: 0,
            expiredLicenses: 0,
            revokedLicenses: 0,
            totalDevices: 0,
            revenueCollected: 0,
        },
    },
    plans: {
        items: [],
    },
    payments: {
        items: [],
        stats: {
            totalPayments: 0,
            totalAmount: 0,
            paid: 0,
            pending: 0,
            failed: 0,
            refunded: 0,
        },
    },
};

void bootstrap();

async function bootstrap() {
    bindGlobalEvents();
    render();
    await refreshData();
}

function bindGlobalEvents() {
    app.addEventListener('click', (event) => {
        const pageButton = event.target.closest('[data-page]');

        if (pageButton) {
            state.currentPage = pageButton.dataset.page;
            render();
            return;
        }

        const actionButton = event.target.closest('[data-action]');

        if (!actionButton) {
            return;
        }

        const action = actionButton.dataset.action;

        if (action === 'refresh-data') {
            void refreshData();
            return;
        }

        if (action === 'revoke-license') {
            void handleRevokeLicense(actionButton.dataset.licenseCode);
            return;
        }

        if (action === 'extend-license') {
            void handleExtendLicense(actionButton.dataset.licenseCode);
        }
    });

    app.addEventListener('submit', (event) => {
        const form = event.target.closest('form');

        if (!form) {
            return;
        }

        event.preventDefault();

        if (form.id === 'create-license-form') {
            void handleCreateLicense(form);
        }

        if (form.id === 'create-plan-form') {
            void handleCreatePlan(form);
        }

        if (form.id === 'create-payment-form') {
            void handleCreatePayment(form);
        }
    });
}

async function refreshData() {
    state.loading = true;
    render();

    try {
        const [licensesResponse, plansResponse, paymentsResponse, pluginResponse] = await Promise.all([
            api.get('/api/admin/licenses'),
            api.get('/api/admin/plans'),
            api.get('/api/admin/payments'),
            api.get('/api/plugin/version'),
        ]);

        state.licenses = licensesResponse.data;
        state.plans = plansResponse.data;
        state.payments = paymentsResponse.data;
        state.plugin = pluginResponse.data;

        if (state.notice?.type === 'error') {
            state.notice = null;
        }
    } catch (error) {
        state.notice = {
            type: 'error',
            message: getErrorMessage(error, 'Unable to load the SaaS dashboard right now.'),
        };
    } finally {
        state.loading = false;
        render();
    }
}

function render() {
    const pageMeta = pageCopy[state.currentPage] || pageCopy.dashboard;

    app.innerHTML = `
        <div class="min-h-screen bg-[radial-gradient(circle_at_top_left,rgba(34,197,94,0.16),transparent_28%),radial-gradient(circle_at_top_right,rgba(56,189,248,0.18),transparent_24%),linear-gradient(180deg,#fdfcf8_0%,#f8fafc_38%,#eef4ff_100%)]">
            <div class="mx-auto grid min-h-screen max-w-[1600px] gap-5 px-4 py-5 lg:grid-cols-[300px_minmax(0,1fr)] xl:px-6">
                ${renderSidebar({
                    currentPage: state.currentPage,
                    items: navigationItems,
                    stats: {
                        totalLicenses: state.licenses.stats.totalLicenses ?? 0,
                        totalDevices: state.licenses.stats.totalDevices ?? 0,
                        totalPlans: state.plans.items.length,
                        totalPayments: state.payments.stats.totalPayments ?? 0,
                    },
                })}

                <main class="space-y-5">
                    ${renderTopbar({
                        title: pageMeta.title,
                        description: pageMeta.description,
                        pluginVersion: state.plugin.version || '—',
                        loading: state.loading,
                        notice: state.notice,
                    })}

                    ${renderCurrentPage()}
                </main>
            </div>
        </div>
    `;
}

function renderCurrentPage() {
    if (state.currentPage === 'licenses') {
        return renderLicensesPage();
    }

    if (state.currentPage === 'subscriptions') {
        return renderSubscriptionsPage();
    }

    if (state.currentPage === 'payments') {
        return renderPaymentsPage();
    }

    return renderDashboardPage();
}

function renderDashboardPage() {
    const stats = state.licenses.stats;

    return `
        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
            <div class="space-y-5">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    ${renderCard({
                        label: 'Active Licenses',
                        value: stats.activeLicenses ?? 0,
                        meta: 'Currently usable subscriptions across all plans.',
                        tone: 'mint',
                    })}
                    ${renderCard({
                        label: 'Expired',
                        value: stats.expiredLicenses ?? 0,
                        meta: 'Subscriptions requiring renewal or extension.',
                        tone: 'amber',
                    })}
                    ${renderCard({
                        label: 'Devices Count',
                        value: stats.totalDevices ?? 0,
                        meta: 'Registered Photoshop installations across all licenses.',
                        tone: 'sky',
                    })}
                    ${renderCard({
                        label: 'Revenue Collected',
                        value: formatCurrency(stats.revenueCollected ?? 0),
                        meta: 'Total paid volume recorded in the payment ledger.',
                        tone: 'slate',
                    })}
                </div>

                ${renderSectionShell('Recent Licenses', renderTable({
                    columns: [
                        { label: 'License', render: (row) => `<div class="font-semibold text-slate-950">${escapeHtml(row.licenseCode)}</div><div class="mt-1 text-xs text-slate-500">${escapeHtml(row.planName || 'No plan')}</div>` },
                        { label: 'Status', render: (row) => renderStatusPill(resolveLicenseStatus(row)) },
                        { label: 'Expiry', render: (row) => formatDate(row.expiry) },
                        { label: 'Devices', render: (row) => escapeHtml(row.devicesCount) },
                        { label: 'Revenue', render: (row) => formatCurrency(row.paidTotal) },
                    ],
                    rows: state.licenses.items.slice(0, 5),
                    emptyMessage: 'Seed data or create your first license to populate this panel.',
                }))}
            </div>

            <div class="space-y-5">
                ${renderSectionShell('Release Snapshot', `
                    <div class="rounded-[28px] border border-slate-200 bg-slate-950 p-6 text-white">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Plugin Update System</p>
                        <h3 class="mt-4 text-3xl font-semibold">${escapeHtml(state.plugin.version || '1.0.1')}</h3>
                        <p class="mt-3 text-sm leading-6 text-slate-300">Current release served by <code class="rounded bg-white/10 px-2 py-1 text-xs">${escapeHtml(state.plugin.url || '#')}</code></p>
                        <a
                            class="mt-5 inline-flex rounded-2xl bg-white px-4 py-3 text-sm font-semibold text-slate-950 transition hover:-translate-y-0.5 hover:shadow-lg"
                            href="${escapeHtml(state.plugin.url || '#')}"
                            target="_blank"
                            rel="noreferrer"
                        >
                            Open Download URL
                        </a>
                    </div>
                `)}

                ${renderSectionShell('Payment Activity', renderTable({
                    columns: [
                        { label: 'Payment', render: (row) => `<div class="font-semibold text-slate-950">#${escapeHtml(row.id)}</div><div class="mt-1 text-xs text-slate-500">${escapeHtml(row.licenseCode)}</div>` },
                        { label: 'Status', render: (row) => renderStatusPill(row.status) },
                        { label: 'Amount', render: (row) => formatCurrency(row.amount) },
                        { label: 'Paid At', render: (row) => formatDateTime(row.paidAt || row.createdAt) },
                    ],
                    rows: state.payments.items.slice(0, 5),
                    emptyMessage: 'Payment records will appear here after you seed or create them.',
                }))}
            </div>
        </section>
    `;
}

function renderLicensesPage() {
    return `
        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
            <div class="space-y-5">
                ${renderSectionShell('Licenses', renderTable({
                    columns: [
                        {
                            label: 'License',
                            render: (row) => `
                                <div class="font-semibold text-slate-950">${escapeHtml(row.licenseCode)}</div>
                                <div class="mt-1 text-xs text-slate-500">${escapeHtml(row.planName || 'No plan linked')}</div>
                            `,
                        },
                        { label: 'Status', render: (row) => renderStatusPill(resolveLicenseStatus(row)) },
                        { label: 'Expiry', render: (row) => formatDate(row.expiry) },
                        { label: 'Devices', render: (row) => `${escapeHtml(row.devicesCount)} / ${escapeHtml(row.maxDevices)}` },
                        { label: 'Revenue', render: (row) => formatCurrency(row.paidTotal) },
                        {
                            label: 'Actions',
                            render: (row) => `
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        data-action="revoke-license"
                                        data-license-code="${escapeHtml(row.licenseCode)}"
                                        class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-100"
                                    >
                                        Revoke
                                    </button>
                                    <button
                                        type="button"
                                        data-action="extend-license"
                                        data-license-code="${escapeHtml(row.licenseCode)}"
                                        class="rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-semibold text-sky-700 transition hover:bg-sky-100"
                                    >
                                        Extend
                                    </button>
                                </div>
                            `,
                        },
                    ],
                    rows: state.licenses.items,
                    emptyMessage: 'No licenses exist yet. Use the create form to provision one.',
                }))}
            </div>

            <div class="space-y-5">
                ${renderSectionShell('Create License', `
                    <form id="create-license-form" class="space-y-4">
                        <label class="block">
                            <span class="mb-2 block text-sm font-semibold text-slate-700">Plan</span>
                            <select name="plan_id" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-950 shadow-sm outline-none ring-0" required>
                                <option value="">Select a plan</option>
                                ${state.plans.items.map((plan) => `
                                    <option value="${escapeHtml(plan.id)}">${escapeHtml(plan.name)} · ${formatCurrency(plan.price)} · ${escapeHtml(titleCase(plan.duration))}</option>
                                `).join('')}
                            </select>
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-sm font-semibold text-slate-700">License Code</span>
                            <input name="license_code" placeholder="Auto-generate if empty" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-950 shadow-sm outline-none" />
                        </label>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-2 block text-sm font-semibold text-slate-700">Max Devices</span>
                                <input type="number" min="1" name="max_devices" placeholder="Use plan default" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-950 shadow-sm outline-none" />
                            </label>
                            <label class="block">
                                <span class="mb-2 block text-sm font-semibold text-slate-700">Expiry Date</span>
                                <input type="date" name="expiry" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-950 shadow-sm outline-none" />
                            </label>
                        </div>
                        <button type="submit" class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:shadow-lg">
                            Create License
                        </button>
                    </form>
                `)}

                ${renderSectionShell('Operational Notes', `
                    <div class="space-y-3 text-sm leading-6 text-slate-500">
                        <p>Activation, validation, and sync are rate-limited and logged on the backend.</p>
                        <p>Extending a license keeps the existing plan while pushing the expiry window forward.</p>
                        <p>Revoking a license marks it unusable immediately for future plugin checks.</p>
                    </div>
                `)}
            </div>
        </section>
    `;
}

function renderSubscriptionsPage() {
    return `
        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
            <div class="space-y-5">
                <div class="grid gap-4 md:grid-cols-2">
                    ${state.plans.items.map((plan) => renderCard({
                        label: plan.duration === 'yearly' ? 'Yearly Plan' : 'Monthly Plan',
                        value: plan.name,
                        meta: `${formatCurrency(plan.price)} · ${plan.maxDevices} devices`,
                        tone: plan.duration === 'yearly' ? 'sky' : 'mint',
                    })).join('') || renderEmptyCard('No plans yet', 'Create your first subscription plan from the form on the right.')}
                </div>

                ${renderSectionShell('Plan Catalog', renderTable({
                    columns: [
                        { label: 'Plan', render: (row) => `<div class="font-semibold text-slate-950">${escapeHtml(row.name)}</div>` },
                        { label: 'Price', render: (row) => formatCurrency(row.price) },
                        { label: 'Duration', render: (row) => escapeHtml(titleCase(row.duration)) },
                        { label: 'Max Devices', render: (row) => escapeHtml(row.maxDevices) },
                        { label: 'Created', render: (row) => formatDateTime(row.createdAt) },
                    ],
                    rows: state.plans.items,
                    emptyMessage: 'No plans available. Use the create plan form to get started.',
                }))}
            </div>

            <div class="space-y-5">
                ${renderSectionShell('Create Plan', `
                    <form id="create-plan-form" class="space-y-4">
                        <label class="block">
                            <span class="mb-2 block text-sm font-semibold text-slate-700">Plan Name</span>
                            <input name="name" placeholder="Pro Annual" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-950 shadow-sm outline-none" required />
                        </label>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-2 block text-sm font-semibold text-slate-700">Price (USD)</span>
                                <input type="number" min="1" step="0.01" name="price" placeholder="49.00" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-950 shadow-sm outline-none" required />
                            </label>
                            <label class="block">
                                <span class="mb-2 block text-sm font-semibold text-slate-700">Duration</span>
                                <select name="duration" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-950 shadow-sm outline-none" required>
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </label>
                        </div>
                        <label class="block">
                            <span class="mb-2 block text-sm font-semibold text-slate-700">Max Devices</span>
                            <input type="number" min="1" name="max_devices" placeholder="2" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-950 shadow-sm outline-none" required />
                        </label>
                        <button type="submit" class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:shadow-lg">
                            Create Plan
                        </button>
                    </form>
                `)}

                ${renderSectionShell('Plan Guidance', `
                    <div class="space-y-3 text-sm leading-6 text-slate-500">
                        <p>Each plan defines billing cadence and default device entitlements for new licenses.</p>
                        <p>Licenses can override max devices when handling custom enterprise exceptions.</p>
                    </div>
                `)}
            </div>
        </section>
    `;
}

function renderPaymentsPage() {
    return `
        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
            <div class="space-y-5">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    ${renderCard({
                        label: 'Total Payments',
                        value: state.payments.stats.totalPayments ?? 0,
                        meta: 'All recorded payment rows in the system.',
                        tone: 'slate',
                    })}
                    ${renderCard({
                        label: 'Paid',
                        value: state.payments.stats.paid ?? 0,
                        meta: 'Successfully settled payment records.',
                        tone: 'mint',
                    })}
                    ${renderCard({
                        label: 'Pending',
                        value: state.payments.stats.pending ?? 0,
                        meta: 'Awaiting settlement or manual reconciliation.',
                        tone: 'amber',
                    })}
                    ${renderCard({
                        label: 'Volume',
                        value: formatCurrency(state.payments.stats.totalAmount ?? 0),
                        meta: 'Aggregate amount across all payment records.',
                        tone: 'sky',
                    })}
                </div>

                ${renderSectionShell('Payment History', renderTable({
                    columns: [
                        { label: 'Payment', render: (row) => `<div class="font-semibold text-slate-950">#${escapeHtml(row.id)}</div><div class="mt-1 text-xs text-slate-500">${escapeHtml(row.licenseCode)}</div>` },
                        { label: 'Plan', render: (row) => escapeHtml(row.planName || '—') },
                        { label: 'Status', render: (row) => renderStatusPill(row.status) },
                        { label: 'Amount', render: (row) => formatCurrency(row.amount) },
                        { label: 'Paid At', render: (row) => formatDateTime(row.paidAt || row.createdAt) },
                    ],
                    rows: state.payments.items,
                    emptyMessage: 'No payments have been logged yet.',
                }))}
            </div>

            <div class="space-y-5">
                ${renderSectionShell('Record Payment', `
                    <form id="create-payment-form" class="space-y-4">
                        <label class="block">
                            <span class="mb-2 block text-sm font-semibold text-slate-700">License</span>
                            <select name="license_code" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-950 shadow-sm outline-none" required>
                                <option value="">Select a license</option>
                                ${state.licenses.items.map((license) => `
                                    <option value="${escapeHtml(license.licenseCode)}">${escapeHtml(license.licenseCode)} · ${escapeHtml(license.planName || 'No plan')}</option>
                                `).join('')}
                            </select>
                        </label>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-2 block text-sm font-semibold text-slate-700">Amount (USD)</span>
                                <input type="number" min="1" step="0.01" name="amount" placeholder="49.00" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-950 shadow-sm outline-none" required />
                            </label>
                            <label class="block">
                                <span class="mb-2 block text-sm font-semibold text-slate-700">Status</span>
                                <select name="status" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-950 shadow-sm outline-none">
                                    <option value="paid">Paid</option>
                                    <option value="pending">Pending</option>
                                    <option value="failed">Failed</option>
                                    <option value="refunded">Refunded</option>
                                </select>
                            </label>
                        </div>
                        <button type="submit" class="w-full rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:shadow-lg">
                            Save Payment
                        </button>
                    </form>
                `)}
            </div>
        </section>
    `;
}

async function handleCreateLicense(form) {
    const formData = new FormData(form);

    try {
        const payload = {
            plan_id: formData.get('plan_id'),
            license_code: formData.get('license_code'),
            max_devices: formData.get('max_devices'),
            expiry: formData.get('expiry'),
        };

        await api.post('/api/admin/create-license', payload);
        form.reset();
        state.notice = {
            type: 'success',
            message: 'License created successfully.',
        };
        await refreshData();
    } catch (error) {
        state.notice = {
            type: 'error',
            message: getErrorMessage(error, 'Failed to create license.'),
        };
        render();
    }
}

async function handleCreatePlan(form) {
    const formData = new FormData(form);

    try {
        const payload = {
            name: formData.get('name'),
            price: formData.get('price'),
            duration: formData.get('duration'),
            max_devices: formData.get('max_devices'),
        };

        await api.post('/api/admin/plans', payload);
        form.reset();
        state.notice = {
            type: 'success',
            message: 'Plan created successfully.',
        };
        await refreshData();
    } catch (error) {
        state.notice = {
            type: 'error',
            message: getErrorMessage(error, 'Failed to create plan.'),
        };
        render();
    }
}

async function handleCreatePayment(form) {
    const formData = new FormData(form);

    try {
        const payload = {
            license_code: formData.get('license_code'),
            amount: formData.get('amount'),
            status: formData.get('status'),
        };

        await api.post('/api/admin/payments', payload);
        form.reset();
        state.notice = {
            type: 'success',
            message: 'Payment recorded successfully.',
        };
        await refreshData();
    } catch (error) {
        state.notice = {
            type: 'error',
            message: getErrorMessage(error, 'Failed to record payment.'),
        };
        render();
    }
}

async function handleRevokeLicense(licenseCode) {
    if (!licenseCode) {
        return;
    }

    try {
        await api.post('/api/admin/revoke-license', {
            license_code: licenseCode,
        });
        state.notice = {
            type: 'success',
            message: `License ${licenseCode} revoked successfully.`,
        };
        await refreshData();
    } catch (error) {
        state.notice = {
            type: 'error',
            message: getErrorMessage(error, 'Failed to revoke the license.'),
        };
        render();
    }
}

async function handleExtendLicense(licenseCode) {
    if (!licenseCode) {
        return;
    }

    const value = window.prompt(`How many days should be added to ${licenseCode}?`, '30');

    if (!value) {
        return;
    }

    try {
        await api.post('/api/admin/extend-license', {
            license_code: licenseCode,
            days: value,
        });
        state.notice = {
            type: 'success',
            message: `License ${licenseCode} extended successfully.`,
        };
        await refreshData();
    } catch (error) {
        state.notice = {
            type: 'error',
            message: getErrorMessage(error, 'Failed to extend the license.'),
        };
        render();
    }
}

function renderSectionShell(title, content) {
    return `
        <section class="rounded-[32px] border border-white/60 bg-white/65 p-5 shadow-[0_24px_80px_rgba(15,23,42,0.08)] backdrop-blur">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Section</p>
                    <h3 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">${escapeHtml(title)}</h3>
                </div>
            </div>
            ${content}
        </section>
    `;
}

function renderStatusPill(status) {
    const normalized = String(status ?? '').toLowerCase();
    const toneClass = {
        active: 'border-emerald-200 bg-emerald-50 text-emerald-700',
        expired: 'border-amber-200 bg-amber-50 text-amber-700',
        revoked: 'border-rose-200 bg-rose-50 text-rose-700',
        paid: 'border-emerald-200 bg-emerald-50 text-emerald-700',
        pending: 'border-amber-200 bg-amber-50 text-amber-700',
        failed: 'border-rose-200 bg-rose-50 text-rose-700',
        refunded: 'border-slate-200 bg-slate-100 text-slate-700',
    }[normalized] || 'border-slate-200 bg-slate-100 text-slate-700';

    return `
        <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] ${toneClass}">
            ${escapeHtml(normalized || 'unknown')}
        </span>
    `;
}

function renderEmptyCard(title, message) {
    return `
        <article class="rounded-[28px] border border-dashed border-slate-300 bg-white/60 p-6 text-center shadow-[0_24px_80px_rgba(15,23,42,0.06)]">
            <h3 class="text-lg font-semibold text-slate-950">${escapeHtml(title)}</h3>
            <p class="mt-2 text-sm leading-6 text-slate-500">${escapeHtml(message)}</p>
        </article>
    `;
}

function resolveLicenseStatus(license) {
    if (license.revoked || String(license.status || '').toLowerCase() === 'revoked') {
        return 'revoked';
    }

    if (new Date(`${license.expiry}T23:59:59.999Z`).valueOf() < Date.now()) {
        return 'expired';
    }

    return 'active';
}
