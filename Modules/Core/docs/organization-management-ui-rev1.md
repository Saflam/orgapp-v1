# Core Organization Management UI — Revision 1

## Included

- System Admin organization list with search, active/inactive filtering, pagination, and activate/deactivate actions.
- Four-step organization creation wizard: details, initial Organization Super Admin, optional modules and system-default SMS/WhatsApp access, and review.
- Organization management screen for module activation, system-default notification access, and organization lifecycle.
- Organization Super Admin general settings screen scoped to the organization resolved from the current host, including a saved preferred theme setting.
- `Core` and `Member` are displayed as required and locked in the creation wizard.
- System-default notification access and module management are System Admin-only.
- API route for changing per-organization system-default notification access moved under the System Admin route group.
- Regression tests added for UI access and system-default notification authorization.

## Not included yet

- Logo/favicon uploads and applying the saved theme across all module layouts.
- A general System Admin edit form for organization name/timezone/currency.
- Dedicated UI for global SMS/WhatsApp provider credentials.
- A dedicated organization listing API endpoint (the UI queries the model directly behind the System Admin route).

## Installation

1. Back up `Modules/Core` in the Laravel application.
2. Extract the revision ZIP and replace the existing `Modules/Core` directory with the included `Core` directory contents. Do not overwrite the application's root files.
3. Run `composer dump-autoload` if your module autoload configuration is not refreshed automatically.
4. Clear cached routes/views: `php artisan optimize:clear`.
5. Confirm Livewire is installed in the host application: `composer show livewire/livewire`.
6. Run the focused tests below before opening the UI.

## UI routes

- System Admin organization list: `/system/organizations`
- Create organization wizard: `/system/organizations/create`
- Manage organization: `/system/organizations/{organization}/manage`
- Organization Admin general settings: `/organization/settings` (requires organization host resolution and organization access middleware)

## Testing instructions

Run from the Laravel application root:

```powershell
php artisan test Modules/Core/Tests/Feature/Organization/Ui/OrganizationManagementUiTest.php
php artisan test Modules/Core/Tests/Feature/Organization/Ui/SystemDefaultNotificationAccessAuthorizationTest.php
php artisan test Modules/Core/Tests/Feature/Organization/CreateOrganizationApiTest.php
php artisan test Modules/Core/Tests/Feature/Organization/OrganizationModuleApiTest.php
php artisan test Modules/Core/Tests/Feature/Organization/OrganizationServiceTest.php
php artisan test
```

If a test fails, share the complete failure output before changing the implementation.

## Postman

Import `postman/Saflivo.postman_collection.json` as a replacement for the current Saflivo collection. It keeps the `Authentication` and `Organization` top-level folders and includes every Core API endpoint found in the supplied Core module routes, grouped into subfolders. Set `baseUrl`, then authenticate and save the returned bearer token to `token`.

The former organization-context endpoint `PUT /api/v1/organization/notification-channels/{channel}/default` is intentionally removed. Granting/revoking system-default channel access is now System Admin-only at:

`PUT /api/v1/system/organizations/{organizationId}/notification-channels/{channel}/default`

Body:

```json
{
  "use_system_default": true
}
```
