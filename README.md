## 权限设计
>根据swagger注释，自动生成权限表数据和路由文件

## 安装

```
composer require sorry510/auth
```

#### 使用

- 生成配置文件

```
php artisan vendor:publish --tag=auth
php artisan vendor:publish --tag=auth-migrations
```

- 执行数据库迁移

```
php artisan migrate
```

- 添加权限中间件

```
<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
  ...
    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'permission' => \App\Http\Middleware\Permission::class, // 权限中间件，必须放到 auth:sanctum 后面执行
    ];
}
```

### 相关命令

```
php artisan swagger 模块名称 // 生成swagger接口文档
php artisan swagger 模块名称 -r 1 // 生成swagger接口文档，同时同步生成路由文件
php artisan route:sync // 根据 openapi.json 对路由文件同步更新
php artisan route:permission // 根据路由文件同步权限列表
```