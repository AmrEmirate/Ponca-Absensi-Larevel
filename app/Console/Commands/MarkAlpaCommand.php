<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Absensi;
use Carbon\Carbon;

class MarkAlpaCommand extends Command
{
    protected $signature = 'attendance:mark-alpa';
    protected $description = 'Menandai karyawan yang tidak absen sebagai ALPA';

    private array $daysInIndonesian = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    public function handle(): void
    {
        $this->info('Menjalankan Cron Job: Pengecekan Absensi Harian...');

        $wibTime = Carbon::now('Asia/Jakarta');

        // Target date adalah hari sebelumnya (karena berjalan tepat jam 00:00)
        $targetDate = $wibTime->copy()->subDay();
        $dayOfWeek = $this->daysInIndonesian[$targetDate->dayOfWeek];
        $startOfDay = $targetDate->toDateString();

        // Ambil semua karyawan dan admin aktif
        $employees = User::whereIn('role', ['KARYAWAN', 'ADMIN'])
            ->where('is_active', true)
            ->get();

        $alpaCount = 0;

        foreach ($employees as $emp) {
            if (!$emp->hari_kerja || !str_contains($emp->hari_kerja, $dayOfWeek)) {
                continue; // Bukan hari kerja, lewati
            }

            $absensi = Absensi::where('user_id', $emp->id)
                ->where('tanggal', $startOfDay)
                ->first();

            if (!$absensi) {
                Absensi::create([
                    'user_id' => $emp->id,
                    'tanggal' => $startOfDay,
                    'status' => 'ALPA',
                ]);

                $this->info("[ALPA] Menandai karyawan {$emp->nama} ({$emp->nik}) sebagai ALPA untuk tanggal {$startOfDay}");
                $alpaCount++;
            }
        }

        $this->info("Cron Job Selesai. {$alpaCount} karyawan ditandai ALPA.");
    }
}
