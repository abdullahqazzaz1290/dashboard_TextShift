import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const logsDir = path.join(__dirname, 'logs');
const logFile = path.join(logsDir, 'application.log');

let logDirectoryPromise;

export async function logInfo(event, data = {}) {
    await writeLog('info', event, data);
}

export async function logError(event, error, data = {}) {
    await writeLog('error', event, {
        ...data,
        error: serializeError(error),
    });
}

async function writeLog(level, event, data) {
    await ensureLogDirectory();

    const entry = {
        timestamp: new Date().toISOString(),
        level,
        event,
        ...data,
    };

    const line = `${JSON.stringify(entry)}\n`;

    if (level === 'error') {
        console.error(line.trim());
    } else {
        console.log(line.trim());
    }

    await fs.appendFile(logFile, line, 'utf8').catch(() => {});
}

async function ensureLogDirectory() {
    if (!logDirectoryPromise) {
        logDirectoryPromise = fs.mkdir(logsDir, { recursive: true });
    }

    return logDirectoryPromise;
}

function serializeError(error) {
    if (!error) {
        return null;
    }

    return {
        message: error.message || String(error),
        code: error.code ?? null,
        statusCode: error.statusCode ?? null,
        stack: error.stack ?? null,
    };
}
