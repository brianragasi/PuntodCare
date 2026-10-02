# Puntod Care

A mobile-friendly pilot interface for connecting families, cemetery caretakers, and administrators. The first phase establishes the PHP structure and shared design; all dashboard records are labeled examples.

## Stack

- PHP 8.2+ with server-rendered views and vanilla JavaScript
- Tailwind CSS 4 and daisyUI 5, compiled into a local CSS file
- MySQL/MariaDB planned for Phase 2; the current preview does not require a database

Node.js is used to build CSS. The application itself runs on PHP and does not need a Node server.

## Run in XAMPP

1. Place this folder at `C:\xampp\htdocs\PuntodCare` and start Apache in XAMPP.
2. Open [http://localhost/PuntodCare/](http://localhost/PuntodCare/) in your browser. The root entry point forwards to `public/`.
3. Use **View as** at the top to preview administrator, family, and caretaker layouts.
4. Open **Interface kit** in the side navigation to see the shared components and try local form validation.

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

To run the UI checks while the PHP server is running, install dependencies and run `npm run test:ui`. The script uses local Chrome by default; set `PUNTOD_CHROME_PATH` if Chrome is elsewhere. Screenshots are written to the ignored `storage/` folder.

## Project map

| Path | Purpose |
| --- | --- |
| `public/` | Browser entry point, compiled CSS, vanilla JavaScript, icon |
| `app/views/` | Shared PHP layout and the three role previews |
| `app/helpers.php` | Escaping, icons, status badges, and preview URLs |
| `resources/css/` | Tailwind and daisyUI source plus custom theme |
| `config/` | Non-secret app settings; local secrets will live in an ignored file |
| `database/` | Database setup and future migrations |
| `storage/` | Future private files; excluded from version control |

For an Apache deployment, set the virtual host document root to `public/` when possible. When using the XAMPP folder URL above, the repository root and private folders include Apache access rules. PHP's built-in server should always use `-t public`.

## Phase boundaries

The three workspaces are **design previews**. Switching roles is available solely to inspect layouts; it does not grant access to real user data. Sample names, graves, counts, requests, and statuses are illustrative. The interface kit validates a form in the browser and deliberately saves nothing. Authentication, persistent records, and real service actions are planned in [ROADMAP.md](ROADMAP.md).

## Design notes

The theme uses deep green, warm ivory, charcoal, and restrained warm accents. Buttons, forms, status badges, alerts, and cards are reusable across roles. Layouts include labels, visible focus states, a skip link, keyboard-accessible mobile navigation, and reduced-motion support.
