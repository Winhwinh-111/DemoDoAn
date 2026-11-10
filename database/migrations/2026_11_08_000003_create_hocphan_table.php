<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `HocPhan` (
  `MaHocPhan` VARCHAR(30) NOT NULL,
  `TenHocPhan` VARCHAR(255) NOT NULL,
  `SoTinChi` INT NULL,
  `MoTa` LONGTEXT NULL,
  PRIMARY KEY (`MaHocPhan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('HocPhan');
    }
};
