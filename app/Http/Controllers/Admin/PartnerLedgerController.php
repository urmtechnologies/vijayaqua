<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Access;
use App\Models\PartnerAccount;
use App\Models\PartnerTransaction;
use App\Support\PartnerBalance;
use App\Support\RupeeAmount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PartnerLedgerController extends Controller
{
    public function index(Request $request): View
    {
        [$entries, $summary] = $this->listing($request, Access::scope(PartnerTransaction::query(), 'partner-ledger'));

        return $request->ajax()
            ? view('admin.partner-ledger.partials.results', compact('entries', 'summary'))
            : view('admin.partner-ledger.index', compact('entries', 'summary'));
    }

    public function account(Request $request, PartnerAccount $partner): View
    {
        $visible = Access::scope($partner->transactions()->getQuery(), 'partner-ledger');
        abort_unless((clone $visible)->exists(), 404);
        [$entries, $summary] = $this->listing($request, $visible);

        return $request->ajax()
            ? view('admin.partner-ledger.partials.results', compact('entries', 'summary'))
            : view('admin.partner-ledger.account', compact('partner', 'entries', 'summary'));
    }

    public function create(Request $request): View
    {
        $partners = PartnerAccount::query();
        if (! Access::all('partner-ledger')) {
            $partners->whereHas('transactions', fn ($q) => $q->where('user_id', auth()->id()));
        }
        return view('admin.partner-ledger.create', [
            'entry' => null, 'partners' => (clone $partners)->orderBy('name')->get(['name']),
            'selectedPartner' => $request->integer('partner') ? (clone $partners)->find($request->integer('partner')) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($data): void {
            $partner = PartnerAccount::createOrFirst(
                ['key' => Str::lower($data['partner_name'])], ['name' => $data['partner_name']]
            );
            PartnerTransaction::create([
                'partner_account_id' => $partner->id,
                'transaction_date' => $data['transaction_date'],
                'type' => $data['type'],
                'amount_rupees' => RupeeAmount::int($data['amount_rupees'], 'amount_rupees'),
                'note' => $data['note'] ?? null,
            ]);
        }, 3);

        return redirect()->route('partner-ledger.index')->with('success', 'Partner transaction recorded.');
    }

    public function edit(PartnerTransaction $transaction): View
    {
        $transaction->load('partner');

        return view('admin.partner-ledger.edit', [
            'entry' => $transaction, 'partners' => Access::all('partner-ledger')
                ? PartnerAccount::orderBy('name')->get(['name'])
                : PartnerAccount::whereHas('transactions', fn ($q) => $q->where('user_id', auth()->id()))->orderBy('name')->get(['name']),
            'selectedPartner' => $transaction->partner,
        ]);
    }

    public function update(Request $request, PartnerTransaction $transaction): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($data, $transaction): void {
            $transaction = PartnerTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            abort_unless(Access::canEdit('partner-ledger', $transaction), 403);
            $partner = PartnerAccount::createOrFirst(
                ['key' => Str::lower($data['partner_name'])], ['name' => $data['partner_name']]
            );
            $transaction->update([
                'partner_account_id' => $partner->id,
                'transaction_date' => $data['transaction_date'],
                'type' => $data['type'],
                'amount_rupees' => RupeeAmount::int($data['amount_rupees'], 'amount_rupees'),
                'note' => $data['note'] ?? null,
            ]);
            $transaction->forceFill(['updated_by' => auth()->id(), 'updated_at' => now()])->save();
        }, 3);

        return redirect()->route('partner-ledger.index')->with('success', 'Partner transaction updated.');
    }

    public function destroy(PartnerTransaction $transaction): RedirectResponse
    {
        DB::transaction(function () use ($transaction): void {
            $record = PartnerTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            abort_unless(Access::canDelete('partner-ledger', $record), 403);
            $record->delete();
        }, 3);

        return back()->with('success', 'Partner transaction removed.');
    }

    private function validated(Request $request): array
    {
        if (is_string($request->input('partner_name'))) {
            $request->merge(['partner_name' => preg_replace('/\s+/u', ' ', trim($request->input('partner_name')))]);
        }

        return $request->validate([
            'partner_name' => ['required', 'string', 'max:150'],
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'type' => ['required', Rule::in(['send', 'receive'])],
            'amount_rupees' => ['required', 'regex:/^[1-9][0-9]{0,15}$/'],
            'note' => ['nullable', 'string', 'max:3000'],
        ]);
    }

    private function listing(Request $request, Builder $query): array
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(['send', 'receive'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest'])],
            'approval' => ['nullable', Rule::in(['pending', 'approved'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        if ($search = trim($filters['search'] ?? '')) {
            $query->where(function ($q) use ($search): void {
                $q->where('note', 'like', '%'.$search.'%')
                    ->orWhereHas('partner', fn ($partner) => $partner->where('name', 'like', '%'.$search.'%'));
            });
        }
        if ($type = $filters['type'] ?? null) $query->where('type', $type);
        if ($approval = $filters['approval'] ?? null) $query->where('approval_status', $approval);
        if ($from = $filters['from'] ?? null) $query->whereDate('transaction_date', '>=', $from);
        if ($to = $filters['to'] ?? null) $query->whereDate('transaction_date', '<=', $to);

        $totals = (clone $query)->toBase()->selectRaw("COUNT(*) AS count, COALESCE(SUM(CASE WHEN approval_status='approved' AND type='send' THEN amount_rupees ELSE 0 END),0) AS sent, COALESCE(SUM(CASE WHEN approval_status='approved' AND type='receive' THEN amount_rupees ELSE 0 END),0) AS received")->first();
        $summary = [
            'count' => $totals->count, 'sent' => (string) $totals->sent,
            'received' => (string) $totals->received,
            'balance' => PartnerBalance::fromTotals((string) $totals->sent, (string) $totals->received),
        ];
        $query->with(['partner', 'creator', 'editor', 'approver']);
        if (($filters['sort'] ?? 'newest') === 'oldest') $query->orderBy('transaction_date')->orderBy('id');
        else $query->orderByDesc('transaction_date')->orderByDesc('id');

        return [$query->paginate(10)->withQueryString(), $summary];
    }
}
