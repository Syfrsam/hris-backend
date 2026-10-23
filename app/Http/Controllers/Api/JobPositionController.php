<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\JobGrade;
use App\Models\JobPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class JobPositionController extends Controller
{
    /**
     * Display a listing of job positions.
     */
    public function index(): JsonResponse
    {
        $positions = Cache::remember('master.job_positions', 300, fn () => JobPosition::with(['department', 'jobGrade'])
            ->withCount('employees')
            ->get());

        return response()->json([
            'success' => true,
            'data' => $positions,
        ]);
    }

    /**
     * Store a newly created job position.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'job_grade_id' => ['nullable', 'exists:job_grades,id'],
            'title' => ['required', 'string', 'max:100'],
        ]);

        $position = JobPosition::create($validated);
        Cache::forget('master.job_positions');
        Cache::forget('master.departments');

        return response()->json([
            'success' => true,
            'message' => 'Jabatan berhasil ditambahkan.',
            'data' => $position->load(['department', 'jobGrade']),
        ], 201);
    }

    /**
     * Display the specified job position.
     */
    public function show(int $id): JsonResponse
    {
        $position = JobPosition::with(['department', 'jobGrade', 'employees.user'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $position,
        ]);
    }

    /**
     * Update the specified job position.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $position = JobPosition::findOrFail($id);

        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'job_grade_id' => ['nullable', 'exists:job_grades,id'],
            'title' => ['required', 'string', 'max:100'],
        ]);

        $position->update($validated);
        Cache::forget('master.job_positions');
        Cache::forget('master.departments');

        return response()->json([
            'success' => true,
            'message' => 'Jabatan berhasil diperbarui.',
            'data' => $position->load(['department', 'jobGrade']),
        ]);
    }

    /**
     * Remove the specified job position.
     */
    public function destroy(int $id): JsonResponse
    {
        $position = JobPosition::findOrFail($id);

        if ($position->employees()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Jabatan tidak dapat dihapus karena masih digunakan oleh data karyawan.',
            ], 422);
        }

        $position->delete();
        Cache::forget('master.job_positions');
        Cache::forget('master.departments');

        return response()->json([
            'success' => true,
            'message' => 'Jabatan berhasil dihapus.',
        ]);
    }

    /**
     * Get lookup options (branches, job grades) for form selects.
     */
    public function options(): JsonResponse
    {
        $options = Cache::remember('master.job_position_options', 300, fn () => [
            'branches' => Branch::where('is_active', true)->get(['id', 'code', 'name', 'radius_meters']),
            'grades' => JobGrade::orderBy('level')->get(['id', 'name', 'level', 'min_salary', 'max_salary']),
        ]);

        return response()->json([
            'success' => true,
            'data' => $options,
        ]);
    }
}
