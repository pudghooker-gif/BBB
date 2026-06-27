<?php

namespace VanguardLTE\Http\Controllers\Web\Backend;

use VanguardLTE\B2B\Services\B2BBackofficeDashboardQuery;
use VanguardLTE\Http\Controllers\Controller;

class B2BDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', '2fa', 'only_for_admin']);
        $this->middleware('permission:access.admin.panel');
    }

    public function index(B2BBackofficeDashboardQuery $dashboard)
    {
        return view('backend.b2b.dashboard', $dashboard->snapshot());
    }
}
