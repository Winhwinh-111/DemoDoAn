<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\RecommendationService;
use App\Models\SinhVien;
use App\Models\TaiLieu;
use App\Models\LichSuTimKiem;
use App\Models\DanhDau;
use App\Models\PhanHoi;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    protected $recommendationService;

    public function __construct(RecommendationService $recommendationService)
    {
        $this->recommendationService = $recommendationService;
    }

    // Trang chủ, Gợi ý tài liệu và Tra cứu tài liệu
    public function index(Request $request)
    {
        $sinhVien = null;
        $recommendedBooks = [];
        $recommendationError = null;
        $allBooks = [];
        $searchResults = null;
        $markedBookIds = [];
        $isSuggestedByMajor = false;

        $keyword = trim($request->get('keyword', ''));
        $category = $request->get('category', 'Tất cả danh mục');

        // Lấy danh sách 6 Từ khóa nổi bật được tìm kiếm nhiều nhất
        $topKeywords = LichSuTimKiem::select('TuKhoa', DB::raw('count(*) as total'))
            ->groupBy('TuKhoa')
            ->orderBy('total', 'desc')
            ->limit(6)
            ->pluck('TuKhoa');

        // Check trạng thái đăng nhập của sinh viên
        if (Session::has('user_id') && Session::get('role') === 'SinhVien') {
            $mssv = Session::get('MSSV');
            $sinhVien = SinhVien::find($mssv);

            // Lấy danh sách mã tài liệu mà sinh viên đã đánh dấu
            $markedBookIds = DanhDau::where('MSSV', $mssv)->pluck('MaTaiLieu')->toArray();

            // Ghi nhận lịch sử tìm kiếm vào CSDL
            if (!empty($keyword)) {
                LichSuTimKiem::create([
                    'MaLuotTimKiem'       => 'SK_' . time() . '_' . rand(100, 999),
                    'MSSV'              => $mssv,
                    'TuKhoa'           => $keyword,
                    'ThoiDiemTimKiem' => now(),
                ]);
            }

            // Gọi gợi ý tài liệu cá nhân hóa (Tự động lấy tham số Top-N & Ngưỡng từ CSDL)
            if ($sinhVien) {
                try {
                    $recommendedBooks = $this->recommendationService
                        ->getRecommendationsForStudent($mssv, null, null, $isSuggestedByMajor);
                } catch (\RuntimeException $e) {
                    report($e);
                    $recommendationError = $e->getMessage();
                    $recommendedBooks = [];
                }
            }
        }

        if (!empty($keyword)) {
            $query = TaiLieu::query();
            $query->where(function ($q) use ($keyword) {
                $q->where('NhanDe', 'LIKE', "%{$keyword}%")
                  ->orWhere('TacGia', 'LIKE', "%{$keyword}%")
                  ->orWhere('ThuatNguChuDe', 'LIKE', "%{$keyword}%")
                  ->orWhere('MoTa', 'LIKE', "%{$keyword}%");
            });

            // Lọc thêm theo Danh mục
            if ($category === 'Sách in') {
                $query->where('MaTaiLieu', 'LIKE', 'HUB%');
            } elseif ($category === 'Sách điện tử') {
                $query->where('MaTaiLieu', 'LIKE', 'TLE%');
            } elseif ($category === 'Đề cương HP') {
                $query->where('MaTaiLieu', 'LIKE', 'DC%');
            }

            $searchResults = $query->paginate(12)->appends(request()->query());
        } else {
            $allBooks = TaiLieu::limit(12)->get();
        }

        return view('home', compact(
            'sinhVien', 
            'recommendedBooks',
            'recommendationError', 
            'allBooks', 
            'searchResults', 
            'keyword', 
            'category',
            'markedBookIds',
            'topKeywords',
            'isSuggestedByMajor'
        ));
    }

    // API Gợi ý hoàn tất từ khóa Autocomplete AJAX
    public function autocomplete(Request $request)
    {
        $query = trim($request->get('query', ''));
        if (empty($query)) {
            return response()->json([]);
        }

        $suggestions = LichSuTimKiem::select('TuKhoa')
            ->where('TuKhoa', 'LIKE', "%{$query}%")
            ->distinct()
            ->limit(5)
            ->pluck('TuKhoa');

        return response()->json($suggestions);
    }

    // Đánh dấu / Bỏ đánh dấu tài liệu
    public function toggleBookmark(Request $request)
    {
        if (!Session::has('user_id') || Session::get('role') !== 'SinhVien') {
            return redirect()->back()->withErrors(['error' => 'Vui lòng đăng nhập tài khoản Sinh viên để sử dụng chức năng đánh dấu!']);
        }

        $mssv = Session::get('MSSV');
        $maTaiLieu = $request->input('MaTaiLieu');

        if (!is_string($maTaiLieu) || trim($maTaiLieu) === '') {
            return redirect()->back()->with('error', 'Không xác định được mã tài liệu cần đánh dấu.');
        }

        $maTaiLieu = trim($maTaiLieu);
        if (!TaiLieu::where('MaTaiLieu', $maTaiLieu)->exists()) {
            return redirect()->back()->with('error', 'Tài liệu không tồn tại trong hệ thống.');
        }

        $danhDau = DanhDau::where('MSSV', $mssv)->where('MaTaiLieu', $maTaiLieu)->first();

        if ($danhDau) {
            $danhDau->delete();
            return redirect()->back()->with('success', 'Đã bỏ đánh dấu tài liệu khỏi danh sách yêu thích!');
        } else {
            DanhDau::create([
                'MaDanhDau'        => 'DD_' . time() . '_' . rand(10, 99),
                'MSSV'               => $mssv,
                'MaTaiLieu'        => $maTaiLieu,
                'ThoiDiemDanhDau' => now(),
            ]);
            return redirect()->back()->with('success', 'Đã đánh dấu tài liệu vào danh sách yêu thích!');
        }
    }

    // Màn hình xem danh sách tài liệu Sinh viên đã đánh dấu
    public function bookmarks()
    {
        if (!Session::has('user_id') || Session::get('role') !== 'SinhVien') {
            return redirect()->route('login');
        }

        $mssv = Session::get('MSSV');
        $sinhVien = SinhVien::find($mssv);

        $markedBooks = TaiLieu::join('DanhDau', 'TaiLieu.MaTaiLieu', '=', 'DanhDau.MaTaiLieu')
            ->where('DanhDau.MSSV', $mssv)
            ->orderBy('DanhDau.ThoiDiemDanhDau', 'desc')
            ->select('TaiLieu.*', 'DanhDau.ThoiDiemDanhDau')
            ->get();

        return view('bookmarks', compact('sinhVien', 'markedBooks'));
    }

    // Gửi phản hồi về mức độ phù hợp của tài liệu được gợi ý
    public function submitFeedback(Request $request)
    {
        if (!Session::has('user_id') || Session::get('role') !== 'SinhVien') {
            return redirect()->back()->with('error', 'Vui lòng đăng nhập bằng tài khoản sinh viên để gửi phản hồi.');
        }

        $mssv = Session::get('MSSV');
        if (!$mssv || !SinhVien::where('MSSV', $mssv)->exists()) {
            return redirect()->back()->with('error', 'Không xác định được hồ sơ sinh viên.');
        }

        $validator = Validator::make($request->all(), [
            'MaTaiLieu' => ['required', 'string', 'max:30', 'exists:TaiLieu,MaTaiLieu'],
            'MucDanhGia' => ['required', 'in:PhuHop,KhongPhuHop'],
        ], [
            'MaTaiLieu.required' => 'Vui lòng chọn tài liệu cần phản hồi.',
            'MaTaiLieu.exists' => 'Tài liệu không tồn tại.',
            'MucDanhGia.required' => 'Vui lòng chọn mức đánh giá.',
            'MucDanhGia.in' => 'Mức đánh giá không hợp lệ.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->errors()->first());
        }

        $data = $validator->validated();
        $maTaiLieu = $data['MaTaiLieu'];

        try {
            $recommendations = $this->recommendationService->getRecommendationsForStudent($mssv);
        } catch (\RuntimeException $e) {
            report($e);
            return redirect()->back()->with('error', 'Chưa thể xác minh danh sách gợi ý. Vui lòng thử lại.');
        }

        $isRecommended = collect($recommendations)->contains(
            fn ($book) => (string) $book->MaTaiLieu === (string) $maTaiLieu
        );

        if (!$isRecommended) {
            return redirect()->back()->with('error', 'Tài liệu không thuộc danh sách gợi ý hiện tại của bạn.');
        }

        $phanHoi = PhanHoi::firstOrNew([
            'MSSV' => $mssv,
            'MaTaiLieu' => $maTaiLieu,
        ]);

        if (!$phanHoi->exists) {
            $phanHoi->MaPhanHoi = 'PH_' . now()->format('ymdHis') . '_' . strtoupper(Str::random(6));
        }

        $phanHoi->MucDanhGia = $data['MucDanhGia'];
        $phanHoi->ThoiDiem = now();
        $phanHoi->save();

        return redirect()->back()->with('success', 'Cảm ơn bạn đã phản hồi về mức độ phù hợp của gợi ý!');
    }

    // Xem thông tin cá nhân và danh sách học phần
    public function profile()
    {
        if (!Session::has('user_id')) {
            return redirect()->route('login');
        }

        $mssv = Session::get('MSSV');
        $sinhVien = SinhVien::with(['hocPhans', 'nganh'])->find($mssv);

        return view('profile', compact('sinhVien'));
    }

    // Xem lịch sử mượn trả
    public function history()
    {
        if (!Session::has('user_id')) {
            return redirect()->route('login');
        }

        $mssv = Session::get('MSSV');
        $sinhVien = SinhVien::find($mssv);

        $lichSuMuon = \App\Models\MuonTra::with('taiLieu')
            ->where('MSSV', $mssv)
            ->orderBy('NgayMuon', 'desc')
            ->get();

        return view('history', compact('sinhVien', 'lichSuMuon'));
    }
}