<?php

use App\Domain\Marketing\Enums\TestimonialSource;
use App\Domain\Marketing\Models\Testimonial;
use App\Domain\Shared\Models\File;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake(config('filesystems.default'));
});

/** @return array<string, mixed> */
function testimonialPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Pablo Fuentes',
        'quote' => 'Great people to work with.',
        'source' => 'google',
        'rating' => 5,
        'is_active' => 1,
    ], $overrides);
}

// --- Back office ---------------------------------------------------------------

it('lists testimonials for someone who manages them', function () {
    Testimonial::factory()->count(2)->create();
    Testimonial::factory()->hidden()->create();

    $this->actingAs(person('office_manager'))->get(main('/admin/testimonials'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/testimonials/index')->has('testimonials', 3)->has('sources', 5));
});

it('keeps testimonials away from roles that do not manage the website', function () {
    $this->actingAs(person('recruiter'))->get(main('/admin/testimonials'))->assertForbidden();
    $this->actingAs(person('recruiter'))->post(main('/admin/testimonials'), testimonialPayload())->assertForbidden();
});

it('creates a testimonial with a photo on the default disk', function () {
    $this->actingAs(person('admin'))
        ->post(main('/admin/testimonials'), testimonialPayload(['photo' => UploadedFile::fake()->image('pablo.jpg')]))
        ->assertSessionHasNoErrors();

    $t = Testimonial::query()->firstOrFail();
    expect($t->name)->toBe('Pablo Fuentes')
        ->and($t->source)->toBe(TestimonialSource::Google)
        ->and($t->is_active)->toBeTrue();
    Storage::disk(config('filesystems.default'))->assertExists($t->photoFile->path);
});

it('validates the form', function () {
    $this->actingAs(person('admin'))
        ->post(main('/admin/testimonials'), testimonialPayload(['name' => '', 'rating' => 9, 'source' => 'myspace', 'photo' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')]))
        ->assertSessionHasErrors(['name', 'rating', 'source', 'photo']);
});

it('updates text, replaces the photo, then removes it', function () {
    $admin = person('admin');
    $this->actingAs($admin)->post(main('/admin/testimonials'), testimonialPayload(['photo' => UploadedFile::fake()->image('a.jpg')]));
    $t = Testimonial::query()->firstOrFail();
    $first = $t->photoFile;

    // Multipart updates arrive as POST + _method=put, as the page sends them.
    $this->actingAs($admin)->post(main("/admin/testimonials/{$t->id}"), testimonialPayload([
        '_method' => 'put', 'quote' => 'Even better the second time.', 'rating' => 4, 'photo' => UploadedFile::fake()->image('b.jpg'),
    ]))->assertSessionHasNoErrors();

    $t->refresh();
    expect($t->quote)->toBe('Even better the second time.')->and($t->rating)->toBe(4)->and($t->photo_file_id)->not->toBe($first->id);
    Storage::disk($first->disk)->assertMissing($first->path);

    $this->actingAs($admin)->put(main("/admin/testimonials/{$t->id}"), testimonialPayload(['remove_photo' => 1]))->assertSessionHasNoErrors();
    expect($t->fresh()->photo_file_id)->toBeNull();
});

it('hides and shows a testimonial from the edit form', function () {
    $t = Testimonial::factory()->create();

    $this->actingAs(person('admin'))->put(main("/admin/testimonials/{$t->id}"), testimonialPayload(['is_active' => 0]))->assertSessionHasNoErrors();
    expect($t->fresh()->is_active)->toBeFalse();

    $this->actingAs(person('admin'))->put(main("/admin/testimonials/{$t->id}"), testimonialPayload(['is_active' => 1]))->assertSessionHasNoErrors();
    expect($t->fresh()->is_active)->toBeTrue();
});

it('deletes a testimonial and its photo', function () {
    $this->actingAs(person('admin'))->post(main('/admin/testimonials'), testimonialPayload(['photo' => UploadedFile::fake()->image('a.jpg')]));
    $t = Testimonial::query()->firstOrFail();
    $file = $t->photoFile;

    $this->actingAs(person('admin'))->delete(main("/admin/testimonials/{$t->id}"))->assertRedirect();

    expect(Testimonial::query()->count())->toBe(0);
    Storage::disk($file->disk)->assertMissing($file->path);
});

// --- Public site ----------------------------------------------------------------

it('shows only active testimonials on the home page, newest first', function () {
    Testimonial::factory()->create(['name' => 'Older Review', 'created_at' => now()->subYear()]);
    Testimonial::factory()->create(['name' => 'Newer Review']);
    Testimonial::factory()->hidden()->create(['name' => 'Hidden Review']);

    $this->get(main('/'))
        ->assertOk()
        ->assertSeeInOrder(['Newer Review', 'Older Review'])
        ->assertDontSee('Hidden Review');
});

it('hides the section when there are no active testimonials', function () {
    Testimonial::factory()->hidden()->create();

    $this->get(main('/'))->assertOk()->assertDontSee('swiper-slide', false);
});

it('serves a photo publicly only while the testimonial is on the site', function () {
    $this->actingAs(person('admin'))->post(main('/admin/testimonials'), testimonialPayload(['photo' => UploadedFile::fake()->image('a.jpg')]));
    $t = Testimonial::query()->firstOrFail();
    auth()->logout();

    $this->get(main($t->photoUrl()))->assertOk()->assertHeader('Cache-Control', 'max-age=604800, public');
    $this->get(main('/'))->assertSee($t->photoUrl(), false);

    $t->update(['is_active' => false]);
    $this->get(main($t->photoUrl()))->assertNotFound();

    // The back office still sees it.
    $this->actingAs(person('admin'))->get(main("/admin/testimonials/{$t->id}/photo"))->assertOk();
});

// --- Legacy import --------------------------------------------------------------

/** A stand-in for the legacy `qualitycleanplus` database and its media folder. */
function legacyTestimonials(array $rows): string
{
    config(['database.connections.legacy_qcp' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    Schema::connection('legacy_qcp')->create('testimonials', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('quote');
        $table->string('photo')->nullable();
        $table->string('social_media');
        $table->unsignedTinyInteger('rating');
        $table->boolean('status')->default(true);
        $table->timestamps();
    });
    DB::connection('legacy_qcp')->table('testimonials')->insert($rows);

    $root = sys_get_temp_dir().'/legacy-qcp-'.uniqid();
    mkdir($root.'/media/2024/04', 0777, true);
    UploadedFile::fake()->image('greg.jpg')->move($root.'/media/2024/04', 'greg.jpg');

    return $root;
}

it('imports legacy testimonials with their photos, and re-runs without duplicating', function () {
    $root = legacyTestimonials([
        ['id' => 11, 'name' => 'Greg Vasquez', 'quote' => 'Great job!', 'photo' => '/media/2024/04/greg.jpg', 'social_media' => 'TikTok', 'rating' => 5, 'status' => 1, 'created_at' => '2024-04-03 19:39:45', 'updated_at' => '2024-04-03 19:39:45'],
        ['id' => 16, 'name' => 'Araceli Herrera', 'quote' => 'Duplicate review', 'photo' => null, 'social_media' => 'Google', 'rating' => 5, 'status' => 0, 'created_at' => '2026-03-04 21:44:33', 'updated_at' => '2026-03-04 21:44:33'],
        ['id' => 2, 'name' => 'Danny Aguilar', 'quote' => 'Helpful staff.', 'photo' => '/media/2024/08/missing.jpg', 'social_media' => 'Google', 'rating' => 5, 'status' => 1, 'created_at' => '2024-03-16 08:45:36', 'updated_at' => '2024-03-16 08:45:36'],
    ]);

    $this->artisan('legacy:import-testimonials', ['--media-root' => $root])
        ->expectsOutputToContain('3 created, 0 updated, 1 photos copied')
        ->expectsOutputToContain('#2 Danny Aguilar: /media/2024/08/missing.jpg')
        ->assertSuccessful();

    $greg = Testimonial::query()->where('name', 'Greg Vasquez')->firstOrFail();
    expect($greg->source)->toBe(TestimonialSource::TikTok)
        ->and($greg->photo_file_id)->not->toBeNull()
        ->and($greg->created_at->toDateTimeString())->toBe('2024-04-03 19:39:45')
        ->and(Testimonial::query()->where('name', 'Araceli Herrera')->firstOrFail()->is_active)->toBeFalse();

    DB::connection('legacy_qcp')->table('testimonials')->where('id', 11)->update(['quote' => 'Great job, again!']);

    $this->artisan('legacy:import-testimonials', ['--media-root' => $root])
        ->expectsOutputToContain('0 created, 3 updated, 0 photos copied')
        ->assertSuccessful();

    expect(Testimonial::query()->count())->toBe(3)
        ->and(File::query()->count())->toBe(1)
        ->and($greg->fresh()->quote)->toBe('Great job, again!');
});
