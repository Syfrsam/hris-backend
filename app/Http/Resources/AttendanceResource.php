<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
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
            'employee_id' => $this->employee_id,
            'employee_name' => $this->employee?->user?->name,
            'nik' => $this->employee?->nik,
            'date' => $this->date?->toDateString(),
            'shift' => [
                'name' => $this->shift?->name,
                'start_time' => $this->shift?->start_time,
                'end_time' => $this->shift?->end_time,
            ],
            'clock_in' => [
                'time' => $this->clock_in_time?->format('H:i:s'),
                'datetime' => $this->clock_in_time?->toIso8601String(),
                'distance_meters' => (float) $this->clock_in_distance_meters,
                'photo_url' => $this->clock_in_photo_path ? (str_starts_with($this->clock_in_photo_path, 'http') ? $this->clock_in_photo_path : url('storage/'.$this->clock_in_photo_path)) : null,
            ],
            'clock_out' => [
                'time' => $this->clock_out_time?->format('H:i:s'),
                'datetime' => $this->clock_out_time?->toIso8601String(),
                'distance_meters' => (float) $this->clock_out_distance_meters,
                'photo_url' => $this->clock_out_photo_path ? (str_starts_with($this->clock_out_photo_path, 'http') ? $this->clock_out_photo_path : url('storage/'.$this->clock_out_photo_path)) : null,
            ],
            'status' => $this->status,
            'late_minutes' => (int) $this->late_minutes,
            'early_leave_minutes' => (int) $this->early_leave_minutes,
            'work_duration_minutes' => (int) $this->work_duration_minutes,
            'notes' => $this->notes,
        ];
    }
}
