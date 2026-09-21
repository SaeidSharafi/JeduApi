<?php

declare(strict_types=1);

namespace App\Enums\System;

use InvalidArgumentException;

/**
 * Single registry for every cache key in the application.
 *
 * Each case owns its key template, its lifetime, its additional stale window
 * and the invalidation tag it belongs to. Callers never build a cache key
 * string themselves; they name a case and pass its named parameters.
 *
 * The OTP lifetimes are the business-tunable `config('otp.*')` values; the
 * registry is where they are declared as the cached durations, so operators
 * still tune them without touching call sites.
 *
 * A null ttl means the entry never expires; a null staleTtl means the key must
 * not be read through CacheStore::flexible().
 */
enum CacheKey: string
{
    case Slider             = 'shop.homepage.sliders';
    case PartnersInHome     = 'shop.homepage.partners';
    case StudentStory       = 'shop.homepage.student-stories:{hash}';
    case PartnersInCourse   = 'shop.course.partners';
    case Partners           = 'shop.partners';
    case StudentQuizzes     = 'student_quizzes:{userId}';
    case TeacherQuizzes     = 'teacher_quizzes:{userId}';
    case GoodForStart       = 'shop.category.{slug}.good-for-start.courses-{limit}';
    case Search             = 'search:{hash}';
    case SearchSuggest      = 'search:suggest:{hash}';
    case PgroongaEnabled    = 'database.pgroonga_enabled';
    case DiscountHandlers   = 'discounts.handler_registry.cache';
    case Settings           = 'settings.all';
    case DigipayAccessToken = 'digipay_access_token';
    case AccessToken        = 'AccessToken::{hash}';
    case Tokenable          = 'token_{id}::id_{env}';
    case UserProfile        = 'user.{id}.profile';
    case OtpValue           = 'otp_{identifier}_{guard}_value_{type}';
    case OtpMarker          = 'otp_{identifier}_{guard}_created_{type}';
    case OtpAttempts        = 'otp_{identifier}_{guard}_verify_attempts_{type}';

    /**
     * Substitute the named parameters into the key template.
     *
     * @param  array<string, scalar|null>  $params
     *
     * @throws InvalidArgumentException when the parameters do not fill every placeholder.
     */
    public function resolve(array $params = []): string
    {
        $resolved = $this->value;

        foreach ($params as $placeholder => $value) {
            $resolved = str_replace('{'.$placeholder.'}', (string) $value, $resolved);
        }

        if (preg_match_all('/\{(\w+)\}/', $resolved, $missing) > 0) {
            throw new InvalidArgumentException(sprintf(
                'Cache key [%s] is missing the parameter(s) [%s].',
                $this->name,
                implode(', ', array_unique($missing[1])),
            ));
        }

        return $resolved;
    }

    /**
     * Lifetime of the entry in seconds; null means it never expires.
     */
    public function ttl(): ?int
    {
        return match ($this) {
            self::UserProfile => 86400,
            self::Slider, self::PartnersInHome, self::PartnersInCourse,
            self::Partners, self::StudentStory, self::StudentQuizzes,
            self::TeacherQuizzes, self::Search => 300,
            self::OtpValue                     => (int) config('otp.ttl_seconds', 300),
            self::OtpMarker                    => (int) config('otp.marker_ttl_seconds', 900),
            self::OtpAttempts                  => (int) config('otp.verify_attempt_window_seconds', 300),
            self::SearchSuggest                => 3600,
            self::GoodForStart                 => 1800,
            self::DigipayAccessToken           => 3300,
            self::AccessToken, self::Tokenable => 360,
            self::Settings, self::DiscountHandlers,
            self::PgroongaEnabled => null,
        };
    }

    /**
     * Additional seconds an entry is served after its fresh lifetime and before it expires;
     * null means the key is never read through CacheStore::flexible().
     */
    public function staleTtl(): ?int
    {
        return match ($this) {
            self::Slider, self::PartnersInHome, self::PartnersInCourse,
            self::Partners, self::StudentStory, self::StudentQuizzes,
            self::TeacherQuizzes, self::Search => 900,
            self::SearchSuggest                => 14400,
            default                            => null,
        };
    }

    /**
     * The invalidation tag this key belongs to.
     */
    public function group(): CacheTag
    {
        return match ($this) {
            self::Slider, self::PartnersInHome,
            self::StudentStory => CacheTag::HomePage,
            self::PartnersInCourse, self::Partners, self::StudentQuizzes,
            self::TeacherQuizzes                                     => CacheTag::Content,
            self::GoodForStart                                       => CacheTag::Catalog,
            self::Search, self::SearchSuggest, self::PgroongaEnabled => CacheTag::Search,
            self::DiscountHandlers                                   => CacheTag::Discounts,
            self::Settings, self::DigipayAccessToken                 => CacheTag::Settings,
            self::AccessToken, self::Tokenable,
            self::UserProfile, self::OtpValue, self::OtpMarker, self::OtpAttempts => CacheTag::Auth,
        };
    }
}
