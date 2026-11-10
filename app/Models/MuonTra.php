<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MuonTra extends Model
{
    protected $table = 'MuonTra';

    protected $primaryKey = 'MaGiaoDich';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'MaGiaoDich',
        'MSSV',
        'MaTaiLieu',
        'NgayMuon',
        'HanTra',
        'NgayTra',
        'TrangThai',
        'GhiChu'
    ];

    protected $casts = [
        'NgayMuon' => 'datetime',
        'HanTra' => 'datetime',
        'NgayTra' => 'datetime'
    ];

    public function taiLieu()
    {
        return $this->belongsTo(
            TaiLieu::class,
            'MaTaiLieu',
            'MaTaiLieu'
        );
    }

    public function sinhVien()
    {
        return $this->belongsTo(
            SinhVien::class,
            'MSSV',
            'MSSV'
        );
    }
}
