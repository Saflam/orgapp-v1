# Saradhi Events module — foundation + event management API

This is a new implementation. It does not import or reuse code from the legacy Events module.

## Dependencies

- Core: organization resolution, authorization, module registration, units.
- Member: declared module dependency; audience resolution will be added in a later phase.

## Included in this package

- Organization-scoped event and event-category schema.
- Central/Unit event scope and Public/Restricted visibility.
- Draft/Published/Cancelled lifecycle with derived schedule status.
- Permission registration through Core's `PermissionRegistry`.
- API endpoints for listing, creating, viewing, updating draft events, publishing, and cancelling.
- Default `Uncategorized` category creation per organization when an event is saved without a category.
- Unit and category ownership validation against the active organization.
- All event reads are scoped to the active organization; event IDs from another organization return not found.
- Editing is limited to draft events. Publishing does not send invitations.

## Routes

All endpoints are under `/api/v1/events` and require `auth`, `organization`, `organization.access`, and `module:Events` middleware plus the relevant permission.

- `GET /api/v1/events` — `events.view`
- `POST /api/v1/events` — `events.manage`
- `GET /api/v1/events/{event}` — `events.view`
- `PUT /api/v1/events/{event}` — `events.manage`; Draft only
- `POST /api/v1/events/{event}/publish` — `events.publish`
- `POST /api/v1/events/{event}/cancel` — `events.publish`

Creation payload fields: `title`, optional `subtitle`, `description`, `venue_name`, `venue_address`, required `starts_at`, `ends_at`, `scope` (`central` or `unit`), `visibility` (`public` or `restricted`), optional `unit_id` for unit scope, optional `category_id`, and optional `timezone`.

Central scope always clears `unit_id`. Unit scope requires a unit in the active organization. Categories must be active and belong to the active organization. Event creation always starts in Draft. Sending invitations is intentionally not part of publish and will be implemented separately.

## Installation

1. Archive the legacy Events module outside the auto-discovered `Modules` directory. Do not merge these files into the old module.
2. Copy this `Events` directory into `Modules/Events`.
3. Run `composer dump-autoload`.
4. Confirm module discovery and middleware aliases in the application.
5. Review migrations and run the project's normal migration/test workflow locally.

## Integration status

PHP syntax validation can be performed independently, but this package has not been executed against the user's application database. Run the project's Laravel feature suite before relying on these endpoints in a deployed environment.

## Backend implementation checkpoint: event categories

The API now includes organization-scoped category management:

- `GET /api/v1/events/categories` — list active categories by default; pass `include_inactive=true` to include inactive categories.
- `GET /api/v1/events/categories/{category}` — retrieve a category in the selected organization.
- `POST /api/v1/events/categories` — create a category (`events.settings.manage`).
- `PATCH /api/v1/events/categories/{category}` — update a non-system category (`events.settings.manage`).
- `DELETE /api/v1/events/categories/{category}` — delete an unused, non-system category (`events.settings.manage`).

Category slugs are generated from the supplied slug or name and made unique within the organization. System categories cannot be updated or deleted. A category already referenced by an event cannot be deleted; deactivate it instead. Category IDs are always resolved within the current organization, so an ID from another organization returns not found.

This checkpoint was syntax-checked with PHP. Full Laravel feature tests still need to run inside the Saradhi host application; the module ZIP alone does not include the application's test bootstrap, middleware aliases, factories, or database schema needed to execute integration tests.


## Feature tests

The module includes feature tests for category management and the event lifecycle:

- `tests/Feature/EventCategoryApiTest.php` covers category permissions, organization isolation, slug generation, and system/used-category protections.
- `tests/Feature/EventApiTest.php` covers event listing, creation and validation, default categories, draft updates, publish/cancel actions, permissions, and cross-organization isolation.

Run these from the Saradhi Laravel application root after copying the module into `Modules/Events`:

```powershell
php artisan test Modules/Events/tests/Feature/EventCategoryApiTest.php
php artisan test Modules/Events/tests/Feature/EventApiTest.php
php artisan test Modules/Events/tests/Feature
php artisan test
```

The ZIP does not include the host application's bootstrap, Core/Member modules, database, or test environment. PHP syntax checks can be performed on this package alone, but Laravel feature tests must be run in the Saradhi application.


## Participant invitations (initial backend workflow)

The participant invitation API records invitations for published, restricted events.
It supports existing organization members and non-member guests. It does not yet
send email/SMS notifications or generate event passes; those are subsequent workflow
steps.

