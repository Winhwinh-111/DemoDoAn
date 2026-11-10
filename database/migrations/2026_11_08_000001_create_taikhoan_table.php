<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `TaiKhoan` (
  `MaTaiKhoan` VARCHAR(30) NOT NULL,
  `TenDangNhap` VARCHAR(100) NOT NULL,
  `MatKhau` VARCHAR(255) NOT NULL,
  `VaiTro` ENUM(
    'SinhVien',
    'QuanTriVienThuVien',
    'QuanTriVienHeThong'
  ) NOT NULL,
  `NgayTao` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`MaTaiKhoan`),
  UNIQUE KEY `UQ_TaiKhoan_TenDangNhap` (`TenDangNhap`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('TaiKhoan');
    }
};
