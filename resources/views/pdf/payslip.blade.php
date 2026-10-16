<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji - {{ $payroll->payslip_number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #2b3a4a;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #1e293b;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .doc-title {
            font-size: 14px;
            font-weight: 600;
            color: #0284c7;
            letter-spacing: 1px;
        }
        .info-table, .calc-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .info-table td {
            padding: 4px 6px;
            vertical-align: top;
        }
        .calc-table th {
            background-color: #f1f5f9;
            color: #1e293b;
            text-align: left;
            padding: 8px;
            font-size: 11px;
            text-transform: uppercase;
            border-bottom: 1px solid #cbd5e1;
        }
        .calc-table td {
            padding: 8px;
            border-bottom: 1px solid #f1f5f9;
        }
        .text-right {
            text-align: right;
        }
        .total-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px;
            margin-top: 15px;
        }
        .net-salary {
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
        }
        .footer {
            margin-top: 40px;
            width: 100%;
        }
        .signature-box {
            text-align: center;
            width: 200px;
            float: right;
        }
        .signature-line {
            margin-top: 60px;
            border-top: 1px solid #64748b;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">{{ $payroll->employee->branch->name ?? 'ENTERPRISE HRIS PLATFORM' }}</div>
        <div>{{ $payroll->employee->branch->address ?? 'Jakarta, Indonesia' }}</div>
        <div class="doc-title" style="margin-top: 8px;">SLIP GAJI KARYAWAN (PAYSLIP)</div>
        <div style="font-size: 11px; color: #64748b;">Periode: {{ $payroll->payrollPeriod->name }}</div>
    </div>

    <table class="info-table">
        <tr>
            <td width="18%"><strong>No. Slip Gaji</strong></td>
            <td width="32%">: {{ $payroll->payslip_number }}</td>
            <td width="18%"><strong>Departemen</strong></td>
            <td width="32%">: {{ $payroll->employee->department->name ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>NIK Karyawan</strong></td>
            <td>: {{ $payroll->employee->nik }}</td>
            <td><strong>Jabatan</strong></td>
            <td>: {{ $payroll->employee->jobPosition->title ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Nama Lengkap</strong></td>
            <td>: <strong>{{ $payroll->employee->user->name }}</strong></td>
            <td><strong>Bank / No. Rekening</strong></td>
            <td>: {{ $payroll->employee->bank_name }} / {{ $payroll->employee->bank_account_number }}</td>
        </tr>
    </table>

    <table class="calc-table">
        <thead>
            <tr>
                <th width="70%">Komponen Pendapatan & Potongan</th>
                <th width="30%" class="text-right">Nominal (IDR)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payroll->items as $item)
                <tr>
                    <td>
                        {{ $item->name }}
                        @if($item->type === 'deduction')
                            <span style="color: #ef4444; font-size: 10px;">[Potongan]</span>
                        @else
                            <span style="color: #10b981; font-size: 10px;">[Pendapatan]</span>
                        @endif
                    </td>
                    <td class="text-right">
                        @if($item->type === 'deduction')
                            - Rp {{ number_format($item->amount, 0, ',', '.') }}
                        @else
                            Rp {{ number_format($item->amount, 0, ',', '.') }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-box">
        <table width="100%">
            <tr>
                <td><strong>Total Pendapatan Kotor (Gross)</strong></td>
                <td class="text-right">Rp {{ number_format($payroll->basic_salary + $payroll->total_allowances + $payroll->total_overtime_pay, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td><strong>Total Potongan (Deductions)</strong></td>
                <td class="text-right" style="color: #ef4444;">- Rp {{ number_format($payroll->total_deductions, 0, ',', '.') }}</td>
            </tr>
            <tr style="border-top: 1px dashed #cbd5e1;">
                <td style="padding-top: 8px;"><span class="net-salary">GAJI BERSIH (TAKE HOME PAY)</span></td>
                <td class="text-right" style="padding-top: 8px;"><span class="net-salary" style="color: #0284c7;">Rp {{ number_format($payroll->net_salary, 0, ',', '.') }}</span></td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <div class="signature-box">
            <div>Disahkan Oleh,</div>
            <div style="font-size: 10px; color: #64748b;">HR Manager</div>
            <div class="signature-line"></div>
            <div><strong>{{ $payroll->payrollPeriod->approver->name ?? 'Dewi Lestari, S.Psi' }}</strong></div>
            <div style="font-size: 9px; color: #94a3b8;">Tanggal: {{ $payroll->payrollPeriod->approved_at ? date('d/m/Y', strtotime($payroll->payrollPeriod->approved_at)) : date('d/m/Y') }}</div>
        </div>
    </div>
</body>
</html>
