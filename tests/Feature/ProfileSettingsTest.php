<?php

use App\Domain\Shared\Models\File;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('renders the profile page', function () {
    $this->actingAs(person('office_manager'))
        ->get(main('/admin/settings/profile'))
        ->assertOk();
});

it('redirects guests to login', function () {
    $this->get(main('/admin/settings/profile'))->assertRedirect();
});

it('updates name and email', function () {
    $user = person('office_manager');

    $this->actingAs($user)
        ->patch(main('/admin/settings/profile'), [
            'name' => 'Dana Updated',
            'email' => 'dana.updated@example.com',
        ])
        ->assertRedirect(main('/admin/settings/profile'))
        ->assertSessionHasNoErrors();

    expect($user->refresh())
        ->name->toBe('Dana Updated')
        ->email->toBe('dana.updated@example.com');
});

it('changes the password when the current password is correct', function () {
    $user = person('office_manager');

    $this->actingAs($user)
        ->put(main('/admin/settings/password'), [
            'current_password' => 'secret',
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('new-secret-123', $user->refresh()->password))->toBeTrue();
});

it('rejects a wrong current password', function () {
    $user = person('office_manager');

    $this->actingAs($user)
        ->from(main('/admin/settings/profile'))
        ->put(main('/admin/settings/password'), [
            'current_password' => 'not-the-password',
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('secret', $user->refresh()->password))->toBeTrue();
});

it('uploads a profile photo and serves it', function () {
    Storage::fake('local');
    $user = person('office_manager');

    $this->actingAs($user)
        ->post(main('/admin/settings/avatar'), ['avatar' => UploadedFile::fake()->image('me.jpg')])
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->avatar_file_id)->not->toBeNull();

    $file = File::query()->findOrFail($user->avatar_file_id);
    Storage::disk('local')->assertExists($file->path);

    $this->get(main("/admin/people/{$user->id}/avatar"))->assertOk();
});

it('replaces the old photo on re-upload', function () {
    Storage::fake('local');
    $user = person('office_manager');

    $this->actingAs($user)->post(main('/admin/settings/avatar'), ['avatar' => UploadedFile::fake()->image('one.jpg')]);
    $firstId = $user->refresh()->avatar_file_id;
    $firstPath = File::query()->findOrFail($firstId)->path;

    $this->actingAs($user)->post(main('/admin/settings/avatar'), ['avatar' => UploadedFile::fake()->image('two.jpg')]);

    expect($user->refresh()->avatar_file_id)->not->toBe($firstId);
    Storage::disk('local')->assertMissing($firstPath);
});

it('rejects non-image avatar uploads', function () {
    Storage::fake('local');

    $this->actingAs(person('office_manager'))
        ->from(main('/admin/settings/profile'))
        ->post(main('/admin/settings/avatar'), ['avatar' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf')])
        ->assertSessionHasErrors('avatar');
});

it('removes the profile photo', function () {
    Storage::fake('local');
    $user = person('office_manager');

    $this->actingAs($user)->post(main('/admin/settings/avatar'), ['avatar' => UploadedFile::fake()->image('me.jpg')]);
    $path = File::query()->findOrFail($user->refresh()->avatar_file_id)->path;

    $this->actingAs($user)->delete(main('/admin/settings/avatar'))->assertSessionHasNoErrors();

    expect($user->refresh()->avatar_file_id)->toBeNull();
    Storage::disk('local')->assertMissing($path);

    $this->get(main("/admin/people/{$user->id}/avatar"))->assertNotFound();
});
