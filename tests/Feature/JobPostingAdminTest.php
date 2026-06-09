<?php

use App\Domain\Recruiting\Enums\JobPostingStatus;
use App\Domain\Recruiting\Models\JobApplication;
use App\Domain\Recruiting\Models\JobPosting;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('lets a recruiter create, publish, and close a posting', function () {
    $recruiter = person('recruiter');

    $this->actingAs($recruiter)->post(main('/admin/job-postings'), [
        'title' => 'Housekeeper',
        'pay_range' => '$16 - $18 / hr',
        'location_label' => 'Phoenix, AZ',
    ])->assertRedirect();

    $posting = JobPosting::query()->firstOrFail();
    expect($posting->status)->toBe(JobPostingStatus::Draft)
        ->and($posting->slug)->toBe('housekeeper');

    $this->actingAs($recruiter)->post(main("/admin/job-postings/{$posting->slug}/publish"))->assertRedirect();
    expect($posting->fresh()->status)->toBe(JobPostingStatus::Published);

    $this->actingAs($recruiter)->post(main("/admin/job-postings/{$posting->slug}/close"))->assertRedirect();
    expect($posting->fresh()->status)->toBe(JobPostingStatus::Closed);
});

it('uniquifies the slug when titles collide', function () {
    $recruiter = person('recruiter');

    $this->actingAs($recruiter)->post(main('/admin/job-postings'), ['title' => 'Housekeeper']);
    $this->actingAs($recruiter)->post(main('/admin/job-postings'), ['title' => 'Housekeeper']);

    expect(JobPosting::query()->pluck('slug')->all())->toBe(['housekeeper', 'housekeeper-2']);
});

it('keeps the slug stable when the title is edited', function () {
    $recruiter = person('recruiter');
    $posting = JobPosting::factory()->create(['title' => 'Houseman', 'slug' => 'houseman']);

    $this->actingAs($recruiter)->put(main("/admin/job-postings/{$posting->slug}"), [
        'title' => 'Senior Houseman',
    ])->assertRedirect();

    expect($posting->fresh()->only(['title', 'slug']))->toBe(['title' => 'Senior Houseman', 'slug' => 'houseman']);
});

it('blocks deleting a posting that has applications', function () {
    $recruiter = person('recruiter');
    $posting = JobPosting::factory()->published()->create();
    JobApplication::factory()->create(['job_posting_id' => $posting->id]);

    $this->actingAs($recruiter)->delete(main("/admin/job-postings/{$posting->slug}"))->assertRedirect();
    expect(JobPosting::query()->whereKey($posting->id)->exists())->toBeTrue();

    $empty = JobPosting::factory()->create();
    $this->actingAs($recruiter)->delete(main("/admin/job-postings/{$empty->slug}"))->assertRedirect();
    expect(JobPosting::query()->whereKey($empty->id)->exists())->toBeFalse();
});

it('forbids roles without job_postings.manage', function () {
    $payroll = person('payroll');

    $this->actingAs($payroll)->get(main('/admin/job-postings'))->assertForbidden();
    $this->actingAs($payroll)->post(main('/admin/job-postings'), ['title' => 'Nope'])->assertForbidden();
});
