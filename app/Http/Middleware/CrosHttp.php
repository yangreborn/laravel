<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class CrosHttp
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
        $response = $next($request);

        // 只对api路由做跨域请求处理
        if (preg_match('/\/api\/'.env('API_VERSION').'\//', $request->getPathInfo())){
            $response->headers->set('Access-Control-Allow-Origin', env('API_ALLOW_ORIGIN'));
            $response->headers->set('Access-Control-Allow-Headers', 'Origin, Content-Type, Cookie, Accept, Authorization');
            $response->headers->set('Access-Control-Expose-Headers', 'Authorization, authenticated, Content-Type, Content-Disposition');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PATCH, PUT, OPTIONS');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }
        return $response;
    }
}
