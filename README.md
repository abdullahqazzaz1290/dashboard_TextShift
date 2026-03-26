# TextShift SaaS Licensing System

Production-ready Node.js SaaS licensing platform for Photoshop UXP workflows, including admin dashboard UI, plan management, payments, and plugin update delivery.

## Scripts

```bash
npm run dev
npm run build
npm start
npm run seed
```

## Environment

Create `.env` from `.env.example` and set:

```bash
SECRET=CHANGE_THIS_SECRET
MYSQLHOST=127.0.0.1
MYSQLPORT=3306
MYSQLUSER=root
MYSQLPASSWORD=change_this_password
MYSQLDATABASE=textshift_licensing
PLUGIN_VERSION=1.0.1
PLUGIN_DOWNLOAD_URL=https://your-server.com/downloads/plugin.zip
```

## API

- `POST /api/license/activate`
- `POST /api/license/validate`
- `POST /api/license/sync`
- `POST /api/admin/create-license`
- `POST /api/admin/revoke-license`
- `GET /api/admin/licenses`
- `GET /api/plugin/version`

## Storage

MySQL stores licenses, devices, plans, and payments. The API creates and extends the schema automatically on startup.
