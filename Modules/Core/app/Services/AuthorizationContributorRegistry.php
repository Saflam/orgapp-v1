<?php

namespace Modules\Core\Services;

use Modules\Core\Contracts\AuthorizationContributor;

class AuthorizationContributorRegistry
{
    /**
     * @var array<int, AuthorizationContributor>
     */
    private array $contributors = [];

    public function register(AuthorizationContributor $contributor): void
    {
        foreach ($this->contributors as $existing) {
            if ($existing::class === $contributor::class) {
                return;
            }
        }

        $this->contributors[] = $contributor;
    }

    /**
     * @return array<int, AuthorizationContributor>
     */
    public function all(): array
    {
        return $this->contributors;
    }
}
