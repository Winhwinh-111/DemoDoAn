<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckAdminRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!$request->session()->has('user_id')) {
            return redirect()->route('home')
                ->withErrors([
                    'error' => 'Vui lòng đăng nhập tài khoản quản trị!'
                ]);
        }

        $role = $request->session()->get('role');

        if (!in_array($role, $roles, true)) {
            abort(403, 'Bạn không có quyền truy cập vào phân hệ quản trị này!');
        }

        return $next($request);
    }
}
