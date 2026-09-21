<?php

declare(strict_types=1);

use App\Console\Commands\Cache\ListCacheKeysCommand;

covers(ListCacheKeysCommand::class);

it('lists every registry case with its template, durations and group', function (): void {
    $this->artisan('cache:keys')
        ->expectsTable(
            ['Key', 'Template', 'TTL', 'Stale TTL', 'Group'],
            [
                ['HomePageContent', 'shop.homepage.content', '3600', 'none', 'home_page'],
                ['Slider', 'shop.homepage.sliders', '300', '900', 'home_page'],
                ['PartnersInHome', 'shop.homepage.partners', '300', '900', 'home_page'],
                ['StudentStory', 'shop.homepage.student-stories:{hash}', '300', '900', 'home_page'],
                ['PartnersInCourse', 'shop.course.partners', '300', '900', 'content'],
                ['Partners', 'shop.partners', '300', '900', 'content'],
                ['StudentQuizzes', 'student_quizzes:{userId}', '300', '900', 'content'],
                ['TeacherQuizzes', 'teacher_quizzes:{userId}', '300', '900', 'content'],
                ['GoodForStart', 'shop.category.{slug}.good-for-start.courses-{limit}', '1800', 'none', 'catalog'],
                ['Search', 'search:{hash}', '300', '900', 'search'],
                ['SearchSuggest', 'search:suggest:{hash}', '3600', '14400', 'search'],
                ['PgroongaEnabled', 'database.pgroonga_enabled', 'forever', 'none', 'search'],
                ['DiscountHandlers', 'discounts.handler_registry.cache', 'forever', 'none', 'discounts'],
                ['Settings', 'settings.all', 'forever', 'none', 'settings'],
                ['DigipayAccessToken', 'digipay_access_token', '3300', 'none', 'settings'],
                ['AccessToken', 'AccessToken::{hash}', '360', 'none', 'auth'],
                ['Tokenable', 'token_{id}::id_{env}', '360', 'none', 'auth'],
                ['UserProfile', 'user.{id}.profile', '86400', 'none', 'auth'],
                ['OtpValue', 'otp_{identifier}_{guard}_value_{type}', '300', 'none', 'auth'],
                ['OtpMarker', 'otp_{identifier}_{guard}_created_{type}', '900', 'none', 'auth'],
                ['OtpAttempts', 'otp_{identifier}_{guard}_verify_attempts_{type}', '300', 'none', 'auth'],
            ],
        )
        ->assertExitCode(0);
});
