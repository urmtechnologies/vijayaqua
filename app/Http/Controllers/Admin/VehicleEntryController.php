<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Product, User, VehicleEntry, VehiclePayment};
use App\Support\{Access, SaleMoney, VehicleWallet};
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VehicleEntryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'approval' => ['nullable', Rule::in(['pending', 'approved'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = Access::scope(VehicleEntry::query(), 'vehicle-entries');
        if ($search = trim($filters['search'] ?? '')) {
            $like = '%'.$search.'%';
            $query->where(fn ($q) => $q->where('from_destination', 'like', $like)
                ->orWhere('to_destination', 'like', $like)
                ->orWhereHas('creator', fn ($person) => $person->where('name', 'like', $like)->orWhere('mobile', 'like', $like))
                ->orWhereHas('referenceUser', fn ($person) => $person->where('name', 'like', $like)->orWhere('mobile', 'like', $like)));
        }
        if ($approval = $filters['approval'] ?? null) $query->where('approval_status', $approval);
        $entries = $query->with(['referenceUser', 'items', 'creator', 'approver'])
            ->orderByRaw("CASE WHEN approval_status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('entry_date')->orderByDesc('id')->paginate(15)->withQueryString();
        return $request->ajax()
            ? view('admin.vehicles.partials.entries', compact('entries'))
            : view('admin.vehicles.index', compact('entries'));
    }

    public function account(Request $request, User $user): View
    {
        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'payments_page' => ['nullable', 'integer', 'min:1'],
        ]);
        $visible = Access::scope(VehicleEntry::query()->where('user_id', $user->id), 'vehicle-entries');
        $canView = Access::all('vehicle-entries') || (int) auth()->id() === (int) $user->id;
        abort_unless($canView && ((clone $visible)->exists() || VehiclePayment::where('account_user_id', $user->id)->exists()), 404);
        $pendingCount = (clone $visible)->where('approval_status', 'pending')->count();
        $entries = $visible->with(['items.product', 'referenceUser', 'creator', 'approver'])
            ->orderByDesc('entry_date')->orderByDesc('id')->paginate(10)->withQueryString();
        $wallet = VehicleWallet::totals($user->id);
        $payments = VehiclePayment::where('account_user_id', $user->id)->with('creator')
            ->orderByDesc('payment_date')->orderByDesc('id')->paginate(20, ['*'], 'payments_page')->withQueryString();
        $data = compact('user', 'entries', 'wallet', 'payments', 'pendingCount');
        return $request->ajax() ? view('admin.vehicles.partials.account-workspace', $data) : view('admin.vehicles.account', $data);
    }

    public function create(Request $request): View
    {
        abort_if($request->user()->role === 'admin', 403);
        $selectedUser = $request->integer('user');
        return view('admin.vehicles.form', [
            'entry' => null, 'staff' => $this->staff(), 'products' => $this->products(),
            'selectedUser' => $selectedUser,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if($request->user()->role === 'admin', 403);
        $data = $this->validated($request);
        $entry = DB::transaction(function () use ($data): VehicleEntry {
            User::whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            $lines = $this->lines($data['items']);
            $entry = VehicleEntry::create([
                'reference_user_id' => $data['reference_user_id'],
                'from_destination' => trim($data['from_destination']),
                'to_destination' => trim($data['to_destination']),
                'entry_date' => $data['entry_date'], 'amount_rupees' => $data['amount_rupees'],
            ]);
            $entry->items()->createMany($lines);
            return $entry;
        }, 3);

        return redirect()->route('vehicle-entries.account', $entry->user_id)
            ->with('success', 'Vehicle entry sent for approval.');
    }

    public function edit(VehicleEntry $vehicleEntry): View
    {
        $vehicleEntry->load('items');
        return view('admin.vehicles.form', [
            'entry' => $vehicleEntry, 'staff' => $this->staff($vehicleEntry->reference_user_id, $vehicleEntry->user_id),
            'products' => $this->products($vehicleEntry), 'selectedUser' => $vehicleEntry->reference_user_id,
        ]);
    }

    public function update(Request $request, VehicleEntry $vehicleEntry): RedirectResponse
    {
        $data = $this->validated($request, $vehicleEntry);
        DB::transaction(function () use ($data, $vehicleEntry): void {
            $entry = VehicleEntry::whereKey($vehicleEntry->id)->lockForUpdate()->firstOrFail();
            abort_unless(Access::canEdit('vehicle-entries', $entry), 403);
            User::withTrashed()->whereKey($entry->user_id)->lockForUpdate()->firstOrFail();
            $old = $entry->items()->pluck('cartons', 'product_id');
            $lines = $this->lines($data['items'], $old);
            if ($entry->approval_status === 'approved') {
                $wallet = VehicleWallet::totals($entry->user_id);
                $oldAmount = SaleMoney::paise($entry->amount_rupees);
                $newAmount = SaleMoney::paise($data['amount_rupees']);
                $chargeAfter = $wallet['charged'] - $oldAmount + $newAmount;
                if ($chargeAfter < $wallet['paid']) {
                    throw ValidationException::withMessages(['amount_rupees' => 'This edit would leave payments greater than approved vehicle charges.']);
                }
            }
            $entry->update([
                'reference_user_id' => $data['reference_user_id'], 'from_destination' => trim($data['from_destination']),
                'to_destination' => trim($data['to_destination']), 'entry_date' => $data['entry_date'],
                'amount_rupees' => $data['amount_rupees'],
            ]);
            $entry->items()->delete();
            $entry->items()->createMany($lines);
        }, 3);

        return redirect()->route('vehicle-entries.account', $vehicleEntry->user_id)->with('success', 'Vehicle entry updated.');
    }

    public function destroy(VehicleEntry $vehicleEntry): RedirectResponse
    {
        $userId = $vehicleEntry->user_id;
        DB::transaction(function () use ($vehicleEntry): void {
            $entry = VehicleEntry::whereKey($vehicleEntry->id)->lockForUpdate()->firstOrFail();
            abort_unless(Access::canDelete('vehicle-entries', $entry), 403);
            User::withTrashed()->whereKey($entry->user_id)->lockForUpdate()->firstOrFail();
            if ($entry->approval_status === 'approved') {
                $wallet = VehicleWallet::totals($entry->user_id);
                if ($wallet['charged'] - SaleMoney::paise($entry->amount_rupees) < $wallet['paid']) {
                    throw ValidationException::withMessages(['entry' => 'Record payments must be reversed before deleting this entry.']);
                }
            }
            $entry->delete();
        }, 3);
        if (! Access::scope(VehicleEntry::where('user_id', $userId), 'vehicle-entries')->exists()
            && ! VehiclePayment::where('account_user_id', $userId)->exists()) {
            return redirect()->route('vehicle-entries.index')->with('success', 'Vehicle entry removed.');
        }
        return redirect()->route('vehicle-entries.account', $userId)->with('success', 'Vehicle entry removed.');
    }

    public function payment(Request $request, User $user): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $data = $request->validate([
            'payment_date' => ['required', 'date_format:Y-m-d'],
            'entry_type' => ['required', Rule::in(['credit', 'debit'])],
            'method' => ['required', Rule::in(['cash', 'upi', 'bank', 'other'])],
            'amount_rupees' => ['required', 'regex:/^(?:0|[1-9][0-9]{0,12})(?:\.[0-9]{1,2})?$/'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $data['amount_rupees'] = $this->positiveAmount($data['amount_rupees']);
        DB::transaction(function () use ($data, $user): void {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (VehiclePayment::whereNull('account_user_id')->whereIn('reference_user_id',
                VehicleEntry::withTrashed()->where('user_id', $user->id)->select('reference_user_id'))->exists()) {
                throw ValidationException::withMessages(['amount_rupees' => 'Finish assigning existing vehicle payments to their account user before recording another payment.']);
            }
            $wallet = VehicleWallet::totals($user->id);
            $amount = SaleMoney::paise($data['amount_rupees']);
            if ($data['entry_type'] === 'credit' && $amount > $wallet['balance']) {
                throw ValidationException::withMessages(['amount_rupees' => 'Payment exceeds the approved wallet balance.']);
            }
            if ($data['entry_type'] === 'debit' && $amount > $wallet['paid']) {
                throw ValidationException::withMessages(['amount_rupees' => 'Debit exceeds the amount already paid.']);
            }
            VehiclePayment::create([...$data, 'account_user_id' => $user->id]);
        }, 3);
        return $request->expectsJson() ? response()->json(['message' => 'Payment recorded.'])
            : back()->with('success', 'Vehicle wallet payment recorded.');
    }

    private function validated(Request $request, ?VehicleEntry $entry = null): array
    {
        $creatorId = $entry?->user_id ?? $request->user()->id;
        $data = $request->validate([
            'reference_user_id' => ['required', 'integer', Rule::notIn(array_unique([$creatorId, $request->user()->id])),
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', '!=', 'admin')
                    ->where(fn ($status) => $status->where('approval_status', 'approved')->when($entry, fn ($legacy) => $legacy->orWhere('id', $entry->reference_user_id)))
                    ->whereNull('deleted_at'))],
            'from_destination' => ['required', 'string', 'max:150'],
            'to_destination' => ['required', 'string', 'max:150'],
            'entry_date' => ['required', 'date_format:Y-m-d'],
            'amount_rupees' => ['required', 'regex:/^(?:0|[1-9][0-9]{0,12})(?:\.[0-9]{1,2})?$/'],
            'items' => ['required', 'array', 'min:1', 'max:40'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')],
            'items.*.cartons' => ['required', 'integer', 'min:1', 'max:1000000000'],
        ]);
        $data['amount_rupees'] = $this->positiveAmount($data['amount_rupees']);
        return $data;
    }

    private function positiveAmount(string $value): string
    {
        $amount = SaleMoney::paise($value, 'amount_rupees');
        if ($amount < 1) throw ValidationException::withMessages(['amount_rupees' => 'Enter an amount greater than zero.']);
        return SaleMoney::decimal($amount);
    }

    private function lines(array $items, $old = null): array
    {
        $old ??= collect();
        $products = Product::withTrashed()->whereIn('id', array_column($items, 'product_id'))
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $lines = [];
        foreach ($items as $index => $item) {
            $product = $products->get($item['product_id']);
            $wasOnEntry = $old->has($item['product_id']);
            if (! $product || (($product->trashed() || $product->status !== 'active' || $product->approval_status !== 'approved') && ! $wasOnEntry)) {
                throw ValidationException::withMessages(["items.$index.product_id" => 'Select an active approved product.']);
            }
            $lines[] = ['product_id' => $product->id, 'product_name' => $product->name, 'cartons' => (int) $item['cartons']];
        }
        return $lines;
    }

    private function staff(?int $include = null, ?int $creatorId = null)
    {
        return User::query()->where('role', '!=', 'admin')
            ->whereNotIn('id', array_unique([$creatorId ?? auth()->id(), auth()->id()]))
            ->where(function ($query) use ($include): void {
                $query->where('approval_status', 'approved')->orWhere('id', $include ?? 0);
            })->orderBy('name')->get(['id', 'name', 'mobile']);
    }

    private function products(?VehicleEntry $entry = null)
    {
        $ids = $entry ? $entry->items()->pluck('product_id') : collect();
        return Product::withTrashed()->where(fn ($q) => $q->where(fn ($active) => $active->where('status', 'active')
            ->where('approval_status', 'approved')->whereNull('deleted_at'))->orWhereIn('id', $ids))
            ->orderBy('name')->get(['id', 'name', 'status', 'deleted_at']);
    }
}
