<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `NganhHoc` (
  `MaNganh` VARCHAR(30) NOT NULL,
  `TenNganh` VARCHAR(200) NOT NULL,
  PRIMARY KEY (`MaNganh`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('NganhHoc');
    }
};
