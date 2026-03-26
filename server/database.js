import mysql from 'mysql2/promise';

let poolPromise;
let schemaPromise;

export async function ensureDatabase() {
    if (!schemaPromise) {
        schemaPromise = ensureDatabaseOnce().catch((error) => {
            schemaPromise = null;
            throw error;
        });
    }

    return schemaPromise;
}

export async function pingDatabase() {
    const pool = await getPool();
    await pool.query('SELECT 1');
}

export async function withTransaction(callback) {
    const pool = await getPool();
    const connection = await pool.getConnection();

    try {
        await connection.beginTransaction();
        const result = await callback(connection);
        await connection.commit();
        return result;
    } catch (error) {
        await connection.rollback().catch(() => {});
        throw error;
    } finally {
        connection.release();
    }
}

export async function findLicenseByCode(executor, licenseCode, { forUpdate = false } = {}) {
    const runner = executor ?? await getPool();
    const [rows] = await runner.execute(
        `SELECT
            l.id,
            l.license_code,
            l.expiry,
            l.max_devices,
            l.status,
            l.revoked,
            l.plan_id,
            l.created_at,
            l.updated_at,
            p.name AS plan_name,
            p.price AS plan_price,
            p.duration AS plan_duration
        FROM licenses l
        LEFT JOIN plans p ON p.id = l.plan_id
        WHERE l.license_code = ?${forUpdate ? ' FOR UPDATE' : ''}`,
        [licenseCode],
    );

    return rows[0] ? mapLicense(rows[0]) : null;
}

export async function listLicenses(executor) {
    const runner = executor ?? await getPool();
    const [rows] = await runner.execute(
        `SELECT
            l.id,
            l.license_code,
            l.expiry,
            l.max_devices,
            l.status,
            l.revoked,
            l.plan_id,
            l.created_at,
            l.updated_at,
            p.name AS plan_name,
            p.price AS plan_price,
            p.duration AS plan_duration,
            (
                SELECT COUNT(*)
                FROM license_devices d
                WHERE d.license_id = l.id
            ) AS devices_count,
            (
                SELECT COUNT(*)
                FROM payments pay
                WHERE pay.license_id = l.id
            ) AS payments_count,
            (
                SELECT COALESCE(SUM(pay.amount), 0)
                FROM payments pay
                WHERE pay.license_id = l.id AND pay.status = 'paid'
            ) AS paid_total,
            (
                SELECT MAX(pay.paid_at)
                FROM payments pay
                WHERE pay.license_id = l.id AND pay.status = 'paid'
            ) AS last_paid_at
        FROM licenses l
        LEFT JOIN plans p ON p.id = l.plan_id
        ORDER BY l.created_at DESC, l.id DESC`,
    );

    return rows.map(mapAdminLicense);
}

export async function createLicense(executor, {
    licenseCode,
    expiry,
    maxDevices,
    status = 'active',
    revoked = false,
    planId = null,
}) {
    const runner = executor ?? await getPool();
    const [result] = await runner.execute(
        `INSERT INTO licenses (
            license_code,
            expiry,
            max_devices,
            status,
            revoked,
            plan_id
        ) VALUES (?, ?, ?, ?, ?, ?)`,
        [licenseCode, expiry, maxDevices, status, revoked ? 1 : 0, planId],
    );

    return {
        id: Number(result.insertId),
        licenseCode,
        expiry,
        maxDevices,
        status,
        revoked: Boolean(revoked),
        planId: planId === null ? null : Number(planId),
    };
}

export async function updateLicenseByCode(executor, licenseCode, fields) {
    const runner = executor ?? await getPool();
    const updates = [];
    const values = [];

    if (Object.hasOwn(fields, 'expiry')) {
        updates.push('expiry = ?');
        values.push(fields.expiry);
    }

    if (Object.hasOwn(fields, 'maxDevices')) {
        updates.push('max_devices = ?');
        values.push(fields.maxDevices);
    }

    if (Object.hasOwn(fields, 'status')) {
        updates.push('status = ?');
        values.push(fields.status);
    }

    if (Object.hasOwn(fields, 'revoked')) {
        updates.push('revoked = ?');
        values.push(fields.revoked ? 1 : 0);
    }

    if (Object.hasOwn(fields, 'planId')) {
        updates.push('plan_id = ?');
        values.push(fields.planId);
    }

    if (updates.length === 0) {
        return;
    }

    values.push(licenseCode);
    await runner.execute(`UPDATE licenses SET ${updates.join(', ')} WHERE license_code = ?`, values);
}

