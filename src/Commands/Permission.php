<?php

namespace Sorry510\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use Spatie\Permission\Models\Permission as PermissionModel;

class Permission extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'route:permission {--guard|guard=web : 是否自动确认}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '根据路由文件生成权限列表';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(Router $router)
    {
        $routes = $router->getRoutes();
        $guard = trim($this->option('guard'));
        $bar = $this->output->createProgressBar(count($routes));
        $bar->start();
        foreach ($routes as $route) {
            if ($route instanceof \Illuminate\Routing\Route) {
                $name = $route->getPermission();
                $lastFind = strrpos($route->getPrefix(), '/');
                $group = $lastFind ? substr($route->getPrefix(), $lastFind + 1) : $route->getPrefix();
                if ($name) {
                    $permission = PermissionModel::where('name', $name)->where('guard_name', $guard)->first();
                    if ($permission) {
                        $permission->describe = $route->getDesc();
                        $permission->group = $group;
                        $permission->save();
                    } else {
                        PermissionModel::create([
                            'name' => $name,
                            'group' => $group,
                            'describe' => $route->getDesc(),
                            'guard_name' => $guard,
                        ]);
                    }
                }
            }
            $bar->advance();
        }
        $bar->finish();
        $this->info('完成同步权限列表');
    }
}
