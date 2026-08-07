<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();

        $query = User::query()->latest();

        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->get('role')) {
            $query->where('role', $role);
        }

        $users = $query->paginate(15)->withQueryString()->through(
            function (User $user) use ($actor) {
                return [
                    ...$user->toAdminArray(),
                    'can_edit' => $actor->canEditUser($user),
                    'can_delete' => $actor->canDeleteUser($user),
                ];
            }
        );

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => [
                'q' => $request->get('q', ''),
                'role' => $request->get('role', ''),
            ],
            'role_options' => User::roleOptions(),
            'stats' => [
                'total' => User::query()->count(),
                'superadmin' => User::query()->where('role', User::ROLE_SUPERADMIN)->count(),
                'admin' => User::query()->where('role', User::ROLE_ADMIN)->count(),
                'teknisi' => User::query()->where('role', User::ROLE_TEKNISI)->count(),
                'agen' => User::query()->where('role', User::ROLE_AGEN)->count(),
            ],
            'can_manage' => $actor->canManageUsers(),
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $actor = $request->user();

        if (! $actor->canManageUsers()) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'Anda tidak memiliki akses untuk menambah pengguna.');
        }

        $pppoeCustomers = \App\Models\PppoeCustomer::query()
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'phone', 'agent_id']);

        return Inertia::render('Admin/Users/Form', [
            'user' => null,
            'role_options' => User::roleOptions($actor),
            'pppoe_customers' => $pppoeCustomers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();

        if (! $actor->canManageUsers()) {
            return back()->with('error', 'Anda tidak memiliki akses untuk menambah pengguna.');
        }

        $validated = $this->validateUser($request, $actor);
        $avatar = $this->storeAvatar($request);

        $user = User::query()->create([
            ...collect($validated)->except(['password_confirmation', 'avatar', 'remove_avatar', 'assigned_customer_ids'])->all(),
            'avatar' => $avatar,
        ]);

        if ($user->isAgen()) {
            $assignedIds = $request->input('assigned_customer_ids', []);
            if (is_array($assignedIds)) {
                \App\Models\PppoeCustomer::query()
                    ->whereIn('id', $assignedIds)
                    ->update(['agent_id' => $user->id]);
            }
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function edit(Request $request, User $user): Response|RedirectResponse
    {
        $actor = $request->user();

        if (! $actor->canEditUser($user)) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'Anda tidak dapat mengedit akun ini.');
        }

        $pppoeCustomers = \App\Models\PppoeCustomer::query()
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'phone', 'agent_id']);

        return Inertia::render('Admin/Users/Form', [
            'user' => $user->toAdminArray(),
            'role_options' => User::roleOptions($actor),
            'pppoe_customers' => $pppoeCustomers,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        if (! $actor->canEditUser($user)) {
            return back()->with('error', 'Anda tidak dapat mengedit akun ini.');
        }

        $validated = $this->validateUser($request, $actor, $user);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        unset($validated['password_confirmation'], $validated['avatar'], $validated['remove_avatar'], $validated['assigned_customer_ids']);

        $avatar = $user->avatar;

        if ($request->boolean('remove_avatar') && $avatar) {
            $user->deleteAvatarFile();
            $avatar = null;
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                $user->deleteAvatarFile();
            }
            $avatar = $this->storeAvatar($request);
        }

        $user->update([
            ...$validated,
            'avatar' => $avatar,
        ]);

        if ($user->isAgen()) {
            $assignedIds = (array) $request->input('assigned_customer_ids', []);
            // Unassign customers no longer in array
            \App\Models\PppoeCustomer::query()
                ->where('agent_id', $user->id)
                ->whereNotIn('id', $assignedIds)
                ->update(['agent_id' => null]);

            // Assign new customers
            if (! empty($assignedIds)) {
                \App\Models\PppoeCustomer::query()
                    ->whereIn('id', $assignedIds)
                    ->update(['agent_id' => $user->id]);
            }
        } else {
            // If role changed from agen to something else, clear assigned customers
            \App\Models\PppoeCustomer::query()
                ->where('agent_id', $user->id)
                ->update(['agent_id' => null]);
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        if (! $actor->canDeleteUser($user)) {
            return back()->with(
                'error',
                $user->isSuperadmin()
                    ? 'Admin tidak dapat menghapus akun Superadmin.'
                    : 'Anda tidak dapat menghapus akun ini.'
            );
        }

        \App\Models\PppoeCustomer::query()
            ->where('agent_id', $user->id)
            ->update(['agent_id' => null]);

        $user->deleteAvatarFile();
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Pengguna berhasil dihapus.');
    }

    private function validateUser(Request $request, User $actor, ?User $existing = null): array
    {
        $allowedRoles = $actor->assignableRoles();

        if ($existing?->role) {
            $allowedRoles = array_values(array_unique([
                ...$allowedRoles,
                $existing->role,
            ]));
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($existing?->id),
            ],
            'role' => ['required', Rule::in($allowedRoles)],
            'assigned_customer_ids' => ['nullable', 'array'],
            'assigned_customer_ids.*' => ['integer', 'exists:pppoe_customers,id'],
            'password' => [
                $existing ? 'nullable' : 'required',
                'confirmed',
                Password::defaults(),
            ],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'remove_avatar' => ['sometimes', 'boolean'],
        ]);
    }

    private function storeAvatar(Request $request): ?string
    {
        if (! $request->hasFile('avatar')) {
            return null;
        }

        return '/storage/'.$request->file('avatar')->store('uploads/avatars', 'public');
    }
}
