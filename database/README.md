# Database setup

Phases 2–6 store real accounts, caretaker applications, administrator catalog records, family grave profiles, reference photos, care requests, evidence photos, in-app updates, change events, and failed login attempts. Copy `config/local.example.php` to the ignored `config/local.php`, then run `php tools/migrate.php` from the project root. The script applies numbered SQL files in order and is safe to rerun. The database user needs permission to create the database and tables during setup; application permissions can be narrowed for deployment.

On a host without PHP command-line access, create the database in its control panel and import the numbered SQL files in order through phpMyAdmin. For GoogieHost, place `local.php` and uploads in the sibling `puntodcare-private/` directory outside `public_html`; see [DEPLOY_GOOGIEHOST.md](../DEPLOY_GOOGIEHOST.md).

Administrator accounts are created through `tools/create-admin.php`. The script reads the password from `PUNTOD_ADMIN_PASSWORD`, so it does not appear in a command argument. `tools/reset-password.php` reads `PUNTOD_NEW_PASSWORD` and revokes existing sessions.

`002_admin_catalog.sql` adds `cemeteries`, `plots`, `service_offerings`, `service_price_history`, `caretaker_cemeteries`, and `admin_events`. Each plot belongs to a cemetery and has a unique section/block/row/lot reference there. Each service belongs to one cemetery and stores its current estimate as an integer number of centavos. A price history row records each initial and changed price. Administrators can grant a verified caretaker access to an active cemetery. Catalog edits and authorization changes create audit events in the same database transaction.

`003_graves.sql` adds family-owned grave profiles, private photo metadata, and profile activity. Photo files are stored under the denied `storage/grave-photos/` directory. A family can see its own photos; an assigned, verified caretaker with current cemetery access can see them while the job is active. No partner data is seeded.

`004_service_requests.sql` adds service requests and an append-only status timeline. A request stores the selected service, pilot price, and identifying grave details as a snapshot. Every status change is checked against the actor, current status, and cemetery access inside a transaction.

`005_evidence_updates.sql` adds private before-and-after evidence metadata and in-app updates. The evidence files are stored under the denied `storage/request-evidence/` directory and served only to authorized request participants. New work submissions require both photo stages. Reported issues can receive an administrator response or a new work round. Payments and condition reports remain outside the pilot.
