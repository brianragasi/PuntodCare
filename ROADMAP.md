# Puntod Care implementation roadmap

## Goal and stack

Build a mobile-friendly pilot for one partner cemetery. A family registers a grave, requests care, and reviews photo evidence submitted by a verified caretaker. An administrator oversees the process.

- Backend: vanilla PHP with PDO and MySQL/MariaDB.
- Frontend: PHP-rendered HTML, vanilla JavaScript, Tailwind CSS, and daisyUI with a custom Puntod Care theme.
- Build tools: Node/npm for compiling CSS; PHP remains the application runtime.
- MVP roles: family member, caretaker, and platform administrator.
- Cemetery administrators receive their own accounts in a later phase. Initially, the platform administrator manages the pilot cemetery.

The phases below define dependencies, not fixed delivery dates. Complete and verify one feature at a time, then explain it before moving on.

**Current progress (October 2, 2026): Phase 1 complete.** The shared theme, PHP structure, three responsive role previews, and interface kit are implemented. All displayed records are examples. The next phase is accounts, login, and server-side permissions.

## 1. Foundation and shared design

### Features

- Organize public entry points, PHP application code, shared templates, configuration, and database setup.
- Create a custom theme: deep green, warm ivory, charcoal, readable typography, and consistent spacing.
- Build shared navigation, forms, buttons, status badges, and feedback messages.
- Establish responsive family, caretaker, and administrator layouts.

### Purpose

Give every feature a consistent appearance and a maintainable starting point.

### Completion criteria

- Shared layouts work on phone and desktop screens.
- Forms have visible labels, keyboard focus, and understandable validation messages.
- Configuration and private files are outside the public web path or explicitly blocked from web access.
- Setup instructions allow the application to run locally in XAMPP.

## 2. Accounts, login, and permissions

### Features

- Family registration, login, and logout.
- Secure PHP sessions and password hashing.
- Role-based navigation and server-side authorization.
- Administrator account creation through a controlled setup process.
- Caretaker enrollment with pending, verified, and suspended states.

### Purpose

Identify users and control who can see records and perform actions.

### Completion criteria

- Public registration cannot grant administrator privileges.
- Users cannot access another family's private records by changing a URL or request identifier.
- Only authorized administrators can verify or suspend caretakers.
- State-changing requests use CSRF protection; database access uses prepared statements; displayed user content is escaped.

## 3. Basic administrator panel

### Features

- Manage the pilot cemetery, sections, blocks/rows, and lot references.
- Review caretaker details and verification status.
- Manage service offerings, descriptions, availability, and pilot prices.
- Search family accounts and grave records within authorized administrative duties.
- Record important administrative changes.

### Purpose

Prepare the cemetery data and approved providers needed for actual service requests.

### Completion criteria

- The administrator can prepare one cemetery and at least one verified caretaker and service.
- Required record fields and duplicate handling are defined.
- Demo prices are clearly identified until validated with the partner and providers.

## 4. Family dashboard and grave profiles

### Features

- Create and edit a grave profile: deceased person's name, dates, cemetery, section, block/row, lot, reference photos, and optional location pin.
- Show the family's graves as readable cards.
- Display service history, latest documented condition, and last inspection date when available.
- Start a care request from a grave profile.

### Purpose

Give families a reliable record of the grave and help caretakers identify the exact tomb.

### Completion criteria

- A family can register and revisit a grave on a phone.
- Grave details and uploaded reference photos are visible only to authorized users.
- Location is supplementary; the headstone name and cemetery/lot details remain part of identification.
- Condition information identifies its report date and source and is not invented when no inspection exists.

## 5. Service requests and caretaker jobs

### Features

- Choose a service, preferred date, instructions, and review the displayed price before submitting.
- Allow the administrator to assign a request to a verified caretaker during the pilot.
- Let the assigned caretaker accept or decline and start the job.
- Require confirmation of the headstone name and grave details before work begins.
- Show a status timeline to the family.

### Initial workflow

