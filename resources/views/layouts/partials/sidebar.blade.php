@php use App\Support\Access; @endphp
<div class="sidebar-left" id="va-sidebar">
    <div class="sidebar-slide h-100"><div id="sidebar-menu"><ul class="left-menu list-unstyled" id="side-menu">
        <li class="menu-title">Workspace</li>
        <li><a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}"><i class="mdi mdi-view-dashboard-outline"></i><span>Dashboard</span></a></li>
        @if(\App\Support\ReportCatalog::visible())<li><a href="{{ route('reports.index') }}" class="{{ request()->routeIs('reports.*') ? 'active' : '' }}"><i class="mdi mdi-chart-box-outline"></i><span>Reports</span></a></li>@endif
        @if(Access::allowed('users'))<li><a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'active' : '' }}"><i class="mdi mdi-account-group-outline"></i><span>Users</span></a></li>@endif
        <li class="menu-title">Modules</li>
        @if(Access::allowed('sales') || Access::allowed('sales', 'create'))
        <li><a href="{{ route('sales.index') }}" class="{{ request()->routeIs('sales.*', 'customers.*', 'payments.*') ? 'active' : '' }}"><i class="ri-shopping-bag-3-line"></i><span>Sales</span></a></li>@endif
        @if(Access::allowed('upcoming-orders'))<li><a href="{{ route('upcoming-orders.index') }}" class="{{ request()->routeIs('upcoming-orders.*') ? 'active' : '' }}"><i class="mdi mdi-calendar-clock-outline"></i><span>Upcoming Orders</span></a></li>@endif
        @if(Access::allowed('attendance'))<li><a href="{{ route('attendance.index') }}" class="{{ request()->routeIs('attendance.*') ? 'active' : '' }}"><i class="mdi mdi-calendar-check-outline"></i><span>Attendance</span></a></li>@endif
        @if(Access::allowed('stock-entries') || Access::allowed('stock-entries', 'create') || (Access::allowed('stock') && Access::all('stock')))
        <li class="{{ request()->routeIs('stock.overview', 'stock-entries.*') ? 'mm-active' : '' }}">
            <a href="#stock-submenu" class="has-arrow va-menu-toggle" aria-expanded="{{ request()->routeIs('stock.overview', 'stock-entries.*') ? 'true' : 'false' }}" aria-controls="stock-submenu"><i class="mdi mdi-package-variant-closed"></i><span>Stock Management</span></a>
            <ul id="stock-submenu" class="sub-menu mm-collapse {{ request()->routeIs('stock.overview', 'stock-entries.*') ? 'mm-show' : '' }}">
                @if(Access::allowed('stock') && Access::all('stock'))<li><a href="{{ route('stock.overview') }}" class="{{ request()->routeIs('stock.overview') ? 'active' : '' }}">Stock Overview</a></li>@endif
                @if(Access::allowed('stock-entries', 'create'))<li><a href="{{ route('stock-entries.create') }}" class="{{ request()->routeIs('stock-entries.create') ? 'active' : '' }}">Add Stock Entry</a></li>@endif
                @if(Access::allowed('stock-entries'))<li><a href="{{ route('stock-entries.index') }}" class="{{ request()->routeIs('stock-entries.index', 'stock-entries.show', 'stock-entries.edit') ? 'active' : '' }}">Stock Entries</a></li>@endif
            </ul>
        </li>@endif
        @if(Access::allowed('expenses'))<li><a href="{{ route('expenses.index') }}" class="{{ request()->routeIs('expenses.*') ? 'active' : '' }}"><i class="mdi mdi-cash-minus"></i><span>Expenses</span></a></li>@endif
        @if(Access::allowed('partner-ledger'))<li><a href="{{ route('partner-ledger.index') }}" class="{{ request()->routeIs('partner-ledger.*', 'partners.*') ? 'active' : '' }}"><i class="mdi mdi-swap-horizontal"></i><span>Partner Status</span></a></li>@endif
        @if(Access::allowed('salaries'))<li><a href="{{ route('salaries.index') }}" class="{{ request()->routeIs('salaries.*') ? 'active' : '' }}"><i class="mdi mdi-wallet-outline"></i><span>Salary</span></a></li>@endif
        @if(auth()->user()->role === 'admin')<li><a href="{{ route('approvals.index') }}" class="{{ request()->routeIs('approvals.*') ? 'active' : '' }}"><i class="mdi mdi-check-circle-outline"></i><span>Approvals</span></a></li>@endif
        <li class="menu-title">Settings</li>
        @if(Access::allowed('products'))<li><a href="{{ route('products.index') }}" class="{{ request()->routeIs('products.*') ? 'active' : '' }}"><i class="mdi mdi-package-variant"></i><span>Products Setting</span></a></li>@endif
        <li><a href="{{ route('password.edit') }}" class="{{ request()->routeIs('password.*') ? 'active' : '' }}"><i class="mdi mdi-lock-outline"></i><span>Change Password</span></a></li>
    </ul></div></div>
</div>
