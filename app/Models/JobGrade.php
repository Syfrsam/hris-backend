<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobGrade extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'level',
        'min_salary',
        'max_salary',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'min_salary' => 'decimal:2',
            'max_salary' => 'decimal:2',
        ];
    }

    public function jobPositions(): HasMany
    {
        return $this->hasMany(JobPosition::class);
    }
}
