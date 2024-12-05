<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\ConversationService;
use App\Services\AssistantService;
use App\Services\ModelService;
use App\Services\JsonStorageService;

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
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
