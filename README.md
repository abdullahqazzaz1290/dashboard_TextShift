# TextShift Licensing API

Node.js licensing backend for Photoshop UXP-compatible activation workflows, paired with a Vite frontend status page.

## Scripts

```bash
npm run dev
npm run build
npm start
```

## Environment

Create `.env` from `.env.example` and set:

```bash
SECRET=CHANGE_THIS_SECRET
```

## API

- `POST /api/license/activate`
- `POST /api/license/validate`
- `POST /api/license/sync`

## Storage

Licenses are stored in `server/data/licenses.json`.
