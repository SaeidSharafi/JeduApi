<?php

declare(strict_types=1);

use App\Models\Product;
use Carbon\Carbon;

describe('Product event date scopes', function (): void {

    describe('eventEnded scope', function (): void {

        it('returns products with event_ended_at before today', function (): void {
            $product = Product::factory()->create([
                'event_ended_at' => Carbon::yesterday(),
            ]);

            $results = Product::query()->eventEnded()->get();

            expect($results->pluck('id'))->toContain($product->id);
        });

        it('excludes products with event_ended_at in the future', function (): void {
            $product = Product::factory()->create([
                'event_ended_at' => Carbon::tomorrow(),
            ]);

            $results = Product::query()->eventEnded()->get();

            expect($results->pluck('id'))->not->toContain($product->id);
        });

        it('excludes products with null event_ended_at', function (): void {
            $product = Product::factory()->create([
                'event_ended_at' => null,
            ]);

            $results = Product::query()->eventEnded()->get();

            expect($results->pluck('id'))->not->toContain($product->id);
        });
    });

    describe('eventNotStarted scope', function (): void {

        it('returns products with event_start_at in the future', function (): void {
            $product = Product::factory()->create([
                'event_start_at' => Carbon::tomorrow(),
            ]);

            $results = Product::query()->eventNotStarted()->get();

            expect($results->pluck('id'))->toContain($product->id);
        });

        it('excludes products with event_start_at in the past', function (): void {
            $product = Product::factory()->create([
                'event_start_at' => Carbon::yesterday(),
            ]);

            $results = Product::query()->eventNotStarted()->get();

            expect($results->pluck('id'))->not->toContain($product->id);
        });

        it('excludes products with null event_start_at', function (): void {
            $product = Product::factory()->create([
                'event_start_at' => null,
            ]);

            $results = Product::query()->eventNotStarted()->get();

            expect($results->pluck('id'))->not->toContain($product->id);
        });
    });

    describe('eventOngoing scope', function (): void {

        it('returns products where today is between start and end', function (): void {
            $product = Product::factory()->create([
                'event_start_at' => Carbon::yesterday(),
                'event_ended_at' => Carbon::tomorrow(),
            ]);

            $results = Product::query()->eventOngoing()->get();

            expect($results->pluck('id'))->toContain($product->id);
        });

        it('excludes products with event_start_at in the future', function (): void {
            $product = Product::factory()->create([
                'event_start_at' => Carbon::tomorrow(),
                'event_ended_at' => Carbon::tomorrow()->addDay(),
            ]);

            $results = Product::query()->eventOngoing()->get();

            expect($results->pluck('id'))->not->toContain($product->id);
        });

        it('excludes products with event_ended_at in the past', function (): void {
            $product = Product::factory()->create([
                'event_start_at' => Carbon::yesterday()->subDay(),
                'event_ended_at' => Carbon::yesterday(),
            ]);

            $results = Product::query()->eventOngoing()->get();

            expect($results->pluck('id'))->not->toContain($product->id);
        });

        it('excludes products with null event_start_at', function (): void {
            $product = Product::factory()->create([
                'event_start_at' => null,
                'event_ended_at' => Carbon::tomorrow(),
            ]);

            $results = Product::query()->eventOngoing()->get();

            expect($results->pluck('id'))->not->toContain($product->id);
        });

        it('excludes products with null event_ended_at', function (): void {
            $product = Product::factory()->create([
                'event_start_at' => Carbon::yesterday(),
                'event_ended_at' => null,
            ]);

            $results = Product::query()->eventOngoing()->get();

            expect($results->pluck('id'))->not->toContain($product->id);
        });
    });

    describe('eventNotEnded scope', function (): void {

        it('returns products with null event_ended_at', function (): void {
            $product = Product::factory()->create([
                'event_ended_at' => null,
            ]);

            $results = Product::query()->eventNotEnded()->get();

            expect($results->pluck('id'))->toContain($product->id);
        });

        it('returns products with event_ended_at in the future', function (): void {
            $product = Product::factory()->create([
                'event_ended_at' => Carbon::tomorrow(),
            ]);

            $results = Product::query()->eventNotEnded()->get();

            expect($results->pluck('id'))->toContain($product->id);
        });

        it('excludes products with event_ended_at in the past', function (): void {
            $product = Product::factory()->create([
                'event_ended_at' => Carbon::yesterday(),
            ]);

            $results = Product::query()->eventNotEnded()->get();

            expect($results->pluck('id'))->not->toContain($product->id);
        });
    });

    describe('null event date handling across all scopes', function (): void {

        it('handles products with all null event dates', function (): void {
            $product = Product::factory()->create([
                'event_start_at' => null,
                'event_ended_at' => null,
            ]);

            expect(Product::query()->eventEnded()->pluck('id'))->not->toContain($product->id);
            expect(Product::query()->eventNotStarted()->pluck('id'))->not->toContain($product->id);
            expect(Product::query()->eventOngoing()->pluck('id'))->not->toContain($product->id);
            expect(Product::query()->eventNotEnded()->pluck('id'))->toContain($product->id);
        });
    });
});
