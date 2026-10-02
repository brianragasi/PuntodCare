# Puntod Care

A mobile-friendly pilot for connecting families, cemetery caretakers, and administrators. Phase 2 adds real accounts and caretaker review. Grave records and service requests are still labeled examples.

## Stack

- PHP 8.2+ with server-rendered views and vanilla JavaScript
- Tailwind CSS 4 and daisyUI 5, compiled into a local CSS file
- MySQL/MariaDB for accounts, caretaker applications, and review history

Node.js is used to build CSS. The application itself runs on PHP and does not need a Node server.

## Run in XAMPP

1. Place this folder at `C:\xampp\htdocs\PuntodCare` and start Apache and MySQL in XAMPP.
2. Copy `config/local.example.php` to `config/local.php` and set the database connection for your local installation.
3. Run `php tools/migrate.php` from the project root. It creates the `puntod_care` database and applies the account tables. Run it again safely if setup was interrupted.
4. Create the first administrator through the command line, as shown below.
5. Open [http://localhost/PuntodCare/](http://localhost/PuntodCare/) and sign in. The root entry point forwards to `public/`.

On this workspace, the database migration has already run and a local administrator has been created. Its email is `admin@puntodcare.local`. The generated password is in the ignored, web-blocked `storage/admin-credentials.json` file. You can create another administrator with your own email or reset the local one before sharing the site.

To create an administrator without putting the password in your command history, use PowerShell:

```powershell
$secret = Read-Host 'New administrator password' -AsSecureString
$pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret)
try {
    $env:PUNTOD_ADMIN_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    php tools/create-admin.php 'Your Name' 'you@example.com'
} finally {
    Remove-Item Env:PUNTOD_ADMIN_PASSWORD -ErrorAction SilentlyContinue
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
}
```

The password must be 12–72 bytes. `tools/reset-password.php` uses the same approach with `PUNTOD_NEW_PASSWORD` and an account email; it signs out previous sessions. There is no self-service password recovery yet.

The compiled `public/assets/app.css` is included, so npm is not required just to view the project. After editing `resources/css/app.css`, run:

```powershell
npm ci
npm run build:css
```

For a PHP-only local preview without XAMPP Apache:

```powershell
php -S 127.0.0.1:8000 -t public
```

Then open [http://127.0.0.1:8000/](http://127.0.0.1:8000/).

To run the UI and account checks while XAMPP is running, install dependencies and run `npm run test:ui`. The script uses local Chrome and `storage/admin-credentials.json` by default; set `PUNTOD_CHROME_PATH`, `PUNTOD_ADMIN_EMAIL`, `PUNTOD_ADMIN_PASSWORD`, or `PUNTOD_BASE_URL` if needed. It creates and removes temporary `.test` accounts. Screenshots are written to the ignored `storage/` folder.

## Project map

| Path | Purpose |
| --- | --- |
| `public/` | Browser entry point, compiled CSS, vanilla JavaScript, icon |
| `app/views/` | Shared PHP layout and the three role previews |
| `app/helpers.php` | Escaping, icons, status badges, and preview URLs |
| `resources/css/` | Tailwind and daisyUI source plus custom theme |
| `config/` | App settings and an ignored local database configuration |
| `database/` | Account migration |
| `storage/` | Private local files; excluded from version control |
| `tools/` | Database setup, controlled administrator creation, password reset, and browser checks |

For an Apache deployment, set the virtual host document root to `public/` when possible. When using the XAMPP folder URL above, the repository root and private folders include Apache access rules. PHP's built-in server should always use `-t public`.

## Phase boundaries

Family members register directly. Caretakers apply with a contact number and service area, then receive a `pending` status until an administrator reviews them. Administrators are created only through the local CLI setup. Signed-in pages use the role stored in MySQL; URL parameters cannot switch roles. Administrators can verify, reject, suspend, and restore caretakers. Review decisions are recorded in `account_events`.

The dashboard grave profiles, assignments, and care workflow remain **design examples**. The administrator's account figures are live. The interface kit form checks fields in the browser and saves nothing. Real grave records and service actions are later phases in [ROADMAP.md](ROADMAP.md).

Passwords are hashed with PHP's password API. Login rotates the session ID; sessions use HTTP-only cookies, and a CLI password reset invalidates previous sessions. POST actions check CSRF tokens, and SQL values use prepared statements. Login failures are limited per email and IP in a 15-minute window. Public deployment also needs HTTPS and an appropriate database user instead of a development `root` account.

## Design notes

The theme uses deep green, warm ivory, charcoal, and restrained warm accents. Buttons, forms, status badges, alerts, and cards are reusable across roles. Layouts include labels, visible focus states, a skip link, keyboard-accessible mobile navigation, and reduced-motion support.
