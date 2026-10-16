# 📡 HRIS Enterprise API Reference & Integration Guide

Dokumentasi lengkap antarmuka REST API sistem HRIS Enterprise. Seluruh endpoint menggunakan prefix `/api/`, format data JSON, dan autentikasi Bearer Token menggunakan **Laravel Sanctum**.

---

## 🔐 1. Akun Pengujian & Demo Kredensial

Semua akun menggunakan default password: `password`

| Peran (Role) | Email | Nama Pengguna | Akses & Wewenang Utama |
|---|---|---|---|
| **Superadmin** | `superadmin@hris.local` | Super Admin | Akses penuh ke seluruh modul sistem |
| **HR Manager** | `hrmanager@hris.local` | HR Manager | Otorisasi Payroll, Approval Cuti & Lembur Tahap 2, Kelola Karyawan |
| **HR Staff** | `hrstaff@hris.local` | HR Staff | Generate Draf Payroll, Master Data, Kelola Karyawan, Anomali Absensi |
| **Manager** | `manager@hris.local` | Budi Pratama | Approval Cuti & Lembur Tahap 1 (Bawahan), Monitoring Tim Absensi |
| **Employee** | `employee@hris.local` | Siti Nurhaliza | Self-service: Clock-In/Out GPS, Ajukan Cuti & Lembur, Unduh Slip Gaji |

---

## 🛡️ 2. Standar Format Respons API

### Respons Sukses (`200 OK` / `201 Created`)
```json
{
  "success": true,
  "message": "Pesan deskriptif operasi berhasil",
  "data": { ... }
}
```

### Respons Validasi / Bisnis Gagal (`422 Unprocessable Entity`)
```json
{
  "success": false,
  "message": "Pesan aturan bisnis (contoh: Lokasi Anda berada 150m dari kantor)",
  "errors": {
    "field_name": ["Detail pesan validasi input"]
  }
}
```

### Respons Akses Ditolak (`403 Forbidden`)
```json
{
  "message": "User does not have the right roles."
}
```

---

## 📌 3. Daftar Endpoint Lengkap (46 Endpoints)

### A. Autentikasi & Profil Pengguna (`/api/auth`)
* `POST /api/auth/login` - Login pengguna (mengembalikan Sanctum bearer token & info profil pengguna).
* `GET /api/auth/me` - Mengambil info profil pengguna yang sedang login beserta role-nya (*Memerlukan Auth*).
* `POST /api/auth/logout` - Revoke token dan logout dari sesi aktif (*Memerlukan Auth*).

---

### B. Master Data (`/api/departments` & `/api/job-positions`)
* `GET /api/departments` - Daftar seluruh departemen.
* `POST /api/departments` - Tambah departemen (*Role: HR Staff, HR Manager, Superadmin*).
* `GET /api/departments/{id}` - Detail departemen.
* `PUT /api/departments/{id}` - Perbarui departemen (*Role: HR Staff, HR Manager, Superadmin*).
* `DELETE /api/departments/{id}` - Hapus departemen (*Role: HR Staff, HR Manager, Superadmin*).
* `GET /api/job-positions` - Daftar seluruh jabatan kerja.
* `GET /api/job-positions/options` - Daftar opsi jabatan ringkas untuk dropdown form.
* `POST /api/job-positions` - Tambah jabatan (*Role: HR Staff, HR Manager, Superadmin*).
* `GET /api/job-positions/{id}` - Detail jabatan.
* `PUT /api/job-positions/{id}` - Perbarui jabatan (*Role: HR Staff, HR Manager, Superadmin*).
* `DELETE /api/job-positions/{id}` - Hapus jabatan (*Role: HR Staff, HR Manager, Superadmin*).

---

### C. Manajemen Karyawan (`/api/employees`)
* `GET /api/employees` - Daftar seluruh profil karyawan (*Role: HR Staff, HR Manager, Superadmin*).
* `POST /api/employees` - Daftarkan karyawan baru beserta kontrak dan user login (*Role: HR Staff, HR Manager, Superadmin*).
* `GET /api/employees/subordinates` - Daftar bawahan langsung (*Role: Manager, HR Manager, Superadmin*).
* `GET /api/employees/{id}` - Detail profil karyawan lengkap (biodata, jabatan, departemen, kontrak, kuota cuti).
* `PUT /api/employees/{id}` - Perbarui profil karyawan (*Role: HR Staff, HR Manager, Superadmin*).
* `DELETE /api/employees/{id}` - Nonaktifkan karyawan (*Role: HR Staff, HR Manager, Superadmin*).

---

### D. Sistem Absensi Geolocation GPS (`/api/attendances`)
* `POST /api/attendances/clock-in` - Presensi masuk kerja. Validasi otomatis radius 100m ke kantor cabang & toleransi keterlambatan 15 menit.
  * *Body:* `{ "latitude": -6.225014, "longitude": 106.809657, "photo": (opsional image) }`
