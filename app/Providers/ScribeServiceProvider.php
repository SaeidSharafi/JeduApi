<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Knuckles\Camel\Extraction\ExtractedEndpointData;
use Knuckles\Scribe\Scribe;
use phpDocumentor\Reflection\Exception;

class ScribeServiceProvider extends ServiceProvider
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
        if (class_exists(\Knuckles\Scribe\Scribe::class)) {
            Scribe::beforeResponseCall(function (
                Request $request,
                ExtractedEndpointData $endpointData
            ) {
                $admin = Admin::first();
                if (!$admin) {
                    \Illuminate\Support\Facades\Log::error("Scribe beforeResponseCall: Admin not found!");
                    throw new Exception("Scribe beforeResponseCall: Admin not found!");
                }
                $token = $admin->createToken('token')->plainTextToken;

                $request->headers->add(["Authorization" => "Bearer $token"]);
                $request->server->set("HTTP_AUTHORIZATION", "Bearer $token");
            });
        }
    }
}
