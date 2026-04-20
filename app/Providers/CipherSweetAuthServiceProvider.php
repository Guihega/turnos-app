<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\CipherSweetUserProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class CipherSweetAuthServiceProvider extends ServiceProvider
{
    /**
     * Register the custom user provider that handles encrypted email lookups.
     *
     * This replaces the default 'eloquent' driver with one that uses
     * CipherSweet blind indexes for the email field.
     */
    public function boot(): void
    {
        Auth::provider('ciphersweet', function (Application $app, array $config) {
            return new CipherSweetUserProvider(
                $app['hash'],
                $config['model'],
            );
        });
    }
}
