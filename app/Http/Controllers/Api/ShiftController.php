<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    /**
     * Display a listing of active work shifts.
     */
    public function index(): JsonResponse
    {
        $shifts = Shift::with('branch:id,name,code')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar jadwal shift presensi berhasil dimuat.',
            'data' => $shifts,
        ]);
    }

    /**
     * Store a newly created shift (Superadmin & HR).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'late_tolerance_minutes' => ['required', 'integer', 'min:0', 'max:180'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $shift = Shift::create([
            'name' => $validated['name'],
            'start_time' => $validated['start_time'].':00',
            'end_time' => $validated['end_time'].':00',
            'late_tolerance_minutes' => $validated['late_tolerance_minutes'],
            'branch_id' => $validated['branch_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Jadwal shift baru berhasil ditambahkan.',
            'data' => $shift->load('branch:id,name,code'),
        ], 201);
    }

    /**
     * Display the specified shift.
     */
    public function show(Shift $shift): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $shift->load('branch:id,name,code'),
        ]);
    }

    /**
     * Update the specified shift (Superadmin & HR).
     */
    public function update(Request $request, Shift $shift): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:50'],
            'start_time' => ['sometimes', 'required'],
            'end_time' => ['sometimes', 'required'],
            'late_tolerance_minutes' => ['sometimes', 'required', 'integer', 'min:0', 'max:180'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['start_time'])) {
            $validated['start_time'] = strlen($validated['start_time']) === 5 ? $validated['start_time'].':00' : $validated['start_time'];
        }
        if (isset($validated['end_time'])) {
            $validated['end_time'] = strlen($validated['end_time']) === 5 ? $validated['end_time'].':00' : $validated['end_time'];
        }

        $shift->update($validated);

        return response()->json([
            'success' => true,
            'message' => "Pengaturan jadwal shift '{$shift->name}' berhasil diperbarui.",
            'data' => $shift->load('branch:id,name,code'),
        ]);
    }

    /**
     * Remove the specified shift (Superadmin only).
     */
    public function destroy(Shift $shift): JsonResponse
    {
        if (Shift::count() <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'Sistem wajib memiliki minimal 1 jadwal shift aktif.',
            ], 422);
        }

        $shift->delete();

        return response()->json([
            'success' => true,
            'message' => "Jadwal shift '{$shift->name}' berhasil dihapus.",
        ]);
    }
}
