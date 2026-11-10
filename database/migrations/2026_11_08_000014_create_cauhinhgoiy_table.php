<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `CauHinhGoiY` (
  `MaCauHinh` VARCHAR(30) NOT NULL,
  `MaTaiKhoan` VARCHAR(30) NOT NULL,
  `TopN` INT NOT NULL,
  `NguongCosine` FLOAT NOT NULL,
  `ThoiDiemTao` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`MaCauHinh`),
  CONSTRAINT `CK_CauHinhGoiY_TopN`
    CHECK (`TopN` > 0),
  CONSTRAINT `CK_CauHinhGoiY_NguongCosine`
    CHECK (`NguongCosine` >= 0 AND `NguongCosine` <= 1),
  CONSTRAINT `FK_CauHinhGoiY_TaiKhoan`
    FOREIGN KEY (`MaTaiKhoan`) REFERENCES `TaiKhoan` (`MaTaiKhoan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('CauHinhGoiY');
    }
};
