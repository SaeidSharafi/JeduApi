<?php

declare(strict_types=1);

use App\Console\Commands\Cache\ListCacheKeysCommand;

mutates(ListCacheKeysCommand::class);

it('lists every registry case with its template, durations and group', function (): void {
    $this->artisan('cache:keys')
        ->expectsTable(
            ['Key', 'Template', 'TTL', 'Stale TTL', 'Group'],
            [
                ['Slider', 'shop.homepage.sliders', '300', '900', 'home_page'],
                ['PartnersInHome', 'shop.homepage.partners', '300', '900', 'home_page'],
                ['StudentStory', 'shop.homepage.student-stories:{hash}', '300', '900', 'home_page'],
                ['PartnersInCourse', 'shop.course.partners', '300', '900', 'content'],
                ['Partners', 'shop.partners', '300', '900', 'content'],
                ['StudentQuizzes', 'student_quizzes:{userId}', '300', '900', 'content'],
                ['TeacherQuizzes', 'teacher_quizzes:{userId}', '300', '900', 'content'],
                ['GoodForStart', 'shop.category.{slug}.good-for-start.courses-{limit}', '1800', 'none', 'catalog'],
                ['CataloguePriceBound', 'shop.catalogue.price-bound.{scope}', '1800', '900', 'catalog'],
                ['Search', 'search:{hash}', '300', '900', 'search'],
                ['SearchSuggest', 'search:suggest:{hash}', '3600', '14400', 'search'],
                ['DiscountHandlers', 'discounts.handler_registry.cache', 'forever', 'none', 'discounts'],
                ['Settings', 'settings.all', 'forever', 'none', 'settings'],
                ['DigipayAccessToken', 'digipay_access_token', '3300', 'none', 'settings'],
                ['AccessToken', 'AccessToken::{hash}', '360', 'none', 'auth'],
                ['Tokenable', 'token_{id}::id_{env}', '360', 'none', 'auth'],
                ['OtpValue', 'otp_{identifier}_{guard}_value_{type}', '300', 'none', 'auth'],
                ['OtpMarker', 'otp_{identifier}_{guard}_created_{type}', '900', 'none', 'auth'],
                ['OtpAttempts', 'otp_{identifier}_{guard}_verify_attempts_{type}', '300', 'none', 'auth'],
                ['AdminDashboardSummary', 'admin.dashboard.summary.{period}', '60', 'none', 'dashboard'],
                ['AdminDashboardMoney', 'admin.dashboard.money.{period}', '60', 'none', 'dashboard'],
                ['AdminDashboardLive', 'admin.dashboard.live', '30', 'none', 'dashboard'],
                ['AdminDashboardWallet', 'admin.dashboard.wallet', '300', 'none', 'dashboard'],
                ['AdminDashboardRankings', 'admin.dashboard.rankings.{period}', '300', 'none', 'dashboard'],
            ],
        )
        ->assertExitCode(0);
});
