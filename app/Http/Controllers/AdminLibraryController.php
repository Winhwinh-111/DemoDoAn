<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TaiLieu;
use App\Models\MuonTra;
use App\Models\PhanHoi;
use App\Models\LichSuTimKiem;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\DB;
use App\Models\SinhVien;
use Illuminate\Validation\ValidationException;

class AdminLibraryController extends Controller
{
    public function dashboard()
    {
        // 1. Dữ liệu tổng quan
        $totalBooks = TaiLieu::count();
        $totalBorrowing = MuonTra::where('TrangThai', 'DangMuon')->count(); // Đang cho mượn
        $recentBorrows = MuonTra::with(['taiLieu', 'sinhVien'])->orderBy('NgayMuon', 'desc')->limit(5)->get();

        // 2. Dữ liệu thống kê 
        $totalFeedback = PhanHoi::count();
        $goodFeedback = PhanHoi::where('MucDanhGia', 'PhuHop')->count();
        $badFeedback = PhanHoi::where('MucDanhGia', 'KhongPhuHop')->count();

        $topKeywords = LichSuTimKiem::select('TuKhoa', DB::raw('count(*) as count'))
            ->groupBy('TuKhoa')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        // Thống kê mượn trả 
        $totalBorrowedHistory = MuonTra::count(); // Tổng số lượng đã mượn
        $totalReturned = MuonTra::where('TrangThai', 'DaTra')->count(); // Tổng số lượng đã trả

        return view('admin.library.dashboard', compact(
            'totalBooks', 'totalBorrowing', 'totalFeedback', 'recentBorrows',
            'goodFeedback', 'badFeedback', 'topKeywords',
            'totalBorrowedHistory', 'totalReturned'
        ));
    }

    public function documentsIndex(Request $request)
    {
        $keyword = $request->get('keyword', '');
        $query = TaiLieu::query();

        if (!empty($keyword)) {
            $query->where('NhanDe', 'LIKE', "%{$keyword}%")
                  ->orWhere('TacGia', 'LIKE', "%{$keyword}%")
                  ->orWhere('MaTaiLieu', 'LIKE', "%{$keyword}%");
        }

        $documents = $query->orderBy('MaTaiLieu', 'desc')->paginate(10);
        return view('admin.library.documents', compact('documents', 'keyword'));
    }

    
    public function documentsStore(Request $request)
    {
        $data = $request->validate([
            'MaTaiLieu' => 'required|string|max:30|unique:TaiLieu,MaTaiLieu',
            'NhanDe' => 'required|string|max:500',
            'TacGia' => 'nullable|string|max:255',
            'NhaXuatBan' => 'nullable|string|max:255',
            'NamXuatBan' => 'nullable|integer|between:1000,9999',
            'NgonNgu' => 'nullable|string|max:50',
            'ISBN' => 'nullable|string|max:30',
            'ThuatNguChuDe' => 'nullable|string',
            'MoTa' => 'nullable|string',
            'NoiLuuGiu' => 'nullable|string|max:255',
            'TrangThaiSuDung' => 'nullable|in:DangSuDung,NgungSuDung',
            'AnhBia' => 'nullable|string|max:500',
        ]);

        $data['TrangThaiSuDung'] =
            $data['TrangThaiSuDung'] ?? 'DangSuDung';

        TaiLieu::create($data);

        return redirect()->back()
            ->with('success', 'Thêm tài liệu thành công!');
    }

    public function documentsUpdate(Request $request, $id)
    {
        $doc = TaiLieu::findOrFail($id);

        $data = $request->validate([
            'NhanDe' => 'required|string|max:500',
            'TacGia' => 'nullable|string|max:255',
            'NhaXuatBan' => 'nullable|string|max:255',
            'NamXuatBan' => 'nullable|integer|between:1000,9999',
            'NgonNgu' => 'nullable|string|max:50',
            'ISBN' => 'nullable|string|max:30',
            'ThuatNguChuDe' => 'nullable|string',
            'MoTa' => 'nullable|string',
            'NoiLuuGiu' => 'nullable|string|max:255',
            'TrangThaiSuDung' => 'required|in:DangSuDung,NgungSuDung',
            'AnhBia' => 'nullable|string|max:500',
        ]);

        $doc->update($data);

        return redirect()->back()
            ->with('success', 'Cập nhật tài liệu thành công!');
    }
        
    public function documentsDelete($id)
    {
        $doc = TaiLieu::findOrFail($id);

        if ($doc->TrangThaiSuDung === 'NgungSuDung') {
            return redirect()->back()
                ->with('success', 'Tài liệu đã ngừng sử dụng!');
        }

        $doc->update([
            'TrangThaiSuDung' => 'NgungSuDung'
        ]);

        return redirect()->back()
            ->with('success', 'Đã ngừng sử dụng tài liệu!');
    }

