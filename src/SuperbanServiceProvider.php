<?php 

namespace Babyfaceeasy\Superban;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SuperbanServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/superban.php', 'cache'
        );
    }

    public function register()
    {
        Route::aliasMiddleware('superban', \Babyfaceeasy\Superban\Middleware\BanRequests::class);
    }
}