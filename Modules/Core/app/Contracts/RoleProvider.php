<?php

namespace Modules\Core\Contracts;

interface RoleProvider
{
    public function module(): ?string;

    /**
     * Return the roles provided by this module.
     *
     * @return array<int, array{
     *     name: string,
     *     code: string,
     *     description?: string|null,
     *     permissions?: array<int, string>
     * }>
     */
    public function roles(): array;
}