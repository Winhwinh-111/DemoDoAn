<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `TaiLieu` (
  `MaTaiLieu` VARCHAR(30) NOT NULL,
  `NhanDe` VARCHAR(500) NOT NULL,
  `TacGia` VARCHAR(255) NULL,
  `NhaXuatBan` VARCHAR(255) NULL,
  `NamXuatBan` INT NULL,
  `NgonNgu` VARCHAR(50) NULL,
  `ISBN` VARCHAR(30) NULL,
  `ThuatNguChuDe` LONGTEXT NULL,
  `MoTa` LONGTEXT NULL,
  `NoiLuuGiu` VARCHAR(255) NULL,
  `TrangThaiSuDung` ENUM('DangSuDung','NgungSuDung')
    NOT NULL DEFAULT 'DangSuDung',
  `AnhBia` VARCHAR(500) NULL,
  PRIMARY KEY (`MaTaiLieu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('TaiLieu');
    }
};
