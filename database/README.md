# Database setup

Phases 2 and 3 store real accounts, caretaker applications, administrator catalog records, account and catalog events, and failed login attempts. Copy `config/local.example.php` to the ignored `config/local.php`, then run `php tools/migrate.php` from the project root. The script applies numbered SQL files in order and is safe to rerun. The database user needs permission to create the database and tables during setup; application permissions can be narrowed for deployment.

Administrator accounts are created through `tools/create-admin.php`. The script reads the password from `PUNTOD_ADMIN_PASSWORD`, so it does not appear in a command argument. `tools/reset-password.php` reads `PUNTOD_NEW_PASSWORD` and revokes existing sessions.

`002_admin_catalog.sql` adds `cemeteries`, `plots`, `service_offerings`, `service_price_history`, `caretaker_cemeteries`, and `admin_events`. Each plot belongs to a cemetery and has a unique section/block/row/lot reference there. Each service belongs to one cemetery and stores its current estimate as an integer number of centavos. A price history row records each initial and changed price. Administrators can grant a verified caretaker access to an active cemetery. Catalog edits and authorization changes create audit events in the same database transaction.

No partner data is seeded. Family grave profiles and service requests belong to later phases; existing family and caretaker dashboard examples do not write to this catalog.
