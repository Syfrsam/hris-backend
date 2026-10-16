<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'shift_id',
        'date',
        'clock_in_time',
        'clock_in_latitude',
        'clock_in_longitude',
        'clock_in_distance_meters',
        'clock_in_photo_path',
        'clock_out_time',
        'clock_out_latitude',
        'clock_out_longitude',
        'clock_out_distance_meters',
        'clock_out_photo_path',
        'status',
        'late_minutes',
        'early_leave_minutes',
        'work_duration_minutes',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'clock_in_time' => 'datetime',
            'clock_out_time' => 'datetime',
            'clock_in_latitude' => 'decimal:8',
            'clock_in_longitude' => 'decimal:8',
            'clock_in_distance_meters' => 'decimal:2',
            'clock_out_latitude' => 'decimal:8',
            'clock_out_longitude' => 'decimal:8',
            'clock_out_distance_meters' => 'decimal:2',
            'late_minutes' => 'integer',
            'early_leave_minutes' => 'integer',
            'work_duration_minutes' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
