<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MoHinhGoiY;
use App\Models\CauHinhGoiY;
use App\Models\SinhVien;
use App\Services\RecommendationService;
use Illuminate\Support\Facades\DB;

try {
    $activeModel = MoHinhGoiY::where('LaHienHanh', 1)
        ->orderByDesc('ThoiDiemTao')->first();
    if (!$activeModel) {
        throw new RuntimeException('Chua co mo hinh hien hanh; can chay UC13 truoc.');
    }

    // Cung cach chon cau hinh voi RecommendationService (UC03).
    $activeConfig = CauHinhGoiY::orderByDesc('ThoiDiemTao')
        ->orderByDesc('MaCauHinh')->first();
    if (!$activeConfig) {
        throw new RuntimeException('Chua co cau hinh goi y.');
    }

    $topN = (int) $activeConfig->TopN;
    $threshold = (float) $activeConfig->NguongCosine;
    if ($topN <= 0 || !is_finite($threshold) || $threshold < 0 || $threshold > 1) {
        throw new RuntimeException('TopN hoac nguong Cosine khong hop le.');
    }

    // De tai lap: giu danh sach MSSV va du lieu CSDL khong doi giua cac lan chay.
    // Co the thay [] bang danh sach MSSV co dinh, vi du ['SV001','SV002'].
    $fixedStudentIds = [];
    $studentQuery = SinhVien::query()->orderBy('MSSV');
    if ($fixedStudentIds !== []) {
        $studentQuery->whereIn('MSSV', $fixedStudentIds);
    } else {
        $studentQuery->limit(50);
    }
    $students = $studentQuery->get();

    $service = app(RecommendationService::class);
    $evaluationData = [];
    $skipped = 0;
    $fallbackCount = 0;

    foreach ($students as $student) {
        // Dung CHINH RecommendationService de xac dinh nhanh du phong theo nganh.
        $usedMajorFallback = false;
        $recommendations = $service->getRecommendationsForStudent(
            (string) $student->MSSV, $topN, $threshold, $usedMajorFallback
        );

        if ($usedMajorFallback) {
            $courseCodes = DB::table('ChuongTrinhDaoTao')
                ->where('MaNganh', $student->MaNganh)->pluck('MaHocPhan')->all();
        } else {
            $courseCodes = DB::table('SinhVienHocPhan')
                ->where('MSSV', $student->MSSV)
                ->whereIn('TrangThai', ['DangHoc', 'DaHoanThanh'])
                ->pluck('MaHocPhan')->all();
        }

        // Tap tham chieu duoc xay dung tu lien ket de cuong, KHONG PHAI nhan doc lap.
        // Chi lay tai lieu con duoc phep goi y theo cac rang buoc nghiep vu UC03.
        $borrowed = DB::table('MuonTra')->where('MSSV', $student->MSSV)
            ->pluck('MaTaiLieu')->all();
        $groundTruth = DB::table('DeCuongHocPhan as d')
            ->join('TaiLieu as t', 't.MaTaiLieu', '=', 'd.MaTaiLieu')
            ->whereIn('d.MaHocPhan', $courseCodes)
            ->where('t.TrangThaiSuDung', 'DangSuDung')
            ->whereNotIn('d.MaTaiLieu', $borrowed)
            ->distinct()->orderBy('d.MaTaiLieu')->pluck('d.MaTaiLieu')->all();

        if ($groundTruth === []) {
            $skipped++;
            continue;
        }

        $predictedItems = array_map(
            static fn ($item) => (string) $item->MaTaiLieu,
            $recommendations
        );
        $evaluationData[] = [
            'mssv' => (string) $student->MSSV,
            'nguon_goi_y' => $usedMajorFallback ? 'TheoNganh' : 'HocPhanCaNhan',
            'ground_truth' => array_values(array_map('strval', $groundTruth)),
            'predictions' => array_values($predictedItems),
        ];
        if ($usedMajorFallback) {
            $fallbackCount++;
        }
    }

    echo json_encode([
        'status' => 'success',
        'metadata' => [
            'ma_mo_hinh' => $activeModel->MaMoHinh,
            'ten_mo_hinh' => $activeModel->TenMoHinh ?? 'Mo hinh hien hanh',
            'ma_cau_hinh' => $activeConfig->MaCauHinh,
            'top_n' => $topN,
            'nguong_cosine' => $threshold,
            'so_sinh_vien' => count($evaluationData),
            'so_sinh_vien_bo_qua_khong_co_tap_tham_chieu' => $skipped,
            'so_sinh_vien_du_phong_theo_nganh' => $fallbackCount,
            'phuong_phap_ground_truth' => 'Lien ket de cuong, loc tai lieu DangSuDung va chua tung muon',
            'timestamp' => date('Y-m-d H:i:s'),
        ],
        'data' => $evaluationData,
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit(1);
}
