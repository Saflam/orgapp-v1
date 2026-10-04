<?php

namespace Modules\Finance\Services;

use DomainException;
use Modules\Finance\Models\FeeObligation;
use Modules\Finance\Models\Payment;

class FeeObligationService
{
    public function outstandingAmount(FeeObligation $obligation): float
    {
        $paidAmount = $obligation->payments()
            ->where('status', 'completed')
            ->sum('amount');

        return max(
            0,
            (float) $obligation->amount - (float) $paidAmount
        );
    }

    public function isSettled(FeeObligation $obligation): bool
    {
        return $this->outstandingAmount($obligation) <= 0;
    }

    public function recordPayment(
        FeeObligation $obligation,
        float $amount,
        string $paymentMethod,
        ?string $reference = null,
    ): Payment {
        if ($amount <= 0) {
            throw new DomainException(
                'Payment amount must be greater than zero.'
            );
        }

        $outstanding = $this->outstandingAmount($obligation);

        if ($amount > $outstanding) {
            throw new DomainException(
                'Payment amount cannot exceed the outstanding amount.'
            );
        }

        return $obligation->payments()->create([
            'amount' => $amount,
            'paid_at' => now(),
            'payment_method' => $paymentMethod,
            'reference' => $reference,
            'status' => 'completed',
        ]);
    }

    public function waive(
        FeeObligation $obligation,
        string $reason,
    ): FeeObligation {
        if ($this->isSettled($obligation)) {
            throw new DomainException(
                'A settled obligation cannot be waived.'
            );
        }

        $obligation->update([
            'status' => 'waived',
            'waived_at' => now()->toDateString(),
            'waiver_reason' => $reason,
        ]);

        return $obligation->refresh();
    }
}