<?php
require_once '../config/init.php';
require_once '../config/database.php';
checkAdminAuth();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    setFlashMessage('error', 'ID Customer tidak valid.');
    redirect('customers.php');
}

// Fetch Customer
$customer = $database->getSingle("SELECT * FROM customers WHERE id = ?", [$id]);

if (!$customer) {
    setFlashMessage('error', 'Customer tidak ditemukan.');
    redirect('customers.php');
}

// Fetch Customer Stats
$total_bookings = $database->getSingle("SELECT COUNT(*) as count FROM bookings WHERE customer_id = ?", [$id])['count'] ?? 0;
$total_nights = $database->getSingle("SELECT COALESCE(SUM(total_nights), 0) as total FROM bookings WHERE customer_id = ? AND status != 'cancelled'", [$id])['total'] ?? 0;
$total_spending = $database->getSingle("SELECT COALESCE(SUM(p.amount), 0) as total 
                                        FROM payments p 
                                        JOIN bookings b ON p.booking_id = b.id 
                                        WHERE b.customer_id = ? AND p.payment_status = 'paid'", [$id])['total'] ?? 0;

// Fetch Booking History
$bookings_query = "SELECT b.*, p.payment_status, p.payment_method, p.amount as paid_amount,
                          GROUP_CONCAT(DISTINCT CONCAT(r.room_number, ' (', rt.type_name, ')') SEPARATOR ', ') as room_names
                   FROM bookings b
                   LEFT JOIN payments p ON b.id = p.booking_id
                   LEFT JOIN booking_details bd ON b.id = bd.booking_id
                   LEFT JOIN rooms r ON bd.room_id = r.id
                   LEFT JOIN room_types rt ON r.room_type_id = rt.id
                   WHERE b.customer_id = ?
                   GROUP BY b.id
                   ORDER BY b.created_at DESC";
$customer_bookings = $database->getAll($bookings_query, [$id]);

// Fetch Service Orders History
$service_orders_query = "SELECT so.*, s.service_name, s.category, b.booking_code
                         FROM service_orders so
                         JOIN services s ON so.service_id = s.id
                         JOIN bookings b ON so.booking_id = b.id
                         WHERE b.customer_id = ?
                         ORDER BY so.created_at DESC";
$customer_services = $database->getAll($service_orders_query, [$id]);

// Calculate total service spending
$total_service_spending = 0;
foreach ($customer_services as $cs) {
    if ($cs['status'] === 'completed') {
        $total_service_spending += $cs['total_price'];
    }
}

$page_title = "Detail Customer - " . $customer['full_name'];
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
                        <h2 class="fw-bold mb-0" style="font-family: 'Outfit', sans-serif;"><?php echo htmlspecialchars($customer['full_name']); ?></h2>
                        <?php 
                        $badge_class = 'bg-secondary text-white';
                        if ($customer['customer_type'] == 'vip') $badge_class = 'bg-warning text-dark';
                        elseif ($customer['customer_type'] == 'corporate') $badge_class = 'bg-info text-dark';
                        ?>
                        <span class="badge <?php echo $badge_class; ?> px-3 py-2 rounded-pill text-uppercase fs-7">
                            <i class="fas fa-crown me-1 small"></i><?php echo ucfirst($customer['customer_type'] ?? 'Regular'); ?>
                        </span>
                    </div>
                    <p class="text-muted small mb-0">ID Customer: #CUST-<?php echo str_pad($customer['id'], 4, '0', STR_PAD_LEFT); ?> | Bergabung: <?php echo date('d F Y', strtotime($customer['created_at'])); ?></p>
                </div>
                <div class="d-flex gap-2">
                    <a href="customers.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                        <i class="fas fa-arrow-left me-1"></i> Kembali ke Daftar
                    </a>
                    <a href="customers.php?action=edit&id=<?php echo $customer['id']; ?>" class="btn btn-warning btn-sm rounded-pill px-3 fw-bold text-dark shadow-sm">
                        <i class="fas fa-edit me-1"></i> Edit Profil
                    </a>
                </div>
            </div>

            <!-- Customer Summary Metrics -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                        <div>
                            <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Total Pengeluaran</span>
                            <h4 class="fw-extrabold mb-0 mt-1 text-success" style="font-family: 'Outfit', sans-serif;">
                                <?php echo formatCurrency($total_spending + $total_service_spending); ?>
                            </h4>
                            <small class="text-muted"><i class="fas fa-wallet me-1 text-success"></i>Kamar + Layanan</small>
                        </div>
                        <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #d1fae5; color: #059669; width: 50px; height: 50px;">
                            <i class="fas fa-money-bill-wave fs-4"></i>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                        <div>
                            <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Total Reservasi</span>
                            <h4 class="fw-extrabold mb-0 mt-1 text-primary" style="font-family: 'Outfit', sans-serif;">
                                <?php echo $total_bookings; ?> <span class="fs-6 text-muted">Kali</span>
                            </h4>
                            <small class="text-muted"><i class="fas fa-calendar-check me-1 text-primary"></i>Riwayat Booking</small>
                        </div>
                        <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #e0e7ff; color: #4338ca; width: 50px; height: 50px;">
                            <i class="fas fa-calendar-check fs-4"></i>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                        <div>
                            <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Durasi Menginap</span>
                            <h4 class="fw-extrabold mb-0 mt-1 text-info" style="font-family: 'Outfit', sans-serif;">
                                <?php echo $total_nights; ?> <span class="fs-6 text-muted">Malam</span>
                            </h4>
                            <small class="text-muted"><i class="fas fa-moon me-1 text-info"></i>Total Menginap</small>
                        </div>
                        <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #e0f2fe; color: #0284c7; width: 50px; height: 50px;">
                            <i class="fas fa-moon fs-4"></i>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                        <div>
                            <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Pesanan Layanan</span>
                            <h4 class="fw-extrabold mb-0 mt-1 text-warning" style="font-family: 'Outfit', sans-serif;">
                                <?php echo count($customer_services); ?> <span class="fs-6 text-muted">Order</span>
                            </h4>
                            <small class="text-muted"><i class="fas fa-concierge-bell me-1 text-warning"></i>Fasilitas / Room Service</small>
                        </div>
                        <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #fef3c7; color: #d97706; width: 50px; height: 50px;">
                            <i class="fas fa-concierge-bell fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <!-- Customer Profile Info -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 bg-white border h-100">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h6 class="m-0 fw-bold text-dark"><i class="fas fa-id-card me-2 text-primary"></i>Informasi Identitas Tamu</h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="text-center mb-4 pb-3 border-bottom">
                                <div class="avatar-circle mx-auto mb-3 bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 75px; height: 75px;">
                                    <i class="fas fa-user fs-2"></i>
                                </div>
                                <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($customer['full_name']); ?></h5>
                                <span class="badge bg-light text-muted border px-3 py-1">Tipe: <?php echo ucfirst($customer['customer_type']); ?></span>
                            </div>

                            <table class="table table-borderless table-sm mb-0">
                                <tr>
                                    <td class="text-muted" style="width: 40%;"><i class="fas fa-envelope me-2 text-muted"></i>Email:</td>
                                    <td class="fw-semibold text-dark"><?php echo htmlspecialchars($customer['email']); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted"><i class="fas fa-phone me-2 text-muted"></i>Telepon:</td>
                                    <td class="fw-semibold text-dark"><?php echo htmlspecialchars($customer['phone']); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted"><i class="fas fa-passport me-2 text-muted"></i><?php echo strtoupper($customer['identity_type']); ?>:</td>
                                    <td class="fw-semibold text-dark"><?php echo htmlspecialchars($customer['identity_number']); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted"><i class="fas fa-globe me-2 text-muted"></i>Negara:</td>
                                    <td class="text-dark"><?php echo htmlspecialchars($customer['country'] ?? 'Indonesia'); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted align-top"><i class="fas fa-map-marker-alt me-2 text-muted"></i>Alamat:</td>
                                    <td class="text-dark"><?php echo nl2br(htmlspecialchars($customer['address'] ?? '-')); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Booking History Table -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4 bg-white border h-100">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <h6 class="m-0 fw-bold text-dark"><i class="fas fa-history me-2 text-primary"></i>Riwayat Reservasi Hotel</h6>
                            <span class="badge bg-primary rounded-pill"><?php echo count($customer_bookings); ?> Reservasi</span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($customer_bookings)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fas fa-calendar-times fs-2 mb-2 d-block text-secondary"></i>
                                    Belum ada riwayat reservasi kamar untuk customer ini.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-3">Kode Booking</th>
                                                <th>Kamar</th>
                                                <th>Periode Menginap</th>
                                                <th>Total Biaya</th>
                                                <th>Status</th>
                                                <th class="text-end pe-3">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($customer_bookings as $booking): ?>
                                            <tr>
                                                <td class="ps-3">
                                                    <a href="booking_detail.php?id=<?php echo $booking['id']; ?>" class="fw-bold text-primary text-decoration-none">
                                                        <?php echo htmlspecialchars($booking['booking_code']); ?>
                                                    </a>
                                                    <br><small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($booking['created_at'])); ?></small>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($booking['room_names'] ?? 'Kamar Standard'); ?></div>
                                                    <small class="text-muted"><?php echo $booking['total_guests']; ?> Tamu (<?php echo $booking['total_nights']; ?> Malam)</small>
                                                </td>
                                                <td>
                                                    <span class="text-dark small">
                                                        <?php echo date('d M Y', strtotime($booking['check_in'])); ?> - <?php echo date('d M Y', strtotime($booking['check_out'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong class="text-dark"><?php echo formatCurrency($booking['final_amount']); ?></strong>
                                                    <br>
                                                    <?php if ($booking['payment_status'] == 'paid'): ?>
                                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 small">Lunas</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 small">Belum Lunas</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_styles = [
                                                        'pending' => 'bg-warning',
                                                        'confirmed' => 'bg-info',
                                                        'checked_in' => 'bg-primary',
                                                        'checked_out' => 'bg-success',
                                                        'cancelled' => 'bg-danger'
                                                    ];
                                                    $st_class = $status_styles[$booking['status']] ?? 'bg-secondary';
                                                    ?>
                                                    <span class="badge <?php echo $st_class; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end pe-3">
                                                    <a href="booking_detail.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-outline-primary" title="Detail Booking">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Service Orders Section -->
            <?php if (!empty($customer_services)): ?>
            <div class="card border-0 shadow-sm rounded-4 bg-white border mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-dark"><i class="fas fa-concierge-bell me-2 text-primary"></i>Riwayat Pesanan Layanan & Service</h6>
                    <span class="badge bg-primary rounded-pill"><?php echo count($customer_services); ?> Pesanan</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Order ID</th>
                                    <th>Booking Ref</th>
                                    <th>Layanan</th>
                                    <th>Qty</th>
                                    <th>Total Biaya</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                    <th class="text-end pe-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customer_services as $service_order): ?>
                                <tr>
                                    <td class="ps-3 fw-bold">#<?php echo $service_order['id']; ?></td>
                                    <td>
                                        <a href="booking_detail.php?id=<?php echo $service_order['booking_id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($service_order['booking_code']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($service_order['service_name']); ?></div>
                                        <small class="text-muted"><?php echo ucfirst($service_order['category']); ?></small>
                                    </td>
                                    <td><span class="badge bg-primary"><?php echo $service_order['quantity']; ?>x</span></td>
                                    <td><strong class="text-success"><?php echo formatCurrency($service_order['total_price']); ?></strong></td>
                                    <td>
                                        <?php 
                                        $s_badges = [
                                            'pending' => 'bg-warning',
                                            'processing' => 'bg-info',
                                            'completed' => 'bg-success',
                                            'cancelled' => 'bg-danger'
                                        ];
                                        ?>
                                        <span class="badge <?php echo $s_badges[$service_order['status']] ?? 'bg-secondary'; ?>">
                                            <?php echo ucfirst($service_order['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($service_order['created_at'])); ?></td>
                                    <td class="text-end pe-3">
                                        <a href="service_order_detail.php?id=<?php echo $service_order['id']; ?>" class="btn btn-sm btn-outline-primary" title="Detail Layanan">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
