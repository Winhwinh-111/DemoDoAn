<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuanTriVien extends Model
{
    protected $table = 'QuanTriVien';
    protected $primaryKey = 'MaQuanTri';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'MaQuanTri',
        'HoTen',
        'Email',
        'GioiTinh',
        'SoDienThoai',
        'ChucVu',
        'MaTaiKhoan'
    ];

    public function getGioiTinhTextAttribute()
    {
        return match ((int) $this->GioiTinh) {
            1 => 'Nam',
            0 => 'Nữ',
            2 => 'Khác',
            default => 'Chưa cập nhật',
        };
    }
    
    public function taiKhoan()
    {
        return $this->belongsTo(TaiKhoan::class, 'MaTaiKhoan', 'MaTaiKhoan');
    }
}