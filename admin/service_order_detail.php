<?php
require_once '../config/init.php';
require_once '../config/database.php';
checkAdminAuth();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    setFlashMessage('error', 'ID Service Order tidak valid.');
    redirect('service_orders.php');
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $new_status = sanitizeInput($_POST['status']);
    if (in_array($new_status, ['pending', 'processing', 'completed', 'cancelled'])) {
        $result = $database->update('service_orders', ['status' => $new_status], "id = $id");
        if ($result) {
            setFlashMessage('success', 'Status pesanan layanan berhasil diubah menjadi: ' . ucfirst($new_status));
        } else {
            setFlashMessage('error', 'Gagal memperbarui status pesanan.');
        }
        redirect('service_order_detail.php?id=' . $id);
    }
}

// Query service order with booking, service, and customer details
$query = "SELECT so.*, 
                 s.service_name, s.category, s.price as unit_price, s.description as service_desc,
                 b.booking_code, b.check_in, b.check_out, b.status as booking_status, b.total_nights,
                 c.id as customer_id, c.full_name as customer_name, c.email as customer_email, 
                 c.phone as customer_phone, c.identity_type, c.identity_number, c.customer_type,
                 r.room_number, rt.type_name as room_type_name
          FROM service_orders so
          JOIN services s ON so.service_id = s.id
          JOIN bookings b ON so.booking_id = b.id
          JOIN customers c ON b.customer_id = c.id
          LEFT JOIN booking_details bd ON b.id = bd.booking_id
          LEFT JOIN rooms r ON bd.room_id = r.id
          LEFT JOIN room_types rt ON r.room_type_id = rt.id
          WHERE so.id = ?";

$order = $database->getSingle($query, [$id]);

if (!$order) {
    setFlashMessage('error', 'Service order tidak ditemukan.');
    redirect('service_orders.php');
}

