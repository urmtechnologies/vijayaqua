<div class="table-responsive">
    <table class="table align-middle mb-0">
        <thead class="table-light"><tr>
            <th>User</th><th>Mobile</th><th>Role</th><th>Salary</th><th>Added</th><th class="text-end">Actions</th>
        </tr></thead>
        <tbody>
        @forelse($users as $user)
            <tr>
                <td><span class="va-avatar">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</span><strong>{{ $user->name }}</strong>
                    <div class="text-muted small">Added by {{ $user->creator?->name ?? 'System' }}@if($user->updated_by) · Edited by {{ $user->editor?->name ?? 'System' }}@endif</div>
                </td>
                <td>{{ $user->mobile }}</td>
                <td><span class="badge badge-label-primary">{{ ucfirst($user->role) }}</span></td>
                <td>₹{{ number_format($user->salary, 2) }}</td>
                <td>{{ $user->created_at->format('d M Y') }}</td>
                <td class="text-end">
                    <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-primary" aria-label="Edit {{ $user->name }}">Edit</a>
                    <button type="button" class="btn btn-sm btn-outline-danger"
                            data-delete-url="{{ route('users.destroy', $user) }}"
                            data-user-name="{{ $user->name }}" aria-label="Delete {{ $user->name }}">Delete</button>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-5">No users found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@include('shared.pagination', ['paginator' => $users])