### Routes

All routes use the organization's tenant context and the Events module middleware.

- `GET /api/v1/events/{event}/participants` — requires `events.view`.
- `POST /api/v1/events/{event}/participants` — requires `events.invitations.send`.

Example member invitation:

```json
{
  "participant_type": "member",
  "member_id": 123
}
```

Example guest invitation:

```json
{
  "participant_type": "guest",
  "name": "Guest Person",
  "email": "guest@example.com",
  "phone": "+919876543210"
}
```

Invitations are scoped to the current organization. Member invitations are rejected
if the member belongs to another organization. Manual invitations are only allowed
for published restricted events. Duplicate member invitations and duplicate guest
email addresses for the same event are rejected.

### Participant API tests

```powershell
php artisan test Modules/Events/tests/Feature/EventParticipantApiTest.php
php artisan test Modules/Events/tests/Feature
php artisan test
```


## Public event registration

Published events with `visibility=public` can accept unauthenticated registration on the organization's domain:

- `POST /api/v1/public/events/{event}/registrations` — no login required; organization host resolution and `module:Events` are still enforced. The endpoint is throttled to 10 requests per minute.

The event can define up to 20 registration fields in the `registration_fields` JSON property when creating or updating a draft event. Each field has a unique `key`, `label`, `type` (`text`, `email`, `phone`, `select`, or `checkbox`), optional `required`, and `options` for select fields. Example:

```json
{
  "registration_fields": [
    {"key": "dietary_preference", "label": "Dietary preference", "type": "select", "required": true, "options": ["Vegetarian", "No preference"]}
  ]
}
```

Registration requires `name` and `email`, accepts optional `phone`, and accepts configured answers under `custom_fields`. Duplicate email addresses for the same event are rejected. Answers are stored in the participant `metadata.custom_fields` field. Registrations create a guest participant with `status=registered`; notifications and event passes are not part of this stage.

Run public registration tests with:

```powershell
php artisan test Modules/Events/tests/Feature/PublicEventRegistrationApiTest.php
php artisan test Modules/Events/tests/Feature
php artisan test
```


## Event passes and notifications

