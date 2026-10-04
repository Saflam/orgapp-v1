<?php

namespace App\Support\Organization;

use Modules\Core\Models\Organization;

class OrganizationResolver
{
    public function resolve(string $host): ?Organization
    {
        return match (config('app.deployment_mode')) {
            'single' => $this->resolveSingleTenant(),
            'multi' => $this->resolveMultiTenant($host),
            default => null,
        };
    }

    protected function resolveSingleTenant(): ?Organization
    {
        $organizationId = config('app.default_organization_id');

        if (! $organizationId) {
            return null;
        }

        return Organization::query()
            ->whereKey($organizationId)
            ->where('is_active', true)
            ->first();
    }

    protected function resolveMultiTenant(string $host): ?Organization
    {
        $baseDomain = config('app.organization_base_domain');

        if (! $baseDomain) {
            return null;
        }

        $host = strtolower($host);
        $baseDomain = strtolower(ltrim($baseDomain, '.'));
        $suffix = '.' . $baseDomain;

        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $subdomain = substr($host, 0, -strlen($suffix));

        if ($subdomain === '' || str_contains($subdomain, '.')) {
            return null;
        }

        return Organization::query()
            ->where('slug', $subdomain)
            ->where('is_active', true)
            ->first();
    }
}