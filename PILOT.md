# Puntod Care pilot demonstration

This walkthrough uses fictional records. The displayed prices are pilot estimates; Puntod Care does not collect or settle payments.

## Prepare a local XAMPP installation

1. Start Apache and MySQL. Follow [SETUP.md](SETUP.md) if this is the first run.
2. From `C:\xampp\htdocs\PuntodCare`, run `php tools/migrate.php` and `php tools/seed-demo.php`.
3. Open the ignored `storage/demo-credentials.json` locally. It contains separate administrator, family, and caretaker sign-ins. Do not commit or share the file. Running the seed again leaves existing demo records intact.
4. Open [http://localhost/PuntodCare/](http://localhost/PuntodCare/) in a browser. The demo cemetery, grave, services, and two requests have **DEMO** names.

## Show an approved request

1. Sign in as the demo administrator. Open **Care requests**, choose **DEMO Grave cleaning**, and assign the demo caretaker.
2. Sign in as the demo caretaker. Open **Assigned jobs**, accept the assignment, and confirm the exact demo headstone, section `DEMO-A`, and lot `12` before starting.
3. Upload one before photo and one after photo, then enter a work note and submit. Use only fictional sample images during a demonstration.
4. Sign in as the demo family. Open **Care requests**, compare both photos, read the note, and choose **Approve reported work**. The status becomes **Completed**.

## Show an issue

Repeat the assignment and caretaker steps for **DEMO Flower placement**. As the family, choose **Report an issue** instead of approving. The administrator can record a response for family review or request a new work round. A new round needs fresh before-and-after photos. Each participant can open **Updates** after reloading the page.

## Verify and record results

Run `npm ci` once, then `npm run test:pilot`. This runs the account/security, administrator, grave, and request browser suites in sequence. It checks private access, invalid transitions, rejected uploads, evidence, family review, issue handling, and mobile widths from 320 to 1280 pixels. Chrome must be installed; set `PUNTOD_CHROME_PATH` if it is elsewhere. Set `PUNTOD_BASE_URL` if the local URL differs. The suites create temporary records and remove them after each run. They use the demo administrator credentials automatically, or `PUNTOD_ADMIN_EMAIL` and `PUNTOD_ADMIN_PASSWORD` if both are set.

Run `php tools/pilot-report.php` for counts on the demo cemetery. For a partner cemetery, pass its exact unique name in quotes. The report shows request statuses, issue reports, and average time from request to family approval. Record dated partner observations in a private note outside Git: what participants tried, what confused them, what failed, and the next action. Do not include names or photos without permission.

A useful private feedback entry is: `Date | Role | Scenario tried | Observation | Impact | Next action | Owner`. Keep one entry per observation and review open actions after each pilot session.

If a form loses its connection before confirming a result, reopen **Care requests** and check the status or timeline before submitting again. Duplicate open requests for the same grave and service are rejected. Uploads are not queued offline; retry an incomplete upload after reconnecting.

## Back up a pilot

Use phpMyAdmin **Export** to save a SQL backup of the configured database. Copy the private photo folders too: local XAMPP uses `storage/`, while a hosted deployment uses the sibling `puntodcare-private/` directory. The SQL backup contains photo metadata but not the files. Keep backups and `local.php` outside the repository. To restore, import the SQL into an isolated database, restore the photo folders to the matching private location, update `local.php`, and verify a grave photo and an evidence photo while signed in as an authorized participant. Never restore a demo backup over partner data.

## Known limits

Photo timestamps reflect upload time, not independently verified capture time or location. Updates appear after page reload, without push notifications. Condition inspections and payment processing are not implemented. Demo accounts have full privileges within their roles; use a separate database and do not share their credentials with a real pilot.
