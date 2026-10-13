<?php

namespace Modules\Finance\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Models\Organization;
use Modules\Finance\Enums\FinanceJournalStatus;
use Modules\Finance\Enums\FinanceReceiptStatus;
use Modules\Finance\Models\FinanceJournal;
use Modules\Finance\Models\FinanceReceipt;
use Modules\Finance\Models\FinanceScope;

class FinanceReceiptService
{
    public function issue(
        Organization $organization,
        FinanceScope $scope,
        FinanceJournal $journal,
        array $data = [],
    ): FinanceReceipt {
        $this->assertJournalBelongsToScope($organization, $scope, $journal);

        if ($journal->status !== FinanceJournalStatus::POSTED) {
            throw new \InvalidArgumentException(
                'A receipt can only be issued for a posted journal.'
            );
        }

        return DB::transaction(function () use ($organization, $scope, $journal, $data): FinanceReceipt {
            $existing = FinanceReceipt::query()
                ->where('finance_journal_id', $journal->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $receipt = FinanceReceipt::create([
                'organization_id' => $organization->id,
                'finance_scope_id' => $scope->id,
                'finance_journal_id' => $journal->id,
                'receipt_number' => $data['receipt_number'] ?? $this->generateReceiptNumber(),
                'issued_at' => $data['issued_at'] ?? now(),
                'status' => FinanceReceiptStatus::ISSUED,
                'source_type' => $journal->source_type,
                'source_id' => $journal->source_id,
                'metadata' => $data['metadata'] ?? [],
            ]);

            return $receipt->load('journal');
        });
    }

    public function forJournal(FinanceJournal $journal): ?FinanceReceipt
    {
        return FinanceReceipt::query()
            ->where('finance_journal_id', $journal->id)
            ->first();
    }

    private function assertJournalBelongsToScope(
        Organization $organization,
        FinanceScope $scope,
        FinanceJournal $journal,
    ): void {
        if ($scope->organization_id !== $organization->id) {
            throw new \InvalidArgumentException(
                'The finance scope does not belong to the organization.'
            );
        }

        if ($journal->organization_id !== $organization->id
            || $journal->finance_scope_id !== $scope->id) {
            throw new \InvalidArgumentException(
                'The journal does not belong to the finance scope.'
            );
        }
    }

    private function generateReceiptNumber(): string
    {
        return 'RCT-' . strtoupper((string) Str::ulid());
    }
}
