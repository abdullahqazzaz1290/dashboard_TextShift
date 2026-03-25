#!/usr/bin/env node

const fs = require('fs');
const os = require('os');
const path = require('path');
const crypto = require('crypto');
const { execFileSync } = require('child_process');

const persistentRuntimeRoot = path.join(os.homedir(), 'Library', 'Application Support', 'CertificateGenerator', 'node-runtime');
const tempRuntimeRoot = path.join(os.tmpdir(), 'cg_node_tools');
const APP_ENTRY_BASENAME = 'TextShift';

let jsxbin;

function clean(value) {
    if (value === null || value === undefined) return '';
    return String(value).replace(/\r/g, '').trim();
}

function sanitizeSegment(value) {
    const cleaned = clean(value)
        .normalize('NFKC')
        .replace(/[^\p{L}\p{N}\s_-]/gu, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '')
        .toLowerCase();

    return cleaned || 'customer';
}

function sanitizeAsciiSegment(value) {
    const cleaned = clean(value)
        .normalize('NFKD')
        .replace(/[^\x20-\x7e]/g, '')
        .replace(/[^A-Za-z0-9\s_-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '')
        .toLowerCase();

    return cleaned || 'customer';
}

function ensureDir(dir) {
    fs.mkdirSync(dir, { recursive: true });
}

function sha256Hex(value) {
    return crypto
        .createHash('sha256')
        .update(clean(value), 'utf8')
        .digest('hex');
}

function deriveActivationSecret(seed, licenseCode) {
    return crypto
        .createHmac('sha256', clean(seed))
        .update(clean(licenseCode), 'utf8')
        .digest('hex');
}

function formatMoney(value) {
    const amount = Number.parseFloat(value || 0);
    if (!Number.isFinite(amount)) {
        return '0.00';
    }

    return amount.toFixed(2);
}

function padCopyNumber(index) {
    return String(index).padStart(2, '0');
}

function resolveCopyLicenseCode(baseLicenseCode, copyNumber, copiesCount) {
    if (copiesCount <= 1) {
        return clean(baseLicenseCode);
    }

    return `${clean(baseLicenseCode)}-${padCopyNumber(copyNumber)}`;
}

function resolveCopyFolderName(copyNumber, copiesCount) {
    if (copiesCount <= 1) {
        return 'root';
    }

    return String(copyNumber);
}

function toAsciiJsStringLiteral(value) {
    const text = clean(value);
    let output = '"';

    for (const char of text) {
        const codePoint = char.codePointAt(0);

        if (char === '\\') {
            output += '\\\\';
            continue;
        }

        if (char === '"') {
            output += '\\"';
            continue;
        }

        if (char === '\n') {
            output += '\\n';
            continue;
        }

        if (char === '\r') {
            output += '\\r';
            continue;
        }

        if (char === '\t') {
            output += '\\t';
            continue;
        }

        if (codePoint >= 0x20 && codePoint <= 0x7e) {
            output += char;
            continue;
        }

        if (codePoint <= 0xffff) {
            output += `\\u${codePoint.toString(16).padStart(4, '0')}`;
            continue;
        }

        const offset = codePoint - 0x10000;
        const high = 0xd800 + (offset >> 10);
        const low = 0xdc00 + (offset & 0x3ff);
        output += `\\u${high.toString(16).padStart(4, '0')}\\u${low.toString(16).padStart(4, '0')}`;
    }

    output += '"';
    return output;
}

function pruneAppleDouble(dir) {
    if (!fs.existsSync(dir)) {
        return;
    }

    const entries = fs.readdirSync(dir, { withFileTypes: true });
    entries.forEach((entry) => {
        const fullPath = path.join(dir, entry.name);

        if (entry.name.indexOf('._') === 0) {
            fs.rmSync(fullPath, { recursive: true, force: true });
            return;
        }

        if (entry.isDirectory()) {
            pruneAppleDouble(fullPath);
        }
    });
}

function emptyDir(dir) {
    fs.rmSync(dir, { recursive: true, force: true });
    fs.mkdirSync(dir, { recursive: true });
}

function resolveRuntimeRoot() {
    const candidates = [
        persistentRuntimeRoot,
        tempRuntimeRoot,
    ];

    for (const candidate of candidates) {
        const packageFile = path.join(candidate, 'node_modules', 'jsxbin', 'package.json');
        if (fs.existsSync(packageFile)) {
            return candidate;
        }
    }

    throw new Error('JSXBIN runtime is not installed. Please install or restore the customer packaging runtime first.');
}

function getJsxbinCompiler() {
    if (!jsxbin) {
        jsxbin = require(path.join(resolveRuntimeRoot(), 'node_modules', 'jsxbin'));
    }

    return jsxbin;
}

function copySelectedProjectFiles(projectRoot, packageRoot) {
    const entries = [
        { type: 'file', source: path.join(projectRoot, 'main.jsx'), target: path.join(packageRoot, `${APP_ENTRY_BASENAME}.jsx`) },
        { type: 'dir', source: path.join(projectRoot, 'core'), target: path.join(packageRoot, 'core') },
        { type: 'dir', source: path.join(projectRoot, 'ui'), target: path.join(packageRoot, 'ui') },
    ];

    entries.forEach((entry) => {
        if (!fs.existsSync(entry.source)) {
            throw new Error(`Missing source entry: ${entry.source}`);
        }

        fs.cpSync(entry.source, entry.target, {
            recursive: entry.type === 'dir',
            force: true,
            preserveTimestamps: true,
        });
    });

    pruneAppleDouble(packageRoot);
}

function injectEmbeddedLicenseData(packageRoot, customerName, licenseCode, expiry, activationSecret, handshakeUrl) {
    const mainFile = path.join(packageRoot, `${APP_ENTRY_BASENAME}.jsx`);
    let content = fs.readFileSync(mainFile, 'utf8');

    content = content.replace(
        /var EMBEDDED_LICENSE_CUSTOMER = ".*?";/,
        `var EMBEDDED_LICENSE_CUSTOMER = ${toAsciiJsStringLiteral(customerName)};`,
    );
    content = content.replace(
        /var EMBEDDED_LICENSE_CODE = ".*?";/,
        `var EMBEDDED_LICENSE_CODE = ${toAsciiJsStringLiteral(licenseCode)};`,
    );
    content = content.replace(
        /var EMBEDDED_LICENSE_EXPIRY = ".*?";/,
        `var EMBEDDED_LICENSE_EXPIRY = ${toAsciiJsStringLiteral(expiry)};`,
    );
    content = content.replace(
        /var EMBEDDED_ACTIVATION_SECRET = ".*?";/,
        `var EMBEDDED_ACTIVATION_SECRET = ${toAsciiJsStringLiteral(activationSecret)};`,
    );
    content = content.replace(
        /var EMBEDDED_HANDSHAKE_URL = ".*?";/,
        `var EMBEDDED_HANDSHAKE_URL = ${toAsciiJsStringLiteral(handshakeUrl)};`,
    );

    fs.writeFileSync(mainFile, content);
}

function writeCopiesManifest(packageRoot, copies, meta) {
    const lines = [
        [
            'copy_number',
            'folder_name',
            'license_code',
            'customer_name',
            'expiry',
            'copies_count',
            'unit_price',
            'total_amount',
        ].join(','),
    ];

    copies.forEach((copy) => {
        lines.push([
            copy.copy_number,
            copy.folder_name,
            copy.license_code,
            `"${String(copy.customer_name).replace(/"/g, '""')}"`,
            copy.expiry,
            meta.copiesCount,
            meta.unitPrice,
            meta.totalAmount,
        ].join(','));
    });

    fs.writeFileSync(path.join(packageRoot, 'invoice.csv'), `\ufeff${lines.join('\n')}`);
}

function collectJsxFiles(dir, results = []) {
    const entries = fs.readdirSync(dir, { withFileTypes: true });

    entries.forEach((entry) => {
        if (entry.name === '.DS_Store' || entry.name.indexOf('._') === 0) {
            return;
        }

        const fullPath = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            collectJsxFiles(fullPath, results);
            return;
        }

        if (/\.jsx$/i.test(entry.name)) {
            results.push(fullPath);
        }
    });

    return results;
}

