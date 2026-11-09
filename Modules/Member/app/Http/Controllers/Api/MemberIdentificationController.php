<?php

namespace Modules\Member\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Core\Models\IdentificationType;
use Modules\Core\Models\Organization;
use Modules\Core\Models\OrganizationIdentification;
use Modules\Member\Models\Member;
use Modules\Member\Models\UserIdentification;
use Modules\Member\Services\IdentificationDocumentService;
use Modules\Member\Services\UserIdentificationService;

class MemberIdentificationController extends Controller
{
    public function __construct(
        private readonly UserIdentificationService $identificationService,
        private readonly IdentificationDocumentService $documentService,
    ) {
    }

    public function index(Organization $organization, Member $member): JsonResponse
    {
        $this->ensureMember($organization, $member);

        $items = $this->identificationService->all($member->user)
            ->map(fn (UserIdentification $item) => $this->serialize($item));

        return response()->json(['data' => $items]);
    }

    public function store(Request $request, Organization $organization, Member $member): JsonResponse
    {
        $this->ensureMember($organization, $member);
        $data = $request->validate([
            'identification_type_id' => ['required', 'integer', 'exists:identification_types,id'],
            'identification_number' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
            'documents' => ['nullable', 'array'],
            'documents.document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'documents.front' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'documents.back' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        $type = $this->enabledType($organization, (int) $data['identification_type_id']);
        abort_if($this->identificationService->find($member->user, $type), 422, 'This identification type already exists for the member.');

        $identification = DB::transaction(function () use ($member, $type, $data, $request) {
            $item = $this->identificationService->create($member->user, $type, $data);
            foreach (['document', 'front', 'back'] as $side) {
                if ($request->hasFile("documents.{$side}")) {
                    $this->documentService->save($item, $request->file("documents.{$side}"), $side);
                }
            }
            return $item->load('identificationType');
        });

        return response()->json(['data' => $this->serialize($identification)], 201);
    }

    public function show(Organization $organization, Member $member, UserIdentification $identification): JsonResponse
    {
        $this->ensureIdentification($organization, $member, $identification);
        return response()->json(['data' => $this->serialize($identification->load('identificationType'))]);
    }

    public function update(Request $request, Organization $organization, Member $member, UserIdentification $identification): JsonResponse
    {
        $this->ensureIdentification($organization, $member, $identification);
        $data = $request->validate([
            'identification_number' => ['sometimes', 'required', 'string', 'max:255'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        $identification->fill($data)->save();

        return response()->json(['data' => $this->serialize($identification->refresh()->load('identificationType'))]);
    }

    public function uploadProof(Request $request, Organization $organization, Member $member, UserIdentification $identification, string $side): JsonResponse
    {
        $this->ensureIdentification($organization, $member, $identification);
        abort_unless(in_array($side, ['document', 'front', 'back'], true), 404);
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        $media = $this->documentService->save($identification, $data['file'], $side);

        return response()->json([
            'message' => 'Identification proof uploaded successfully.',
            'data' => ['side' => $side, 'file_name' => $media->file_name, 'mime_type' => $media->mime_type],
        ], 201);
    }

    public function proof(Organization $organization, Member $member, UserIdentification $identification, string $side)
    {
        $this->ensureIdentification($organization, $member, $identification);
        abort_unless(in_array($side, ['document', 'front', 'back'], true), 404);
        $media = $this->documentService->find($identification, $side);
        abort_unless($media && is_file($media->getPath()), 404);

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $media->file_name).'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function deleteProof(Organization $organization, Member $member, UserIdentification $identification, string $side): JsonResponse
    {
        $this->ensureIdentification($organization, $member, $identification);
        abort_unless(in_array($side, ['document', 'front', 'back'], true), 404);
        $this->documentService->remove($identification, $side);

        return response()->json(['message' => 'Identification proof deleted successfully.']);
    }

    public function destroy(Organization $organization, Member $member, UserIdentification $identification): JsonResponse
    {
        $this->ensureIdentification($organization, $member, $identification);
        $this->identificationService->delete($identification);

        return response()->json(['message' => 'Identification record deleted successfully.']);
    }

    private function enabledType(Organization $organization, int $typeId): IdentificationType
    {
        $enabled = OrganizationIdentification::query()
            ->where('organization_id', $organization->id)
            ->where('identification_type_id', $typeId)
            ->where('is_enabled', true)
            ->exists();

        abort_unless($enabled, 422, 'This identification type is not enabled for the organization.');

        return IdentificationType::query()->findOrFail($typeId);
    }

    private function ensureMember(Organization $organization, Member $member): void
    {
        abort_unless((int) $member->organization_id === (int) $organization->id, 404);
    }

    private function ensureIdentification(Organization $organization, Member $member, UserIdentification $identification): void
    {
        $this->ensureMember($organization, $member);
        abort_unless((int) $identification->user_id === (int) $member->user_id, 404);
    }

    private function serialize(UserIdentification $item): array
    {
        $item->loadMissing('identificationType');

        $proofs = [];
        foreach (['document', 'front', 'back'] as $side) {
            $media = $this->documentService->find($item, $side);
            $proofs[$side] = $media ? [
                'available' => true,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
            ] : ['available' => false];
        }

        return [
            'id' => $item->id,
            'identification_type' => $item->identificationType ? [
                'id' => $item->identificationType->id,
                'code' => $item->identificationType->code,
                'name' => $item->identificationType->name,
            ] : null,
            'identification_number' => $item->identification_number,
            'expires_at' => $item->expires_at?->toDateString(),
            'metadata' => $item->metadata,
            'proofs' => $proofs,
            'created_at' => $item->created_at?->toISOString(),
            'updated_at' => $item->updated_at?->toISOString(),
        ];
    }
}
