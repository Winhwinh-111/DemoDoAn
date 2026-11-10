<?php

namespace App\Services;

use App\Models\CauHinhGoiY;
use App\Models\MoHinhGoiY;
use App\Models\SinhVien;
use App\Models\TaiLieu;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RecommendationService
{
    public function getRecommendationsForStudent(
        string $mssv,
        ?int $topN = null,
        ?float $minThreshold = null,
        ?bool &$usedMajorFallback = null
    ): array {
        $usedMajorFallback = false;
        $student = SinhVien::find($mssv);
        if (!$student) {
            return [];
        }

        $config = Cache::remember('app_latest_config', 86400, static function () {
            $current = CauHinhGoiY::orderByDesc('ThoiDiemTao')
                ->orderByDesc('MaCauHinh')
                ->first();

            return $current ? [
                'TopN' => (int) $current->TopN,
                'NguongCosine' => (float) $current->NguongCosine,
            ] : [
                'TopN' => 10,
                'NguongCosine' => 0.15,
            ];
        });

        $topN = $topN ?? (int) $config['TopN'];
        $minThreshold = $minThreshold ?? (float) $config['NguongCosine'];
        if ($topN < 1 || !is_finite($minThreshold) || $minThreshold < 0 || $minThreshold > 1) {
            throw new \RuntimeException('Cấu hình gợi ý không hợp lệ.');
        }

        $activeModel = MoHinhGoiY::where('LaHienHanh', 1)
            ->orderByDesc('ThoiDiemTao')
            ->first();

        if (!$activeModel || !$activeModel->DuongDanTep) {
            throw new \RuntimeException('Chưa có mô hình gợi ý hiện hành. Quản trị viên hệ thống cần cập nhật mô hình UC13.');
        }

        $artifact = Cache::remember('hub_tfidf_model_' . $activeModel->MaMoHinh, 3600, function () use ($activeModel) {
            $relative = str_replace('\\', '/', (string) $activeModel->DuongDanTep);
            if (!preg_match('~^models/[a-zA-Z0-9_.-]+\.json$~', $relative)) {
                throw new \RuntimeException('Đường dẫn mô hình gợi ý không hợp lệ.');
            }

            $path = storage_path('app/' . $relative);
            if (!is_file($path) || !is_readable($path)) {
                throw new \RuntimeException('Không đọc được tệp mô hình gợi ý hiện hành.');
            }

            $json = file_get_contents($path);
            if ($json === false) {
                throw new \RuntimeException('Không đọc được nội dung mô hình gợi ý.');
            }

            try {
                $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new \RuntimeException('Tệp mô hình JSON không hợp lệ.', 0, $e);
            }

            if (!is_array($data)
                || ($data['format_version'] ?? null) !== 1
                || ($data['algorithm'] ?? null) !== 'content_based_tfidf_cosine') {
                throw new \RuntimeException('Phiên bản hoặc thuật toán mô hình không tương thích với UC03.');
            }

            foreach (['vocabulary', 'idf', 'course_vectors', 'document_vectors'] as $field) {
                if (!isset($data[$field]) || !is_array($data[$field]) || $data[$field] === []) {
                    throw new \RuntimeException('Tệp mô hình thiếu dữ liệu: ' . $field);
                }
            }

            if (count($data['vocabulary']) !== count($data['idf'])) {
                throw new \RuntimeException('Số chiều từ vựng và IDF trong mô hình không khớp.');
            }

            return $data;
        });

        $courseVectors = $artifact['course_vectors'];
        $documentVectors = $artifact['document_vectors'];

        $personalCodes = DB::table('SinhVienHocPhan')
            ->where('MSSV', $mssv)
            ->whereIn('TrangThai', ['DangHoc', 'DaHoanThanh'])
            ->pluck('MaHocPhan')
            ->all();

        $validCodes = array_values(array_unique(array_filter($personalCodes, static function ($code) use ($courseVectors) {
            return isset($courseVectors[$code]) && is_array($courseVectors[$code]) && $courseVectors[$code] !== [];
        })));

        if ($validCodes === []) {
            $majorCodes = DB::table('ChuongTrinhDaoTao')
                ->where('MaNganh', $student->MaNganh)
                ->pluck('MaHocPhan')
                ->all();

            $validCodes = array_values(array_unique(array_filter($majorCodes, static function ($code) use ($courseVectors) {
                return isset($courseVectors[$code]) && is_array($courseVectors[$code]) && $courseVectors[$code] !== [];
            })));
            $usedMajorFallback = $validCodes !== [];
        }

        if ($validCodes === []) {
            return [];
        }

        $courseNames = DB::table('HocPhan')
            ->whereIn('MaHocPhan', $validCodes)
            ->pluck('TenHocPhan', 'MaHocPhan')
            ->all();

        $borrowed = DB::table('MuonTra')
            ->where('MSSV', $mssv)
            ->pluck('MaTaiLieu')
            ->all();

        $eligibleDocuments = TaiLieu::query()
            ->where('TrangThaiSuDung', 'DangSuDung')
            ->whereNotIn('MaTaiLieu', $borrowed)
            ->whereIn('MaTaiLieu', array_keys($documentVectors))
            ->get()
            ->keyBy('MaTaiLieu');

        $ranked = [];
        foreach ($eligibleDocuments as $code => $document) {
            $docVector = $documentVectors[$code] ?? [];
            if (!is_array($docVector) || $docVector === []) {
                continue;
            }

            $bestScore = -1.0;
            $bestCourse = null;
            foreach ($validCodes as $courseCode) {
                $score = $this->cosineFromNormalizedVectors($courseVectors[$courseCode], $docVector);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestCourse = $courseCode;
                }
            }

            if ($bestScore >= $minThreshold && $bestCourse !== null) {
                $document->scores = $bestScore;
                $document->reason = 'Học phần: ' . ($courseNames[$bestCourse] ?? $bestCourse);
                $ranked[] = $document;
            }
        }

        usort($ranked, static function ($a, $b) {
            $scoreOrder = $b->scores <=> $a->scores;
            return $scoreOrder !== 0 ? $scoreOrder : strcmp((string) $a->MaTaiLieu, (string) $b->MaTaiLieu);
        });

        return array_slice($ranked, 0, $topN);
    }

    private function cosineFromNormalizedVectors(array $left, array $right): float
    {
        if (count($left) > count($right)) {
            [$left, $right] = [$right, $left];
        }

        $sum = 0.0;
        foreach ($left as $index => $weight) {
            if (isset($right[$index]) && is_numeric($weight) && is_numeric($right[$index])) {
                $sum += (float) $weight * (float) $right[$index];
            }
        }

        return max(0.0, min(1.0, $sum));
    }
}