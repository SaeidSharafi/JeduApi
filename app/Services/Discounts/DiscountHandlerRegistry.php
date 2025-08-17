<?php

declare(strict_types=1);

namespace App\Services\Discounts;

use App\Attributes\DiscountHandler;
use App\Services\Discounts\Cart\Actions\ApplyPercentageDiscountToItemsAction;
use App\Services\Discounts\Cart\Conditions\CartValueCondition;
use App\Services\Discounts\Product\Actions\ApplyPercentageDiscountToProductAction;
use App\Services\Discounts\Product\Conditions\ProductCategoryCondition;
use App\Services\Discounts\Configs\ApplyPercentageDiscountConfigData;
use App\Services\Discounts\Configs\CartValueConditionConfigData;
use App\Services\Discounts\Configs\ProductCategoryConditionConfigData;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Event\Code\Throwable;
use ReflectionClass;

/**
 * Centralized registry for all discount handlers to eliminate code duplication
 * and provide consistent handler discovery across different services.
 */
final class DiscountHandlerRegistry
{
    private array $cartConditionHandlers = [];
    private array $cartActionHandlers = [];
    private array $productConditionHandlers = [];
    private array $productActionHandlers = [];
    private array $handlerConfigMap = [];

    public function __construct(private readonly Filesystem $filesystem)
    {
        $this->initializeStaticHandlers();
        $this->discoverHandlers();
    }

    /**
     * Initialize the static handlers that are always available.
     * This provides fallback when auto-discovery fails.
     */
    private function initializeStaticHandlers(): void
    {
        // Cart handlers
        $this->cartConditionHandlers = [
            'cart_value_over' => CartValueCondition::class,
        ];

        $this->cartActionHandlers = [
            'apply_percentage_off' => ApplyPercentageDiscountToItemsAction::class,
        ];

        // Product handlers
        $this->productConditionHandlers = [
            'product_in_category' => ProductCategoryCondition::class,
        ];

        $this->productActionHandlers = [
            'apply_percentage_off_product' => ApplyPercentageDiscountToProductAction::class,
        ];

        // Config mappings
        $this->handlerConfigMap = [
            CartValueCondition::class => CartValueConditionConfigData::class,
            ProductCategoryCondition::class => ProductCategoryConditionConfigData::class,
            ApplyPercentageDiscountToItemsAction::class => ApplyPercentageDiscountConfigData::class,
            ApplyPercentageDiscountToProductAction::class => ApplyPercentageDiscountConfigData::class,
        ];
    }

    /**
     * Auto-discover handlers using the DiscountHandler attribute.
     */
    private function discoverHandlers(): void
    {
        $this->discoverCartHandlers();
        $this->discoverProductHandlers();
    }

    private function discoverCartHandlers(): void
    {
        // Discover cart actions
        $this->discoverHandlersInDirectory(
            app_path('Services/Discounts/Cart/Actions'),
            'App\\Services\\Discounts\\Cart\\Actions',
            function (DiscountHandler $handler, string $class) {
                if ($handler->type === 'action') {
                    $this->cartActionHandlers[$handler->key] = $class;
                }
            }
        );

        // Discover cart conditions
        $this->discoverHandlersInDirectory(
            app_path('Services/Discounts/Cart/Conditions'),
            'App\\Services\\Discounts\\Cart\\Conditions',
            function (DiscountHandler $handler, string $class) {
                if ($handler->type === 'condition') {
                    $this->cartConditionHandlers[$handler->key] = $class;
                }
            }
        );
    }

    private function discoverProductHandlers(): void
    {
        // Discover product actions
        $this->discoverHandlersInDirectory(
            app_path('Services/Discounts/Product/Actions'),
            'App\\Services\\Discounts\\Product\\Actions',
            function (DiscountHandler $handler, string $class) {
                if ($handler->type === 'action') {
                    $this->productActionHandlers[$handler->key] = $class;
                }
            }
        );

        // Discover product conditions
        $this->discoverHandlersInDirectory(
            app_path('Services/Discounts/Product/Conditions'),
            'App\\Services\\Discounts\\Product\\Conditions',
            function (DiscountHandler $handler, string $class) {
                if ($handler->type === 'condition') {
                    $this->productConditionHandlers[$handler->key] = $class;
                }
            }
        );
    }

    private function discoverHandlersInDirectory(string $baseDir, string $namespace, callable $callback): void
    {
        if (!$this->filesystem->exists($baseDir)) {
            return;
        }

        foreach ($this->filesystem->files($baseDir) as $file) {
            $class = $namespace . '\\' . $file->getFilenameWithoutExtension();

            if (!class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(DiscountHandler::class);

            foreach ($attributes as $attribute) {
                $handler = $attribute->newInstance();
                $callback($handler, $class);

                // Auto-discover config class based on naming convention
                $this->discoverConfigClass($handler, $class);
            }
        }
    }

    private function discoverConfigClass(DiscountHandler $handler, string $class): void
    {
        // Get config class directly from the handler using the interface method
        if (class_exists($class)) {
            try {
                // Call the static getConfigClass method from the handler
                $configClassName = $class::getConfigClass();

                if (class_exists($configClassName)) {
                    $this->handlerConfigMap[$class] = $configClassName;
                }
            } catch (\Throwable $e) {
                // Handler doesn't implement getConfigClass method or other error
                // This is expected for handlers that don't follow the new pattern yet
                // We silently skip handlers that don't have valid config class mappings
            }
        }
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
}
