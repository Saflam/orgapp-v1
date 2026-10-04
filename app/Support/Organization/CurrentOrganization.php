<?php

namespace App\Support\Organization;

use Modules\Core\Models\Organization;

class CurrentOrganization
{
    protected ?Organization $organization = null;

    public function set(Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function get(): Organization
    {
        if ($this->organization === null) {
            throw new \RuntimeException('No current organization has been set.');
        }

        return $this->organization;
    }

    public function check(): bool
    {
        return $this->organization !== null;
    }

    public function clear(): void
    {
        $this->organization = null;
    }
}