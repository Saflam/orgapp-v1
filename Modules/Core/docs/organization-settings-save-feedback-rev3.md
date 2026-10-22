# Organization Settings Save Feedback — Revision 3

## Changes
- The organization settings Livewire component now exposes a `successMessage` property and displays an inline success notice after a successful save.
- The submit button is disabled while the `save` action is running and displays `Saving…` to provide immediate feedback.
- The success notice uses `role="status"` and `aria-live="polite"` for assistive technologies.
- The saved theme value remains a preference stored under `ui.theme`; applying it consistently across all module layouts is a separate follow-up task.

## Manual UI test
1. Log in as the Saradhi Organization Super Administrator.
2. Open `/organization/settings` on the Saradhi tenant host.
3. Change the organization name, timezone, currency, or preferred theme to valid values.
4. Click **Save settings**.
5. Confirm the button temporarily shows **Saving…**, then an inline message says **Organization settings saved successfully.**
6. Refresh the page and confirm the saved values remain.
7. Submit invalid values and confirm validation errors appear and no success notice is shown for the failed submission.
8. Confirm a user without `organization.settings.manage` remains forbidden.

## Test commands
Run from the Laravel application root:

```powershell
composer dump-autoload
php artisan optimize:clear
php artisan test Modules/Core/Tests/Feature/Organization/Ui/OrganizationManagementUiTest.php
php artisan test Modules/Core/Tests/Feature/Authorization/OrganizationSettingsPermissionRegistrationTest.php
php artisan test
```

The package's PHP files should also be linted. Laravel tests must be run in the host application; they are not claimed as executed by packaging validation.
