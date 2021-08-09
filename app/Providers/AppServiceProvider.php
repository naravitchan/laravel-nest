<?php
namespace App\Providers;

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
        \Log::info('- register -');
        // Bind the NestJsService to the container
        $this->app->singleton(\App\Services\NestJsService::class);
    }
}
