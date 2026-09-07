<?php

namespace App\Http\Controllers\Settings;

use App\Domain\People\Actions\UpdatePersonAvatar;
use App\Domain\People\Models\Person;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Notifications\NotificationCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        /** @var Person $person */
        $person = $request->user();

        return Inertia::render('settings/profile', [
            'person' => [
                'name' => $person->name,
                'email' => $person->email,
                'phone' => $person->phone,
                'status' => Str::headline($person->status->value),
                'is_active' => $person->status->isActive(),
                'hire_date' => $person->hire_date?->format('M j, Y'),
                'joined' => $person->created_at?->format('M j, Y'),
                'avatar' => $person->avatarUrl(),
            ],
            'roles' => $person->getRoleNames()
                ->map(fn (string $role): string => Str::headline($role))
                ->values()
                ->all(),
            'notificationSettings' => [
                'muted' => $person->muted_notifications ?? [],
                'categories' => array_map(fn (NotificationCategory $category): array => [
                    'value' => $category->value,
                    'label' => $category->label(),
                    'description' => $category->description(),
                    'emails' => $category->sendsEmail(),
                ], NotificationCategory::cases()),
            ],
        ]);
    }

    /**
     * Save which notification categories the user muted.
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'muted' => ['present', 'array'],
            'muted.*' => ['string', Rule::enum(NotificationCategory::class)],
        ]);

        /** @var Person $person */
        $person = $request->user();
        $person->update(['muted_notifications' => array_values(array_unique($validated['muted']))]);

        return $this->backToProfile($request);
    }

    /**
     * The profile page on the surface this request came from.
     *
     * Not to_route(): the same controller serves both surfaces and the named
     * route is the back-office one. Not back() either — that depends on a
     * Referer header the browser is free not to send.
     */
    private function backToProfile(Request $request): RedirectResponse
    {
        return redirect()->to($request->getHost() === config('domains.qcminute')
            ? '/settings/profile'
            : '/admin/settings/profile');
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return $this->backToProfile($request);
    }

    /**
     * Set the user's profile photo.
     */
    public function updateAvatar(Request $request, UpdatePersonAvatar $action): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:5120'],
        ]);

        /** @var Person $person */
        $person = $request->user();
        /** @var UploadedFile $photo */
        $photo = $request->file('avatar');

        $action->handle($person, $photo);

        return $this->backToProfile($request);
    }

    /**
     * Remove the user's profile photo.
     */
    public function destroyAvatar(Request $request, UpdatePersonAvatar $action): RedirectResponse
    {
        /** @var Person $person */
        $person = $request->user();

        $action->remove($person);

        return $this->backToProfile($request);
    }
}
