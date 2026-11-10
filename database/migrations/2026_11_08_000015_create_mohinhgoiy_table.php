<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `MoHinhGoiY` (
  `MaMoHinh` VARCHAR(30) NOT NULL,
  `MaTaiKhoan` VARCHAR(30) NOT NULL,
  `MaCauHinh` VARCHAR(30) NOT NULL,
  `ThoiDiemTao` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `DuongDanTep` VARCHAR(500) NOT NULL,
  `LaHienHanh` TINYINT NOT NULL,
  PRIMARY KEY (`MaMoHinh`),
  CONSTRAINT `CK_MoHinhGoiY_LaHienHanh`
    CHECK (`LaHienHanh` IN (0,1)),
  CONSTRAINT `FK_MoHinhGoiY_TaiKhoan`
    FOREIGN KEY (`MaTaiKhoan`) REFERENCES `TaiKhoan` (`MaTaiKhoan`),
  CONSTRAINT `FK_MoHinhGoiY_CauHinh`
    FOREIGN KEY (`MaCauHinh`) REFERENCES `CauHinhGoiY` (`MaCauHinh`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('MoHinhGoiY');
    }
};
