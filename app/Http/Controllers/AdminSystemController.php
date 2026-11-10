<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CauHinhGoiY;
use App\Models\MoHinhGoiY;
use App\Models\SinhVien;
use App\Models\HocPhan;
use App\Models\NganhHoc;
use App\Models\MuonTra;
use App\Models\TaiLieu;
use App\Services\NlpTokenizerService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AdminSystemController extends Controller
{
    public function dashboard()
    {
        $studentCount = SinhVien::count();
        $courseCount = HocPhan::count();
        $majorCount = NganhHoc::count();
        $borrowCount = MuonTra::count();
        
        $activeModel = MoHinhGoiY::where('LaHienHanh', 1)->first();
        $totalModels = MoHinhGoiY::count();

        return view('admin.system.dashboard', compact(
            'studentCount', 'courseCount', 'majorCount', 'borrowCount', 
            'activeModel', 'totalModels'
        ));
    }

    public function importDataView()
    {
        return view('admin.system.import');
    }

    public function processImportData(Request $request)
    {
        $request->validate([
            'data_type'   => 'required',
            'file_import' => 'required|file|mimes:csv,txt,xlsx,xls|max:5000',
        ], [
            'data_type.required'   => 'Vui lòng chọn loại dữ liệu đào tạo cần nhập!',
            'file_import.required' => 'Vui lòng chọn tệp dữ liệu!',
            'file_import.mimes'    => 'Hệ thống chỉ hỗ trợ định dạng .csv, .xlsx, .xls!',
            'file_import.max'      => 'Dung lượng tệp không được vượt quá 5MB!',
        ]);

        $type = strtolower(trim($request->input('data_type')));

        try {
            $file = $request->file('file_import');
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (empty($rows) || count($rows) < 2) {
                return redirect()->back()->with('error', 'Tệp dữ liệu rỗng hoặc chỉ có dòng tiêu đề!');
            }

            $headerRow = array_shift($rows);

            $normalize = function ($value) {
                if ($value === null) return '';
                $value = mb_strtolower(trim((string) $value));
                $value = Str::ascii($value);
                return preg_replace('/[^a-z0-9]/', '', $value);
            };

            $findColIdx = function ($keywords) use ($headerRow, $normalize) {
                foreach ($headerRow as $index => $header) {
                    $normalizedHeader = $normalize($header);
                    foreach ($keywords as $keyword) {
                        if ($normalizedHeader !== '' && $normalizedHeader === $keyword) {
                            return $index;
                        }
                    }
                }
                return null;
            };

            $getCell = function ($data, $index) {
                if ($index === null || !isset($data[$index])) return null;
                $value = trim((string) $data[$index]);
                return $value === '' ? null : $value;
            };

            $validLength = static function ($value, int $max): bool {
                return $value === null || mb_strlen((string) $value, 'UTF-8') <= $max;
            };

            $maHpIdx      = $findColIdx(['mahocphan', 'mamonhoc', 'mahp', 'mamon']);
            $tenHpIdx     = $findColIdx(['tenhocphan', 'tenmonhoc', 'tenhp', 'tenmon']);
            $stcIdx       = $findColIdx(['sotinchi', 'stc', 'sotc', 'tinchi']);
            $moTaIdx      = $findColIdx(['mota', 'ghichu', 'tomtat']);
            $maTlIdx      = $findColIdx(['matailieu', 'matl', 'masach']);
            $maNganhIdx   = $findColIdx(['manganh', 'machuyennganh', 'manch']);
            $tenNganhIdx  = $findColIdx(['tennganh', 'tenchuyennganh', 'tench']);
            $mssvIdx      = $findColIdx(['mssv', 'masinhvien', 'masv', 'mathe']);
            $hoTenIdx     = $findColIdx(['hoten', 'tensinhvien', 'tensv']);
            $ngaySinhIdx  = $findColIdx(['ngaysinh', 'ns']);
            $gioiTinhIdx  = $findColIdx(['gioitinh', 'phai']);
            $sdtIdx       = $findColIdx(['sodienthoai', 'sdt', 'dienthoai', 'phone']);
            $hanTheIdx    = $findColIdx(['hanthe', 'ngayhethan']);
            $maTkIdx      = $findColIdx(['mataikhoan', 'matk', 'taikhoan']);
            $loaiTlIdx    = $findColIdx(['loaitailieu', 'loaitl']);
            $ndDeCuongIdx = $findColIdx(['noidungdecuong', 'decuong']);
            $trangThaiIdx = $findColIdx(['trangthai', 'trangthaihoc']);

            $structureError = null;

            if (in_array($type, ['nganh_hoc', 'nganhhoc'])) {
                if ($maNganhIdx === null || $tenNganhIdx === null) $structureError = 'Tệp ngành học phải có cột Mã ngành và Tên ngành.';
            } elseif (in_array($type, ['hoc_phan', 'hocphan'])) {
                if ($maHpIdx === null || $tenHpIdx === null) $structureError = 'Tệp học phần phải có cột Mã học phần và Tên học phần.';
            } elseif (in_array($type, ['de_cuong_hoc_phan', 'decuonghocphan'])) {
                if ($maHpIdx === null || $maTlIdx === null || ($ndDeCuongIdx === null && $moTaIdx === null)) {
                    $structureError = 'Tệp đề cương phải có cột Mã học phần, Mã tài liệu và Nội dung đề cương.';
                }
            } elseif (in_array($type, ['chuong_trinh_dao_tao', 'chuongtrinhdaotao'])) {
                if ($maNganhIdx === null || $maHpIdx === null) $structureError = 'Tệp chương trình đào tạo phải có cột Mã ngành và Mã học phần.';
            } elseif (in_array($type, ['sinh_vien', 'sinhvien'])) {
                if ($mssvIdx === null || $hoTenIdx === null || $maNganhIdx === null || $maTkIdx === null) {
                    $structureError = 'Tệp sinh viên phải có cột MSSV, Họ tên, Mã ngành và Mã tài khoản.';
                }
            } elseif (in_array($type, ['sinh_vien_hoc_phan', 'sinhvienhocphan'])) {
                if ($mssvIdx === null || $maHpIdx === null) $structureError = 'Tệp sinh viên - học phần phải có cột MSSV và Mã học phần.';
            } else {
                $structureError = 'Loại dữ liệu nhập không được hỗ trợ.';
            }

            if ($structureError) {
                return redirect()->back()->with('error', $structureError);
            }

            $parseDate = function ($value) {
                if ($value === null || trim((string) $value) === '') return null;
                if (is_numeric($value) && (float) $value > 0) {
                    try {
                        return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
                    } catch (\Throwable $e) {
                        return false;
                    }
                }
                $value = trim((string) $value);
                foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $format) {
                    $date = \DateTime::createFromFormat('!' . $format, $value);
                    if ($date && $date->format($format) === $value) return $date->format('Y-m-d');
                }
                return false;
            };

            $countSuccess = 0;
            $countSkipped = 0;
            $errors = [];
            $processedKeys = [];

            DB::beginTransaction();

            foreach ($rows as $rowIndex => $data) {
                if (empty(array_filter($data, fn($v) => $v !== null && trim((string)$v) !== ''))) continue;
                $lineNum = $rowIndex + 2;

                /* 1. NGÀNH HỌC */
                if (in_array($type, ['nganh_hoc', 'nganhhoc'])) {
                    $maNganh = $getCell($data, $maNganhIdx);
                    $tenNganh = $getCell($data, $tenNganhIdx);

                    if (empty($maNganh) || empty($tenNganh)) { $countSkipped++; $errors[] = "Dòng {$lineNum}: Thiếu mã ngành hoặc tên ngành."; continue; }
                    if (!$validLength($maNganh, 30) || !$validLength($tenNganh, 200)) {
                        $countSkipped++;
                        $errors[] = "Dòng {$lineNum}: Mã ngành hoặc tên ngành vượt độ dài cho phép.";
                        continue;
                    }
                    if (NganhHoc::where('MaNganh', $maNganh)->exists()) { $countSkipped++; $errors[] = "Dòng {$lineNum}: Mã ngành [{$maNganh}] đã tồn tại."; continue; }
                    if (in_array($maNganh, $processedKeys, true)) { $countSkipped++; $errors[] = "Dòng {$lineNum}: Mã ngành [{$maNganh}] bị trùng trong tệp."; continue; }

                    NganhHoc::create(['MaNganh' => $maNganh, 'TenNganh' => $tenNganh]);
                    $processedKeys[] = $maNganh;
                    $countSuccess++;
                }

                /* 2. HỌC PHẦN */
                elseif (in_array($type, ['hoc_phan', 'hocphan'])) {
                    $maHocPhan = $getCell($data, $maHpIdx);
                    $tenHocPhan = $getCell($data, $tenHpIdx);
                    $stcValue = $getCell($data, $stcIdx);
                    $moTa = $getCell($data, $moTaIdx);

                    if (empty($maHocPhan) || empty($tenHocPhan)) { $countSkipped++; $errors[] = "Dòng {$lineNum}: Thiếu mã học phần hoặc tên học phần."; continue; }
                    if (!$validLength($maHocPhan, 30) || !$validLength($tenHocPhan, 255)) {
                        $countSkipped++;
                        $errors[] = "Dòng {$lineNum}: Mã học phần hoặc tên học phần vượt độ dài cho phép.";
                        continue;
                    }
                    if (HocPhan::where('MaHocPhan', $maHocPhan)->exists()) { $countSkipped++; $errors[] = "Dòng {$lineNum}: Mã học phần [{$maHocPhan}] đã tồn tại."; continue; }
                    if (in_array($maHocPhan, $processedKeys, true)) { $countSkipped++; $errors[] = "Dòng {$lineNum}: Mã học phần [{$maHocPhan}] bị trùng trong tệp."; continue; }

                    $soTinChi = null;
                    if ($stcValue !== null) {
                        $soTinChi = filter_var($stcValue, FILTER_VALIDATE_INT);
                        if ($soTinChi === false || $soTinChi < 0 || $soTinChi > 10) {
                            $countSkipped++;
                            $errors[] = "Dòng {$lineNum}: Số tín chỉ phải là số nguyên không âm hợp lệ.";
                            continue;
                        }
                    }

                    HocPhan::create([
                        'MaHocPhan' => $maHocPhan,
                        'TenHocPhan' => $tenHocPhan,
                        'SoTinChi' => $soTinChi,
                        'MoTa' => $moTa
                    ]);
                    $processedKeys[] = $maHocPhan;
                    $countSuccess++;
                }

                /* 3. ĐỀ CƯƠNG HỌC PHẦN */
                elseif (in_array($type, ['de_cuong_hoc_phan', 'decuonghocphan'])) {
                    $maHocPhan   = $getCell($data, $maHpIdx);
                    $maTaiLieu   = $getCell($data, $maTlIdx);
                    $noiDung     = $getCell($data, $ndDeCuongIdx ?? $moTaIdx);
                    $rawLoaiTl   = $getCell($data, $loaiTlIdx);

                    if (empty($maHocPhan) || empty($maTaiLieu) || empty($noiDung)) { $countSkipped++; $errors[] = "Dòng {$lineNum}: Thiếu mã học phần, mã tài liệu hoặc nội dung đề cương."; continue; }
                    if (!$validLength($maHocPhan, 30) || !$validLength($maTaiLieu, 30)) {
                        $countSkipped++;
                        $errors[] = "Dòng {$lineNum}: Mã học phần hoặc mã tài liệu vượt quá 30 ký tự.";
                        continue;
                    }
                    if (!HocPhan::where('MaHocPhan', $maHocPhan)->exists()) { $countSkipped++; $errors[] = "Dòng {$lineNum}: Học phần [{$maHocPhan}] chưa tồn tại."; continue; }
                    if (!TaiLieu::where('MaTaiLieu', $maTaiLieu)->exists()) { $countSkipped++; $errors[] = "Dòng {$lineNum}: Tài liệu [{$maTaiLieu}] chưa tồn tại."; continue; }

                    $key = $maHocPhan . '|' . $maTaiLieu;
                    if (DB::table('DeCuongHocPhan')->where('MaHocPhan', $maHocPhan)->where('MaTaiLieu', $maTaiLieu)->exists() || in_array($key, $processedKeys, true)) {
                        $countSkipped++; $errors[] = "Dòng {$lineNum}: Cặp học phần [{$maHocPhan}] - tài liệu [{$maTaiLieu}] đã tồn tại trong đề cương."; continue;
                    }

                    $normalizedLoai = mb_strtolower(trim((string) ($rawLoaiTl ?? '')), 'UTF-8');            
                    $loaiMap = [
                        'giáo trình chính' => 'GiaoTrinh',
                        'giáo trình' => 'GiaoTrinh',
                        'giaotrinhchinh' => 'GiaoTrinh',
                        'giaotrinh' => 'GiaoTrinh',
                        'giao trinh' => 'GiaoTrinh',
                        'tài liệu tham khảo' => 'ThamKhao',
                        'tham khảo' => 'ThamKhao',
                        'thamkhao' => 'ThamKhao',
                        'tham khao' => 'ThamKhao'
                    ];
                    if ($rawLoaiTl === null) {
                        $loaiTaiLieu = 'ThamKhao';
                    } else {
                        $loaiTaiLieu = $loaiMap[$normalizedLoai] ?? null;

                        if ($loaiTaiLieu === null) {
                            $countSkipped++;
                            $errors[] = "Dòng {$lineNum}: Loại tài liệu không hợp lệ.";
                            continue;
                        }
                    }
                    DB::table('DeCuongHocPhan')->insert([
                        'MaHocPhan' => $maHocPhan, 'MaTaiLieu' => $maTaiLieu,
                        'NoiDungDeCuong' => $noiDung, 'LoaiTaiLieu' => $loaiTaiLieu
                    ]);
                    $processedKeys[] = $key;
                    $countSuccess++;
                }

                /* 4. CHƯƠNG TRÌNH ĐÀO TẠO */
                elseif (in_array($type, ['chuong_trinh_dao_tao', 'chuongtrinhdaotao'])) {
                    $maNganh = $getCell($data, $maNganhIdx);
                    $maHocPhan = $getCell($data, $maHpIdx);
                    if (empty($maNganh) || empty($maHocPhan)) { $countSkipped++; $errors[] = "Dòng {$lineNum}: Thiếu mã ngành hoặc mã học phần."; continue; }
                    if (!$validLength($maNganh, 30) || !$validLength($maHocPhan, 30)) {
                        $countSkipped++;
                        $errors[] = "Dòng {$lineNum}: Mã ngành hoặc mã học phần vượt quá 30 ký tự.";
                        continue;
                    }
                    if (!NganhHoc::where('MaNganh', $maNganh)->exists()) { $countSkipped++; $errors[] = "Dòng {$lineNum}: Mã ngành [{$maNganh}] chưa tồn tại."; continue; }
                    if (!HocPhan::where('MaHocPhan', $maHocPhan)->exists()) { $countSkipped++; $errors[] = "Dòng {$lineNum}: Mã học phần [{$maHocPhan}] chưa tồn tại."; continue; }

                    $key = $maNganh . '|' . $maHocPhan;
                    if (DB::table('ChuongTrinhDaoTao')->where('MaNganh', $maNganh)->where('MaHocPhan', $maHocPhan)->exists() || in_array($key, $processedKeys, true)) {
                        $countSkipped++; $errors[] = "Dòng {$lineNum}: Cặp ngành - học phần đã tồn tại."; continue;
                    }

                    DB::table('ChuongTrinhDaoTao')->insert(['MaNganh' => $maNganh, 'MaHocPhan' => $maHocPhan]);
                    $processedKeys[] = $key;
                    $countSuccess++;
                }

                /* 5. HỒ SƠ SINH VIÊN */
                elseif (in_array($type, ['sinh_vien', 'sinhvien'])) {
                    $mssv = $getCell($data, $mssvIdx);
                    $hoTen = $getCell($data, $hoTenIdx);
                    $maNganh = $getCell($data, $maNganhIdx);
                    $maTk = $getCell($data, $maTkIdx);

                    if (empty($mssv) || empty($hoTen)) { $countSkipped++; $errors[] = "Dòng {$lineNum}: Thiếu MSSV hoặc họ tên sinh viên."; continue; }
                    if (!$validLength($mssv, 30) || !$validLength($hoTen, 200) || !$validLength($maNganh, 30) || !$validLength($maTk, 30)) {
                        $countSkipped++;
                        $errors[] = "Dòng {$lineNum}: Thông tin sinh viên vượt độ dài cho phép.";
                        continue;
                    }
                    if (SinhVien::where('MSSV', $mssv)->exists() || in_array($mssv, $processedKeys, true)) {
                        $countSkipped++; $errors[] = "Dòng {$lineNum}: MSSV [{$mssv}] đã tồn tại."; continue;
                    }

                    if (empty($maNganh) || !NganhHoc::where('MaNganh', $maNganh)->exists()) {
                        $countSkipped++; $errors[] = "Dòng {$lineNum}: Mã ngành [{$maNganh}] không tồn tại hoặc bị trống."; continue;
                    }

                    if (empty($maTk)) {
                        $countSkipped++;
                        $errors[] = "Dòng {$lineNum}: Thiếu mã tài khoản.";
                        continue;
                    }

                    $taiKhoan = DB::table('TaiKhoan')
                        ->where('MaTaiKhoan', $maTk)
                        ->first();

                    if (!$taiKhoan) {
                        $countSkipped++;
                        $errors[] = "Dòng {$lineNum}: Tài khoản [{$maTk}] không tồn tại.";
                        continue;
                    }

                    if ($taiKhoan->VaiTro !== 'SinhVien') {
                        $countSkipped++;
                        $errors[] = "Dòng {$lineNum}: Tài khoản [{$maTk}] không thuộc vai trò SinhVien.";
                        continue;
                    }

                    if (SinhVien::where('MaTaiKhoan', $maTk)->exists()) {
                        $countSkipped++;
                        $errors[] = "Dòng {$lineNum}: Tài khoản [{$maTk}] đã được liên kết với sinh viên khác.";
                        continue;
                    }

                    $ngaySinhValue = $getCell($data, $ngaySinhIdx);
                    $hanTheValue = $getCell($data, $hanTheIdx);
                    $ngaySinh = $parseDate($ngaySinhValue);
                    $hanThe = $parseDate($hanTheValue);

                    if ($ngaySinhValue !== null && $ngaySinh === false) { $countSkipped++; $errors[] = "Dòng {$lineNum}: Ngày sinh không đúng định dạng."; continue; }
                    if ($hanTheValue !== null && $hanThe === false) { $countSkipped++; $errors[] = "Dòng {$lineNum}: Hạn thẻ không đúng định dạng."; continue; }

                    $gioiTinhRaw = mb_strtolower(trim((string) ($getCell($data, $gioiTinhIdx) ?? 'khác')), 'UTF-8');
                    $genderMap = ['1' => 1, 'nam' => 1, 'm' => 1, 'male' => 1, '0' => 0, 'nữ' => 0, 'nu' => 0, 'f' => 0, 'female' => 0, '2' => 2, 'khác' => 2, 'khac' => 2, 'other' => 2];
                    if (!array_key_exists($gioiTinhRaw, $genderMap)) {
                        $countSkipped++; $errors[] = "Dòng {$lineNum}: Giới tính [{$gioiTinhRaw}] không hợp lệ (Chấp nhận: Nam, Nữ, Khác)."; continue;
                    }
                    $gioiTinh = $genderMap[$gioiTinhRaw];

                    $sdt = $getCell($data, $sdtIdx);
                    if ($sdt !== null && !preg_match('/^[0-9]{9,11}$/', $sdt)) {
                        $countSkipped++; $errors[] = "Dòng {$lineNum}: Số điện thoại không hợp lệ."; continue;
                    }

                    SinhVien::create([
                        'MSSV' => $mssv, 'HoTen' => $hoTen, 'NgaySinh' => $ngaySinh,
                        'GioiTinh' => $gioiTinh, 'SoDienThoai' => $sdt,
                        'HanThe' => $hanThe, 'MaNganh' => $maNganh,
                        'MaTaiKhoan' => $maTk
                    ]);
                    $processedKeys[] = $mssv;
                    $countSuccess++;
                }

                /* 6. SINH VIÊN - HỌC PHẦN */
                elseif (in_array($type, ['sinh_vien_hoc_phan', 'sinhvienhocphan'])) {
                    $mssv = $getCell($data, $mssvIdx);
                    $maHocPhan = $getCell($data, $maHpIdx);

                    if (empty($mssv) || empty($maHocPhan)) { $countSkipped++; $errors[] = "Dòng {$lineNum}: Thiếu MSSV hoặc mã học phần."; continue; }
                    if (!$validLength($mssv, 30) || !$validLength($maHocPhan, 30)) {
                        $countSkipped++;
                        $errors[] = "Dòng {$lineNum}: MSSV hoặc mã học phần vượt quá 30 ký tự.";
                        continue;
                    }
                    if (!SinhVien::where('MSSV', $mssv)->exists()) { $countSkipped++; $errors[] = "Dòng {$lineNum}: Sinh viên [{$mssv}] chưa tồn tại."; continue; }
                    if (!HocPhan::where('MaHocPhan', $maHocPhan)->exists()) { $countSkipped++; $errors[] = "Dòng {$lineNum}: Học phần [{$maHocPhan}] chưa tồn tại."; continue; }

                    $key = $mssv . '|' . $maHocPhan;
                    if (DB::table('SinhVienHocPhan')->where('MSSV', $mssv)->where('MaHocPhan', $maHocPhan)->exists() || in_array($key, $processedKeys, true)) {
                        $countSkipped++; $errors[] = "Dòng {$lineNum}: Cặp sinh viên - học phần đã tồn tại."; continue;
                    }

                    $rawTrangThai = mb_strtolower(trim((string) ($getCell($data, $trangThaiIdx) ?? 'đang học')), 'UTF-8');
                    $statusMap = ['đang học' => 'DangHoc', 'danghoc' => 'DangHoc', 'đã hoàn thành' => 'DaHoanThanh', 'dahoanthanh' => 'DaHoanThanh'];
                    $trangThai = $statusMap[$rawTrangThai] ?? null;

                    if ($trangThai === null) { $countSkipped++; $errors[] = "Dòng {$lineNum}: Trạng thái học phần không hợp lệ."; continue; }

                    DB::table('SinhVienHocPhan')->insert(['MSSV' => $mssv, 'MaHocPhan' => $maHocPhan, 'TrangThai' => $trangThai]);
                    $processedKeys[] = $key;
                    $countSuccess++;
                }
            }

            if ($countSuccess === 0) {
                DB::rollBack();
                $errorDetails = implode(' | ', array_slice($errors, 0, 8));
                return redirect()->back()->with('error', 'Không có bản ghi hợp lệ để nhập. ' . ($errorDetails ?: 'Vui lòng kiểm tra lại dữ liệu trong tệp.'));
            }

            DB::commit();
            try { Cache::forget('app_latest_config'); } catch (\Throwable $e) {}

            $message = "Nhập dữ liệu hoàn tất! Thành công: {$countSuccess} bản ghi.";
            if ($countSkipped > 0) {
                $errorDetails = implode(' | ', array_slice($errors, 0, 5));
                $message .= " Bỏ qua {$countSkipped} dòng lỗi hoặc trùng. Chi tiết: {$errorDetails}";
            }

            return redirect()->back()->with('success', $message);

        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) DB::rollBack();
            return redirect()->back()->with('error', 'Lỗi xử lý tệp: ' . $e->getMessage());
        }
    }

    public function configView()
    {
        $config = CauHinhGoiY::orderByDesc('ThoiDiemTao')
            ->orderByDesc('MaCauHinh')
            ->first();

        if (!$config) {
            $config = new CauHinhGoiY([
                'TopN' => 10,
                'NguongCosine' => 0.15
            ]);
        }

        $activeModel = MoHinhGoiY::where('LaHienHanh', 1)
            ->first();

        return view(
            'admin.system.config',
            compact('config', 'activeModel')
        );
    }
    public function saveConfig(Request $request)
    {
        $data = $request->validate([
            'TopN' => 'required|integer|min:1',
            'NguongCosine' => 'required|numeric|between:0,1'
        ], [
            'TopN.required' => 'Vui lòng nhập Top-N.',
            'TopN.integer' => 'Top-N phải là số nguyên.',
            'TopN.min' => 'Top-N phải lớn hơn 0.',
            'NguongCosine.required' => 'Vui lòng nhập ngưỡng Cosine.',
            'NguongCosine.numeric' => 'Ngưỡng Cosine phải là số.',
            'NguongCosine.between' => 'Ngưỡng Cosine phải từ 0 đến 1.'
        ]);

        $topN = (int) $data['TopN'];
        $nguongCosine = (float) $data['NguongCosine'];

        try {
            $result = DB::transaction(function () use (
                $topN,
                $nguongCosine
            ) {
                $current = CauHinhGoiY::orderByDesc('ThoiDiemTao')
                    ->orderByDesc('MaCauHinh')
                    ->first();

                if (
                    $current &&
                    (int) $current->TopN === $topN &&
                    abs((float) $current->NguongCosine - $nguongCosine) < 0.000001
                ) {
                    return [
                        'changed' => false,
                        'id' => $current->MaCauHinh
                    ];
                }

                $maCauHinh = 'CH_'
                    . now()->format('YmdHis')
                    . '_'
                    . strtoupper(Str::random(6));

                CauHinhGoiY::create([
                    'MaCauHinh' => $maCauHinh,
                    'MaTaiKhoan' => session('user_id'),
                    'TopN' => $topN,
                    'NguongCosine' => $nguongCosine,
                    'ThoiDiemTao' => now()
                ]);

                return [
                    'changed' => true,
                    'id' => $maCauHinh
                ];
            }, 3);

            Cache::forget('app_latest_config');

            if (!$result['changed']) {
                return redirect()->back()->with(
                    'success',
                    'Tham số không thay đổi. Đã giữ cấu hình hiện tại.'
                );
            }

            return redirect()->back()->with(
                'success',
                "Đã lưu cấu hình mới [{$result['id']}] thành công!"
            );
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->with(
                'error',
                'Không thể lưu cấu hình gợi ý.'
            );
        }
    }
        /* CHỨC NĂNG CẬP NHẬT MÔ HÌNH GỢI Ý */
    public function retrainModel()
    {
        $tempPath = null;
        $finalPath = null;

        try {
            $config = CauHinhGoiY::orderByDesc('ThoiDiemTao')
                ->orderByDesc('MaCauHinh')
                ->first();

            if (!$config) {
                return back()->with('error', 'Chưa có cấu hình gợi ý hợp lệ.');
            }

            $courses = DB::table('HocPhan as hp')
                ->join('DeCuongHocPhan as dc', 'hp.MaHocPhan', '=', 'dc.MaHocPhan')
                ->whereNotNull('dc.NoiDungDeCuong')
                ->whereRaw("TRIM(dc.NoiDungDeCuong) <> ''")
                ->select('hp.MaHocPhan', 'hp.TenHocPhan', 'hp.MoTa', 'dc.NoiDungDeCuong')
                ->get()
                ->groupBy('MaHocPhan');

            $courseData = [];
            foreach ($courses as $code => $records) {
                $first = $records->first();
                $outlines = $records->pluck('NoiDungDeCuong')->implode(' ');
                $courseData[$code] = [
                    'text' => trim($first->TenHocPhan . ' ' . ($first->MoTa ?? '') . ' ' . $outlines),
                ];
            }

            $documents = TaiLieu::where('TrangThaiSuDung', 'DangSuDung')
                ->get(['MaTaiLieu', 'NhanDe', 'ThuatNguChuDe', 'MoTa', 'NgonNgu']);

            $documentData = [];
            foreach ($documents as $document) {
                $documentData[$document->MaTaiLieu] = [
                    'text' => trim($document->NhanDe . ' ' . ($document->ThuatNguChuDe ?? '') . ' ' . ($document->MoTa ?? '')),
                    'language' => $document->NgonNgu,
                ];
            }

            if (!$courseData || !$documentData) {
                return back()->with('error', 'Không đủ dữ liệu học phần có đề cương hoặc tài liệu đang sử dụng để tạo mô hình.');
            }

            $script = storage_path('app/public/scripts/build_tfidf_model.py');
            if (!is_file($script)) {
                return back()->with('error', 'Không tìm thấy script Python xây dựng mô hình.');
            }

            $python = (string) config('services.python.executable', 'python');
            if (trim($python) === '') {
                throw new \RuntimeException('Chưa cấu hình đường dẫn thực thi Python.');
            }

            $processEnv = [
                'PYTHONIOENCODING' => 'utf-8',
                'PYTHONUTF8' => '1',
            ];

            if (PHP_OS_FAMILY === 'Windows') {
                $systemRoot = getenv('SystemRoot')
                    ?: getenv('SYSTEMROOT')
                    ?: ($_SERVER['SystemRoot'] ?? null)
                    ?: ($_ENV['SystemRoot'] ?? null)
                    ?: getenv('WINDIR')
                    ?: 'C:\\Windows';

                $systemRoot = rtrim(str_replace('/', '\\', $systemRoot), '\\');
                if (!is_dir($systemRoot . DIRECTORY_SEPARATOR . 'System32')) {
                    throw new \RuntimeException('Không tìm thấy thư mục System32 của Windows: ' . $systemRoot);
                }

                $processEnv['SystemRoot'] = $systemRoot;
                $processEnv['WINDIR'] = $systemRoot;
                $processEnv['SystemDrive'] = substr($systemRoot, 0, 2);

                $pythonDir = dirname($python);
                $existingPath = getenv('PATH') ?: getenv('Path') ?: '';
                $paths = [
                    $pythonDir,
                    $pythonDir . DIRECTORY_SEPARATOR . 'Library' . DIRECTORY_SEPARATOR . 'bin',
                    $pythonDir . DIRECTORY_SEPARATOR . 'DLLs',
                    $systemRoot . DIRECTORY_SEPARATOR . 'System32',
                    $systemRoot,
                ];
                $paths = array_values(array_filter($paths, 'is_dir'));
                $processEnv['PATH'] = implode(PATH_SEPARATOR, $paths)
                    . ($existingPath !== '' ? PATH_SEPARATOR . $existingPath : '');
            }

            $preflight = new \Symfony\Component\Process\Process(
                [$python, '-c', 'import _overlapped; from sklearn.feature_extraction.text import TfidfVectorizer; print("UC13_PYTHON_OK")'],
                base_path(),
                $processEnv
            );
            $preflight->setTimeout(60);
            $preflight->run();
            if (!$preflight->isSuccessful()) {
                $stderr = trim($preflight->getErrorOutput());
                \Illuminate\Support\Facades\Log::error('UC13 Python môi trường thất bại', [
                    'exit_code' => $preflight->getExitCode(),
                    'python' => $python,
                    'system_root' => $processEnv['SystemRoot'] ?? null,
                    'stderr' => $stderr,
                ]);
                throw new \RuntimeException(
                    'Không khởi tạo được thư viện Python/Windows cho UC13. Xem mục "UC13 Python môi trường thất bại" trong laravel.log.'
                );
            }

            $process = new \Symfony\Component\Process\Process(
                [$python, $script],
                base_path(),
                $processEnv
            );

            $process->setTimeout(900);
            $process->setInput(json_encode([
                'courses' => $courseData,
                'documents' => $documentData,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $process->run();

            if (!$process->isSuccessful()) {
                $pythonError = trim($process->getErrorOutput());
                \Illuminate\Support\Facades\Log::error('UC13 Python TF-IDF thất bại', [
                    'exit_code' => $process->getExitCode(),
                    'python' => $python,
                    'script' => $script,
                    'stderr' => $pythonError,
                    'stdout' => trim($process->getOutput()),
                ]);
                throw new \RuntimeException('Python TF-IDF thất bại. Xem stderr trong storage/logs/laravel.log.');
            }

            $output = $process->getOutput();
            $artifact = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
            foreach (['vocabulary', 'idf', 'course_vectors', 'document_vectors'] as $field) {
                if (empty($artifact[$field]) || !is_array($artifact[$field])) {
                    throw new \RuntimeException('Mô hình JSON thiếu trường hợp lệ: ' . $field);
                }
            }
            if (count($artifact['vocabulary']) !== count($artifact['idf'])) {
                throw new \RuntimeException('Từ vựng và IDF không cùng số chiều.');
            }
            if (array_diff(array_keys($courseData), array_keys($artifact['course_vectors']))
                || array_diff(array_keys($documentData), array_keys($artifact['document_vectors']))) {
                throw new \RuntimeException('Danh sách vector không khớp dữ liệu đầu vào.');
            }

            $directory = storage_path('app/models');
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new \RuntimeException('Không thể tạo thư mục lưu mô hình.');
            }

            $modelId = 'MH_' . now()->format('ymdHis') . '_' . strtoupper(\Illuminate\Support\Str::random(5));
            $fileName = 'tfidf_' . $modelId . '.json';
            $tempPath = $directory . DIRECTORY_SEPARATOR . $fileName . '.tmp';
            $finalPath = $directory . DIRECTORY_SEPARATOR . $fileName;
            $relativePath = 'models/' . $fileName;

            if (file_put_contents($tempPath, $output, LOCK_EX) === false) {
                throw new \RuntimeException('Không thể ghi mô hình tạm thời.');
            }
            $readBack = json_decode(file_get_contents($tempPath), true, 512, JSON_THROW_ON_ERROR);
            if ($readBack !== $artifact || !rename($tempPath, $finalPath)) {
                throw new \RuntimeException('Không thể xác minh hoặc hoàn tất tệp mô hình.');
            }
            $tempPath = null;

            DB::transaction(function () use ($modelId, $config, $relativePath) {
                MoHinhGoiY::where('LaHienHanh', 1)->update(['LaHienHanh' => 0]);
                MoHinhGoiY::create([
                    'MaMoHinh' => $modelId,
                    'MaTaiKhoan' => session('user_id'),
                    'MaCauHinh' => $config->MaCauHinh,
                    'ThoiDiemTao' => now(),
                    'DuongDanTep' => $relativePath,
                    'LaHienHanh' => 1,
                ]);
            }, 3);

            return back()->with('success', 'Đã xây dựng và kích hoạt mô hình TF-IDF ' . $modelId . '.');
        } catch (\Throwable $error) {
            report($error);
            if ($tempPath && is_file($tempPath)) {
                @unlink($tempPath);
            }
            if ($finalPath && is_file($finalPath)) {
                @unlink($finalPath);
            }
            return back()->with('error', 'Cập nhật mô hình thất bại: ' . $error->getMessage());
        }
    }
}