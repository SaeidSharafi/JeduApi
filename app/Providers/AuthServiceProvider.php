<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Category;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Refund;
use App\Models\Seminar;
use App\Models\Staff;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use App\Models\Vendor;
use App\Policies\Admin\CategoryPolicy;
use App\Policies\Admin\CoursePolicy;
use App\Policies\Admin\DigitalAssetPolicy;
use App\Policies\Admin\OrderPolicy;
use App\Policies\Admin\ProductDeliveryOptionPolicy;
use App\Policies\Admin\ProductPolicy;
use App\Policies\Admin\RefundPolicy;
use App\Policies\Admin\RolePolicy;
use App\Policies\Admin\SeminarPolicy;
use App\Policies\Admin\StaffPolicy;
use App\Policies\Admin\TeacherPolicy;
use App\Policies\Admin\TermPolicy;
use App\Policies\Admin\UserPolicy;
use App\Policies\Admin\VendorPolicy;
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
    }
}
