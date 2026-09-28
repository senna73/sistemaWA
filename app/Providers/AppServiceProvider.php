<?php

namespace App\Providers;

use App\Models\FinancialBatches;
use App\Observers\FinancialBatchObserver;
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
        FinancialBatches::observe(FinancialBatchObserver::class);
    }
}
