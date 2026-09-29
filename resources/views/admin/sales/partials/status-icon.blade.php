@php
    $draft = $record->is_draft ?? false;
    $approved = ! $draft && $record->approval_status === 'approved';
    $label = $draft ? 'Draft sale' : ($approved ? 'Approved' : 'Pending approval');
    $style = $draft ? 'va-status-draft' : ($approved ? 'va-status-approved' : 'va-status-pending');
    $icon = $draft ? 'ri-draft-line' : ($approved ? 'ri-checkbox-circle-line' : 'ri-time-line');
@endphp
<span class="va-status-icon {{ $style }}" title="{{ $label }}" aria-label="{{ $label }}">
    <i class="{{ $icon }}" aria-hidden="true"></i>
</span>
