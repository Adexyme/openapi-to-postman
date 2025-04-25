<?php
namespace Adexyme\OpenApiToPostman;

use Adexyme\OpenApiToPostman\Console\GeneratePostmanCollection;
use Illuminate\Support\ServiceProvider;

class OpenApiToPostmanServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(GeneratePostmanCollection::class);
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([GeneratePostmanCollection::class]);
        }
    }
}
