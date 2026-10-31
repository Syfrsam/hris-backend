<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenaltySetting extends Model
{
    use HasFactory;

    protected $table = 'penalty_settings';

    protected $fillable = [
        'late_penalty_type',
        'hourly_rate',
        'incident_rate',
        'grace_period_minutes',
        'absent_penalty_type',
        'absent_daily_amount',
        'is_active',
        'notes',
        'updated_by',
    ];

    protected $casts = [
        'hourly_rate' => 'float',
        'incident_rate' => 'float',
        'absent_daily_amount' => 'float',
        'grace_period_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the active company penalty settings (singleton pattern).
     */
    public static function getActive(): self
    {
        return self::firstOrCreate([], [
            'late_penalty_type' => 'hourly_rate',
            'hourly_rate' => 25000.00,
            'incident_rate' => 50000.00,
            'grace_period_minutes' => 15,
            'absent_penalty_type' => 'salary_proportional',
            'absent_daily_amount' => 150000.00,
            'is_active' => true,
            'notes' => 'Aturan denda standar perusahaan: Denda keterlambatan flat Rp 25.000 / jam dihitung pro-rata menit setelah toleransi shift.',
        ]);
    }
}
