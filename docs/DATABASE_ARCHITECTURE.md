# HRIS Database Architecture & Entity Relationship Diagram (ERD)

Dokumentasi arsitektur database resmi untuk sistem **HRIS Enterprise Platform**, mencakup manajemen multi-cabang, hierarki karyawan (*multi-level workforce*), presensi otomatis berbasis GPS radius, *two-step approval workflow* (Manager & HR), serta *automated payroll engine*.

---

## 1. Visualisasi Entity Relationship Diagram (ERD Lengkap)

Diagram di bawah ini menyatukan **seluruh tabel, atribut, tipe data, kunci (PK/FK/UK), dan relasi antar modul** dalam satu diagram arsitektur utuh:

```mermaid
erDiagram
    %% --- Relasi Organisasi & Profil Karyawan ---
    USERS ||--|| EMPLOYEES : "has profile"
    BRANCHES ||--o{ EMPLOYEES : "assigned branch"
    BRANCHES ||--o{ SHIFTS : "defines shifts"
    DEPARTMENTS ||--o{ JOB_POSITIONS : "has positions"
    JOB_GRADES ||--o{ JOB_POSITIONS : "defines salary grade"
    JOB_POSITIONS ||--o{ EMPLOYEES : "assigned position"
    EMPLOYEES ||--o{ EMPLOYMENT_CONTRACTS : "holds contracts"

    %% --- Relasi Presensi & Shift ---
    SHIFTS ||--o{ ATTENDANCES : "work schedule"
    EMPLOYEES ||--o{ ATTENDANCES : "records daily"

    %% --- Relasi Cuti & Lembur ---
    LEAVE_TYPES ||--o{ LEAVE_BALANCES : "allocates quota"
    EMPLOYEES ||--o{ LEAVE_BALANCES : "owns yearly balance"
    LEAVE_TYPES ||--o{ LEAVE_REQUESTS : "categorizes leave"
    EMPLOYEES ||--o{ LEAVE_REQUESTS : "submits leave"
    EMPLOYEES ||--o{ OVERTIME_REQUESTS : "submits overtime"

    %% --- Relasi Alur Approval (Two-Step Workflow) ---
    LEAVE_REQUESTS ||--o{ APPROVALS : "requires approval"
    OVERTIME_REQUESTS ||--o{ APPROVALS : "requires approval"
    USERS ||--o{ APPROVALS : "reviewed by"

    %% --- Relasi Payroll Engine ---
    USERS ||--o{ PAYROLL_PERIODS : "managed by"
    PAYROLL_PERIODS ||--o{ PAYROLLS : "monthly batch"
    EMPLOYEES ||--o{ PAYROLLS : "receives payslip"
    PAYROLLS ||--o{ PAYROLL_ITEMS : "itemized breakdown"

    USERS {
        bigint id PK
        string name
        string email UK
        string password
        string phone
        string avatar_url
        boolean is_active
        timestamp created_at
    }

    BRANCHES {
        bigint id PK
        string code UK
        string name
        text address
        decimal latitude
        decimal longitude
        int radius_meters
        string timezone
        boolean is_active
    }

    DEPARTMENTS {
        bigint id PK
        string code UK
        string name
    }

    JOB_GRADES {
        bigint id PK
        string name
        int level
        decimal min_salary
        decimal max_salary
    }

    JOB_POSITIONS {
        bigint id PK
        bigint department_id FK
        bigint job_grade_id FK
        string title
    }

    EMPLOYEES {
        bigint id PK
        bigint user_id FK,UK "Akun User"
        bigint manager_id FK "Line Manager (Self-ref employees.id)"
        bigint branch_id FK "Cabang Kantor"
        bigint department_id FK "Departemen"
        bigint job_position_id FK "Jabatan"
        string nik UK
        string ktp_number UK
        string gender
        date birth_date
        string birth_place
        string marital_status
        int dependents_count
        string bank_name
        string bank_account_number
        string bank_account_holder
        string employment_status
        date join_date
        date resign_date
    }

    EMPLOYMENT_CONTRACTS {
        bigint id PK
        bigint employee_id FK
        string contract_number UK
        string contract_type
        date start_date
        date end_date
        decimal basic_salary
        string document_path
        string status
    }

    SHIFTS {
        bigint id PK
        bigint branch_id FK
        string name
        time start_time
        time end_time
        int late_tolerance_minutes
        boolean is_active
    }

    ATTENDANCES {
        bigint id PK
        bigint employee_id FK
        bigint shift_id FK
        date date
        timestamp clock_in_time
        decimal clock_in_latitude
        decimal clock_in_longitude
        decimal clock_in_distance_meters
        string clock_in_photo_path
        timestamp clock_out_time
        decimal clock_out_latitude
        decimal clock_out_longitude
        decimal clock_out_distance_meters
        string clock_out_photo_path
        string status
        int late_minutes
        int early_leave_minutes
        int work_duration_minutes
        text notes
    }

    LEAVE_TYPES {
        bigint id PK
        string code UK
        string name
        int default_quota
        boolean deduct_quota
        int min_notice_days
        boolean requires_attachment
        boolean is_active
    }

    LEAVE_BALANCES {
        bigint id PK
        bigint employee_id FK
        bigint leave_type_id FK
        smallint year
        int total_quota
        int used_quota
        int remaining_quota
        date valid_until
    }

    LEAVE_REQUESTS {
        bigint id PK
        string request_number UK
        bigint employee_id FK
        bigint leave_type_id FK
        date start_date
        date end_date
        int total_days
        text reason
        string attachment_path
        string status
        string current_step
    }

    OVERTIME_REQUESTS {
        bigint id PK
        string request_number UK
        bigint employee_id FK
        date date
        time start_time
        time end_time
        decimal duration_hours
        text task_description
        decimal rate_multiplier
        string status
        string current_step
    }

    APPROVALS {
        bigint id PK
        string approvable_type
        bigint approvable_id
        string stage
        bigint reviewer_id FK
        string status
        text notes
        timestamp action_at
    }

    PAYROLL_PERIODS {
        bigint id PK
        string name
        date start_date
        date cutoff_date
        date payment_date
        string status
        bigint generated_by FK "HR Staff"
        bigint approved_by FK "HR Manager"
        timestamp approved_at
    }

    PAYROLLS {
        bigint id PK
        bigint payroll_period_id FK
        bigint employee_id FK
        string payslip_number UK
        decimal basic_salary
        decimal total_allowances
        decimal total_overtime_pay
        decimal total_deductions
        decimal late_deduction_amount
        decimal bpjs_kesehatan
        decimal bpjs_ketenagakerjaan
        decimal tax_pph21
        decimal net_salary
        string pdf_path
        string status
    }

    PAYROLL_ITEMS {
        bigint id PK
        bigint payroll_id FK
        string name
        string type
        decimal amount
        string notes
    }
```

