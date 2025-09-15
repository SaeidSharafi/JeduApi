<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\Audit\AdminAuditLogController;
use App\Http\Controllers\Api\Admin\Category\CategoryController;
use App\Http\Controllers\Api\Admin\Category\CategoryItemsController;
use App\Http\Controllers\Api\Admin\DiscountPromotionController;
use App\Http\Controllers\Api\Admin\DiscountPromotionStatisticsController;
use App\Http\Controllers\Api\Admin\DiscountPromotionStatusUpdateController;
use App\Http\Controllers\Api\Admin\FileManagement\UploadMediaController;
use App\Http\Controllers\Api\Admin\FileManagement\UploadPrivateController;
use App\Http\Controllers\Api\Admin\FileManagement\ViewMediaController;
use App\Http\Controllers\Api\Admin\FileManagement\ViewPrivateFileController;
use App\Http\Controllers\Api\Admin\NextPaymentDetailsController;
use App\Http\Controllers\Api\Admin\OrderCalculationController;
use App\Http\Controllers\Api\Admin\OrderController;
use App\Http\Controllers\Api\Admin\PrivateFileDownloadController;
use App\Http\Controllers\Api\Admin\Product\ArchiveProductController;
use App\Http\Controllers\Api\Admin\Product\CourseController;
use App\Http\Controllers\Api\Admin\Product\DigitalAssetController;
use App\Http\Controllers\Api\Admin\Product\ProductController;
use App\Http\Controllers\Api\Admin\Product\ProductDeliveryOptionController;
use App\Http\Controllers\Api\Admin\Product\SeminarController;
use App\Http\Controllers\Api\Admin\Review\ApproveReviewController;
use App\Http\Controllers\Api\Admin\Review\RejectReviewController;
use App\Http\Controllers\Api\Admin\Review\UpdateReviewFeaturedStatusController;
use App\Http\Controllers\Api\Admin\Settings\AboutUsInfoController;
use App\Http\Controllers\Api\Admin\Settings\ContactInfoController;
use App\Http\Controllers\Api\Admin\Settings\HomePageBlockController;
use App\Http\Controllers\Api\Admin\Settings\SettingController;
use App\Http\Controllers\Api\Admin\Settings\SliderController;
use App\Http\Controllers\Api\Admin\Settings\StudentStoryController;
use App\Http\Controllers\Api\Admin\Wallet\AdminWalletController;
use App\Http\Controllers\Api\Admin\WalletCampaign\AdminWalletCampaignController;
use App\Http\Controllers\Api\Admin\WalletCampaign\BulkCampaignAllocationController;
use App\Http\Controllers\Api\Admin\WalletCampaign\TriggerCampaignAllocationController;

