<span class="badge {{ $record->approval_status === 'approved' ? 'bg-success' : 'bg-warning text-dark' }}"
      @if($record->approved_by) title="Approved by {{ $record->approver?->name ?? 'Admin' }} on {{ $record->approved_at ? \Illuminate\Support\Carbon::parse($record->approved_at)->format('d M Y, h:i A') : '' }}" @endif>
    {{ $record->approval_status === 'approved' ? 'Approved' : 'Pending approval' }}
</span>