* `POST /api/attendances/clock-out` - Presensi pulang kerja. Menghitung total jam kerja efektif hari berjalan.
  * *Body:* `{ "latitude": -6.225014, "longitude": 106.809657, "photo": (opsional image) }`
* `GET /api/attendances/today` - Status presensi hari ini karyawan yang login (jam masuk, status terlambat, jam pulang).
* `GET /api/attendances/my-history` - Riwayat presensi bulanan karyawan yang login.
* `GET /api/attendances/anomalies` - Daftar anomali presensi (terlambat, tanpa clock-out, tidak hadir) (*Role: HR Staff, HR Manager, Superadmin*).
* `GET /api/attendances/team` - Rekap kehadiran tim bawahan langsung (*Role: Manager, HR Manager, Superadmin*).

---

### E. Manajemen Pengajuan Cuti & Izin Sakit (`/api/leaves`)
* `GET /api/leaves/types` - Daftar jenis cuti/izin (Cuti Tahunan, Izin Sakit, Melahirkan, dsb).
* `GET /api/leaves/balances` - Informasi sisa kuota cuti karyawan yang login (kuota awal, terpakai, sisa).
* `GET /api/leaves` - Riwayat pengajuan cuti pribadi karyawan yang login.
* `POST /api/leaves` - Mengajukan cuti / izin.
  * *Aturan Bisnis:*
    * Cuti Tahunan wajib diajukan minimal H-3 sebelum tanggal cuti.
    * Izin Sakit wajib melampirkan surat dokter (maks 1MB).
  * *Body:* `{ "leave_type_id": 1, "start_date": "2026-10-25", "end_date": "2026-10-26", "reason": "...", "attachment": (file) }`

---

### F. Manajemen Lembur (`/api/overtimes`)
* `GET /api/overtimes` - Riwayat pengajuan lembur pribadi karyawan yang login.
* `POST /api/overtimes` - Mengajukan lembur kerja.
  * *Aturan Bisnis:* Maksimal 4 jam per hari kerja.
  * *Body:* `{ "date": "2026-10-20", "start_time": "17:00", "end_time": "19:30", "duration_hours": 2.5, "task_description": "..." }`

---

### G. Alur Persetujuan Bertingkat / Two-Step Approval (`/api/approvals`)
(*Akses: Manager, HR Manager, Superadmin*)
* `GET /api/approvals/leaves` - Mengambil daftar pengajuan cuti yang menunggu persetujuan (otomatis disaring sesuai tahap: Line Manager atau HR Manager).
* `POST /api/approvals/leaves/{id}/approve` - Setujui cuti (Tahap 1: diteruskan ke HR; Tahap 2: cuti disahkan & kuota tahunan dipotong secara atomik).
* `POST /api/approvals/leaves/{id}/reject` - Tolak cuti dengan alasan penolakan wajib diisi.
* `GET /api/approvals/overtimes` - Mengambil daftar pengajuan lembur yang menunggu persetujuan.
* `POST /api/approvals/overtimes/{id}/approve` - Setujui pengajuan lembur.
* `POST /api/approvals/overtimes/{id}/reject` - Tolak lembur dengan catatan.

---

### H. Penggajian & Slip Gaji Digital (`/api/payrolls`)
* `GET /api/payrolls/periods` - Daftar periode cut-off penggajian bulanan (*Role: HR Staff, HR Manager, Superadmin*).
* `POST /api/payrolls/periods` - Buat periode cut-off penggajian baru (*Role: HR Staff, HR Manager, Superadmin*).
* `POST /api/payrolls/periods/{id}/generate-draft` - Kalkulasi otomatis draf gaji seluruh karyawan aktif setelah tanggal cut-off (25). Menghitung gaji pokok, tunjangan, upah lembur formula Depnaker, potongan telat, BPJS Kesehatan & Ketenagakerjaan, PPh 21 (*Role: HR Staff, HR Manager, Superadmin*).
* `POST /api/payrolls/periods/{id}/authorize` - Otorisasi final dan publikasi slip gaji digital kepada seluruh karyawan (*Role: HR Manager, Superadmin*).
* `GET /api/payrolls/periods/{id}/list` - Rekapitulasi daftar gaji karyawan pada periode terkait (*Role: HR Staff, HR Manager, Superadmin*).
* `GET /api/payrolls/my-payslips` - Daftar slip gaji yang sudah dipublikasikan milik karyawan yang login.
* `GET /api/payrolls/{id}` - Rincian item slip gaji individual.
* `GET /api/payrolls/{id}/download` - **Unduh Dokumen PDF Slip Gaji Resmi** (dibuat menggunakan DomPDF dengan rincian pendapatan, potongan, dan legalitas).
