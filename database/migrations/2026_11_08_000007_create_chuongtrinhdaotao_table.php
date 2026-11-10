<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `ChuongTrinhDaoTao` (
  `MaNganh` VARCHAR(30) NOT NULL,
  `MaHocPhan` VARCHAR(30) NOT NULL,
  CONSTRAINT `FK_ChuongTrinhDaoTao_NganhHoc`
    FOREIGN KEY (`MaNganh`) REFERENCES `NganhHoc` (`MaNganh`),
  CONSTRAINT `FK_ChuongTrinhDaoTao_HocPhan`
    FOREIGN KEY (`MaHocPhan`) REFERENCES `HocPhan` (`MaHocPhan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('ChuongTrinhDaoTao');
    }
};
