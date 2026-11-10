<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `SinhVienHocPhan` (
  `MSSV` VARCHAR(30) NOT NULL,
  `MaHocPhan` VARCHAR(30) NOT NULL,
  `TrangThai` ENUM('DangHoc','DaHoanThanh')
    NOT NULL DEFAULT 'DangHoc',
  CONSTRAINT `FK_SinhVienHocPhan_SinhVien`
    FOREIGN KEY (`MSSV`) REFERENCES `SinhVien` (`MSSV`),
  CONSTRAINT `FK_SinhVienHocPhan_HocPhan`
    FOREIGN KEY (`MaHocPhan`) REFERENCES `HocPhan` (`MaHocPhan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('SinhVienHocPhan');
    }
};
