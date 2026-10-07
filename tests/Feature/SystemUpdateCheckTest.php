<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GitUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemUpdateCheckTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function opening_check_with_auto_does_not_flash_when_github_answers(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->mock(GitUpdateService::class, function ($mock) {
            $mock->shouldReceive('gatherStatus')
                ->once()
                ->andReturn([
                    'fetch_ok' => true,
                    'message' => 'Berhasil mengecek update dari GitHub.',
                ]);
        });

        $this->actingAs($admin)
            ->post('/admin/system/update/check', ['auto' => 1])
            ->assertRedirect(route('admin.system.update.index'))
            ->assertSessionMissing('success')
            ->assertSessionMissing('error');
    }

    #[Test]
    public function manual_check_flashes_the_result(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->mock(GitUpdateService::class, function ($mock) {
            $mock->shouldReceive('gatherStatus')
                ->once()
                ->andReturn([
                    'fetch_ok' => true,
                    'message' => 'Berhasil mengecek update dari GitHub.',
                ]);
        });

        $this->actingAs($admin)
            ->post('/admin/system/update/check')
            ->assertRedirect(route('admin.system.update.index'))
            ->assertSessionHas('success', 'Berhasil mengecek update dari GitHub.');
    }

    #[Test]
    public function failed_auto_check_still_flashes_the_error(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->mock(GitUpdateService::class, function ($mock) {
            $mock->shouldReceive('gatherStatus')
                ->once()
                ->andReturn([
                    'fetch_ok' => false,
                    'message' => 'Gagal menghubungi GitHub: fetch gagal.',
                ]);
        });

        $this->actingAs($admin)
            ->post('/admin/system/update/check', ['auto' => 1])
            ->assertRedirect(route('admin.system.update.index'))
            ->assertSessionHas('error', 'Gagal menghubungi GitHub: fetch gagal.');
    }
}
