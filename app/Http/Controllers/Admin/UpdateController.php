<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GitUpdateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UpdateController extends Controller
{
    public function __construct(private readonly GitUpdateService $git)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Admin/System/Update', [
            'repo' => $this->git->gatherStatus(fetch: false),
        ]);
    }

    public function check(Request $request): RedirectResponse
    {
        $status = $this->git->gatherStatus(fetch: true);
        $redirect = redirect()->route('admin.system.update.index');

        if (! ($status['fetch_ok'] ?? false)) {
            return $redirect->with(
                'error',
                $status['message'] ?? 'Gagal mengecek update dari GitHub.',
            );
        }

        if ($request->boolean('auto')) {
            return $redirect;
        }

        return $redirect->with(
            'success',
            $status['message'] ?? 'Status repositori diperbarui.',
        );
    }

    public function pull(): RedirectResponse
    {
        $status = $this->git->gatherStatus(fetch: true);

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
        $pull = $this->git->runGit(['pull', '--ff-only', 'origin', $branch], 90);

        if (! ($pull['ok'] ?? false)) {
            $detail = $pull['error'] !== '' ? $pull['error'] : ($pull['output'] !== '' ? $pull['output'] : 'git pull gagal.');

            return redirect()
                ->route('admin.system.update.index')
                ->with('error', 'Gagal pull dari GitHub: '.$detail);
        }

        $after = $this->git->gatherStatus(fetch: false);
        $commit = $after['local_commit_short'] ?? null;
        $version = $after['local_version'] ?? null;

        return redirect()
            ->route('admin.system.update.index')
            ->with(
                'success',
                'Pull berhasil'
                .($commit ? " · {$commit}" : '')
                .($version ? " · v{$version}" : '')
                .' · tanpa npm'
            );
    }
}
