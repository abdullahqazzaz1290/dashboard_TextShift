import 'dotenv/config';
import {
    createLicense,
    createPayment,
    createPlan,
    ensureDatabase,
    findLicenseByCode,
    findPlanByName,
    withTransaction,
} from './database.js';

const DEFAULT_PLAN_NAME = 'Starter Monthly';
const TEST_LICENSE_CODE = 'TS-2026-TEST';

await ensureDatabase();

await withTransaction(async (connection) => {
    let plan = await findPlanByName(connection, DEFAULT_PLAN_NAME);
    let createdLicense = false;

    if (!plan) {
        const createdPlan = await createPlan(connection, {
            name: DEFAULT_PLAN_NAME,
            price: 29,
            duration: 'monthly',
            maxDevices: 2,
        });

        plan = {
            ...createdPlan,
            createdAt: null,
            updatedAt: null,
        };
    }

    let license = await findLicenseByCode(connection, TEST_LICENSE_CODE, { forUpdate: true });

    if (!license) {
        const expiry = addDuration(new Date(), plan.duration);

        await createLicense(connection, {
            licenseCode: TEST_LICENSE_CODE,
            expiry,
            maxDevices: plan.maxDevices,
            status: 'active',
            revoked: false,
            planId: plan.id,
        });

        license = await findLicenseByCode(connection, TEST_LICENSE_CODE, { forUpdate: true });
        createdLicense = true;
    }

    if (license && createdLicense) {
        await createPayment(connection, {
            licenseId: license.id,
            amount: plan.price,
            status: 'paid',
            paidAt: toMysqlDateTime(new Date()),
        });
    }
});

console.log(`Seed completed for plan "${DEFAULT_PLAN_NAME}" and license "${TEST_LICENSE_CODE}".`);

function addDuration(date, duration) {
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

function toMysqlDateTime(date) {
    return date.toISOString().slice(0, 19).replace('T', ' ');
}
