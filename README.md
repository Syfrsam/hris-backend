# HRIS Enterprise Backend API

[![CI Test Suite](https://github.com/YOUR_GITHUB_USERNAME/hris-backend/actions/workflows/ci.yml/badge.svg)](https://github.com/YOUR_GITHUB_USERNAME/hris-backend/actions)
![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql)
![Pest / PHPUnit](https://img.shields.io/badge/Tests-17%20Passed%20(69%20Assertions)-brightgreen)

Sistem RESTful API komprehensif untuk HRIS Enterprise Platform yang mengelola presensi karyawan berbasis geofencing GPS, pengajuan izin cuti & lembur bertahap (2-step approval), dan engine penggajian otomatis berformula Depnaker dengan ekspor PDF payslip digital.

---

## 🚀 Fitur Utama

- **Authentication & RBAC:** Laravel Sanctum dengan 5 wewenang peran Spatie (`Superadmin`, `HR Manager`, `HR Staff`, `Manager`, `Employee`).
- **Presensi GPS & Toleransi Shift:** Formula Haversine dengan batas radius 100m dari kantor HQ Jakarta, toleransi keterlambatan 15 menit, dan audit anomali harian.
- **Manajemen Cuti & Izin Sakit:** Validasi cuti tahunan H-3, izin sakit wajib surat dokter <= 1MB, dan row-locking (`lockForUpdate`) pencegah race condition kuota.
- **Kompensasi Lembur:** Batas maksimum lembur 4.0 jam per hari dengan formula resmi Depnaker (1.5x jam pertama, 2.0x jam berikutnya).
- **Workflow Persetujuan 2-Tahap:** Line Manager (Tahap 1) ➔ HR Manager (Tahap 2) dengan catatan peninjauan terstruktur.
- **Engine Payroll Bulanan:** Cut-off absensi tanggal 25, 1-Click Generate Draft kalkulasi gaji bersih (BPJS Kesehatan, BPJS Ketenagakerjaan, PPh 21, denda telat), dan pengesahan digital HR Manager.
- **Slip Gaji Digital DomPDF:** Template slip gaji PDF digital resmi dengan rincian pendapatan, potongan, dan legalitas stempel perusahaan.

---

## 🌿 Strategi Percabangan (GitFlow)

Repositori ini menggunakan alur kerja cabang standar industri:
- `main`: Kode produksi (*production-ready*), selalu stabil dan siap rilis.
- `dev`: Cabang integrasi pengembangan aktif untuk pengujian harian.
- `feature/<nama-fitur>`: Cabang dari `dev` untuk membuat fitur baru sebelum di-Pull Request (PR) ke `dev`.
- `hotfix/<nama-perbaikan>`: Cabang darurat dari `main` untuk perbaikan cepat (*urgent bugfix*), lalu dimerge kembali ke `main` dan `dev`.

---

## 🤖 Robot Pengujian Otomatis (GitHub Actions CI)

Setiap `git push` atau `Pull Request` ke branch `main` atau `dev` akan memicu robot pengujian otomatis (`.github/workflows/ci.yml`):
1. Menyiapkan runtime PHP 8.2 & ekstensi pendukung.
2. Menginstall dependensi Composer.
3. Menjalankan seluruh rangkaian test otomatis (`php artisan test` - 17 unit/feature tests, 69 assertions).
4. Melakukan audit standar gaya kode menggunakan Laravel Pint.
5. Memberikan tanda centang hijau (**Passed**) atau merah (**Failed**) secara otomatis pada GitHub.

---

## 💻 Panduan Instalasi Lokal

### 1. Kebutuhan Sistem
- PHP >= 8.3 (ekstensi: `pdo_mysql`, `gd`, `bcmath`, `zip`, `intl`)
- Composer >= 2.x
- MySQL >= 8.0 (misal: Laragon / XAMPP)

### 2. Langkah Setup
```bash
# 1. Clone repositori
git clone https://github.com/YOUR_GITHUB_USERNAME/hris-backend.git
cd hris-backend

# 2. Install dependensi composer
composer install

# 3. Salin environment config
cp .env.example .env

# 4. Generate application key
php artisan key:generate

# 5. Konfigurasi database di .env
# DB_DATABASE=hris_db
# DB_USERNAME=root
# DB_PASSWORD=

# 6. Jalankan migrasi & data dummy seeders
php artisan migrate:fresh --seed

# 7. Hubungkan storage symlink
php artisan storage:link

# 8. Jalankan server lokal
php artisan serve
# Berjalan di http://127.0.0.1:8000
```

### 3. Menjalankan Pengujian Manual
```bash
php artisan test
```

---

## 🔑 Akun Demo Karyawan (Password: `password`)

| Role | Email | Wewenang |
|---|---|---|
| **Superadmin** | `superadmin@hris.local` | Akses penuh sistem & konfigurasi master data |
| **HR Manager** | `hrmanager@hris.local` | Final approval cuti/lembur & otorisasi publikasi slip gaji |
| **HR Staff** | `hrstaff@hris.local` | Registrasi karyawan, audit anomali, & 1-click generate draft gaji |
| **Manager** | `manager@hris.local` | Monitoring presensi tim & approval tahap 1 permohonan tim |
| **Employee** | `employee@hris.local` | Presensi GPS 100m, ajukan cuti/lembur, & unduh slip gaji PDF |

---

## 📄 Dokumentasi API & Postman
- Dokumentasi Endpoint lengkap: [`docs/API_DOCUMENTATION.md`](docs/API_DOCUMENTATION.md)
- Postman Collection dengan Auto-Token Test Script: [`docs/hris_postman_collection.json`](docs/hris_postman_collection.json)
