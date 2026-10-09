# Deploy Puntod Care on GoogieHost

The GoogieHost account for `puntodcare.whf.bz` has PHP 8.3 and the `kcsmdehy_puntod_care` database. The phpMyAdmin screenshot already shows all 17 application tables and existing rows. **Do not import the schema or a local database backup again into this database.** Check the existing `users` rows before sharing the public URL; any copied demo accounts should have their passwords changed or be removed.

## 1. Build the files on your computer

From the project directory, run `php tools/build-hosting-files.php`. It creates two ignored files in `storage/`:

- `PuntodCare-GoogieHost.zip` contains only tracked application files needed by the website. It has no local database config, database password, or photos.
- `puntodcare-install.sql` combines the numbered migrations for a **new empty database**. The current GoogieHost database already has tables, so do not import this file now.

The compiled CSS is included in the ZIP. GoogieHost does not need Node.js or npm.

## 2. Upload the website

In DirectAdmin **File Manager**, open `domains/puntodcare.whf.bz/public_html`. Upload `storage/PuntodCare-GoogieHost.zip` there and extract it **in that directory**. `index.php`, `.htaccess`, `app/`, `config/`, and `public/` must be directly inside `public_html`, without a second `PuntodCare` folder. Remove the default `index.html` placeholder if it takes precedence over Puntod Care's `index.php`. Delete the uploaded ZIP after extraction.

The dashboard screenshot did not show Git Manager. Until a Git deployment integration or another secure deployment method is configured and tested, **pushing to GitHub does not update GoogieHost**. For each code update, rebuild the ZIP and replace the application files in `public_html`. Keep the private directory in step 3 untouched.

## 3. Add the live database configuration

In File Manager, create `domains/puntodcare.whf.bz/puntodcare-private` beside `public_html`, then create `local.php` inside it. Copy the structure from [config/local.example.php](config/local.example.php). Set `host` to `localhost`, `port` to `3306`, and both `name` and `username` to the database values displayed by DirectAdmin. Enter the password directly in this private file; **never put it in GitHub, the ZIP, or `public_html`**. The application automatically reads this sibling directory when it exists.

Make sure PHP can write to `puntodcare-private`. The app stores grave and request photos in that directory. Your XAMPP installation continues to use its own ignored `config/local.php` and `storage/`.

## 4. Check the live site

Enable SSL and visit `https://puntodcare.whf.bz/`. Verify the login page loads with styles, sign in with an account that exists in the hosted `users` table, and test a request with a non-sensitive photo. Check that `pdo_mysql`, `mbstring`, and `fileinfo` are enabled and that the PHP upload limit permits at least a 5 MB photo.

Confirm `https://puntodcare.whf.bz/config/app.php` and `https://puntodcare.whf.bz/app/database.php` are denied. The private `local.php` must be outside `public_html`. If a private file is reachable, fix the web root and access rules before sharing the URL.

If the hosted `users` table does not contain an administrator you want to keep, run `php tools/prepare-live-admin.php "Your Name" you@example.com` **on your computer**. Import only its generated `storage/live-admin-import.sql` into the selected hosted database, and keep `storage/live-admin-credentials.json` private. Do not upload those files to `public_html`.

Back up both the hosted database and `puntodcare-private` regularly. When you rotate the database password in DirectAdmin, update the private `local.php` to match. A future migration needs its own database import; uploading new code does not change the schema.
