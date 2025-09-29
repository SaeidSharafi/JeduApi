<?php
it('handle empty product ids', function (): void {
    $job = new App\Jobs\UpdateProductPricingJob([]);
    $job->handle(app(App\Services\ProductPriceService::class));
    $this->assertTrue(true);
});

it('handle non existing product ids', function (): void {
    $job = new App\Jobs\UpdateProductPricingJob([9999, 10000]);
    $job->handle(app(App\Services\ProductPriceService::class));
    $this->assertTrue(true);
});
