<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AdminActionLog;
use App\Models\Category;
use App\Models\Partner;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\DiscountPromotion;
use App\Models\HomePageBlock;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Refund;
use App\Models\Review;
use App\Models\Seminar;
use App\Models\Setting;
use App\Models\Slider;
use App\Models\Staff;
use App\Models\StudentStory;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Wallet;
use App\Models\WalletCampaign;
use App\Policies\Admin\AdminActionLogPolicy;
use App\Policies\Admin\CategoryPolicy;
use App\Policies\Admin\PartnerPolicy;
use App\Policies\Admin\CoursePolicy;
use App\Policies\Admin\DigitalAssetPolicy;
use App\Policies\Admin\DiscountPromotionPolicy;
use App\Policies\Admin\HomePageBlockPolicy;
use App\Policies\Admin\OrderPolicy;
use App\Policies\Admin\ProductDeliveryOptionPolicy;
use App\Policies\Admin\ProductPolicy;
use App\Policies\Admin\RefundPolicy;
use App\Policies\Admin\ReviewPolicy;
use App\Policies\Admin\RolePolicy;
use App\Policies\Admin\SeminarPolicy;
use App\Policies\Admin\SettingPolicy;
use App\Policies\Admin\SliderPolicy;
use App\Policies\Admin\StaffPolicy;
use App\Policies\Admin\StudentStoryPolicy;
use App\Policies\Admin\TeacherPolicy;
use App\Policies\Admin\TermPolicy;
use App\Policies\Admin\UserPolicy;
use App\Policies\Admin\VendorPolicy;
use App\Policies\Admin\WalletCampaignPolicy;
use App\Policies\Admin\WalletPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

final class AuthServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::before(function (Staff|User $user, mixed $ability, mixed $arguments) {
            // if doing operaion on Super Admin handle authorization in AdminPolciy
            if (count($arguments) === 1 && $arguments[0] instanceof Staff) {
                return null;
            }

            return ($user instanceof Staff && $user->is_admin) ? true : null;
        });

        Gate::policy(Vendor::class, VendorPolicy::class);
        Gate::policy(Staff::class, StaffPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Course::class, CoursePolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(DigitalAsset::class, DigitalAssetPolicy::class);
        Gate::policy(Seminar::class, SeminarPolicy::class);
        Gate::policy(Teacher::class, TeacherPolicy::class);
        Gate::policy(Term::class, TermPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(ProductDeliveryOption::class, ProductDeliveryOptionPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Refund::class, RefundPolicy::class);
        Gate::policy(DiscountPromotion::class, DiscountPromotionPolicy::class);
        Gate::policy(Wallet::class, WalletPolicy::class);
        Gate::policy(WalletCampaign::class, WalletCampaignPolicy::class);
        Gate::policy(AdminActionLog::class, AdminActionLogPolicy::class);
        Gate::policy(Setting::class, SettingPolicy::class);
        Gate::policy(Review::class, ReviewPolicy::class);
        Gate::policy(Slider::class, SliderPolicy::class);
        Gate::policy(HomePageBlock::class, HomePageBlockPolicy::class);
        Gate::policy(Partner::class, PartnerPolicy::class);
        Gate::policy(StudentStory::class, StudentStoryPolicy::class);

    }
}
