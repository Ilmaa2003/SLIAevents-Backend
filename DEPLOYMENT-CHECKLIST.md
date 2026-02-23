# GoDaddy Deployment Checklist

## Pre-Deployment Preparation

### ✅ Step 1: Updated Files Review

The following files have been updated for GoDaddy deployment:

1. **`public/index.php`**
   - ✅ Added deployment path configuration
   - ✅ Easy switch between local and production paths
   - ⚠️ **ACTION REQUIRED:** Before uploading to GoDaddy, uncomment the production path line and add your cPanel username

2. **`.env.example`**
   - ✅ Updated to production settings (`APP_ENV=production`, `APP_DEBUG=false`)
   - ✅ Set `API_PREFIX=` (empty) for subdirectory deployment
   - ✅ Database configuration ready for cPanel
   - ⚠️ **ACTION REQUIRED:** Rename to `.env` on server and update database credentials

3. **`public/.htaccess`**
   - ✅ Already configured with CORS headers
   - ✅ Ready for deployment

---

## Deployment Steps

### 📦 Step 2: Create Deployment Package

**Option A: Via cPanel File Manager (Recommended)**
1. Zip your entire project locally (exclude `node_modules`)
2. Keep `vendor` folder included in the zip

**Option B: Via FTP**
1. Use FileZilla or similar FTP client
2. Upload files directly to the server

---

### 🚀 Step 3: Upload to GoDaddy

1. **Login to cPanel** → File Manager

2. **Create Backend Directory:**
   - Navigate to home directory (usually `/home/your_username/`)
   - Create new folder: `slia_backend`
   - Upload and extract your zip file here

3. **Setup Public Directory:**
   - Navigate to `public_html/`
   - Create new folder: `api`
   - Copy **ONLY** the contents of `slia_backend/public/` to `public_html/api/`
   - Files to copy: `index.php`, `.htaccess`, and any asset files

---

### ⚙️ Step 4: Configure for Production

#### A. Update `public_html/api/index.php`

Find this section:
```php
// === LOCAL DEVELOPMENT PATHS (ACTIVE) ===
$basePath = __DIR__.'/../';

// === PRODUCTION PATHS (UNCOMMENT FOR DEPLOYMENT) ===
// $basePath = '/home/your_username/slia_backend/';
```

Change to (replace `your_username` with your actual cPanel username):
```php
// === LOCAL DEVELOPMENT PATHS (ACTIVE) ===
// $basePath = __DIR__.'/../';

// === PRODUCTION PATHS (UNCOMMENT FOR DEPLOYMENT) ===
$basePath = '/home/your_actual_username/slia_backend/';
```

#### B. Create and Configure `.env` File

1. In `slia_backend/` folder, create a new file named `.env`
2. Copy contents from `.env.example`
3. Update the following settings:

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:YOUR_EXISTING_KEY_FROM_LOCAL_ENV
APP_URL=https://sliaannualsessions.lk
API_PREFIX=

# Frontend URL
FRONTEND_URL=https://sliaannualsessions.lk
SANCTUM_STATEFUL_DOMAINS=sliaannualsessions.lk
SESSION_DOMAIN=.sliaannualsessions.lk

# Database - Get these from cPanel MySQL Databases
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_actual_database_name
DB_USERNAME=your_actual_database_user
DB_PASSWORD=your_actual_database_password

