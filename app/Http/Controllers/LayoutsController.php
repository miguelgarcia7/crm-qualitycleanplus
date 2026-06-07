<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class LayoutsController extends Controller
{

    public function boxed()
    {
        return Inertia::render('admin/layouts/boxed/index');
    }

    public function compact()
    {
        return Inertia::render('admin/layouts/compact/index');
    }

    public function horizontal()
    {
        return Inertia::render('admin/layouts/horizontal/index');
    }

    public function preloader()
    {
        return Inertia::render('admin/layouts/preloader/index');
    }

    public function scrollable()
    {
        return Inertia::render('admin/layouts/scrollable/index');
    }

    public function sidebarCompact()
    {
        return Inertia::render('admin/layouts/sidebar-compact/index');
    }

    public function sidebarGradient()
    {
        return Inertia::render('admin/layouts/sidebar-gradient/index');
    }

    public function sidebarGray()
    {
        return Inertia::render('admin/layouts/sidebar-gray/index');
    }

    public function sidebarImage()
    {
        return Inertia::render('admin/layouts/sidebar-image/index');
    }

    public function sidebarLight()
    {
        return Inertia::render('admin/layouts/sidebar-light/index');
    }

    public function sidebarNoIcons()
    {
        return Inertia::render('admin/layouts/sidebar-no-icons/index');
    }

    public function sidebarOffcanvas()
    {
        return Inertia::render('admin/layouts/sidebar-offcanvas/index');
    }

    public function sidebarOnHover()
    {
        return Inertia::render('admin/layouts/sidebar-on-hover/index');
    }

    public function sidebarWithLines()
    {
        return Inertia::render('admin/layouts/sidebar-with-lines/index');
    }

    public function topbarDark()
    {
        return Inertia::render('admin/layouts/topbar-dark/index');
    }

    public function topbarGradient()
    {
        return Inertia::render('admin/layouts/topbar-gradient/index');
    }

    public function topbarGray()
    {
        return Inertia::render('admin/layouts/topbar-gray/index');
    }
}