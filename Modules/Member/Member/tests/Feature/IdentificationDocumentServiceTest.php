<?php

namespace Modules\Member\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\IdentificationType;
use Modules\Member\Models\UserIdentification;
use Modules\Member\Services\IdentificationDocumentService;
use Tests\TestCase;

class IdentificationDocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function identification(): UserIdentification
    {
        $user = User::factory()->create();

        $identificationType = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);

        return UserIdentification::create([
            'user_id' => $user->id,
            'identification_type_id' => $identificationType->id,
            'identification_number' => 'CIV-123456',
            'metadata' => [],
        ]);
    }

    public function test_document_can_be_saved(): void
    {
        $identification = $this->identification();

        $file = UploadedFile::fake()->create(
            'civil-id.pdf',
            500,
            'application/pdf',
        );

        $media = app(IdentificationDocumentService::class)->save(
            identification: $identification,
            file: $file,
            side: 'document',
        );

        $this->assertNotNull($media->id);

        $this->assertSame(
            'document',
            $media->getCustomProperty('document_side'),
        );

        $this->assertSame(
            'identification-documents',
            $media->collection_name,
        );

        $this->assertCount(
            1,
            $identification->getMedia('identification-documents'),
        );

        Storage::disk('local')->assertExists(
            $media->getPathRelativeToRoot(),
        );
    }

    public function test_front_document_can_be_saved(): void
    {
        $identification = $this->identification();

        $file = UploadedFile::fake()->create(
            'civil-id-front.jpg',
            500,
            'image/jpeg',
        );

        $media = app(IdentificationDocumentService::class)->save(
            identification: $identification,
            file: $file,
            side: 'front',
        );

        $this->assertSame(
            'front',
            $media->getCustomProperty('document_side'),
        );

        $this->assertSame(
            'civil-id-front.jpg',
            $media->file_name,
        );
    }

    public function test_back_document_can_be_saved(): void
    {
        $identification = $this->identification();

        $file = UploadedFile::fake()->create(
            'civil-id-back.jpg',
            500,
            'image/jpeg',
        );

        $media = app(IdentificationDocumentService::class)->save(
            identification: $identification,
            file: $file,
            side: 'back',
        );

        $this->assertSame(
            'back',
            $media->getCustomProperty('document_side'),
        );
    }

    public function test_existing_document_for_same_side_is_replaced(): void
    {
        $identification = $this->identification();

        $service = app(IdentificationDocumentService::class);

        $firstFile = UploadedFile::fake()->create(
            'old-civil-id.pdf',
            500,
            'application/pdf',
        );

        $firstMedia = $service->save(
            identification: $identification,
            file: $firstFile,
            side: 'document',
        );

        $secondFile = UploadedFile::fake()->create(
            'new-civil-id.pdf',
            500,
            'application/pdf',
        );

        $secondMedia = $service->save(
            identification: $identification,
            file: $secondFile,
            side: 'document',
        );

        $this->assertNotSame(
            $firstMedia->id,
            $secondMedia->id,
        );

        $this->assertDatabaseMissing(
            'media',
            [
                'id' => $firstMedia->id,
            ],
        );

        $this->assertDatabaseHas(
            'media',
            [
                'id' => $secondMedia->id,
            ],
        );

        $this->assertCount(
            1,
            $identification->fresh()
                ->getMedia('identification-documents'),
        );
    }

    public function test_front_and_back_documents_can_exist_together(): void
    {
        $identification = $this->identification();

        $service = app(IdentificationDocumentService::class);

        $front = $service->save(
            identification: $identification,
            file: UploadedFile::fake()->create(
                'front.jpg',
                500,
                'image/jpeg',
            ),
            side: 'front',
        );

        $back = $service->save(
            identification: $identification,
            file: UploadedFile::fake()->create(
                'back.jpg',
                500,
                'image/jpeg',
            ),
            side: 'back',
        );

        $media = $identification->fresh()
            ->getMedia('identification-documents');

        $this->assertCount(2, $media);

        $this->assertSame(
            'front',
            $front->getCustomProperty('document_side'),
        );

        $this->assertSame(
            'back',
            $back->getCustomProperty('document_side'),
        );
    }

    public function test_document_can_be_found_by_side(): void
    {
        $identification = $this->identification();

        $service = app(IdentificationDocumentService::class);

        $saved = $service->save(
            identification: $identification,
            file: UploadedFile::fake()->create(
                'front.jpg',
                500,
                'image/jpeg',
            ),
            side: 'front',
        );

        $found = $service->find(
            identification: $identification->fresh(),
            side: 'front',
        );

        $this->assertNotNull($found);
        $this->assertSame($saved->id, $found->id);
    }

    public function test_document_can_be_removed_by_side(): void
    {
        $identification = $this->identification();

        $service = app(IdentificationDocumentService::class);

        $media = $service->save(
            identification: $identification,
            file: UploadedFile::fake()->create(
                'front.jpg',
                500,
                'image/jpeg',
            ),
            side: 'front',
        );

        $service->remove(
            identification: $identification,
            side: 'front',
        );

        $this->assertDatabaseMissing(
            'media',
            [
                'id' => $media->id,
            ],
        );

        $this->assertNull(
            $service->find(
                identification: $identification->fresh(),
                side: 'front',
            ),
        );
    }

    public function test_invalid_document_side_is_rejected(): void
    {
        $identification = $this->identification();

        $this->expectException(\InvalidArgumentException::class);

        app(IdentificationDocumentService::class)->save(
            identification: $identification,
            file: UploadedFile::fake()->create(
                'invalid.pdf',
                500,
                'application/pdf',
            ),
            side: 'invalid',
        );
    }

    public function test_save_many_can_store_multiple_sides(): void
    {
        $identification = $this->identification();

        $media = app(IdentificationDocumentService::class)->saveMany(
            identification: $identification,
            documents: [
                'front' => UploadedFile::fake()->create(
                    'front.jpg',
                    500,
                    'image/jpeg',
                ),
                'back' => UploadedFile::fake()->create(
                    'back.jpg',
                    500,
                    'image/jpeg',
                ),
            ],
        );

        $this->assertCount(2, $media);

        $this->assertArrayHasKey('front', $media);
        $this->assertArrayHasKey('back', $media);

        $this->assertSame(
            'front',
            $media['front']->getCustomProperty('document_side'),
        );

        $this->assertSame(
            'back',
            $media['back']->getCustomProperty('document_side'),
        );
    }
}