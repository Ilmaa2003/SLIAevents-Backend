# Troubleshooting 505 Error on GoDaddy

## What is a 505 Error?

**505 HTTP Version Not Supported** - This means the server doesn't support the HTTP protocol version used in the request.

However, on shared hosting like GoDaddy, this often appears when there's actually a **different server error** being masked.

## Common Causes & Solutions

### 1. Check Error Logs (MOST IMPORTANT)

**In cPanel:**
1. Go to **Errors** → **Error Log**
2. Look for recent errors
3. Share the actual error message

**Or check Laravel logs:**
- File: `slia_backend/storage/logs/laravel.log`
- Look at the most recent entries

### 2. File Permissions Issue

**Most common cause on cPanel!**

```bash
# Set correct permissions
chmod -R 775 slia_backend/storage/
chmod -R 775 slia_backend/bootstrap/cache/
```

**In cPanel File Manager:**
1. Navigate to `slia_backend/storage/`
2. Right-click → Change Permissions
3. Set to **775**
4. ✅ Check "Recurse into subdirectories"
5. Click Change Permissions
6. Repeat for `slia_backend/bootstrap/cache/`

### 3. Missing .env File

Check if `slia_backend/.env` exists:
- If not, copy from `.env.example`
- Update database credentials

### 4. Wrong Path in index.php

**Check `public_html/api/index.php`:**

```php
// Should be:
$basePath = '/home/YOUR_ACTUAL_USERNAME/slia_backend/';

// NOT:
$basePath = '/home/your_username/slia_backend/';  // ❌ Template text
```

**How to find your username:**
- Look at cPanel file manager path
- It shows: `/home/USERNAME/public_html`
- Use that USERNAME

### 5. PHP Version Issue

Laravel 10 requires **PHP 8.1 or higher**

**In cPanel:**
1. Go to **Select PHP Version**
2. Choose **PHP 8.1** or **PHP 8.2**
3. Save

### 6. Missing Vendor Directory

If you didn't upload `vendor/` folder:

```bash
cd slia_backend
composer install --no-dev
```

Or upload the `vendor/` folder from your local machine.

### 7. .htaccess Issues

**Check `public_html/api/.htaccess` exists and contains:**

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Handle Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

## Quick Diagnostic Steps

### Step 1: Test Direct PHP Access

Try accessing:
```
https://sliaannualsessions.lk/api/index.php
```

**If this works:** `.htaccess` issue
**If this fails:** PHP/path configuration issue

### Step 2: Create Test File

Create `public_html/api/test.php`:
```php
<?php
phpinfo();
```

Access: `https://sliaannualsessions.lk/api/test.php`

**If this works:** PHP is working, issue is with Laravel
**If this fails:** Server/PHP configuration issue

### Step 3: Check Path Resolution

Create `public_html/api/pathtest.php`:
```php
<?php
$basePath = '/home/your_username/slia_backend/';
echo "Checking path: " . $basePath . "<br>";
echo "Path exists: " . (file_exists($basePath) ? 'YES' : 'NO') . "<br>";
echo "Vendor exists: " . (file_exists($basePath . 'vendor/autoload.php') ? 'YES' : 'NO') . "<br>";
```

Replace `your_username` with your actual username and access the file.

## Most Likely Solutions (Try in Order)

### Solution 1: Fix Permissions (90% of cases)
```bash
chmod -R 775 slia_backend/storage/
chmod -R 775 slia_backend/bootstrap/cache/
```

### Solution 2: Fix Path in index.php
Update `public_html/api/index.php` with correct username:
```php
$basePath = '/home/ACTUAL_USERNAME/slia_backend/';
```

### Solution 3: Check PHP Version
Ensure PHP 8.1+ is selected in cPanel

### Solution 4: Verify .env Exists
Create `slia_backend/.env` from `.env.example`

## What to Check Right Now

1. **cPanel Error Log** - What's the actual error?
2. **File Permissions** - Is `storage/` writable?
3. **Username in path** - Is it correct in `api/index.php`?
4. **PHP Version** - Is it 8.1 or higher?
5. **Laravel Logs** - Check `slia_backend/storage/logs/laravel.log`

## Need More Help?

Please provide:
1. ✅ Error from cPanel Error Log
2. ✅ Last few lines from `slia_backend/storage/logs/laravel.log`
3. ✅ Your cPanel username (visible in file manager path)
4. ✅ Result of accessing `https://sliaannualsessions.lk/api/test.php`

This will help pinpoint the exact issue!
