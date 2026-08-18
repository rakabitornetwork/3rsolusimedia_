<?php

namespace Tests\Unit;

use App\Support\AdminListState;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminListStateTest extends TestCase
{
    private function request(string $uri, array $query = []): Request
    {
        $request = Request::create($uri, 'GET', $query);
        $request->setLaravelSession($this->app['session.store']);

        return $request;
    }

    #[Test]
    public function it_saves_and_restores_list_filters(): void
    {
        $first = $this->request('/admin/billing', [
            'status' => 'unpaid',
            'q' => 'budi',
        ]);
        AdminListState::apply($first, AdminListState::BILLING, [
            'q', 'status', 'overdue', 'grace', 'page',
        ]);

        $this->assertSame('unpaid', $first->session()->get(AdminListState::sessionKey(AdminListState::BILLING))['status']);

        $second = $this->request('/admin/billing');
        AdminListState::apply($second, AdminListState::BILLING, [
            'q', 'status', 'overdue', 'grace', 'page',
        ]);

        $this->assertSame('unpaid', $second->query('status'));
        $this->assertSame('budi', $second->query('q'));
    }

    #[Test]
    public function it_reuses_last_router_across_pages(): void
    {
        $hotspot = $this->request('/admin/network/hotspot', ['router_id' => '7']);
        AdminListState::apply($hotspot, AdminListState::HOTSPOT, [
            'router_id', 'q',
        ], preferLastRouter: true);

        $this->assertSame(7, AdminListState::lastRouterId($hotspot));

        $sessions = $this->request('/admin/customers/pppoe/sessions');
        AdminListState::apply($sessions, AdminListState::PPPOE_SESSIONS, [
            'router_id', 'q',
        ], preferLastRouter: true);

        $this->assertSame('7', (string) $sessions->query('router_id'));
    }

    #[Test]
    public function it_redirects_to_saved_filters(): void
    {
        $this->app['session.store']->put(AdminListState::sessionKey(AdminListState::PPPOE), [
            'status' => 'isolated',
            'page' => 2,
        ]);

        $response = AdminListState::to('admin.customers.pppoe', AdminListState::PPPOE);

        $this->assertSame(
            route('admin.customers.pppoe', [
                'status' => 'isolated',
                'page' => 2,
            ]),
            $response->getTargetUrl()
        );
    }
}
