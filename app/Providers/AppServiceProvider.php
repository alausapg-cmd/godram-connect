<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
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
        // Short, stable names for things that can be put in the spotlight.
        Relation::morphMap([
            'video' => \App\Models\Video::class,
            'production' => \App\Models\Production::class,
            'story' => \App\Models\Story::class,
        ]);
    }
}
