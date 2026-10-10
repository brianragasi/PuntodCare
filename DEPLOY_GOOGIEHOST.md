# Deploy Puntod Care on GoogieHost

The GoogieHost account for `puntodcare.whf.bz` has PHP 8.3 and the `kcsmdehy_puntod_care` database. The phpMyAdmin screenshot already shows all 17 application tables and existing rows. **Do not import the schema or a local database backup again into this database.** Check the existing `users` rows before sharing the public URL; any copied demo accounts should have their passwords changed or be removed.

## 1. Build the files on your computer

From the project directory, run `php tools/build-hosting-files.php`. It creates two ignored files in `storage/`:

- `PuntodCare-GoogieHost.zip` contains only tracked application files needed by the website. It has no local database config, database password, or photos.
- `puntodcare-install.sql` combines the numbered migrations for a **new empty database**. The current GoogieHost database already has tables, so do not import this file now.

The compiled CSS is included in the ZIP. GoogieHost does not need Node.js or npm.

## 2. Publish automatically from GitHub main

The repository has a GitHub Actions workflow at `.github/workflows/deploy-googiehost.yml`. It runs on every push to `main`, builds the same application-only ZIP described above, uploads its contents over encrypted FTP, and checks the live page and assets. It never uploads the database, `config/local.php`, `storage/`, or photos. It syncs deletions inside `app/` and `public/` only; other host files are left alone.

One-time setup:

1. In DirectAdmin **FTP Management**, find the FTP username, or create an FTP account scoped to `domains/puntodcare.whf.bz/public_html` if your plan allows one. Use that account's FTP password, **not** the MySQL password. The workflow connects to `cloud3.googiehost.com` because that is the hostname on this server's FTP TLS certificate. It refuses an unencrypted connection.
2. In the GitHub repository, open **Settings → Secrets and variables → Actions**. Create repository secrets `GOOGIEHOST_FTP_USER` and `GOOGIEHOST_FTP_PASSWORD` with those FTP values. Do not put credentials in Git-tracked files.
3. On the same GitHub page, create the repository **variable** `GOOGIEHOST_FTP_REMOTE_DIR`. Use the path *as seen by that FTP account*: normally `domains/puntodcare.whf.bz/public_html` for an account rooted at the hosting home, or `.` for an account rooted directly at `public_html`. Confirm the location through DirectAdmin FTP Management or an FTP client. The workflow uploads files directly into this directory, so the correct value matters.
4. Open **Actions → Deploy to GoogieHost → Run workflow** once. Check that the `deploy` job succeeds and that `https://puntodcare.whf.bz/` works. After that, each push to `main` runs the same deployment automatically. A failed job means the live site may still have the previous version; review the job log before another push.

Keep `index.php`, `.htaccess`, `app/`, `config/`, and `public/` directly in `public_html`. The private directory in step 3 must remain beside `public_html`, never inside it. The old manual upload ZIP can be removed from `public_html` after the automated deployment succeeds.

## 3. Add the live database configuration

In File Manager, create `domains/puntodcare.whf.bz/puntodcare-private` beside `public_html`, then create `local.php` inside it. Copy the structure from [config/local.example.php](config/local.example.php). Set `host` to `localhost`, `port` to `3306`, and both `name` and `username` to the database values displayed by DirectAdmin. Enter the password directly in this private file; **never put it in GitHub, the ZIP, or `public_html`**. The application automatically reads this sibling directory when it exists.

Make sure PHP can write to `puntodcare-private`. The app stores grave and request photos in that directory. Your XAMPP installation continues to use its own ignored `config/local.php` and `storage/`.

## 4. Check the live site

Enable SSL and visit `https://puntodcare.whf.bz/`. Verify the login page loads with styles, sign in with an account that exists in the hosted `users` table, and test a request with a non-sensitive photo. Check that `pdo_mysql`, `mbstring`, and `fileinfo` are enabled and that the PHP upload limit permits at least a 5 MB photo.

Confirm `https://puntodcare.whf.bz/config/app.php` and `https://puntodcare.whf.bz/app/database.php` are denied. The private `local.php` must be outside `public_html`. If a private file is reachable, fix the web root and access rules before sharing the URL.

If the hosted `users` table does not contain an administrator you want to keep, run `php tools/prepare-live-admin.php "Your Name" you@example.com` **on your computer**. Import only its generated `storage/live-admin-import.sql` into the selected hosted database, and keep `storage/live-admin-credentials.json` private. Do not upload those files to `public_html`.

Back up both the hosted database and `puntodcare-private` regularly. When you rotate the database password in DirectAdmin, update the private `local.php` to match. A future migration needs its own database import; uploading new code does not change the schema.
