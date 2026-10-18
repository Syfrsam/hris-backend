<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
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
            'user_id' => $this->user_id,
            'nik' => $this->nik,
            'ktp_number' => $this->ktp_number,
            'gender' => $this->gender,
            'birth_date' => $this->birth_date?->toDateString(),
            'birth_place' => $this->birth_place,
            'marital_status' => $this->marital_status,
            'dependents_count' => $this->dependents_count,
            'bank_name' => $this->bank_name,
            'bank_account_number' => $this->bank_account_number,
            'bank_account_holder' => $this->bank_account_holder,
            'employment_status' => $this->employment_status,
            'join_date' => $this->join_date?->toDateString(),
            'resign_date' => $this->resign_date?->toDateString(),
            'user' => [
                'name' => $this->user?->name,
                'email' => $this->user?->email,
                'phone' => $this->user?->phone,
                'avatar_url' => $this->user?->avatar_url ? url('storage/'.$this->user->avatar_url) : null,
                'roles' => $this->user?->getRoleNames(),
            ],
            'department' => $this->whenLoaded('department', function () {
                return [
                    'id' => $this->department?->id,
                    'code' => $this->department?->code,
                    'name' => $this->department?->name,
                ];
            }),
            'branch' => $this->whenLoaded('branch', function () {
                return [
                    'id' => $this->branch?->id,
                    'code' => $this->branch?->code,
                    'name' => $this->branch?->name,
                    'latitude' => $this->branch?->latitude,
                    'longitude' => $this->branch?->longitude,
                    'radius_meters' => $this->branch?->radius_meters,
                ];
            }),
            'job_position' => $this->whenLoaded('jobPosition', function () {
                return [
                    'id' => $this->jobPosition?->id,
                    'title' => $this->jobPosition?->title,
                    'grade' => $this->jobPosition?->jobGrade?->name,
                ];
            }),
            'manager' => $this->whenLoaded('manager', function () {
                return [
                    'id' => $this->manager?->id,
                    'nik' => $this->manager?->nik,
                    'name' => $this->manager?->user?->name,
                ];
            }),
            'active_contract' => $this->whenLoaded('activeContract', function () {
                return [
                    'contract_number' => $this->activeContract?->contract_number,
                    'contract_type' => $this->activeContract?->contract_type,
                    'basic_salary' => (float) $this->activeContract?->basic_salary,
                    'document_url' => $this->activeContract?->document_path ? (str_starts_with($this->activeContract->document_path, 'http') ? $this->activeContract->document_path : url('storage/'.$this->activeContract->document_path)) : null,
                ];
            }),
        ];
    }
}
