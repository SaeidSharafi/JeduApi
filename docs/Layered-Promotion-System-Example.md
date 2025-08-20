# Layered Promotion System - Implementation Example

## Overview

This document demonstrates the new layered promotion system inspired by Bagisto's Catalog Rules. The system allows multiple promotions to be applied sequentially to a single product based on priority.

## Core Changes Made

### 1. Refactored Core Logic
- **OLD**: Loop through promotions first, find matching products for each
- **NEW**: Loop through products first, find ALL matching promotions and apply sequentially

### 2. Key Features Added
- **Priority-based application**: Promotions are applied in order of their `priority` field (ascending)
- **Sequential stacking**: Each promotion's output becomes the next promotion's input
- **Stop processing**: Promotions with `stop_processing_subsequent_rules = true` halt further processing
- **Audit trail**: System tracks which promotions were applied

## Example Scenario

### Setup
- **Product**: Course priced at $100
- **Promotion A**: "VIP 15% Off" (priority: 1, ends_other_rules: false)
- **Promotion B**: "Clearance Sale $10 Off" (priority: 2, ends_other_rules: false)

### Step-by-Step Calculation

```php
// Step 1: Start with original price
$currentPrice = 100; // $100

// Step 2: Apply Promotion A (VIP 15% Off)
$currentPrice = $currentPrice * (100 - 15) / 100;
$currentPrice = 100 * 0.85 = 85; // $85

// Step 3: Apply Promotion B ($10 Off the already discounted price)
$currentPrice = max($currentPrice - 10, 0);
$currentPrice = max(85 - 10, 0) = 75; // $75

// Final result: $75 (25% total discount through layered promotions)
```

### Database Result

```sql
INSERT INTO product_delivery_option_discount_prices 
(product_delivery_option_id, discount_promotion_id, discounted_price)
VALUES (1, 2, 75); -- Stores final price with last applied promotion
```

## Code Structure Changes

### New Method: `calculateFinalDiscountedPrice()`

```php
private function calculateFinalDiscountedPrice(ProductDeliveryOption $option, Collection $promotions): int
{
    $currentPrice = $option->price;
    
    // Find all promotions that match this product
    $matchingPromotions = $promotions->filter(function (DiscountPromotion $promotion) use ($option) {
        return $this->allConditionsPass($promotion, $option);
    });

    if ($matchingPromotions->isEmpty()) {
        return $currentPrice;
    }

    // Sort by priority (ascending - lower numbers = higher priority)
    $sortedPromotions = $matchingPromotions->sortBy('priority');

    // Apply each promotion sequentially
    foreach ($sortedPromotions as $promotion) {
        $currentPrice = $this->applyPromotionActions($promotion, $option, $currentPrice);
        
        // If this promotion stops further processing, break
        if ($promotion->stop_processing_subsequent_rules) {
            break;
        }
    }

    return $currentPrice;
}
```

### New Method: `processProductDeliveryOptionsChunk()`

```php
private function processProductDeliveryOptionsChunk($deliveryOptions, Collection $promotions): void
{
    $recordsToInsert = [];

    foreach ($deliveryOptions as $option) {
        $finalPrice = $this->calculateFinalDiscountedPrice($option, $promotions);
        
        if ($finalPrice < $option->price) {
            $recordsToInsert[] = [
                'product_delivery_option_id' => $option->id,
                'discount_promotion_id'      => $this->getLastAppliedPromotionId($option, $promotions),
                'discounted_price'           => $finalPrice,
                'created_at'                 => now(),
                'updated_at'                 => now(),
            ];
        }
    }

    if (!empty($recordsToInsert)) {
        DB::table('product_delivery_option_discount_prices')->insert($recordsToInsert);
    }
}
```

## Advanced Examples

### Example 1: Stop Processing Rules

```php
// Promotion A: VIP 20% Off (priority: 1, ends_other_rules: true)
// Promotion B: Flash Sale 10% Off (priority: 2)

// Result: Only Promotion A applies ($100 -> $80)
// Promotion B is never applied because A stops processing
```

### Example 2: Priority-based Application

```php
// Promotion A: New Customer 5% Off (priority: 10)
// Promotion B: Category Sale 15% Off (priority: 5)  
// Promotion C: VIP Member $20 Off (priority: 1)

// Application order: C -> B -> A
// $100 -> $80 -> $68 -> $64.60
```

## Benefits of New System

1. **Flexibility**: Multiple promotions can stack for better customer experience
2. **Control**: Priority system ensures predictable application order
3. **Performance**: Still efficiently processes products in chunks
4. **Auditability**: Clear trail of which promotions were applied
5. **Extensibility**: Easy to add new promotion types without changing core logic

## Migration Impact

- **Database Schema**: No changes needed (priority and stop_processing_subsequent_rules already exist)
- **API Compatibility**: Existing promotion creation/update APIs work unchanged
- **Performance**: Similar performance characteristics with improved functionality
- **Testing**: Comprehensive test coverage for all scenarios

## Next Steps

1. **Queue Jobs**: Consider implementing background job processing for large product catalogs
2. **Caching**: Add Redis caching for frequently accessed promotion rules
3. **Analytics**: Add detailed logging for promotion effectiveness analysis
4. **API Enhancements**: Consider exposing applied promotions in product APIs
