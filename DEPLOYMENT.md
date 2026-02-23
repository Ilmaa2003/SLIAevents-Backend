# GoDaddy cPanel Deployment Guide

This guide helps you deploy the SLIA Events backend API to a GoDaddy cPanel environment, specifically addressing the structure where the frontend is at `domain.com` and the backend is at `domain.com/api`.

## Prerequisite: Directory Structure

On your local machine, your project likely looks like this:
```
/SLIAevents
  ├── app/
  ├── public/
  ├── .env
  └── ...
```

On GoDaddy file manager, you want to achieve this:
```
/home/your_user/
  ├── public_html/          <-- Frontend files (index.html, JS, etc.) belong here
  │    └── api/             <-- Backend PUBLIC files only
  │         ├── index.php
  │         └── .htaccess
  └── slia_backend/         <-- All other Laravel files (app, config, vendor, etc.)
```

> **Security Note:** Do NOT put your entire Laravel project inside `public_html`. Only the contents of the `public` folder should be accessible.

## Step 1: Prepare Files

1.  **Zip your project**: Exclude `node_modules`. You SHOULD include `vendor` if you cannot run `composer install` on the server. If you have SSH access, it's better to run composer on the server.
2.  **Upload**:
    *   Create a folder `slia_backend` in your home directory (outside `public_html`).
    *   Upload and extract your zip there.

## Step 2: Configure Public Folder

1.  Create a folder named `api` inside `public_html`.
2.  Copy the **contents** of your local `public` folder into `public_html/api`.
3.  Edit `public_html/api/index.php`:

    Find these lines:
    ```php
    if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
        require $maintenance;
    }
    // ...
    require __DIR__.'/../vendor/autoload.php';
    // ...
    $app = require_once __DIR__.'/../bootstrap/app.php';
    ```

    Change the paths to point to your `slia_backend` folder. Ideally, use a full path.
    ```php
    // Example: /home/sliauser/slia_backend/...
    require '/home/your_username/slia_backend/vendor/autoload.php';
    $app = require_once '/home/your_username/slia_backend/bootstrap/app.php';
    ```

## Step 3: Environment Configuration

1.  Copy `.env.example` to `.env` in your `slia_backend` folder.
2.  Edit `.env` with your production database credentials.
3.  **Crucial Setting**:
    Since you are serving from the `/api` subfolder, but Laravel's routing also expects an `/api` prefix, you might end up with URLs like `domain.com/api/api/agm`.

    To avoid this, we added a configuration option.
    In your `.env` file, add or update:

    ```ini
    APP_URL=https://sliaannualsessions.lk
    API_PREFIX=
    ```
    
    *Setting `API_PREFIX` to empty tells Laravel that the requests already come into the `/api` folder so strictly match the path after that.*

    **However**, if your frontend sends requests to `https://sliaannualsessions.lk/api/agm`, and your `.htaccess` forwards everything in `/api` to Laravel, Laravel sees the path `/agm`.
    
    If your routes are defined as `Route::prefix('api')->...`, Laravel expects `/api/agm`.
    
    **Recommended Config for subfolder deployment:**
    
    If you deploy to `domain.com/api`:
    *   Frontend sends request to: `https://sliaannualsessions.lk/api/agm/registrations`
    *   Server maps `/api` to your index.php.
    *   Path info sent to Laravel is `agm/registrations` (because `/api` is the script path).
    *   Laravel router checks routes.
    *   If routes have `prefix('api')`, it expects `api/agm/registrations`. Match FAILS.
    
    **Solution**: Set `API_PREFIX=` (empty) in your `.env`.
    
## Step 4: Storage Permissions

Ensure the following folders in `slia_backend` have write permissions (775 or 755 usually work, dep on server user):
- `storage/`
- `bootstrap/cache/`

## Step 5: Database

1.  Create a database in cPanel MySQL Databases.
2.  Import your local SQL dump via phpMyAdmin.
3.  Update `.env` `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.

## Troubleshooting

- **404 Not Found**: Check `.htaccess` in `public_html/api`. It should look like this:
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
      RewriteRule ^(.*)/$ /$1 [L,R=301]

      # Handle Front Controller...
      RewriteCond %{REQUEST_FILENAME} !-d
      RewriteCond %{REQUEST_FILENAME} !-f
      RewriteRule ^ index.php [L]
  </IfModule>
  ```
  Note: If `RewriteRule ^(.*)$ public/$1 [L]` is there, remove it, as we are already "inside" the public equivalent.

- **500 Error**: Check `slia_backend/storage/logs/laravel.log`.
