<?php

namespace App\Services;

use App\Support\AppSettings;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class BillingCycleService
{
    /**
     * Pola B: jatuh tempo pada tanggal tetap setiap bulan.
     * Due pertama = tanggal billing_day berikutnya yang benar-benar setelah start_date.
     */
    public function nextDueDate(CarbonInterface|string $startDate, int $billingDay): Carbon
    {
        $billingDay = $this->normalizeBillingDay($billingDay);
        $start = Carbon::parse($startDate)->startOfDay();

        $candidate = $this->alignToBillingDay($start->copy(), $billingDay);

        if ($candidate->greaterThan($start)) {
            return $candidate;
        }

        return $this->alignToBillingDay($start->copy()->addMonthNoOverflow(), $billingDay);
    }

    /**
     * Jatuh tempo pertama: pakai tanggal yang dipilih admin bila valid,
     * jangan maju ke bulan berikutnya hanya karena hari ini sudah lewat tanggal itu.
     */
    public function firstDueDate(
        CarbonInterface|string $startDate,
        int $billingDay,
        CarbonInterface|string|null $explicitDue = null,
    ): Carbon {
        $start = Carbon::parse($startDate)->startOfDay();
        $billingDay = $this->normalizeBillingDay($billingDay);

        if ($explicitDue) {
            $due = $this->alignToBillingDay($explicitDue, $billingDay);
            if ($due->greaterThan($start)) {
                return $due;
            }
        }

        return $this->nextDueDate($start, $billingDay);
    }

    /**
     * Samakan tanggal ke billing_day pada bulan yang sama (maks. 28 / akhir bulan).
     */
    public function alignToBillingDay(CarbonInterface|string $monthAnchor, int $billingDay): Carbon
    {
        $billingDay = $this->normalizeBillingDay($billingDay);
        $date = Carbon::parse($monthAnchor)->startOfDay();
        $day = min($billingDay, $date->daysInMonth);

        return $date->day($day);
    }

    /**
     * Hitung tagihan prorata dari start_date sampai due_date (hari due tidak dihitung).
     * Denominator = panjang siklus penuh (tanggal billing sebelumnya → due).
     *
     * @return array{
     *     start_date: string,
     *     due_date: string,
     *     billing_day: int,
     *     days: int,
     *     cycle_days: int,
     *     package_price: int,
     *     raw_amount: int,
     *     amount: int,
     *     amount_label: string,
     *     daily_rate: float,
     *     summary: string
     * }
     */
    public function calculateProrata(
        CarbonInterface|string $startDate,
        int $billingDay,
        int $packagePrice,
        CarbonInterface|string|null $dueDate = null,
    ): array {
        $billingDay = $this->normalizeBillingDay($billingDay);
        $start = Carbon::parse($startDate)->startOfDay();
        $due = $this->firstDueDate($start, $billingDay, $dueDate);

        $previousBilling = $this->alignToBillingDay($due->copy()->subMonthNoOverflow(), $billingDay);
        $cycleDays = max(1, (int) $previousBilling->diffInDays($due));
        $usedDays = max(0, (int) $start->diffInDays($due));

        $rawAmount = $usedDays === 0
            ? 0
            : (int) round($packagePrice * $usedDays / $cycleDays);
        $amount = $this->roundUpToThousand($rawAmount);

        return [
            'start_date' => $start->toDateString(),
            'due_date' => $due->toDateString(),
            'billing_day' => $billingDay,
            'days' => $usedDays,
            'cycle_days' => $cycleDays,
            'package_price' => $packagePrice,
            'raw_amount' => $rawAmount,
            'amount' => $amount,
            'amount_label' => 'Rp '.number_format($amount, 0, ',', '.'),
            'daily_rate' => round($packagePrice / $cycleDays, 2),
            'summary' => sprintf(
                'Prorata %s s/d %s (%d/%d hari siklus) × Rp %s = %s%s',
                $start->format('d M Y'),
                $due->copy()->subDay()->format('d M Y'),
                $usedDays,
                $cycleDays,
                number_format($packagePrice, 0, ',', '.'),
                'Rp '.number_format($amount, 0, ',', '.'),
                $rawAmount !== $amount
                    ? ' (dibulatkan dari Rp '.number_format($rawAmount, 0, ',', '.').')'
                    : ''
            ),
        ];
    }

    /**
     * Bulatkan ke atas ke kelipatan yang dikonfigurasi (default Rp 1.000).
     */
    public function roundUpToThousand(int $amount): int
    {
        if ($amount <= 0) {
            return 0;
        }

        $step = AppSettings::billingRoundTo();

        return (int) (ceil($amount / $step) * $step);
    }

    /**
     * Majukan jatuh tempo ke billing_day pada bulan berikutnya.
     */
    public function advanceDueDate(CarbonInterface|string $currentDue, int $billingDay): Carbon
    {
        $billingDay = $this->normalizeBillingDay($billingDay);
        $due = Carbon::parse($currentDue)->startOfDay();

        return $this->alignToBillingDay($due->copy()->addMonthNoOverflow(), $billingDay);
    }

    /**
     * Jatuh tempo setelah pelunasan N bulan.
     *
     * Dihitung dari due invoice (siklus berjalan). Jika hasilnya masih hari ini
     * atau sudah lewat — misalnya pelanggan terisolir lalu perpanjang 2 bulan
     * sekaligus dari due lama — dihitung ulang dari hari ini agar N bulan
     * pembayaran = N bulan layanan ke depan dan isolir terbuka.
     */
    public function dueDateAfterPayment(
        CarbonInterface|string $invoiceDue,
        int $billingDay,
        int $months = 1,
        CarbonInterface|string|null $today = null,
    ): Carbon {
        $months = max(1, $months);
        $billingDay = $this->normalizeBillingDay($billingDay);
        $today = Carbon::parse($today ?? now())->startOfDay();

        $cursor = $this->advanceMonths(
            Carbon::parse($invoiceDue)->startOfDay(),
            $billingDay,
            $months,
        );

        if ($cursor->greaterThan($today)) {
            return $cursor;
        }

        return $this->advanceMonths($today, $billingDay, $months);
    }

    private function advanceMonths(Carbon $from, int $billingDay, int $months): Carbon
    {
        $cursor = $from->copy()->startOfDay();

        for ($i = 0; $i < $months; $i++) {
            $cursor = $this->advanceDueDate($cursor, $billingDay);
        }

        return $cursor;
    }

    public function normalizeBillingDay(int $billingDay): int
    {
        return max(1, min(28, $billingDay));
    }
}
