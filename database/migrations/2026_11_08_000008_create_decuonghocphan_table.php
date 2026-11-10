<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `DeCuongHocPhan` (
  `MaHocPhan` VARCHAR(30) NOT NULL,
  `MaTaiLieu` VARCHAR(30) NOT NULL,
  `NoiDungDeCuong` LONGTEXT NULL,
  `LoaiTaiLieu` ENUM('GiaoTrinh','ThamKhao')
    NOT NULL DEFAULT 'ThamKhao',
  CONSTRAINT `FK_DeCuongHocPhan_HocPhan`
    FOREIGN KEY (`MaHocPhan`) REFERENCES `HocPhan` (`MaHocPhan`),
  CONSTRAINT `FK_DeCuongHocPhan_TaiLieu`
    FOREIGN KEY (`MaTaiLieu`) REFERENCES `TaiLieu` (`MaTaiLieu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('DeCuongHocPhan');
    }
};
