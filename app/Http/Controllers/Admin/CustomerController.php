<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Customer, SalePayment};
use App\Support\{Access, CustomerWallet};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function lookup(Request $request): JsonResponse
    {
        $input = $request->validate(['mobile' => ['required', 'regex:/^[0-9]{10}$/']]);
        $customer = Customer::where('mobile', $input['mobile'])->first();
        if (! $customer || (! Access::allowed('sales') && ! Access::allowed('sales', 'create'))) {
            return response()->json(['found' => false]);
        }
        $sales = Access::scope($customer->sales()->getQuery(), 'sales');
        if (! Access::all('sales') && ! (clone $sales)->exists()) return response()->json(['found' => false]);
        $wallet = CustomerWallet::totals($customer->id);
        return response()->json([
            'found' => true, 'name' => $customer->name,
            'account_url' => route('customers.show', $customer),
            'invoices' => (clone $sales)->where('is_draft', false)->count(),
            'due' => $wallet['balance'], 'recent' => [],
        ]);
    }

    public function show(Customer $customer): View
    {
        $visible = Access::scope($customer->sales()->getQuery(), 'sales');
        abort_unless((clone $visible)->exists(), 404);
        $draft = (clone $visible)->where('is_draft', true)->latest('id')->first();
        $sales = (clone $visible)->where('is_draft', false)
            ->with(['creator', 'referenceUser', 'approver', 'items'])
            ->withSum(['payments as paid_total' => fn ($q) => $q->where('approval_status', 'approved')], 'amount_rupees')
            ->orderByDesc('sale_date')->orderByDesc('id')->paginate(10)->withQueryString();
        $wallet = CustomerWallet::totals($customer->id);
        $payments = SalePayment::query()->whereIn('sale_id', (clone $visible)->where('is_draft', false)->select('sales.id'))
            ->with(['sale', 'creator', 'approver'])->orderByDesc('payment_date')->orderByDesc('id')
            ->paginate(20, ['*'], 'payments_page')->withQueryString();
        $pendingCount = (clone $visible)->where('is_draft', false)->where('approval_status', 'pending')->count();
        return view('admin.customers.show', compact('customer', 'sales', 'payments', 'wallet', 'draft', 'pendingCount'));
    }
}
