<?php

declare(strict_types=1);

use App\Enums\System\CacheKeysEnum;
use App\Services\CacheInvalidationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use SmartCache\Facades\SmartCache;

beforeEach(function (): void {
    $this->service = new CacheInvalidationService();
});

it('invalidates direct keys', function (): void {
    $model              = new class() extends Model {};
    $invalidationConfig = ['key1', 'key2'];

    SmartCache::shouldReceive('forget')->with('key1')->once();
    SmartCache::shouldReceive('forget')->with('key2')->once();

    $this->service->invalidateForModel($model, $invalidationConfig);
});

it('invalidates patterns', function (): void {
    $model              = new class() extends Model {};
    $invalidationConfig = [
        ['type' => 'pattern', 'value' => 'pattern1:*'],
        ['type' => 'pattern', 'value' => 'pattern2:*'],
    ];

    SmartCache::shouldReceive('flushPatterns')->with(['pattern1:*', 'pattern2:*'])->once();

    $this->service->invalidateForModel($model, $invalidationConfig);
});

it('invalidates enum keys', function (): void {
    $model              = new class() extends Model {};
    $invalidationConfig = [CacheKeysEnum::HomePageContent];

    SmartCache::shouldReceive('forget')->with(CacheKeysEnum::HomePageContent->value)->once();

    $this->service->invalidateForModel($model, $invalidationConfig);
});

it('handles mixed configuration', function (): void {
    $model              = new class() extends Model {};
    $invalidationConfig = [
        CacheKeysEnum::HomePageContent,
        'direct.key',
        ['type' => 'pattern', 'value' => 'shop.category.*'],
    ];

    SmartCache::shouldReceive('forget')->with(CacheKeysEnum::HomePageContent->value)->once();
    SmartCache::shouldReceive('forget')->with('direct.key')->once();
    SmartCache::shouldReceive('flushPatterns')->with(['shop.category.*'])->once();

    $this->service->invalidateForModel($model, $invalidationConfig);
});

it('logs error on direct key invalidation failure', function (): void {
    $model              = new class() extends Model {};
    $invalidationConfig = ['key1'];
    $exception          = new Exception('Cache forget failed');

    SmartCache::shouldReceive('forget')->with('key1')->andThrow($exception);

    Log::shouldReceive('debug')
        ->with(
            'Cache invalidation failed for direct keys on '.get_class($model),
            ['keys' => ['key1'], 'error' => $exception->getMessage()]
        )
        ->once();

    $this->service->invalidateForModel($model, $invalidationConfig);
});

it('logs error on pattern invalidation failure', function (): void {
    $model              = new class() extends Model {};
    $invalidationConfig = [['type' => 'pattern', 'value' => 'pattern1:*']];
    $exception          = new Exception('Cache flush failed');

    SmartCache::shouldReceive('flushPatterns')->with(['pattern1:*'])->andThrow($exception);

    Log::shouldReceive('debug')
        ->with(
            'Cache invalidation failed for patterns on '.get_class($model),
            ['patterns' => ['pattern1:*'], 'error' => $exception->getMessage()]
        )
        ->once();

    $this->service->invalidateForModel($model, $invalidationConfig);
});

it('handles model class string correctly', function (): void {
    $modelClass         = 'App\Models\SomeModel';
    $invalidationConfig = ['key1'];

    SmartCache::shouldReceive('forget')->with('key1')->once();
    Log::shouldReceive('debug')->never(); // Ensure no errors are logged

    $this->service->invalidateForModel($modelClass, $invalidationConfig);
});
