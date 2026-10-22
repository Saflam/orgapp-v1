<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Livewire\AccessControl\OrganizationUsers;
use Modules\Core\Livewire\AccessControl\OrganizationRoles;
use Modules\Core\Livewire\AccessControl\OrganizationRoleAssignments;
use Modules\Core\Livewire\Organizations\OrganizationIndex;
use Modules\Core\Livewire\Organizations\OrganizationIdentificationSettings;
use Modules\Core\Livewire\Organizations\OrganizationManage;
use Modules\Core\Livewire\Organizations\OrganizationSelfSettings;
use Modules\Core\Livewire\Organizations\OrganizationWizard;
use Modules\Core\Livewire\Organizations\OrganizationWhatsAppSettings;
use Modules\Core\Livewire\Organizations\UnitManagement;
use Modules\Core\Livewire\System\SystemWhatsAppSettings;
use Modules\Core\Livewire\System\SystemWhatsAppUsage;
use Modules\Core\Livewire\System\SystemWhatsAppUsageRecords;
use Modules\Core\Livewire\System\SystemWhatsAppCostReconciliation;
use Modules\Core\Livewire\System\SystemWhatsAppFailedDeliveries;
use Modules\Core\Livewire\System\SystemWhatsAppMessageHistory;
use Modules\Core\Livewire\System\WhatsAppTemplateGuide;

Route::middleware(['auth', 'system.admin'])
    ->prefix('system/organizations')
    ->name('core.organizations.')
    ->group(function (): void {
        Route::get('/', OrganizationIndex::class)->name('index');
        Route::get('/create', OrganizationWizard::class)->name('create');
        Route::get('/{organization}/manage', OrganizationManage::class)->name('manage');
        Route::get('/{organization}/identifications', OrganizationIdentificationSettings::class)->name('identifications');
        Route::get('/{organization}/units', UnitManagement::class)->name('units');
    });

Route::middleware(['auth', 'organization', 'organization.access'])
    ->get('/organization/settings', OrganizationSelfSettings::class)
    ->name('core.organization.settings');

Route::middleware(['auth', 'organization', 'organization.access'])->group(function (): void {
    Route::get('/organization/access-control/users', OrganizationUsers::class)->name('core.access-control.users');
    Route::get('/organization/access-control/roles', OrganizationRoles::class)->name('core.access-control.roles');
    Route::get('/organization/access-control/assignments', OrganizationRoleAssignments::class)->name('core.access-control.assignments');
});

Route::middleware(['auth', 'organization', 'organization.access'])
    ->get('/organization/identifications', OrganizationIdentificationSettings::class)
    ->name('core.organization.identifications');

Route::middleware(['auth', 'organization', 'organization.access'])
    ->get('/organization/units', UnitManagement::class)
    ->name('core.organization.units');

Route::middleware(['auth', 'system.admin'])->group(function (): void {
    Route::get('/system/whatsapp-settings', SystemWhatsAppSettings::class)->name('core.whatsapp.settings');
    Route::get('/system/whatsapp-usage', SystemWhatsAppUsage::class)->name('core.whatsapp.usage');
    Route::get('/system/whatsapp-usage-records', SystemWhatsAppUsageRecords::class)->name('core.whatsapp.usage-records');
    Route::get('/system/whatsapp-cost-reconciliation', SystemWhatsAppCostReconciliation::class)->name('core.whatsapp.cost-reconciliation');
    Route::get('/system/whatsapp-failed-deliveries', SystemWhatsAppFailedDeliveries::class)->name('core.whatsapp.failed-deliveries');
    Route::get('/system/whatsapp-message-history', SystemWhatsAppMessageHistory::class)->name('core.whatsapp.message-history');
    Route::get('/system/whatsapp-templates', WhatsAppTemplateGuide::class)->name('core.whatsapp.templates');
});

Route::middleware(['auth', 'organization', 'organization.access'])
    ->get('/organization/notifications', OrganizationWhatsAppSettings::class)
    ->name('core.organization.notifications');
