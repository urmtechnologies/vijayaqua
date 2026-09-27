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
                <li><span class="va-nav-pending"><i class="mdi mdi-cart-outline"></i>Sales <small>Coming soon</small></span></li>
                <li><span class="va-nav-pending"><i class="mdi mdi-calendar-check-outline"></i>Attendance <small>Coming soon</small></span></li>
                <li><span class="va-nav-pending"><i class="mdi mdi-package-variant-closed"></i>Stock Management <small>Coming soon</small></span></li>
                <li><span class="va-nav-pending"><i class="mdi mdi-cash-minus"></i>Expenses <small>Coming soon</small></span></li>
                <li><span class="va-nav-pending"><i class="mdi mdi-wallet-outline"></i>Salary <small>Coming soon</small></span></li>
            </ul>
        </div>
    </div>
</div>
