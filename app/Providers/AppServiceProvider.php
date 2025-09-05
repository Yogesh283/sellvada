<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
  public function boot(): void
{
    Vite::prefetch(concurrency: 3);
    Gate::define('access-admin', fn ($user) => (bool) $user->is_admin);
}



    
}
