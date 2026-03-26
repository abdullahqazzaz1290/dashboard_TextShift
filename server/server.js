import 'dotenv/config';
import cors from 'cors';
import crypto from 'node:crypto';
import fs from 'node:fs/promises';
import path from 'node:path';
import express from 'express';
import { fileURLToPath, pathToFileURL } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const dataDir = path.join(__dirname, 'data');
const licensesFile = path.join(dataDir, 'licenses.json');
const projectRoot = path.resolve(__dirname, '..');
const distDir = path.join(projectRoot, 'dist');
const publicDir = path.join(projectRoot, 'public');
const frontendEntry = path.join(distDir, 'index.html');
const defaultPort = Number.parseInt(process.env.PORT ?? '3000', 10) || 3000;
let mutationQueue = Promise.resolve();

export function createApp(secretKey = resolveSecret()) {
    const app = express();

    app.use(cors());
    app.use(express.json({ limit: '1mb' }));

    app.get('/health', (_request, response) => {
        response.json({
            ok: true,
            service: 'textshift-licensing-api',
            timestamp: new Date().toISOString(),
        });
    });

    app.post('/api/license/activate', asyncHandler(async (request, response) => {
        response.json(await activateLicense({
            licenseCode: request.body.license_code,
            deviceId: request.body.device_id,
            fingerprint: request.body.fingerprint,
            secret: secretKey,
        }));
    }));

    app.post('/api/license/validate', asyncHandler(async (request, response) => {
        response.json(await validateLicense({
            licenseCode: request.body.license_code,
            deviceToken: request.body.device_token,
            secret: secretKey,
        }));
    }));

    app.post('/api/license/sync', asyncHandler(async (request, response) => {
        response.json(await syncLicense({
            licenseCode: request.body.license_code,
            deviceToken: request.body.device_token,
            lastSync: request.body.last_sync,
            secret: secretKey,
        }));
    }));

    app.use(express.static(publicDir, {
        extensions: ['html'],
        index: false,
    }));
    app.use(express.static(distDir, {
        extensions: ['html'],
        index: false,
    }));

    app.use(asyncHandler(async (request, response, next) => {
        if (request.path.startsWith('/api/')) {
            next();
            return;
        }

        if (await fileExists(frontendEntry)) {
            response.sendFile(frontendEntry);
            return;
        }

        response.type('html').send(`<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>TextShift Licensing API</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 40px; background: #f8fafc; color: #0f172a; }
            main { max-width: 720px; margin: 0 auto; background: white; border-radius: 16px; padding: 32px; box-shadow: 0 12px 40px rgba(15, 23, 42, 0.08); }
            code { background: #e2e8f0; padding: 2px 6px; border-radius: 6px; }
        </style>
    </head>
    <body>
        <main>
            <h1>TextShift Licensing API</h1>
            <p>The backend is running.</p>
            <p>Build the frontend with <code>npm run build</code> to serve the Vite app from this process.</p>
        </main>
    </body>
</html>`);
    }));

    app.use((error, _request, response, next) => {
        if (error instanceof SyntaxError && 'body' in error) {
            response.status(400).json({
                error: 'Invalid JSON payload.',
                code: 'INVALID_JSON',
            });
            return;
        }

        next(error);
    });

    app.use((error, _request, response, _next) => {
        const statusCode = Number.isInteger(error.statusCode) ? error.statusCode : 500;

        response.status(statusCode).json({
            error: error.message || 'Internal server error.',
            ...(error.details || {}),
        });
    });

    return app;
}

export async function startServer({ port = defaultPort, secret = process.env.SECRET } = {}) {
    const secretKey = resolveSecret(secret);
    await ensureStorage();

    const app = createApp(secretKey);

    return new Promise((resolve, reject) => {
        const server = app.listen(port, () => {
            console.log(`Licensing API listening on port ${port}`);
            resolve(server);
        });

        server.on('error', reject);
    });
}