async function compileToJsxbin(packageRoot) {
    const jsxFiles = collectJsxFiles(packageRoot);
    const outputs = jsxFiles.map((file) => file.replace(/\.jsx$/i, '.jsxbin'));

    if (jsxFiles.length === 0) {
        return { compiledFiles: 0, removedSources: 0 };
    }

    await getJsxbinCompiler()(jsxFiles, outputs);

    jsxFiles.forEach((file) => {
        fs.rmSync(file, { force: true });
    });

    return {
        compiledFiles: outputs.length,
        removedSources: jsxFiles.length,
    };
}

function createArchive(archivesRoot, packageRoot, packageName) {
    ensureDir(archivesRoot);

    const archivePath = path.join(archivesRoot, `${packageName}.zip`);
    if (fs.existsSync(archivePath)) {
        fs.rmSync(archivePath, { force: true });
    }

    execFileSync('/usr/bin/zip', [
        '-rq',
        archivePath,
        packageName,
        '-x',
        '*/._*',
        '*/.DS_Store',
        '*/__MACOSX/*',
        `${packageName}/.license.dat`,
        `${packageName}/.device`,
        `${packageName}/**/.device`,
    ], {
        cwd: path.dirname(packageRoot),
        stdio: 'pipe',
    });

    return archivePath;
}

