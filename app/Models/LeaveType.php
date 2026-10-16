<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'default_quota',
        'deduct_quota',
        'min_notice_days',
        'requires_attachment',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_quota' => 'integer',
            'deduct_quota' => 'boolean',
            'min_notice_days' => 'integer',
            'requires_attachment' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