export async function activateLicense({
    licenseCode,
    deviceId,
    fingerprint,
    secret = process.env.SECRET,
} = {}) {
    const secretKey = resolveSecret(secret);
    const normalizedLicenseCode = requireText(licenseCode, 'license_code');
    const normalizedDeviceId = requireText(deviceId, 'device_id');
    const normalizedFingerprint = requireText(fingerprint, 'fingerprint');

    return mutateLicenses((licenses) => {
        const license = licenses[normalizedLicenseCode];

        if (!license) {
            throw httpError(404, 'License not found.', { code: 'LICENSE_NOT_FOUND' });
        }

        const status = resolveLicenseStatus(license);
        if (status !== 'active') {
            throw httpError(403, `License is ${status}.`, {
                code: `LICENSE_${status.toUpperCase()}`,
                status,
                expiry: license.expiry,
            });
        }

        const maxDevices = normalizeMaxDevices(license.max_devices);
        const devices = ensureDevices(license);
        const now = new Date().toISOString();

        let device = devices.find((entry) => {
            const entryDeviceId = normalizeText(entry.device_id);
            const entryFingerprint = normalizeText(entry.fingerprint);

            return entryDeviceId === normalizedDeviceId || entryFingerprint === normalizedFingerprint;
        });

        if (!device) {
            if (devices.length >= maxDevices) {
                throw httpError(409, 'Maximum device limit reached.', {
                    code: 'DEVICE_LIMIT_REACHED',
                    max_devices: maxDevices,
                });
            }

            device = {
                device_id: normalizedDeviceId,
                fingerprint: normalizedFingerprint,
                token: generateDeviceToken(),
                activated_at: now,
                last_sync: now,
            };

            devices.push(device);
        } else {
            device.device_id = normalizedDeviceId;
            device.fingerprint = normalizedFingerprint;
            device.token = normalizeText(device.token) || generateDeviceToken();
            device.last_sync = now;
            device.activated_at = device.activated_at || now;
        }

        return {
            status: 'active',
            expiry: license.expiry,
            device_token: device.token,
            signature: createSignature(secretKey, normalizedLicenseCode, device.token, license.expiry),
        };
    });
}

export async function validateLicense({
    licenseCode,
    deviceToken,
    secret = process.env.SECRET,
} = {}) {
    const secretKey = resolveSecret(secret);
    const normalizedLicenseCode = requireText(licenseCode, 'license_code');
    const normalizedDeviceToken = requireText(deviceToken, 'device_token');

    const licenses = await readLicenses();
    const license = licenses[normalizedLicenseCode];

    if (!license) {
        throw httpError(404, 'License not found.', { code: 'LICENSE_NOT_FOUND' });
    }

    const device = findDeviceByToken(license, normalizedDeviceToken);
    if (!device) {
        throw httpError(401, 'Device token is invalid.', { code: 'INVALID_DEVICE_TOKEN' });
    }

    return buildLicenseStateResponse(secretKey, normalizedLicenseCode, normalizedDeviceToken, license);
}

export async function syncLicense({
    licenseCode,
    deviceToken,
    lastSync,
    secret = process.env.SECRET,
} = {}) {
    const secretKey = resolveSecret(secret);
    const normalizedLicenseCode = requireText(licenseCode, 'license_code');
    const normalizedDeviceToken = requireText(deviceToken, 'device_token');
    const normalizedLastSync = normalizeOptionalText(lastSync);

    return mutateLicenses((licenses) => {
        const license = licenses[normalizedLicenseCode];

        if (!license) {
            throw httpError(404, 'License not found.', { code: 'LICENSE_NOT_FOUND' });
        }

        const device = findDeviceByToken(license, normalizedDeviceToken);
        if (!device) {
            throw httpError(401, 'Device token is invalid.', { code: 'INVALID_DEVICE_TOKEN' });
        }

        device.last_sync = normalizedLastSync || new Date().toISOString();

        return buildLicenseStateResponse(secretKey, normalizedLicenseCode, normalizedDeviceToken, license);
    });
}

function asyncHandler(handler) {
    return function wrappedHandler(request, response, next) {
        Promise.resolve(handler(request, response, next)).catch(next);
    };
}

function httpError(statusCode, message, details = {}) {
    const error = new Error(message);
    error.statusCode = statusCode;
    error.details = details;
    return error;
}

