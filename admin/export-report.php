<?php
require_once '../config/init.php';
require_once '../config/database.php';
checkAdminAuth();

if (!isAdmin() && !isOwner()) {
    die('Akses ditolak.');
}

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$format = $_GET['format'] ?? 'csv';

// Set headers for download
$filename = "Laporan_Hotel_" . HOTEL_NAME . "_{$start_date}_to_{$end_date}.csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Open output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// 1. Title Block
fputcsv($output, [strtoupper(HOTEL_NAME) . ' - EXECUTIVE MANAGEMENT & FINANCIAL REPORT']);
fputcsv($output, ['Periode Laporan:', date('d F Y', strtotime($start_date)) . ' s.d ' . date('d F Y', strtotime($end_date))]);
fputcsv($output, ['Tanggal Unduh:', date('d F Y, H:i:s')]);
fputcsv($output, ['Diunduh Oleh:', ($_SESSION['full_name'] ?? $_SESSION['username']) . ' (' . strtoupper($_SESSION['role']) . ')']);
fputcsv($output, []);

// 2. Financial Summary
$revenue_stats = $database->getSingle("
    SELECT 
        COUNT(DISTINCT b.id) as total_bookings,
        COUNT(DISTINCT p.id) as total_transactions,
        COALESCE(SUM(p.amount), 0) as total_revenue,
        COALESCE(AVG(p.amount), 0) as avg_transaction,
        COALESCE(SUM(CASE WHEN p.payment_method = 'cash' THEN p.amount ELSE 0 END), 0) as cash_revenue,
        COALESCE(SUM(CASE WHEN p.payment_method = 'transfer' THEN p.amount ELSE 0 END), 0) as transfer_revenue,
        COALESCE(SUM(CASE WHEN p.payment_method = 'credit_card' THEN p.amount ELSE 0 END), 0) as credit_card_revenue,
        COALESCE(SUM(CASE WHEN p.payment_method = 'qris' THEN p.amount ELSE 0 END), 0) as qris_revenue
    FROM bookings b
    LEFT JOIN payments p ON b.id = p.booking_id
    WHERE p.payment_status = 'paid'
    AND DATE(p.payment_date) BETWEEN ? AND ?
", [$start_date, $end_date]);

fputcsv($output, ['=== 1. RINGKASAN FINANSIAL & TRANSAKSI ===']);
fputcsv($output, ['Metrik', 'Nilai']);
fputcsv($output, ['Total Pendapatan Bersih (Paid)', 'Rp ' . number_format($revenue_stats['total_revenue'] ?? 0, 0, ',', '.')]);
fputcsv($output, ['Total Reservasi Masuk', $revenue_stats['total_bookings'] ?? 0]);
fputcsv($output, ['Total Transaksi Sukses', $revenue_stats['total_transactions'] ?? 0]);
fputcsv($output, ['Rata-rata Nilai Transaksi', 'Rp ' . number_format($revenue_stats['avg_transaction'] ?? 0, 0, ',', '.')]);
fputcsv($output, ['Pembayaran Tunai (Cash)', 'Rp ' . number_format($revenue_stats['cash_revenue'] ?? 0, 0, ',', '.')]);
fputcsv($output, ['Pembayaran Transfer Bank', 'Rp ' . number_format($revenue_stats['transfer_revenue'] ?? 0, 0, ',', '.')]);
fputcsv($output, ['Pembayaran Kartu Kredit', 'Rp ' . number_format($revenue_stats['credit_card_revenue'] ?? 0, 0, ',', '.')]);
fputcsv($output, ['Pembayaran QRIS', 'Rp ' . number_format($revenue_stats['qris_revenue'] ?? 0, 0, ',', '.')]);
fputcsv($output, []);

// 3. Room Type Performance
$room_performance = $database->getAll("
    SELECT 
        rt.type_name,
        COUNT(b.id) as booking_count,
        COALESCE(SUM(b.final_amount), 0) as total_revenue,
        COALESCE(AVG(b.final_amount), 0) as avg_revenue,
        COUNT(DISTINCT r.id) as room_count
    FROM room_types rt
    LEFT JOIN rooms r ON rt.id = r.room_type_id
    LEFT JOIN booking_details bd ON r.id = bd.room_id
    LEFT JOIN bookings b ON bd.booking_id = b.id AND DATE(b.created_at) BETWEEN ? AND ?
    GROUP BY rt.id, rt.type_name
    ORDER BY total_revenue DESC
", [$start_date, $end_date]);

fputcsv($output, ['=== 2. KINERJA PENDAPATAN PER TIPE KAMAR ===']);
fputcsv($output, ['Tipe Kamar', 'Jumlah Unit Kamar', 'Total Reservasi', 'Total Pendapatan', 'Rata-rata / Booking']);
foreach ($room_performance as $rp) {
    fputcsv($output, [
        $rp['type_name'],
        $rp['room_count'],
        $rp['booking_count'],
        'Rp ' . number_format($rp['total_revenue'], 0, ',', '.'),
        'Rp ' . number_format($rp['avg_revenue'], 0, ',', '.')
    ]);
}
fputcsv($output, []);

// 4. Detailed Booking Transactions
$bookings_list = $database->getAll("
    SELECT b.booking_code, c.full_name as guest_name, c.phone as guest_phone,
           r.room_number, rt.type_name, b.check_in, b.check_out, b.total_nights,
           b.final_amount, b.status, p.payment_method, p.payment_status
    FROM bookings b
    LEFT JOIN customers c ON b.customer_id = c.id
    LEFT JOIN booking_details bd ON b.id = bd.booking_id
    LEFT JOIN rooms r ON bd.room_id = r.id
    LEFT JOIN room_types rt ON r.room_type_id = rt.id
    LEFT JOIN payments p ON b.id = p.booking_id
    WHERE DATE(b.created_at) BETWEEN ? AND ?
    GROUP BY b.id
    ORDER BY b.created_at DESC
", [$start_date, $end_date]);

fputcsv($output, ['=== 3. RINCIAN TRANSAKSI RESERVASI ===']);
fputcsv($output, ['Kode Booking', 'Nama Tamu', 'No. Telepon', 'No. Kamar', 'Tipe Kamar', 'Check In', 'Check Out', 'Malam', 'Total Biaya', 'Status Reservasi', 'Metode Bayar', 'Status Pembayaran']);
foreach ($bookings_list as $bk) {
    fputcsv($output, [
        $bk['booking_code'],
        $bk['guest_name'] ?? 'Tamu Tanpa Nama',
        $bk['guest_phone'] ?? '-',
        $bk['room_number'] ?? '-',
        $bk['type_name'] ?? '-',
        $bk['check_in'],
        $bk['check_out'],
        $bk['total_nights'],
        'Rp ' . number_format($bk['final_amount'], 0, ',', '.'),
        ucfirst(str_replace('_', ' ', $bk['status'])),
        ucfirst($bk['payment_method'] ?? 'cash'),
        ucfirst($bk['payment_status'] ?? 'unpaid')
    ]);
}
fputcsv($output, []);

// 5. Service & Extra Orders
$service_orders = $database->getAll("
    SELECT so.id, s.service_name, s.category, so.quantity, so.total_price, so.order_status, so.created_at,
           b.booking_code, c.full_name as guest_name
    FROM service_orders so
    JOIN services s ON so.service_id = s.id
    LEFT JOIN bookings b ON so.booking_id = b.id
    LEFT JOIN customers c ON b.customer_id = c.id
    WHERE DATE(so.created_at) BETWEEN ? AND ?
    ORDER BY so.created_at DESC
", [$start_date, $end_date]);

fputcsv($output, ['=== 4. RINCIAN PESANAN LAYANAN & ROOM SERVICE ===']);
fputcsv($output, ['ID Order', 'Nama Layanan', 'Kategori', 'Qty', 'Total Biaya', 'Status Pesanan', 'Kode Booking', 'Nama Tamu', 'Waktu Pesan']);
foreach ($service_orders as $so) {
    fputcsv($output, [
        '#ORD-' . $so['id'],
        $so['service_name'],
        ucfirst($so['category']),
        $so['quantity'],
        'Rp ' . number_format($so['total_price'], 0, ',', '.'),
        ucfirst($so['order_status']),
        $so['booking_code'] ?? '-',
        $so['guest_name'] ?? '-',
        $so['created_at']
    ]);
}

fclose($output);
exit();
