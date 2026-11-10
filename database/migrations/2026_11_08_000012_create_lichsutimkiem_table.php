<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `LichSuTimKiem` (
  `MaLuotTimKiem` VARCHAR(30) NOT NULL,
  `MSSV` VARCHAR(30) NOT NULL,
  `TuKhoa` VARCHAR(500) NOT NULL,
  `ThoiDiemTimKiem` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`MaLuotTimKiem`),
  CONSTRAINT `FK_LichSuTimKiem_SinhVien`
    FOREIGN KEY (`MSSV`) REFERENCES `SinhVien` (`MSSV`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('LichSuTimKiem');
    }
};
