<?php

namespace Modules\Member\Services;

use Illuminate\Http\UploadedFile;
use Modules\Member\Models\MembershipApplication;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MembershipApplicationDocumentService
{
    private const COLLECTION = 'application-documents';

    private const ALLOWED_SIDES = [
        'document',
        'front',
        'back',
    ];

    /**
     * @param array<string, UploadedFile> $documents
     * @return array<string, Media>
     */
    public function saveMany(
        MembershipApplication $application,
        int $identificationTypeId,
        array $documents,
    ): array {
        $media = [];

        foreach ($documents as $side => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $this->validateSide($side);
            $this->removeExisting(
                application: $application,
                identificationTypeId: $identificationTypeId,
                side: $side,
            );

            $media[$side] = $application
                ->addMedia($file)
                ->withCustomProperties([
                    'identification_type_id' => $identificationTypeId,
                    'document_side' => $side,
                ])
                ->toMediaCollection(self::COLLECTION);
        }

        return $media;
    }

    /**
     * @return array<int, Media>
     */
    public function forIdentification(
        MembershipApplication $application,
        int $identificationTypeId,
    ): array {
        return $application
            ->getMedia(self::COLLECTION)
            ->filter(
                fn (Media $media): bool =>
                    (int) $media->getCustomProperty('identification_type_id')
                    === $identificationTypeId
            )
            ->values()
            ->all();
    }

    public function remove(
        MembershipApplication $application,
        int $identificationTypeId,
        string $side,
    ): void {
        $this->validateSide($side);
        $this->removeExisting(
            application: $application,
            identificationTypeId: $identificationTypeId,
            side: $side,
        );
    }

    private function removeExisting(
        MembershipApplication $application,
        int $identificationTypeId,
        string $side,
    ): void {
        foreach ($this->forIdentification($application, $identificationTypeId) as $media) {
            if ($media->getCustomProperty('document_side') === $side) {
                $media->delete();
            }
        }
    }

    private function validateSide(string $side): void
    {
        if (! in_array($side, self::ALLOWED_SIDES, true)) {
            throw new \InvalidArgumentException(
                "Invalid application document side [{$side}]. "
                . 'Allowed values are document, front, and back.'
            );
        }
    }
}
