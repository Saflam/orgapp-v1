<style>
/* This page uses the application's own CSS so it remains styled without a Tailwind build. */
.member-directory { display:grid; gap:24px; color:var(--ink,#172033); }
.member-directory .md-header { display:flex; flex-wrap:wrap; align-items:flex-end; justify-content:space-between; gap:12px; }
.member-directory h1 { margin:0; font-size:26px; line-height:1.25; font-weight:750; letter-spacing:-.025em; }
.member-directory p { margin:5px 0 0; color:var(--muted,#68758b); }
.member-directory .md-count { color:var(--muted,#68758b); font-size:13px; }
.member-directory .md-panel { padding:20px; border:1px solid var(--line,#e3e8f0); border-radius:16px; background:var(--panel,#fff); box-shadow:0 2px 8px rgb(23 32 51 / 4%); }
.member-directory .md-filters { display:grid; grid-template-columns:minmax(240px,2fr) repeat(2,minmax(150px,1fr)); gap:16px; }
.member-directory label { display:block; margin-bottom:6px; color:#475569; font-size:13px; font-weight:650; }
.member-directory input,.member-directory select { width:100%; min-height:42px; padding:9px 12px; border:1px solid #cbd5e1; border-radius:9px; background:var(--panel,#fff); color:var(--ink,#172033); font-size:13px; }
.member-directory input:focus,.member-directory select:focus { outline:2px solid #dbeafe; border-color:#64748b; }
.member-directory .md-sort { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; margin-top:18px; padding-top:16px; border-top:1px solid #eef2f7; }
.member-directory .md-sort-label { color:#475569; font-size:13px; font-weight:650; }
.member-directory .md-controls { display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
.member-directory button { min-height:38px; padding:8px 13px; border:1px solid #cbd5e1; border-radius:9px; background:var(--panel,#fff); color:#334155; font-size:13px; font-weight:650; cursor:pointer; }
.member-directory button:hover { background:#f8fafc; }
.member-directory button.md-selected { border-color:#172033; background:#172033; color:#fff; }
.member-directory .md-table-wrap { overflow-x:auto; }
.member-directory table { width:100%; border-collapse:collapse; text-align:left; }
.member-directory thead { background:#f8fafc; }
.member-directory th { padding:13px 16px; color:#64748b; font-size:11px; font-weight:750; text-transform:uppercase; letter-spacing:.055em; white-space:nowrap; }
.member-directory td { padding:13px 16px; border-top:1px solid #eef2f7; vertical-align:middle; }
.member-directory tbody tr:hover { background:#f8fafc; }
.member-directory .md-name { color:#172033; font-weight:700; }
.member-directory .md-sub { margin-top:3px; color:#64748b; font-size:12px; }
.member-directory .md-contact { color:#475569; font-size:13px; }
.member-directory .md-status { display:inline-flex; padding:4px 10px; border-radius:999px; background:#f1f5f9; color:#475569; font-size:12px; font-weight:700; white-space:nowrap; }
.member-directory .md-empty { padding:44px 16px; text-align:center; }
.member-directory .md-empty strong { color:#334155; }
.member-directory .md-pagination { padding:12px 16px; border-top:1px solid #e2e8f0; }
.member-directory .md-pagination nav { display:flex; align-items:center; justify-content:space-between; gap:12px; }
.member-directory .md-pagination a,.member-directory .md-pagination span { display:inline-block; padding:6px 10px; border:1px solid #e2e8f0; border-radius:7px; font-size:12px; }
@media(max-width:900px) { .member-directory .md-filters { grid-template-columns:1fr 1fr; } .member-directory .md-search { grid-column:1 / -1; } }
@media(max-width:600px) { .member-directory .md-panel { padding:14px; } .member-directory .md-filters { grid-template-columns:1fr; } .member-directory .md-search { grid-column:auto; } .member-directory .md-sort { align-items:stretch; } .member-directory .md-controls { width:100%; } .member-directory .md-controls select { flex:1; min-width:0; } }
</style>

<div class="member-directory">
    <div class="md-header">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Members</h1>
            <p class="mt-1 text-sm text-slate-500">Search and review members you are authorized to access.</p>
        </div>
        <div class="md-count">{{ $members->total() }} members found</div>
    </div>

    <section class="md-panel">
        <div class="md-filters">
            <div class="md-search">
                <label for="member-directory-search">Search members</label>
                <input id="member-directory-search" type="search" wire:model.live.debounce.300ms="search"
                    placeholder="Name, email, phone or membership number"
                    >
            </div>
            <div>
                <label for="member-directory-unit">Unit</label>
                <select id="member-directory-unit" wire:model.live="unitId" >
                    <option value="">All permitted units</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="member-directory-status">Membership status</label>
                <select id="member-directory-status" wire:model.live="status">
                    <option value="">All statuses</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="md-sort">
            <div class="md-sort-label">Sort filtered results</div>
            <div class="md-controls">
                <select wire:model.live="sortBy" aria-label="Sort results by" >
                    <option value="name">Name</option>
                    <option value="membership_number">Membership number</option>
                </select>
                <div >
                    <button type="button" wire:click="setSortDirection('asc')"
                        class="{{ $sortDirection === 'asc' ? 'md-selected' : '' }}">Ascending</button>
                    <button type="button" wire:click="setSortDirection('desc')"
                        class="{{ $sortDirection === 'desc' ? 'md-selected' : '' }}">Descending</button>
                </div>
                <button type="button" wire:click="$set('search', ''); $set('unitId', ''); $set('status', ''); $set('sortBy', 'name'); $set('sortDirection', 'asc')"
                    >Reset</button>
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
                        <th >Membership no.</th>
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
                            <td >
                                <div class="md-name">{{ $member->user?->name ?? 'Unnamed member' }}</div>
                                <div class="md-sub">Member #{{ $member->id }}</div>
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
                                <span class="md-sub">Profile management in next phase</span>
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
