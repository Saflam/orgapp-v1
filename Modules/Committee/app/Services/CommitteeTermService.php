<?php

namespace Modules\Committee\Services;

use DomainException;
use Modules\Committee\Enums\CommitteeTermStatus;
use Modules\Committee\Models\CommitteeTerm;

class CommitteeTermService
{
    public function activate(
        CommitteeTerm $term,
    ): CommitteeTerm {
        if ($term->status !== CommitteeTermStatus::DRAFT) {
            throw new DomainException(
                'Only a draft committee term can be activated.'
            );
        }

        if ($term->start_date->gt($term->end_date)) {
            throw new DomainException(
                'The committee term start date must be before or equal to the end date.'
            );
        }

        $this->ensureNoOverlappingTerm($term);

        $term->update([
            'status' => CommitteeTermStatus::ACTIVE,
        ]);

        return $term->refresh();
    }

    public function complete(
        CommitteeTerm $term,
    ): CommitteeTerm {
        if ($term->status !== CommitteeTermStatus::ACTIVE) {
            throw new DomainException(
                'Only an active committee term can be completed.'
            );
        }

        $term->update([
            'status' => CommitteeTermStatus::COMPLETED,
        ]);

        return $term->refresh();
    }

    public function cancel(
        CommitteeTerm $term,
    ): CommitteeTerm {
        if (
            ! in_array(
                $term->status,
                [
                    CommitteeTermStatus::DRAFT,
                    CommitteeTermStatus::ACTIVE,
                ],
                true
            )
        ) {
            throw new DomainException(
                'Only a draft or active committee term can be cancelled.'
            );
        }

        $term->update([
            'status' => CommitteeTermStatus::CANCELLED,
        ]);

        return $term->refresh();
    }

    private function ensureNoOverlappingTerm(
        CommitteeTerm $term,
    ): void {
        $overlappingTermExists = CommitteeTerm::query()
            ->where('committee_id', $term->committee_id)
            ->whereKeyNot($term->id)
            ->whereDate('start_date', '<=', $term->end_date)
            ->whereDate('end_date', '>=', $term->start_date)
            ->exists();

        if ($overlappingTermExists) {
            throw new DomainException(
                'The committee term overlaps with an existing term.'
            );
        }
    }
}