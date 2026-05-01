<?php

use App\Providers\AppServiceProvider;
use App\Providers\CipherSweetAuthServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\RepositoryServiceProvider;

return [
    AppServiceProvider::class,
    CipherSweetAuthServiceProvider::class,
    HorizonServiceProvider::class,
    RepositoryServiceProvider::class,
];
