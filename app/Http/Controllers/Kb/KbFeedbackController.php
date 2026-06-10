<?php

namespace App\Http\Controllers\Kb;

use App\Domain\KnowledgeBase\Models\KbArticle;
use App\Domain\People\Models\Person;
use App\Domain\Shared\Models\Feedback;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * KB feedback queue (Phase 08c) — votes and free-form suggestions/issues/
 * questions left on articles. Admins resolve entries with optional notes.
 * Routes are gated `kb.feedback.manage`.
 */
class KbFeedbackController extends Controller
{
    public function index(): Response
    {
        $entries = Feedback::query()
            ->with(['person:id,name', 'resolver:id,name', 'feedbackable'])
            ->latest('id')
            ->get()
            ->map(function (Feedback $f): array {
                $article = $f->feedbackable instanceof KbArticle ? $f->feedbackable : null;

                return [
                    'id' => $f->id,
                    'type' => $f->type->value,
                    'type_label' => $f->type->label(),
                    'is_vote' => $f->type->isVote(),
                    'message' => $f->message,
                    'person' => $f->person->name ?? 'Anonymous',
                    'article_title' => $article?->title,
                    'article_slug' => $article?->slug,
                    'is_resolved' => $f->is_resolved,
                    'resolved_by' => $f->resolver?->name,
                    'resolved_at' => $f->resolved_at?->format('M j, Y'),
                    'admin_notes' => $f->admin_notes,
                    'created_at' => $f->created_at?->format('M j, Y g:i A'),
                ];
            });

        return Inertia::render('admin/kb/feedback/index', [
            'entries' => $entries,
        ]);
    }

    public function update(Request $request, Feedback $feedback): RedirectResponse
    {
        /** @var Person $actor */
        $actor = $request->user();

        /** @var array{is_resolved: bool, admin_notes: string|null} $validated */
        $validated = $request->validate([
            'is_resolved' => ['required', 'boolean'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['is_resolved']
            ? $feedback->markResolved($actor, $validated['admin_notes'])
            : $feedback->markUnresolved();

        return back()->with('success', $validated['is_resolved'] ? 'Feedback resolved.' : 'Feedback reopened.');
    }

    public function destroy(Feedback $feedback): RedirectResponse
    {
        $feedback->delete();

        return back()->with('success', 'Feedback deleted.');
    }
}
