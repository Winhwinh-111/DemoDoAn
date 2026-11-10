<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `MuonTra` (
  `MaGiaoDich` VARCHAR(30) NOT NULL,
  `MSSV` VARCHAR(30) NOT NULL,
  `MaTaiLieu` VARCHAR(30) NOT NULL,
  `NgayMuon` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `HanTra` DATETIME NOT NULL,
  `NgayTra` DATETIME NULL,
  `TrangThai` ENUM('DangMuon','DaTra') NOT NULL DEFAULT 'DangMuon',
  `GhiChu` VARCHAR(1000) NULL,
  PRIMARY KEY (`MaGiaoDich`),
  CONSTRAINT `FK_MuonTra_SinhVien`
    FOREIGN KEY (`MSSV`) REFERENCES `SinhVien` (`MSSV`),
  CONSTRAINT `FK_MuonTra_TaiLieu`
    FOREIGN KEY (`MaTaiLieu`) REFERENCES `TaiLieu` (`MaTaiLieu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `TR_MuonTra_TinhHanTra`
BEFORE INSERT ON `MuonTra`
FOR EACH ROW
SET NEW.`HanTra` = DATE_ADD(
  COALESCE(NEW.`NgayMuon`, CURRENT_TIMESTAMP),
  INTERVAL 15 DAY
);
SQL
        );
    }

    public function down(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS `TR_MuonTra_TinhHanTra`");
        Schema::dropIfExists('MuonTra');
    }
};
