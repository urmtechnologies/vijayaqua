<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Access;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $canSeeUsers = Access::allowed('users');
        $query = $canSeeUsers ? Access::scope(User::where('role', '!=', 'admin'), 'users') : User::whereRaw('1 = 0');
        return view('admin.dashboard', [
            'canSeeUsers' => $canSeeUsers,
            'userCount' => (clone $query)->count(),
            'monthlySalary' => (clone $query)->sum('salary'),
            'recentUsers' => (clone $query)->latest()->limit(5)->get(),
        ]);
    }
}
