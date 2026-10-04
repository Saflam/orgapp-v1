<?php

namespace Modules\Member\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Models\IdentificationType;
use Modules\Member\Models\UserIdentification;

class UserIdentificationService
{
    /**
     * Create or update a user's identification for a type.
     *
     * @param array<string, mixed> $data
     */
    public function save(
        User $user,
        IdentificationType $identificationType,
        array $data,
    ): UserIdentification {
        return UserIdentification::updateOrCreate(
            [
                'user_id' => $user->id,
                'identification_type_id' => $identificationType->id,
            ],
            [
                'identification_number' => $data['identification_number'],
                'metadata' => $data['metadata'] ?? null,
            ],
        );
    }

    /**
     * Create a user's identification.
     *
     * @param array<string, mixed> $data
     */
    public function create(
        User $user,
        IdentificationType $identificationType,
        array $data,
    ): UserIdentification {
        return UserIdentification::create([
            'user_id' => $user->id,
            'identification_type_id' => $identificationType->id,
            'identification_number' => $data['identification_number'],
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    /**
     * Update an existing user identification.
     *
     * @param array<string, mixed> $data
     */
    public function update(
        UserIdentification $identification,
        array $data,
    ): UserIdentification {
        $identification->update([
            'identification_number' => $data['identification_number'],
            'metadata' => $data['metadata'] ?? $identification->metadata,
        ]);

        return $identification->refresh();
    }

    public function find(
        User $user,
        IdentificationType $identificationType,
    ): ?UserIdentification {
        return UserIdentification::query()
            ->where('user_id', $user->id)
            ->where(
                'identification_type_id',
                $identificationType->id
            )
            ->first();
    }

    /**
     * @return Collection<int, UserIdentification>
     */
    public function all(User $user): Collection
    {
        return UserIdentification::query()
            ->where('user_id', $user->id)
            ->with('identificationType')
            ->get();
    }

    public function delete(
        UserIdentification $identification,
    ): void {
        $identification->delete();
    }
}