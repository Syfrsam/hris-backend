<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class OvertimeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_number',
        'employee_id',
        'date',
        'start_time',
        'end_time',
        'duration_hours',
        'task_description',
        'rate_multiplier',
        'status',
        'current_step',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'duration_hours' => 'decimal:2',
            'rate_multiplier' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable');
    }
}
