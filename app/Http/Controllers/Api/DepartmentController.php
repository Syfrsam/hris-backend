<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    /**
     * Display a listing of departments.
     */
    public function index(): JsonResponse
    {
        $departments = Department::withCount(['jobPositions', 'employees'])->get();

        return response()->json([
            'success' => true,
            'data' => $departments,
        ]);
    }

    /**
     * Store a newly created department.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:departments,code'],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $department = Department::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Departemen berhasil ditambahkan.',
            'data' => $department,
        ], 201);
    }

    /**
     * Display the specified department.
     */
    public function show(int $id): JsonResponse
    {
        $department = Department::with(['jobPositions.jobGrade'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $department,
        ]);
    }

    /**
     * Update the specified department.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $department = Department::findOrFail($id);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:departments,code,'.$id],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $department->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Departemen berhasil diperbarui.',
            'data' => $department,
        ]);
    }

    /**
     * Remove the specified department.
     */
    public function destroy(int $id): JsonResponse
    {
        $department = Department::findOrFail($id);

        if ($department->employees()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Departemen tidak dapat dihapus karena masih memiliki data karyawan.',
            ], 422);
        }

        $department->delete();

        return response()->json([
            'success' => true,
            'message' => 'Departemen berhasil dihapus.',
        ]);
    }
}
