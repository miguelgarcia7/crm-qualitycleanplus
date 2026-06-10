<?php

use App\Domain\KnowledgeBase\Enums\KbArticleStatus;
use App\Domain\KnowledgeBase\Models\KbArticle;
use App\Domain\KnowledgeBase\Models\KbCategory;
use App\Domain\KnowledgeBase\Models\KbTag;
use App\Domain\Shared\Enums\FeedbackType;
use App\Domain\Shared\Models\Feedback;
use App\Domain\Shared\Models\File;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/** @return array<string, mixed> */
function kbArticlePayload(KbCategory $category, array $overrides = []): array
{
    return array_merge([
        'title' => 'How to Clock In',
        'summary' => 'Scan the QR code at the property entrance.',
        'content' => '<p>Open the camera, scan the code, take the selfie.</p>',
        'categories' => [$category->id],
    ], $overrides);
}

it('renders the reader hub for staff with kb access', function () {
    KbArticle::factory()->published()->featured()->create();

    $this->actingAs(person('front_desk'))->get(main('/admin/kb'))->assertOk();
});

it('forbids the reader hub without the view permission', function () {
    $this->actingAs(person('property_manager'))->get(main('/admin/kb'))->assertForbidden();
});

it('gates role-restricted articles to matching roles', function () {
    $general = KbArticle::factory()->published()->create();
    $contractorOnly = KbArticle::factory()->published()->create();
    $contractorOnly->roles()->attach(Role::findByName('contractor')->id);

    $frontDesk = person('front_desk');
    $contractor = person('contractor');
    $superAdmin = person('super_admin');

    expect(KbArticle::query()->visibleTo($frontDesk)->pluck('id')->all())->toBe([$general->id])
        ->and(KbArticle::query()->visibleTo($contractor)->pluck('id')->all())->toContain($contractorOnly->id)
        ->and(KbArticle::query()->visibleTo($superAdmin)->count())->toBe(2);

    $this->actingAs($frontDesk)->get(main("/admin/kb/article/{$general->slug}"))->assertOk();
    $this->actingAs($frontDesk)->get(main("/admin/kb/article/{$contractorOnly->slug}"))->assertForbidden();
});

it('hides drafts and archived articles from readers', function () {
    $draft = KbArticle::factory()->create();
    $archived = KbArticle::factory()->archived()->create();

    $reader = person('front_desk');
    $this->actingAs($reader)->get(main("/admin/kb/article/{$draft->slug}"))->assertForbidden();
    $this->actingAs($reader)->get(main("/admin/kb/article/{$archived->slug}"))->assertForbidden();
});

it('lets hr create a draft with a generated slug and auto-created tags', function () {
    $category = KbCategory::factory()->create();

    $this->actingAs(person('hr'))
        ->post(main('/admin/kb/articles'), kbArticlePayload($category, ['tags' => ['Time Clock', 'Safety']]))
        ->assertRedirect();

    $article = KbArticle::query()->firstOrFail();
    expect($article->status)->toBe(KbArticleStatus::Draft)
        ->and($article->slug)->toBe('how-to-clock-in')
        ->and($article->categories()->pluck('kb_categories.id')->all())->toBe([$category->id])
        ->and($article->tags()->pluck('name')->sort()->values()->all())->toBe(['Safety', 'Time Clock'])
        ->and(KbTag::query()->count())->toBe(2);
});

it('uniquifies the slug when titles collide', function () {
    $category = KbCategory::factory()->create();
    $hr = person('hr');

    $this->actingAs($hr)->post(main('/admin/kb/articles'), kbArticlePayload($category));
    $this->actingAs($hr)->post(main('/admin/kb/articles'), kbArticlePayload($category));

    expect(KbArticle::query()->orderBy('id')->pluck('slug')->all())->toBe(['how-to-clock-in', 'how-to-clock-in-2']);
});

it('snapshots a version on update and keeps the slug stable', function () {
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->create(['title' => 'Original', 'slug' => 'original', 'content' => '<p>Old body</p>']);
    $hr = person('hr');

    $this->actingAs($hr)->put(main("/admin/kb/articles/{$article->slug}"), kbArticlePayload($category, [
        'title' => 'Renamed Guide',
        'content' => '<p>New body</p>',
        'change_summary' => 'Rewrote the steps',
    ]))->assertRedirect();

    $article->refresh();
    expect($article->version)->toBe(2)
        ->and($article->slug)->toBe('original')
        ->and($article->title)->toBe('Renamed Guide')
        ->and($article->last_edited_by)->toBe($hr->id);

    $snapshot = $article->versions()->firstOrFail();
    expect($snapshot->version)->toBe(1)
        ->and($snapshot->title)->toBe('Original')
        ->and($snapshot->content)->toBe('<p>Old body</p>')
        ->and($snapshot->change_summary)->toBe('Rewrote the steps');
});

