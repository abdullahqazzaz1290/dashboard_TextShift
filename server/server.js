import 'dotenv/config';
import cors from 'cors';
import crypto from 'node:crypto';
import fs from 'node:fs/promises';
import path from 'node:path';
import express from 'express';
import rateLimit from 'express-rate-limit';
import { fileURLToPath, pathToFileURL } from 'node:url';
import {
    createDevice,
    createLicense,
    createPayment,
    createPlan,
    ensureDatabase,
    findDeviceByToken,
    findLicenseByCode,
    findPlanById,
    listDevicesForLicense,
    listLicenses,
    listPayments,
    listPlans,
    pingDatabase,
    updateDevice,
    updateLicenseByCode,
    withTransaction,
} from './database.js';
import { logError, logInfo } from './logger.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..');
const distDir = path.join(projectRoot, 'dist');
const publicDir = path.join(projectRoot, 'public');
const frontendEntry = path.join(distDir, 'index.html');
const defaultPort = Number.parseInt(process.env.PORT ?? '3000', 10) || 3000;

export function createApp(secretKey = resolveSecret()) {
    const app = express();
    const adminLimiter = createLimiter({
        windowMs: parsePositiveInteger(process.env.ADMIN_RATE_LIMIT_WINDOW_MS, 15 * 60 * 1000),
        max: parsePositiveInteger(process.env.ADMIN_RATE_LIMIT_MAX, 240),
        message: 'Too many admin requests. Please try again shortly.',
    });

    app.set('trust proxy', 1);
    app.use(cors());
    app.use(express.json({ limit: '1mb' }));
    app.use(createLimiter({
        windowMs: parsePositiveInteger(process.env.RATE_LIMIT_WINDOW_MS, 15 * 60 * 1000),
        max: parsePositiveInteger(process.env.RATE_LIMIT_MAX, 500),
        message: 'Too many requests. Please try again later.',
    }));

    app.get('/health', asyncHandler(async (_request, response) => {
        await pingDatabase();

        response.json({
            ok: true,
            service: 'textshift-saas-licensing',
            database: 'mysql',
            plugin: resolvePluginInfo(),
            timestamp: new Date().toISOString(),
        });
    }));

    app.get('/api/plugin/version', asyncHandler(async (_request, response) => {
        response.json(resolvePluginInfo());
    }));

    app.post('/api/license/activate', createLimiter({
        windowMs: parsePositiveInteger(process.env.ACTIVATION_RATE_LIMIT_WINDOW_MS, 10 * 60 * 1000),
        max: parsePositiveInteger(process.env.ACTIVATION_RATE_LIMIT_MAX, 30),
        message: 'Too many activation attempts. Please wait before trying again.',
    }), asyncHandler(async (request, response) => {
        const licenseCode = normalizeLicenseCode(request.body.license_code);
        const deviceId = normalizeText(request.body.device_id);
        const fingerprint = normalizeText(request.body.fingerprint);

        void logInfo('license.activation.attempt', {
            ...requestContext(request),
            licenseCode,
            deviceId,
            fingerprintHash: digestFingerprint(fingerprint),
        });

        const payload = await activateLicense({
            licenseCode,
            deviceId,
            fingerprint,
            secret: secretKey,
        });

        void logInfo('license.activation.success', {
            ...requestContext(request),
            licenseCode,
            deviceId,
        });

        response.json(payload);
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

    app.use('/api/admin', adminLimiter);

    app.get('/api/admin/licenses', asyncHandler(async (_request, response) => {
        const items = await listLicenses();
        response.json({
            items,
            stats: summarizeLicenses(items),
        });
    }));

    app.post('/api/admin/create-license', asyncHandler(async (request, response) => {
        const item = await createAdminLicense(request.body);
        void logInfo('admin.license.created', {
            licenseCode: item.licenseCode,
            planId: item.planId,
        });
        response.status(201).json({ item });
    }));

    app.post('/api/admin/revoke-license', asyncHandler(async (request, response) => {
        const item = await revokeAdminLicense(request.body);
        void logInfo('admin.license.revoked', {
            licenseCode: item.licenseCode,
        });
        response.json({ item });
    }));

    app.post('/api/admin/extend-license', asyncHandler(async (request, response) => {
        const item = await extendAdminLicense(request.body);
        void logInfo('admin.license.extended', {
            licenseCode: item.licenseCode,
            expiry: item.expiry,
        });
        response.json({ item });
    }));

    app.get('/api/admin/plans', asyncHandler(async (_request, response) => {
        const items = await listPlans();
        response.json({ items });
    }));

    app.post('/api/admin/plans', asyncHandler(async (request, response) => {
        const item = await createAdminPlan(request.body);
        void logInfo('admin.plan.created', {
            planId: item.id,
            name: item.name,
        });
        response.status(201).json({ item });
    }));

    app.get('/api/admin/payments', asyncHandler(async (_request, response) => {
        const items = await listPayments();
        response.json({
            items,
            stats: summarizePayments(items),
        });
    }));

    app.post('/api/admin/payments', asyncHandler(async (request, response) => {
        const item = await createAdminPayment(request.body);
        void logInfo('admin.payment.created', {
            paymentId: item.id,
            licenseCode: item.licenseCode,
            status: item.status,
        });
        response.status(201).json({ item });
    }));

    app.use('/api', (_request, response) => {
        response.status(404).json({
            error: 'API route not found.',
            code: 'ROUTE_NOT_FOUND',
        });
    });

    app.use(express.static(publicDir, {
        extensions: ['html'],
        index: false,
    }));
    app.use(express.static(distDir, {
        extensions: ['html'],
        index: false,
    }));

    app.use(asyncHandler(async (_request, response) => {
        if (await fileExists(frontendEntry)) {
            response.sendFile(frontendEntry);
            return;
        }

        response.type('html').send(`<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>TextShift SaaS Licensing</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 40px; background: #f8fafc; color: #0f172a; }
            main { max-width: 720px; margin: 0 auto; background: white; border-radius: 16px; padding: 32px; box-shadow: 0 12px 40px rgba(15, 23, 42, 0.08); }
            code { background: #e2e8f0; padding: 2px 6px; border-radius: 6px; }
        </style>
    </head>
    <body>
        <main>
            <h1>TextShift SaaS Licensing</h1>
            <p>The backend is running.</p>
            <p>Build the frontend with <code>npm run build</code> to serve the dashboard from this process.</p>
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

    app.use((error, request, response, _next) => {
        const normalized = normalizeError(error);
        void logError('request.error', normalized, {
            ...requestContext(request),
            path: request.path,
            method: request.method,
        });

        response.status(normalized.statusCode).json({
            error: normalized.message,
            ...(normalized.details || {}),
        });
    });

    return app;
}

export async function startServer({ port = defaultPort, secret = process.env.SECRET } = {}) {
    const secretKey = resolveSecret(secret);
    await ensureDatabase();
    await pingDatabase();
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
    await ensureDatabase();
    const normalizedLicenseCode = requireLicenseCode(licenseCode);
    const normalizedDeviceId = requireText(deviceId, 'device_id');
    const normalizedFingerprint = requireText(fingerprint, 'fingerprint');

    return withTransaction(async (connection) => {
        const license = await requireLicense(connection, normalizedLicenseCode, { forUpdate: true });
        const status = resolveLicenseStatus(license);

        if (status !== 'active') {
            throw httpError(403, `License is ${status}.`, {
                code: `LICENSE_${status.toUpperCase()}`,
                status,
                expiry: license.expiry,
            });
        }

        const devices = await listDevicesForLicense(connection, license.id);
        const now = toMysqlDateTime(new Date());

        let device = devices.find((entry) => {
            return entry.deviceId === normalizedDeviceId || entry.fingerprint === normalizedFingerprint;
        });

        if (!device) {
            if (devices.length >= license.maxDevices) {
                throw httpError(409, 'Maximum device limit reached.', {
                    code: 'DEVICE_LIMIT_REACHED',
                    max_devices: license.maxDevices,
                });
            }

            device = await createDevice(connection, {
                licenseId: license.id,
                deviceId: normalizedDeviceId,
                fingerprint: normalizedFingerprint,
                token: generateDeviceToken(),
                activatedAt: now,
                lastSync: now,
            });
        } else {
            const nextToken = normalizeText(device.token) || generateDeviceToken();
            const nextActivatedAt = device.activatedAt || now;

            await updateDevice(connection, device.id, {
                deviceId: normalizedDeviceId,
                fingerprint: normalizedFingerprint,
                token: nextToken,
                activatedAt: nextActivatedAt,
                lastSync: now,
            });

            device = {
                ...device,
                deviceId: normalizedDeviceId,
                fingerprint: normalizedFingerprint,
                token: nextToken,
                activatedAt: nextActivatedAt,
                lastSync: now,
            };
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
    await ensureDatabase();
    const normalizedLicenseCode = requireLicenseCode(licenseCode);
    const normalizedDeviceToken = requireText(deviceToken, 'device_token');

    const license = await requireLicense(undefined, normalizedLicenseCode);
    await requireDevice(undefined, license.id, normalizedDeviceToken);

    return buildLicenseStateResponse(secretKey, normalizedLicenseCode, normalizedDeviceToken, license);
}

export async function syncLicense({
    licenseCode,
    deviceToken,
    lastSync,
    secret = process.env.SECRET,
} = {}) {
    const secretKey = resolveSecret(secret);
    await ensureDatabase();
    const normalizedLicenseCode = requireLicenseCode(licenseCode);
    const normalizedDeviceToken = requireText(deviceToken, 'device_token');
    const normalizedLastSync = optionalDateTime(lastSync, 'last_sync');

    return withTransaction(async (connection) => {
        const license = await requireLicense(connection, normalizedLicenseCode, { forUpdate: true });
        const device = await requireDevice(connection, license.id, normalizedDeviceToken, { forUpdate: true });
        const nextLastSync = normalizedLastSync || toMysqlDateTime(new Date());

        await updateDevice(connection, device.id, {
            lastSync: nextLastSync,
        });

        return buildLicenseStateResponse(secretKey, normalizedLicenseCode, normalizedDeviceToken, license);
    });
}

export async function createAdminLicense(payload = {}) {
    await ensureDatabase();

    return withTransaction(async (connection) => {
        const planId = requirePositiveInteger(payload.plan_id, 'plan_id');
        const plan = await requirePlan(connection, planId);
        const requestedLicenseCode = optionalLicenseCode(payload.license_code);
        const licenseCode = requestedLicenseCode || await generateUniqueLicenseCode(connection);
        const maxDevices = optionalPositiveInteger(payload.max_devices, 'max_devices') ?? plan.maxDevices;
        const expiry = optionalDateOnly(payload.expiry, 'expiry') || addPlanDuration(new Date(), plan.duration);

        const existing = await findLicenseByCode(connection, licenseCode, { forUpdate: true });
        if (existing) {
            throw httpError(409, 'License code already exists.', {
                code: 'LICENSE_CODE_EXISTS',
            });
        }

        await createLicense(connection, {
            licenseCode,
            expiry,
            maxDevices,
            status: 'active',
            revoked: false,
            planId: plan.id,
        });

        return requireAdminLicense(connection, licenseCode, { forUpdate: true });
    });
}

export async function revokeAdminLicense(payload = {}) {
    await ensureDatabase();

    return withTransaction(async (connection) => {
        const licenseCode = requireLicenseCode(payload.license_code);
        await requireLicense(connection, licenseCode, { forUpdate: true });

        await updateLicenseByCode(connection, licenseCode, {
            status: 'revoked',
            revoked: true,
        });

        return requireAdminLicense(connection, licenseCode, { forUpdate: true });
    });
}

export async function extendAdminLicense(payload = {}) {
    await ensureDatabase();

    return withTransaction(async (connection) => {
        const licenseCode = requireLicenseCode(payload.license_code);
        const days = requirePositiveInteger(payload.days ?? payload.duration_days ?? 30, 'days');
        const license = await requireLicense(connection, licenseCode, { forUpdate: true });
        const nextExpiry = extendExpiryDate(license.expiry, days);

        await updateLicenseByCode(connection, licenseCode, {
            expiry: nextExpiry,
            status: license.revoked ? 'revoked' : 'active',
        });

        return requireAdminLicense(connection, licenseCode, { forUpdate: true });
    });
}

export async function createAdminPlan(payload = {}) {
    await ensureDatabase();

    return withTransaction(async (connection) => {
        const name = requireText(payload.name, 'name');
        const price = requirePrice(payload.price, 'price');
        const duration = requireEnum(payload.duration, 'duration', ['monthly', 'yearly']);
        const maxDevices = requirePositiveInteger(payload.max_devices, 'max_devices');

        return createPlan(connection, {
            name,
            price,
            duration,
            maxDevices,
        });
    });
}

export async function createAdminPayment(payload = {}) {
    await ensureDatabase();

    return withTransaction(async (connection) => {
        const licenseCode = requireLicenseCode(payload.license_code);
        const amount = requirePrice(payload.amount, 'amount');
        const status = requireEnum(payload.status ?? 'paid', 'status', ['pending', 'paid', 'failed', 'refunded']);
        const paidAt = status === 'paid'
            ? (optionalDateTime(payload.paid_at, 'paid_at') || toMysqlDateTime(new Date()))
            : null;
        const license = await requireLicense(connection, licenseCode, { forUpdate: true });

        const payment = await createPayment(connection, {
            licenseId: license.id,
            amount,
            status,
            paidAt,
        });

        return {
            ...payment,
            licenseCode,
            planName: license.planName,
        };
    });
}

function asyncHandler(handler) {
    return function wrappedHandler(request, response, next) {
        Promise.resolve(handler(request, response, next)).catch(next);
    };
}

function createLimiter({ windowMs, max, message }) {
    return rateLimit({
        windowMs,
        max,
        standardHeaders: true,
        legacyHeaders: false,
        handler: (_request, response) => {
            response.status(429).json({
                error: message,
                code: 'RATE_LIMITED',
            });
        },
    });
}

function httpError(statusCode, message, details = {}) {
    const error = new Error(message);
    error.statusCode = statusCode;
    error.details = details;
    return error;
}

function normalizeError(error) {
    if (error.statusCode) {
        return error;
    }

    if (error.code === 'ER_DUP_ENTRY') {
        return httpError(409, 'A duplicate record already exists.', {
            code: 'DUPLICATE_ENTRY',
        });
    }

    if (error.code === 'ER_NO_REFERENCED_ROW_2') {
        return httpError(400, 'Referenced record does not exist.', {
            code: 'INVALID_REFERENCE',
        });
    }

    return httpError(500, error.message || 'Internal server error.');
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

function requireLicenseCode(value) {
    const normalized = normalizeLicenseCode(value);

    if (!normalized) {
        throw httpError(400, 'license_code is required.', {
            code: 'VALIDATION_ERROR',
            field: 'license_code',
        });
    }

    return normalized;
}

function optionalLicenseCode(value) {
    const normalized = normalizeLicenseCode(value);
    return normalized || null;
}

function normalizeLicenseCode(value) {
    const normalized = normalizeText(value).toUpperCase();

    if (!normalized) {
        return '';
    }

    if (!/^[A-Z0-9-]{6,64}$/.test(normalized)) {
        throw httpError(400, 'license_code must contain only letters, numbers, and hyphens.', {
            code: 'VALIDATION_ERROR',
            field: 'license_code',
        });
    }

    return normalized;
}

function requirePositiveInteger(value, field) {
    const parsed = Number.parseInt(String(value ?? ''), 10);

    if (!Number.isInteger(parsed) || parsed <= 0) {
        throw httpError(400, `${field} must be a positive integer.`, {
            code: 'VALIDATION_ERROR',
            field,
        });
    }

    return parsed;
}

function optionalPositiveInteger(value, field) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    return requirePositiveInteger(value, field);
}

function requirePrice(value, field) {
    const parsed = Number.parseFloat(String(value ?? ''));

    if (!Number.isFinite(parsed) || parsed <= 0) {
        throw httpError(400, `${field} must be a positive number.`, {
            code: 'VALIDATION_ERROR',
            field,
        });
    }

    return Number(parsed.toFixed(2));
}

function requireEnum(value, field, allowedValues) {
    const normalized = normalizeText(value).toLowerCase();

    if (!allowedValues.includes(normalized)) {
        throw httpError(400, `${field} must be one of: ${allowedValues.join(', ')}.`, {
            code: 'VALIDATION_ERROR',
            field,
        });
    }

    return normalized;
}

function optionalDateOnly(value, field) {
    const normalized = normalizeText(value);

    if (!normalized) {
        return null;
    }

    if (!/^\d{4}-\d{2}-\d{2}$/.test(normalized)) {
        throw httpError(400, `${field} must be a valid YYYY-MM-DD date.`, {
            code: 'VALIDATION_ERROR',
            field,
        });
    }

    return normalized;
}

function optionalDateTime(value, field) {
    const normalized = normalizeText(value);

    if (!normalized) {
        return null;
    }

    const date = new Date(normalized);

    if (Number.isNaN(date.valueOf())) {
        throw httpError(400, `${field} must be a valid datetime string.`, {
            code: 'VALIDATION_ERROR',
            field,
        });
    }

    return toMysqlDateTime(date);
}

function normalizeText(value) {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value).trim();
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
    const normalized = normalizeText(expiry);

    if (!/^\d{4}-\d{2}-\d{2}$/.test(normalized)) {
        throw httpError(500, 'License has an invalid expiry value.', {
            code: 'INVALID_LICENSE_CONFIGURATION',
        });
    }

    const [year, month, day] = normalized.split('-').map(Number);
    const expiryTime = Date.UTC(year, month - 1, day, 23, 59, 59, 999);

    return Date.now() > expiryTime;
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

function resolveSecret(secret = process.env.SECRET) {
    const normalizedSecret = normalizeText(secret);

    if (!normalizedSecret) {
        throw new Error('Missing required SECRET environment variable.');
    }

    return normalizedSecret;
}

function resolvePluginInfo() {
    return {
        version: normalizeText(process.env.PLUGIN_VERSION) || '1.0.1',
        url: normalizeText(process.env.PLUGIN_DOWNLOAD_URL) || 'https://your-server.com/downloads/plugin.zip',
    };
}

async function requireLicense(connection, licenseCode, { forUpdate = false } = {}) {
    const license = await findLicenseByCode(connection, licenseCode, { forUpdate });

    if (!license) {
        throw httpError(404, 'License not found.', { code: 'LICENSE_NOT_FOUND' });
    }

    if (!Number.isInteger(license.maxDevices) || license.maxDevices <= 0) {
        throw httpError(500, 'License has an invalid max_devices value.', {
            code: 'INVALID_LICENSE_CONFIGURATION',
        });
    }

    return license;
}

async function requireAdminLicense(connection, licenseCode, { forUpdate = false } = {}) {
    const items = await listLicenses(connection);
    const item = items.find((entry) => entry.licenseCode === licenseCode);

    if (item) {
        return item;
    }

    const license = await requireLicense(connection, licenseCode, { forUpdate });
    return {
        ...license,
        devicesCount: (await listDevicesForLicense(connection, license.id)).length,
        paymentsCount: 0,
        paidTotal: 0,
        lastPaidAt: null,
    };
}

async function requirePlan(connection, planId) {
    const plan = await findPlanById(connection, planId);

    if (!plan) {
        throw httpError(404, 'Plan not found.', { code: 'PLAN_NOT_FOUND' });
    }

    return plan;
}

async function requireDevice(connection, licenseId, deviceToken, { forUpdate = false } = {}) {
    const device = await findDeviceByToken(connection, licenseId, deviceToken, { forUpdate });

    if (!device) {
        throw httpError(401, 'Device token is invalid.', { code: 'INVALID_DEVICE_TOKEN' });
    }

    return device;
}

async function generateUniqueLicenseCode(connection) {
    const year = new Date().getUTCFullYear();

    for (let attempt = 0; attempt < 8; attempt += 1) {
        const candidate = `TS-${year}-${crypto.randomBytes(3).toString('hex').toUpperCase()}`;
        const existing = await findLicenseByCode(connection, candidate, { forUpdate: true });

        if (!existing) {
            return candidate;
        }
    }

    throw httpError(500, 'Failed to generate a unique license code.', {
        code: 'LICENSE_CODE_GENERATION_FAILED',
    });
}

function addPlanDuration(date, duration) {
    const next = new Date(Date.UTC(
        date.getUTCFullYear(),
        date.getUTCMonth(),
        date.getUTCDate(),
    ));

    if (duration === 'yearly') {
        next.setUTCFullYear(next.getUTCFullYear() + 1);
    } else {
        next.setUTCMonth(next.getUTCMonth() + 1);
    }

    return next.toISOString().slice(0, 10);
}

function extendExpiryDate(expiry, days) {
    const today = new Date();
    const currentExpiry = new Date(`${expiry}T00:00:00.000Z`);
    const base = currentExpiry.valueOf() > Date.now() ? currentExpiry : new Date(Date.UTC(
        today.getUTCFullYear(),
        today.getUTCMonth(),
        today.getUTCDate(),
    ));

    base.setUTCDate(base.getUTCDate() + days);
    return base.toISOString().slice(0, 10);
}

function summarizeLicenses(items) {
    const stats = {
        totalLicenses: items.length,
        activeLicenses: 0,
        expiredLicenses: 0,
        revokedLicenses: 0,
        totalDevices: 0,
        monthlyPlans: 0,
        yearlyPlans: 0,
        revenueCollected: 0,
    };

    for (const item of items) {
        const status = resolveLicenseStatus(item);

        if (status === 'active') stats.activeLicenses += 1;
        if (status === 'expired') stats.expiredLicenses += 1;
        if (status === 'revoked') stats.revokedLicenses += 1;

        stats.totalDevices += Number(item.devicesCount ?? 0);
        stats.revenueCollected += Number(item.paidTotal ?? 0);

        if (item.planDuration === 'monthly') stats.monthlyPlans += 1;
        if (item.planDuration === 'yearly') stats.yearlyPlans += 1;
    }

    return stats;
}

function summarizePayments(items) {
    return items.reduce((summary, item) => {
        summary.totalPayments += 1;
        summary.totalAmount += Number(item.amount ?? 0);

        if (item.status === 'paid') summary.paid += 1;
        if (item.status === 'pending') summary.pending += 1;
        if (item.status === 'failed') summary.failed += 1;
        if (item.status === 'refunded') summary.refunded += 1;

        return summary;
    }, {
        totalPayments: 0,
        totalAmount: 0,
        paid: 0,
        pending: 0,
        failed: 0,
        refunded: 0,
    });
}

function requestContext(request) {
    return {
        ip: request.ip,
        method: request.method,
        path: request.path,
        userAgent: request.get('user-agent') || null,
    };
}

function digestFingerprint(fingerprint) {
    return normalizeText(fingerprint)
        ? crypto.createHash('sha256').update(normalizeText(fingerprint), 'utf8').digest('hex').slice(0, 16)
        : null;
}

function toMysqlDateTime(date) {
    return date.toISOString().slice(0, 19).replace('T', ' ');
}

function parsePositiveInteger(value, fallback) {
    const parsed = Number.parseInt(String(value ?? ''), 10);
    return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback;
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
    startServer().catch(async (error) => {
        await logError('startup.failure', error);
        process.exit(1);
    });
}