export async function listDevicesForLicense(executor, licenseId) {
    const runner = executor ?? await getPool();
    const [rows] = await runner.execute(
        `SELECT
            id,
            license_id,
            device_id,
            fingerprint,
            token,
            activated_at,
            last_sync,
            created_at,
            updated_at
        FROM license_devices
        WHERE license_id = ?
        ORDER BY id ASC`,
        [licenseId],
    );

    return rows.map(mapDevice);
}

export async function findDeviceByToken(executor, licenseId, deviceToken, { forUpdate = false } = {}) {
    const runner = executor ?? await getPool();
    const [rows] = await runner.execute(
        `SELECT
            id,
            license_id,
            device_id,
            fingerprint,
            token,
            activated_at,
            last_sync,
            created_at,
            updated_at
        FROM license_devices
        WHERE license_id = ? AND token = ?${forUpdate ? ' FOR UPDATE' : ''}`,
        [licenseId, deviceToken],
    );

    return rows[0] ? mapDevice(rows[0]) : null;
}

export async function createDevice(executor, {
    licenseId,
    deviceId,
    fingerprint,
    token,
    activatedAt,
    lastSync,
}) {
    const runner = executor ?? await getPool();
    const [result] = await runner.execute(
        `INSERT INTO license_devices (
            license_id,
            device_id,
            fingerprint,
            token,
            activated_at,
            last_sync
        ) VALUES (?, ?, ?, ?, ?, ?)`,
        [licenseId, deviceId, fingerprint, token, activatedAt, lastSync],
    );

    return {
        id: Number(result.insertId),
        licenseId,
        deviceId,
        fingerprint,
        token,
        activatedAt,
        lastSync,
    };
}

export async function updateDevice(executor, deviceRecordId, fields) {
    const runner = executor ?? await getPool();
    const updates = [];
    const values = [];

    if (Object.hasOwn(fields, 'deviceId')) {
        updates.push('device_id = ?');
        values.push(fields.deviceId);
    }

    if (Object.hasOwn(fields, 'fingerprint')) {
        updates.push('fingerprint = ?');
        values.push(fields.fingerprint);
    }

    if (Object.hasOwn(fields, 'token')) {
        updates.push('token = ?');
        values.push(fields.token);
    }

    if (Object.hasOwn(fields, 'activatedAt')) {
        updates.push('activated_at = ?');
        values.push(fields.activatedAt);
    }

    if (Object.hasOwn(fields, 'lastSync')) {
        updates.push('last_sync = ?');
        values.push(fields.lastSync);
    }

    if (updates.length === 0) {
        return;
    }

    values.push(deviceRecordId);
    await runner.execute(`UPDATE license_devices SET ${updates.join(', ')} WHERE id = ?`, values);
}

export async function listPlans(executor) {
    const runner = executor ?? await getPool();
    const [rows] = await runner.execute(
        `SELECT
            id,
            name,
            price,
            duration,
            max_devices,
            created_at,
            updated_at
        FROM plans
        ORDER BY created_at ASC, id ASC`,
    );

    return rows.map(mapPlan);
}

export async function findPlanById(executor, planId) {
    const runner = executor ?? await getPool();
    const [rows] = await runner.execute(
        `SELECT
            id,
            name,
            price,
            duration,
            max_devices,
            created_at,
            updated_at
        FROM plans
        WHERE id = ?`,
        [planId],
    );

    return rows[0] ? mapPlan(rows[0]) : null;
}

export async function findPlanByName(executor, planName) {
    const runner = executor ?? await getPool();
    const [rows] = await runner.execute(
        `SELECT
            id,
            name,
            price,
            duration,
            max_devices,
            created_at,
            updated_at
        FROM plans
        WHERE name = ?`,
        [planName],
    );

    return rows[0] ? mapPlan(rows[0]) : null;
}

export async function createPlan(executor, {
    name,
    price,
    duration,
    maxDevices,
}) {
    const runner = executor ?? await getPool();
    const [result] = await runner.execute(
        `INSERT INTO plans (
            name,
            price,
            duration,
            max_devices
        ) VALUES (?, ?, ?, ?)`,
        [name, price, duration, maxDevices],
    );

    return {
        id: Number(result.insertId),
        name,
        price: Number(price),
        duration,
        maxDevices,
    };
}

export async function listPayments(executor) {
    const runner = executor ?? await getPool();
    const [rows] = await runner.execute(
        `SELECT
            pay.id,
            pay.license_id,
            pay.amount,
            pay.status,
            pay.paid_at,
            pay.created_at,
            l.license_code,
            p.name AS plan_name
        FROM payments pay
        INNER JOIN licenses l ON l.id = pay.license_id
        LEFT JOIN plans p ON p.id = l.plan_id
        ORDER BY COALESCE(pay.paid_at, pay.created_at) DESC, pay.id DESC`,
    );

    return rows.map(mapPayment);
}