- Authorized users issue one pass per participant with `POST /api/v1/events/{event}/participants/{participant}/pass`.
- The raw random bearer token is returned once to the authorized issuer; the database stores only its SHA-256 hash.
- If the participant has an email address (or a linked member user's email), pass email delivery is dispatched through `DeliverEventPassEmail`. The response reports `notification_status` and `notification_queued_to`; `queued` means dispatch was accepted, not that email delivery has completed.
- The pass notification renders the QR code using the installed `linkxtr/laravel-qrcode` package. The QR contains only the opaque pass token, not personal information.
- A scanner can validate a token with `POST /api/v1/public/event-passes/validate` and JSON `{ "token": "..." }`. The response intentionally omits participant email and phone.
- Pass issuance is manual and permission-protected. Automatic issuance on registration remains a separate workflow decision.

### Secure QR email delivery

The service renders the QR SVG before dispatch and encrypts the SVG with Laravel encryption before writing it to the private `local` storage disk. The queued job carries only the pass ID and encrypted artifact path; it does not serialize the raw bearer token, QR contents, or recipient email. The job resolves the recipient when it runs, rechecks event/participant/pass status, renders the SVG in the email, and deletes the encrypted artifact after successful delivery or terminal failure.

The `event_passes` record tracks notification delivery state (`not_requested`, `queued`, `sending`, `sent`, `failed`, or `cancelled`) and sent/failed timestamps. Reprocessing a job after delivery is recorded as `sent` skips another send. Email transport still has at-least-once semantics: if the mail provider accepts a message but the worker fails before the database records success, a retry may duplicate the email. No universal exactly-once guarantee is claimed.

The implementation uses the host application's configured Laravel queue connection. Redis configuration is intentionally deferred. If the configured queue driver is `sync`, the job executes during the request and does not provide durable asynchronous retries; use the currently supported persistent queue backend until Redis is implemented.

Run the pass delivery tests with:

```powershell
php artisan test Modules/Events/tests/Feature/EventPassApiTest.php
php artisan test Modules/Events/tests/Feature/EventNotificationQueueTest.php
```

## QR attendance scanning

Authorized scanners can record explicit entry and exit movements using the opaque token returned at pass issuance. The raw token is hashed before lookup, and pass updates run inside a database transaction with a row lock. The pass tracks its configured capacity and current number inside; each movement is retained in `event_attendance_scans`.

Endpoint: `POST /api/v1/events/attendance/scan` (requires `events.attendance.scan`). Example request:

```json
{
  "token": "<opaque-pass-token>",
  "action": "entry",
  "quantity": 1
}
```

Use `action: "exit"` for exits and re-entry is supported after a successful exit. `quantity` defaults to 1, which also supports group passes by admitting or exiting multiple people per scan. The endpoint does not return participant email or phone.

Run `php artisan test Modules/Events/tests/Feature/EventAttendanceApiTest.php`, then the full Events feature tests and `php artisan test`.

## Event attendance report API

Authorized users with `events.reports.view` can retrieve an organization-scoped event summary:

```http
GET /api/v1/events/{event}/report
```

The JSON response summarizes participant counts by type and status, pass counts and active capacity, current people inside, remaining capacity, passes never scanned, distinct passes scanned, and entry/exit scan movement counts and quantities. Access is scoped to the current organization.

**Reporting limitation:** attendance scans record quantities against a pass, not identities for each individual in a group. Therefore `entry_quantity_total` includes re-entry and must not be treated as a unique-person count. This checkpoint explicitly documents that limitation rather than reporting an unsupported unique-attendee figure. PDF export is a subsequent step.

Run the report tests with:

```powershell
php artisan test Modules/Events/tests/Feature/EventReportApiTest.php
php artisan test Modules/Events/tests/Feature
php artisan test
```


## Individual attendee tracking for group passes

Group passes can now have an explicit attendee roster. Create the roster before the first scan:

- `POST /api/v1/events/{event}/passes/{pass}/attendees` — requires `events.invitations.send`.
- `attendees` is an array of `{name, email?, category?}` objects. Categories supported are `member`, `guest`, `vip`, `volunteer`, and `other`.
- Roster size cannot exceed pass capacity, and the roster cannot be changed after attendance scans begin.
- For identity-aware scanning, send `attendee_ids` with the scan request instead of `quantity`, for example:

  ```json
  {"token":"<opaque-token>","action":"entry","attendee_ids":[101,102]}
  ```

  Exit scans use the same attendee IDs with `action: "exit"`. Each attendee has an inside/outside status, first and last entry times, last exit time, and entry count. Re-entry is supported; entering an attendee who is already inside or exiting one who is outside is rejected.

The existing quantity-only scan mode remains supported for passes without an attendee roster. A pass cannot mix quantity-only scans with identity-based attendee scans. Historical quantity-only scans cannot be converted into unique people retroactively; reports distinguish those movements from individual attendees whose identities were recorded.

Run the individual attendee tests with:

```powershell
php artisan test Modules/Events/tests/Feature/EventPassAttendeeApiTest.php
php artisan test Modules/Events/tests/Feature/EventAttendanceApiTest.php
php artisan test Modules/Events/tests/Feature/EventReportApiTest.php
php artisan test Modules/Events/tests/Feature
php artisan test
```

## Event volunteer assignment and attendance authorization

Attendance scanners must have both the organization-level `events.attendance.scan` permission and an active assignment to the specific event. An event manager with `events.volunteers.manage` can assign an active organization member:

- `GET /api/v1/events/{event}/volunteers` — list current and historical assignments.
- `POST /api/v1/events/{event}/volunteers` with `{"user_id": 123}` — assign an active organization member.
- `DELETE /api/v1/events/{event}/volunteers/{user}` — revoke the active assignment.

Assignment and revocation are retained as separate records so reassigning a previously revoked volunteer does not overwrite the earlier audit history. A user must have an active organization membership to be assigned. Revoked or unassigned users cannot scan passes for that event.

Additional permission: `events.volunteers.manage`.

Run the volunteer and attendance authorization tests with:

```powershell
php artisan test Modules/Events/tests/Feature/EventVolunteerApiTest.php
php artisan test Modules/Events/tests/Feature/EventAttendanceApiTest.php
php artisan test Modules/Events/tests/Feature/EventPassAttendeeApiTest.php
```

## Attendance audit history

Authenticated organization users with `events.attendance.audit.view` can inspect the attendance scan history for an event:

```http
GET /api/v1/events/{event}/attendance/scans
```

Supported query parameters:

- `action`: `entry` or `exit`
- `pass_id`: filter to a pass
- `attendee_id`: filter to an individually tracked attendee
- `scanned_by`: filter to the scanning user ID
- `from` and `to`: date/time range for `scanned_at`
- `per_page`: 1–100, default 25
- `page`: page number, default 1

The response is ordered newest-first and includes the scanner's ID/name, pass and participant summary, and attendee summary when an individual attendee was scanned. It deliberately does not expose email addresses, QR tokens, or token hashes. Results are scoped to the selected organization and event.

## Attendance report reconciliation

The event report separates quantity-based movements from identity-based attendee scans so group-pass quantities and individual people are not combined into a misleading unique-attendee figure. It also reports registered, inside, outside, and uniquely scanned rostered attendees, with category-level registered/inside/scanned counts.

The `data_quality` section compares each pass with an individual attendee roster against its `current_inside` value and reports `passes_with_occupancy_mismatch` plus `occupancy_reconciliation` (`consistent` or `review_required`). This is a read-only diagnostic: it does not modify attendance records. A mismatch should be investigated using the attendance audit endpoint before any correction is made.

Run report accuracy tests:

```powershell
php artisan test Modules/Events/tests/Feature/EventReportApiTest.php
php artisan test Modules/Events/tests/Feature/EventAttendanceAuditApiTest.php
php artisan test Modules/Events/tests/Feature
php artisan test
```

## Event and participant lifecycle controls

Lifecycle endpoints preserve attendance history while preventing cancelled or revoked records from being used for admission:

- `POST /api/v1/events/{event}/cancel` cancels the event and atomically revokes all active passes. Existing scan history and `current_inside` values are preserved for audit purposes. Further scans are rejected because the event is no longer published.
- `POST /api/v1/events/{event}/participants/{participant}/cancel` cancels a participant and revokes their active pass. Cancellation is idempotent. It is rejected while the participant's pass records one or more people inside the event.
- `POST /api/v1/events/{event}/passes/{pass}/revoke` revokes a pass. Revocation is idempotent and is rejected while the pass records people inside the event.

Participant cancellation and pass revocation require `events.invitations.send`; event cancellation requires `events.publish`. All endpoints scope records to the current organization and selected event. Revocation and cancellation do not delete historical attendance scans.

Run lifecycle tests with:

```powershell
php artisan test Modules/Events/tests/Feature/EventLifecycleApiTest.php
php artisan test Modules/Events/tests/Feature/EventPassApiTest.php
php artisan test Modules/Events/tests/Feature/EventAttendanceApiTest.php
php artisan test Modules/Events/tests/Feature
php artisan test
```

## Participant lifecycle email notifications

The participant workflow sends email notifications after the database transaction has committed for:

- Restricted-event invitations.
- Successful public registration.
- Participant cancellation.
- Event cancellation (to participants who have not already been cancelled or declined).

These lifecycle notifications implement Laravel's `ShouldQueue` contract and are dispatched after the calling database transaction commits. A mail transport failure that occurs later in a queue worker is not caught by the request-time notification dispatch code and cannot roll back the already committed registration or cancellation. Worker exceptions and terminal failures are handled through Laravel's configured logging and failed-job mechanisms. See **Notification queue reliability** below for retry policy and production operations. A durable outbox is not implemented by this module.

Run the notification-related feature tests with:

```powershell
php artisan test Modules/Events/tests/Feature/EventParticipantApiTest.php
php artisan test Modules/Events/tests/Feature/PublicEventRegistrationApiTest.php
php artisan test Modules/Events/tests/Feature/EventLifecycleApiTest.php
```


## Notification queue reliability

Participant lifecycle emails (invitation, public registration confirmation, participant cancellation, and event cancellation) implement Laravel's `ShouldQueue` contract. They are dispatched after the calling database transaction commits, use four maximum attempts, and retry with delays of 30, 120, and 300 seconds. Pass-issued emails use the secure `DeliverEventPassEmail` job described above.

Redis configuration is intentionally deferred. The module uses the host application's configured Laravel queue connection. Local verification confirmed that this application currently uses the `database` queue connection and `database-uuids` failed-job backend, and the user has verified queue-worker execution and passing feature tests. Do not repeat queue implementation work unless a reproducible defect is found. Before production, confirm the deployment uses a persistent queue connection, a supervised worker, and a real mail transport. A `sync` queue driver executes jobs during the request and does not provide durable asynchronous retries.

```powershell
php artisan queue:work --tries=4 --timeout=60
```

Inspect and retry failed jobs using the application's configured failed-job driver:

```powershell
php artisan queue:failed
php artisan queue:retry all
```

Queue retry delivery is **at-least-once**: if a mail provider accepts a message but the worker crashes before acknowledging the job, a retry can send a duplicate. Exactly-once email delivery cannot be guaranteed by Laravel queues alone.

### Operational verification for notification delivery

Feature tests verify job policy and terminal failure state handling, but they do not prove a persistent worker is running in the deployment environment. Before production:

1. Configure a persistent Laravel queue connection and a durable failed-job backend (`QUEUE_CONNECTION` must not be `sync`).
2. Run a worker under a process supervisor (for example, Supervisor or the platform's process manager), with the configured queue and timeout aligned to the job policy.
3. Dispatch a test event pass to a controlled mailbox and confirm the pass moves from `queued` to `sent`, the email contains a QR image, and the encrypted artifact is removed.
4. In a non-production environment, simulate a mail transport failure; verify retry attempts occur and terminal failure updates `notification_delivery_status=failed` and `notification_failed_at`.
5. Inspect failed jobs with `php artisan queue:failed`; after correcting the transport/configuration, retry with `php artisan queue:retry <id>` or `php artisan queue:retry all`. Verify the delivery-status endpoint reflects recovery.
6. Confirm worker restart/deployment behavior and alerting for growing queue depth or failed-job counts.

Useful commands (the exact queue name/connection must match your deployment):

```powershell
php artisan queue:work --tries=4 --timeout=60
php artisan queue:failed
php artisan queue:retry all
php artisan queue:restart
```

Do not run a one-off foreground worker as the production process manager. Keep the worker alive using the deployment's supervisor. Email delivery is at-least-once: a provider acceptance followed by a worker crash before success is recorded can result in a duplicate email; this cannot be made exactly-once by queue retries alone.

Run notification queue policy tests with:

```powershell
php artisan test Modules/Events/tests/Feature/EventNotificationQueueTest.php
php artisan test Modules/Events/tests/Feature/EventPassApiTest.php
php artisan test Modules/Events/tests/Feature/EventParticipantApiTest.php
php artisan test Modules/Events/tests/Feature/PublicEventRegistrationApiTest.php
php artisan test Modules/Events/tests/Feature/EventLifecycleApiTest.php
php artisan test Modules/Events/tests/Feature
php artisan test
```


## Pass notification recovery and monitoring

Delivery status is available to users with `events.invitations.send` permission:

- `GET /api/v1/events/{event}/pass-deliveries` returns delivery-state counts and a paginated list of failed deliveries. It does not return email addresses, QR payloads, tokens, or token hashes.
- `POST /api/v1/events/{event}/passes/{pass}/retry-delivery` retries a failed delivery only. The endpoint rotates the pass token, invalidating the old QR token, then creates a new encrypted QR artifact and queues delivery. The replacement token is never returned by the retry endpoint.

A retry is rejected unless the event is published, the pass is active, the participant is not cancelled/declined, the delivery status is `failed`, and no people are recorded inside the pass. A retry requires a valid participant email address. This avoids attempting to reuse a QR artifact that was deleted after the previous job reached terminal failure.

Redis is not required for this feature. Jobs use the host application's configured Laravel queue connection. If that connection is `sync`, delivery executes synchronously; use a persistent queue backend and worker for asynchronous delivery.


### Pass-validation tenant isolation

Public pass-token validation is scoped to the organization resolved from the request host. A valid token issued by one organization must not reveal event or participant details when submitted to another organization's endpoint.

## Security hardening: API rate limits

The attendance scan endpoint uses the named `events-attendance` limiter: 60 requests per minute per authenticated user and organization host. Pass-delivery retries use `events-pass-retry`: 10 requests per minute per authenticated user and organization host. Public registration remains limited to 10 requests per minute, and public pass-token validation remains limited to 30 requests per minute.

These limits are abuse controls, not a replacement for event-specific volunteer authorization or pass/capacity validation. The limiter uses Laravel's configured cache backend; shared, production-grade rate limiting across multiple app instances depends on using a shared cache backend (Redis is planned for a later phase).

Run the rate-limit regression test with:

```powershell
php artisan test Modules/Events/tests/Feature/EventRateLimitTest.php
php artisan test Modules/Events/tests/Feature
php artisan test
```

## Attendance concurrency and data integrity

Attendance scans lock the event-pass row inside a database transaction before checking or changing occupancy. Attendee rows are also locked in ascending primary-key order for identity-based batch scans. Pass roster changes, revocation, and participant cancellation use the same pass-row locking boundary where applicable.

Before recording a new movement, the attendance service checks the stored occupancy counter against its source records:

- For quantity-based passes, `current_inside` must equal the sum of entry quantities minus exit quantities.
- For identity-based passes, `current_inside` must equal the number of rostered attendees whose status is `inside`; quantity-only scan history must not be mixed into that pass.

If the values disagree, the scan is rejected with a validation error and the service does not silently repair the data. Review the attendance audit and reconcile the records before scanning again. This avoids compounding an existing discrepancy without an auditable correction.

Run the integrity regression tests with:

```powershell
php artisan test Modules/Events/tests/Feature/EventAttendanceApiTest.php
php artisan test Modules/Events/tests/Feature/EventPassAttendeeApiTest.php
php artisan test Modules/Events/tests/Feature
php artisan test
```

The attendance feature tests cover entry/exit/re-entry reconciliation, mismatched occupancy counters, invalid mixed attendee batches, and transaction rollback when a later scan insert fails. The rollback test deliberately throws during the second scan-record insert and verifies that attendee state, pass occupancy, and scan history all return to their original state.

`lockForUpdate()` provides effective row-level serialization on supported transactional databases such as MySQL/InnoDB and PostgreSQL. SQLite-based feature tests can validate state transitions and rollback behavior, but they do not prove production row-lock concurrency. A true simultaneous-request test must use separate processes/connections and the same database engine configured for production. Run two entry requests at the same time against a pass with capacity 1; exactly one should succeed, the other should be rejected, `current_inside` must remain 1, and exactly one entry scan must be stored. Repeat with overlapping requests for the same individual attendee and verify there is only one valid state transition. Do not treat sequential HTTP feature tests as proof of concurrency safety.

## Registration limits, venue capacity, and attendance corrections

Events support optional `registration_opens_at`, `registration_closes_at`, `registration_capacity`, and `venue_capacity` fields. Null capacities mean unlimited. Registration windows are evaluated using the event timezone (falling back to the application timezone). The registration capacity counts participants whose status is not `cancelled` or `declined`; invitations and public registrations both consume capacity. Event rows are locked while capacity is checked and a participant is created so concurrent registration requests serialize on databases with row-level locking.

Venue capacity represents the maximum number of people currently inside the event. Attendance scans lock the event row before locking the pass, then sum the other passes' `current_inside` values before committing an entry. This serializes admissions across passes for the same event when the database supports `SELECT ... FOR UPDATE`.

Authorized users with `events.attendance.correct` may call `POST /api/v1/events/{event}/passes/{pass}/attendance-corrections` with `current_inside` and a mandatory reason of at least 10 characters. This workflow is intentionally limited to quantity-based passes; individual-attendee passes require an attendee-level correction workflow and are rejected here. Corrections preserve original scan rows and append a separate correction record containing actor, timestamp, reason, before/after occupancy, and occupancy delta. `GET /api/v1/events/{event}/attendance/corrections` requires `events.attendance.audit.view` and lists correction records. Quantity-based reconciliation includes recorded correction deltas.

Suggested regression commands:

```powershell
php artisan test Modules/Events/tests/Feature/PublicEventRegistrationApiTest.php
php artisan test Modules/Events/tests/Feature/EventAttendanceApiTest.php
php artisan test Modules/Events/tests/Feature
php artisan test
```

True concurrency guarantees still require integration testing with separate processes/connections against the intended production database engine. SQLite feature tests alone do not prove row-lock behavior.


## Business-rule checkpoint (October 2026)

- Added an explicit participant no-show operation, restricted to events that have ended. Cancelled/declined participants and participants with any attendance scan cannot be marked as no-shows. The operation is exposed at `POST /api/v1/events/{event}/participants/{participant}/no-show` and requires `events.participants.no_show`.
- New entry scans are rejected after the event end time. Exit scans remain allowed after the event ends so occupancy can be reconciled and attendance history retained. Public pass validation no longer treats a pass as valid after the event has ended.
- Draft event updates reject removal of custom registration fields that already have stored answers, reject type changes, and reject select-option changes that exclude a stored answer.
- Event list/detail queries use Core's `AuthorizationService` for organization-wide or exact-unit `events.view` authorization. Exact-unit assignments do not imply child-unit access.

Authorization scope note: the list/detail query integration uses Core's `AuthorizationService` for organization-wide or exact-unit `events.view` access; exact-unit assignments do not imply access to child units. The module's current tests passing is not, by itself, proof that every participant, pass, attendance, volunteer, and report endpoint enforces endpoint-wide unit authorization. Keep any additional route-level unit-scope verification on the security/deployment checklist rather than reopening queue implementation work.
