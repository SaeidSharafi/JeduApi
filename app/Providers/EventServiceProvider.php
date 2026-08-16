<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Category;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\Seminar;
use App\Models\Setting;
use App\Models\Term;
use App\Observers\CategorySearchIndexObserver;
use App\Observers\InvalidationObserver;
use App\Observers\ProductableAvailabilityObserver;
use App\Observers\SettingObserver;
use App\Observers\TermAvailabilityObserver;
use App\Subscribers\CampaignEventSubscriber;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class EventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Wallet campaign event dispatch is mapped explicitly via a subscriber
        // (deliberate deviation from auto-discovered one-listener-per-event).
        Event::subscribe(CampaignEventSubscriber::class);

        // Get the list of models from our config file
        $modelsToObserve = array_keys(config('cache_invalidation.map', []));

        // Automatically attach our single observer to every model in the list
        foreach ($modelsToObserve as $modelClass) {
            $modelClass::observe(InvalidationObserver::class);
        }

        Setting::observe(SettingObserver::class);
        Category::observe(CategorySearchIndexObserver::class);
        Course::observe(ProductableAvailabilityObserver::class);
        Seminar::observe(ProductableAvailabilityObserver::class);
        DigitalAsset::observe(ProductableAvailabilityObserver::class);
        Term::observe(TermAvailabilityObserver::class);
    }
}