> [!NOTE]
> **Catatan Struktur Hierarki Atasan (Line Manager):**
> Pada tabel `EMPLOYEES`, kolom `manager_id` adalah *Foreign Key* yang mereferensikan kembali ke `employees.id` (atasan langsung). Pada diagram di atas relasi ini dinyatakan secara eksplisit pada atribut `bigint manager_id FK "Line Manager (Self-ref employees.id)"` tanpa menarik garis memutar (*self-loop*) agar Mermaid dapat me-render diagram secara presisi tanpa ada garis yang saling memotong/menabrak isi tabel.

---

## 2. Rincian Kamus Data (Data Dictionary)

### Modul 1: Organization & Branch Hierarchy
| Nama Tabel | Deskripsi | Kunci Utama (PK) | Kunci Asing (FK) & Relasi |
| :--- | :--- | :--- | :--- |
| `branches` | Data kantor pusat & cabang beserta titik GPS koordinat (lat/lng) dan radius validasi (default 100m). | `id` | - |
| `departments` | Unit kerja organisasi (e.g. IT, HR, Finance, Operations). | `id` | - |
| `job_grades` | Penjenjangan level jabatan beserta rentang gaji minimum & maksimum. | `id` | - |
| `job_positions` | Posisi pekerjaan spesifik yang terafiliasi dengan departemen dan grade gaji. | `id` | `department_id`, `job_grade_id` |

### Modul 2: User, Role & Employee Management
| Nama Tabel | Deskripsi | Kunci Utama (PK) | Kunci Asing (FK) & Relasi |
| :--- | :--- | :--- | :--- |
| `users` | Akun autentikasi sistem. Hak akses dikelola oleh Spatie Role-Based Access Control (`roles`, `permissions`). | `id` | - |
| `employees` | Profil lengkap data kepegawaian. Menyimpan referensi `manager_id` (Line Manager) untuk alur approval. | `id` | `user_id` (1:1), `manager_id` (self), `branch_id`, `department_id`, `job_position_id` |
| `employment_contracts`| Riwayat kontrak kerja karyawan (PKWT, PKWTT, Internship) dan histori gaji pokok resmi. | `id` | `employee_id` |

### Modul 3: Automated Attendance (GPS & Tolerance)
| Nama Tabel | Deskripsi | Kunci Utama (PK) | Constraint & Logika Bisnis |
| :--- | :--- | :--- | :--- |
| `shifts` | Jadwal jam kerja operasional kantor dengan toleransi keterlambatan 15 menit. | `id` | `late_tolerance_minutes = 15` |
| `attendances` | Log presensi harian karyawan. Menyimpan titik GPS dan jarak aktual saat clock-in/out. | `id` | `UNIQUE(employee_id, date)`. Jika waktu check-in melebihi `shift.start_time + 15 menit`, tercatat `late_minutes`. |

