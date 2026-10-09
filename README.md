# Puntod Care

Puntod Care is a mobile-friendly pilot for families, cemetery caretakers, and administrators. Families can create accounts, caretakers can apply for review, and administrators can manage cemeteries, plot references, caretaker access, and service prices. Family grave profiles and service requests are planned next.

The app uses vanilla PHP and JavaScript, MySQL/MariaDB, Tailwind CSS, and daisyUI. Node.js is only needed when rebuilding the CSS.

## Run locally

1. Put the project in `C:\xampp\htdocs\PuntodCare` and start Apache and MySQL in XAMPP.
2. Copy `config/local.example.php` to `config/local.php` and set your database connection.
3. Run `php tools/migrate.php` from the project folder.
4. Open [http://localhost/PuntodCare/](http://localhost/PuntodCare/).

On a fresh installation, create an administrator with `tools/create-admin.php`. The compiled CSS is included, so npm is not needed to run the app. For development, use `npm ci` and `npm run build:css` after changing styles.

See [SETUP.md](SETUP.md) for a first-run guide, [ROADMAP.md](ROADMAP.md) for project progress, and [database/README.md](database/README.md) for database details.
