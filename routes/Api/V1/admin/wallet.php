<?php

use App\Http\Controllers\Api\Admin\Audit\AdminAuditLogController;
use App\Http\Controllers\Api\Admin\Audit\ComplianceReportController;
use App\Http\Controllers\Api\Admin\Audit\SuspiciousActivityController;
use App\Http\Controllers\Api\Admin\WalletCampaign\AdminWalletCampaignController;
use App\Http\Controllers\Api\Admin\WalletCampaign\BulkCampaignAllocationController;
use App\Http\Controllers\Api\Admin\WalletCampaign\TriggerCampaignAllocationController;

// Wallet Campaign routes
Route::resource('wallet-campaigns', AdminWalletCampaignController::class);

// User-centric campaign allocation (primary route)
Route::post('users/{user}/wallet-campaigns/{wallet_campaign}/trigger-allocation',
    TriggerCampaignAllocationController::class)
    ->name('users.wallet-campaigns.trigger-allocation');

// Campaign-centric bulk allocation (secondary route)
Route::post('wallet-campaigns/{wallet_campaign}/bulk-trigger-allocation',
    BulkCampaignAllocationController::class)
    ->name('wallet-campaigns.bulk-trigger-allocation');

// Audit and Compliance routes
Route::prefix('audit')->name('audit.')->group(function () {
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
