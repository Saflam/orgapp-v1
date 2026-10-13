<?php

namespace Modules\Events\Http\Controllers\Api;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Events\Services\EventCategoryService;

class EventCategoryController extends Controller
{
    public function __construct(private readonly EventCategoryService $categories)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'include_inactive' => ['sometimes', 'boolean'],
        ]);
        $query = $this->categories->queryForOrganization($this->organization())
            ->orderBy('name');

        if (! ($data['include_inactive'] ?? false)) {
            $query->where('is_active', true);
        }

        return response()->json(['data' => $query->paginate(50)]);
    }

    public function store(Request $request): JsonResponse
    {
        $organization = $this->organization();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:140'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category = $this->categories->create(
            $organization,
            (int) $request->user()->getAuthIdentifier(),
            [
                'name' => $data['name'],
                'slug' => $data['slug'] ?? $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ],
        );

        return response()->json(['data' => $category], 201);
    }

    public function show(int $category): JsonResponse
    {
        $record = $this->categories->queryForOrganization($this->organization())->findOrFail($category);

        return response()->json(['data' => $record]);
    }

    public function update(Request $request, int $category): JsonResponse
    {
        $organization = $this->organization();
        $record = $this->categories->queryForOrganization($organization)->findOrFail($category);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:140'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('slug', $data) && $data['slug'] === null) {
            unset($data['slug']);
        }

        return response()->json(['data' => $this->categories->update($record, $organization, $data)]);
    }

    public function destroy(int $category): JsonResponse
    {
        $organization = $this->organization();
        $record = $this->categories->queryForOrganization($organization)->findOrFail($category);
        $this->categories->delete($record, $organization);

        return response()->json(['message' => 'Event category deleted.']);
    }

    private function organization(): Organization
    {
        $organization = app(CurrentOrganization::class)->get();
        if (! $organization instanceof Organization) {
            throw ValidationException::withMessages(['organization' => 'The selected organization is invalid.']);
        }

        return $organization;
    }
}
