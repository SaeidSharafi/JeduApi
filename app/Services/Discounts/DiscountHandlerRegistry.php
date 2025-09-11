<?php

declare(strict_types=1);

namespace App\Services\Discounts;

use App\Attributes\DiscountHandlerKey;
use App\Contracts\Discounts\DiscountActionContract;
use App\Contracts\Discounts\DiscountConditionContract;
use App\Contracts\Discounts\ProductDiscountActionContract;
use App\Contracts\Discounts\ProductDiscountConditionContract;
use App\Enums\Order\DiscountTypeEnum;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Centralized registry for all discount handlers to eliminate code duplication
 * and provide consistent handler discovery across different services.
 */
final class DiscountHandlerRegistry
{
    public const CACHE_KEY = 'discounts.handler_registry.cache';

    private array $discoveryMap = [
        DiscountConditionContract::class        => 'cartConditionHandlers',
        DiscountActionContract::class           => 'cartActionHandlers',
        ProductDiscountConditionContract::class => 'productConditionHandlers',
        ProductDiscountActionContract::class    => 'productActionHandlers',
    ];

    private array $cartConditionHandlers = [];

    private array $cartActionHandlers = [];

    private array $productConditionHandlers = [];

    private array $productActionHandlers = [];

    private array $handlerConfigMap = [];

    public function __construct(
        private readonly Filesystem $filesystem,
    ) {
        if (config()->get('app.debug')) {
            $this->discoverAndCacheHandlers();

            return;
        }

        $cachedHandlers = Cache::get(self::CACHE_KEY);

        if ($cachedHandlers) {
            $this->loadHandlersFromCache($cachedHandlers);
        } else {
            $this->discoverAndCacheHandlers();
        }
    }

    public function getHandlerClassByKey(string $key, string $type, DiscountTypeEnum $discountType): ?string
    {
        if ($type === 'condition') {
            if (isset($this->cartConditionHandlers[$key]) && $discountType === DiscountTypeEnum::CART_CHECKOUT) {
                return $this->cartConditionHandlers[$key];
            }
            if (isset($this->productConditionHandlers[$key]) && $discountType === DiscountTypeEnum::PRODUCT_SPECIFIC) {
                return $this->productConditionHandlers[$key];
            }
        }
        if ($type === 'action') {
            if (isset($this->cartActionHandlers[$key]) && $discountType === DiscountTypeEnum::CART_CHECKOUT) {
                return $this->cartActionHandlers[$key];
            }

            if (isset($this->productActionHandlers[$key]) && $discountType === DiscountTypeEnum::PRODUCT_SPECIFIC) {
                return $this->productActionHandlers[$key];
            }
        }

        return null; // Not found
    }

    // Getters for cart handlers
    public function getCartConditionHandlers(): array
    {
        return $this->cartConditionHandlers;
    }

    public function getCartActionHandlers(): array
    {
        return $this->cartActionHandlers;
    }

    // Getters for product handlers
    public function getProductConditionHandlers(): array
    {
        return $this->productConditionHandlers;
    }

    public function getProductActionHandlers(): array
    {
        return $this->productActionHandlers;
    }

    public function getHandlerConfigMap(): array
    {
        return $this->handlerConfigMap;
    }

    // Helper methods for getting specific handler classes
    public function getCartConditionHandler(string $key): ?string
    {
        return $this->cartConditionHandlers[$key] ?? null;
    }

    public function getCartActionHandler(string $key): ?string
    {
        return $this->cartActionHandlers[$key] ?? null;
    }

    public function getProductConditionHandler(string $key): ?string
    {
        return $this->productConditionHandlers[$key] ?? null;
    }

    public function getProductActionHandler(string $key): ?string
    {
        return $this->productActionHandlers[$key] ?? null;
    }

    public function getConfigClass(string $handlerClass): ?string
    {
        return $this->handlerConfigMap[$handlerClass] ?? null;
    }

    /**
     * Populates the registry properties from a cached array.
     */
    private function loadHandlersFromCache(array $cachedData): void
    {
        $this->cartConditionHandlers    = $cachedData['cartConditions']    ?? [];
        $this->cartActionHandlers       = $cachedData['cartActions']       ?? [];
        $this->productConditionHandlers = $cachedData['productConditions'] ?? [];
        $this->productActionHandlers    = $cachedData['productActions']    ?? [];
        $this->handlerConfigMap         = $cachedData['configMap']         ?? [];
    }

