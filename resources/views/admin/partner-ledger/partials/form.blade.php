<form method="POST" action="{{ $entry ? route('partner-ledger.update', $entry) : route('partner-ledger.store') }}" data-safe-submit>
    @csrf
    @if($entry) @method('PUT') @endif
    <div class="card">
        <div class="card-header"><h4 class="card-title mb-0">{{ $entry ? 'Edit Transaction' : 'New Partner Transaction' }}</h4></div>
        <div class="card-body row g-3">
            <div class="col-md-6"><label for="partnerName" class="form-label">Partner Name <span class="text-danger">*</span></label>
                <input id="partnerName" name="partner_name" class="form-control @error('partner_name') is-invalid @enderror" list="partnerSuggestions" value="{{ old('partner_name', $selectedPartner?->name) }}" maxlength="150" autocomplete="off" placeholder="Choose or type a name" required>
                <datalist id="partnerSuggestions">@foreach($partners as $partner)<option value="{{ $partner->name }}"></option>@endforeach</datalist>
                @error('partner_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6"><label for="partnerType" class="form-label">Type <span class="text-danger">*</span></label>
                <select id="partnerType" name="type" class="form-select @error('type') is-invalid @enderror" required>
                    <option value="">Select type</option><option value="send" @selected(old('type', $entry?->type) === 'send')>Send</option><option value="receive" @selected(old('type', $entry?->type) === 'receive')>Receive</option>
                </select>
                @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6"><label for="partnerAmount" class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                <input type="number" id="partnerAmount" name="amount_rupees" class="form-control @error('amount_rupees') is-invalid @enderror" min="1" step="1" value="{{ old('amount_rupees', $entry?->amount_rupees) }}" required>
                @error('amount_rupees')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6"><label for="partnerDate" class="form-label">Date <span class="text-danger">*</span></label>
                <input type="date" id="partnerDate" name="transaction_date" class="form-control @error('transaction_date') is-invalid @enderror" value="{{ old('transaction_date', $entry?->transaction_date?->format('Y-m-d') ?? now()->toDateString()) }}" required>
                @error('transaction_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12"><label for="partnerNote" class="form-label">Note</label>
                <textarea id="partnerNote" name="note" class="form-control @error('note') is-invalid @enderror" rows="4" maxlength="3000">{{ old('note', $entry?->note) }}</textarea>
                @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2"><a href="{{ route('partner-ledger.index') }}" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-primary" data-submit-button><span class="spinner-border spinner-border-sm me-1 d-none" data-submit-spinner aria-hidden="true"></span><span data-submit-label>{{ $entry ? 'Save Changes' : 'Record Transaction' }}</span></button>
        </div>
    </div>
</form>
