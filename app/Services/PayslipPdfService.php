<?php

namespace App\Services;

use App\Models\Salary;

class PayslipPdfService
{
    private array $commands = [];

    public function render(Salary $salary): string
    {
        $this->commands = [];
        $employee = $salary->employee;
        $attendance = $salary->attendance_summary ?? [];
        $company = $salary->creator?->company_name ?: config('app.name', 'Company Name');

        $this->rect(35, 792, 525, 45);
        $this->text(50, 815, 16, $company, true);
        $this->text(50, 800, 9, 'Salary Slip for '.$salary->salary_month->format('F Y'));

        $this->rect(35, 700, 525, 92);
        $this->labelValue(45, 772, 'Employee ID', $employee->employee_id ?: '-');
        $this->labelValue(45, 755, 'Employee Name', $employee->name);
        $this->labelValue(45, 738, 'Designation', $employee->designation ?: '-');
        $this->labelValue(45, 721, 'Department', $employee->department ?: '-');
        $this->labelValue(45, 704, 'Date of Joining', $employee->date_of_joining?->format('d M Y') ?: '-');
        $this->labelValue(315, 772, 'Payment Mode', $salary->payment_mode ?: '-');
        $this->labelValue(315, 755, 'Bank', $salary->bank_name ?: ($employee->bank_name ?: '-'));
        $this->labelValue(315, 738, 'Account No.', $this->maskedAccount($salary->account_upi_address ?: $employee->account_number));
        $this->labelValue(315, 721, 'Payment Date', $salary->payment_date?->format('d M Y') ?: '-');

        $this->rect(35, 625, 525, 58);
        $this->gridLine(35, 654, 560, 654);
        $this->gridLine(167, 625, 167, 683);
        $this->gridLine(297, 625, 297, 683);
        $this->gridLine(430, 625, 430, 683);
        $this->text(42, 666, 9, 'Gross Wages');
        $this->text(175, 666, 9, $this->money($salary->gross_earnings), true);
        $this->text(305, 666, 9, 'Total Working Days');
        $this->text(438, 666, 9, (string) ($attendance['total_working_days'] ?? '-'), true);
        $this->text(42, 637, 9, 'Paid Days');
        $paidDays = ($attendance['present_days'] ?? 0) + ($attendance['paid_leaves'] ?? 0);
        $this->text(175, 637, 9, (string) $paidDays, true);
        $this->text(305, 637, 9, 'Leaves / LWP');
        $this->text(438, 637, 9, (string) ($attendance['unpaid_leaves'] ?? 0), true);

        $earnings = collect($salary->earnings ?? [])->values();
        $deductions = collect($salary->deductions ?? [])->values();
        $rowCount = max(4, $earnings->count(), $deductions->count());
        $top = 600;
        $rowHeight = 22;
        $bottom = $top - (($rowCount + 2) * $rowHeight);
        $this->rect(35, $bottom, 525, $top - $bottom);
        $this->gridLine(297, $bottom, 297, $top);
        $this->gridLine(35, $top - $rowHeight, 560, $top - $rowHeight);
        $this->text(145, $top - 15, 10, 'Earnings', true);
        $this->text(405, $top - 15, 10, 'Deductions', true);

        for ($index = 0; $index < $rowCount; $index++) {
            $y = $top - (($index + 2) * $rowHeight);
            $this->gridLine(35, $y, 560, $y);
            $earning = $earnings->get($index);
            $deduction = $deductions->get($index);
            if ($earning) {
                $this->text(43, $y + 7, 9, $earning['name']);
                $this->text(225, $y + 7, 9, $this->money($earning['amount']), true);
            }
            if ($deduction) {
                $this->text(305, $y + 7, 9, $deduction['name']);
                $this->text(485, $y + 7, 9, $this->money($deduction['amount']), true);
            }
        }

        $totalY = $bottom + $rowHeight;
        $this->text(43, $totalY + 7, 9, 'Total Earnings', true);
        $this->text(225, $totalY + 7, 9, $this->money($salary->gross_earnings), true);
        $this->text(305, $totalY + 7, 9, 'Total Deductions', true);
        $this->text(485, $totalY + 7, 9, $this->money($salary->total_deductions), true);
        $this->rect(297, $bottom - 30, 263, 30);
        $this->text(385, $bottom - 20, 11, 'Net Salary', true);
        $this->text(485, $bottom - 20, 11, $this->money($salary->net_payable), true);
        $this->text(35, $bottom - 55, 8, 'This is a computer-generated payslip and does not require a signature.');

        return $this->document(implode("\n", $this->commands));
    }

    private function labelValue(float $x, float $y, string $label, string $value): void
    {
        $this->text($x, $y, 8, $label);
        $this->text($x + 90, $y, 8, ':  '.$value, true);
    }

    private function money(mixed $amount): string
    {
        return 'Rs. '.number_format((float) $amount, 2);
    }

    private function maskedAccount(?string $value): string
    {
        if (! $value) {
            return '-';
        }
        $lastFour = substr(preg_replace('/\s+/', '', $value), -4);

        return 'XXXX XXXX '.$lastFour;
    }

    private function text(float $x, float $y, int $size, string $value, bool $bold = false): void
    {
        $font = $bold ? 'F2' : 'F1';
        $safe = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) ?: $value;
        $safe = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $safe);
        $this->commands[] = "BT /{$font} {$size} Tf {$x} {$y} Td ({$safe}) Tj ET";
    }

    private function rect(float $x, float $y, float $width, float $height): void
    {
        $this->commands[] = "{$x} {$y} {$width} {$height} re S";
    }

    private function gridLine(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->commands[] = "{$x1} {$y1} m {$x2} {$y2} l S";
    }

    private function document(string $stream): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $number => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($number + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }
}