it('blocks editing an archived article', function () {
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->archived()->create();

    $this->actingAs(person('hr'))
        ->put(main("/admin/kb/articles/{$article->slug}"), kbArticlePayload($category))
        ->assertForbidden();
});

it('keeps the original published_at across unpublish and republish', function () {
    $admin = person('admin');
    $article = KbArticle::factory()->create();

    $this->actingAs($admin)->post(main("/admin/kb/articles/{$article->slug}/publish"))->assertRedirect();
    $original = $article->fresh()->published_at;

    $this->actingAs($admin)->post(main("/admin/kb/articles/{$article->slug}/unpublish"));
    $this->travel(2)->days();
    $this->actingAs($admin)->post(main("/admin/kb/articles/{$article->slug}/publish"));

    expect($article->fresh()->published_at?->toDateTimeString())->toBe($original?->toDateTimeString());
});

it('lets admin publish but not hr', function () {
    $article = KbArticle::factory()->create();

    $this->actingAs(person('hr'))->post(main("/admin/kb/articles/{$article->slug}/publish"))->assertForbidden();
    $this->actingAs(person('admin'))->post(main("/admin/kb/articles/{$article->slug}/publish"))->assertRedirect();

    expect($article->fresh()->status)->toBe(KbArticleStatus::Published);
});

it('only lets publishers manage role visibility', function () {
    $category = KbCategory::factory()->create();
    $article = KbArticle::factory()->create();
    $roleId = Role::findByName('contractor')->id;

    $this->actingAs(person('hr'))
        ->put(main("/admin/kb/articles/{$article->slug}"), kbArticlePayload($category, ['roles' => [$roleId]]));
    expect($article->fresh()->roles()->count())->toBe(0);

    $this->actingAs(person('office_manager'))
        ->put(main("/admin/kb/articles/{$article->slug}"), kbArticlePayload($category, ['roles' => [$roleId]]));
    expect($article->fresh()->roles()->pluck('roles.id')->all())->toBe([$roleId]);
});

it('requires at least one category', function () {
    $this->actingAs(person('hr'))
        ->post(main('/admin/kb/articles'), ['title' => 'No Category', 'content' => '<p>x</p>'])
        ->assertSessionHasErrors(['categories']);
});

it('blocks deleting a category that still has articles', function () {
    $category = KbCategory::factory()->create();
    $empty = KbCategory::factory()->create();
    KbArticle::factory()->create()->categories()->attach($category->id);

    $manager = person('office_manager');
    $this->actingAs($manager)->delete(main("/admin/kb/categories/{$category->slug}"))->assertRedirect();
    expect(KbCategory::query()->whereKey($category->id)->exists())->toBeTrue();

    $this->actingAs($manager)->delete(main("/admin/kb/categories/{$empty->slug}"))->assertRedirect();
    expect(KbCategory::query()->whereKey($empty->id)->exists())->toBeFalse();
});

it('searches only published articles visible to the reader', function () {
    KbArticle::factory()->published()->create(['title' => 'Uniform Care Basics']);
    KbArticle::factory()->create(['title' => 'Uniform Draft Notes']);
    $gated = KbArticle::factory()->published()->create(['title' => 'Uniform Manager Playbook']);
    $gated->roles()->attach(Role::findByName('contractor')->id);

    $this->actingAs(person('front_desk'))
        ->get(main('/admin/kb/search').'?q=Uniform')
        ->assertOk()
        ->assertSee('Uniform Care Basics', false)
        ->assertDontSee('Uniform Draft Notes', false)
        ->assertDontSee('Uniform Manager Playbook', false);
});

it('suggests matching articles as json', function () {
    $article = KbArticle::factory()->published()->create(['title' => 'Lost Badge Procedure']);

    $this->actingAs(person('front_desk'))
        ->get(main('/admin/kb/suggest').'?q=Badge')
        ->assertOk()
        ->assertJsonFragment(['slug' => $article->slug]);
});

it('records one vote per person and lets it flip', function () {
    $article = KbArticle::factory()->published()->create();
    $reader = person('front_desk');

    $this->actingAs($reader)->post(main("/admin/kb/article/{$article->slug}/feedback"), ['type' => 'helpful'])->assertRedirect();
    expect($article->feedback()->count())->toBe(1);

    $this->actingAs($reader)->post(main("/admin/kb/article/{$article->slug}/feedback"), ['type' => 'helpful'])
        ->assertSessionHasErrors(['feedback']);
    expect($article->feedback()->count())->toBe(1);

    $this->actingAs($reader)->post(main("/admin/kb/article/{$article->slug}/feedback"), ['type' => 'not_helpful'])->assertRedirect();
    $vote = $article->feedback()->firstOrFail();
    expect($article->feedback()->count())->toBe(1)
        ->and($vote->type)->toBe(FeedbackType::NotHelpful);
});

