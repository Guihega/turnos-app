<?php

use App\Providers\AppServiceProvider;
use App\Providers\BillingServiceProvider;
use App\Providers\CipherSweetAuthServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\RepositoryServiceProvider;

return [
    AppServiceProvider::class,
    BillingServiceProvider::class,
    CipherSweetAuthServiceProvider::class,
    HorizonServiceProvider::class,
    RepositoryServiceProvider::class,
];
