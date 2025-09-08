<?php

return [
    Illuminate\Foundation\Providers\FoundationServiceProvider::class,
    // … your other providers

    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\RouteServiceProvider::class,

    // Filament Admin Panel provider (important!)
    App\Providers\Filament\AdminPanelProvider::class,
];