it('requires a message for detailed feedback', function () {
    $article = KbArticle::factory()->published()->create();
    $reader = person('front_desk');

    $this->actingAs($reader)->post(main("/admin/kb/article/{$article->slug}/feedback"), ['type' => 'suggestion'])
        ->assertSessionHasErrors(['message']);

    $this->actingAs($reader)->post(main("/admin/kb/article/{$article->slug}/feedback"), [
        'type' => 'issue',
        'message' => 'The selfie step is outdated.',
    ])->assertRedirect();

    $entry = Feedback::query()->firstOrFail();
    expect($entry->type)->toBe(FeedbackType::Issue)
        ->and($entry->is_resolved)->toBeFalse();
});

it('lets a manager resolve feedback', function () {
    $entry = Feedback::factory()->suggestion()->create();
    $manager = person('office_manager');

    $this->actingAs($manager)->put(main("/admin/kb/feedback/{$entry->id}"), [
        'is_resolved' => true,
        'admin_notes' => 'Updated the article.',
    ])->assertRedirect();

    $entry->refresh();
    expect($entry->is_resolved)->toBeTrue()
        ->and($entry->resolved_by)->toBe($manager->id)
        ->and($entry->admin_notes)->toBe('Updated the article.');
});

it('increments the view count when an article is read', function () {
    $article = KbArticle::factory()->published()->create();

    $this->actingAs(person('front_desk'))->get(main("/admin/kb/article/{$article->slug}"))->assertOk();

    expect($article->fresh()->view_count)->toBe(1);
});

it('restricts the manage surface to editors', function () {
    $this->actingAs(person('front_desk'))->get(main('/admin/kb/articles'))->assertForbidden();
    $this->actingAs(person('hr'))->get(main('/admin/kb/articles'))->assertOk();
});

it('serves the simplified contractor reader on qc minute', function () {
    $general = KbArticle::factory()->published()->create(['title' => 'Paycheck Schedule']);
    $contractorOnly = KbArticle::factory()->published()->create(['title' => 'Clocking In At Your Hotel']);
    $contractorOnly->roles()->attach(Role::findByName('contractor')->id);
    $staffOnly = KbArticle::factory()->published()->create(['title' => 'Office Backup Procedures']);
    $staffOnly->roles()->attach(Role::findByName('w2_employee')->id);

    $contractor = person('contractor');

    $this->actingAs($contractor)->get(qcminute('/kb'))
        ->assertOk()
        ->assertSee('Paycheck Schedule', false)
        ->assertSee('Clocking In At Your Hotel', false)
        ->assertDontSee('Office Backup Procedures', false);

    $this->actingAs($contractor)->get(qcminute("/kb/{$contractorOnly->slug}"))->assertOk();
    $this->actingAs($contractor)->get(qcminute("/kb/{$staffOnly->slug}"))->assertForbidden();

    $this->actingAs($contractor)->post(qcminute("/kb/{$general->slug}/feedback"), ['type' => 'helpful'])->assertRedirect();
    expect($general->feedback()->count())->toBe(1);

    $this->actingAs(person('property_manager'))->get(qcminute('/kb'))->assertForbidden();
});

it('uploads, downloads, and gates attachments', function () {
    Storage::fake(config('filesystems.default'));

    $article = KbArticle::factory()->published()->create();

    $this->actingAs(person('hr'))->post(main("/admin/kb/articles/{$article->slug}/attachments"), [
        'attachment' => UploadedFile::fake()->create('guide.pdf', 120, 'application/pdf'),
    ])->assertRedirect();

    $file = File::query()->firstOrFail();
    expect($file->fileable_id)->toBe($article->id)
        ->and($file->original_name)->toBe('guide.pdf');

    $this->actingAs(person('front_desk'))->get(main("/admin/kb/attachments/{$file->id}"))->assertOk();

    $article->roles()->attach(Role::findByName('contractor')->id);
    $this->actingAs(person('front_desk'))->get(main("/admin/kb/attachments/{$file->id}"))->assertForbidden();
});

it('lets office managers delete articles but not hr', function () {
    $article = KbArticle::factory()->create();

    $this->actingAs(person('hr'))->delete(main("/admin/kb/articles/{$article->slug}"))->assertForbidden();
    $this->actingAs(person('office_manager'))->delete(main("/admin/kb/articles/{$article->slug}"))->assertRedirect();

    $this->assertSoftDeleted('kb_articles', ['id' => $article->id]);
});
