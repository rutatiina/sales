<?php

namespace Rutatiina\Sales;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Rutatiina\Invoice\Traits\Recurring\Schedule as RecurringInvoiceScheduleTrait;

class SalesServiceProvider extends ServiceProvider
{
    use RecurringInvoiceScheduleTrait;

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        include __DIR__.'/routes/routes.php';
        //include __DIR__.'/routes/api.php';

        //$this->loadViewsFrom(__DIR__.'/resources/views', 'sales');
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->make('Rutatiina\Sales\Http\Controllers\SalesController');
    }
}
