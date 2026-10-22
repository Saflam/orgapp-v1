<div class="committee-page">
    <header class="committee-page-header committee-page-header-row">
        <div><h1>Manage committee members</h1><p class="committee-page-description">{{ $term->committee->name }} · {{ $term->start_date?->format('d M Y') }} – {{ $term->end_date?->format('d M Y') }}</p><p class="committee-page-description">Scope: {{ $term->committee->unit?->name ?? 'Central organization' }}</p></div>
        <div class="committee-page-actions"><a href="{{ route('committee.terms', $term->committee_id) }}" class="committee-button committee-button-secondary">← Back to terms</a></div>
    </header>
    @if (session()->has('committee_message'))<div class="committee-alert committee-alert-success">{{ session('committee_message') }}</div>@endif
    @if (session()->has('committee_error'))<div class="committee-alert committee-alert-error">{{ session('committee_error') }}</div>@endif
    @if($term->status?->value === 'active' && $canManageMemberships)
        <section class="committee-form-card">
            <h2>Add member</h2>
            @if($designationOptions->isEmpty())<p class="committee-muted">No active designations match this committee's scope. Create an appropriate designation first.</p>@else
            <form wire:submit="addCommitteeMember" class="committee-member-assignment-form">
                <div class="committee-form-field committee-assignment-designation"><label for="committee-member-designation">Designation</label><select id="committee-member-designation" wire:model="designationId" class="committee-select" required><option value="">Select a designation</option>@foreach($designationOptions as $designation)<option value="{{ $designation->id }}">{{ $designation->name }}</option>@endforeach</select>@error('designationId')<small class="committee-field-error">{{ $message }}</small>@enderror</div>
                <div class="committee-form-field committee-assignment-member"><label for="committee-member-search">Find member by name, email, or membership number</label><div class="committee-member-search-wrap"><input id="committee-member-search" type="search" wire:model.live.debounce.300ms="memberSearch" class="committee-search-input" placeholder="Start typing a name, email, or membership number…" autocomplete="off">@if($memberId !== '')<button type="button" wire:click="clearSelectedMember" class="committee-clear-member" aria-label="Clear selected member">×</button>@endif
                    @if($memberId === '' && trim($memberSearch) !== '')<div class="committee-member-search-results" role="listbox" aria-label="Matching members">@forelse($memberOptions as $memberOption)<button type="button" role="option" wire:key="committee-member-option-{{ $memberOption->id }}" wire:click="selectMember({{ $memberOption->id }})" class="committee-member-search-result">
                        @php($optionName = trim((string) $memberOption->user?->name) ?: 'Unnamed member')
                        <img src="{{ route('committee.member-photo', $memberOption->id) }}" alt="" class="committee-member-avatar" loading="lazy" onerror="this.hidden=true;this.nextElementSibling.hidden=false"><span class="committee-member-avatar committee-member-avatar-placeholder" hidden>{{ mb_substr($optionName, 0, 1) }}</span><span class="committee-member-result-copy"><strong>{{ $optionName }}</strong><small>{{ $memberOption->membership_number ?: ('Member #' . $memberOption->id) }}</small></span>
                    </button>@empty<div class="committee-member-search-empty">No matching members found.</div>@endforelse</div>@endif</div><small class="committee-muted">Search results appear as you type. Select one member to assign.</small>@error('memberId')<small class="committee-field-error">{{ $message }}</small>@enderror</div>
                <div class="committee-assignment-submit"><button type="submit" class="committee-button committee-button-primary" @disabled($memberId === '' || $designationId === '')>Add</button></div>
            </form>@endif
        </section>
    @elseif($term->status?->value !== 'active')
        <div class="committee-alert">This term is not active. Member assignments are read-only until the term is active.</div>
    @endif
    @if($concludingMembershipId)<section class="committee-form-card"><h2>Conclude committee assignment</h2><p class="committee-muted">This assignment will move out of the current members list and remain available in membership history. Please record the reason.</p><form wire:submit="concludeCommitteeMember" class="committee-member-assignment-form"><div class="committee-form-field"><label for="conclusion-date">Effective date</label><input id="conclusion-date" type="date" wire:model="conclusionDate" class="committee-input" required>@error('conclusionDate')<small class="committee-field-error">{{ $message }}</small>@enderror</div><div class="committee-form-field" style="grid-column:span 2"><label for="conclusion-reason">Reason for concluding assignment</label><textarea id="conclusion-reason" wire:model="conclusionReason" rows="3" maxlength="5000" class="committee-textarea" placeholder="Enter why this committee assignment is being concluded…" required></textarea>@error('conclusionReason')<small class="committee-field-error">{{ $message }}</small>@enderror</div><div class="committee-assignment-submit"><button type="submit" class="committee-button committee-button-primary">Confirm conclusion</button> <button type="button" wire:click="cancelConclusion" class="committee-button committee-button-secondary">Cancel</button></div></form></section>@endif
    <section class="committee-table-card"><div class="committee-table-card-header"><h2>Current members</h2><a class="committee-button committee-button-secondary" href="{{ route('committee.membership-history', $term->id) }}">Membership history ({{ $formerMembershipCount }})</a></div><div class="committee-table-scroll"><table class="committee-table committee-members-table"><thead><tr><th>Photo</th><th>Order</th><th>Designation</th><th>Name</th><th>Membership number</th><th>Assigned unit</th><th>Actions</th></tr></thead><tbody>
        @forelse($term->memberships->sortBy('id') as $membership)
            @php($status = $membership->status?->value ?? (string) $membership->status)
            @php($memberRecord = $membership->member)
            @php($memberName = trim((string) $memberRecord?->user?->name) ?: 'Unnamed member')
            <tr wire:key="committee-membership-{{ $membership->id }}"><td><img src="{{ route('committee.member-photo', $memberRecord?->id ?? 0) }}" alt="Profile photo of {{ $memberName }}" class="committee-member-avatar committee-member-table-avatar" loading="lazy" onerror="this.hidden=true;this.nextElementSibling.hidden=false"><span class="committee-member-avatar committee-member-avatar-placeholder committee-member-table-avatar" hidden>{{ mb_substr($memberName, 0, 1) }}</span></td><td>{{ $membership->designation?->sort_order ?? 100 }}</td><td>{{ $membership->designation?->name ?? '—' }} @if($membership->designation?->is_key_position)<span class="committee-status is-key-position">Key position</span>@endif</td><td class="committee-primary-cell"><a href="{{ route('committee.member-profile', $memberRecord?->id ?? 0) }}">{{ $memberName }}</a></td><td>{{ $memberRecord?->membership_number ?: ('Member #' . $membership->member_id) }}</td><td>{{ $memberRecord?->unit?->name ?? '—' }}</td><td>@if($canManageMemberships && $status === 'active')<button type="button" wire:click="beginConclusion({{ $membership->id }})" class="committee-button committee-button-secondary">Conclude assignment</button>@else<span class="committee-muted">—</span>@endif</td></tr>
        @empty<tr><td colspan="7" class="committee-empty-state">No current members are assigned to this term.</td></tr>@endforelse
    </tbody></table></div></section>
