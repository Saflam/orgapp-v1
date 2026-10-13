<?php

namespace Modules\Finance\Contracts;

interface FinanceModuleIntegration
{
    public function sourceModule(): string;

    /** @return array<int, array<string, mixed>> */
    public function accountDefinitions(int $organizationId): array;

    /** @return array<int, array<string, mixed>> */
    public function itemDefinitions(int $organizationId): array;
}
