<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\Core\Models\Organization;
use Modules\Member\Models\Member;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable([
    'name',
    'email',
    'phone',
    'password',
    'is_system_admin',
])]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable implements HasMedia
{
    use HasApiTokens, HasFactory, Notifiable, InteractsWithMedia;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_system_admin' => 'boolean',
        ];
    }


    /**
     * Determine whether this user is an active member of an organization.
     *
     * An active organization access membership alone is not sufficient: the user
     * must also have a Member-module record with an active membership.
     *
     * If no organization is supplied, the current organization is used when set.
     */
    public function isMember(Organization|int|null $organization = null): bool
    {
        if ($organization === null) {
            $currentOrganization = app(\App\Support\Organization\CurrentOrganization::class);

            if (!$currentOrganization->check()) {
                return false;
            }

            $organization = $currentOrganization->get();
        }

        $organizationId = $organization instanceof Organization
            ? $organization->id
            : $organization;

        $hasActiveOrganizationAccess = $this->organizationMemberships()
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->exists();

        if (!$hasActiveOrganizationAccess) {
            return false;
        }

        return Member::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $this->id)
            ->whereHas('memberships', function ($query): void {
                $query->where('status', 'active');
            })
            ->exists();
    }


    public function registerMediaCollections(): void
    {
        // Profile photos are intentionally public for the member directory.
        // Keep the global media disk private so identification documents and
        // other sensitive media are not made public by configuration.
        $this->addMediaCollection('profile-photo')
            ->singleFile()
            ->useDisk('public');
    }

    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function details(): HasOne
    {
        return $this->hasOne(UserDetail::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(UserPhoneNumber::class)->orderBy('id');
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(UserEmergencyContact::class)->orderBy('id');
    }
}