import '../css/app.css';

const app = document.querySelector('#app');

if (!app) {
    throw new Error('Missing #app container.');
}

const endpoints = [
    {
        method: 'POST',
        route: '/api/license/activate',
        description: 'Activates a license on a device and returns a signed device token.',
        payload: {
            license_code: 'TS-2026-001',
            device_id: 'design-station-01',
            fingerprint: 'ps-uxp-fingerprint-abc123',
        },
    },
    {
        method: 'POST',
        route: '/api/license/validate',
        description: 'Validates the current device token and returns the license state.',
        payload: {
            license_code: 'TS-2026-001',
            device_token: 'generated-device-token',
        },
    },
    {
        method: 'POST',
        route: '/api/license/sync',
        description: 'Refreshes device state and returns the latest expiry and signature.',
        payload: {
            license_code: 'TS-2026-001',
            device_token: 'generated-device-token',
            last_sync: new Date().toISOString(),
        },
    },
];

app.innerHTML = `
    <div class="shell">
        <section class="hero">
            <p class="eyebrow">TextShift Licensing API</p>
            <h1>Production-ready licensing backend for Photoshop UXP workflows.</h1>
            <p class="lede">
                Express powers activation, validation, sync, multi-device control, and HMAC signatures for Railway deployments.
            </p>
            <div class="hero-grid">
                <article class="stat-card">
                    <span class="stat-label">Runtime</span>
                    <strong>Node.js + Express</strong>
                </article>
                <article class="stat-card">
                    <span class="stat-label">Security</span>
                    <strong>HMAC-SHA256 signatures</strong>
                </article>
                <article class="stat-card">
                    <span class="stat-label">Storage</span>
                    <strong>JSON license registry</strong>
                </article>
            </div>
        </section>

        <section class="panel">
            <div class="panel-heading">
                <p class="eyebrow">Frontend</p>
                <h2>Vite status page kept in place</h2>
            </div>
            <p class="panel-copy">
                Build the frontend with <code>npm run build</code>, then run <code>npm start</code> to serve the API and the generated static app from one Node process.
            </p>
        </section>

        <section class="panel">
            <div class="panel-heading">
                <p class="eyebrow">Endpoints</p>
                <h2>Licensing routes</h2>
            </div>
            <div class="endpoint-list">
                ${endpoints
                    .map((endpoint) => `
                        <article class="endpoint-card">
                            <div class="endpoint-topline">
                                <span class="badge">${endpoint.method}</span>
                                <code>${endpoint.route}</code>
                            </div>
                            <p>${endpoint.description}</p>
                            <pre>${JSON.stringify(endpoint.payload, null, 2)}</pre>
                        </article>
                    `)
                    .join('')}
            </div>
        </section>
    </div>
`;