Requested -> Assigned -> Accepted -> In progress -> Awaiting family review -> Completed

Also support declined assignments, cancellation, and reported issues with defined permissions. A decline returns the request for reassignment. Family review can either approve completion or open an issue.

### Purpose

Coordinate the work and make its progress understandable to all three roles.

### Completion criteria

- One family can request care and one assigned caretaker can perform it.
- Only the assigned, verified caretaker can update that job.
- The server enforces valid status transitions and records who changed the status and when.
- The request stores the agreed service and price so later catalog edits do not change the original request.

## 6. Photo evidence, family review, and issue handling

### Features

- Upload before-and-after photos and caretaker completion notes.
- Record submission times and preserve the request history.
- Present a clear photo comparison to the family.
- Let the family approve the work or report an issue.
- Let the administrator review and resolve issues with a recorded reason.
- Provide in-app updates for assignment, evidence submission, and review outcomes.

### Purpose

Make completed care reviewable and give families a clear way to raise concerns.

### Completion criteria

- Required evidence must be submitted before a job enters family review.
- Photo uploads have file type, size, and access checks and cannot execute as server code.
- Family approval completes the service; reported issues enter an administrative review flow.
- Photo evidence supports review but is not presented as an automatic guarantee of authenticity.
- Service completion and payment settlement are separate states. This phase does not claim to hold or release money.

## 7. Pilot verification and demonstration

### Features and checks

- Prepare clearly labeled demo accounts and sample data for all three roles.
- Walk through registration, request, assignment, caretaker confirmation, evidence, and family approval.
- Verify unauthorized access attempts, invalid status transitions, and rejected uploads.
- Check mobile usability, empty states, form errors, loading feedback, and interrupted submissions.
- Prepare setup, backup, and demonstration instructions.
- Record partner feedback and track request completion, turnaround, and reported issues.

### Purpose

Deliver a coherent prototype that the team can demonstrate and evaluate with its partner cemetery.

### Completion criteria

- The complete core workflow works with persisted database records.
- The team can demonstrate an approved job and a reported issue.
- Known limitations are documented, and demo transactions cannot be mistaken for real payments.

## Later phases

Prioritize these using pilot feedback rather than implementing them all before the core workflow works:

1. Family invitations and shared grave permissions.
2. Scheduled inspections and reminders.
3. Separate cemetery administrator accounts with cemetery-scoped permissions.
4. Real payment integration, transaction reconciliation, refunds, and provider payouts after the payment and operating model is agreed.
5. Undas planner and family visit coordination.
6. Optional memorial pages and authorized QR access.
7. PWA installation and carefully scoped offline behavior; authenticated data and uploads need explicit caching and retry rules.
8. Subscriptions, expanded reports, and multiple cemeteries after demand is validated.

## Explanation required after each implemented feature

Report each feature using this format:

### Feature: [name]

**Purpose:** The problem this feature solves.

**Who uses it:** The roles permitted to use it.

**How it works:** The user's actions, what the application records or checks, and the resulting outcome.

**Example:** A concrete Puntod Care scenario.

**How to try it:** The page to open and steps to follow, including demo credentials only when they exist and are safe to share.

**Verification:** What was actually tested and the outcome. State any unverified behavior.

**Current limits:** Any missing behavior that affects use of the feature.

Keep explanations in plain language. Include technical details only when they explain a meaningful behavior or limitation. Do not describe planned functionality as implemented.

### Example explanation: caretaker verification

**Purpose:** Allow the platform administrator to approve a caretaker before assigning grave-care work.

**Who uses it:** The administrator reviews the application; the caretaker sees their verification status.

**How it works:** A caretaker begins as pending. The administrator reviews the submitted information and records approval or rejection. The server checks verification status when assigning and accepting jobs.

**Example:** An administrator approves Maria after the required pilot checks. Maria becomes eligible for a cleaning assignment at the partner cemetery.

**How to try it / Verification / Current limits:** Supply these after implementation, based on the actual screens, checks, and supported behavior.

This example describes intended behavior, not an already implemented feature.
