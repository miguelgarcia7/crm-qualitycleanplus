<?php

namespace App\Domain\Marketing\Models;

use App\Domain\People\Models\Person;
use App\Notifications\ContactInquiryReceived;
use Carbon\CarbonImmutable;
use Database\Factories\ContactInquiryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A lead submitted through the marketing site's contact forms (Phase 08b-i),
 * either a "Job Seekers" general inquiry or a "Business" staffing/services inquiry
 * (`type`). Stored, emailed to that type's recipients via
 * {@see ContactInquiryReceived}, and listed in the back-office inbox
 * (/admin/inquiries), where staff mark each one handled.
 *
 * @property CarbonImmutable|null $handled_at
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
        'locale',
        'spam_check',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'handled_by_id')->withTrashed();
    }

    public function name(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
