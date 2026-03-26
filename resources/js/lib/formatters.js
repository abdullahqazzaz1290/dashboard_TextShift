export function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

export function formatDate(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(`${String(value).slice(0, 10)}T00:00:00.000Z`);

    if (Number.isNaN(date.valueOf())) {
        return escapeHtml(value);
    }

    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        timeZone: 'UTC',
    }).format(date);
}

export function formatDateTime(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(String(value).replace(' ', 'T') + 'Z');

    if (Number.isNaN(date.valueOf())) {
        return escapeHtml(value);
    }

    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: 'UTC',
    }).format(date);
}

export function formatCurrency(value) {
    const amount = Number(value ?? 0);

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 2,
    }).format(Number.isFinite(amount) ? amount : 0);
}

export function titleCase(value) {
    return String(value ?? '')
        .split(/[\s_-]+/)
        .filter(Boolean)
        .map((token) => token.charAt(0).toUpperCase() + token.slice(1))
        .join(' ');
}
