# Deploy Puntod Care on GoogieHost

GoogieHost provides PHP 8.x, MySQL/phpMyAdmin, SSL, and a DirectAdmin panel. The application needs all four. Code pushed to GitHub does **not** update the database or the uploaded photos. Automatic code deployment requires an active Git integration and push webhook; check that your GoogieHost plan exposes DirectAdmin **Advanced Features → Git** before relying on it. See [GoogieHost PHP hosting](https://googiehost.com/freephphosting) and [DirectAdmin Git Manager](https://docs.directadmin.com/other-hosting-services/git/).

## 1. Connect the repository

In GoogieHost DirectAdmin, open **Advanced Features → Git** if available. Add remote `https://github.com/brianragasi/PuntodCare.git`, select branch `main`, and set the deploy directory to the domain's **existing** `domains/YOUR-DOMAIN/public_html` directory (relative to your hosting home). Deploy once. The result must place `index.php`, `app/`, `config/`, `database/`, and `public/` directly inside `public_html`, not inside a second `PuntodCare` folder. The site's root URL then redirects to `/public/`.

If GoogieHost left a default `index.html` page in `public_html`, remove that placeholder after confirming the repository files deployed; otherwise it may appear instead of Puntod Care's `index.php`.

If DirectAdmin shows a webhook URL for this repository, copy that exact URL. In GitHub, open this repository's **Settings → Webhooks → Add webhook**, paste the URL, use the content type and secret required by DirectAdmin, and select the **push** event. Keep SSL verification enabled. Check the GitHub webhook delivery and DirectAdmin deploy log after a test push. [DirectAdmin documents](https://docs.directadmin.com/other-hosting-services/git/) that automated fetch and deploy require a valid deploy branch and directory. [GitHub's webhook guide](https://docs.github.com/en/webhooks/using-webhooks/creating-webhooks) explains the Settings screen.

The compiled CSS is already in the repository, so the host does not need Node or npm. Once the webhook works, reviewed pushes to `main` update application code on the live site.

If **Git** or a webhook is unavailable on your GoogieHost plan, this automatic path is not configured. You can deploy manually with the File Manager/FTP for now; ask GoogieHost whether Git Manager is enabled before expecting `main` pushes to appear automatically. Do not add a public PHP endpoint that accepts unauthenticated deployment requests.

## 2. Keep private data outside Git deployment

In DirectAdmin File Manager, create `domains/YOUR-DOMAIN/puntodcare-private` next to `public_html`, **not inside it**. The app automatically uses this directory when it exists. Create `local.php` there using [config/local.example.php](config/local.example.php) as the template. Enter the **GoogieHost database host, database name, database user, password, and port** shown by DirectAdmin; these may include your hosting account prefix. Do not commit, paste into GitHub, or upload this file into `public_html`.

The app will create `puntodcare-private/grave-photos/` and `puntodcare-private/request-evidence/` when photos are uploaded, provided PHP can write to `puntodcare-private`. These files persist outside Git deployments. Do not copy local `storage/` contents or demo credentials to the live site. On XAMPP, where this sibling directory does not exist, the app keeps using the existing ignored `config/local.php` and `storage/` paths.

## 3. Create the hosted database and first administrator

In DirectAdmin **MySQL Management**, create a database and user. Open phpMyAdmin, select **that database**, and import `database/001_accounts.sql` through `database/005_evidence_updates.sql` in number order. If GoogieHost provides a PHP command line, `php tools/migrate.php` is an alternative after `local.php` is in place. It uses an existing database without requiring permission to create a new one.

On your **own computer**, run `php tools/prepare-live-admin.php "Your Name" you@example.com`. The script generates a random password and two ignored local files: `storage/live-admin-import.sql` and `storage/live-admin-credentials.json`. Import only the SQL file into the selected GoogieHost database in phpMyAdmin. Keep the credentials file private and delete the SQL file after the import. Do not upload either file to the website. Sign in with those credentials, then create family and caretaker accounts through the website as needed. Do not put the local demo database or demo admin account on a public site.

## 4. Check the live site before sharing

- Enable SSL and open `https://YOUR-DOMAIN/`. The login page should load with its styles at `/public/?page=login`.
- Select PHP 8.1 or newer and check `pdo_mysql`, `mbstring`, and `fileinfo` are enabled. Set `upload_max_filesize` to at least 5 MB and `post_max_size` above that (for example 8 MB) for care photos. If the app displays a setup/database error, recheck `puntodcare-private/local.php` and the imported tables.
- Confirm `https://YOUR-DOMAIN/database/001_accounts.sql` and `https://YOUR-DOMAIN/config/local.example.php` are denied, and `https://YOUR-DOMAIN/puntodcare-private/local.php` is not reachable. If any private file downloads, stop sharing the URL until the web root and access rules are corrected.
- Sign in as the administrator; create one fictional cemetery/service, register a family and caretaker, and test a request with a small non-sensitive photo. Verify the photo remains after the next code deployment.
- Check the GitHub webhook's delivery result and DirectAdmin's deployment log after a harmless code push. A successful GitHub push is not proof that the live site updated.

The live site allows public family registration. If only teammates should access this preview, put the site behind DirectAdmin's password-protected directory feature or another host-level access gate before sharing the URL. Keep demo photos fictional and back up the database **and** `puntodcare-private/` together. Every future schema file must still be imported or migrated on the server; Git deployment updates code only.
