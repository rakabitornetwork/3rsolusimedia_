<?php

namespace App\Services;

use Symfony\Component\Process\Process;
use Throwable;

class GitUpdateService
{
    /**
     * @return array<string, mixed>
     */
    public function gatherStatus(bool $fetch = false, int $fetchTimeout = 60): array
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
            'local_version' => null,
            'local_version_full' => null,
            'remote_version' => null,
            'latest_tag' => null,
            'commits_since_tag' => 0,
            'ahead' => 0,
            'behind' => 0,
            'dirty' => false,
            'can_pull' => false,
            'sync_status' => 'unknown',
            'sync_label' => 'Tidak terdeteksi',
            'fetch_ok' => null,
            'message' => null,
            'github_url' => null,
            'incoming_commits' => [],
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
            $fetchResult = $this->runGit(['fetch', 'origin', '--prune', '--tags'], $fetchTimeout);
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

        $base = [...$base, ...$this->resolveVersions($remoteRef)];

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

            if ($base['behind'] > 0) {
                $base['incoming_commits'] = $this->listIncomingCommits(
                    $base['local_commit'],
                    $base['remote_commit'],
                    5,
                );
            }
        } elseif ($base['local_commit'] && ! $base['remote_commit']) {
            $base['sync_status'] = 'unknown';
            $base['sync_label'] = 'Remote tracking belum tersedia — coba Cek update';
        }

        $base['can_pull'] = $base['sync_status'] === 'behind' && ! $base['dirty'];

        if ($base['message'] === null) {
            $base['message'] = $base['sync_label'];
        }

        return $base;
    }

    /**
     * Ringkas untuk badge Dashboard — otomatis fetch dari GitHub.
     *
     * @return array{
     *     available: bool,
     *     has_update: bool,
     *     behind: int,
     *     sync_status: string,
     *     sync_label: string,
     *     local_version: ?string,
     *     remote_version: ?string,
     *     remote_commit_short: ?string,
     *     incoming_commits: array<int, array<string, string>>,
     *     fetch_ok: ?bool,
     *     href: string
     * }|null
     */
    public function dashboardNotice(): ?array
    {
        $status = $this->gatherStatus(fetch: true, fetchTimeout: 20);

        if (! ($status['available'] ?? false)) {
            return null;
        }

        $hasUpdate = in_array($status['sync_status'], ['behind', 'diverged'], true)
            && ($status['behind'] ?? 0) > 0;

        if (! $hasUpdate) {
            return null;
        }

        return [
            'available' => true,
            'has_update' => true,
            'behind' => (int) ($status['behind'] ?? 0),
            'sync_status' => (string) ($status['sync_status'] ?? 'behind'),
            'sync_label' => (string) ($status['sync_label'] ?? 'Ada update di GitHub'),
            'local_version' => $status['local_version'] ?? null,
            'remote_version' => $status['remote_version'] ?? null,
            'remote_commit_short' => $status['remote_commit_short'] ?? null,
            'incoming_commits' => $status['incoming_commits'] ?? [],
            'fetch_ok' => $status['fetch_ok'] ?? null,
            'href' => '/admin/system/update',
        ];
    }

    /**
     * @return array<int, array{hash: string, subject: string, author: string, date: string}>
     */
    public function listIncomingCommits(string $localCommit, string $remoteCommit, int $limit = 5): array
    {
        $log = $this->runGit([
            'log',
            '--pretty=format:%h%x1f%s%x1f%an%x1f%cr',
            '--no-merges',
            '-n',
            (string) $limit,
            $localCommit.'..'.$remoteCommit,
        ]);

        if (! ($log['ok'] ?? false) || $log['output'] === '') {
            return [];
        }

        $lines = preg_split('/\R/', $log['output']) ?: [];

        return collect($lines)
            ->map(function (string $line) {
                $parts = explode("\x1f", $line);
                if (count($parts) < 4) {
                    return null;
                }

                return [
                    'hash' => trim($parts[0]),
                    'subject' => trim($parts[1]),
                    'author' => trim($parts[2]),
                    'date' => trim($parts[3]),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     local_version: ?string,
     *     local_version_full: ?string,
     *     remote_version: ?string,
     *     latest_tag: ?string,
     *     commits_since_tag: int
     * }
     */
    private function resolveVersions(string $remoteRef): array
    {
        $localTag = $this->runGit(['describe', '--tags', '--abbrev=0', 'HEAD']);
        $localFull = $this->runGit(['describe', '--tags', 'HEAD']);
        $remoteTag = $this->runGit(['describe', '--tags', '--abbrev=0', $remoteRef]);
        $latestTag = $this->runGit(['tag', '-l', '--sort=-v:refname']);

        $localVersion = ($localTag['ok'] ?? false) && $localTag['output'] !== ''
            ? $this->normalizeVersion($localTag['output'])
            : null;
        $localVersionFull = ($localFull['ok'] ?? false) && $localFull['output'] !== ''
            ? $localFull['output']
            : $localVersion;
        $remoteVersion = ($remoteTag['ok'] ?? false) && $remoteTag['output'] !== ''
            ? $this->normalizeVersion($remoteTag['output'])
            : null;

        $latest = null;
        if (($latestTag['ok'] ?? false) && $latestTag['output'] !== '') {
            $tags = preg_split('/\R/', $latestTag['output']) ?: [];
            $latest = $this->normalizeVersion(trim((string) ($tags[0] ?? ''))) ?: null;
        }

        $commitsSince = 0;
        if (is_string($localVersionFull) && preg_match('/^.+-(\d+)-g[0-9a-f]+$/i', $localVersionFull, $m)) {
            $commitsSince = (int) $m[1];
        }

        return [
            'local_version' => $localVersion,
            'local_version_full' => $localVersionFull,
            'remote_version' => $remoteVersion ?: $latest,
            'latest_tag' => $latest ?: $remoteVersion ?: $localVersion,
            'commits_since_tag' => $commitsSince,
        ];
    }

    private function normalizeVersion(string $tag): string
    {
        $tag = trim($tag);
        if ($tag === '') {
            return '';
        }

        return str_starts_with(strtolower($tag), 'v') ? substr($tag, 1) : $tag;
    }

    /**
     * @param  list<string>  $args
     * @return array{ok: bool, output: string, error: string}
     */
    public function runGit(array $args, int $timeout = 20): array
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
