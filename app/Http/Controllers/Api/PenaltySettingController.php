<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PenaltySetting;
use App\Models\PayrollPeriod;
use App\Services\PayrollService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PenaltySettingController extends Controller
{
    protected PayrollService $payrollService;

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;
    }

    /**
     * Get active penalty rules and configuration.
     */
    public function show(): JsonResponse
    {
        $setting = PenaltySetting::getActive();
        $setting->load('updatedByUser:id,name');

        return response()->json([
            'success' => true,
            'data' => $setting,
        ]);
    }

    /**
     * Update penalty rules configuration (Superadmin only).
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'late_penalty_type' => 'required|in:hourly_rate,salary_proportional,fixed_per_incident,none',
            'hourly_rate' => 'required|numeric|min:0',
            'incident_rate' => 'nullable|numeric|min:0',
            'grace_period_minutes' => 'required|integer|min:0|max:120',
            'absent_penalty_type' => 'required|in:salary_proportional,fixed_daily',
            'absent_daily_amount' => 'nullable|numeric|min:0',
            'is_active' => 'required|boolean',
            'notes' => 'nullable|string|max:500',
            'recalculate_active_draft' => 'nullable|boolean',
        ]);

        $setting = PenaltySetting::getActive();

        $setting->update([
            'late_penalty_type' => $validated['late_penalty_type'],
            'hourly_rate' => $validated['hourly_rate'],
            'incident_rate' => $validated['incident_rate'] ?? 50000.00,
            'grace_period_minutes' => $validated['grace_period_minutes'],
            'absent_penalty_type' => $validated['absent_penalty_type'],
            'absent_daily_amount' => $validated['absent_daily_amount'] ?? 150000.00,
            'is_active' => (bool) $validated['is_active'],
            'notes' => $validated['notes'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        $recalculated = false;
        // Optionally recalculate draft payroll periods if requested or active
        if ($request->boolean('recalculate_active_draft')) {
            $draftPeriod = PayrollPeriod::where('status', 'calculated')->latest()->first();
            if ($draftPeriod) {
                try {
                    $this->payrollService->generateDraftPayroll($draftPeriod->id, $request->user()->id);
                    $recalculated = true;
                } catch (Exception) {
                    // ignore if cannot recalculate immediately
                }
            }
        }

        $setting->load('updatedByUser:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Peraturan denda dan pemotongan berhasil diperbarui.' . ($recalculated ? ' Draf payroll aktif telah dihitung ulang sesuai aturan baru.' : ''),
            'data' => $setting,
            'recalculated' => $recalculated,
        ]);
    }
}
