<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'userCount' => User::where('role', '!=', 'admin')->count(),
            'monthlySalary' => User::where('role', '!=', 'admin')->sum('salary'),
            'recentUsers' => User::where('role', '!=', 'admin')->latest()->limit(5)->get(),
        ]);
    }
}
