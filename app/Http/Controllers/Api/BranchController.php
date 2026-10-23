<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BranchController extends Controller
{
    /**
     * Display a listing of branches.
     */
    public function index(): JsonResponse
    {
        $branches = Cache::remember('master.branches', 300, fn () => Branch::withCount('employees')->orderBy('id', 'asc')->get());

        return response()->json([
            'success' => true,
            'data' => $branches,
        ]);
    }

    /**
     * Store a newly created branch.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:branches,code'],
            'name' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:10', 'max:5000'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['timezone'] = $validated['timezone'] ?? 'Asia/Jakarta';
        $validated['is_active'] = $validated['is_active'] ?? true;

        $branch = Branch::create($validated);
        Cache::forget('master.branches');
        Cache::forget('master.job_position_options');

        return response()->json([
            'success' => true,
            'message' => 'Cabang kantor berhasil ditambahkan.',
            'data' => $branch,
        ], 201);
    }

    /**
     * Display the specified branch.
     */
    public function show(int $id): JsonResponse
    {
        $branch = Branch::withCount('employees')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $branch,
        ]);
    }

    /**
     * Update the specified branch.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);

        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:20', 'unique:branches,code,'.$id],
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'address' => ['sometimes', 'required', 'string', 'max:255'],
            'latitude' => ['sometimes', 'required', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['sometimes', 'required', 'integer', 'min:10', 'max:5000'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $branch->update($validated);
        Cache::forget('master.branches');
        Cache::forget('master.job_position_options');

        return response()->json([
            'success' => true,
            'message' => 'Lokasi dan koordinat cabang kantor berhasil diperbarui.',
            'data' => $branch->fresh(),
        ]);
    }

    /**
     * Remove the specified branch.
     */
    public function destroy(int $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);

        if ($branch->employees()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cabang kantor tidak dapat dihapus karena masih ditugaskan pada karyawan aktif.',
            ], 422);
        }

        $branch->delete();
        Cache::forget('master.branches');
        Cache::forget('master.job_position_options');

        return response()->json([
            'success' => true,
            'message' => 'Cabang kantor berhasil dihapus.',
        ]);
    }
}
