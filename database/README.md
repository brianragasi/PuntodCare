# Database setup

Phase 2 stores real accounts, caretaker applications, account events, and failed login attempts. Copy `config/local.example.php` to the ignored `config/local.php`, then run `php tools/migrate.php` from the project root. The migration is safe to rerun. The database user needs permission to create the database and tables during setup; application permissions can be narrowed for deployment.

Administrator accounts are created through `tools/create-admin.php`. The script reads the password from `PUNTOD_ADMIN_PASSWORD`, so it does not appear in a command argument. `tools/reset-password.php` reads `PUNTOD_NEW_PASSWORD` and revokes existing sessions.

Grave, cemetery, and service-request tables belong to later phases. The dashboard examples do not write to this database.
