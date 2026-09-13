<?php
$unread_msg_count = 0;
if (isset($db)) {
    try {
        $stmt_msg = $db->query("SELECT COUNT(*) FROM owner_messages WHERE status = 'unread'");
        if ($stmt_msg) {
            $unread_msg_count = (int)$stmt_msg->fetchColumn();
        }
    } catch (Exception $e) {
        $unread_msg_count = 0;
    }
}
?>
<nav class="col-md-3 col-lg-2 d-md-block sidebar collapse shadow-sm">
    <div class="position-sticky pt-3 pb-4">
        <div class="px-3 mb-3 d-none d-md-block">
            <?php if (isOwner()): ?>
                <span class="text-uppercase fw-bold text-warning" style="font-size: 0.7rem; letter-spacing: 0.08em;">
                    <i class="fas fa-crown me-1"></i> Owner Portal
                </span>
            <?php elseif (isReceptionist()): ?>
                <span class="text-uppercase fw-bold text-info" style="font-size: 0.7rem; letter-spacing: 0.08em;">
                    <i class="fas fa-concierge-bell me-1"></i> Front Desk Staff
                </span>
            <?php else: ?>
                <span class="text-uppercase fw-bold text-muted" style="font-size: 0.7rem; letter-spacing: 0.08em;">
                    <i class="fas fa-shield-alt me-1"></i> Admin Menu
                </span>
            <?php endif; ?>
        </div>

        <ul class="nav flex-column">
            <!-- 1. Dashboard -->
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas <?php echo isOwner() ? 'fa-crown text-warning' : (isReceptionist() ? 'fa-concierge-bell text-info' : 'fa-tachometer-alt text-primary'); ?>"></i> 
                    <span><?php echo isOwner() ? 'Dashboard Owner' : (isReceptionist() ? 'Front Desk Hub' : 'Dashboard Admin'); ?></span>
                </a>
            </li>

            <?php if (isOwner()): ?>
            <!-- ================= OWNER MENUS ================= -->
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                    <i class="fas fa-chart-line text-success"></i> <span>Laporan & Finansial</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : ''; ?>" href="messages.php">
                    <i class="fas fa-envelope text-info"></i> <span>Pesan dari Staf</span>
                    <?php if ($unread_msg_count > 0): ?>
                        <span class="badge bg-danger rounded-pill ms-auto"><?php echo $unread_msg_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'room_types.php' ? 'active' : ''; ?>" href="room_types.php">
                    <i class="fas fa-layer-group text-primary"></i> <span>Tipe & Tarif Kamar</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'rooms.php' ? 'active' : ''; ?>" href="rooms.php">
                    <i class="fas fa-bed text-purple" style="color: #a855f7;"></i> <span>Monitoring Kamar</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'bookings.php' ? 'active' : ''; ?>" href="bookings.php">
                    <i class="fas fa-calendar-check text-warning"></i> <span>Data Reservasi</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'employees.php' ? 'active' : ''; ?>" href="employees.php">
                    <i class="fas fa-id-badge text-secondary"></i> <span>Data Karyawan</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reviews.php' ? 'active' : ''; ?>" href="reviews.php">
                    <i class="fas fa-star text-warning"></i> <span>Ulasan Tamu</span>
                </a>
            </li>

            <?php elseif (isReceptionist()): ?>
            <!-- ================= RECEPTIONIST MENUS ================= -->
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : ''; ?>" href="messages.php">
                    <i class="fas fa-paper-plane text-warning"></i> <span>Kirim Pesan ke Owner</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'rooms.php' ? 'active' : ''; ?>" href="rooms.php">
                    <i class="fas fa-bed"></i> <span>Status Kamar</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'room_types.php' ? 'active' : ''; ?>" href="room_types.php">
                    <i class="fas fa-layer-group"></i> <span>Info Tipe Kamar</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'bookings.php' ? 'active' : ''; ?>" href="bookings.php">
                    <i class="fas fa-calendar-check"></i> <span>Pemesanan (Booking)</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'active' : ''; ?>" href="customers.php">
                    <i class="fas fa-users"></i> <span>Data Tamu (Customer)</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : ''; ?>" href="payments.php">
                    <i class="fas fa-wallet"></i> <span>Kasir & Pembayaran</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['services.php', 'service_orders.php']) ? 'active' : ''; ?>" 
                   data-bs-toggle="collapse" href="#servicesMenu" role="button">
                    <i class="fas fa-concierge-bell"></i> <span>Layanan & Service</span>
                    <i class="fas fa-chevron-down ms-auto fs-6"></i>
                </a>
                <div class="collapse <?php echo in_array(basename($_SERVER['PHP_SELF']), ['services.php', 'service_orders.php']) ? 'show' : ''; ?>" id="servicesMenu">
                    <ul class="nav flex-column ms-3 mt-1 border-start border-secondary ps-2">
                        <li class="nav-item">
                            <a class="nav-link py-2 <?php echo basename($_SERVER['PHP_SELF']) == 'services.php' ? 'active' : ''; ?>" href="services.php">
                                <i class="fas fa-list me-2"></i> Daftar Layanan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-2 <?php echo basename($_SERVER['PHP_SELF']) == 'service_orders.php' ? 'active' : ''; ?>" href="service_orders.php">
                                <i class="fas fa-clipboard-check me-2"></i> Pesanan Layanan
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'maintenance.php' ? 'active' : ''; ?>" href="maintenance.php">
                    <i class="fas fa-tools"></i> <span>Maintenance Kamar</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reviews.php' ? 'active' : ''; ?>" href="reviews.php">
                    <i class="fas fa-star"></i> <span>Ulasan (Reviews)</span>
                </a>
            </li>

            <?php else: ?>
            <!-- ================= ADMIN MENUS ================= -->
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'rooms.php' ? 'active' : ''; ?>" href="rooms.php">
                    <i class="fas fa-bed"></i> <span>Status Kamar</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'room_types.php' ? 'active' : ''; ?>" href="room_types.php">
                    <i class="fas fa-layer-group"></i> <span>Tipe Kamar</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'bookings.php' ? 'active' : ''; ?>" href="bookings.php">
                    <i class="fas fa-calendar-check"></i> <span>Pemesanan (Booking)</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'active' : ''; ?>" href="customers.php">
                    <i class="fas fa-users"></i> <span>Data Tamu (Customer)</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : ''; ?>" href="payments.php">
                    <i class="fas fa-wallet"></i> <span>Kasir & Pembayaran</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['services.php', 'service_orders.php']) ? 'active' : ''; ?>" 
                   data-bs-toggle="collapse" href="#servicesMenu" role="button">
                    <i class="fas fa-concierge-bell"></i> <span>Layanan & Service</span>
                    <i class="fas fa-chevron-down ms-auto fs-6"></i>
                </a>
                <div class="collapse <?php echo in_array(basename($_SERVER['PHP_SELF']), ['services.php', 'service_orders.php']) ? 'show' : ''; ?>" id="servicesMenu">
                    <ul class="nav flex-column ms-3 mt-1 border-start border-secondary ps-2">
                        <li class="nav-item">
                            <a class="nav-link py-2 <?php echo basename($_SERVER['PHP_SELF']) == 'services.php' ? 'active' : ''; ?>" href="services.php">
                                <i class="fas fa-list me-2"></i> Daftar Layanan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-2 <?php echo basename($_SERVER['PHP_SELF']) == 'service_orders.php' ? 'active' : ''; ?>" href="service_orders.php">
                                <i class="fas fa-clipboard-check me-2"></i> Pesanan Layanan
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : ''; ?>" href="messages.php">
                    <i class="fas fa-envelope text-info"></i> <span>Pesan Staf & Resepsi</span>
                    <?php if ($unread_msg_count > 0): ?>
                        <span class="badge bg-danger rounded-pill ms-auto"><?php echo $unread_msg_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reviews.php' ? 'active' : ''; ?>" href="reviews.php">
                    <i class="fas fa-star"></i> <span>Ulasan (Reviews)</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'maintenance.php' ? 'active' : ''; ?>" href="maintenance.php">
                    <i class="fas fa-tools"></i> <span>Maintenance Kamar</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'employees.php' ? 'active' : ''; ?>" href="employees.php">
                    <i class="fas fa-id-badge"></i> <span>Karyawan</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                    <i class="fas fa-chart-line"></i> <span>Laporan Keuangan</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                    <i class="fas fa-sliders-h"></i> <span>Pengaturan Sistem</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
        
        <!-- Sidebar Widget -->
        <?php
        if (function_exists('getBookingStatistics')) {
            $sidebar_stats = getBookingStatistics($db);
        } else {
            $sidebar_stats = ['available_rooms' => 0, 'pending_bookings' => 0];
        }
        ?>
        <div class="mx-2 mt-4 p-3 rounded-3" style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1);">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small fw-bold text-light">Status Ringkas</span>
                <span class="badge bg-success rounded-pill" style="font-size: 0.65rem;">Online</span>
            </div>
            <div class="row text-center g-2 mt-1">
                <div class="col-6">
                    <div class="p-2 rounded bg-dark">
                        <div class="text-emerald-400 fw-bold fs-6 text-success"><?php echo $sidebar_stats['available_rooms'] ?? 0; ?></div>
                        <small class="text-muted" style="font-size: 0.7rem;">Kamar Free</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-2 rounded bg-dark">
                        <div class="text-warning fw-bold fs-6"><?php echo $sidebar_stats['pending_bookings'] ?? 0; ?></div>
                        <small class="text-muted" style="font-size: 0.7rem;">Pending</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>