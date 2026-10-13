<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Finance\Enums\FinanceCustomerType;

class FinanceCustomer extends Model
{
    protected $table = 'finance_customers';

    protected $fillable = [
        'organization_id', 'user_id', 'customer_type', 'name', 'email', 'phone',
        'billing_address', 'is_system_managed', 'is_active', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'customer_type' => FinanceCustomerType::class,
            'billing_address' => 'array',
            'is_system_managed' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(\App\Models\User::class); }
}
