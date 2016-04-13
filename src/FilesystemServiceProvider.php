<?php namespace CupOfTea\Filesystem;

use Illuminate\Support\ServiceProvider;

class FilesystemServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app['filesystem']->mount('~', getenv('HOME'), false);
        $this->app['filesystem.cwd'];
        $this->app['filesystem.root'];
        $this->app['filesystem.storage'];
        $this->app['filesystem.sysroot'];
    }
    
    /**
     * Register the Filesystem.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('filesystem', function ($app) {
            return new FilesystemManager($app);
        });
        
        $this->app->singleton('filesystem.cwd', function ($app) {
            return $app['filesystem']->mount('cwd', getcwd(), false);
        });
        
        $this->app->singleton('filesystem.root', function ($app) {
            return $app['filesystem']->mount('root', dirname(dirname(__DIR__)), false);
        });
        
        $this->app->singleton('filesystem.storage', function ($app) {
            return $app['filesystem']->mount('storage', $app->storagePath(), false);
        });
        
        $this->app->singleton('filesystem.sysroot', function ($app) {
            return $app['filesystem']->mount('sysroot', DIRECTORY_SEPARATOR, false);
        });
    }
}
