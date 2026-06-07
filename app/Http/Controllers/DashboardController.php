<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{

    public function ecommerce()
    {
        return Inertia::render('admin/dashboard/ecommerce/index');
    }
}