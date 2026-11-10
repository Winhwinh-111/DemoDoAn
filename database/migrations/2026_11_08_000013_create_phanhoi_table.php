<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `PhanHoi` (
  `MaPhanHoi` VARCHAR(30) NOT NULL,
  `MSSV` VARCHAR(30) NOT NULL,
  `MaTaiLieu` VARCHAR(30) NOT NULL,
  `MucDanhGia` ENUM('PhuHop','KhongPhuHop') NOT NULL,
  `ThoiDiem` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`MaPhanHoi`),
  UNIQUE KEY `UQ_PhanHoi_MSSV_MaTaiLieu` (`MSSV`,`MaTaiLieu`),
  CONSTRAINT `FK_PhanHoi_SinhVien`
    FOREIGN KEY (`MSSV`) REFERENCES `SinhVien` (`MSSV`),
  CONSTRAINT `FK_PhanHoi_TaiLieu`
    FOREIGN KEY (`MaTaiLieu`) REFERENCES `TaiLieu` (`MaTaiLieu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('PhanHoi');
    }
};
