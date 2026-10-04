<?php

namespace Modules\Committee\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Committee\Enums\CommitteeKind;
use Modules\Committee\Enums\CommitteeStatus;
use Modules\Committee\Enums\CommitteeTermStatus;
use Modules\Committee\Models\Committee;
use Modules\Committee\Models\CommitteeTerm;
use Modules\Committee\Models\CommitteeType;
use Modules\Committee\Services\CommitteeTermService;
use Modules\Core\Models\Organization;
use Tests\TestCase;

class CommitteeTermServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createTerm(
        CommitteeTermStatus $status = CommitteeTermStatus::DRAFT,
    ): CommitteeTerm {
        $organization = Organization::factory()->create();

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'EXECUTIVE',
            'is_active' => true,
        ]);

        $committee = Committee::create([
            'organization_id' => $organization->id,
            'committee_type_id' => $committeeType->id,
            'unit_id' => null,
            'parent_committee_id' => null,
            'name' => 'Central Committee 2026',
            'kind' => CommitteeKind::RECURRING,
            'status' => CommitteeStatus::ACTIVE,
        ]);

        return CommitteeTerm::create([
            'committee_id' => $committee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => $status,
        ]);
    }

    public function test_draft_term_can_be_activated(): void
    {
        $term = $this->createTerm();

        $service = app(CommitteeTermService::class);

        $term = $service->activate($term);

        $this->assertSame(
            CommitteeTermStatus::ACTIVE,
            $term->status
        );
    }

    public function test_active_term_can_be_completed(): void
    {
        $term = $this->createTerm(
            CommitteeTermStatus::ACTIVE
        );

        $service = app(CommitteeTermService::class);

        $term = $service->complete($term);

        $this->assertSame(
            CommitteeTermStatus::COMPLETED,
            $term->status
        );
    }

    public function test_draft_term_can_be_cancelled(): void
    {
        $term = $this->createTerm();

        $service = app(CommitteeTermService::class);

        $term = $service->cancel($term);

        $this->assertSame(
            CommitteeTermStatus::CANCELLED,
            $term->status
        );
    }

    public function test_active_term_can_be_cancelled(): void
    {
        $term = $this->createTerm(
            CommitteeTermStatus::ACTIVE
        );

        $service = app(CommitteeTermService::class);

        $term = $service->cancel($term);

        $this->assertSame(
            CommitteeTermStatus::CANCELLED,
            $term->status
        );
    }

    public function test_active_term_cannot_be_activated_again(): void
    {
        $term = $this->createTerm(
            CommitteeTermStatus::ACTIVE
        );

        $service = app(CommitteeTermService::class);

        $this->expectException(\DomainException::class);

        $service->activate($term);
    }

    public function test_completed_term_cannot_be_activated(): void
    {
        $term = $this->createTerm(
            CommitteeTermStatus::COMPLETED
        );

        $service = app(CommitteeTermService::class);

        $this->expectException(\DomainException::class);

        $service->activate($term);
    }

    public function test_cancelled_term_cannot_be_activated(): void
    {
        $term = $this->createTerm(
            CommitteeTermStatus::CANCELLED
        );

        $service = app(CommitteeTermService::class);

        $this->expectException(\DomainException::class);

        $service->activate($term);
    }

    public function test_completed_term_cannot_be_completed_again(): void
    {
        $term = $this->createTerm(
            CommitteeTermStatus::COMPLETED
        );

        $service = app(CommitteeTermService::class);

        $this->expectException(\DomainException::class);

        $service->complete($term);
    }

    public function test_cancelled_term_cannot_be_completed(): void
    {
        $term = $this->createTerm(
            CommitteeTermStatus::CANCELLED
        );

        $service = app(CommitteeTermService::class);

        $this->expectException(\DomainException::class);

        $service->complete($term);
    }

    public function test_completed_term_cannot_be_cancelled(): void
    {
        $term = $this->createTerm(
            CommitteeTermStatus::COMPLETED
        );

        $service = app(CommitteeTermService::class);

        $this->expectException(\DomainException::class);

        $service->cancel($term);
    }

    public function test_cancelled_term_cannot_be_cancelled_again(): void
    {
        $term = $this->createTerm(
            CommitteeTermStatus::CANCELLED
        );

        $service = app(CommitteeTermService::class);

        $this->expectException(\DomainException::class);

        $service->cancel($term);
    }

    public function test_overlapping_terms_for_same_committee_cannot_be_activated(): void
    {
        $activeTerm = $this->createTerm(
            CommitteeTermStatus::ACTIVE
        );

        $overlappingTerm = CommitteeTerm::create([
            'committee_id' => $activeTerm->committee_id,
            'start_date' => '2026-03-15',
            'end_date' => '2026-06-30',
            'status' => CommitteeTermStatus::DRAFT,
        ]);

        $service = app(CommitteeTermService::class);

        $this->expectException(\DomainException::class);

        $service->activate($overlappingTerm);
    }

    public function test_term_starting_on_previous_term_end_date_cannot_be_activated(): void
    {
        $activeTerm = $this->createTerm(
            CommitteeTermStatus::ACTIVE
        );

        $overlappingTerm = CommitteeTerm::create([
            'committee_id' => $activeTerm->committee_id,
            'start_date' => '2026-12-31',
            'end_date' => '2027-12-31',
            'status' => CommitteeTermStatus::DRAFT,
        ]);

        $service = app(CommitteeTermService::class);

        $this->expectException(\DomainException::class);

        $service->activate($overlappingTerm);
    }

    public function test_adjacent_terms_are_allowed(): void
    {
        $activeTerm = $this->createTerm(
            CommitteeTermStatus::ACTIVE
        );

        $activeTerm->update([
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
        ]);

        $nextTerm = CommitteeTerm::create([
            'committee_id' => $activeTerm->committee_id,
            'start_date' => '2026-04-01',
            'end_date' => '2026-12-31',
            'status' => CommitteeTermStatus::DRAFT,
        ]);

        $service = app(CommitteeTermService::class);

        $nextTerm = $service->activate($nextTerm);

        $this->assertSame(
            CommitteeTermStatus::ACTIVE,
            $nextTerm->status
        );
    }

    public function test_terms_for_different_committees_can_overlap(): void
    {
        $organization = Organization::factory()->create();

        $committeeType = CommitteeType::create([
            'organization_id' => $organization->id,
            'name' => 'Executive Committee',
            'code' => 'EXECUTIVE',
            'is_active' => true,
        ]);

        $firstCommittee = Committee::create([
            'organization_id' => $organization->id,
            'committee_type_id' => $committeeType->id,
            'unit_id' => null,
            'parent_committee_id' => null,
            'name' => 'Central Committee 2026',
            'kind' => CommitteeKind::RECURRING,
            'status' => CommitteeStatus::ACTIVE,
        ]);

        $secondCommittee = Committee::create([
            'organization_id' => $organization->id,
            'committee_type_id' => $committeeType->id,
            'unit_id' => null,
            'parent_committee_id' => null,
            'name' => 'Girls Wing Committee 2026',
            'kind' => CommitteeKind::RECURRING,
            'status' => CommitteeStatus::ACTIVE,
        ]);

        CommitteeTerm::create([
            'committee_id' => $firstCommittee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => CommitteeTermStatus::ACTIVE,
        ]);

        $secondTerm = CommitteeTerm::create([
            'committee_id' => $secondCommittee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => CommitteeTermStatus::DRAFT,
        ]);

        $service = app(CommitteeTermService::class);

        $secondTerm = $service->activate($secondTerm);

        $this->assertSame(
            CommitteeTermStatus::ACTIVE,
            $secondTerm->status
        );
    }

    public function test_term_with_end_date_before_start_date_cannot_be_activated(): void
    {
        $term = $this->createTerm();

        $term->update([
            'start_date' => '2026-12-31',
            'end_date' => '2026-01-01',
        ]);

        $service = app(CommitteeTermService::class);

        $this->expectException(\DomainException::class);

        $service->activate($term->refresh());
    }
}