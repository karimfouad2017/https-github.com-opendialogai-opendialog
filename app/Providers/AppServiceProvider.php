<?php

namespace App\Providers;

use App\User;
use App\Observers\UserObserver;
use App\Services\FileUploadInterface;
use App\Services\FileUploadClient;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(FileUploadInterface::class, function () {
            return new FileUploadClient();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        User::observe(UserObserver::class);

        if (config('app.force_https')) {
            URL::forceScheme('https');
        }
    }
}
