<?php

namespace App\Console\Commands;

use App\Services\PppoeSessionMonitor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class WatchPppoeSessions extends Command
{
    protected $signature = 'pppoe:watch-sessions {--dry-run : Deteksi perubahan tanpa mengirim Telegram atau menyimpan snapshot}';

    protected $description = 'Pantau sesi PPPoE aktif di MikroTik dan kirim notifikasi connected/disconnected ke chat admin Telegram';

    public function handle(PppoeSessionMonitor $monitor): int
    {
        if ($this->option('dry-run')) {
            $summary = $monitor->run(persist: false, send: false);
            $this->info($summary['message'] ?: 'Dry-run selesai.');

            foreach ($summary['events'] as $event) {
                $this->line($this->eventLine($event));
            }

            return self::SUCCESS;
        }

        $lock = Cache::lock('pppoe:session-watch', 50);
        if (! $lock->get()) {
            $this->warn('Pemantauan sesi PPPoE masih berjalan.');

            return self::SUCCESS;
        }

        try {
            $summary = $monitor->run();
            $this->info($summary['message']);

            foreach ($summary['events'] as $event) {
                $this->line($this->eventLine($event));
            }
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function eventLine(array $event): string
    {
        $type = (string) ($event['type'] ?? '');
        $router = (string) ($event['router'] ?? '');

        return match ($type) {
            'up' => 'UP '.$router.' '.$event['username'],
            'down' => 'DOWN '.$router.' '.$event['username'],
            'mass_up' => 'MASS-UP '.$router.' '.$event['count'],
            'mass_down' => 'MASS-DOWN '.$router.' '.$event['count'],
            default => $type.' '.$router,
        };
    }
}
