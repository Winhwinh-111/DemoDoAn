import subprocess
import json
import math
import os
from datetime import datetime
import openpyxl
from openpyxl.chart import LineChart, Reference
from openpyxl.styles import Font, PatternFill, Alignment, Border, Side
from openpyxl.utils import get_column_letter

def calculate_precision_k(ground_truth, predictions, k):
    if not predictions or k <= 0: 
        return 0.0
    top_k = [str(x) for x in predictions[:k]]
    gt = set(str(x) for x in ground_truth)
    hits = set(top_k).intersection(gt)
    return len(hits) / k

def calculate_recall_k(ground_truth, predictions, k):
    if not ground_truth or k <= 0: 
        return 0.0
    top_k = [str(x) for x in predictions[:k]]
    gt = set(str(x) for x in ground_truth)
    hits = set(top_k).intersection(gt)
    return len(hits) / len(gt)

def calculate_ndcg_k(ground_truth, predictions, k):
    if not predictions or not ground_truth or k <= 0:
        return 0.0
    dcg = 0.0
    idcg = 0.0
    top_k = [str(x) for x in predictions[:k]]
    gt = set(str(x) for x in ground_truth)
    
    for i, item in enumerate(top_k):
        if item in gt:
            dcg += 1.0 / math.log2(i + 2)
            
    for i in range(min(k, len(gt))):
        idcg += 1.0 / math.log2(i + 2)
        
    return dcg / idcg if idcg > 0 else 0.0

