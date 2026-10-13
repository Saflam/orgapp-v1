<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\PaymentReceipt;
use Modules\Core\Services\PaymentReceiptService;
use Tests\TestCase;

class PaymentReceiptServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_payment_receipt_is_idempotent_per_source_payment(): void
    {
        $organization = Organization::factory()->create();
        $service = app(PaymentReceiptService::class);

        $first = $service->issue(
            organization: $organization,
            ownerModule: 'Member',
            sourceType: 'membership_payment',
            sourceId: 100,
            receiptNumber: 'MP-100',
            data: ['amount' => '25.00'],
        );

        $second = $service->issue(
            organization: $organization,
            ownerModule: 'Finance',
            sourceType: 'membership_payment',
            sourceId: 100,
            receiptNumber: 'FIN-100',
            data: ['amount' => '25.00'],
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame('Member', $second->owner_module);
        $this->assertSame('MP-100', $second->receipt_number);
        $this->assertSame(1, PaymentReceipt::query()->count());
    }
}
