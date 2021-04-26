<?php

namespace Sorry510;

use Illuminate\Support\ServiceProvider;
use Sorry510\Commands\Permission;
use Sorry510\Commands\RouteAst;
use Sorry510\Commands\Swagger;

/**
 * 权限服务
 * @Author sorry510 491559675@qq.com
 */
class AuthProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {

    }

    /**
     * Register the console commands for the package.
     *
     * @return void
     */
    protected function registerCommands()
    {
        $this->commands([
            Permission::class,
            RouteAst::class,
            Swagger::class,
        ]);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->registerCommands();
            $this->publishConfig();
            $this->registerMigrations(); // 不注册 migrate
        }
        $this->injectMethod(); // 注入自定义的方法
    }

    protected function injectMethod()
    {
        \Illuminate\Routing\Route::macro('setDesc', function (string $value) {
            $this->_desc = $value;
            return $this;
        });
        \Illuminate\Routing\Route::macro('getDesc', function () {
            return isset($this->_desc) ? $this->_desc : null; // 描述
        });
        \Illuminate\Routing\Route::macro('setPermission', function (string $value) {
            $this->_permission = $value; // 权限
            return $this;
        });
        \Illuminate\Routing\Route::macro('getPermission', function () {
            return isset($this->_permission) ? $this->_permission : null;
        });
    }

    protected function publishConfig()
    {
        $this->publishes([
            __DIR__ . '/Commands/stub/swaggerInfo.php' => base_path('swaggerInfo.php'),
            __DIR__ . '/config/permission.php' => config_path('permission.php'),
            __DIR__ . '/config/sanctum.php' => config_path('sanctum.php'),
            __DIR__ . '/Middleware/Permission.php' => app_path('Http/Middleware/Permission.php'),
        ], 'auth');

        $this->publishes([
            __DIR__ . '/migrations' => database_path('migrations'),
        ], 'auth-migrations');

    }

    protected function registerMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations');
    }
}
