<?php

namespace Modules\Member\Contracts;

use Illuminate\Http\Request;
use Modules\Core\Models\Organization;
use Modules\Member\Models\Member;

interface MemberProfileSectionExtension
{
    /** The optional organization module that owns this section. */
    public function module(): string;

    /** Human-readable section heading shown on the member profile. */
    public function title(): string;

    /** View supplied by the owning module to render its section content. */
    public function view(): string;

    /** View supplied by the owning module to edit its section content. */
    public function editView(): string;

    /** Return view data, or null when this module elects not to show a section. */
    public function data(Organization $organization, Member $member): ?array;

    /** Validate and persist this module's member-specific profile fields. */
    public function update(Request $request, Organization $organization, Member $member): void;
}
