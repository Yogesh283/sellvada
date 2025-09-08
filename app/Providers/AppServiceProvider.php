<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        // empty or put your bindings here
    }

    public function boot()
    {
        // empty or boot logic not dependent on Filament
    }
}
