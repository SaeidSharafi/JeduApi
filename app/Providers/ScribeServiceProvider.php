<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Scribe;
use phpDocumentor\Reflection\Exception;

final class ScribeServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (class_exists(Scribe::class)) {
            Scribe::beforeResponseCall(function (
                Request $request,
                ExtractedEndpointData $endpointData
            ): void {
                $staff = Staff::first();
                if (! $staff) {
                    \Illuminate\Support\Facades\Log::error('Scribe beforeResponseCall: Admin not found!');
                    throw new Exception('Scribe beforeResponseCall: Admin not found!');
                }
                $token = $staff->createToken('token')->plainTextToken;

                $request->headers->add(['Authorization' => "Bearer $token"]);
                $request->server->set('HTTP_AUTHORIZATION', "Bearer $token");
            });
        }
    }
}
