# Final Deployment Guide - Separated Structure

## Recommended Structure (Frontend & Backend Separated)

```
/home/your_username/
│
├── public_html/              ← Frontend + API endpoint only
│   ├── index.html           ← Frontend entry
│   ├── assets/              ← Frontend assets
│   └── api/                 ← API endpoint (just 2 files)
│       ├── index.php        ← Points to slia_backend
│       └── .htaccess        ← CORS & routing
│
└── slia_backend/             ← Complete Laravel app (secure)
    ├── app/
    ├── bootstrap/
    ├── config/
    ├── database/
    ├── public/              ← Source for api/ files
    ├── resources/
    ├── routes/
    ├── storage/
    ├── vendor/
    ├── .env
    └── .htaccess
```

## Why This Approach?

✅ **More Secure** - Laravel files outside web root
✅ **Clean Separation** - Frontend and backend completely separate
✅ **Professional** - Industry best practice
✅ **Easier Updates** - Update backend without touching frontend

## Deployment Steps

### Step 1: Upload Backend

1. **Create folder** `slia_backend` in your home directory (same level as `public_html`)
2. **Upload entire Laravel project** to `slia_backend/`
3. **Include everything**: app, bootstrap, config, database, public, resources, routes, storage, vendor, .env.example, .htaccess

### Step 2: Setup API Endpoint

1. **Create folder** `public_html/api/`
2. **Copy these 2 files only:**
   - `slia_backend/public/index.php` → `public_html/api/index.php`
   - `slia_backend/public/.htaccess` → `public_html/api/.htaccess`

### Step 3: Configure API Entry Point

**Edit `public_html/api/index.php`:**

Find the deployment configuration section and update your cPanel username:

```php
// === PRODUCTION PATHS (ACTIVE FOR DEPLOYMENT) ===
$basePath = '/home/your_actual_username/slia_backend/';
```

**How to find your username:**
- Look at your cPanel URL or home directory path
- It's usually shown in cPanel file manager
- Example: If path shows `/home/sliauser/`, your username is `sliauser`

### Step 4: Configure Environment

1. **Create `.env`** in `slia_backend/`:
   ```bash
   cp .env.example .env
   ```

2. **Edit `.env`** with your database credentials:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://sliaannualsessions.lk
   API_PREFIX=
   
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=your_cpanel_database_name
   DB_USERNAME=your_cpanel_database_user
   DB_PASSWORD=your_cpanel_database_password
   ```

### Step 5: Set Permissions

```bash
chmod -R 775 slia_backend/storage/
chmod -R 775 slia_backend/bootstrap/cache/
```

### Step 6: Upload Frontend

1. **Build your frontend** (on local machine):
   ```bash
   npm run build
   ```

2. **Upload `dist/` contents** to `public_html/`:
   - `dist/index.html` → `public_html/index.html`
   - `dist/assets/` → `public_html/assets/`
   - All other built files

### Step 7: Test

1. **Test API:**
   ```
   https://sliaannualsessions.lk/api/health
   ```
   Should return JSON with `"status": "ok"`

2. **Test Frontend:**
   ```
   https://sliaannualsessions.lk/
   ```
   Should load your React app

3. **Test Integration:**
   - Go to any registration page
   - Try member verification
   - Should work without errors

## Quick Reference

### File Locations

| What | Where | Notes |
|------|-------|-------|
| Frontend | `public_html/` | index.html, assets/ |
| API Endpoint | `public_html/api/` | Just 2 files |
| Backend App | `slia_backend/` | Complete Laravel |
| Database Config | `slia_backend/.env` | Update credentials |
| Logs | `slia_backend/storage/logs/` | Check for errors |

### Important Paths

**In `public_html/api/index.php`:**
```php
$basePath = '/home/your_username/slia_backend/';
```

**In `slia_backend/.env`:**
```env
APP_URL=https://sliaannualsessions.lk
API_PREFIX=
DB_HOST=localhost
```

## Troubleshooting

### "500 Internal Server Error"
- Check permissions on `slia_backend/storage/` (should be 775)
- Check `slia_backend/.env` exists
- Check logs: `slia_backend/storage/logs/laravel.log`

### "Unexpected token '<'" Error
- Verify `public_html/api/index.php` has correct path
- Check that `$basePath` points to `slia_backend/`
- Ensure username in path is correct

### Database Connection Error
- Verify credentials in `slia_backend/.env`
- Use `localhost` not `127.0.0.1`
- Check database user has privileges

## Deployment Checklist

- [ ] Created `slia_backend/` folder
- [ ] Uploaded Laravel project to `slia_backend/`
- [ ] Created `public_html/api/` folder
- [ ] Copied `index.php` and `.htaccess` to `api/`
- [ ] Updated username in `api/index.php`
- [ ] Created and configured `slia_backend/.env`
- [ ] Set permissions on `storage/` and `bootstrap/cache/`
- [ ] Uploaded frontend to `public_html/`
- [ ] Tested API endpoint (returns JSON)
- [ ] Tested frontend (loads correctly)
- [ ] Tested integration (registration works)

**When all checked, deployment is complete!** 🎉
