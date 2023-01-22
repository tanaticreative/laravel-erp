<?php


namespace Tan\ERP;

use Tan\ERP\Contracts\EntityEvent;
use Tan\ERP\Events\WebhookRequestCreateEvent;
use Tan\ERP\Events\WebhookRequestDeleteEvent;
use Tan\ERP\Events\WebhookRequestUpdateEvent;
use Tan\ERP\Listeners\EntityEventListener;
use Tan\ERP\Listeners\WebhookRequestListener;
use Tan\ERP\Observers\CompanyAddressObserver;
use Tan\ERP\Observers\CompanyObserver;
use Tan\ERP\Observers\InvoiceObserver;
use Tan\ERP\Observers\ProductObserver;
use Tan\ERP\Observers\TenderObserver;
use Tan\ERP\Observers\UserObserver;
use App\Components\ERP\Observers\OrderObserver;
use App\Models\Company;
use App\Models\Product;
use App\Models\Tender;
use App\Models\User;
use Tan\ERP\Commands\RetryFailedJobsCommand;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     */
    public function boot()
    {
        if (Config::get('erp.sync.enabled')) {
            User::observe(UserObserver::class);
            Company::observe(CompanyObserver::class);
            Company\Address::observe(CompanyAddressObserver::class);
            Product::observe(ProductObserver::class);
            Tender::observe(TenderObserver::class);
            Tender\Order::observe(OrderObserver::class);
          //  Tender\Invoice::observe(InvoiceObserver::class);
            //
            Event::listen([WebhookRequestUpdateEvent::class], WebhookRequestListener::class);
            Event::listen([WebhookRequestCreateEvent::class], WebhookRequestListener::class);
            Event::listen([WebhookRequestDeleteEvent::class], WebhookRequestListener::class);
            //
            Event::listen([EntityEvent::class], EntityEventListener::class);
        }

        $this->publishes([
            __DIR__ .'/Support/config/erp.php' => config_path('erp.php'),
            __DIR__ .'/Support/config/logging.php' => config_path('logging.php')
        ], 'erp-config');
    }


    /**
     * Register bindings in the container.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ .'/Support/config/erp.php', 'erp');
        $this->mergeConfigFrom(__DIR__ .'/Support/config/logging.php', 'logging');
        $this->loadRoutesFrom(__DIR__ .'/Support/routes.php');
        $this->loadMigrationsFrom(__DIR__ .'/Support/migrations');

        $this->app->bind('ERPManager', ERPManager::class);
        $this->app->bind('ERPClient', ERPClient::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                RetryFailedJobsCommand::class,
            ]);
        }
    }
}
