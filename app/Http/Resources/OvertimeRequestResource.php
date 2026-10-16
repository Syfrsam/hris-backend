<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OvertimeRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->employee?->user?->name,
            'department' => $this->employee?->department?->name,
            'date' => $this->date?->toDateString(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'duration_hours' => (float) $this->duration_hours,
            'task_description' => $this->task_description,
            'rate_multiplier' => (float) $this->rate_multiplier,
            'status' => $this->status,
            'current_step' => $this->current_step,
            'approvals' => $this->approvals->map(function ($approval) {
                return [
                    'stage' => $approval->stage,
                    'status' => $approval->status,
                    'reviewer_name' => $approval->reviewer?->name,
                    'notes' => $approval->notes,
                    'action_at' => $approval->action_at?->toIso8601String(),
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
