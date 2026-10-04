<?php

namespace Modules\Member\Contracts;

use Modules\Core\Models\Organization;
use Modules\Member\Models\Member;

interface MembershipApplicationExtension
{
    /**
     * The module that owns this extension.
     */
    public function module(): string;

    /**
     * Fields contributed to the membership application.
     *
     * This can later be consumed by the frontend to build
     * the appropriate form sections.
     *
     * @return array<string, array<string, mixed>>
     */
    public function fields(): array;

    /**
     * Validation rules contributed by this extension.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array;

    /**
     * Persist extension-specific application data.
     *
     * @param array<string, mixed> $data
     */
    public function persist(
        Organization $organization,
        Member $member,
        array $data,
    ): void;
}