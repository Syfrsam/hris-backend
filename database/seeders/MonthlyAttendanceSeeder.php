<?php

namespace Database\Seeders;

use App\Models\Approval;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\PayrollPeriod;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class MonthlyAttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $shift = Shift::first() ?? Shift::create([
            'name' => 'Regular Office Hour',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'late_tolerance_minutes' => 15,
            'is_active' => true,
        ]);

        $branch = Branch::first() ?? Branch::create([
            'code' => 'JKT-HQ',
            'name' => 'Jakarta Head Office',
            'address' => 'Jl. Jenderal Sudirman Kav. 52-53, Jakarta Selatan',
            'latitude' => -6.225014,
            'longitude' => 106.809657,
            'radius_meters' => 100,
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $employees = Employee::with('user')->get();

        if ($employees->isEmpty()) {
            $this->command?->info('Tidak ada karyawan untuk dibuatkan presensi.');
            return;
        }

        // 1. Pastikan Periode Payroll Oktober 2026 sudah ada
        $payrollPeriod = PayrollPeriod::firstOrCreate(
            ['name' => 'Payroll Periode Oktober 2026'],
            [
                'start_date' => '2026-10-01',
                'cutoff_date' => '2026-10-25',
                'payment_date' => '2026-10-28',
                'status' => 'draft',
            ]
        );

        $startDate = Carbon::create(2026, 10, 1);
        $endDate = Carbon::create(2026, 10, 31);

        $samplePhotos = [
            'https://res.cloudinary.com/dr5slbx0q/image/upload/v1793040252/avatars/jrmle3vm60v3h4ta8oqa.jpg',
            'https://res.cloudinary.com/dr5slbx0q/image/upload/v1793040255/avatars/pjmzclryvfefasbuhcxv.jpg',
            'https://res.cloudinary.com/dr5slbx0q/image/upload/v1793040257/avatars/a0cejo96gqy8gmpi5hpg.jpg',
            'https://res.cloudinary.com/dr5slbx0q/image/upload/v1793040258/avatars/h4qoziovmycksh7zhtao.jpg',
            'https://res.cloudinary.com/dr5slbx0q/image/upload/v1793453293/avatars/irmz8qgemknqumpivosf.webp',
        ];

        $totalAttendanceCreated = 0;

        foreach ($employees as $employeeIndex => $employee) {
            $currentDate = $startDate->copy();

            while ($currentDate->lessThanOrEqualTo($endDate)) {
                // Hanya hari kerja (Senin - Jumat)
                if ($currentDate->isWeekday()) {
                    $dateString = $currentDate->toDateString();
                    $dayOfMonth = $currentDate->day;

                    // Buat variasi keterlambatan realistis untuk beberapa hari tertentu (contoh: tanggal 6 dan 19)
                    $isLateDay = in_array($dayOfMonth, [6, 19]) && ($employeeIndex % 2 === 0);

                    if ($isLateDay) {
                        // Terlambat: Masuk jam 08:25 (lewat 25 menit dari start time 08:00)
                        $clockInHour = 8;
                        $clockInMinute = 25;
                        $clockInSecond = rand(10, 50);
                        $lateMinutes = 25;
                        $status = 'late';
                        $notes = 'Terlambat kendala kemacetan lalu lintas';
                    } else {
                        // Tepat Waktu: Masuk antara 07:40 - 08:05
                        $clockInHour = 7;
                        $clockInMinute = rand(45, 58);
                        if (rand(0, 10) > 8) {
                            $clockInHour = 8;
                            $clockInMinute = rand(0, 8);
                        }
                        $clockInSecond = rand(10, 50);
                        $lateMinutes = 0;
                        $status = 'on_time';
                        $notes = 'Hadir tepat waktu di kantor';
                    }

                    $clockInTime = $currentDate->copy()->setTime($clockInHour, $clockInMinute, $clockInSecond);

                    // Pulang kerja antara jam 17:05 - 17:35
                    $clockOutHour = 17;
                    $clockOutMinute = rand(5, 30);
                    $clockOutSecond = rand(10, 50);
                    $clockOutTime = $currentDate->copy()->setTime($clockOutHour, $clockOutMinute, $clockOutSecond);

                    $workDurationMinutes = (int) $clockInTime->diffInMinutes($clockOutTime);

                    // Koordinat kantor dengan sedikit jitter (jarak 10-35 meter)
                    $latJitter = (rand(-20, 20) / 100000);
                    $lonJitter = (rand(-20, 20) / 100000);
                    $distance = round(rand(12, 45) + (rand(1, 9) / 10), 2);

                    $photoUrl = $samplePhotos[$employeeIndex % count($samplePhotos)];

                    Attendance::updateOrCreate(
                        [
                            'employee_id' => $employee->id,
                            'date' => $dateString,
                        ],
                        [
                            'shift_id' => $shift->id,
                            'clock_in_time' => $clockInTime,
                            'clock_in_latitude' => $branch->latitude + $latJitter,
                            'clock_in_longitude' => $branch->longitude + $lonJitter,
                            'clock_in_distance_meters' => $distance,
                            'clock_in_photo_path' => $photoUrl,
                            'clock_out_time' => $clockOutTime,
                            'clock_out_latitude' => $branch->latitude + $latJitter,
                            'clock_out_longitude' => $branch->longitude + $lonJitter,
                            'clock_out_distance_meters' => $distance,
                            'clock_out_photo_path' => $photoUrl,
                            'status' => $status,
                            'late_minutes' => $lateMinutes,
                            'early_leave_minutes' => 0,
                            'work_duration_minutes' => $workDurationMinutes,
                            'notes' => $notes,
                        ]
                    );

                    $totalAttendanceCreated++;
                }

                $currentDate->addDay();
            }

            // 2. Tambahkan data lembur (Approved Overtime) untuk karyawan agar perhitungan upah lembur payroll terlihat
            if ($employeeIndex % 2 === 0) {
                $overtimeDate1 = '2026-10-08';
                $ot1 = OvertimeRequest::updateOrCreate(
                    [
                        'request_number' => 'OT-202610-'.sprintf('%03d', $employee->id).'-1',
                    ],
                    [
                        'employee_id' => $employee->id,
                        'date' => $overtimeDate1,
                        'start_time' => '17:30:00',
                        'end_time' => '19:30:00',
                        'duration_hours' => 2.0,
                        'task_description' => 'Penyusunan audit presensi & rekapitulasi data operasional',
                        'rate_multiplier' => 1.5,
                        'status' => 'approved',
                        'current_step' => 1,
                    ]
                );

                $overtimeDate2 = '2026-10-16';
                $ot2 = OvertimeRequest::updateOrCreate(
                    [
                        'request_number' => 'OT-202610-'.sprintf('%03d', $employee->id).'-2',
                    ],
                    [
                        'employee_id' => $employee->id,
                        'date' => $overtimeDate2,
                        'start_time' => '17:30:00',
                        'end_time' => '20:30:00',
                        'duration_hours' => 3.0,
                        'task_description' => 'Penyelesaian closing pembukuan dan persiapan evaluasi bulanan',
                        'rate_multiplier' => 2.0,
                        'status' => 'approved',
                        'current_step' => 1,
                    ]
                );
            }
        }

        $this->command?->info("Sukses: Telah dibuat {$totalAttendanceCreated} catatan presensi selama 1 bulan penuh (Oktober 2026) untuk semua karyawan.");
    }
}
