<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `SinhVien` (
  `MSSV` VARCHAR(30) NOT NULL,
  `HoTen` VARCHAR(200) NOT NULL,
  `NgaySinh` DATE NULL,
  `GioiTinh` TINYINT NOT NULL DEFAULT 2,
  `SoDienThoai` VARCHAR(20) NULL,
  `HanThe` DATE NULL,
  `MaNganh` VARCHAR(30) NOT NULL,
  `MaTaiKhoan` VARCHAR(30) NOT NULL,
  PRIMARY KEY (`MSSV`),
  UNIQUE KEY `UQ_SinhVien_MaTaiKhoan` (`MaTaiKhoan`),
  CONSTRAINT `CK_SinhVien_GioiTinh`
    CHECK (`GioiTinh` IN (0,1,2)),
  CONSTRAINT `FK_SinhVien_NganhHoc`
    FOREIGN KEY (`MaNganh`) REFERENCES `NganhHoc` (`MaNganh`),
  CONSTRAINT `FK_SinhVien_TaiKhoan`
    FOREIGN KEY (`MaTaiKhoan`) REFERENCES `TaiKhoan` (`MaTaiKhoan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('SinhVien');
    }
};
