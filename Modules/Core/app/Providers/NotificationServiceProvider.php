<?php

namespace Modules\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Services\NotificationChannelRegistry;
use Modules\Core\Services\NotificationChannelResolver;
use Modules\Core\Services\MetaWhatsAppCloudApiChannel;
use Modules\Core\Services\NotificationService;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationChannelRegistry::class, function () {
            $registry = new NotificationChannelRegistry();
            $registry->register(app(MetaWhatsAppCloudApiChannel::class));
            return $registry;
        });
        $this->app->singleton(
            NotificationService::class,
            fn ($app) => new NotificationService(
                $app->make(NotificationChannelRegistry::class),
                $app->make(NotificationChannelResolver::class),
            ),
        );
    }
}
