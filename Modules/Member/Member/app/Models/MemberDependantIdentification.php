<?php

namespace Modules\Member\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\IdentificationType;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MemberDependantIdentification extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'dependant_id',
        'identification_type_id',
        'identification_number',
        'expires_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'date', 'metadata' => 'array'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('identification-documents')
            ->useDisk(config('media-library.disk_name'));
    }

    public function dependant(): BelongsTo
    {
        return $this->belongsTo(MemberDependant::class, 'dependant_id');
    }

    public function identificationType(): BelongsTo
    {
        return $this->belongsTo(IdentificationType::class);
    }

    public function document(string $side): ?Media
    {
        return $this->getMedia('identification-documents')->first(
            fn (Media $media): bool => $media->getCustomProperty('document_side') === $side
        );
    }
}
