<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind('git.version', function () {
            if (! is_dir(base_path('.git'))) {
                return 'development';
            }

            $version = shell_exec('git describe --tags --always --dirty');

            return $version ? trim($version) : '0.0.1';
        });
    }
}
