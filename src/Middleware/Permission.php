<?php

namespace App\Http\Middleware;

use App\Constants\ErrorCode;
use Closure;

/**
 * 权限中间件
 *
 * @Author sorry510 491559675@qq.com
 * @DateTime 2021-3-18
 */
class Permission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return resJson(ErrorCode::NO_LOGIN, '', null, 401);
        }
        // // 验证用户是否被禁用
        // if ($user instanceof UserModel && $user->status != "1") {
        //     return resJson(ErrorCode::USER_FREEZE, '', null, 401);
        // }
        if ($user->hasRole('superAdmin', 'web')) {
            // 超级管理员
            return $next($request);
        }
        // 路由经过了用户认证
        /**
         * @var \Illuminate\Routing\Route
         */
        $route = $request->route();
        $permission = $route->getPermission(); // 获取权限名称
        if ($permission) {
            // 路由设置了权限
            try {
                if ($user->hasPermissionTo($permission, 'web')) {
                    return $next($request);
                } else {
                    // 没有通过权限
                    return resJson(ErrorCode::NO_AUTH, '', null, 403);
                }
            } catch (\Throwable $e) {
                // 权限配置错误
                return resJson(ErrorCode::NO_AUTH, '', null, 403);
            }
        }
        return $next($request);
    }
}
