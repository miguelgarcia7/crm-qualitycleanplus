<?php

namespace App\Domain\Marketing\Models;

use Database\Factories\ContactInquiryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A lead submitted through the marketing site's contact forms (Phase 08b-i),
 * either a "Job Seekers" general inquiry or a "Business" staffing/services inquiry
 * (`type`). Stored for auditability; not wired to notifications yet.
 */
class ContactInquiry extends Model
{
    /** @use HasFactory<ContactInquiryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'first_name',
        'last_name',
        'email',
        'phone',
        'company',
        'address',
        'city',
        'state',
        'zip',
        'inquiry_type',
        'call_back_time',
        'message',
    ];
}
