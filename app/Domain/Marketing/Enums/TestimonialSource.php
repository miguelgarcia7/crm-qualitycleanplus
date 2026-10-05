<?php

namespace App\Domain\Marketing\Enums;

/**
 * Where a testimonial was originally left. The value names the badge icon the
 * home page shows (`/images/icons/social-media-{value}.svg`).
 */
enum TestimonialSource: string
{
    case Google = 'google';
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case TikTok = 'tiktok';
    case Twitter = 'twitter';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::TikTok => 'TikTok',
            self::Twitter => 'X (Twitter)',
        };
    }
}
