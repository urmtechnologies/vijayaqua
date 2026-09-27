<div class="sidebar-left" id="va-sidebar">
    <div class="sidebar-slide h-100">
        <div id="sidebar-menu">
            <ul class="left-menu list-unstyled" id="side-menu">
                <li class="menu-title">Workspace</li>
                <li>
                    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="mdi mdi-view-dashboard-outline"></i><span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'active' : '' }}">
                        <i class="mdi mdi-account-group-outline"></i><span>Users</span>
                    </a>
                </li>
                <li class="menu-title">Modules</li>
                <li class="{{ request()->routeIs('sales.*', 'customers.*', 'payments.*') ? 'mm-active' : '' }}">
                    <a href="#sales-submenu" class="has-arrow va-menu-toggle"
                       aria-expanded="{{ request()->routeIs('sales.*', 'customers.*', 'payments.*') ? 'true' : 'false' }}"
                       aria-controls="sales-submenu">
                        <i class="mdi mdi-cart-outline"></i><span>Sales</span>
                    </a>
                    <ul id="sales-submenu" class="sub-menu mm-collapse {{ request()->routeIs('sales.*', 'customers.*', 'payments.*') ? 'mm-show' : '' }}">
                        <li><a href="{{ route('sales.create') }}" class="{{ request()->routeIs('sales.create') ? 'active' : '' }}">Add Sale</a></li>
                        <li><a href="{{ route('sales.index') }}" class="{{ request()->routeIs('sales.index', 'sales.show', 'customers.show') ? 'active' : '' }}">Sales List</a></li>
                        <li><a href="{{ route('payments.index') }}" class="{{ request()->routeIs('payments.*') ? 'active' : '' }}">Payments</a></li>
                    </ul>
                </li>
                <li><a href="{{ route('upcoming-orders.index') }}" class="{{ request()->routeIs('upcoming-orders.*') ? 'active' : '' }}"><i class="mdi mdi-calendar-clock-outline"></i><span>Upcoming Orders</span></a></li>
                <li><span class="va-nav-pending"><i class="mdi mdi-calendar-check-outline"></i>Attendance <small>Coming soon</small></span></li>
                <li class="{{ request()->routeIs('stock.overview', 'stock-entries.*') ? 'mm-active' : '' }}">
                    <a href="#stock-submenu" class="has-arrow va-menu-toggle"
                       aria-expanded="{{ request()->routeIs('stock.overview', 'stock-entries.*') ? 'true' : 'false' }}"
                       aria-controls="stock-submenu">
                        <i class="mdi mdi-package-variant-closed"></i><span>Stock Management</span>
                    </a>
                    <ul id="stock-submenu" class="sub-menu mm-collapse {{ request()->routeIs('stock.overview', 'stock-entries.*') ? 'mm-show' : '' }}">
                        <li><a href="{{ route('stock.overview') }}" class="{{ request()->routeIs('stock.overview') ? 'active' : '' }}">Stock Overview</a></li>
                        <li>
                            <a href="{{ route('stock-entries.create') }}" class="{{ request()->routeIs('stock-entries.create') ? 'active' : '' }}"
                               @if(request()->routeIs('stock-entries.create')) aria-current="page" @endif>Add Stock Entry</a>
                        </li>
                        <li>
                            <a href="{{ route('stock-entries.index') }}" class="{{ request()->routeIs('stock-entries.index', 'stock-entries.show', 'stock-entries.edit') ? 'active' : '' }}"
                               @if(request()->routeIs('stock-entries.index', 'stock-entries.show', 'stock-entries.edit')) aria-current="page" @endif>Stock Entries</a>
                        </li>
                    </ul>
                </li>
                <li><a href="{{ route('expenses.index') }}" class="{{ request()->routeIs('expenses.*') ? 'active' : '' }}"><i class="mdi mdi-cash-minus"></i><span>Expenses</span></a></li>
                <li><a href="{{ route('partner-ledger.index') }}" class="{{ request()->routeIs('partner-ledger.*', 'partners.*') ? 'active' : '' }}"><i class="mdi mdi-swap-horizontal"></i><span>Partner Status</span></a></li>
                <li><span class="va-nav-pending"><i class="mdi mdi-wallet-outline"></i>Salary <small>Coming soon</small></span></li>
                <li class="menu-title">Settings</li>
                <li>
                    <a href="{{ route('products.index') }}" class="{{ request()->routeIs('products.*') ? 'active' : '' }}">
                        <i class="mdi mdi-package-variant"></i><span>Products Setting</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>
