<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'name'])],
            'role' => ['nullable', 'string', 'max:20'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = User::query()->where('role', '!=', 'admin')->with(['creator', 'editor']);

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

        return $request->ajax()
            ? view('admin.users.partials.results', compact('users'))
            : view('admin.users.index', [
                'users' => $users,
                'roles' => $this->roles(),
            ]);
    }

    public function create(): View
    {
        return view('admin.users.create', ['roles' => $this->roles()]);
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
        User::create($data);

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user): View
    {
        $this->ensureEditable($user);

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => $this->roles(),
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

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->ensureEditable($user);
        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    private function ensureEditable(User $user): void
    {
        abort_if($user->role === 'admin', 404);
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
