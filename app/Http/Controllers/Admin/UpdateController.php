<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Process\Process;
use Throwable;

class UpdateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/System/Update', [
            'repo' => $this->gatherStatus(fetch: false),
        ]);
    }

    public function check(): RedirectResponse
    {
        $status = $this->gatherStatus(fetch: true);

        return redirect()
            ->route('admin.system.update.index')
            ->with(
                ($status['fetch_ok'] ?? false) ? 'success' : 'error',
                $status['message'] ?? 'Status repositori diperbarui.'
            );
    }

    public function pull(): RedirectResponse
    {
        $status = $this->gatherStatus(fetch: true);

        if (! ($status['fetch_ok'] ?? false)) {
            return redirect()
                ->route('admin.system.update.index')
                ->with('error', $status['message'] ?? 'Gagal menghubungi GitHub sebelum pull.');
        }

        if (! ($status['can_pull'] ?? false)) {
            $reason = match ($status['sync_status'] ?? 'unknown') {
                'up_to_date' => 'Tidak ada update terbaru dari GitHub.',
                'ahead' => 'Server lokal lebih maju dari GitHub. Pull dibatalkan.',
                'diverged' => 'Cabang berbeda (diverged). Pull otomatis dibatalkan.',
                default => $status['dirty']
                    ? 'Ada perubahan lokal yang belum di-commit. Pull dibatalkan agar tidak bentrok.'
                    : 'Pull tidak tersedia. Cek update terlebih dahulu.',
            };

            return redirect()
                ->route('admin.system.update.index')
                ->with('error', $reason);
        }

        $branch = $status['branch'] ?: 'main';
        $pull = $this->runGit(['pull', '--ff-only', 'origin', $branch], 90);

        if (! ($pull['ok'] ?? false)) {
            $detail = $pull['error'] !== '' ? $pull['error'] : ($pull['output'] !== '' ? $pull['output'] : 'git pull gagal.');

            return redirect()
                ->route('admin.system.update.index')
                ->with('error', 'Gagal pull dari GitHub: '.$detail);
        }

        $after = $this->gatherStatus(fetch: false);
        $summary = trim(($pull['output'] !== '' ? $pull['output'] : 'Pull berhasil.'));
        $commit = $after['local_commit_short'] ?? null;

        return redirect()
            ->route('admin.system.update.index')
            ->with(
                'success',
                'Update berhasil di-pull dari GitHub'
                .($commit ? " (sekarang di {$commit})" : '')
                .'. npm run build tidak dijalankan. Jika ada migrasi, jalankan php artisan migrate --force.'
                .($summary !== '' ? ' '.$summary : '')
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function gatherStatus(bool $fetch): array
    {
        $base = [
            'available' => false,
            'git_binary' => false,
            'remote_url' => null,
            'remote_name' => 'origin',
            'branch' => null,
            'local_commit' => null,
            'local_commit_short' => null,
            'remote_commit' => null,
            'remote_commit_short' => null,
            'ahead' => 0,
            'behind' => 0,
            'dirty' => false,
            'can_pull' => false,
            'sync_status' => 'unknown',
            'sync_label' => 'Tidak terdeteksi',
            'fetch_ok' => null,
            'message' => null,
            'github_url' => null,
            'checked_at' => now()->toIso8601String(),
        ];

        if (! is_dir(base_path('.git'))) {
            return [
                ...$base,
                'message' => 'Folder .git tidak ditemukan. Aplikasi ini belum terinisialisasi sebagai repositori Git.',
            ];
        }

        $version = $this->runGit(['--version']);
        if (! ($version['ok'] ?? false)) {
            return [
                ...$base,
                'message' => 'Perintah git tidak tersedia di server ini. Status update tidak bisa dicek otomatis.',
            ];
        }

        $base['git_binary'] = true;
        $base['available'] = true;

        if ($fetch) {
            $fetchResult = $this->runGit(['fetch', 'origin', '--prune'], 60);
            $base['fetch_ok'] = $fetchResult['ok'];
            if (! $fetchResult['ok']) {
                $base['message'] = 'Gagal menghubungi GitHub: '.
                    ($fetchResult['error'] !== '' ? $fetchResult['error'] : 'fetch gagal.');
            } else {
                $base['message'] = 'Berhasil mengecek update dari GitHub.';
            }
        }

        $branch = $this->runGit(['rev-parse', '--abbrev-ref', 'HEAD']);
        $base['branch'] = $branch['ok'] ? $branch['output'] : null;

        $local = $this->runGit(['rev-parse', 'HEAD']);
        if ($local['ok'] && $local['output'] !== '') {
            $base['local_commit'] = $local['output'];
            $base['local_commit_short'] = substr($local['output'], 0, 7);
        }

        $remoteUrl = $this->runGit(['remote', 'get-url', 'origin']);
        if ($remoteUrl['ok'] && $remoteUrl['output'] !== '') {
            $base['remote_url'] = $remoteUrl['output'];
            $base['github_url'] = $this->toGithubBrowseUrl($remoteUrl['output']);
        }

        $remoteRef = 'origin/'.($base['branch'] ?: 'main');
        $remote = $this->runGit(['rev-parse', $remoteRef]);
        if ($remote['ok'] && $remote['output'] !== '') {
            $base['remote_commit'] = $remote['output'];
            $base['remote_commit_short'] = substr($remote['output'], 0, 7);
        }

        $dirty = $this->runGit(['status', '--porcelain']);
        $base['dirty'] = $dirty['ok'] && $dirty['output'] !== '';

        if ($base['local_commit'] && $base['remote_commit']) {
            $counts = $this->runGit([
                'rev-list',
                '--left-right',
                '--count',
                $base['local_commit'].'...'.$base['remote_commit'],
            ]);

            if ($counts['ok'] && preg_match('/^(\d+)\s+(\d+)$/', $counts['output'], $m)) {
                $base['ahead'] = (int) $m[1];
                $base['behind'] = (int) $m[2];
            }

            if ($base['ahead'] === 0 && $base['behind'] === 0) {
                $base['sync_status'] = 'up_to_date';
                $base['sync_label'] = 'Sinkron dengan GitHub';
            } elseif ($base['ahead'] > 0 && $base['behind'] === 0) {
                $base['sync_status'] = 'ahead';
                $base['sync_label'] = 'Lokal lebih maju (belum di-push)';
            } elseif ($base['ahead'] === 0 && $base['behind'] > 0) {
                $base['sync_status'] = 'behind';
                $base['sync_label'] = 'Ada update di GitHub';
            } else {
                $base['sync_status'] = 'diverged';
                $base['sync_label'] = 'Cabang berbeda (diverged)';
            }
        } elseif ($base['local_commit'] && ! $base['remote_commit']) {
            $base['sync_status'] = 'unknown';
            $base['sync_label'] = 'Remote tracking belum tersedia — coba Cek update';
        }

        // Pull hanya jika remote lebih maju, working tree bersih, dan fast-forward aman.
        $base['can_pull'] = $base['sync_status'] === 'behind' && ! $base['dirty'];

        if ($base['message'] === null) {
            $base['message'] = $base['sync_label'];
        }

        return $base;
    }

    /**
     * @param  list<string>  $args
     * @return array{ok: bool, output: string, error: string}
     */
    private function runGit(array $args, int $timeout = 20): array
    {
        try {
            $command = array_merge(
                ['git', '-c', 'safe.directory='.base_path()],
                $args,
            );

            $process = new Process($command, base_path());
            $process->setTimeout($timeout);
            $process->run();

            return [
                'ok' => $process->isSuccessful(),
                'output' => trim($process->getOutput()),
                'error' => trim($process->getErrorOutput()),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'output' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function toGithubBrowseUrl(string $remoteUrl): ?string
    {
        $url = trim($remoteUrl);

        if (preg_match('#^git@github\.com:(.+?)(?:\.git)?$#', $url, $m)) {
            return 'https://github.com/'.$m[1];
        }

        if (preg_match('#^https?://github\.com/(.+?)(?:\.git)?$#', $url, $m)) {
            return 'https://github.com/'.$m[1];
        }

        return null;
    }
}
