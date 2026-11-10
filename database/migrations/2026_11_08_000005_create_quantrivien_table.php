<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `QuanTriVien` (
  `MaQuanTri` VARCHAR(30) NOT NULL,
  `HoTen` VARCHAR(200) NOT NULL,
  `Email` VARCHAR(150) NULL,
  `GioiTinh` TINYINT NOT NULL DEFAULT 2,
  `SoDienThoai` VARCHAR(20) NULL,
  `ChucVu` VARCHAR(150) NULL,
  `MaTaiKhoan` VARCHAR(30) NOT NULL,
  PRIMARY KEY (`MaQuanTri`),
  UNIQUE KEY `UQ_QuanTriVien_MaTaiKhoan` (`MaTaiKhoan`),
  CONSTRAINT `CK_QuanTriVien_GioiTinh`
    CHECK (`GioiTinh` IN (0,1,2)),
  CONSTRAINT `FK_QuanTriVien_TaiKhoan`
    FOREIGN KEY (`MaTaiKhoan`) REFERENCES `TaiKhoan` (`MaTaiKhoan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('QuanTriVien');
    }
};
