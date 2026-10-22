<?php

namespace Modules\Committee\Livewire;

use App\Support\Organization\CurrentOrganization;
use Illuminate\View\View;
use Livewire\Component;
use Modules\Committee\Enums\CommitteeKind;
use Modules\Committee\Enums\CommitteeTermStatus;
use Modules\Committee\Models\Committee;
use Modules\Committee\Models\CommitteeTerm;
use Modules\Committee\Services\CommitteeService;
use Modules\Committee\Services\CommitteeTermService;
use Modules\Core\Data\AuthorizationContext;
use Modules\Core\Models\Organization;
use Modules\Core\Services\AuthorizationService;
use Modules\Core\Services\OrganizationModuleService;

class CommitteeTermManagement extends Component
{
    public int $organizationId;
    public int $committeeId;
    public string $termStartDate = '';
    public string $termEndDate = '';
    public ?int $editingTermId = null;

    public function mount(
        int $committee,
        CurrentOrganization $currentOrganization,
        AuthorizationService $authorization,
        OrganizationModuleService $modules,
    ): void {
        $organization = $currentOrganization->get();
        $this->organizationId = (int) $organization->id;
        $this->committeeId = $committee;
        abort_unless($modules->isEnabled($organization, 'Committee'), 404);
        $this->authorizeManage($authorization);
        $this->committee();
    }

    public function saveTerm(CommitteeService $service): void
    {
        $this->authorizeManage();
        $committee = $this->committee();
        $validated = $this->validate([
            'termStartDate' => ['required', 'date'],
            'termEndDate' => ['required', 'date', 'after:termStartDate'],
        ]);

        try {
            if ($this->editingTermId !== null) {
                $term = $committee->terms()->whereKey($this->editingTermId)->firstOrFail();
                $service->updateTerm($term, $validated['termStartDate'], $validated['termEndDate']);
                $message = 'Committee term updated successfully.';
            } else {
                $service->createTerm($committee, $validated['termStartDate'], $validated['termEndDate']);
                $message = 'Committee term created as a draft. Activate it when it is ready.';
            }
        } catch (\Throwable $exception) {
            report($exception);
            $this->addError('termEndDate', $exception instanceof \DomainException || $exception instanceof \InvalidArgumentException
                ? $exception->getMessage()
                : 'The committee term could not be saved. Check the dates and try again.');
            return;
        }

        $this->resetTermForm();
        session()->flash('committee_message', $message);
    }

    public function editTerm(int $termId): void
    {
        $this->authorizeManage();
        $term = $this->committee()->terms()->whereKey($termId)->firstOrFail();
        abort_if($term->status === CommitteeTermStatus::COMPLETED || $term->status === CommitteeTermStatus::CANCELLED, 422, 'Completed or cancelled terms cannot be edited.');
        $this->editingTermId = (int) $term->id;
        $this->termStartDate = $term->start_date->format('Y-m-d');
        $this->termEndDate = $term->end_date->format('Y-m-d');
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->resetTermForm();
        $this->resetValidation();
    }

    public function activateTerm(int $termId, CommitteeTermService $service): void
    {
        $this->authorizeManage();
        $term = $this->committee()->terms()->whereKey($termId)->firstOrFail();
        try {
            $service->activate($term);
            session()->flash('committee_message', 'Committee term activated successfully.');
        } catch (\Throwable $exception) {
            report($exception);
            session()->flash('committee_error', $exception instanceof \DomainException ? $exception->getMessage() : 'The committee term could not be activated.');
        }
    }

    public function completeTerm(int $termId, CommitteeTermService $service): void
    {
        $this->authorizeManage();
        $term = $this->committee()->terms()->whereKey($termId)->firstOrFail();
        try {
            $service->complete($term);
            session()->flash('committee_message', 'Committee term completed successfully.');
        } catch (\Throwable $exception) {
            report($exception);
            session()->flash('committee_error', $exception instanceof \DomainException ? $exception->getMessage() : 'The committee term could not be completed.');
        }
    }

    private function resetTermForm(): void
    {
        $this->termStartDate = '';
        $this->termEndDate = '';
        $this->editingTermId = null;
    }

    private function committee(): Committee
    {
        $organization = $this->organization();
        $committee = Committee::query()->where('organization_id', $organization->id)->with(['terms' => fn ($query) => $query->orderByDesc('start_date')->with(['memberships' => fn ($memberships) => $memberships->where('status', 'active')->where(function ($query): void { $query->whereNull('start_date')->orWhereDate('start_date', '<=', today()); })->where(function ($query): void { $query->whereNull('end_date')->orWhereDate('end_date', '>=', today()); })->with('member.user')])])->whereKey($this->committeeId)->firstOrFail();
        abort_unless($committee->kind === CommitteeKind::RECURRING, 404);
        return $committee;
    }

    private function organization(): Organization
    {
        $organization = Organization::query()->findOrFail($this->organizationId);
        abort_unless(app(OrganizationModuleService::class)->isEnabled($organization, 'Committee'), 404);
        return $organization;
    }

    private function authorizeManage(?AuthorizationService $authorization = null): void
    {
        $user = auth()->user();
        $context = new AuthorizationContext($this->organizationId);
        abort_unless($user && ($authorization ?? app(AuthorizationService::class))->can($user, 'committee.manage', $context), 403);
    }

    public function render(): View
    {
        $organization = $this->organization();
        $this->authorizeManage();
        return view('committee::livewire.committee-terms', ['committee' => $this->committee()])
            ->layout('core::layouts.organization-admin', ['pageTitle' => 'Manage Committee Terms']);
    }
}