export async function createPayment(executor, {
    licenseId,
    amount,
    status,
    paidAt = null,
}) {
    const runner = executor ?? await getPool();
    const [result] = await runner.execute(
        `INSERT INTO payments (
            license_id,
            amount,
            status,
            paid_at
        ) VALUES (?, ?, ?, ?)`,
        [licenseId, amount, status, paidAt],
    );

    return {
        id: Number(result.insertId),
        licenseId,
        amount: Number(amount),
        status,
        paidAt,
    };
}

async function ensureDatabaseOnce() {
    const pool = await getPool();

    await pool.query(`
        CREATE TABLE IF NOT EXISTS plans (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            price DECIMAL(10, 2) NOT NULL,
            duration ENUM('monthly', 'yearly') NOT NULL,
            max_devices INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY plans_name_unique (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);

    await pool.query(`
        CREATE TABLE IF NOT EXISTS licenses (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            license_code VARCHAR(191) NOT NULL,
            expiry DATE NOT NULL,
            max_devices INT UNSIGNED NOT NULL DEFAULT 1,
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            revoked TINYINT(1) NOT NULL DEFAULT 0,
            plan_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY licenses_license_code_unique (license_code),
            KEY licenses_plan_id_index (plan_id),
            CONSTRAINT licenses_plan_id_foreign
                FOREIGN KEY (plan_id) REFERENCES plans (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);

    await ensureColumn(pool, 'licenses', 'status', `VARCHAR(32) NOT NULL DEFAULT 'active' AFTER max_devices`);
    await ensureColumn(pool, 'licenses', 'revoked', `TINYINT(1) NOT NULL DEFAULT 0 AFTER status`);
    await ensureColumn(pool, 'licenses', 'plan_id', 'BIGINT UNSIGNED NULL AFTER revoked');
    await ensureColumn(pool, 'licenses', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    await ensureColumn(pool, 'licenses', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    await ensureIndex(pool, 'licenses', 'licenses_plan_id_index', 'plan_id');
    await ensureForeignKey(
        pool,
        'licenses',
        'licenses_plan_id_foreign',
        'plan_id',
        'plans',
        'id',
        'ON DELETE SET NULL',
    );

    await pool.query(`
        CREATE TABLE IF NOT EXISTS license_devices (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            license_id BIGINT UNSIGNED NOT NULL,
            device_id VARCHAR(191) NOT NULL,
            fingerprint VARCHAR(191) NOT NULL,
            token VARCHAR(191) NOT NULL,
            activated_at DATETIME NOT NULL,
            last_sync DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY license_devices_token_unique (token),
            UNIQUE KEY license_devices_license_device_unique (license_id, device_id),
            UNIQUE KEY license_devices_license_fingerprint_unique (license_id, fingerprint),
            CONSTRAINT license_devices_license_id_foreign
                FOREIGN KEY (license_id) REFERENCES licenses (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);

    await ensureColumn(pool, 'license_devices', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    await ensureColumn(pool, 'license_devices', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

    await pool.query(`
        CREATE TABLE IF NOT EXISTS payments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            license_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            status VARCHAR(32) NOT NULL,
            paid_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY payments_license_id_index (license_id),
            CONSTRAINT payments_license_id_foreign
                FOREIGN KEY (license_id) REFERENCES licenses (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);

    await ensureColumn(pool, 'payments', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
}

async function ensureColumn(executor, tableName, columnName, definition) {
    if (await hasColumn(executor, tableName, columnName)) {
        return;
    }

    await executor.query(`ALTER TABLE \`${tableName}\` ADD COLUMN \`${columnName}\` ${definition}`);
}

async function ensureIndex(executor, tableName, indexName, columnExpression) {
    if (await hasIndex(executor, tableName, indexName)) {
        return;
    }

    await executor.query(`ALTER TABLE \`${tableName}\` ADD INDEX \`${indexName}\` (${columnExpression})`);
}

async function ensureForeignKey(executor, tableName, constraintName, columnName, referenceTable, referenceColumn, deleteClause) {
    if (await hasConstraint(executor, tableName, constraintName)) {
        return;
    }

    await executor.query(
        `ALTER TABLE \`${tableName}\`
         ADD CONSTRAINT \`${constraintName}\`
         FOREIGN KEY (\`${columnName}\`) REFERENCES \`${referenceTable}\` (\`${referenceColumn}\`) ${deleteClause}`,
    );
}

async function hasColumn(executor, tableName, columnName) {
    const [rows] = await executor.execute(
        `SELECT 1
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
         LIMIT 1`,
        [tableName, columnName],
    );

    return rows.length > 0;
}

async function hasIndex(executor, tableName, indexName) {
    const [rows] = await executor.execute(
        `SELECT 1
         FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND INDEX_NAME = ?
         LIMIT 1`,
        [tableName, indexName],
    );

    return rows.length > 0;
}

async function hasConstraint(executor, tableName, constraintName) {
    const [rows] = await executor.execute(
        `SELECT 1
         FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND CONSTRAINT_NAME = ?
         LIMIT 1`,
        [tableName, constraintName],
    );

    return rows.length > 0;
}

async function getPool() {
    if (!poolPromise) {
        poolPromise = Promise.resolve(createPool());
    }

    return poolPromise;
}

function createPool() {
    const config = resolveDatabaseConfig();

    return mysql.createPool({
        ...config,
        connectionLimit: parsePositiveInteger(process.env.DB_CONNECTION_LIMIT, 10),
        waitForConnections: true,
        queueLimit: 0,
        enableKeepAlive: true,
        keepAliveInitialDelay: 0,
        timezone: 'Z',
        dateStrings: true,
    });
}

function resolveDatabaseConfig() {
    const connectionUrl = normalizeText(process.env.DATABASE_URL) || normalizeText(process.env.MYSQL_URL);

    if (connectionUrl) {
        return configFromUrl(connectionUrl);
    }

    return buildConfig({
        host: requireEnv('MYSQLHOST'),
        port: parsePositiveInteger(process.env.MYSQLPORT, 3306),
        user: requireEnv('MYSQLUSER'),
        password: process.env.MYSQLPASSWORD ?? '',
        database: requireEnv('MYSQLDATABASE'),
    });
}

function configFromUrl(connectionUrl) {
    const url = new URL(connectionUrl);

    if (!['mysql:', 'mysql2:'].includes(url.protocol)) {
        throw new Error('DATABASE_URL/MYSQL_URL must use the mysql protocol.');
    }

    return buildConfig({
        host: decodeURIComponent(url.hostname),
        port: parsePositiveInteger(url.port, 3306),
        user: decodeURIComponent(url.username),
        password: decodeURIComponent(url.password),
        database: decodeURIComponent(url.pathname.replace(/^\/+/, '')),
    });
}

function buildConfig({ host, port, user, password, database }) {
    return {
        host,
        port,
        user,
        password,
        database,
        ssl: resolveSslConfig(),
    };
}

function resolveSslConfig() {
    const flag = normalizeText(process.env.MYSQL_SSL).toLowerCase();

    if (flag === 'true' || flag === '1' || flag === 'required') {
        return { rejectUnauthorized: false };
    }

    return undefined;
}

function mapLicense(row) {
    return {
        id: Number(row.id),
        licenseCode: row.license_code,
        expiry: row.expiry,
        maxDevices: Number(row.max_devices),
        status: row.status,
        revoked: Boolean(row.revoked),
        planId: row.plan_id === null ? null : Number(row.plan_id),
        planName: row.plan_name ?? null,
        planPrice: row.plan_price === null || row.plan_price === undefined ? null : Number(row.plan_price),
        planDuration: row.plan_duration ?? null,
        createdAt: row.created_at ?? null,
        updatedAt: row.updated_at ?? null,
    };
}

function mapAdminLicense(row) {
    return {
        ...mapLicense(row),
        devicesCount: Number(row.devices_count ?? 0),
        paymentsCount: Number(row.payments_count ?? 0),
        paidTotal: Number(row.paid_total ?? 0),
        lastPaidAt: row.last_paid_at ?? null,
    };
}

function mapDevice(row) {
    return {
        id: Number(row.id),
        licenseId: Number(row.license_id),
        deviceId: row.device_id,
        fingerprint: row.fingerprint,
        token: row.token,
        activatedAt: row.activated_at,
        lastSync: row.last_sync,
        createdAt: row.created_at ?? null,
        updatedAt: row.updated_at ?? null,
    };
}

function mapPlan(row) {
    return {
        id: Number(row.id),
        name: row.name,
        price: Number(row.price),
        duration: row.duration,
        maxDevices: Number(row.max_devices),
        createdAt: row.created_at ?? null,
        updatedAt: row.updated_at ?? null,
    };
}

function mapPayment(row) {
    return {
        id: Number(row.id),
        licenseId: Number(row.license_id),
        amount: Number(row.amount),
        status: row.status,
        paidAt: row.paid_at ?? null,
        createdAt: row.created_at ?? null,
        licenseCode: row.license_code,
        planName: row.plan_name ?? null,
    };
}

function requireEnv(name) {
    const value = normalizeText(process.env[name]);

    if (!value) {
        throw new Error(`Missing required ${name} environment variable.`);
    }

    return value;
}

function parsePositiveInteger(value, fallback) {
    const parsed = Number.parseInt(String(value ?? ''), 10);
    return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback;
}

function normalizeText(value) {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value).trim();
}