def run_evaluation():
    print(">>> Đang kết nối CSDL và chạy mô hình hiện hành...")
    result = subprocess.run(['php', 'evaluation_bridge.php'], capture_output=True, text=True, encoding='utf-8', errors='replace', cwd=os.path.dirname(os.path.abspath(__file__)))
    
    try:
        response = json.loads(result.stdout)
    except json.JSONDecodeError:
        print("Lỗi đọc JSON từ bridge PHP. Kết quả đầu ra:", result.stdout)
        print("Chi tiết stderr:", result.stderr)
        return

    if response.get('status') == 'error':
        print(f"Lỗi hệ thống: {response.get('message')}")
        print("Chi tiết stderr:", result.stderr)
        return

    meta = response['metadata']
    data = response['data']
    num_users = len(data)
    
    if num_users == 0:
        print("Không có sinh viên hợp lệ để đánh giá.")
        return

    # Lựa chọn K hợp lý dựa trên TopN thiết lập (ưu tiên K từ 1 đến 5 cho mẫu nhỏ)
    top_n = meta.get('top_n', 5)
    max_k = min(top_n, 5) if top_n > 0 else 5
    k_range = list(range(1, max_k + 1))

    print("\n================ THÔNG TIN CẤU HÌNH MÔ HÌNH ================")
    print(f"Mã mô hình hiện hành : {meta['ma_mo_hinh']}")
    print(f"Mã cấu hình hiện hành: {meta['ma_cau_hinh']}")
    print(f"TopN gợi ý           : {meta['top_n']}")
    print(f"Ngưỡng Cosine         : {meta['nguong_cosine']}")
    print(f"Số sinh viên kiểm thử: {num_users}")
    print(f"Dải K đánh giá       : 1 đến {max_k}")

    # Tính toán các giá trị chỉ số
    summary_metrics = []
    print("\n================ KẾT QUẢ ĐÁNH GIÁ (DEMO) ================")
    print(f"{'K':<6} | {'Precision@K':<14} | {'Recall@K':<14} | {'NDCG@K':<14}")
    print("-" * 55)

    for k in k_range:
        avg_p = sum(calculate_precision_k(d['ground_truth'], d['predictions'], k) for d in data) / num_users
        avg_r = sum(calculate_recall_k(d['ground_truth'], d['predictions'], k) for d in data) / num_users
        avg_n = sum(calculate_ndcg_k(d['ground_truth'], d['predictions'], k) for d in data) / num_users
        
        summary_metrics.append({
            'k': f"@{k}",
            'k_num': k,
            'precision': round(avg_p, 4),
            'recall': round(avg_r, 4),
            'ndcg': round(avg_n, 4)
        })
        print(f"@{k:<5} | {avg_p:<14.4f} | {avg_r:<14.4f} | {avg_n:<14.4f}")

    # Tạo thư mục lưu kết quả
    output_dir = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'evaluation_logs')
    os.makedirs(output_dir, exist_ok=True)
    timestamp_str = datetime.now().strftime('%Y%m%d_%H%M%S')
    
    # 1. XUẤT FILE JSON
    json_path = os.path.join(output_dir, f"evaluation_report_{timestamp_str}.json")
    json_output = {
        "metadata": meta,
        "evaluation_notes": "Đánh giá đối chiếu với liên kết tài liệu trong đề cương học phần (tập tham chiếu proxy), không phải nhãn liên quan do người học đánh giá độc lập. K từ 1 đến min(TopN,5).",
        "metrics_summary": summary_metrics,
        "detailed_results": data
    }
    with open(json_path, 'w', encoding='utf-8') as f_json:
        json.dump(json_output, f_json, indent=2, ensure_ascii=False)
    print(f"\n[OK] Đã xuất file JSON : {json_path}")

    # 2. XUẤT FILE EXCEL CÓ BIỂU ĐỒ ĐƯỜNG
    excel_path = os.path.join(output_dir, f"evaluation_report_{timestamp_str}.xlsx")
    wb = openpyxl.Workbook()
    
    # --- Sheet 1: Tổng quan & Biểu đồ ---
    ws_sum = wb.active
    ws_sum.title = "TongQuan_Va_BieuDo"

    font_title = Font(name="Segoe UI", size=14, bold=True, color="003366")
    font_header = Font(name="Segoe UI", size=10, bold=True, color="FFFFFF")
    fill_header = PatternFill(start_color="003366", end_color="003366", fill_type="solid")
    fill_zebra = PatternFill(start_color="F8F9FA", end_color="F8F9FA", fill_type="solid")
    border_thin = Border(
        left=Side(style='thin', color='D9D9D9'),
        right=Side(style='thin', color='D9D9D9'),
        top=Side(style='thin', color='D9D9D9'),
        bottom=Side(style='thin', color='D9D9D9')
    )

    ws_sum.cell(row=1, column=1, value="BÁO CÁO ĐÁNH GIÁ MÔ HÌNH GỢI Ý TÀI LIỆU").font = font_title

    # Ghi Metadata
    meta_info = [
        ("Mã mô hình hiện hành:", meta['ma_mo_hinh']),
        ("Tên mô hình:", meta['ten_mo_hinh']),
        ("Mã cấu hình hiện hành:", meta['ma_cau_hinh']),
        ("TopN thiết lập:", meta['top_n']),
        ("Ngưỡng Cosine:", meta['nguong_cosine']),
        ("Số lượng SV đánh giá:", meta['so_sinh_vien']),
        ("Thời gian thực thi:", meta['timestamp'])
    ]
    for idx, (lbl, val) in enumerate(meta_info, start=3):
        ws_sum.cell(row=idx, column=1, value=lbl).font = Font(name="Segoe UI", bold=True, size=10)
        ws_sum.cell(row=idx, column=2, value=val).font = Font(name="Segoe UI", size=10)

    # Bảng tổng hợp mét-ric
    start_row = 11
    headers_sum = ["Top-K", "Precision@K", "Recall@K", "NDCG@K"]
    for col_idx, h in enumerate(headers_sum, start=1):
        cell = ws_sum.cell(row=start_row, column=col_idx, value=h)
        cell.font = font_header
        cell.fill = fill_header
        cell.alignment = Alignment(horizontal="center", vertical="center")

    for r_offset, m in enumerate(summary_metrics, start=1):
        r_idx = start_row + r_offset
        ws_sum.cell(row=r_idx, column=1, value=m['k']).alignment = Alignment(horizontal="center")
        
        for c_idx, key in enumerate(['precision', 'recall', 'ndcg'], start=2):
            cell = ws_sum.cell(row=r_idx, column=c_idx, value=m[key])
            cell.number_format = "0.0000"
            cell.alignment = Alignment(horizontal="right")
            
        for c_idx in range(1, 5):
            ws_sum.cell(row=r_idx, column=c_idx).border = border_thin

    # Nhúng Biểu đồ đường (Line Chart) vào Excel
    chart = LineChart()
    chart.title = "Biến thiên 3 chỉ số Đánh giá (Precision@K, Recall@K, NDCG@K)"
    chart.style = 13
    chart.y_axis.title = "Giá trị Metric (0.0 - 1.0)"
    chart.x_axis.title = "Giá trị K"
    chart.width = 16
    chart.height = 10

    data_ref = Reference(ws_sum, min_col=2, min_row=start_row, max_col=4, max_row=start_row + len(summary_metrics))
    cats_ref = Reference(ws_sum, min_col=1, min_row=start_row + 1, max_row=start_row + len(summary_metrics))

    chart.add_data(data_ref, titles_from_data=True)
    chart.set_categories(cats_ref)
    ws_sum.add_chart(chart, "F3")

    # --- Sheet 2: Chi tiết từng sinh viên ---
    ws_detail = wb.create_sheet(title="ChiTiet_SinhVien")
    ws_detail.cell(row=1, column=1, value="CHI TIẾT DỮ LIỆU ĐÁNH GIÁ THEO TỪNG SINH VIÊN").font = font_title

    headers_detail = ["STT", "MSSV", "Số lượng Ground Truth", "Ground Truth (Mã tài liệu)", "Mô hình Gợi ý (Predictions)", f"Số Hits (@{max_k})"]
    for col_idx, h in enumerate(headers_detail, start=1):
        cell = ws_detail.cell(row=3, column=col_idx, value=h)
        cell.font = font_header
        cell.fill = fill_header
        cell.alignment = Alignment(horizontal="center", vertical="center")

    for idx, item in enumerate(data, start=1):
        r = 3 + idx
        gt_str = ", ".join(item['ground_truth'])
        pred_str = ", ".join(item['predictions'])
        hits = len(set(item['predictions'][:max_k]).intersection(set(item['ground_truth'])))
        
        ws_detail.cell(row=r, column=1, value=idx).alignment = Alignment(horizontal="center")
        ws_detail.cell(row=r, column=2, value=item['mssv']).alignment = Alignment(horizontal="center")
        ws_detail.cell(row=r, column=3, value=len(item['ground_truth'])).alignment = Alignment(horizontal="right")
        ws_detail.cell(row=r, column=4, value=gt_str)
        ws_detail.cell(row=r, column=5, value=pred_str)
        ws_detail.cell(row=r, column=6, value=hits).alignment = Alignment(horizontal="right")
        
        for col_idx in range(1, 7):
            cell = ws_detail.cell(row=r, column=col_idx)
            cell.border = border_thin
            if idx % 2 == 0:
                cell.fill = fill_zebra

    # Auto-fit độ rộng cột cho cả 2 sheet
    for sheet in [ws_sum, ws_detail]:
        for col in sheet.columns:
            max_len = max(len(str(cell.value or '')) for cell in col)
            col_letter = get_column_letter(col[0].column)
            sheet.column_dimensions[col_letter].width = max(max_len + 3, 12)

    wb.save(excel_path)
    print(f"[OK] Đã xuất file Excel: {excel_path} (đã bao gồm Biểu đồ đường)")

    # 3. Xuất riêng biểu đồ đường PNG để đưa vào báo cáo đồ án.
    try:
        import matplotlib
        matplotlib.use('Agg')
        import matplotlib.pyplot as plt
    except ImportError:
        print("[LƯU Ý] Chưa xuất được ảnh PNG vì thiếu matplotlib.")
        print("Cài đặt bằng lệnh: python -m pip install matplotlib")
    else:
        k_values = [m['k_num'] for m in summary_metrics]
        fig, ax = plt.subplots(figsize=(10, 6))
        for field, label, marker in [
            ('precision', 'Precision@K', 'o'),
            ('recall', 'Recall@K', 's'),
            ('ndcg', 'NDCG@K', '^'),
        ]:
            ax.plot(k_values, [m[field] for m in summary_metrics],
                    marker=marker, linewidth=2, markersize=7, label=label)

        ax.set_title('Đánh giá kết quả gợi ý tài liệu theo Top-K', fontsize=14, pad=14)
        ax.set_xlabel('Giá trị K')
        ax.set_ylabel('Giá trị chỉ số')
        ax.set_xticks(k_values)
        ax.set_ylim(0.0, 1.05)
        ax.grid(True, alpha=0.25)
        ax.legend(loc='best')
        fig.tight_layout()

        image_path = os.path.join(output_dir, f"evaluation_chart_{timestamp_str}.png")
        fig.savefig(image_path, dpi=300, bbox_inches='tight')
        plt.close(fig)
        print(f"[OK] Đã xuất ảnh biểu đồ PNG: {image_path}")

if __name__ == "__main__":
    run_evaluation()