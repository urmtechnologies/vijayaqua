<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Product, User, VehicleEntry, VehiclePayment};
use App\Support\{Access, SaleMoney, StockBalance, VehicleWallet};
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VehicleEntryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $visible = Access::scope(VehicleEntry::query(), 'vehicle-entries');
        $people = User::withTrashed()->whereIn('id', (clone $visible)->select('reference_user_id'));
        if ($search = trim($filters['search'] ?? '')) {
            $people->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('mobile', 'like', '%'.$search.'%'));
        }
        $accounts = $people->orderBy('name')->paginate(10)->withQueryString();
        $accounts->getCollection()->each(function (User $person) use ($visible): void {
            $person->setAttribute('wallet', VehicleWallet::totals($person->id));
            $person->setAttribute('entry_count', (clone $visible)->where('reference_user_id', $person->id)->count());
        });
        return $request->ajax()
            ? view('admin.vehicles.partials.accounts', compact('accounts'))
            : view('admin.vehicles.index', compact('accounts'));
    }

    public function account(Request $request, User $user): View
    {
        $visible = Access::scope(VehicleEntry::query()->where('reference_user_id', $user->id), 'vehicle-entries');
        abort_unless((clone $visible)->exists(), 404);
        $entries = $visible->with(['items.product', 'creator', 'approver'])
            ->orderByDesc('entry_date')->orderByDesc('id')->paginate(10)->withQueryString();
        $wallet = VehicleWallet::totals($user->id);
        $payments = VehiclePayment::where('reference_user_id', $user->id)->with('creator')
            ->orderByDesc('payment_date')->orderByDesc('id')->paginate(20, ['*'], 'payments_page')->withQueryString();
        return view('admin.vehicles.account', compact('user', 'entries', 'wallet', 'payments'));
    }

    public function create(Request $request): View
    {
        $selectedUser = $request->integer('user');
        return view('admin.vehicles.form', [
            'entry' => null, 'staff' => $this->staff(), 'products' => $this->products(),
            'selectedUser' => $selectedUser,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $entry = DB::transaction(function () use ($data): VehicleEntry {
            User::whereKey($data['reference_user_id'])->lockForUpdate()->firstOrFail();
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

        return redirect()->route('vehicle-entries.account', $entry->reference_user_id)
            ->with('success', $entry->approval_status === 'pending' ? 'Vehicle entry sent for approval.' : 'Vehicle entry recorded.');
    }

    public function edit(VehicleEntry $vehicleEntry): View
    {
        $vehicleEntry->load('items');
        return view('admin.vehicles.form', [
            'entry' => $vehicleEntry, 'staff' => $this->staff($vehicleEntry->reference_user_id),
            'products' => $this->products($vehicleEntry), 'selectedUser' => $vehicleEntry->reference_user_id,
        ]);
    }

    public function update(Request $request, VehicleEntry $vehicleEntry): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($data, $vehicleEntry): void {
            $entry = VehicleEntry::whereKey($vehicleEntry->id)->lockForUpdate()->firstOrFail();
            abort_unless(Access::canEdit('vehicle-entries', $entry), 403);
            User::whereIn('id', array_unique([$entry->reference_user_id, (int) $data['reference_user_id']]))
                ->orderBy('id')->lockForUpdate()->get();
            $old = $entry->items()->pluck('cartons', 'product_id');
            $lines = $this->lines($data['items'], $entry, $old);
            if ($entry->approval_status === 'approved') {
                $wallet = VehicleWallet::totals($entry->reference_user_id);
                $oldAmount = SaleMoney::paise($entry->amount_rupees);
                $newAmount = SaleMoney::paise($data['amount_rupees']);
                $chargeAfter = $wallet['charged'] - $oldAmount
                    + ((int) $data['reference_user_id'] === (int) $entry->reference_user_id ? $newAmount : 0);
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

        return redirect()->route('vehicle-entries.account', $data['reference_user_id'])->with('success', 'Vehicle entry updated.');
    }

    public function destroy(VehicleEntry $vehicleEntry): RedirectResponse
    {
        $userId = $vehicleEntry->reference_user_id;
        DB::transaction(function () use ($vehicleEntry): void {
            $entry = VehicleEntry::whereKey($vehicleEntry->id)->lockForUpdate()->firstOrFail();
            abort_unless(Access::canDelete('vehicle-entries', $entry), 403);
            User::whereKey($entry->reference_user_id)->lockForUpdate()->firstOrFail();
            if ($entry->approval_status === 'approved') {
                $wallet = VehicleWallet::totals($entry->reference_user_id);
                if ($wallet['charged'] - SaleMoney::paise($entry->amount_rupees) < $wallet['paid']) {
                    throw ValidationException::withMessages(['entry' => 'Record payments must be reversed before deleting this entry.']);
                }
            }
            Product::withTrashed()->whereIn('id', $entry->items()->pluck('product_id'))->orderBy('id')->lockForUpdate()->get();
            $entry->delete();
        }, 3);
        return redirect()->route('vehicle-entries.account', $userId)->with('success', 'Vehicle entry removed.');
    }

    public function payment(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $data = $request->validate([
            'payment_date' => ['required', 'date_format:Y-m-d'],
            'entry_type' => ['required', Rule::in(['credit', 'debit'])],
            'method' => ['required', Rule::in(['cash', 'upi', 'bank', 'other'])],
            'amount_rupees' => ['required', 'regex:/^[1-9][0-9]{0,12}(?:\.[0-9]{1,2})?$/'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        DB::transaction(function () use ($data, $user): void {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $wallet = VehicleWallet::totals($user->id);
            $amount = SaleMoney::paise($data['amount_rupees']);
            if ($data['entry_type'] === 'credit' && $amount > $wallet['balance']) {
                throw ValidationException::withMessages(['amount_rupees' => 'Payment exceeds the approved wallet balance.']);
            }
            if ($data['entry_type'] === 'debit' && $amount > $wallet['paid']) {
                throw ValidationException::withMessages(['amount_rupees' => 'Debit exceeds the amount already paid.']);
            }
            VehiclePayment::create([...$data, 'reference_user_id' => $user->id]);
        }, 3);
        return back()->with('success', 'Vehicle wallet payment recorded.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'reference_user_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', '!=', 'admin')->where('approval_status', 'approved')->whereNull('deleted_at'))],
            'from_destination' => ['required', 'string', 'max:150'],
            'to_destination' => ['required', 'string', 'max:150'],
            'entry_date' => ['required', 'date_format:Y-m-d'],
            'amount_rupees' => ['required', 'regex:/^[1-9][0-9]{0,12}(?:\.[0-9]{1,2})?$/'],
            'items' => ['required', 'array', 'min:1', 'max:40'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')],
            'items.*.cartons' => ['required', 'integer', 'min:1', 'max:1000000000'],
        ]);
        $data['amount_rupees'] = SaleMoney::decimal(SaleMoney::paise($data['amount_rupees']));
        return $data;
    }

    private function lines(array $items, ?VehicleEntry $entry = null, $old = null): array
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
            // Pending staff entries do not reserve stock. Only approved dispatches reduce stock.
            if (($entry?->approval_status === 'approved' || (! $entry && auth()->user()->role === 'admin')) && StockBalance::available($product->id)
                + ($entry?->approval_status === 'approved' ? (int) $old->get($product->id, 0) : 0) < $item['cartons']) {
                throw ValidationException::withMessages(["items.$index.cartons" => "Not enough stock for {$product->name}."]);
            }
            $lines[] = ['product_id' => $product->id, 'product_name' => $product->name, 'cartons' => (int) $item['cartons']];
        }
        return $lines;
    }

    private function staff(?int $include = null)
    {
        return User::query()->where('role', '!=', 'admin')->where(function ($query) use ($include): void {
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
