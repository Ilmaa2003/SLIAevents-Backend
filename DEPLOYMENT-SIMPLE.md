# Simplified GoDaddy Deployment Guide

## Simplified Structure (Everything in public_html)

This is the **easier approach** - put everything in `public_html`:

```
public_html/
├── api/                    # Copy from public/ folder
│   ├── index.php          # Laravel entry point
│   └── .htaccess          # Already configured
├── app/                    # Laravel application
├── bootstrap/
├── config/
├── database/
├── resources/
├── routes/
├── storage/               # Set to 775 permissions
├── vendor/
├── .env                   # Create from .env.example
├── .htaccess              # Root htaccess (redirects to api/)
└── [frontend files]       # index.html, assets/, etc.
```

## Deployment Steps

### 1. Upload Files

**Upload entire Laravel project to `public_html/`:**
- Use cPanel File Manager or FTP
- Upload everything (app, bootstrap, config, database, public, resources, routes, storage, vendor, etc.)
- **Important:** Upload `.htaccess` from project root to `public_html/.htaccess`

### 2. Setup API Directory

**Copy public folder contents:**
```
public_html/public/index.php  →  public_html/api/index.php
public_html/public/.htaccess  →  public_html/api/.htaccess
```

**Then delete the public folder:**
```
Delete: public_html/public/
```

### 3. Update index.php

Edit `public_html/api/index.php` and change the path:

**Find:**
```php
// === LOCAL DEVELOPMENT PATHS (ACTIVE) ===
$basePath = __DIR__.'/../';
```

**Change to:**
```php
// === PRODUCTION PATHS (public_html deployment) ===
$basePath = __DIR__.'/../../';
```

This makes it point from `public_html/api/` → `public_html/`

### 4. Configure .env

1. Copy `.env.example` to `.env` in `public_html/`
2. Update database credentials:
   ```env
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=your_cpanel_database_name
   DB_USERNAME=your_cpanel_database_user
   DB_PASSWORD=your_cpanel_database_password
   ```

### 5. Set Permissions

```bash
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
```

Or via cPanel File Manager:
- Right-click `storage/` → Permissions → 775 (recursive)
- Right-click `bootstrap/cache/` → Permissions → 775 (recursive)

### 6. Test

Open in browser:
- `https://sliaannualsessions.lk/api/health` → Should return JSON
- `https://sliaannualsessions.lk/` → Your frontend

## Advantages of This Approach

✅ **Simpler** - No need for absolute paths or username configuration
✅ **Fewer steps** - Just upload and adjust one relative path
✅ **Easier to understand** - Everything is in one place
✅ **Works immediately** - No need to find your cPanel username

## Security Note

⚠️ With this setup, your Laravel files (app, config, etc.) are inside `public_html`. This is fine for most shared hosting, but ensure:
- `.htaccess` files are in place
- `storage/` and `bootstrap/cache/` are not directly accessible
- `.env` file is protected (should be by default)

## Quick Checklist

- [ ] Upload entire project to `public_html/`
- [ ] Copy `public/index.php` to `api/index.php`
- [ ] Copy `public/.htaccess` to `api/.htaccess`
- [ ] Delete `public/` folder
- [ ] Edit `api/index.php` → Change to `$basePath = __DIR__.'/../../';`
- [ ] Create `.env` and configure database
- [ ] Set permissions on `storage/` and `bootstrap/cache/`
- [ ] Test API endpoint
- [ ] Upload frontend files to `public_html/`
- [ ] Test complete application

## File Structure After Deployment

```
public_html/
├── api/
│   ├── index.php         ✅ Updated with $basePath = __DIR__.'/../../';
│   └── .htaccess         ✅ Has CORS headers
├── app/                  ✅ Laravel app
├── bootstrap/
│   └── cache/            ✅ Permissions 775
├── storage/              ✅ Permissions 775
├── .env                  ✅ Database configured
├── .htaccess             ✅ Redirects /api to api/
├── index.html            ✅ Frontend
└── assets/               ✅ Frontend assets
```