function createArchiveFromDirectory(archivePath, sourceDir) {
    ensureDir(path.dirname(archivePath));

    if (fs.existsSync(archivePath)) {
        fs.rmSync(archivePath, { force: true });
    }

    execFileSync('/usr/bin/zip', [
        '-rq',
        archivePath,
        '.',
        '-x',
        '*/._*',
        '*.DS_Store',
        '*/.DS_Store',
        '*/__MACOSX/*',
        '.license.dat',
        '.license.lock',
        '.device',
        '*/.license.dat',
        '*/.license.lock',
        '*/.device',
    ], {
        cwd: sourceDir,
        stdio: 'pipe',
    });

    return archivePath;
}

async function main() {
    const inputPath = process.argv[2];
    if (!inputPath) {
        throw new Error('Missing input JSON path.');
    }

    const payload = JSON.parse(fs.readFileSync(inputPath, 'utf8'));
    const projectRoot = path.resolve(payload.projectRoot);
    const deliveryRoot = path.resolve(payload.deliveryRoot);
    const customerName = clean(payload.customerName);
    const licenseCode = clean(payload.licenseCode);
    const activationSeed = clean(payload.activationSeed);
    const handshakeUrl = clean(payload.handshakeUrl);
    const copiesCount = Math.max(1, Number.parseInt(payload.copiesCount || 1, 10) || 1);
    const unitPrice = formatMoney(payload.unitPrice || 0);
    const totalAmount = formatMoney(payload.totalAmount || 0);
    const expiry = clean(payload.expiry);

    if (!customerName || !licenseCode || !expiry || !activationSeed) {
        throw new Error('customerName, licenseCode, expiry, and activationSeed are required.');
    }

    const packageName = `${sanitizeSegment(customerName)}-${sanitizeSegment(licenseCode)}`;
    const buildName = `.build-${sanitizeAsciiSegment(customerName)}-${sanitizeAsciiSegment(licenseCode)}`;
    const packagesRoot = path.join(deliveryRoot, 'packages');
    const archivesRoot = path.join(deliveryRoot, 'archives');
    const packageRoot = path.join(packagesRoot, packageName);
    const buildRoot = path.join(packagesRoot, buildName);

    ensureDir(packagesRoot);
    emptyDir(buildRoot);

    if (fs.existsSync(packageRoot)) {
        fs.rmSync(packageRoot, { recursive: true, force: true });
    }

    const copies = [];

    for (let copyNumber = 1; copyNumber <= copiesCount; copyNumber += 1) {
        const folderName = resolveCopyFolderName(copyNumber, copiesCount);
        const targetRoot = copiesCount <= 1
            ? buildRoot
            : path.join(buildRoot, folderName);
        const copyLicenseCode = resolveCopyLicenseCode(licenseCode, copyNumber, copiesCount);
        const activationSecret = deriveActivationSecret(activationSeed, copyLicenseCode);

        ensureDir(targetRoot);
        copySelectedProjectFiles(projectRoot, targetRoot);
        injectEmbeddedLicenseData(
            targetRoot,
            customerName,
            copyLicenseCode,
            expiry,
            activationSecret,
            handshakeUrl,
        );

        copies.push({
            copy_number: copyNumber,
            folder_name: folderName,
            license_code: copyLicenseCode,
            customer_name: customerName,
            expiry,
            activation_secret_hash: sha256Hex(activationSecret),
        });
    }

    if (copiesCount > 1) {
        writeCopiesManifest(buildRoot, copies, {
            copiesCount,
            unitPrice,
            totalAmount,
        });
    }

    const compileResult = await compileToJsxbin(buildRoot);
    pruneAppleDouble(buildRoot);
    fs.renameSync(buildRoot, packageRoot);
    pruneAppleDouble(packageRoot);
    const archivePath = createArchive(archivesRoot, packageRoot, packageName);
    const copyArchivesRoot = path.join(deliveryRoot, 'copy-archives', packageName);
    const enrichedCopies = copies.map((copy) => {
        if (copiesCount <= 1) {
            return {
                ...copy,
                archive_name: path.basename(archivePath),
                archive_relative: path.join('deliveries', 'archives', path.basename(archivePath)),
            };
        }

        const archiveName = `${padCopyNumber(copy.copy_number)}-${sanitizeAsciiSegment(copy.license_code)}.zip`;
        const copyRoot = path.join(packageRoot, copy.folder_name);
        const copyArchivePath = createArchiveFromDirectory(
            path.join(copyArchivesRoot, archiveName),
            copyRoot,
        );

        return {
            ...copy,
            archive_name: path.basename(copyArchivePath),
            archive_relative: path.join('deliveries', 'copy-archives', packageName, path.basename(copyArchivePath)),
        };
    });

    process.stdout.write(JSON.stringify({
        package_name: packageName,
        archive_name: path.basename(archivePath),
        package_relative: path.join('deliveries', 'packages', packageName),
        archive_relative: path.join('deliveries', 'archives', path.basename(archivePath)),
        compiled_files: compileResult.compiledFiles,
        source_files_removed: compileResult.removedSources,
        copies: enrichedCopies,
    }));
}

main().catch((error) => {
    process.stderr.write((error && error.message) ? error.message : String(error));
    process.exit(1);
});
