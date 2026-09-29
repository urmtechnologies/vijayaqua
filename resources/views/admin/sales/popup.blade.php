<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Edit {{ $sale->invoice_no }}</title>
<link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}"><link rel="stylesheet" href="{{ asset('assets/css/remixicon.css') }}"><link rel="stylesheet" href="{{ asset('assets/css/app.min.css') }}"><link rel="stylesheet" href="{{ asset('assets/css/vijay-aqua.css') }}"><link rel="stylesheet" href="{{ asset('assets/css/sales.css') }}"></head><body><main class="p-3 va-sales-shell"><div class="mb-3"><strong>{{ $sale->invoice_no }}</strong> · {{ $sale->customer->name }}</div>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@include('admin.sales.partials.form')
</main><script src="{{ asset('assets/js/sales-form.js') }}" defer></script></body></html>
