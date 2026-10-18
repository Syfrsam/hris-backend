<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
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
            'leave_type' => [
                'id' => $this->leaveType?->id,
                'name' => $this->leaveType?->name,
                'code' => $this->leaveType?->code,
            ],
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'total_days' => (int) $this->total_days,
            'reason' => $this->reason,
            'attachment_url' => $this->attachment_path ? (str_starts_with($this->attachment_path, 'http') ? $this->attachment_path : url('storage/'.$this->attachment_path)) : null,
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