# Queue & Mail Settings
QUEUE_CONNECTION=sync
MAIL_MAILER=smtp
MAIL_HOST=localhost
MAIL_PORT=25
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=noreply@sliaannualsessions.lk
MAIL_FROM_NAME="SLIA Events"
MAIL_ALWAYS_CC=sliaanualevents@gmail.com
```

---

### 🗄️ Step 5: Setup Database

1. **Create Database in cPanel:**
   - cPanel → MySQL Databases
   - Create new database (note the full name with prefix)
   - Create new database user
   - Add user to database with ALL PRIVILEGES

2. **Import Data:**
   - cPanel → phpMyAdmin
   - Select your database
   - Import → Choose your SQL file
   - Click Go

3. **Update `.env`** with the database credentials from Step 1

---

### 🔐 Step 6: Set Permissions

In cPanel File Manager, set these permissions:

1. **`slia_backend/storage/`** → 755 or 775 (recursive)
   - Right-click → Change Permissions
   - Check "Recurse into subdirectories"

2. **`slia_backend/bootstrap/cache/`** → 755 or 775 (recursive)

3. **`slia_backend/.env`** → 644

---

### 🧪 Step 7: Test Your Deployment

#### Test 1: Health Check
Open in browser:
```
https://sliaannualsessions.lk/api/health
```
✅ **Expected:** JSON response with `"status": "ok"`
❌ **If HTML error:** Check `.htaccess` and `index.php` paths

#### Test 2: API Root
```
https://sliaannualsessions.lk/api/
```
✅ **Expected:** JSON with API information

#### Test 3: Inauguration Stats
```
https://sliaannualsessions.lk/api/inauguration/stats
```
✅ **Expected:** JSON with stats (may be empty if no data)
❌ **If database error:** Check `.env` database credentials

#### Test 4: Frontend Integration
1. Open your frontend application
2. Try member verification on any registration page
3. ✅ **Expected:** Member found or "not found" message
4. ❌ **If network error:** Check CORS headers in `.htaccess`

---

### 🐛 Troubleshooting

#### "500 Internal Server Error"
```bash
# Check permissions
# In cPanel Terminal:
cd ~/slia_backend
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/

# Check error log
tail -f storage/logs/laravel.log
```

#### "Unexpected token '<'" Error
- Verify `public_html/api/index.php` has correct paths
- Check that `API_PREFIX=` is empty in `.env`
- Ensure `.htaccess` exists in `public_html/api/`

#### Database Connection Error
- Verify database credentials in `.env`
- Ensure `DB_HOST=localhost` (not `127.0.0.1`)
- Ensure `DB_PORT=3306`
- Check user has privileges on database

#### Routes Not Found / 404
- Clear caches (see below)
- Verify `.htaccess` in `public_html/api/` exists
- Check that mod_rewrite is enabled

---

### 🔧 Maintenance Commands

**Clear Caches (Run via cPanel Terminal or SSH):**
```bash
cd ~/slia_backend
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

**Optimize for Production:**
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 📋 Quick Reference

### File Locations on Server

```
/home/your_username/
├── public_html/
│   ├── index.html              (Frontend)
│   ├── assets/                 (Frontend assets)
│   └── api/                    (Backend public files only)
│       ├── index.php          (✏️ Update paths here)
│       └── .htaccess          (✅ Already configured)
│
└── slia_backend/               (Main Laravel app)
    ├── .env                    (✏️ Create and configure)
    ├── app/
    ├── bootstrap/
    ├── config/
    ├── database/
    ├── public/                 (Don't use - copy to api/)
    ├── resources/
    ├── routes/
    ├── storage/                (📝 Set permissions to 775)
    └── vendor/
```

### Critical Settings Summary

| Setting | Value |
|---------|-------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://sliaannualsessions.lk` |
| `API_PREFIX` | ` ` (empty) |
| `DB_HOST` | `localhost` |
| `DB_PORT` | `3306` |
| Storage permissions | `775` or `755` |
| Bootstrap cache permissions | `775` or `755` |

---

## ✅ Deployment Completion Checklist

- [ ] Uploaded project to `slia_backend/`
- [ ] Copied public files to `public_html/api/`
- [ ] Updated paths in `public_html/api/index.php`
- [ ] Created and configured `.env` file
- [ ] Created database in cPanel
- [ ] Imported database SQL
- [ ] Updated database credentials in `.env`
- [ ] Set permissions on `storage/` and `bootstrap/cache/`
- [ ] Tested health endpoint (returns JSON)
- [ ] Tested API root endpoint (returns JSON)
- [ ] Tested database connectivity (stats endpoint)
- [ ] Tested frontend integration (no network errors)
- [ ] Cleared and cached configs

**When all checked, your deployment is complete! 🎉**
