# Start Puntod Care in XAMPP

1. Start **Apache** and **MySQL** in the XAMPP control panel.
2. Put the project at `C:\xampp\htdocs\PuntodCare`. That folder should contain `index.php` directly, not another nested `PuntodCare` folder.
3. Copy `config/local.example.php` to `config/local.php`. Use the database name shown in phpMyAdmin and your local MySQL username/password. The default example expects a database named `puntod_care` on port 3306.
4. From the project folder, run `php tools/migrate.php` to create any missing tables. Importing a SQL dump into phpMyAdmin is optional; it does not replace the local configuration file.
5. Open [http://localhost/PuntodCare/](http://localhost/PuntodCare/) in a browser. Use **Create a family account** to try the family view. An administrator account is needed to add cemeteries and services.

The design is included in `public/assets/app.css`; no npm or separate design server is needed to view the site.

If you see a setup/database error, check `config/local.php` and confirm MySQL is running. If you see a 404, check the folder name and URL. If the login page appears without styling, check that `public/assets/app.css` exists. Do not open `index.php` directly from File Explorer; use the localhost URL.
