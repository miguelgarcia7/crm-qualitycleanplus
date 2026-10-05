<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Error pages for the back office, QC Minute and the sign-in screens — the
 * reference library's error card (design-reference/error/*) rendered as the
 * Inertia `error` page, in place of Laravel's bare pages. The public marketing
 * site has its own (App\Domain\Marketing\Support\SiteErrorPage); JSON and API
 * clients keep their JSON errors.
 *
 * Wired as an exception `respond` hook in bootstrap/app.php: it sees the
 * finished error response and swaps only the statuses below. Returning an
 * Inertia response works for full page loads and Inertia visits alike.
 */
final class AppErrorPage
{
    /** @var list<int> */
    private const STATUSES = [403, 404, 429, 500, 503];

    public static function respond(Response $response, Throwable $e, Request $request): Response
    {
        $status = $response->getStatusCode();

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return $response;
        }

        // An expired CSRF token (a stale sign-in screen, a form left open):
        // back to where they were, which now carries a fresh token.
        if ($status === 419 && $request->hasSession()) {
            return redirect()->back();
        }

        if (! in_array($status, self::STATUSES, true)) {
            return $response;
        }

        // Keep the stack-trace page while debugging.
        if ($status === 500 && config('app.debug')) {
            return $response;
        }

        // The app's bundle, explicitly: an error can follow something that
        // pointed Vite at the marketing bundle (UseMarketingVite).
        Vite::useBuildDirectory('build')->useHotFile(public_path('hot'));

        return Inertia::render('error', [
            'status' => $status,
            // A 403's reason is written by the app for the user ("This account
            // cannot access QC Minute."); other statuses use the page's copy.
            'message' => $status === 403 && $e->getMessage() !== '' && $e->getMessage() !== 'This action is unauthorized.'
                ? $e->getMessage()
                : null,
            'home' => self::home($request),
            // A wrong-door account needs a way out; logout is POST-only.
            'signed_in' => auth()->check(),
        ])->toResponse($request)->setStatusCode($status);
    }

    /** Where "Back to home" goes on this surface. */
    private static function home(Request $request): string
    {
        return $request->getHost() === config('domains.qcminute') ? '/' : '/admin/dashboard';
    }
}
