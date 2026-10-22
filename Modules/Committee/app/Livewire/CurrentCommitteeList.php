<?php

namespace Modules\Committee\Livewire;

use App\Support\Organization\CurrentOrganization;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Committee\Enums\CommitteeTermStatus;
use Modules\Committee\Models\Committee;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Member\Models\Member;

class CurrentCommitteeList extends Component
{
    public int $organizationId;

    public function mount(CurrentOrganization $currentOrganization, OrganizationModuleService $modules): void
    {
        $organization = $currentOrganization->get();
        $this->organizationId = (int) $organization->id;
        abort_unless($modules->isEnabled($organization, 'Committee'), 404);
        abort_unless(auth()->user()?->isMember($organization), 403);
    }

    private function organization(): Organization
    {
        $organization = Organization::query()->findOrFail($this->organizationId);
        abort_unless(app(OrganizationModuleService::class)->isEnabled($organization, 'Committee'), 404);
        abort_unless(auth()->user()?->isMember($organization), 403);
        return $organization;
    }

    public function render(): View
    {
        $organization = $this->organization();
        $today = today();
        $member = Member::query()->where('organization_id', $organization->id)->where('user_id', auth()->id())->with('unit')->firstOrFail();
        $unitId = $member->unit_id;

        $committees = Committee::query()
            ->where('organization_id', $organization->id)
            ->where('status', 'active')
            ->where(function ($query) use ($unitId): void {
                $query->whereNull('unit_id');
                if ($unitId !== null) { $query->orWhere('unit_id', $unitId); }
            })
            ->whereHas('terms', function ($terms) use ($today): void {
                $terms->where('status', CommitteeTermStatus::ACTIVE->value)
                    ->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today);
            })
            ->with([
                'unit',
                'terms' => function ($terms) use ($today): void {
                    $terms->where('status', CommitteeTermStatus::ACTIVE->value)
                        ->whereDate('start_date', '<=', $today)
                        ->whereDate('end_date', '>=', $today)
                        ->orderByDesc('start_date')
                        ->with(['memberships' => function ($memberships) use ($today): void {
                            $memberships->where('status', 'active')
                                ->where(function ($query) use ($today): void { $query->whereNull('start_date')->orWhereDate('start_date', '<=', $today); })
                                ->where(function ($query) use ($today): void { $query->whereNull('end_date')->orWhereDate('end_date', '>=', $today); })
                                ->with(['member.user', 'member.unit', 'designation']);
                        }]);
                },
            ])
            ->orderByRaw('CASE WHEN unit_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->get();

        foreach ($committees as $committee) {
            foreach ($committee->terms as $term) {
                $term->setRelation('memberships', $term->memberships
                    ->sortBy(fn ($assignment) => sprintf('%05d-%s', $assignment->designation?->sort_order ?? 100, mb_strtolower((string) $assignment->member?->user?->name)))
                    ->values());
            }
        }

        return view('committee::livewire.current-committees', compact('committees', 'member'))
            ->layout('core::layouts.organization-admin', ['pageTitle' => 'Current Committees']);
    }
}
