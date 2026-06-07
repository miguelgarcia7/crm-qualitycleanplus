<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class IconsController extends Controller
{
    public function tabler()
    {
        return Inertia::render('admin/icons/tabler/index');
    }
}
