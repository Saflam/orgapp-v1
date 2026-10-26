<style>
/* This page uses the application's own CSS so it remains styled without a Tailwind build. */
.member-directory { display:grid; gap:24px; color:var(--ink,#172033); }
.member-directory .md-header { display:flex; flex-wrap:wrap; align-items:flex-end; justify-content:space-between; gap:12px; }
.member-directory h1 { margin:0; font-size:26px; line-height:1.25; font-weight:750; letter-spacing:-.025em; }
.member-directory p { margin:5px 0 0; color:var(--muted,#68758b); }
.member-directory .md-count { color:var(--muted,#68758b); font-size:13px; }
.member-directory .md-header-actions { display:flex; align-items:center; gap:12px; }
.member-directory .md-export { position:relative; }
.member-directory .md-export summary { list-style:none; display:inline-flex; align-items:center; gap:8px; height:40px; padding:0 13px; border:1px solid #cbd5e1; border-radius:9px; background:#fff; color:#334155; font-size:13px; font-weight:650; cursor:pointer; }
.member-directory .md-export summary::-webkit-details-marker { display:none; }
.member-directory .md-export-menu { position:absolute; z-index:20; right:0; top:calc(100% + 6px); min-width:150px; padding:5px; border:1px solid #e2e8f0; border-radius:10px; background:#fff; box-shadow:0 8px 24px rgb(23 32 51 / 12%); }
.member-directory .md-export-menu button { display:block; width:100%; text-align:left; border:0; height:auto; min-height:36px; }
.member-directory .md-panel { padding:20px; border:1px solid var(--line,#e3e8f0); border-radius:16px; background:var(--panel,#fff); box-shadow:0 2px 8px rgb(23 32 51 / 4%); }
.member-directory .md-filters { display:grid; grid-template-columns:minmax(150px,1.25fr) minmax(105px,.75fr) minmax(105px,.75fr) minmax(125px,.9fr) auto; gap:8px; align-items:end; }
.member-directory label { display:block; margin-bottom:6px; color:#475569; font-size:13px; font-weight:650; }
.member-directory input,.member-directory select { box-sizing:border-box; width:100%; height:42px; min-height:42px; padding:0 12px; border:1px solid #cbd5e1; border-radius:9px; background:var(--panel,#fff); color:var(--ink,#172033); font-size:13px; line-height:1.25; }
.member-directory input:focus,.member-directory select:focus { outline:2px solid #dbeafe; border-color:#64748b; }
.member-directory button { box-sizing:border-box; height:42px; min-height:42px; padding:0 10px; border:1px solid #cbd5e1; border-radius:9px; background:var(--panel,#fff); color:#334155; font-size:13px; font-weight:650; line-height:1.25; cursor:pointer; }
.member-directory .md-icon-button { display:inline-flex; box-sizing:border-box; width:42px; min-width:42px; height:42px; min-height:42px; align-items:center; justify-content:center; padding:0; flex:0 0 42px; }
.member-directory .md-filter-actions { display:flex; gap:6px; align-items:center; }
.member-directory .md-filter-actions button { flex:0 0 auto; }
.member-directory .md-filter-actions svg { width:17px; height:17px; }
.member-directory button:hover { background:#f8fafc; }
.member-directory button.md-selected { border-color:#172033; background:#172033; color:#fff; }
.member-directory .md-table-wrap { overflow-x:auto; }
.member-directory table { width:100%; border-collapse:collapse; text-align:left; }
.member-directory thead { background:#f8fafc; }
.member-directory th { padding:13px 16px; color:#64748b; font-size:11px; font-weight:750; text-transform:uppercase; letter-spacing:.055em; white-space:nowrap; }
.member-directory td { padding:13px 16px; border-top:1px solid #eef2f7; vertical-align:middle; }
.member-directory tbody tr:hover { background:#f8fafc; }
.member-directory .md-member-cell { display:flex; align-items:center; gap:10px; min-width:180px; }
.member-directory .md-avatar { display:block; width:36px; height:36px; flex:0 0 36px; border-radius:50%; object-fit:cover; background:#e2e8f0; }
.member-directory .md-avatar-fallback { display:flex; align-items:center; justify-content:center; color:#475569; font-size:12px; font-weight:750; }
.member-directory .md-name { color:#172033; font-weight:700; }
.member-directory .md-sub { margin-top:3px; color:#64748b; font-size:12px; }
.member-directory .md-contact { color:#475569; font-size:13px; }
.member-directory .md-status { display:inline-flex; padding:4px 10px; border-radius:999px; background:#f1f5f9; color:#475569; font-size:12px; font-weight:700; white-space:nowrap; }
.member-directory .md-empty { padding:44px 16px; text-align:center; }
.member-directory .md-empty strong { color:#334155; }
.member-directory .md-pagination { padding:12px 16px; border-top:1px solid #e2e8f0; }
.member-directory .md-pagination nav { display:flex; align-items:center; justify-content:space-between; gap:12px; }
.member-directory .md-pagination a,.member-directory .md-pagination span { display:inline-block; padding:6px 10px; border:1px solid #e2e8f0; border-radius:7px; font-size:12px; }
@media(max-width:900px) { .member-directory .md-filters { grid-template-columns:minmax(150px,1.2fr) minmax(100px,.8fr) minmax(100px,.8fr); } .member-directory .md-search { grid-column:auto; } .member-directory .md-filter-actions { grid-column:3; justify-content:flex-end; } }
@media(max-width:600px) { .member-directory .md-panel { padding:14px; } .member-directory .md-filters { grid-template-columns:1fr 1fr; } .member-directory .md-search { grid-column:1 / -1; } .member-directory .md-filter-actions { grid-column:1 / -1; justify-content:flex-start; } }
</style>

<div class="member-directory">
    <div class="md-header">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Members</h1>
            <p class="mt-1 text-sm text-slate-500">Search and review members you are authorized to access.</p>
        </div>
        <div class="md-header-actions">
            <div class="md-count">{{ $members->total() }} members found</div>
            <details class="md-export">
                <summary aria-label="Export members">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M5 17v4h14v-4"/></svg>
                    Export
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </summary>
                <div class="md-export-menu">
                    <button type="button" wire:click="exportExcel">Excel (CSV)</button>
                    <button type="button" wire:click="exportPdf">PDF</button>
                </div>
            </details>
        </div>
    </div>

    <section class="md-panel">
        <div class="md-filters">
            <div class="md-search">
                <label for="member-directory-search">Search members</label>
                <input id="member-directory-search" type="search" wire:model.live.debounce.300ms="searchInput" placeholder="Name, email, phone or MID">
            </div>
            <div>
                <label for="member-directory-unit">Unit</label>
                <select id="member-directory-unit" wire:model.live="unitId">
                    <option value="">All units</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="member-directory-status">Status</label>
                <select id="member-directory-status" wire:model.live="status">
                    <option value="">All statuses</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md-filter-actions" aria-label="Search actions">
                <button type="button" class="md-icon-button" wire:click="applyFilters" aria-label="Search members" title="Search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                </button>
                <button type="button" class="md-icon-button" wire:click="resetFilters" aria-label="Reset filters" title="Reset">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"></path><path d="M3 3v5h5"></path></svg>
                </button>
            </div>
        </div>
    </section>

    <section class="md-panel" style="padding:0;overflow:hidden">
        <div class="md-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th >Member</th>
                        <th >Contact</th>
                        <th>MID</th>
                        <th >Unit</th>
                        <th >Status</th>
                        <th style="text-align:right">Action</th>
                    </tr>
                </thead>
                <tbody >
                    @forelse($members as $member)
                        @php
                            $currentMembership = $member->memberships->sortByDesc('starts_at')->first();
                            $currentStatus = $currentMembership?->status ?? 'unknown';
                        @endphp
                        <tr wire:key="member-directory-{{ $member->id }}" >
                            <td>
                                @php
                                    $memberName = $member->user?->name ?? 'Unnamed member';
                                    $profilePhotoUrl = $member->user?->getFirstMediaUrl('profile-photo');
                                    $initials = collect(preg_split('/\\s+/', trim($memberName)))
                                        ->filter()
                                        ->take(2)
                                        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                                        ->implode('');
                                @endphp
                                <div class="md-member-cell">
                                    @if($profilePhotoUrl)
                                        <img class="md-avatar" src="{{ $profilePhotoUrl }}" alt="{{ $memberName }} profile photo" loading="lazy">
                                    @else
                                        <span class="md-avatar md-avatar-fallback" aria-hidden="true">{{ $initials ?: '?' }}</span>
                                    @endif
                                    <div class="md-name"><a href="{{ route('member.profile', $member->id) }}" class="hover:underline">{{ $memberName }}</a></div>
                                </div>
                            </td>
                            <td class="md-contact">
                                @if($member->user?->email)<div>{{ $member->user->email }}</div>@endif
                                @if($member->user?->phone)<div class="md-sub">{{ $member->user->phone }}</div>@endif
                            </td>
                            <td >{{ $member->membership_number ?: '—' }}</td>
                            <td >{{ $member->unit?->name ?? 'Organization-wide' }}</td>
                            <td >
                                <span class="md-status">{{ \Illuminate\Support\Str::headline($currentStatus instanceof \BackedEnum ? $currentStatus->value : (string) $currentStatus) }}</span>
                            </td>
                            <td style="text-align:right;white-space:nowrap">
                                <a href="{{ route('member.profile', $member->id) }}" class="md-icon-button" style="display:inline-flex;text-decoration:none" aria-label="View {{ $memberName }}" title="View member profile">
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="md-empty">
                            <strong>No members found</strong>
                            <p>Try changing your search or filters.</p>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($members->hasPages())
            <div class="md-pagination">{{ $members->links() }}</div>
        @endif
    </section>
</div>
