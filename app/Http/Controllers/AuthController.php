<?php

namespace App\Http\Controllers;

use App\Models\TaiKhoan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'TenDangNhap' => 'required|string',
            'MatKhau' => 'required|string',
        ], [
            'TenDangNhap.required' => 'Vui lòng nhập tài khoản!',
            'MatKhau.required' => 'Vui lòng nhập mật khẩu!',
        ]);

        $user = TaiKhoan::where(
            'TenDangNhap',
            $request->TenDangNhap
        )->first();

        if (
            !$user ||
            !Hash::check($request->MatKhau, $user->MatKhau)
        ) {
            return redirect()->route('home')
                ->withErrors([
                    'error' => 'Tên đăng nhập hoặc mật khẩu không chính xác!'
                ])
                ->withInput($request->only('TenDangNhap'))
                ->with('show_login_modal', true);
        }

        $role = $user->VaiTro;

        if (!in_array($role, [
            'SinhVien',
            'QuanTriVienThuVien',
            'QuanTriVienHeThong'
        ], true)) {
            abort(403, 'Vai trò tài khoản không hợp lệ.');
        }

        $sv = null;
        $qtv = null;

        if ($role === 'SinhVien') {
            $sv = $user->sinhVien;

            if (!$sv) {
                return redirect()->route('home')
                    ->withErrors([
                        'error' => 'Tài khoản chưa có hồ sơ sinh viên hợp lệ.'
                    ])
                    ->with('show_login_modal', true);
            }
        } else {
            $qtv = $user->quanTriVien;

            if (!$qtv) {
                return redirect()->route('home')
                    ->withErrors([
                        'error' => 'Tài khoản chưa có hồ sơ quản trị viên hợp lệ.'
                    ])
                    ->with('show_login_modal', true);
            }
        }

        $request->session()->regenerate();

        Session::put('user_id', $user->MaTaiKhoan);
        Session::put('username', $user->TenDangNhap);
        Session::put('role', $role);

        if ($role === 'SinhVien') {
            Session::put('MSSV', $sv->MSSV);
            Session::put('student_name', $sv->HoTen);

            return redirect()->route('home');
        }

        Session::put('admin_name', $qtv->HoTen);
        Session::put('GioiTinh', $qtv->gioi_tinh_text);
        Session::put('MaQuanTri', $qtv->MaQuanTri);
        Session::put('ChucVu', $qtv->ChucVu);
        Session::put('SoDienThoai', $qtv->SoDienThoai);
        Session::put('Email', $qtv->Email);

        if ($role === 'QuanTriVienThuVien') {
            return redirect()->route('admin.library.dashboard');
        }

        return redirect()->route('admin.system.dashboard');
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
