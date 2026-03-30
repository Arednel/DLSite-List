<?php

namespace App\Providers;

use App\View\Components\Fields\Priority;
use App\View\Components\Fields\ReListenValue;
use App\View\Components\Fields\ScoreSelect;
use App\View\Components\Fields\StatusSelect;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
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
        Blade::components([
            'fields.status-select' => StatusSelect::class,
            'fields.score-select' => ScoreSelect::class,
            'fields.priority' => Priority::class,
            'fields.re-listen-value' => ReListenValue::class,
        ]);

        Schema::defaultStringLength(191);

        if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&  $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
            \URL::forceScheme('https');
        }
    }
}
