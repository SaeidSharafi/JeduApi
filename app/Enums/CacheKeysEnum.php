<?php

namespace App\Enums;

enum CacheKeysEnum:string
{
    case HomePageContent = 'shop.homepage.content';
    case UserProfile    = 'user.{id}.profile';
    case StudentStory   = 'shop.homepage.student-stories';
    case Slider         = 'shop.homepage.sliders';
    case PartnersInHome = 'shop.homepage.partners';
    case PartnersInCourse = 'shop.course.partners';
    case Partners = 'shop.partners';
    case Settings = 'settings.all';


    /**
     * Generates the final cache key string by replacing placeholders.
     *
     * @param array<string, scalar> $params Associative array of placeholders and their values.
     * @return string The final, ready-to-use cache key.
     */
    public function key(array $params = []): string
    {
        $key = $this->value;

        if (empty($params)) {
            return $key;
        }

        foreach ($params as $placeholder => $value) {
            $key = str_replace("{{$placeholder}}", (string) $value, $key);
        }

        return $key;
    }

    /**
     * Defines the Time-To-Live (TTL) for each cache key.
     *
     * @return int|DateInterval The cache duration in seconds or a DateInterval object.
     */
    public function ttl(): int|DateInterval
    {
        return match ($this) {
            self::HomePageContent => 3600,
            self::UserProfile    => 86400,
            self::StudentStory, self::Slider,
            self::PartnersInHome, self::PartnersInCourse, self::Partners => 7200,
            default => 0, // Default to no expiration (forever)
        };
    }


}
