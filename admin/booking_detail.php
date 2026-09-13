<?php
require_once '../config/init.php';
require_once '../config/database.php';
checkAdminAuth();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    setFlashMessage('error', 'ID Booking tidak valid.');
    redirect('bookings.php');
}

$query = "SELECT b.*, c.full_name as customer_name, c.email as customer_email, c.phone as customer_phone, c.address as customer_address, c.identity_type, c.identity_number,
                 p.id as payment_id, p.payment_status, p.payment_method, p.amount as paid_amount, p.payment_date, p.transaction_id,
                 u.full_name as processed_by
          FROM bookings b
          JOIN customers c ON b.customer_id = c.id
          LEFT JOIN payments p ON b.id = p.booking_id
          LEFT JOIN users u ON b.user_id = u.id
          WHERE b.id = ?";
$booking = $database->getSingle($query, [$id]);

if (!$booking) {
    setFlashMessage('error', 'Detail booking tidak ditemukan.');
    redirect('bookings.php');
}

// Fetch booking room details
$details_query = "SELECT bd.*, r.room_number, rt.type_name, rt.base_price
                 FROM booking_details bd
                 JOIN rooms r ON bd.room_id = r.id
                 JOIN room_types rt ON r.room_type_id = rt.id
                 WHERE bd.booking_id = ?";
$room_details = $database->getAll($details_query, [$id]);

$page_title = "Detail Booking - " . $booking['booking_code'];
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
                    <h2 class="fw-bold mb-1">Detail Reservasi #<?php echo htmlspecialchars($booking['booking_code']); ?></h2>
                    <p class="text-muted small mb-0">Informasi rincian pemesanan kamar dan pembayaran.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="bookings.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                        <i class="fas fa-arrow-left me-1"></i> Kembali ke Daftar
                    </a>
                    <a href="receipt.php?booking_id=<?php echo $booking['id']; ?>" target="_blank" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold shadow-sm">
                        <i class="fas fa-receipt me-1"></i> Cetak / Download Nota PDF
                    </a>
                </div>
            </div>

            <div class="row g-4">
                <!-- Booking Master Summary -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white py-3">
                            <h6 class="m-0 fw-bold text-dark"><i class="fas fa-bookmark me-2 text-primary"></i>Informasi Pemesanan</h6>
                        </div>
                        <div class="card-body p-4">
                            <table class="table table-borderless align-middle mb-0">
                                <tr>
                                    <td class="text-muted w-40">Status Booking</td>
                                    <td>
                                        <span class="badge bg-primary text-uppercase px-3 py-2 rounded-pill">
                                            <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Kode Booking</td>
                                    <td class="fw-bold text-primary fs-6"><?php echo htmlspecialchars($booking['booking_code']); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Tanggal Check-in</td>
                                    <td class="fw-semibold text-success"><i class="far fa-calendar-check me-1"></i> <?php echo date('d F Y', strtotime($booking['check_in'])); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Tanggal Check-out</td>
                                    <td class="fw-semibold text-danger"><i class="far fa-calendar-times me-1"></i> <?php echo date('d F Y', strtotime($booking['check_out'])); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Durasi Stay</td>
                                    <td><?php echo $booking['total_nights']; ?> malam</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Jumlah Tamu</td>
                                    <td><?php echo $booking['total_guests']; ?> Tamu (<?php echo $booking['adults']; ?> Dewasa, <?php echo $booking['children']; ?> Anak)</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Total Biaya</td>
                                    <td class="fw-bold fs-5 text-primary">Rp <?php echo number_format($booking['final_amount'], 0, ',', '.'); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Permintaan Khusus</td>
                                    <td><?php echo htmlspecialchars($booking['special_requests'] ?? 'Tidak ada.'); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Customer Info -->
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white py-3">
                            <h6 class="m-0 fw-bold text-dark"><i class="fas fa-user me-2 text-warning"></i>Informasi Customer</h6>
                        </div>
                        <div class="card-body p-4">
                            <table class="table table-borderless align-middle mb-0">
                                <tr>
                                    <td class="text-muted w-40">Nama Lengkap</td>
                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Email</td>
                                    <td><?php echo htmlspecialchars($booking['customer_email']); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">No. Telepon</td>
                                    <td><?php echo htmlspecialchars($booking['customer_phone']); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">No. Identitas</td>
                                    <td><?php echo strtoupper($booking['identity_type']); ?>: <?php echo htmlspecialchars($booking['identity_number'] ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Alamat</td>
                                    <td><?php echo htmlspecialchars($booking['customer_address'] ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Status Pembayaran</td>
                                    <td>
                                        <?php if ($booking['payment_status'] == 'paid'): ?>
                                            <span class="badge bg-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i> LUNAS</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark px-3 py-2 rounded-pill"><i class="fas fa-clock me-1"></i> PENDING / UNPAID</span>
                                        <?php endif; ?>
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
                    <h6 class="m-0 fw-bold text-dark"><i class="fas fa-bed me-2 text-success"></i>Kamar Teralokasi</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Tipe Kamar</th>
                                    <th>Nomor Kamar</th>
                                    <th>Harga per Malam</th>
                                    <th class="pe-4 text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($room_details as $detail): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark"><?php echo htmlspecialchars($detail['type_name']); ?></td>
                                    <td><span class="badge bg-light text-dark border">Kamar <?php echo htmlspecialchars($detail['room_number']); ?></span></td>
                                    <td>Rp <?php echo number_format($detail['price_per_night'], 0, ',', '.'); ?></td>
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