Route::middleware(['auth:staff', 'admin.audit'])->group(function (): void {
    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::resource('staff', App\Http\Controllers\Api\Admin\StaffController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::resource('role', App\Http\Controllers\Api\Admin\RoleController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::get('permission', App\Http\Controllers\Api\Admin\PermissonController::class)
            ->name('permission.index');
        Route::resource('vendor', App\Http\Controllers\Api\Admin\VendorController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        Route::resource('teacher', App\Http\Controllers\Api\Admin\TeacherController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        Route::post('media/upload', UploadMediaController::class)->name('media.upload');
        Route::get('media/{media}', ViewMediaController::class)->name('media.view');
        Route::post('private-file/upload', UploadPrivateController::class)
            ->name('private-upload.upload');
        Route::get('private-file/{file}', ViewPrivateFileController::class)
            ->name('private-upload.view');
        Route::get('private-file/{file}/download', PrivateFileDownloadController::class)
            ->name('private-upload.download');

        Route::resource('term', App\Http\Controllers\Api\Admin\TermController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        Route::resource('user', App\Http\Controllers\Api\Admin\UserController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        // Product Management and Categories
        Route::resource('category', CategoryController::class)
            ->except(['edit', 'create']);

        // GoodForStart endpoints for category items
        Route::prefix('category/{category}')->name('category.')->group(function () {
            Route::get('items', CategoryItemsController::class)->name('items.index');
            Route::post('good-for-start', [App\Http\Controllers\Api\Admin\Category\GoodForStartController::class, 'set'])
                ->name('good-for-start.set');
        });

        Route::resource('course', CourseController::class)
            ->except(['edit', 'create']);
        Route::resource('digital-asset', DigitalAssetController::class)
            ->except(['edit', 'create']);
        Route::resource('seminar', SeminarController::class)
            ->except(['edit', 'create']);

        Route::resource('product', ProductController::class)
            ->except(['edit', 'create']);
        Route::post('product/{product}/archive', ArchiveProductController::class)
            ->name('product.archive');
        Route::resource('product/{product}/delivery-option', ProductDeliveryOptionController::class)
            ->except(['edit', 'create']);

        // Order and Payment Management
        Route::resource('order', OrderController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::post('order/preview', OrderCalculationController::class)
            ->name('order.preview');
        Route::resource('order/{order}/order-item', App\Http\Controllers\Api\Admin\OrderItemController::class)
            ->only(['index', 'show']);

        Route::resource('order/{order}/payment', App\Http\Controllers\Api\Admin\PaymentController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::get('order/{order}/next-payment-details', NextPaymentDetailsController::class)
            ->name('next-payment-details');

        Route::resource('/order-item/{orderItem}/refund', App\Http\Controllers\Api\Admin\RefundController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::put('refund/{refund}/status', App\Http\Controllers\Api\Admin\RefundUpdateStatusController::class)
            ->name('refund.status');

        // Discount Promotion routes
        Route::resource('discount-promotion', DiscountPromotionController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::put('discount-promotion/{discountPromotion}/status', DiscountPromotionStatusUpdateController::class)
            ->name('discount-promotion.toggle-status');
        Route::get('discount-promotion-statistics', DiscountPromotionStatisticsController::class)
            ->name('discount-promotion.statistics');

        // Discount Info routes (for frontend to get available rules, actions, etc.)
        Route::get('discount-info', [App\Http\Controllers\Api\Admin\DiscountInfoController::class, 'index'])
            ->name('discount-info');
        Route::get('discount-info/conditions', [App\Http\Controllers\Api\Admin\DiscountInfoController::class, 'conditions'])
            ->name('discount-info.conditions');
        Route::get('discount-info/actions', [App\Http\Controllers\Api\Admin\DiscountInfoController::class, 'actions'])
            ->name('discount-info.actions');
        Route::get('discount-info/operators', [App\Http\Controllers\Api\Admin\DiscountInfoController::class, 'operators'])
            ->name('discount-info.operators');
        Route::get('discount-info/types', [App\Http\Controllers\Api\Admin\DiscountInfoController::class, 'types'])
            ->name('discount-info.types');
        Route::prefix('wallet')->name('wallet.')->group(function (): void {
            Route::resource('/', AdminWalletController::class)
                ->only(['index', 'show'])
                ->parameters(['' => 'wallet']);

            Route::post('create', App\Http\Controllers\Api\Admin\Wallet\CreateWalletController::class)->name('create');
            Route::post('deposit/{wallet}', App\Http\Controllers\Api\Admin\Wallet\DepositToWalletController::class)->name('deposit');
            Route::post('withdrawal/{wallet}', App\Http\Controllers\Api\Admin\Wallet\WithdrawFromWalletController::class)->name('withdrawal');
            Route::post('adjustment/{wallet}', App\Http\Controllers\Api\Admin\Wallet\AdjustWalletController::class)->name('adjustment');
        });

        // Wallet Campaign routes
        Route::resource('wallet-campaigns', AdminWalletCampaignController::class);

        // User-centric campaign allocation (primary route)
        Route::post('users/{user}/wallet-campaigns/{wallet_campaign}/trigger-allocation', TriggerCampaignAllocationController::class)
            ->name('users.wallet-campaigns.trigger-allocation');

        // Campaign-centric bulk allocation (secondary route)
        Route::post('wallet-campaigns/{wallet_campaign}/bulk-trigger-allocation', BulkCampaignAllocationController::class)
            ->name('wallet-campaigns.bulk-trigger-allocation');

        // Audit and Compliance routes
        Route::prefix('audit')->name('audit.')->group(function () {
            // Admin action logs
            Route::get('admin-actions', [AdminAuditLogController::class, 'index'])
                ->name('admin-actions.index');
            Route::get('admin-actions/{adminActionLog}', [AdminAuditLogController::class, 'show'])
                ->name('admin-actions.show');

            // Compliance reporting
            Route::post('compliance-report', App\Http\Controllers\Api\Admin\Audit\ComplianceReportController::class)
                ->name('compliance-report');

            // Suspicious activity detection
            Route::post('suspicious-activity', App\Http\Controllers\Api\Admin\Audit\SuspiciousActivityController::class)
                ->name('suspicious-activity');
        });

        // Settings Management
        Route::prefix('settings')->name('settings.')->group(function (): void {
            Route::get('/', [SettingController::class, 'index'])
                ->name('index');
            Route::get('contact-info', [ContactInfoController::class, 'show'])
                ->name('contact-info.show');
            Route::put('contact-info', [ContactInfoController::class, 'update'])
                ->name('contact-info.update');
            Route::get('about-us', [AboutUsInfoController::class, 'show'])
                ->name('about-us.show');
            Route::put('about-us', [AboutUsInfoController::class, 'update'])
                ->name('about-us.update');
            Route::get('footer', [App\Http\Controllers\Api\Admin\Settings\FooterController::class, 'show'])
                ->name('footer.show');
            Route::put('footer', [App\Http\Controllers\Api\Admin\Settings\FooterController::class, 'update'])
                ->name('footer.update');
            Route::get('header', [App\Http\Controllers\Api\Admin\Settings\HeaderController::class, 'show'])
                ->name('header.show');
            Route::put('header', [App\Http\Controllers\Api\Admin\Settings\HeaderController::class, 'update'])
                ->name('header.update');
            Route::resource('slider', SliderController::class)
                ->only(['index', 'show', 'store', 'update', 'destroy']);
            Route::resource('collaboration-carousel', App\Http\Controllers\Api\Admin\Settings\CollaborationCarouselController::class)
                ->only(['index', 'show', 'store', 'update', 'destroy']);
            Route::apiResource('home-page-block', HomePageBlockController::class);

            Route::apiResource('student-stories', StudentStoryController::class);
        });

        Route::resource('review', App\Http\Controllers\Api\Admin\Review\ReviewController::class)
            ->only(['index', 'show', 'destroy']);
        Route::post('review/{review}/approve', ApproveReviewController::class)
            ->name('review.approve');
        Route::post('review/{review}/reject', RejectReviewController::class)
            ->name('review.reject');
        Route::patch('review/{review}/featured', UpdateReviewFeaturedStatusController::class)
            ->name('review.update-featured-status');
    });
});