</div>
<style>
.committee-modal-backdrop{position:fixed;inset:0;z-index:1000;display:flex;align-items:flex-start;justify-content:center;overflow-y:auto;padding:5vh 20px 30px;background:rgba(12,20,36,.58);backdrop-filter:blur(3px)}
.committee-modal-panel{width:min(1120px,100%);max-height:90vh;overflow-y:auto;margin:0!important;box-shadow:0 24px 80px rgba(0,0,0,.24)}
.committee-members-modal-backdrop{z-index:1010}
.committee-members-modal-panel{align-self:flex-start}
.committee-member-avatar[hidden]{display:none!important}
.committee-member-assignment-form{display:grid;grid-template-columns:minmax(220px,.8fr) minmax(280px,1.6fr) auto;align-items:start;gap:14px;margin:18px 0 24px;padding:18px;border:1px solid var(--line,#e3e8f0);border-radius:12px;background:var(--canvas,#f6f8fc)}
.committee-member-assignment-form .committee-form-field{min-width:0;margin:0}
.committee-assignment-submit{padding-bottom:1px}
.committee-assignment-submit .committee-button{min-height:42px;min-width:84px}
.committee-member-search-wrap{position:relative}
.committee-member-search-wrap input{padding-right:38px}
.committee-clear-member{position:absolute;right:8px;top:7px;width:28px;height:28px;border:0;border-radius:6px;background:transparent;color:var(--muted,#68758b);font-size:22px;cursor:pointer}
.committee-member-search-results{position:absolute;z-index:40;top:calc(100% + 5px);left:0;right:0;max-height:320px;overflow:auto;border:1px solid var(--line,#e3e8f0);border-radius:10px;background:var(--panel,#fff);box-shadow:0 14px 35px rgba(20,35,60,.16)}
.committee-member-search-result{display:flex;width:100%;align-items:center;gap:11px;padding:10px 12px;border:0;border-bottom:1px solid var(--line,#e3e8f0);background:transparent;color:var(--ink,#172033);text-align:left;cursor:pointer}
.committee-member-search-result:last-child{border-bottom:0}
.committee-member-search-result:hover,.committee-member-search-result:focus-visible{background:var(--canvas,#f6f8fc);outline:none}
.committee-member-avatar{display:inline-flex;flex:0 0 38px;width:38px;height:38px;align-items:center;justify-content:center;object-fit:cover;border-radius:50%;border:1px solid var(--line,#e3e8f0);background:var(--brand-soft,#edf2ff);color:var(--brand,#4776e6);font-size:14px;font-weight:750}
.committee-member-avatar-placeholder{display:inline-flex}
.committee-member-result-copy{display:flex;min-width:0;flex-direction:column;gap:1px}
.committee-member-result-copy strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px}
.committee-member-result-copy small{color:var(--muted,#68758b);font-size:11px}
.committee-member-search-empty{padding:14px;color:var(--muted,#68758b);font-size:13px}
.committee-member-table-avatar{width:36px;height:36px;flex-basis:36px}
.committee-members-table{min-width:820px}.committee-table-card-header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 18px}.committee-table-card-header h2{margin:0}
.committee-members-table td:first-child{width:62px}
@media(max-width:820px){.committee-member-assignment-form{grid-template-columns:1fr}.committee-assignment-submit .committee-button{width:100%}.committee-modal-backdrop{padding:12px}.committee-modal-panel{max-height:calc(100vh - 24px)}}

.committee-page{max-width:1400px;margin:0 auto}.committee-page-actions{display:flex;gap:10px;align-items:center;justify-content:flex-end;flex-wrap:wrap}.committee-member-assignment-form{align-items:start}.committee-assignment-submit{align-self:start;padding-top:25px}.committee-member-search-results{z-index:20}.committee-button{text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
</style>
