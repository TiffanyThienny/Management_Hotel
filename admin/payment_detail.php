<?php
require_once '../config/init.php';
require_once '../config/database.php';
checkAdminAuth();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    setFlashMessage('error', 'ID Pembayaran tidak valid.');
    redirect('payments.php');
}

$query = "SELECT p.*, b.id as booking_id, b.booking_code, b.check_in, b.check_out, b.total_nights, b.total_guests, b.final_amount as booking_total,
                 c.full_name as customer_name, c.email as customer_email, c.phone as customer_phone, c.address as customer_address, c.identity_type, c.identity_number,
                 u.full_name as processed_by
          FROM payments p
          JOIN bookings b ON p.booking_id = b.id
          JOIN customers c ON b.customer_id = c.id
          LEFT JOIN users u ON b.user_id = u.id
          WHERE p.id = ?";
$payment = $database->getSingle($query, [$id]);

if (!$payment) {
    setFlashMessage('error', 'Detail pembayaran tidak ditemukan.');
    redirect('payments.php');
}

// Fetch booking room details
$details_query = "SELECT bd.*, r.room_number, rt.type_name, rt.base_price
                 FROM booking_details bd
                 JOIN rooms r ON bd.room_id = r.id
                 JOIN room_types rt ON r.room_type_id = rt.id
                 WHERE bd.booking_id = ?";
$room_details = $database->getAll($details_query, [$payment['booking_id']]);

$page_title = "Detail Pembayaran #" . $payment['id'];
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Navigation -->
        <?php include '../includes/admin-sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between align-items-center pb-3 mb-4 border-bottom">
                <div>
                    <h2 class="fw-bold mb-1">Detail Transaksi Pembayaran #<?php echo $payment['id']; ?></h2>
                    <p class="text-muted small mb-0">Rincian status pembayaran dan informasi reservasi.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="payments.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                        <i class="fas fa-arrow-left me-1"></i> Kembali ke Daftar
                    </a>
                    <a href="receipt.php?id=<?php echo $payment['id']; ?>" target="_blank" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold shadow-sm">
                        <i class="fas fa-receipt me-1"></i> Cetak / Download Nota PDF
                    </a>
                </div>
            </div>

            <div class="row g-4">
                <!-- Payment Summary Card -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white py-3">
                            <h6 class="m-0 fw-bold text-dark"><i class="fas fa-credit-card me-2 text-primary"></i>Informasi Transaksi</h6>
                        </div>
                        <div class="card-body p-4">
                            <table class="table table-borderless align-middle mb-0">
                                <tr>
                                    <td class="text-muted w-40">Status Pembayaran</td>
                                    <td>
                                        <?php if ($payment['payment_status'] == 'paid'): ?>
                                            <span class="badge bg-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i> LUNAS (Paid)</span>
                                        <?php elseif ($payment['payment_status'] == 'pending'): ?>
                                            <span class="badge bg-warning text-dark px-3 py-2 rounded-pill"><i class="fas fa-clock me-1"></i> Menunggu Konfirmasi</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger px-3 py-2 rounded-pill"><i class="fas fa-times-circle me-1"></i> GAGAL</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Nominal Pembayaran</td>
                                    <td class="fw-bold fs-5 text-primary">Rp <?php echo number_format($payment['amount'], 0, ',', '.'); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Metode Pembayaran</td>
                                    <td class="fw-semibold text-uppercase"><i class="fas fa-wallet me-1 text-info"></i> <?php echo $payment['payment_method']; ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">ID Transaksi / Ref</td>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($payment['transaction_id'] ?? 'TRX-' . str_pad($payment['id'], 6, '0', STR_PAD_LEFT)); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Bank / Akun</td>
                                    <td><?php echo htmlspecialchars($payment['bank_name'] ?? '-'); ?> <?php echo htmlspecialchars($payment['account_number'] ? "({$payment['account_number']})" : ''); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Waktu Pembayaran</td>
                                    <td><?php echo $payment['payment_date'] ? date('d F Y, H:i', strtotime($payment['payment_date'])) : '-'; ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Tanggal Dicatat</td>
                                    <td><?php echo date('d F Y, H:i', strtotime($payment['created_at'])); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Catatan Pembayaran</td>
                                    <td><?php echo htmlspecialchars($payment['notes'] ?? 'Tidak ada catatan.'); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Customer & Booking Card -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white py-3">
                            <h6 class="m-0 fw-bold text-dark"><i class="fas fa-user-circle me-2 text-warning"></i>Informasi Tamu & Booking</h6>
                        </div>
                        <div class="card-body p-4">
                            <table class="table table-borderless align-middle mb-0">
                                <tr>
                                    <td class="text-muted w-40">Kode Booking</td>
                                    <td>
                                        <a href="booking_detail.php?id=<?php echo $payment['booking_id']; ?>" class="fw-bold text-primary text-decoration-none">
                                            <?php echo htmlspecialchars($payment['booking_code']); ?> <i class="fas fa-external-link-alt ms-1 small"></i>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Nama Pelanggan</td>
                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($payment['customer_name']); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Email</td>
                                    <td><?php echo htmlspecialchars($payment['customer_email']); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">No. Telepon</td>
                                    <td><?php echo htmlspecialchars($payment['customer_phone']); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Identitas</td>
                                    <td><?php echo strtoupper($payment['identity_type']); ?>: <?php echo htmlspecialchars($payment['identity_number'] ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Tanggal Stay</td>
                                    <td class="fw-semibold">
                                        <?php echo date('d/m/Y', strtotime($payment['check_in'])); ?> s.d <?php echo date('d/m/Y', strtotime($payment['check_out'])); ?>
                                        <small class="text-muted d-block">(<?php echo $payment['total_nights']; ?> malam)</small>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Room List -->
            <div class="card border-0 shadow-sm rounded-4 mt-4">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-dark"><i class="fas fa-bed me-2 text-success"></i>Rincian Kamar Dipesan</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Tipe Kamar</th>
                                    <th>Nomor Kamar</th>
                                    <th>Harga per Malam</th>
                                    <th>Jumlah Malam</th>
                                    <th class="pe-4 text-end">Total Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($room_details as $detail): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark"><?php echo htmlspecialchars($detail['type_name']); ?></td>
                                    <td><span class="badge bg-light text-dark border">Kamar <?php echo htmlspecialchars($detail['room_number']); ?></span></td>
                                    <td>Rp <?php echo number_format($detail['price_per_night'], 0, ',', '.'); ?></td>
                                    <td><?php echo $payment['total_nights']; ?> malam</td>
                                    <td class="pe-4 text-end fw-bold text-primary">Rp <?php echo number_format($detail['total_price'], 0, ',', '.'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