### Modul 4: Leave, Permit & Overtime (Time-Off)
| Nama Tabel | Deskripsi | Kunci Utama (PK) | Constraint & Logika Bisnis |
| :--- | :--- | :--- | :--- |
| `leave_types` | Master jenis cuti. Menyimpan aturan H-3 pengajuan (`min_notice_days = 3`) dan syarat surat dokter (`requires_attachment`). | `id` | `code` UNIQUE |
| `leave_balances` | Kuota tahunan cuti per karyawan per tahun. | `id` | `UNIQUE(employee_id, leave_type_id, year)`. Constraint `remaining_quota >= 0`. |
| `leave_requests` | Formulir pengajuan cuti/sakit/izin. Menyimpan file attachment bukti surat dokter. | `id` | `request_number` UNIQUE |
| `overtime_requests`| Pengajuan lembur kerja karyawan. Dibatasi maksimal 4.0 jam per hari kerja. | `id` | `request_number` UNIQUE, `duration_hours <= 4.0` |

### Modul 5: Two-Step Approval Workflow
| Nama Tabel | Deskripsi | Kunci Utama (PK) | Polimorfik & Tahapan |
| :--- | :--- | :--- | :--- |
| `approvals` | Jejak audit persetujuan bertingkat untuk Cuti dan Lembur. | `id` | `approvable_type` (`LeaveRequest` / `OvertimeRequest`), `approvable_id`, `stage` (`manager` -> `hr`), `reviewer_id` (`users.id`). |

### Modul 6: Automated Payroll Engine & Payslips
| Nama Tabel | Deskripsi | Kunci Utama (PK) | Logika Bisnis & Otorisasi |
| :--- | :--- | :--- | :--- |
| `payroll_periods` | Periode penggajian bulanan berdasarkan tanggal cut-off (e.g. tanggal 25). | `id` | Draft digenerate oleh HR Staff (`generated_by`), disahkan oleh HR Manager (`approved_by`). |
| `payrolls` | Kalkulasi gaji bersih per karyawan (gaji pokok + tunjangan + lembur - potongan telat - BPJS - PPh 21). | `id` | `UNIQUE(payroll_period_id, employee_id)`. Terintegrasi dengan link file slip gaji PDF. |
| `payroll_items` | Rincian breakdown setiap komponen penambah dan pengurang gaji. | `id` | `payroll_id` ON DELETE CASCADE |

---

## 3. Strategi Indexing & Optimasi Query (High Performance)

```sql
-- 1. Optimasi query riwayat absensi bulanan karyawan (Fast Cut-off Aggregation)
CREATE INDEX idx_attendances_emp_date ON attendances(employee_id, date);
CREATE INDEX idx_attendances_date_status ON attendances(date, status);

-- 2. Optimasi query bawahan langsung pada Dashboard Manager
CREATE INDEX idx_employees_manager ON employees(manager_id);
CREATE INDEX idx_employees_branch_dept ON employees(branch_id, department_id);

-- 3. Optimasi query pengajuan yang butuh review segera (Pending Approval Tasks)
CREATE INDEX idx_leave_requests_step ON leave_requests(current_step, status);
CREATE INDEX idx_overtime_requests_step ON overtime_requests(current_step, status);

-- 4. Optimasi pencarian audit jejak persetujuan (Polymorphic Index)
CREATE INDEX idx_approvals_target ON approvals(approvable_type, approvable_id, stage);
```

---

## 4. Keamanan Transaksi Database (ACID Safety)

1. **Pencegahan Sisa Saldo Cuti Minus (*No Race Conditions*)**:
   Saat HR Manager memberikan persetujuan akhir pada `leave_requests`, sistem menjalankan:
   ```php
   DB::transaction(function () use ($leaveRequest) {
       $balance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
           ->where('leave_type_id', $leaveRequest->leave_type_id)
           ->where('year', now()->year)
           ->lockForUpdate() // Row locking mencegah double deduct
           ->firstOrFail();

       if ($balance->remaining_quota < $leaveRequest->total_days) {
           throw new InsufficientLeaveBalanceException();
       }

       $balance->decrement('remaining_quota', $leaveRequest->total_days);
       $balance->increment('used_quota', $leaveRequest->total_days);
       
       $leaveRequest->update(['status' => 'approved', 'current_step' => 'completed']);
   });
   ```

2. **Konsistensi Generate Payroll Batch**:
   Kalkulasi ribuan data absensi menjadi draft slip gaji diproses per chunk dengan `DB::transaction` agar data nominal pendapatan, potongan keterlambatan, lembur, dan slip item selalu presisi 100%.
