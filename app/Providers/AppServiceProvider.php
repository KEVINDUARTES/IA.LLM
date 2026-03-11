<?php

namespace App\Providers;

use App\Services\AI\AIServiceInterface;
use App\Services\AI\OpenAIService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AIServiceInterface::class, function () {
            return new OpenAIService(
                apiKey: config('openai.api_key'),
                model:  config('openai.model', 'gpt-4o-mini'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
