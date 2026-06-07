<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ErrorController;
use App\Http\Controllers\IconsController;
use App\Http\Controllers\LayoutsController;
use App\Http\Controllers\PagesController;

Route::redirect('/', '/dashboard/ecommerce');

Route::prefix('dashboard')->group(function () {
    Route::get('/ecommerce', [DashboardController::class, 'ecommerce']);
});

Route::prefix('icons')->group(function () {
    Route::get('/tabler', [IconsController::class, 'tabler']);
});

Route::prefix('layouts')->group(function () {
    Route::get('/boxed', [LayoutsController::class, 'boxed']);
    Route::get('/compact', [LayoutsController::class, 'compact']);
    Route::get('/horizontal', [LayoutsController::class, 'horizontal']);
    Route::get('/preloader', [LayoutsController::class, 'preloader']);
    Route::get('/scrollable', [LayoutsController::class, 'scrollable']);
    Route::get('/sidebar-compact', [LayoutsController::class, 'sidebarCompact']);
    Route::get('/sidebar-gradient', [LayoutsController::class, 'sidebarGradient']);
    Route::get('/sidebar-gray', [LayoutsController::class, 'sidebarGray']);
    Route::get('/sidebar-image', [LayoutsController::class, 'sidebarImage']);
    Route::get('/sidebar-light', [LayoutsController::class, 'sidebarLight']);
    Route::get('/sidebar-no-icons', [LayoutsController::class, 'sidebarNoIcons']);
    Route::get('/sidebar-offcanvas', [LayoutsController::class, 'sidebarOffcanvas']);
    Route::get('/sidebar-on-hover', [LayoutsController::class, 'sidebarOnHover']);
    Route::get('/sidebar-with-lines', [LayoutsController::class, 'sidebarWithLines']);
    Route::get('/topbar-dark', [LayoutsController::class, 'topbarDark']);
    Route::get('/topbar-gradient', [LayoutsController::class, 'topbarGradient']);
    Route::get('/topbar-gray', [LayoutsController::class, 'topbarGray']);
});

Route::prefix('pages')->group(function () {
    Route::get('/empty', [PagesController::class, 'empty']);
});

Route::prefix('auth')->group(function () {
    Route::get('/card/delete-account', [AuthController::class, 'cardDeleteAccount']);
    Route::get('/card/lock-screen', [AuthController::class, 'cardLockScreen']);
    Route::get('/card/login-pin', [AuthController::class, 'cardLoginPin']);
    Route::get('/card/new-pass', [AuthController::class, 'cardNewPass']);
    Route::get('/card/reset-pass', [AuthController::class, 'cardResetPass']);
    Route::get('/card/sign-in', [AuthController::class, 'cardSignIn']);
    Route::get('/card/sign-up', [AuthController::class, 'cardSignUp']);
    Route::get('/card/success-mail', [AuthController::class, 'cardSuccessMail']);
    Route::get('/card/two-factor', [AuthController::class, 'cardTwoFactor']);
    Route::get('/delete-account', [AuthController::class, 'deleteAccount']);
    Route::get('/lock-screen', [AuthController::class, 'lockScreen']);
    Route::get('/login-pin', [AuthController::class, 'loginPin']);
    Route::get('/new-pass', [AuthController::class, 'newPass']);
    Route::get('/reset-pass', [AuthController::class, 'resetPass']);
    Route::get('/sign-in', [AuthController::class, 'signIn']);
    Route::get('/sign-up', [AuthController::class, 'signUp']);
    Route::get('/split/delete-account', [AuthController::class, 'splitDeleteAccount']);
    Route::get('/split/lock-screen', [AuthController::class, 'splitLockScreen']);
    Route::get('/split/login-pin', [AuthController::class, 'splitLoginPin']);
    Route::get('/split/new-pass', [AuthController::class, 'splitNewPass']);
    Route::get('/split/reset-pass', [AuthController::class, 'splitResetPass']);
    Route::get('/split/sign-in', [AuthController::class, 'splitSignIn']);
    Route::get('/split/sign-up', [AuthController::class, 'splitSignUp']);
    Route::get('/split/success-mail', [AuthController::class, 'splitSuccessMail']);
    Route::get('/split/two-factor', [AuthController::class, 'splitTwoFactor']);
    Route::get('/success-mail', [AuthController::class, 'successMail']);
    Route::get('/two-factor', [AuthController::class, 'twoFactor']);
});

Route::prefix('error')->group(function () {
    Route::get('/400', [ErrorController::class, 'error400']);
    Route::get('/401', [ErrorController::class, 'error401']);
    Route::get('/403', [ErrorController::class, 'error403']);
    Route::get('/404', [ErrorController::class, 'error404']);
    Route::get('/408', [ErrorController::class, 'error408']);
    Route::get('/500', [ErrorController::class, 'error500']);
    Route::get('/maintenance', [ErrorController::class, 'maintenance']);
});