$page_title = "Detail Pesanan Layanan #" . $order['id'];
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Navigation -->
        <?php include '../includes/admin-sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <!-- Header section -->
            <div class="d-flex justify-content-between align-items-center pb-3 mb-4 border-bottom flex-wrap gap-2">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h2 class="fw-bold mb-0" style="font-family: 'Outfit', sans-serif;">Pesanan Layanan #<?php echo $order['id']; ?></h2>
                        <?php 
                        $status_badges = [
                            'pending' => 'bg-warning text-dark',
                            'processing' => 'bg-info text-dark',
                            'completed' => 'bg-success text-white',
                            'cancelled' => 'bg-danger text-white'
                        ];
                        $badge_class = $status_badges[$order['status']] ?? 'bg-secondary text-white';
                        ?>
                        <span class="badge <?php echo $badge_class; ?> px-3 py-2 rounded-pill text-uppercase fs-7">
                            <i class="fas fa-circle me-1 small"></i><?php echo ucfirst($order['status']); ?>
                        </span>
                    </div>
                    <p class="text-muted small mb-0">Dipesan pada: <?php echo date('d F Y, H:i', strtotime($order['created_at'])); ?> WIB</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="service_orders.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                        <i class="fas fa-arrow-left me-1"></i> Kembali ke Daftar
                    </a>
                </div>
            </div>

            <div class="row g-4">
                <!-- Service Order Details -->
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white border">
                        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                            <h6 class="m-0 fw-bold text-dark"><i class="fas fa-concierge-bell me-2 text-primary"></i>Rincian Item Layanan</h6>
                            <span class="badge bg-light text-primary border px-2 py-1 text-uppercase">
                                Kategori: <?php echo ucfirst($order['category'] ?? 'General'); ?>
                            </span>
                        </div>
                        <div class="card-body p-4">
                            <div class="table-responsive">
                                <table class="table table-borderless align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="py-2">Item Layanan</th>
                                            <th class="py-2 text-center">Harga Satuan</th>
                                            <th class="py-2 text-center">Jumlah</th>
                                            <th class="py-2 text-end">Total Biaya</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="border-bottom">
                                            <td class="py-3">
                                                <div class="fw-bold fs-6 text-dark"><?php echo htmlspecialchars($order['service_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($order['service_desc'] ?? 'Layanan Hotel'); ?></small>
                                            </td>
                                            <td class="text-center py-3"><?php echo formatCurrency($order['unit_price']); ?></td>
                                            <td class="text-center py-3">
                                                <span class="badge bg-primary px-3 py-2 rounded-pill"><?php echo $order['quantity']; ?>x</span>
                                            </td>
                                            <td class="text-end py-3 fw-bold text-success fs-6"><?php echo formatCurrency($order['total_price']); ?></td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="text-end pt-3 fw-bold">Total Pembayaran:</td>
                                            <td class="text-end pt-3 fw-bolder text-primary fs-5"><?php echo formatCurrency($order['total_price']); ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <?php if (!empty($order['notes'])): ?>
                            <div class="mt-4 p-3 bg-light rounded-3 border">
                                <div class="fw-bold text-dark mb-1 small"><i class="fas fa-sticky-note me-2 text-warning"></i>Catatan Khusus / Permintaan Tamu:</div>
                                <p class="text-muted mb-0 small"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Update Status Card -->
                    <div class="card border-0 shadow-sm rounded-4 bg-white border">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h6 class="m-0 fw-bold text-dark"><i class="fas fa-tasks me-2 text-primary"></i>Kelola Status Pesanan</h6>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_status">
                                <div class="row align-items-center g-3">
                                    <div class="col-md-7">
                                        <label class="form-label small fw-bold text-muted">Update Status Terkini</label>
                                        <select name="status" class="form-select">
                                            <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>⏳ Pending (Menunggu Pemrosesan)</option>
                                            <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>⚙️ Processing (Sedang Dikerjakan)</option>
                                            <option value="completed" <?php echo $order['status'] == 'completed' ? 'selected' : ''; ?>>✅ Completed (Selesai Diberikan)</option>
                                            <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>❌ Cancelled (Dibatalkan)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-5 pt-md-4">
                                        <button type="submit" class="btn btn-primary w-100 fw-bold">
                                            <i class="fas fa-save me-1"></i> Simpan Status
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Customer & Booking Info Sidebars -->
                <div class="col-lg-5">
                    <!-- Customer Information -->
                    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white border">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-bold text-dark"><i class="fas fa-user me-2 text-primary"></i>Data Customer</h6>
                            <a href="customer_detail.php?id=<?php echo $order['customer_id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-2 py-0">
                                Profil Lengkap
                            </a>
                        </div>
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-3 me-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="fas fa-user-check fs-4"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($order['customer_name']); ?></h5>
                                    <span class="badge <?php echo $order['customer_type'] == 'vip' ? 'bg-warning text-dark' : ($order['customer_type'] == 'corporate' ? 'bg-info text-dark' : 'bg-light text-secondary border'); ?> text-uppercase mt-1">
                                        <?php echo ucfirst($order['customer_type'] ?? 'Regular'); ?> Member
                                    </span>
                                </div>
                            </div>
                            <table class="table table-borderless table-sm mb-0">
                                <tr>
                                    <td class="text-muted" style="width: 35%;"><i class="fas fa-envelope me-2"></i>Email</td>
                                    <td class="fw-semibold text-dark"><?php echo htmlspecialchars($order['customer_email']); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted"><i class="fas fa-phone me-2"></i>Telepon</td>
                                    <td class="fw-semibold text-dark"><?php echo htmlspecialchars($order['customer_phone']); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted"><i class="fas fa-id-card me-2"></i>Identitas</td>
                                    <td class="text-dark"><?php echo strtoupper($order['identity_type'] ?? 'KTP'); ?>: <?php echo htmlspecialchars($order['identity_number'] ?? '-'); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Associated Booking Info -->
                    <div class="card border-0 shadow-sm rounded-4 bg-white border">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-bold text-dark"><i class="fas fa-bed me-2 text-primary"></i>Informasi Reservasi Terkait</h6>
                            <a href="booking_detail.php?id=<?php echo $order['booking_id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-2 py-0">
                                Buka Booking
                            </a>
                        </div>
                        <div class="card-body p-4">
                            <div class="mb-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted small">Kode Reservasi:</span>
                                    <div class="fw-bold text-primary fs-6"><?php echo htmlspecialchars($order['booking_code']); ?></div>
                                </div>
                                <span class="badge bg-light text-dark border px-3 py-2 text-uppercase">
                                    <?php echo ucfirst($order['booking_status']); ?>
                                </span>
                            </div>
                            <table class="table table-borderless table-sm mb-0">
                                <tr>
                                    <td class="text-muted" style="width: 40%;"><i class="fas fa-door-open me-2"></i>Kamar</td>
                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($order['room_number'] ?? 'Kamar'); ?> (<?php echo htmlspecialchars($order['room_type_name'] ?? 'Standar'); ?>)</td>
                                </tr>
                                <tr>
                                    <td class="text-muted"><i class="far fa-calendar-alt me-2"></i>Check In</td>
                                    <td class="text-dark"><?php echo date('d M Y', strtotime($order['check_in'])); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted"><i class="far fa-calendar-check me-2"></i>Check Out</td>
                                    <td class="text-dark"><?php echo date('d M Y', strtotime($order['check_out'])); ?> (<?php echo $order['total_nights']; ?> malam)</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