    public function documentsImport(Request $request)
    {
        $request->validate([
            'file_import' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
        ]);

        try {
            $spreadsheet = IOFactory::load($request->file('file_import')->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

            $aliases = [
                'MaTaiLieu' => ['matailieu', 'matl', 'masach'],
                'NhanDe' => ['nhande', 'tensach', 'tentailieu', 'tieude', 'title'],
                'TacGia' => ['tacgia', 'author'],
                'NhaXuatBan' => ['nhaxuatban', 'nxb', 'publisher'],
                'NamXuatBan' => ['namxuatban', 'namxb', 'nam', 'publicationyear'],
                'NgonNgu' => ['ngonngu', 'language'],
                'ISBN' => ['isbn'],
                'ThuatNguChuDe' => ['thuatnguchude', 'chude', 'thuatngu', 'keywords'],
                'MoTa' => ['mota', 'tomtat', 'description'],
                'NoiLuuGiu' => ['noiluugiu', 'vitri', 'kho', 'location'],
                'TrangThaiSuDung' => ['trangthaisudung', 'trangthai', 'status'],
                'AnhBia' => ['anhbia', 'hinhbia', 'cover', 'image', 'url'],
            ];

            $normalize = static function ($value): string {
                $value = preg_replace('/^\xEF\xBB\xBF/', '', trim((string) ($value ?? '')));
                return preg_replace('/[^a-z0-9]/', '', strtolower(\Illuminate\Support\Str::ascii($value)));
            };

            $headerIndex = null;
            $positions = [];

            foreach ($rows as $rowIndex => $row) {
                $headers = array_map($normalize, $row);
                $current = [];

                foreach ($aliases as $field => $names) {
                    $current[$field] = null;
                    foreach ($headers as $position => $header) {
                        if (in_array($header, $names, true)) {
                            $current[$field] = $position;
                            break;
                        }
                    }
                }

                if ($current['MaTaiLieu'] !== null && $current['NhanDe'] !== null) {
                    $headerIndex = $rowIndex;
                    $positions = $current;
                    break;
                }
            }

            if ($headerIndex === null) {
                return back()->with('error', 'File thiếu hai cột MaTaiLieu và NhanDe.');
            }

            $created = 0;
            $updated = 0;
            $summary = [
                'duplicate_file' => 0,
                'missing_code' => 0,
                'missing_title' => 0,
                'invalid_data' => 0,
                'database_error' => 0,
            ];
            $details = [];
            $seen = [];

            foreach (array_slice($rows, $headerIndex + 1) as $index => $row) {
                if (!collect($row)->contains(fn ($cell) => trim((string) ($cell ?? '')) !== '')) {
                    continue;
                }

                $line = $headerIndex + $index + 2;
                $data = [];

                foreach ($positions as $field => $position) {
                    if ($position === null) {
                        continue;
                    }

                    $value = $row[$position] ?? null;
                    $value = is_scalar($value) ? trim((string) $value) : null;
                    $data[$field] = $value === '' ? null : $value;
                }

                if (empty($data['MaTaiLieu'])) {
                    $summary['missing_code']++;
                    $details[] = "Dòng {$line}: Thiếu mã tài liệu.";
                    continue;
                }

                if (empty($data['NhanDe'])) {
                    $summary['missing_title']++;
                    $details[] = "Dòng {$line}: Thiếu nhan đề.";
                    continue;
                }

                $key = mb_strtolower($data['MaTaiLieu'], 'UTF-8');
                if (isset($seen[$key])) {
                    $summary['duplicate_file']++;
                    $details[] = "Dòng {$line}: Mã {$data['MaTaiLieu']} trùng trong file.";
                    continue;
                }
                $seen[$key] = true;

                $validator = \Illuminate\Support\Facades\Validator::make($data, [
                    'MaTaiLieu' => 'required|string|max:30',
                    'NhanDe' => 'required|string|max:500',
                    'TacGia' => 'nullable|string|max:255',
                    'NhaXuatBan' => 'nullable|string|max:255',
                    'NamXuatBan' => 'nullable|integer|between:1000,9999',
                    'NgonNgu' => 'nullable|string|max:50',
                    'ISBN' => 'nullable|string|max:30',
                    'ThuatNguChuDe' => 'nullable|string',
                    'MoTa' => 'nullable|string',
                    'NoiLuuGiu' => 'nullable|string|max:255',
                    'TrangThaiSuDung' => 'nullable|in:DangSuDung,NgungSuDung',
                    'AnhBia' => 'nullable|string|max:500',
                ]);

                if ($validator->fails()) {
                    $summary['invalid_data']++;
                    $details[] = "Dòng {$line}: " . implode('; ', $validator->errors()->all());
                    continue;
                }

                $code = $data['MaTaiLieu'];
                unset($data['MaTaiLieu']);

                try {
                    $wasUpdated = DB::transaction(function () use ($code, $data) {
                        $document = TaiLieu::where('MaTaiLieu', $code)->lockForUpdate()->first();

                        if ($document) {
                            $changes = array_filter($data, static fn ($value) => $value !== null);
                            if ($changes !== []) {
                                $document->update($changes);
                            }
                            return true;
                        }

                        $data['TrangThaiSuDung'] = $data['TrangThaiSuDung'] ?? 'DangSuDung';
                        TaiLieu::create(['MaTaiLieu' => $code] + $data);
                        return false;
                    });

                    $wasUpdated ? $updated++ : $created++;
                } catch (\Throwable $e) {
                    report($e);
                    $summary['database_error']++;
                    $details[] = "Dòng {$line}: Không thể lưu mã {$code}.";
                }
            }

            $skipped = array_sum($summary);
            return back()
                ->with('success', "Import hoàn tất: thêm {$created}, cập nhật {$updated}, bỏ qua {$skipped} dòng.")
                ->with('import_summary', $summary)
                ->with('import_details', $details);
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Không thể đọc file. Vui lòng kiểm tra định dạng.');
        }
    }

    // Hiển thị danh sách mượn trả
    public function borrowIndex()
    {
        $borrowList = MuonTra::with(['taiLieu', 'sinhVien'])->orderBy('NgayMuon', 'desc')->paginate(10);
        return view('admin.library.borrow', compact('borrowList'));
    }

    // Tạo phiếu mượn tài liệu mới từ Modal
    
    public function borrowStore(Request $request)
    {
        $data = $request->validate([
            'MSSV' => 'required|string|max:30',
            'MaTaiLieu' => 'required|string|max:30',
            'GhiChu' => 'nullable|string|max:1000'
        ]);

        try {
            DB::transaction(function () use ($data) {
                $student = SinhVien::where(
                    'MSSV',
                    $data['MSSV']
                )->first();

                if (!$student) {
                    throw ValidationException::withMessages([
                        'MSSV' => 'Sinh viên không tồn tại.'
                    ]);
                }

                $now = now();

                if (
                    $student->HanThe !== null &&
                    \Carbon\Carbon::parse($student->HanThe)
                        ->startOfDay()
                        ->lt($now->copy()->startOfDay())
                ) {
                    throw ValidationException::withMessages([
                        'MSSV' => 'Thẻ thư viện của sinh viên đã hết hạn.'
                    ]);
                }

                $document = TaiLieu::where(
                    'MaTaiLieu',
                    $data['MaTaiLieu']
                )->lockForUpdate()->first();

                if (!$document) {
                    throw ValidationException::withMessages([
                        'MaTaiLieu' => 'Tài liệu không tồn tại.'
                    ]);
                }

                if ($document->TrangThaiSuDung !== 'DangSuDung') {
                    throw ValidationException::withMessages([
                        'MaTaiLieu' => 'Tài liệu đã ngừng sử dụng.'
                    ]);
                }

                $isBorrowed = MuonTra::where(
                    'MaTaiLieu',
                    $document->MaTaiLieu
                )->where(
                    'TrangThai',
                    'DangMuon'
                )->exists();

                if ($isBorrowed) {
                    throw ValidationException::withMessages([
                        'MaTaiLieu' => 'Tài liệu hiện đang được mượn.'
                    ]);
                }

                MuonTra::create([
                    'MaGiaoDich' => 'GD_' . time(),
                    'MSSV' => $student->MSSV,
                    'MaTaiLieu' => $document->MaTaiLieu,
                    'NgayMuon' => $now,
                    'HanTra' => $now->copy()->addDays(15),
                    'NgayTra' => null,
                    'TrangThai' => 'DangMuon',
                    'GhiChu' => $data['GhiChu'] ?? null
                ]);
            }, 3);

            return redirect()->back()->with(
                'success',
                'Tạo phiếu mượn tài liệu thành công!'
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->with(
                'error',
                'Không thể tạo phiếu mượn. Vui lòng thử lại.'
            );
        }
    }

    // Cập nhật trạng thái trả sách
    public function borrowReturnStatus(Request $request, $id)
    {
        $data = $request->validate([
            'GhiChu' => 'nullable|string|max:1000'
        ]);

        try {
            DB::transaction(function () use ($id, $data) {
                $borrow = MuonTra::where(
                    'MaGiaoDich',
                    $id
                )->lockForUpdate()->firstOrFail();

                if ($borrow->TrangThai !== 'DangMuon') {
                    throw ValidationException::withMessages([
                        'TrangThai' => 'Giao dịch này đã được xác nhận trả.'
                    ]);
                }

                $updates = [
                    'NgayTra' => now(),
                    'TrangThai' => 'DaTra'
                ];

                if (
                    isset($data['GhiChu']) &&
                    trim($data['GhiChu']) !== ''
                ) {
                    $oldNote = trim((string) $borrow->GhiChu);
                    $newNote = trim($data['GhiChu']);

                    $note = $oldNote === ''
                        ? $newNote
                        : $oldNote . "\n" . $newNote;

                    if (mb_strlen($note) > 1000) {
                        throw ValidationException::withMessages([
                            'GhiChu' => 'Tổng độ dài ghi chú vượt quá 1000 ký tự.'
                        ]);
                    }

                    $updates['GhiChu'] = $note;
                }

                $borrow->update($updates);
            }, 3);

            return redirect()->back()->with(
                'success',
                'Xác nhận trả tài liệu thành công!'
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->with(
                'error',
                'Không thể xác nhận trả tài liệu.'
            );
        }
    }
}