function requireText(value, field) {
    const normalized = normalizeText(value);

    if (!normalized) {
        throw httpError(400, `${field} is required.`, {
            code: 'VALIDATION_ERROR',
            field,
        });
    }

    return normalized;
}

function normalizeOptionalText(value) {
    return normalizeText(value) || null;
}

function normalizeText(value) {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value).trim();
}

function normalizeMaxDevices(value) {
    const parsed = Number.parseInt(String(value ?? '1'), 10);

    if (!Number.isInteger(parsed) || parsed <= 0) {
        throw httpError(500, 'License has an invalid max_devices value.', {
            code: 'INVALID_LICENSE_CONFIGURATION',
        });
    }

    return parsed;
}

function ensureDevices(license) {
    if (!Array.isArray(license.devices)) {
        license.devices = [];
    }

    return license.devices;
}

function resolveLicenseStatus(license) {
    if (license.revoked === true || normalizeText(license.status).toLowerCase() === 'revoked') {
        return 'revoked';
    }

    if (isExpired(license.expiry)) {
        return 'expired';
    }

    return 'active';
}

function isExpired(expiry) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(normalizeText(expiry))) {
        throw httpError(500, 'License has an invalid expiry value.', {
            code: 'INVALID_LICENSE_CONFIGURATION',
        });
    }

    const [year, month, day] = expiry.split('-').map(Number);
    const expiryTime = Date.UTC(year, month - 1, day, 23, 59, 59, 999);

    return Date.now() > expiryTime;
}

function findDeviceByToken(license, deviceToken) {
    return ensureDevices(license).find((entry) => normalizeText(entry.token) === deviceToken) || null;
}

function buildLicenseStateResponse(secretKey, licenseCode, deviceToken, license) {
    return {
        status: resolveLicenseStatus(license),
        expiry: license.expiry,
        signature: createSignature(secretKey, licenseCode, deviceToken, license.expiry),
    };
}

function createSignature(secretKey, licenseCode, deviceToken, expiry) {
    return crypto
        .createHmac('sha256', secretKey)
        .update(`${licenseCode}${deviceToken}${expiry}`, 'utf8')
        .digest('hex');
}

function generateDeviceToken() {
    return crypto.randomBytes(32).toString('hex');
}

async function ensureStorage() {
    await fs.mkdir(dataDir, { recursive: true });

    try {
        await fs.access(licensesFile);
    } catch {
        await fs.writeFile(licensesFile, '{}\n', 'utf8');
    }
}

async function readLicenses() {
    await ensureStorage();

    const raw = await fs.readFile(licensesFile, 'utf8');
    const parsed = normalizeText(raw) ? JSON.parse(raw) : {};

    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
        throw httpError(500, 'License storage is corrupted.', {
            code: 'INVALID_LICENSE_STORE',
        });
    }

    return parsed;
}

function mutateLicenses(mutator) {
    const operation = mutationQueue.then(async () => {
        const licenses = await readLicenses();
        const result = await mutator(licenses);
        await writeLicenses(licenses);
        return result;
    });

    mutationQueue = operation.catch(() => {});

    return operation;
}

async function writeLicenses(licenses) {
    const tempFile = `${licensesFile}.tmp`;
    const serialized = `${JSON.stringify(licenses, null, 2)}\n`;

    await fs.writeFile(tempFile, serialized, 'utf8');
    await fs.rename(tempFile, licensesFile);
}

function resolveSecret(secret = process.env.SECRET) {
    const normalizedSecret = normalizeText(secret);

    if (!normalizedSecret) {
        throw new Error('Missing required SECRET environment variable.');
    }

    return normalizedSecret;
}

async function fileExists(filePath) {
    try {
        await fs.access(filePath);
        return true;
    } catch {
        return false;
    }
}

function isDirectExecution() {
    return Boolean(process.argv[1]) && import.meta.url === pathToFileURL(process.argv[1]).href;
}

if (isDirectExecution()) {
    startServer().catch((error) => {
        console.error(error.message || error);
        process.exit(1);
    });
}
