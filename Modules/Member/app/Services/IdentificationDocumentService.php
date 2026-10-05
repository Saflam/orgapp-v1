<?php

namespace Modules\Member\Services;

use Illuminate\Http\UploadedFile;
use Modules\Member\Models\UserIdentification;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class IdentificationDocumentService
{
    private const COLLECTION = 'identification-documents';

    private const ALLOWED_SIDES = [
        'document',
        'front',
        'back',
    ];

    public function save(
        UserIdentification $identification,
        UploadedFile $file,
        string $side,
    ): Media {
        $this->validateSide($side);

        $this->removeExisting(
            identification: $identification,
            side: $side,
        );

        return $identification
            ->addMedia($file)
            ->withCustomProperties([
                'document_side' => $side,
            ])
            ->toMediaCollection(self::COLLECTION);
    }

    public function saveFromPath(
        UserIdentification $identification,
        string $path,
        string $side,
        ?string $fileName = null,
    ): Media {
        $this->validateSide($side);

        $this->removeExisting(
            identification: $identification,
            side: $side,
        );

        $adder = $identification
            ->addMedia($path)
            ->withCustomProperties([
                'document_side' => $side,
            ]);

        if ($fileName !== null) {
            $adder->usingFileName($fileName);
        }

        return $adder->toMediaCollection(self::COLLECTION);
    }

    /**
     * @param array<string, UploadedFile> $documents
     * @return array<string, Media>
     */
    public function saveMany(
        UserIdentification $identification,
        array $documents,
    ): array {
        $media = [];

        foreach ($documents as $side => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $media[$side] = $this->save(
                identification: $identification,
                file: $file,
                side: $side,
            );
        }

        return $media;
    }

    public function remove(
        UserIdentification $identification,
        string $side,
    ): void {
        $this->validateSide($side);

        $this->removeExisting(
            identification: $identification,
            side: $side,
        );
    }

    public function find(
        UserIdentification $identification,
        string $side,
    ): ?Media {
        $this->validateSide($side);

        return $identification
            ->media()
            ->where('collection_name', self::COLLECTION)
            ->get()
            ->first(
                fn (Media $media): bool =>
                    $media->getCustomProperty('document_side') === $side
            );
    }

    /**
     * @return array<int, Media>
     */
    public function all(
        UserIdentification $identification,
    ): array {
        return $identification
            ->media()
            ->where('collection_name', self::COLLECTION)
            ->get()
            ->all();
    }

    private function removeExisting(
        UserIdentification $identification,
        string $side,
    ): void {
        $existing = $this->find(
            identification: $identification,
            side: $side,
        );

        $existing?->delete();
    }

    private function validateSide(string $side): void
    {
        if (! in_array($side, self::ALLOWED_SIDES, true)) {
            throw new \InvalidArgumentException(
                "Invalid identification document side [{$side}]. "
                . 'Allowed values are document, front, and back.'
            );
        }
    }
}
