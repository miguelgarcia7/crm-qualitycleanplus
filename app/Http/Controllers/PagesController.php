<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class PagesController extends Controller
{

    public function empty()
    {
        return Inertia::render('admin/pages/empty/index');
    }
}