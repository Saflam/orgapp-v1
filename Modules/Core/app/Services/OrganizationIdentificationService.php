<?php

namespace Modules\Core\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\IdentificationType;
use Modules\Core\Models\Organization;
use Modules\Core\Models\OrganizationIdentification;

class OrganizationIdentificationService
{
    private const DOCUMENT_REQUIREMENT_TYPES = [
        'document',
        'front',
        'back',
    ];

    public function configure(
        Organization $organization,
        IdentificationType $identificationType,
        bool $isEnabled = true,
        bool $isRequired = false,
        bool $requiresDocument = false,
        array $documentRequirements = [],
    ): OrganizationIdentification {
        if ($requiresDocument && $documentRequirements === []) {
            $documentRequirements = ['document'];
        }

        $this->validateDocumentRequirements(
            isEnabled: $isEnabled,
            isRequired: $isRequired,
            requiresDocument: $requiresDocument,
            documentRequirements: $documentRequirements,
        );

        return DB::transaction(function () use (
            $organization,
            $identificationType,
            $isEnabled,
            $isRequired,
            $requiresDocument,
            $documentRequirements,
        ) {
            return OrganizationIdentification::updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'identification_type_id' => $identificationType->id,
                ],
                [
                    'is_enabled' => $isEnabled,
                    'is_required' => $isRequired,
                    'requires_document' => $requiresDocument,
                    'document_requirements' => $documentRequirements,
                ],
            );
        });
    }

    public function enable(
        Organization $organization,
        IdentificationType $identificationType,
    ): OrganizationIdentification {
        return $this->configure(
            organization: $organization,
            identificationType: $identificationType,
            isEnabled: true,
        );
    }

    public function disable(
        Organization $organization,
        IdentificationType $identificationType,
    ): OrganizationIdentification {
        return $this->configure(
            organization: $organization,
            identificationType: $identificationType,
            isEnabled: false,
        );
    }

    public function setRequired(
        Organization $organization,
        IdentificationType $identificationType,
        bool $required = true,
    ): OrganizationIdentification {
        $configuration = $organization->identifications()
            ->where('identification_type_id', $identificationType->id)
            ->first();

        if (
            $required
            && $configuration !== null
            && ! $configuration->is_enabled
        ) {
            throw ValidationException::withMessages([
                'identification_type' =>
                    'A disabled identification type cannot be required.',
            ]);
        }

        return $this->configure(
            organization: $organization,
            identificationType: $identificationType,
            isEnabled: $configuration?->is_enabled ?? true,
            isRequired: $required,
            requiresDocument: $configuration?->requires_document ?? false,
            documentRequirements:
                $configuration?->document_requirements ?? [],
        );
    }

    public function setDocumentRequired(
        Organization $organization,
        IdentificationType $identificationType,
        bool $required = true,
        array $documentRequirements = [],
    ): OrganizationIdentification {
        $configuration = $organization->identifications()
            ->where('identification_type_id', $identificationType->id)
            ->first();

        if (
            $required
            && $configuration !== null
            && ! $configuration->is_enabled
        ) {
            throw ValidationException::withMessages([
                'identification_type' =>
                    'A disabled identification type cannot require a document.',
            ]);
        }

        if ($required && $documentRequirements === []) {
            $documentRequirements = ['document'];
        }

        return $this->configure(
            organization: $organization,
            identificationType: $identificationType,
            isEnabled: $configuration?->is_enabled ?? true,
            isRequired: $configuration?->is_required ?? false,
            requiresDocument: $required,
            documentRequirements: $required
                ? $documentRequirements
                : [],
        );
    }

    public function get(
        Organization $organization,
        IdentificationType $identificationType,
    ): ?OrganizationIdentification {
        return $organization->identifications()
            ->where('identification_type_id', $identificationType->id)
            ->first();
    }

    public function configurations(Organization $organization)
    {
        return $organization->identifications()
            ->with('identificationType')
            ->get();
    }

    public function enabled(Organization $organization)
    {
        return $organization->identifications()
            ->with('identificationType')
            ->where('is_enabled', true)
            ->get();
    }

    public function required(Organization $organization)
    {
        return $organization->identifications()
            ->with('identificationType')
            ->where('is_enabled', true)
            ->where('is_required', true)
            ->get();
    }

    private function validateDocumentRequirements(
        bool $isEnabled,
        bool $isRequired,
        bool $requiresDocument,
        array $documentRequirements,
    ): void {
        if (! $isEnabled && ($isRequired || $requiresDocument)) {
            throw ValidationException::withMessages([
                'identification_type' =>
                    'A disabled identification type cannot be required or require a document.',
            ]);
        }

        $invalidRequirements = array_values(
            array_diff(
                $documentRequirements,
                self::DOCUMENT_REQUIREMENT_TYPES,
            )
        );

        if ($invalidRequirements !== []) {
            throw ValidationException::withMessages([
                'document_requirements' =>
                    'Invalid document requirement type: '
                    . implode(', ', $invalidRequirements)
                    . '. Allowed values are document, front, and back.',
            ]);
        }

        if (! $requiresDocument && $documentRequirements !== []) {
            throw ValidationException::withMessages([
                'document_requirements' =>
                    'Document requirements cannot be configured when document upload is not required.',
            ]);
        }

        if (
            in_array('document', $documentRequirements, true)
            && (
                in_array('front', $documentRequirements, true)
                || in_array('back', $documentRequirements, true)
            )
        ) {
            throw ValidationException::withMessages([
                'document_requirements' =>
                    'The document requirement cannot contain document together with front or back.',
            ]);
        }

        if (
            in_array('back', $documentRequirements, true)
            && ! in_array('front', $documentRequirements, true)
        ) {
            throw ValidationException::withMessages([
                'document_requirements' =>
                    'A back document requires a front document.',
            ]);
        }

        if ($requiresDocument && $documentRequirements === []) {
            throw ValidationException::withMessages([
                'document_requirements' =>
                    'At least one document requirement is required when document upload is required.',
            ]);
        }
    }
}