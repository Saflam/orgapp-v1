<?php

namespace Modules\Core\Livewire\Organizations;

use App\Support\Organization\CurrentOrganization;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Core\Models\IdentificationType;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthorizationService;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Services\OrganizationIdentificationService;

class OrganizationIdentificationSettings extends Component
{
    public Organization $organization;

    /** @var array<int, array<string, mixed>> */
    public array $settings = [];

    public string $successMessage = '';

    public string $newTypeName = '';
    public string $newTypeDescription = '';
    public bool $newTypeIsRequired = false;
    public bool $newTypeRequiresDocument = false;
    /** @var array<int, string> */
    public array $newTypeDocumentRequirements = [];

    public function mount(
        ?Organization $organization = null,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        OrganizationIdentificationService $identifications,
    ): void {
        // Livewire may supply an empty Organization model when this tenant
        // route has no {organization} parameter. Prefer it only when it is persisted.
        $this->organization = ($organization instanceof Organization
            && $organization->exists
            && $organization->id !== null)
                ? $organization
                : $currentOrganization->get();

        $this->ensureCanManage($authorization);
        $this->loadSettings($identifications);
    }

    public function addIdentificationType(
        AuthorizationService $authorization,
        OrganizationIdentificationService $identifications,
    ): void {
        $this->ensureCanManage($authorization);
        $this->validate([
            'newTypeName' => ['required', 'string', 'max:255'],
            'newTypeDescription' => ['nullable', 'string', 'max:2000'],
            'newTypeIsRequired' => ['required', 'boolean'],
            'newTypeRequiresDocument' => ['required', 'boolean'],
            'newTypeDocumentRequirements' => ['array'],
            'newTypeDocumentRequirements.*' => ['in:document,front,back'],
        ]);

        $documentRequirements = $this->newTypeRequiresDocument
            ? array_values(array_unique($this->newTypeDocumentRequirements))
            : [];

        if ($this->newTypeRequiresDocument && $documentRequirements === []) {
            $documentRequirements = ['document'];
        }

        $type = IdentificationType::query()->create([
            'name' => trim($this->newTypeName),
            'description' => trim($this->newTypeDescription) !== '' ? trim($this->newTypeDescription) : null,
        ]);

        $identifications->configure(
            organization: $this->organization,
            identificationType: $type,
            isEnabled: true,
            isRequired: $this->newTypeIsRequired,
            requiresDocument: $this->newTypeRequiresDocument,
            documentRequirements: $documentRequirements,
        );

        $this->newTypeName = '';
        $this->newTypeDescription = '';
        $this->newTypeIsRequired = false;
        $this->newTypeRequiresDocument = false;
        $this->newTypeDocumentRequirements = [];
        $this->resetValidation();
        $this->loadSettings($identifications);
        $this->successMessage = 'Identification type added and its initial requirements saved.';
    }

    public function save(
        AuthorizationService $authorization,
        OrganizationIdentificationService $identifications,
    ): void {
        $this->successMessage = '';
        $this->ensureCanManage($authorization);

        $validated = $this->validate([
            'settings' => ['required', 'array'],
            'settings.*.is_enabled' => ['required', 'boolean'],
            'settings.*.is_required' => ['required', 'boolean'],
            'settings.*.requires_document' => ['required', 'boolean'],
            'settings.*.document_requirements' => ['array'],
            'settings.*.document_requirements.*' => ['in:document,front,back'],
            'settings.*.has_expiry' => ['required', 'boolean'],
            'settings.*.expiry_reminder_days' => ['array'],
            'settings.*.expiry_reminder_days.*' => ['integer', 'min:0', 'max:3650'],
        ]);

        $types = IdentificationType::query()
            ->whereIn('id', array_map('intval', array_keys($validated['settings'])))
            ->get()
            ->keyBy('id');

        foreach ($validated['settings'] as $typeId => $setting) {
            $type = $types->get((int) $typeId);
            abort_unless($type, 404);

            $requirements = array_values(array_unique($setting['document_requirements'] ?? []));
            $reminders = array_values(array_unique(array_map('intval', $setting['expiry_reminder_days'] ?? [])));

            $identifications->configure(
                organization: $this->organization,
                identificationType: $type,
                isEnabled: (bool) $setting['is_enabled'],
                isRequired: (bool) $setting['is_required'],
                requiresDocument: (bool) $setting['requires_document'],
                documentRequirements: $requirements,
                hasExpiry: (bool) $setting['has_expiry'],
                expiryReminderDays: $reminders,
            );
        }

        $this->loadSettings($identifications);
        $this->successMessage = 'Identification requirements saved successfully.';
    }

    public function render(AuthorizationService $authorization): View
    {
        $this->ensureCanManage($authorization);

        return view('core::livewire.organizations.identification-settings')
            ->layout('core::layouts.organization-admin', [
                'pageTitle' => 'Identification requirements',
                'layoutOrganization' => $this->organization,
            ]);
    }

    private function loadSettings(OrganizationIdentificationService $identifications): void
    {
        $existing = $identifications->configurations($this->organization)->keyBy('identification_type_id');

        $this->settings = IdentificationType::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (IdentificationType $type) use ($existing): array {
                $configuration = $existing->get($type->id);

                return [$type->id => [
                    'name' => $type->name,
                    'code' => $type->code,
                    'description' => $type->description ?? '',
                    'is_enabled' => (bool) ($configuration?->is_enabled ?? false),
                    'is_required' => (bool) ($configuration?->is_required ?? false),
                    'requires_document' => (bool) ($configuration?->requires_document ?? false),
                    'document_requirements' => $configuration?->document_requirements ?? [],
                    'has_expiry' => (bool) ($configuration?->has_expiry ?? false),
                    'expiry_reminder_days' => $configuration?->expiry_reminder_days ?? [],
                ]];
            })
            ->all();
    }

    private function ensureCanManage(AuthorizationService $authorization): void
    {
        $user = auth()->user();

        if ($user?->is_system_admin) {
            return;
        }

        abort_unless(
            $user
            && $this->organization->exists
            && $this->organization->id !== null
            && $authorization->can(
                $user,
                'organization.settings.manage',
                new AuthorizationContext((int) $this->organization->id),
            ),
            403,
        );
    }
}
