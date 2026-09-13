<?php
require_once '../config/init.php';
checkUserAuth();

$page_title = "Detail Reservasi - Grand Luxury Hotel";
include '../includes/header.php';

$user_id = $_SESSION['user_id'];
$booking_id = $_GET['id'] ?? 0;

if (!$booking_id) {
    setFlashMessage('error', 'Booking ID tidak valid');
    redirect('history.php');
}

// Get booking details
$booking = $database->getSingle("
    SELECT b.*, 
           GROUP_CONCAT(DISTINCT r.room_number ORDER BY r.room_number SEPARATOR ', ') as room_number,
           COUNT(DISTINCT bd.id) as total_rooms_booked,
           r.floor, r.view_type, rt.type_name, rt.description as room_description,
           rt.capacity, rt.size, rt.bed_type, rt.image as room_image, c.full_name, c.email, c.phone, c.identity_number,
           u.username, u.full_name as booked_by
    FROM bookings b
    JOIN booking_details bd ON b.id = bd.booking_id
    JOIN rooms r ON bd.room_id = r.id
    JOIN room_types rt ON r.room_type_id = rt.id
    JOIN customers c ON b.customer_id = c.id
    JOIN users u ON b.user_id = u.id
    WHERE b.id = ? AND b.user_id = ?
    GROUP BY b.id
", [$booking_id, $user_id]);

if (!$booking) {
    setFlashMessage('error', 'Booking tidak ditemukan');
    redirect('history.php');
}

// Get payment information
$payments = $database->getAll("
    SELECT * FROM payments 
    WHERE booking_id = ? 
    ORDER BY created_at DESC
", [$booking_id]);

// Get facilities for the room
$facilities = $database->getAll("
    SELECT f.name, f.icon, f.category
    FROM facilities f
    JOIN room_facilities rf ON f.id = rf.facility_id
    JOIN room_types rt ON rf.room_type_id = rt.id
    WHERE rt.id = (SELECT room_type_id FROM rooms WHERE id = (SELECT room_id FROM booking_details WHERE booking_id = ?))
    ORDER BY f.category, f.name
", [$booking_id]);

// Calculate payment summary
$total_paid = 0;
$pending_payments = 0;
foreach ($payments as $payment) {
    if ($payment['payment_status'] == 'paid') {
        $total_paid += $payment['amount'];
    } elseif ($payment['payment_status'] == 'pending') {
        $pending_payments += $payment['amount'];
    }
}
$remaining_amount = max(0, $booking['final_amount'] - $total_paid);
?>

<style>
    .voucher-card {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #312e81 100%);
        border-radius: 20px;
        padding: 30px;
        color: #fff;
        margin-bottom: 24px;
        border: 1px solid rgba(255, 255, 255, 0.15);
        box-shadow: 0 10px 30px rgba(0,0,0,0.06);
        position: relative;
        overflow: hidden;
    }
    .voucher-card h1, .voucher-card h2, .voucher-card h3, .voucher-card h4 {
        color: #ffffff !important;
        font-weight: 800 !important;
    }
    .voucher-card p {
        color: #e2e8f0 !important;
    }
    .detail-card {
        background: #ffffff;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        overflow: hidden;
        margin-bottom: 24px;
    }
    .detail-card-header {
        padding: 18px 24px;
        background: #ffffff;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .badge-status-pill {
        font-size: 0.8rem;
        font-weight: 700;
        padding: 8px 18px;
        border-radius: 50rem;
        letter-spacing: 0.04em;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .badge-pending { background-color: #fef3c7; color: #d97706; }
    .badge-confirmed { background-color: #e0f2fe; color: #0284c7; }
    .badge-checked_in { background-color: #d1fae5; color: #059669; }
    .badge-checked_out { background-color: #f3e8ff; color: #7c3aed; }
    .badge-cancelled { background-color: #fee2e2; color: #dc2626; }

    .stay-timeline-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 20px;
        text-align: center;
    }

    @media print {
        header, footer, .no-print { display: none !important; }
        .voucher-card { background: #fff !important; color: #000 !important; border: 2px solid #000 !important; }
        .detail-card { border: 1px solid #000 !important; box-shadow: none !important; }
    }
</style>

<div class="container py-4">
    
    <!-- E-Voucher Header Banner -->
    <div class="voucher-card">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-warning text-dark fw-bold px-3 py-1 rounded-pill">
                        <i class="fas fa-ticket-alt me-1"></i> E-VOUCHER RESERVASI
                    </span>
                    <span class="text-white-50 small">Kode: #<?php echo htmlspecialchars($booking['booking_code']); ?></span>
                </div>
                <h2 class="fw-extrabold mb-1" style="font-family: 'Outfit', sans-serif; font-size: 1.85rem;">
                    <?php echo htmlspecialchars($booking['type_name']); ?> (Kamar <?php echo htmlspecialchars($booking['room_number']); ?>)
                </h2>
                <p class="mb-0 text-white-50">
                    Dipesan pada <?php echo date('d F Y, H:i', strtotime($booking['created_at'])); ?> WIB &bull; Atas nama <?php echo htmlspecialchars($booking['full_name']); ?>
                </p>
            </div>
            <div class="text-end">
                <span class="badge-status-pill badge-<?php echo $booking['status']; ?> shadow-sm fs-6 mb-2">
                    <i class="fas fa-circle" style="font-size: 0.6rem;"></i>
                    <?php echo strtoupper($booking['status']); ?>
                </span>
                <div class="d-flex gap-2 justify-content-end no-print">
                    <a href="receipt.php?booking_id=<?php echo $booking['id']; ?>" target="_blank" class="btn btn-sm btn-warning rounded-pill px-3 fw-bold shadow-sm">
                        <i class="fas fa-print me-1"></i> Cetak Nota Resmi
                    </a>
                    <a href="history.php" class="btn btn-sm btn-light rounded-pill px-3 fw-bold">
                        <i class="fas fa-arrow-left me-1"></i> Kembali
                    </a>
                </div>
            </div>
    <?php 
    $primary_payment = !empty($payments) ? $payments[0] : null;
    $pay_method = $primary_payment['payment_method'] ?? 'cash';
    $pay_status = $primary_payment['payment_status'] ?? 'pending';
    ?>

    <?php if ($pay_status === 'paid' || $total_paid >= $booking['final_amount']): ?>
    <div class="alert alert-success border-0 rounded-4 p-3 mb-4 shadow-sm d-flex align-items-center gap-3">
        <i class="fas fa-check-circle fa-2x text-success"></i>
        <div>
            <h6 class="fw-bold mb-1 text-success">Pembayaran Lunas & Terkonfirmasi</h6>
            <p class="mb-0 small text-dark">Seluruh tagihan reservasi Anda telah terbayar lunas. Silakan tunjukkan E-Voucher ini saat proses check-in di hotel.</p>
        </div>
    </div>
    <?php elseif ($pay_method === 'cash'): ?>
    <div class="alert alert-info border-0 rounded-4 p-3 mb-4 shadow-sm d-flex align-items-center gap-3">
        <i class="fas fa-hotel fa-2x text-info"></i>
        <div>
            <h6 class="fw-bold mb-1 text-info">Metode: Bayar di Hotel (Pay at Hotel)</h6>
            <p class="mb-0 small text-dark">Reservasi Anda telah terdaftar dan kamar telah dikunci. Pelunasan sebesar <strong><?php echo formatCurrency($booking['final_amount']); ?></strong> dapat dilakukan tunai/kartu saat tiba di resepsionis.</p>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-warning border-0 rounded-4 p-3 mb-4 shadow-sm d-flex align-items-center gap-3">
        <i class="fas fa-clock fa-2x text-warning"></i>
        <div>
            <h6 class="fw-bold mb-1 text-dark">Konfirmasi Transfer Diterima (Menunggu Verifikasi)</h6>
            <p class="mb-0 small text-muted">Data transfer Anda (No. Ref: <strong><?php echo htmlspecialchars($primary_payment['transaction_id'] ?? '-'); ?></strong>) sedang dalam proses verifikasi tim keuangan kami.</p>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left Side: Room & Stay Details -->
        <div class="col-lg-8">
            
            <!-- Stay Time Cards -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <h5 class="fw-bold mb-0 text-dark" style="font-family: 'Outfit', sans-serif;">
                        <i class="fas fa-calendar-check me-2 text-primary"></i>Jadwal Menginap & Check-in
                    </h5>
                    <span class="badge bg-primary-subtle text-primary fw-bold px-3 py-1 rounded-pill">
                        <?php echo $booking['total_nights']; ?> Malam
                    </span>
                </div>
                <div class="p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="stay-timeline-box border-start border-4 border-success">
                                <span class="text-success fw-bold small text-uppercase"><i class="fas fa-sign-in-alt me-1"></i>Check-in</span>
                                <h4 class="fw-bold text-dark mt-2 mb-1" style="font-family: 'Outfit', sans-serif;">
                                    <?php echo date('d M Y', strtotime($booking['check_in'])); ?>
                                </h4>
                                <small class="text-muted">Pukul <?php echo CHECK_IN_TIME; ?> WIB</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stay-timeline-box border-start border-4 border-primary">
                                <span class="text-primary fw-bold small text-uppercase"><i class="fas fa-moon me-1"></i>Durasi</span>
                                <h4 class="fw-bold text-dark mt-2 mb-1" style="font-family: 'Outfit', sans-serif;">
                                    <?php echo $booking['total_nights']; ?> Malam
                                </h4>
                                <small class="text-muted"><?php echo $booking['adults']; ?> Dewasa, <?php echo $booking['children']; ?> Anak</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stay-timeline-box border-start border-4 border-danger">
                                <span class="text-danger fw-bold small text-uppercase"><i class="fas fa-sign-out-alt me-1"></i>Check-out</span>
                                <h4 class="fw-bold text-dark mt-2 mb-1" style="font-family: 'Outfit', sans-serif;">
                                    <?php echo date('d M Y', strtotime($booking['check_out'])); ?>
                                </h4>
                                <small class="text-muted">Pukul <?php echo CHECK_OUT_TIME; ?> WIB</small>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($booking['special_requests'])): ?>
                    <div class="mt-4 p-3 bg-light rounded-3 border">
                        <span class="fw-bold text-dark small d-block mb-1"><i class="fas fa-comment-dots text-primary me-1"></i>Permintaan Khusus:</span>
                        <p class="text-muted mb-0 small"><?php echo nl2br(htmlspecialchars($booking['special_requests'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Room Specifications -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <h5 class="fw-bold mb-0 text-dark" style="font-family: 'Outfit', sans-serif;">
                        <i class="fas fa-bed me-2 text-indigo"></i>Spesifikasi Kamar
                    </h5>
                </div>
                <div class="p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded-3">
                                <span class="text-muted small d-block">Tipe & Nomor Kamar</span>
                                <strong class="text-dark fs-6"><?php echo htmlspecialchars($booking['type_name']); ?> - Room <?php echo htmlspecialchars($booking['room_number']); ?></strong>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded-3">
                                <span class="text-muted small d-block">Lantai & Pemandangan</span>
                                <strong class="text-dark fs-6">Lantai <?php echo htmlspecialchars($booking['floor']); ?> (<?php echo ucfirst($booking['view_type'] ?? 'Standard'); ?> View)</strong>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded-3">
                                <span class="text-muted small d-block">Kapasitas & Tipe Ranjang</span>
                                <strong class="text-dark fs-6"><?php echo htmlspecialchars($booking['capacity']); ?> Orang &bull; <?php echo htmlspecialchars($booking['bed_type'] ?? 'King Bed'); ?></strong>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded-3">
                                <span class="text-muted small d-block">Luas Kamar</span>
                                <strong class="text-dark fs-6"><?php echo htmlspecialchars($booking['size'] ?? '32 m²'); ?></strong>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($facilities)): ?>
                    <div>
                        <span class="text-muted fw-bold d-block small mb-2 text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Fasilitas Kamar:</span>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($facilities as $fac): ?>
                            <span class="badge bg-light text-dark border px-3 py-2 rounded-pill fw-semibold">
                                <i class="fas fa-<?php echo htmlspecialchars($fac['icon'] ?? 'check'); ?> text-primary me-1"></i>
                                <?php echo htmlspecialchars($fac['name']); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Right Side: Guest Data & Payment Breakdown -->
        <div class="col-lg-4">
            
            <!-- Guest Information -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-user-circle me-2 text-primary"></i>Informasi Tamu</h6>
                </div>
                <div class="p-3">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width: 44px; height: 44px;">
                            <?php echo strtoupper(substr($booking['full_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark"><?php echo htmlspecialchars($booking['full_name']); ?></h6>
                            <small class="text-muted"><?php echo htmlspecialchars($booking['email']); ?></small>
                        </div>
                    </div>
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">No. Telepon:</span>
                            <strong><?php echo htmlspecialchars($booking['phone'] ?? '-'); ?></strong>
                        </li>
                        <?php if ($booking['identity_number']): ?>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">No. Identitas:</span>
                            <strong><?php echo htmlspecialchars($booking['identity_number']); ?></strong>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Payment Summary -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-receipt me-2 text-success"></i>Rincian Pembayaran</h6>
                </div>
                <div class="p-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Total Tagihan:</span>
                        <strong class="text-dark"><?php echo formatCurrency($booking['final_amount']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Sudah Terbayar:</span>
                        <strong class="text-success"><?php echo formatCurrency($total_paid); ?></strong>
                    </div>
                    <?php if ($pending_payments > 0): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Menunggu Verifikasi:</span>
                        <strong class="text-warning"><?php echo formatCurrency($pending_payments); ?></strong>
                    </div>
                    <?php endif; ?>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between mb-3">
                        <span class="fw-bold text-dark">Sisa Pembayaran:</span>
                        <strong class="fs-5 <?php echo $remaining_amount > 0 ? 'text-danger' : 'text-success'; ?>" style="font-family: 'Outfit', sans-serif;">
                            <?php echo formatCurrency($remaining_amount); ?>
                        </strong>
                    </div>

                    <?php if ($remaining_amount > 0 && in_array($booking['status'], ['pending', 'confirmed'])): ?>
                    <div class="d-grid mb-2">
                        <a href="payment.php?booking_id=<?php echo $booking_id; ?>" class="btn btn-success rounded-pill py-2 fw-bold shadow-sm">
                            <i class="fas fa-wallet me-1"></i> Bayar Sisa Sekarang
                        </a>
                    </div>
                    <?php endif; ?>

                    <div class="d-grid">
                        <a href="receipt.php?booking_id=<?php echo $booking['id']; ?>" target="_blank" class="btn btn-outline-primary rounded-pill py-2 fw-semibold">
                            <i class="fas fa-file-invoice-dollar me-1"></i> Cetak Nota / Kuitansi Resmi
                        </a>
                    </div>
                </div>
            </div>

            <!-- Payment Transaction History -->
            <?php if (!empty($payments)): ?>
            <div class="detail-card">
                <div class="detail-card-header">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-history me-2 text-muted"></i>Riwayat Transaksi</h6>
                </div>
                <div class="p-3">
                    <div class="list-group list-group-flush small">
                        <?php foreach ($payments as $pay): ?>
                        <div class="list-group-item px-0 py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-dark"><?php echo formatCurrency($pay['amount']); ?></strong>
                                    <div class="text-muted" style="font-size: 0.72rem;">
                                        <?php echo strtoupper($pay['payment_method']); ?> &bull; <?php echo date('d/m/Y H:i', strtotime($pay['created_at'])); ?>
                                    </div>
                                </div>
                                <span class="badge <?php echo $pay['payment_status'] == 'paid' ? 'bg-success' : 'bg-warning text-dark'; ?> rounded-pill px-2 py-1">
                                    <?php echo strtoupper($pay['payment_status']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

</div>

<?php include '../includes/footer.php'; ?>