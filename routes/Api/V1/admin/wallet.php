<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\Audit\AdminAuditLogController;
use App\Http\Controllers\Api\Admin\Audit\ComplianceReportController;
use App\Http\Controllers\Api\Admin\Audit\SuspiciousActivityController;
use App\Http\Controllers\Api\Admin\WalletCampaign\AdminWalletCampaignController;
use App\Http\Controllers\Api\Admin\WalletCampaign\ManualCampaignAllocationController;

// Wallet Campaign routes
Route::apiResource('wallet-campaigns', AdminWalletCampaignController::class);

// Manual allocation from a manual_allocation campaign (1..100 users)
Route::post('wallet-campaigns/{wallet_campaign}/allocations',
    ManualCampaignAllocationController::class)
    ->name('wallet-campaigns.allocations');

// Audit and Compliance routes
Route::prefix('audit')->name('audit.')->group(function (): void {
    // Admin action logs
    Route::get('admin-actions', [AdminAuditLogController::class, 'index'])
        ->name('admin-actions.index');
    Route::get('admin-actions/{adminActionLog}', [AdminAuditLogController::class, 'show'])
        ->name('admin-actions.show');

    // Compliance reporting
    Route::post('compliance-report', ComplianceReportController::class)
        ->name('compliance-report');

    // Suspicious activity detection
    Route::post('suspicious-activity', SuspiciousActivityController::class)
        ->name('suspicious-activity');
});
