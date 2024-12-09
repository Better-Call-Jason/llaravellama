<?php

namespace App\Providers;

use App\Services\AssistantService;
use App\Services\ConversationService;
use App\Services\ModelService;
use Illuminate\Support\ServiceProvider;

class DataServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ConversationService::class, function ($app) {
            return new ConversationService();
        });

        $this->app->singleton(AssistantService::class, function ($app) {
            return new AssistantService();
        });

        $this->app->singleton(ModelService::class, function ($app) {
            return new ModelService();
        });

        $this->app->singleton(OllamaService::class, function ($app) {
            return new OllamaService($app->make(ModelService::class));
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
