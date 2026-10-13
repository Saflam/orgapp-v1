<?php

namespace Modules\Finance\Services;

use App\Models\User;
use Modules\Core\Models\Organization;
use Modules\Finance\Enums\FinanceCustomerType;
use Modules\Finance\Models\FinanceCustomer;

class FinanceCustomerService
{
    public function resolveMemberCustomer(Organization $organization, User $user, array $attributes = []): FinanceCustomer
    {
        return FinanceCustomer::updateOrCreate(
            ['organization_id' => $organization->id, 'user_id' => $user->id],
            [
                'customer_type' => FinanceCustomerType::MEMBER,
                'name' => $attributes['name'] ?? $user->name,
                'email' => $attributes['email'] ?? $user->email,
                'phone' => $attributes['phone'] ?? $user->phone,
                'billing_address' => $attributes['billing_address'] ?? null,
                'is_system_managed' => true,
                'is_active' => true,
                'metadata' => $attributes['metadata'] ?? [],
            ]
        );
    }
}
