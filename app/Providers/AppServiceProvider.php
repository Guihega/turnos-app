<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
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
        \Illuminate\Support\Facades\Vite::prefetch(concurrency: 3);

        // Agrega esta línea para registrar la política de tickets:
        \Illuminate\Support\Facades\Gate::policy(
            \App\Models\Ticket::class, 
            \App\Policies\TicketPolicy::class
        );
    }
}
 