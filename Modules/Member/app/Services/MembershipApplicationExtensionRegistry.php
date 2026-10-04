<?php

namespace Modules\Member\Services;

use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Member\Contracts\MembershipApplicationExtension;
use Modules\Member\Models\Member;

class MembershipApplicationExtensionRegistry
{
    /**
     * @var array<string, MembershipApplicationExtension>
     */
    private array $extensions = [];

    public function __construct(
        private OrganizationModuleService $organizationModuleService,
    ) {
    }

    public function register(
        MembershipApplicationExtension $extension,
    ): void {
        $this->extensions[$extension->module()] = $extension;
    }

    /**
     * @return array<string, MembershipApplicationExtension>
     */
    public function enabledFor(
        Organization $organization,
    ): array {
        return collect($this->extensions)
            ->filter(
                fn (MembershipApplicationExtension $extension): bool =>
                    $this->organizationModuleService->isEnabled(
                        $organization,
                        $extension->module(),
                    )
            )
            ->all();
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function fields(
        Organization $organization,
    ): array {
        return collect($this->enabledFor($organization))
            ->mapWithKeys(
                fn (MembershipApplicationExtension $extension): array => [
                    $extension->module() => $extension->fields(),
                ]
            )
            ->all();
    }

    /**
     * @return array<string, array<string, array<int, mixed>>>
     */
    public function rules(
        Organization $organization,
    ): array {
        return collect($this->enabledFor($organization))
            ->mapWithKeys(
                fn (MembershipApplicationExtension $extension): array => [
                    $extension->module() => $extension->rules(),
                ]
            )
            ->all();
    }

    /**
     * Persist all enabled extension data.
     *
     * @param array<string, array<string, mixed>> $extensionData
     */
    public function persist(
        Organization $organization,
        Member $member,
        array $extensionData,
    ): void {
        foreach ($this->enabledFor($organization) as $extension) {
            $data = $extensionData[$extension->module()] ?? [];

            $extension->persist(
                organization: $organization,
                member: $member,
                data: $data,
            );
        }
    }
}