    /**
     * Performs the file discovery and stores the result in the cache.
     */
    private function discoverAndCacheHandlers(): void
    {
        $this->discoverHandlers();

        $dataToCache = [
            'cartConditions'    => $this->cartConditionHandlers,
            'cartActions'       => $this->cartActionHandlers,
            'productConditions' => $this->productConditionHandlers,
            'productActions'    => $this->productActionHandlers,
            'configMap'         => $this->handlerConfigMap,
        ];

        Cache::forever(self::CACHE_KEY, $dataToCache);
    }

    /**
     * Auto-discover handlers by scanning the file system.
     * (This is the original discovery logic)
     */
    private function discoverHandlers(): void
    {
        foreach (array_keys($this->discoveryMap) as $interface) {
            $propertyName          = $this->discoveryMap[$interface];
            $this->{$propertyName} = [];
        }
        $this->handlerConfigMap = [];

        // Get discovery paths from the config file.
        $discoveryPaths = config()->get('discounts.discovery_paths', []);

        foreach ($discoveryPaths as $baseNamespace => $relativePath) {
            $this->discoverHandlersInPath($baseNamespace, $relativePath);
        }
    }

    private function discoverHandlersInPath(string $baseNamespace, string $relativePath): void
    {
        $absolutePath = mb_rtrim(app_path($relativePath), DIRECTORY_SEPARATOR);

        if (! $this->filesystem->isDirectory($absolutePath)) {
            return;
        }

        foreach ($this->filesystem->allFiles($absolutePath) as $file) {
            // @codeCoverageIgnoreStart
            if ($file->getExtension() !== 'php') {
                continue;
            }
            // @codeCoverageIgnoreEnd

            $className = $this->getClassNameFromFile($file->getPathname(), $absolutePath, $baseNamespace);

            // @codeCoverageIgnoreStart
            if (! $className || ! class_exists($className)) {
                continue;
            }
            // @codeCoverageIgnoreEnd

            try {
                $reflection = new ReflectionClass($className);
                // @codeCoverageIgnoreStart
                if (! $reflection->isInstantiable()) {
                    continue;
                }
                // @codeCoverageIgnoreEnd

                $this->registerHandlerIfApplicable($reflection, $className);
            }
            // @codeCoverageIgnoreStart
            catch (Throwable $e) {
                Log::warning('Could not reflect class for discount handler discovery.', [
                    'class'     => $className,
                    'exception' => $e->getMessage(),
                ]);
            }
            // @codeCoverageIgnoreEnd
        }
    }

    private function registerHandlerIfApplicable(ReflectionClass $reflection, string $className): void
    {
        $attributes = $reflection->getAttributes(DiscountHandlerKey::class);
        if (empty($attributes)) {
            return; // Skip classes without the required attribute.
        }

        $handlerKey = $attributes[0]->newInstance()->key;

        foreach ($this->discoveryMap as $interface => $property) {
            if ($reflection->implementsInterface($interface)) {
                $this->{$property}[$handlerKey] = $className;
                $this->discoverConfigClass($className);
            }
        }
    }

    private function discoverConfigClass(string $handlerClass): void
    {
        // Get config class directly from the handler using the interface method
        if (class_exists($handlerClass)) {
            try {
                // Call the static getConfigClass method from the handler
                $configClassName = $handlerClass::getConfigClass();

                if (class_exists($configClassName)) {
                    $this->handlerConfigMap[$handlerClass] = $configClassName;
                }
            }
            // @codeCoverageIgnoreStart
            catch (\Throwable $e) {
                // Handler doesn't implement getConfigClass method or other error
                // This is expected for handlers that don't follow the new pattern yet
                // We silently skip handlers that don't have valid config class mappings
            }
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Get the fully qualified class name from a file.
     */
    private function getClassNameFromFile(string $filePath, string $basePath, string $baseNamespace): ?string
    {
        $relativePath   = mb_ltrim(Str::after($filePath, $basePath), DIRECTORY_SEPARATOR);
        $classPath      = Str::beforeLast($relativePath, '.php');
        $classNamespace = str_replace(DIRECTORY_SEPARATOR, '\\', $classPath);

        return mb_rtrim($baseNamespace, '\\').'\\'.$classNamespace;
    }
}
