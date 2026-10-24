<?php

namespace Modules\Member\Services;

use Illuminate\Http\Request;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Member\Contracts\MemberProfileSectionExtension;
use Modules\Member\Models\Member;

class MemberProfileSectionExtensionRegistry
{
    /** @var array<string, MemberProfileSectionExtension> */
    private array $extensions = [];

    public function __construct(
        private readonly OrganizationModuleService $organizationModuleService,
    ) {
    }

    public function register(MemberProfileSectionExtension $extension): void
    {
        $this->extensions[$extension->module()] = $extension;
    }

    /**
     * Build the module-contributed profile sections for one organization/member.
     * Disabled optional modules do not contribute UI or data.
     *
     * @return array<int, array{module:string,title:string,view:string,edit_view:string,data:array<string,mixed>}>
     */
    public function sections(Organization $organization, Member $member): array
    {
        $sections = [];

        foreach ($this->extensions as $extension) {
            if (! $this->organizationModuleService->isEnabled($organization, $extension->module())) {
                continue;
            }

            $data = $extension->data($organization, $member);
            if ($data === null) {
                continue;
            }

            $sections[] = [
                'module' => $extension->module(),
                'title' => $extension->title(),
                'view' => $extension->view(),
                'edit_view' => $extension->editView(),
                'data' => $data,
            ];
        }

        return $sections;
    }

    /**
     * Dispatch an update to the owning module only when it is registered and enabled.
     */
    public function update(
        string $module,
        Request $request,
        Organization $organization,
        Member $member,
    ): void {
        $extension = $this->extensions[$module] ?? null;
        abort_if($extension === null, 404);

        abort_unless($this->organizationModuleService->isEnabled($organization, $extension->module()), 404);
        abort_unless((int) $member->organization_id === (int) $organization->id, 404);

        $extension->update($request, $organization, $member);
    }
}
