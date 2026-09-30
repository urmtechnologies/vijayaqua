<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\{Access, SalaryMath};
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'name'])],
            'role' => ['nullable', 'string', 'max:20'],
            'approval' => ['nullable', Rule::in(['pending', 'approved'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Access::scope(User::query()->where('role', '!=', 'admin'), 'users')->with(['creator', 'editor', 'approver']);
        if ($approval = $filters['approval'] ?? null) $query->where('approval_status', $approval);

        if ($search = trim($filters['search'] ?? '')) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('mobile', 'like', '%'.$search.'%');
            });
        }

        if ($role = $filters['role'] ?? null) {
            $query->where('role', $role);
        }

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('id'),
            'name' => $query->orderBy('name')->orderBy('id'),
            default => $query->orderByDesc('id'),
        };

        $users = $query->paginate(10)->withQueryString();
        $wallets = SalaryMath::wallets($users->pluck('id'));

        return $request->ajax()
            ? view('admin.users.partials.results', compact('users', 'wallets'))
            : view('admin.users.index', [
                'users' => $users, 'wallets' => $wallets,
                'roles' => $this->roles(), 'modules' => config('operations.modules'),
            ]);
    }

    public function create(): View
    {
        return view('admin.users.create', ['roles' => $this->roles(), 'modules' => config('operations.modules')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['role' => $this->normalizeRole($request->input('role'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'regex:/^[0-9]{10}$/', 'unique:users,mobile'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'salary' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'role' => ['required', 'regex:/^[a-z][a-z0-9_-]{1,19}$/', Rule::notIn(['admin'])],
        ]);

        $this->rejectRoleTypo($data['role']);
        $permissions = $this->validatedPermissions($request);
        DB::transaction(function () use ($data, $permissions): void {
            $user = User::create($data);
            if (auth()->user()->role === 'admin') $this->savePermissions($user, $permissions);
        });

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user): View
    {
        $this->ensureEditable($user);

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => $this->roles(), 'modules' => config('operations.modules'),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureEditable($user);
        $request->merge(['role' => $this->normalizeRole($request->input('role'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'regex:/^[0-9]{10}$/', Rule::unique('users', 'mobile')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'salary' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'role' => ['required', 'regex:/^[a-z][a-z0-9_-]{1,19}$/', Rule::notIn(['admin'])],
        ]);

        $this->rejectRoleTypo($data['role']);
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $permissions = $this->validatedPermissions($request);
        $hasPermissions = $request->exists('permissions');
        DB::transaction(function () use ($user, $data, $permissions, $hasPermissions): void {
            $record = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless(Access::canEdit('users', $record), 403);
            $record->update($data);
            if (auth()->user()->role === 'admin' && $hasPermissions) $this->savePermissions($record, $permissions);
        });

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->ensureEditable($user);
        DB::transaction(function () use ($user): void {
            $record = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless(Access::canDelete('users', $record), 403);
            $record->delete();
        }, 3);

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    private function ensureEditable(User $user): void
    {
        abort_if($user->role === 'admin', 404);
        abort_unless(Access::record('users', request()->routeIs('users.destroy') ? 'delete' : 'edit', $user), 403);
    }

    private function validatedPermissions(Request $request): array
    {
        // Staff may create an unprivileged account, but cannot grant themselves or others access.
        if (auth()->user()->role !== 'admin') return [];
        $modules = array_keys(config('operations.modules'));
        return $request->validate([
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['array'],
            'permissions.*.scope' => ['sometimes', Rule::in(['self', 'all'])],
            ...collect($modules)->flatMap(fn ($module) => [
                'permissions.'.$module => ['sometimes', 'array'],
                ...collect(['view', 'create', 'edit', 'delete', 'invoice'])->mapWithKeys(fn ($action) => [
                    'permissions.'.$module.'.'.$action => ['sometimes', 'boolean'],
                ])->all(),
            ])->all(),
        ])['permissions'] ?? [];
    }

    private function savePermissions(User $user, array $permissions): void
    {
        foreach (array_keys(config('operations.modules')) as $module) {
            $values = $permissions[$module] ?? [];
            $user->permissions()->updateOrCreate(['module' => $module], [
                'scope' => $values['scope'] ?? 'self',
                'can_view' => ! empty($values['view']) || ! empty($values['create']) || ! empty($values['edit']) || ! empty($values['delete']) || ! empty($values['invoice']),
                'can_create' => ! in_array($module, ['stock', 'salaries'], true) && ! empty($values['create']),
                'can_edit' => ! in_array($module, ['stock', 'salaries'], true) && ! empty($values['edit']),
                'can_delete' => ! in_array($module, ['stock', 'salaries'], true) && ! empty($values['delete']),
                'can_invoice' => $module === 'sales' && ! empty($values['invoice']),
            ]);
        }
    }

    private function roles(): Collection
    {
        return User::query()->where('role', '!=', 'admin')
            ->distinct()->pluck('role')->push('staff')
            ->filter()->unique()->sort()->values();
    }

    private function normalizeRole(mixed $role): string
    {
        if (! is_string($role)) return '';

        return strtolower(trim(preg_replace('/\s+/', '-', trim($role)), '-'));
    }

    private function rejectRoleTypo(string $role): void
    {
        foreach ($this->roles() as $existing) {
            if ($role !== $existing && levenshtein($role, $existing) === 1) {
                throw ValidationException::withMessages([
                    'role' => "Did you mean '{$existing}'? Select the suggested role to avoid a typo.",
                ]);
            }
        }
    }
}
