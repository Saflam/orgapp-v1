<div class="access-control-page">
    <div class="page-head"><div><p class="eyebrow">Access Control</p><h1>User role assignments</h1><p class="muted">Assign organization-level roles to active users. Assignments are limited to this organization.</p></div></div>
    @if ($successMessage)<div class="card" style="color:var(--ok);padding:12px 16px">{{ $successMessage }}</div>@endif
    <section class="card">
        <div style="position:relative;max-width:640px">
            <label for="organization-user-search" style="display:block;margin-bottom:7px">Find user</label>
            <input id="organization-user-search" type="search" wire:model.live.debounce.250ms="search" placeholder="Type at least 2 characters of a name or email" autocomplete="off" aria-label="Find organization user" style="width:100%;box-sizing:border-box">
            @if (!$membershipId && mb_strlen(trim($search)) >= 2)
                <div class="user-suggestions" style="position:absolute;z-index:20;top:100%;left:0;right:0;background:var(--surface, #fff);border:1px solid var(--line);border-radius:10px;box-shadow:0 12px 28px rgba(0,0,0,.12);max-height:280px;overflow-y:auto">
                    @forelse ($memberships as $membership)
                        <button type="button" wire:click="selectMembership({{ $membership->id }})" style="display:block;width:100%;text-align:left;padding:11px 13px;border:0;border-bottom:1px solid var(--line);background:transparent;color:inherit;cursor:pointer">
                            <strong>{{ $membership->user?->name ?? 'Unknown user' }}</strong>
                            <span class="muted" style="display:block;font-size:.9em">{{ $membership->user?->email }}</span>
                        </button>
                    @empty
                        <p class="muted" style="padding:12px;margin:0">No active organization users found.</p>
                    @endforelse
                </div>
            @endif
        </div>
        @error('membershipId')<small class="error-text">{{ $message }}</small>@enderror
        @if ($membershipId)
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:18px;padding:12px 14px;border:1px solid var(--line);border-radius:10px">
                <div><small class="muted">Selected organization user</small><div><strong>{{ $selectedMembershipLabel }}</strong></div></div>
                <button class="btn secondary" type="button" wire:click="changeMembership">Change user</button>
            </div>
            <form wire:submit="save" style="margin-top:22px"><h2>Assign roles</h2><p class="muted">Select all roles this user should hold in the organization. Saving replaces the user's current organization-level assignments.</p>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin:16px 0">
                    @forelse ($roles as $role)
                        <label class="permission-option" style="display:flex;gap:10px;align-items:flex-start;padding:14px;border:1px solid var(--line);border-radius:10px"><input type="checkbox" wire:model="selectedRoles" value="{{ $role->id }}"><span><strong>{{ $role->name }}</strong><small class="muted" style="display:block">{{ $role->description ?: $role->code }}</small></span></label>
                    @empty
                        <p class="muted">No assignable custom roles are available.</p>
                    @endforelse
                </div>
                @error('selectedRoles.*')<small class="error-text">{{ $message }}</small>@enderror
                <button class="btn primary" type="submit">Save role assignments</button>
            </form>
        @else
            <p class="muted" style="margin-top:18px">Search for a user and select a suggestion to manage their organization roles.</p>
        @endif
    </section>
</div>
