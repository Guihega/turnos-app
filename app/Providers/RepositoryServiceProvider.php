<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\MetricsRepositoryInterface;
use App\Repositories\Contracts\TicketRepositoryInterface;
use App\Repositories\Eloquent\MetricsRepository;
use App\Repositories\Eloquent\TicketRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * All repository bindings.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        TicketRepositoryInterface::class => TicketRepository::class,
        MetricsRepositoryInterface::class => MetricsRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->bindings as $abstract => $concrete) {
            $this->app->bind($abstract, $concrete);
        }
    }

    public function boot(): void
    {
        //
    }
}
