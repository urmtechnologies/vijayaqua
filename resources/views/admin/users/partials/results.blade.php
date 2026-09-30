<div class="table-responsive">
    <table class="table align-middle mb-0">
        <thead class="table-light"><tr>
            <th>User</th><th>Mobile</th><th>Role</th><th>Salary</th><th>Wallet balance</th><th>Added</th><th class="text-end">Actions</th>
        </tr></thead>
        <tbody>
        @forelse($users as $user)
            <tr>
                <td><span class="va-avatar">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</span><strong>{{ $user->name }}</strong> @include('shared.approval-status', ['record' => $user])
                    <div class="text-muted small">Added by {{ $user->creator?->name ?? 'System' }}@if($user->updated_by) · Edited by {{ $user->editor?->name ?? 'System' }}@endif</div>
                </td>
                <td>{{ $user->mobile }}</td>
                <td><span class="badge badge-label-primary">{{ ucfirst($user->role) }}</span></td>
                <td>₹{{ number_format($user->salary, 2) }}</td>
                @php $balance = $wallets[$user->id]['balance']; @endphp
                <td class="{{ $balance > 0 ? 'text-danger' : 'text-success' }}">{{ $balance < 0 ? '−' : '' }}₹{{ \App\Support\SalaryMath::format(abs($balance)) }}</td>
                <td>{{ $user->created_at->format('d M Y') }}</td>
                <td class="text-end">
                    @if(auth()->user()->role === 'admin')<a href="{{ route('salaries.create', ['employee_id' => $user->id]) }}" class="btn btn-sm btn-outline-primary" aria-label="Wallet for {{ $user->name }}">Wallet</a>@endif
                    @if(\App\Support\Access::canEdit('users', $user))<a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-primary" aria-label="Edit {{ $user->name }}">Edit</a>@endif
                    @if(\App\Support\Access::canDelete('users', $user))<button type="button" class="btn btn-sm btn-outline-danger"
                            data-delete-url="{{ route('users.destroy', $user) }}"
                            data-user-name="{{ $user->name }}" aria-label="Delete {{ $user->name }}">Delete</button>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-5">No users found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@include('shared.pagination', ['paginator' => $users])
