<?php
require_once '../config/init.php';

checkAdminAuth();

$is_owner = isOwner();
$is_receptionist = isReceptionist();
$is_admin = isAdmin();

if ($is_owner) {
    $page_title = "Owner Executive Dashboard & Analytics";
} elseif ($is_receptionist) {
    $page_title = "Front Desk Hub & Resepsionis";
} else {
    $page_title = "Admin Dashboard";
}

include '../includes/header.php';

// Get statistics safely from database
$stats = getBookingStatistics($db);

// Additional statistics directly from database
$query_cust = "SELECT COUNT(*) as count FROM customers";
$stmt_cust = $db->query($query_cust);
$stats['total_customers'] = $stmt_cust->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$query_occ = "SELECT COUNT(*) as count FROM rooms WHERE status = 'occupied'";
$stmt_occ = $db->query($query_occ);
$stats['occupied_rooms'] = $stmt_occ->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Room status summary
$query_room_st = "SELECT status, COUNT(*) as count FROM rooms GROUP BY status";
$room_status_raw = $database->getAll($query_room_st);
$room_status = [
    'available' => 0,
    'occupied' => 0,
    'maintenance' => 0,
    'cleaning' => 0
];
foreach ($room_status_raw as $r_st) {
    if (isset($room_status[$r_st['status']])) {
        $room_status[$r_st['status']] = (int)$r_st['count'];
    }
}

// Calculate Occupancy Rate percentage
$total_rooms_cnt = max(1, (int)($stats['total_rooms'] ?? 1));
$occupancy_rate = round(($stats['occupied_rooms'] / $total_rooms_cnt) * 100, 1);

// Today's activities
$today = date('Y-m-d');
$query_today = "SELECT 
            (SELECT COUNT(*) FROM bookings WHERE check_in = '$today' AND status IN ('confirmed', 'checked_in')) as today_checkins,
            (SELECT COUNT(*) FROM bookings WHERE check_out = '$today' AND status IN ('checked_in', 'checked_out')) as today_checkouts,
            (SELECT COUNT(*) FROM bookings WHERE DATE(created_at) = '$today') as today_bookings";
$today_stats = $database->getSingle($query_today);

if ($is_receptionist) {
    // Receptionist Specific Data
    // 1. Arrivals (confirmed bookings with check_in on or before today)
    $query_arrivals = "SELECT b.*, c.full_name, c.phone as customer_phone, c.email as customer_email,
                              r.room_number, rt.type_name, p.payment_status
                       FROM bookings b
                       LEFT JOIN customers c ON b.customer_id = c.id
                       LEFT JOIN booking_details bd ON b.id = bd.booking_id
                       LEFT JOIN rooms r ON bd.room_id = r.id
                       LEFT JOIN room_types rt ON r.room_type_id = rt.id
                       LEFT JOIN payments p ON b.id = p.booking_id
                       WHERE b.status = 'confirmed' AND b.check_in <= :today
                       GROUP BY b.id
                       ORDER BY b.check_in ASC";
    $stmt_arr = $db->prepare($query_arrivals);
    $stmt_arr->execute(['today' => $today]);
    $arrivals = $stmt_arr->fetchAll(PDO::FETCH_ASSOC);

    // 2. Departures (checked-in bookings with check_out on or before today)
    $query_departures = "SELECT b.*, c.full_name, c.phone as customer_phone, c.email as customer_email,
                                r.room_number, rt.type_name, p.payment_status
                         FROM bookings b
                         LEFT JOIN customers c ON b.customer_id = c.id
                         LEFT JOIN booking_details bd ON b.id = bd.booking_id
                         LEFT JOIN rooms r ON bd.room_id = r.id
                         LEFT JOIN room_types rt ON r.room_type_id = rt.id
                         LEFT JOIN payments p ON b.id = p.booking_id
                         WHERE b.status = 'checked_in' AND b.check_out <= :today
                         GROUP BY b.id
                         ORDER BY b.check_out ASC";
    $stmt_dep = $db->prepare($query_departures);
    $stmt_dep->execute(['today' => $today]);
    $departures = $stmt_dep->fetchAll(PDO::FETCH_ASSOC);

    // 3. In-House Guests
    $query_inhouse = "SELECT b.*, c.full_name, c.phone as customer_phone, c.email as customer_email,
                             r.room_number, rt.type_name, p.payment_status
                      FROM bookings b
                      LEFT JOIN customers c ON b.customer_id = c.id
                      LEFT JOIN booking_details bd ON b.id = bd.booking_id
                      LEFT JOIN rooms r ON bd.room_id = r.id
                      LEFT JOIN room_types rt ON r.room_type_id = rt.id
                      LEFT JOIN payments p ON b.id = p.booking_id
                      WHERE b.status = 'checked_in'
                      GROUP BY b.id
                      ORDER BY r.room_number ASC";
    $stmt_inh = $db->query($query_inhouse);
    $in_house = $stmt_inh->fetchAll(PDO::FETCH_ASSOC);

    // 4. All Rooms with floor & type
    $query_rooms = "SELECT r.*, rt.type_name, rt.base_price 
                    FROM rooms r 
                    LEFT JOIN room_types rt ON r.room_type_id = rt.id 
                    ORDER BY r.floor ASC, r.room_number ASC";
    $all_rooms = $db->query($query_rooms)->fetchAll(PDO::FETCH_ASSOC);

} else {
    // Admin & Owner Shared Data (Financial & Analytics)
    // Total revenue (paid payments)
    $query_rev = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_status = 'paid'";
    $stmt_rev = $db->query($query_rev);
    $stats['total_revenue'] = $stmt_rev->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Monthly revenue (current month)
    $query_mrev = "SELECT COALESCE(SUM(p.amount), 0) as total 
                   FROM payments p 
                   JOIN bookings b ON p.booking_id = b.id 
                   WHERE p.payment_status = 'paid' 
                   AND MONTH(b.created_at) = MONTH(CURRENT_DATE()) 
                   AND YEAR(b.created_at) = YEAR(CURRENT_DATE())";
    $stmt_mrev = $db->query($query_mrev);
    $stats['monthly_revenue'] = $stmt_mrev->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Chart Data: Last 6 Months Revenue & Booking Trend
    $max_date_stmt = $db->query("SELECT MAX(check_in) as max_date FROM bookings");
    $max_date_str = $max_date_stmt->fetch(PDO::FETCH_ASSOC)['max_date'] ?? date('Y-m-d');
    $max_ts = strtotime($max_date_str);

    $monthly_labels = [];
    $monthly_booking_data = [];
    $monthly_revenue_data = [];

    for ($i = 5; $i >= 0; $i--) {
        $month_ts = strtotime("-$i months", $max_ts);
        $m_num = date('m', $month_ts);
        $y_num = date('Y', $month_ts);
        $m_label = date('M Y', $month_ts);
        
        $monthly_labels[] = $m_label;
        
        // Count bookings
        $q_b = "SELECT COUNT(*) as count FROM bookings WHERE MONTH(check_in) = :m AND YEAR(check_in) = :y";
        $stmt_b = $db->prepare($q_b);
        $stmt_b->execute(['m' => $m_num, 'y' => $y_num]);
        $monthly_booking_data[] = (int)($stmt_b->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        
        // Sum revenue
        $q_r = "SELECT COALESCE(SUM(p.amount), 0) as total 
                FROM payments p 
                JOIN bookings b ON p.booking_id = b.id 
                WHERE p.payment_status = 'paid' 
                AND MONTH(b.check_in) = :m AND YEAR(b.check_in) = :y";
        $stmt_r = $db->prepare($q_r);
        $stmt_r->execute(['m' => $m_num, 'y' => $y_num]);
        $monthly_revenue_data[] = (float)($stmt_r->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    if ($is_owner) {
        // Owner Specific Data
        // 1. Average Rating & Reviews
        $rating_stmt = $db->query("SELECT COALESCE(AVG(overall_rating), 4.8) as avg_r, COUNT(*) as count_r FROM reviews WHERE is_approved = 1");
        $rating_data = $rating_stmt->fetch(PDO::FETCH_ASSOC);
        $avg_rating = round($rating_data['avg_r'] ?? 4.8, 1);
        $total_approved_reviews = (int)($rating_data['count_r'] ?? 0);

        // 2. Room Types Revenue & Performance
        $q_rt_perf = "SELECT rt.type_name, rt.base_price, rt.capacity,
                             COUNT(DISTINCT r.id) as total_units,
                             COUNT(b.id) as booking_count,
                             COALESCE(SUM(b.final_amount), 0) as total_revenue
                      FROM room_types rt
                      LEFT JOIN rooms r ON rt.id = r.room_type_id
                      LEFT JOIN booking_details bd ON r.id = bd.room_id
                      LEFT JOIN bookings b ON bd.booking_id = b.id
                      GROUP BY rt.id
                      ORDER BY total_revenue DESC";
        $owner_room_perf = $db->query($q_rt_perf)->fetchAll(PDO::FETCH_ASSOC);

        // 3. Unread Messages from Staff
        $q_owner_msgs = "SELECT * FROM owner_messages WHERE status = 'unread' ORDER BY CASE priority WHEN 'mendesak' THEN 1 WHEN 'penting' THEN 2 ELSE 3 END, created_at DESC LIMIT 3";
        $owner_unread_messages = $db->query($q_owner_msgs)->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // Admin Specific Data
        // Recent 5 Bookings
        $query_recent = "SELECT b.*, c.full_name, c.email as customer_email, c.phone as customer_phone,
                                r.room_number, rt.type_name,
                                p.payment_status, p.payment_method
                         FROM bookings b
                         LEFT JOIN customers c ON b.customer_id = c.id
                         LEFT JOIN booking_details bd ON b.id = bd.booking_id
                         LEFT JOIN rooms r ON bd.room_id = r.id
                         LEFT JOIN room_types rt ON r.room_type_id = rt.id
                         LEFT JOIN payments p ON b.id = p.booking_id
                         GROUP BY b.id
                         ORDER BY b.created_at DESC
                         LIMIT 5";
        $recent_bookings = $database->getAll($query_recent);

        // Booking Status Distribution
        $q_status = "SELECT status, COUNT(*) as count FROM bookings GROUP BY status";
        $booking_status_raw = $database->getAll($q_status);
        $booking_status_counts = [
            'pending' => 0,
            'confirmed' => 0,
            'checked_in' => 0,
            'checked_out' => 0,
            'cancelled' => 0
        ];
        foreach ($booking_status_raw as $bs) {
            if (isset($booking_status_counts[$bs['status']])) {
                $booking_status_counts[$bs['status']] = (int)$bs['count'];
            }
        }
    }
}
?>

<?php if (!$is_receptionist): ?>
<!-- Include Chart.js Library for Executive Views -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<?php endif; ?>

<style>
    /* Dashboard Hero Banner */
    .dashboard-hero {
        background: #1e293b;
        color: #ffffff;
        border-radius: 16px;
        padding: 24px 30px;
        border: 1px solid #334155;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        margin-bottom: 24px;
    }

    .dashboard-hero h2 {
        color: #ffffff !important;
    }

    .dashboard-hero p {
        color: #94a3b8 !important;
    }

    .stat-card-widget {
        background: #ffffff;
        border-radius: 18px;
        padding: 22px 24px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .stat-card-widget:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 25px rgba(0, 0, 0, 0.08);
        border-color: #cbd5e1;
    }

    .stat-icon-wrapper {
        width: 56px;
        height: 56px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .card-custom {
        border-radius: 18px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        transition: all 0.3s ease;
    }

    .badge-status-pill {
        font-size: 0.75rem;
        font-weight: 700;
        padding: 6px 14px;
        border-radius: 50rem;
        letter-spacing: 0.03em;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .badge-pending { background-color: #fef3c7; color: #d97706; }
    .badge-confirmed { background-color: #e0f2fe; color: #0284c7; }
    .badge-checked_in { background-color: #d1fae5; color: #059669; }
    .badge-checked_out { background-color: #f3e8ff; color: #7c3aed; }
    .badge-cancelled { background-color: #fee2e2; color: #dc2626; }

    .quick-action-btn {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 18px 12px;
        transition: all 0.3s ease;
        text-decoration: none;
        color: #1e293b;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .quick-action-btn:hover {
        background: #ffffff;
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.06);
        border-color: #4f46e5;
        color: #4f46e5;
    }

    .quick-action-btn i {
        font-size: 1.8rem;
        transition: transform 0.3s ease;
    }

    .quick-action-btn:hover i {
        transform: scale(1.15);
    }

    /* Room Rack Card Styling */
    .room-rack-card {
        border-radius: 14px;
        border: 2px solid #e2e8f0;
        padding: 14px;
        transition: all 0.25s ease;
        background: #ffffff;
        position: relative;
    }

    .room-rack-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 18px rgba(0,0,0,0.06);
    }

    .room-rack-available { border-color: #86efac; background: #f0fdf4; }
    .room-rack-occupied { border-color: #fca5a5; background: #fef2f2; }
    .room-rack-cleaning { border-color: #93c5fd; background: #eff6ff; }
    .room-rack-maintenance { border-color: #fde047; background: #fefce8; }

    .nav-tabs-custom .nav-link {
        color: #64748b;
        font-weight: 600;
        border: none;
        border-bottom: 3px solid transparent;
        padding: 12px 20px;
        border-radius: 0;
    }
    .nav-tabs-custom .nav-link.active {
        color: #4f46e5;
        background: transparent;
        border-bottom-color: #4f46e5;
    }
</style>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Navigation -->
        <?php include '../includes/admin-sidebar.php'; ?>

        <!-- Main Dashboard Body -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            
            <?php if ($is_owner): ?>
            <!-- ========================================== -->
            <!-- OWNER EXECUTIVE DASHBOARD & PORTAL -->
            <!-- ========================================== -->

            <!-- Hero Welcome Banner -->
            <div class="dashboard-hero d-flex flex-wrap justify-content-between align-items-center" 
                 style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-warning text-dark fw-bold px-3 py-1 rounded-pill" style="letter-spacing: 0.05em;">
                            <i class="fas fa-crown me-1"></i> OWNER EXECUTIVE SUITE
                        </span>
                        <span class="text-white-50 small">Hotel Ownership & Performance Portal</span>
                    </div>
                    <h2 class="fw-extrabold mb-1" style="font-family: 'Outfit', sans-serif;">
                        Selamat Datang, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Bapak Owner'); ?>! 👑
                    </h2>
                    <p class="mb-0">Pantau pertumbuhan bisnis hotel, performa finansial, okupansi, dan pesan operasional staf secara real-time.</p>
                </div>
                <div class="mt-3 mt-md-0 d-flex gap-2 align-items-center flex-wrap">
                    <a href="export-report.php?start_date=<?php echo date('Y-01-01'); ?>&end_date=<?php echo date('Y-12-31'); ?>" class="btn btn-success btn-md rounded-pill px-3 fw-bold shadow-sm">
                        <i class="fas fa-file-excel me-1"></i> Download Laporan Excel
                    </a>
                    <a href="reports.php" class="btn btn-warning btn-md rounded-pill px-3 fw-bold shadow-sm">
                        <i class="fas fa-chart-line me-1"></i> Laporan Rinci
                    </a>
                </div>
            </div>

            <!-- Top Metric Stat Cards for Owner -->
            <div class="row g-3 mb-4">
                <!-- Card 1: Total Omset Bersih -->
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card-widget">
                        <div>
                            <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Total Omset (Semua Waktu)</span>
                            <h3 class="fs-4 fw-extrabold mb-0 mt-1 text-success" style="font-family: 'Outfit', sans-serif;">
                                Rp <?php echo number_format($stats['total_revenue'], 0, ',', '.'); ?>
                            </h3>
                            <span class="small text-muted"><i class="fas fa-calendar-alt text-success me-1"></i>Bulan Ini: Rp <?php echo number_format($stats['monthly_revenue'], 0, ',', '.'); ?></span>
                        </div>
                        <div class="stat-icon-wrapper" style="background: #d1fae5; color: #059669;">
                            <i class="fas fa-coins"></i>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Occupancy Rate -->
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card-widget">
                        <div>
                            <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Tingkat Okupansi Kamar</span>
                            <h3 class="display-6 fw-extrabold mb-0 mt-1 text-primary" style="font-family: 'Outfit', sans-serif;">
                                <?php echo $occupancy_rate; ?>%
                            </h3>
                            <span class="small text-muted"><i class="fas fa-bed text-primary me-1"></i><?php echo $stats['occupied_rooms']; ?> dari <?php echo $stats['total_rooms']; ?> Kamar Terisi</span>
                        </div>
                        <div class="stat-icon-wrapper" style="background: #e0f2fe; color: #0284c7;">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Total Reservasi -->
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card-widget">
                        <div>
                            <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Total Reservasi Masuk</span>
                            <h3 class="display-6 fw-extrabold mb-0 mt-1" style="font-family: 'Outfit', sans-serif; color: #0f172a;">
                                <?php echo $stats['total_bookings']; ?>
                            </h3>
                            <span class="small text-muted"><i class="fas fa-users text-info me-1"></i><?php echo $stats['total_customers']; ?> Pelanggan Terdaftar</span>
                        </div>
                        <div class="stat-icon-wrapper" style="background: #fef3c7; color: #d97706;">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Kepuasan Tamu (Rating) -->
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card-widget">
                        <div>
                            <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Rating & Kepuasan Tamu</span>
                            <h3 class="display-6 fw-extrabold mb-0 mt-1 text-warning" style="font-family: 'Outfit', sans-serif;">
                                <?php echo $avg_rating; ?> <span class="fs-6 text-muted font-normal">/ 5.0 ⭐</span>
                            </h3>
                            <span class="small text-muted"><i class="fas fa-star text-warning me-1"></i><?php echo $total_approved_reviews; ?> Ulasan Terverifikasi</span>
                        </div>
                        <div class="stat-icon-wrapper" style="background: #fefce8; color: #ca8a04;">
                            <i class="fas fa-award"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Urgent Staff Messages Alert for Owner -->
            <?php if (!empty($owner_unread_messages)): ?>
            <div class="card-custom p-4 mb-4 border-warning" style="background: #fffbeb; border-left: 6px solid #f59e0b;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-danger rounded-pill px-3 py-1 fw-bold"><i class="fas fa-bell me-1"></i> PERHATIAN OWNER</span>
                        <h6 class="fw-bold mb-0 text-dark">Ada <?php echo count($owner_unread_messages); ?> Pesan / Catatan Baru dari Staf Resepsionis</h6>
                    </div>
                    <a href="messages.php" class="btn btn-sm btn-outline-dark rounded-pill px-3 fw-bold">
                        Buka Semua Pesan <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="row g-2 mt-2">
                    <?php foreach ($owner_unread_messages as $ou_msg): ?>
                    <div class="col-md-4">
                        <div class="p-3 bg-white rounded-3 border shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge <?php echo $ou_msg['priority'] === 'mendesak' ? 'bg-danger' : 'bg-warning text-dark'; ?> rounded-pill" style="font-size: 0.65rem;">
                                    <?php echo ucfirst($ou_msg['priority']); ?>
                                </span>
                                <small class="text-muted" style="font-size: 0.7rem;"><?php echo date('d M, H:i', strtotime($ou_msg['created_at'])); ?></small>
                            </div>
                            <div class="fw-bold text-dark small text-truncate"><?php echo htmlspecialchars($ou_msg['subject']); ?></div>
                            <small class="text-muted d-block text-truncate"><?php echo htmlspecialchars($ou_msg['message']); ?></small>
                            <a href="messages.php" class="small fw-bold text-primary text-decoration-none mt-2 d-inline-block">Tanggapi Pesan &rarr;</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Interactive Charts Section for Owner -->
            <div class="row g-4 mb-4">
                <!-- Main Chart: Monthly Revenue & Booking Trend -->
                <div class="col-lg-8">
                    <div class="card-custom p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-chart-area text-primary me-2"></i>Tren Pendapatan & Pertumbuhan Bisnis Hotel</h6>
                                <small class="text-muted">Perbandingan omset dan jumlah booking dalam 6 bulan terakhir.</small>
                            </div>
                            <a href="export-report.php?start_date=<?php echo date('Y-01-01'); ?>&end_date=<?php echo date('Y-12-31'); ?>" class="btn btn-sm btn-outline-success rounded-pill px-3">
                                <i class="fas fa-download me-1"></i> Export Data
                            </a>
                        </div>
                        <div style="position: relative; min-height: 280px; width: 100%;">
                            <canvas id="revenueTrendChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Donut Chart: Room Status Distribution -->
                <div class="col-lg-4">
                    <div class="card-custom p-4 h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-pie-chart text-warning me-2"></i>Status Ketersediaan Kamar</h6>
                                <span class="badge bg-primary rounded-pill"><?php echo $stats['total_rooms']; ?> Kamar</span>
                            </div>
                            <div style="position: relative; height: 210px; width: 100%;" class="d-flex justify-content-center">
                                <canvas id="roomStatusChart"></canvas>
                            </div>
                        </div>
                        <div class="row text-center g-2 mt-3 pt-3 border-top">
                            <div class="col-6">
                                <div class="p-2 rounded bg-light">
                                    <small class="text-muted d-block">Siap Huni</small>
                                    <span class="fw-bold text-success fs-6"><?php echo $room_status['available']; ?> Kamar</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 rounded bg-light">
                                    <small class="text-muted d-block">Terisi Tamu</small>
                                    <span class="fw-bold text-danger fs-6"><?php echo $room_status['occupied']; ?> Kamar</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Room Types Performance Table for Owner -->
            <div class="card-custom p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <div>
                        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-layer-group text-primary me-2"></i>Analisis Kinerja Pendapatan Berdasarkan Tipe Kamar</h6>
                        <small class="text-muted">Rincian kontribusi omset dan tingkat keterisian tiap kategori kamar hotel.</small>
                    </div>
                    <a href="room_types.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                        Lihat Rincian Tipe Kamar <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr class="text-uppercase text-muted" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                                <th>Kategori Tipe Kamar</th>
                                <th>Tarif / Malam</th>
                                <th>Kapasitas & Unit</th>
                                <th>Total Booking</th>
                                <th>Total Omset Dihasilkan</th>
                                <th class="text-end">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($owner_room_perf as $orp): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($orp['type_name']); ?></div>
                                </td>
                                <td>
                                    <div class="fw-bold text-primary">Rp <?php echo number_format($orp['base_price'], 0, ',', '.'); ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?php echo $orp['total_units']; ?> Kamar</span>
                                    <span class="badge bg-light text-secondary border ms-1"><?php echo $orp['capacity']; ?> Tamu</span>
                                </td>
                                <td>
                                    <span class="badge bg-info bg-opacity-10 text-primary fw-bold px-2 py-1"><?php echo $orp['booking_count']; ?> Reservasi</span>
                                </td>
                                <td>
                                    <strong class="text-success fs-6">Rp <?php echo number_format($orp['total_revenue'], 0, ',', '.'); ?></strong>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                        <i class="fas fa-check-circle me-1"></i>Aktif
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Owner Quick Action Hub -->
            <div class="card-custom p-4 mb-4">
                <h6 class="fw-bold text-dark mb-3"><i class="fas fa-bolt me-2 text-warning"></i>Akses Pintas Cepat Owner</h6>
                <div class="row g-3">
                    <div class="col-md-2 col-4">
                        <a href="reports.php" class="quick-action-btn">
                            <i class="fas fa-chart-line text-success"></i>
                            <span class="small fw-bold">Laporan Finansial</span>
                        </a>
                    </div>
                    <div class="col-md-2 col-4">
                        <a href="export-report.php?start_date=<?php echo date('Y-01-01'); ?>&end_date=<?php echo date('Y-12-31'); ?>" class="quick-action-btn">
                            <i class="fas fa-file-excel text-primary"></i>
                            <span class="small fw-bold">Download Excel</span>
                        </a>
                    </div>
                    <div class="col-md-2 col-4">
                        <a href="messages.php" class="quick-action-btn">
                            <i class="fas fa-envelope text-info"></i>
                            <span class="small fw-bold">Pesan Staf</span>
                        </a>
                    </div>
                    <div class="col-md-2 col-4">
                        <a href="room_types.php" class="quick-action-btn">
                            <i class="fas fa-layer-group text-warning"></i>
                            <span class="small fw-bold">Tipe Kamar</span>
                        </a>
                    </div>
                    <div class="col-md-2 col-4">
                        <a href="reviews.php" class="quick-action-btn">
                            <i class="fas fa-star text-warning"></i>
                            <span class="small fw-bold">Ulasan Tamu</span>
                        </a>
                    </div>
                    <div class="col-md-2 col-4">
                        <a href="employees.php" class="quick-action-btn">
                            <i class="fas fa-id-badge text-secondary"></i>
                            <span class="small fw-bold">Data Karyawan</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Chart script for Owner -->
            <script>
            document.addEventListener("DOMContentLoaded", function () {
                // Chart 1: Revenue & Booking Trend
                const ctxTrend = document.getElementById('revenueTrendChart').getContext('2d');
                new Chart(ctxTrend, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($monthly_labels); ?>,
                        datasets: [
                            {
                                type: 'line',
                                label: 'Pendapatan (Rp)',
                                data: <?php echo json_encode($monthly_revenue_data); ?>,
                                borderColor: '#10b981',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                yAxisID: 'yRevenue'
                            },
                            {
                                type: 'bar',
                                label: 'Jumlah Booking',
                                data: <?php echo json_encode($monthly_booking_data); ?>,
                                backgroundColor: 'rgba(79, 70, 229, 0.75)',
                                hoverBackgroundColor: '#4f46e5',
                                borderRadius: 8,
                                barThickness: 24,
                                yAxisID: 'yBooking'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top', labels: { font: { family: 'Plus Jakarta Sans', weight: '600' } } },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) { label += ': '; }
                                        if (context.dataset.yAxisID === 'yRevenue') {
                                            label += 'Rp ' + context.raw.toLocaleString('id-ID');
                                        } else {
                                            label += context.raw + ' Reservasi';
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { grid: { display: false } },
                            yRevenue: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                grid: { color: '#f1f5f9' },
                                ticks: {
                                    callback: function(value) {
                                        if (value >= 1000000) return 'Rp ' + (value/1000000).toFixed(1) + ' Jt';
                                        if (value >= 1000) return 'Rp ' + (value/1000).toFixed(0) + ' Rb';
                                        return 'Rp ' + value;
                                    }
                                }
                            },
                            yBooking: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                grid: { display: false },
                                ticks: { precision: 0 }
                            }
                        }
                    }
                });

                // Chart 2: Room Status Distribution
                const ctxRoom = document.getElementById('roomStatusChart').getContext('2d');
                new Chart(ctxRoom, {
                    type: 'doughnut',
                    data: {
                        labels: ['Available (Tersedia)', 'Occupied (Terisi)', 'Maintenance (Perbaikan)', 'Cleaning (Pembersihan)'],
                        datasets: [{
                            data: [
                                <?php echo $room_status['available']; ?>,
                                <?php echo $room_status['occupied']; ?>,
                                <?php echo $room_status['maintenance']; ?>,
                                <?php echo $room_status['cleaning']; ?>
                            ],
                            backgroundColor: ['#10b981', '#ef4444', '#f59e0b', '#3b82f6'],
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11, family: 'Plus Jakarta Sans' } } }
                        },
                        cutout: '70%'
                    }
                });
            });
            </script>

            <?php elseif ($is_receptionist): ?>
            <!-- ========================================== -->
            <!-- RECEPTIONIST FRONT DESK OPERATIONS HUB -->
            <!-- ========================================== -->

            <!-- Hero Welcome Banner -->
            <div class="dashboard-hero d-flex flex-wrap justify-content-between align-items-center" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-info text-dark fw-bold px-3 py-1 rounded-pill" style="letter-spacing: 0.05em;"><i class="fas fa-concierge-bell me-1"></i> FRONT DESK HUB</span>
                        <span class="text-white-50 small">Sistem Operasional Resepsionis</span>
                    </div>
                    <h2 class="fw-extrabold mb-1" style="font-family: 'Outfit', sans-serif;">
                        Halo, <?php echo htmlspecialchars($_SESSION['user']['full_name'] ?? $_SESSION['full_name'] ?? 'Resepsionis'); ?>! 👋
                    </h2>
                    <p class="mb-0">Kelola reservasi tamu, proses check-in / check-out cepat, dan pantau ketersediaan kamar hari ini.</p>
                </div>
                <div class="mt-3 mt-md-0 d-flex gap-2 align-items-center flex-wrap">
                    <a href="messages.php" class="btn btn-outline-warning btn-md rounded-pill px-3 fw-bold">
                        <i class="fas fa-paper-plane me-1"></i> Kirim Pesan ke Owner
                    </a>
                    <a href="bookings.php?action=create" class="btn btn-warning btn-md rounded-pill px-4 fw-bold shadow-sm">
                        <i class="fas fa-user-plus me-1"></i> Walk-In Booking
                    </a>
                </div>
            </div>

            <!-- Front Desk Metric Cards -->
            <div class="row g-3 mb-4">
                <!-- Card 1: Check-in Hari Ini -->
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card-widget">
                        <div>
                            <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Tamu Check-In Hari Ini</span>
                            <h3 class="display-6 fw-extrabold mb-0 mt-1 text-primary" style="font-family: 'Outfit', sans-serif;">
                                <?php echo count($arrivals); ?>
                            </h3>
                            <span class="small text-muted"><i class="fas fa-sign-in-alt text-primary me-1"></i>Menunggu Kedatangan</span>
                        </div>
                        <div class="stat-icon-wrapper" style="background: #e0f2fe; color: #0284c7;">
                            <i class="fas fa-door-open"></i>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Check-out Hari Ini -->
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card-widget">
                        <div>
                            <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Tamu Check-Out Hari Ini</span>
                            <h3 class="display-6 fw-extrabold mb-0 mt-1 text-purple" style="font-family: 'Outfit', sans-serif; color: #7c3aed;">
                                <?php echo count($departures); ?>
                            </h3>
                            <span class="small text-muted"><i class="fas fa-sign-out-alt text-purple me-1" style="color: #7c3aed;"></i>Jadwal Kepulangan</span>
                        </div>
                        <div class="stat-icon-wrapper" style="background: #f3e8ff; color: #7c3aed;">
                            <i class="fas fa-key"></i>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Kamar Siap Huni (Available) -->
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card-widget">
                        <div>
                            <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Kamar Siap Huni</span>
                            <h3 class="display-6 fw-extrabold mb-0 mt-1 text-success" style="font-family: 'Outfit', sans-serif;">
                                <?php echo $room_status['available']; ?> <span class="fs-6 text-muted font-normal">/ <?php echo $stats['total_rooms']; ?></span>
                            </h3>
                            <span class="small text-success fw-semibold"><i class="fas fa-check-circle me-1"></i>Siap Untuk Tamu Baru</span>
                        </div>
                        <div class="stat-icon-wrapper" style="background: #dcfce7; color: #15803d;">
                            <i class="fas fa-bed"></i>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Kamar Perlu Penanganan (Cleaning / Maintenance) -->
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card-widget">
                        <div>
                            <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Kamar Sedang Dibersihkan</span>
                            <h3 class="display-6 fw-extrabold mb-0 mt-1 text-warning" style="font-family: 'Outfit', sans-serif;">
                                <?php echo $room_status['cleaning']; ?> <span class="fs-6 text-muted font-normal">(<?php echo $room_status['maintenance']; ?> Repair)</span>
                            </h3>
                            <span class="small text-muted"><i class="fas fa-broom text-warning me-1"></i>Housekeeping & Perbaikan</span>
                        </div>
                        <div class="stat-icon-wrapper" style="background: #fef3c7; color: #d97706;">
                            <i class="fas fa-broom"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Front Desk Operational Tables Tabs -->
            <div class="card-custom mb-4">
                <div class="border-bottom px-4 pt-3">
                    <ul class="nav nav-tabs nav-tabs-custom" id="frontDeskTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="arrivals-tab" data-bs-toggle="tab" data-bs-target="#arrivals" type="button" role="tab">
                                <i class="fas fa-plane-arrival text-primary me-2"></i>Kedatangan Tamu (Check-In) 
                                <span class="badge bg-primary rounded-pill ms-2"><?php echo count($arrivals); ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="departures-tab" data-bs-toggle="tab" data-bs-target="#departures" type="button" role="tab">
                                <i class="fas fa-plane-departure text-purple me-2" style="color: #7c3aed;"></i>Keberangkatan (Check-Out) 
                                <span class="badge bg-purple rounded-pill ms-2" style="background-color: #7c3aed; color: #fff;"><?php echo count($departures); ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="inhouse-tab" data-bs-toggle="tab" data-bs-target="#inhouse" type="button" role="tab">
                                <i class="fas fa-users text-success me-2"></i>Tamu Menginap Saat Ini 
                                <span class="badge bg-success rounded-pill ms-2"><?php echo count($in_house); ?></span>
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="tab-content p-4" id="frontDeskTabContent">
                    <!-- Tab 1: Arrivals -->
                    <div class="tab-pane fade show active" id="arrivals" role="tabpanel">
                        <?php if (empty($arrivals)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-check fs-1 text-muted opacity-50 mb-3 d-block"></i>
                                <h6 class="fw-bold text-dark">Tidak ada tamu yang menunggu check-in untuk hari ini.</h6>
                                <p class="text-muted small">Semua tamu terjadwal sudah berhasil diproses atau belum ada jadwal tiba.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr class="text-uppercase text-muted" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                                            <th>Kode & Tamu</th>
                                            <th>Kamar & Tipe</th>
                                            <th>Tanggal Stay</th>
                                            <th>Status Bayar</th>
                                            <th class="text-end pe-3">Aksi Front Desk</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($arrivals as $arr): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-primary small"><?php echo htmlspecialchars($arr['booking_code']); ?></div>
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($arr['full_name']); ?></div>
                                                <small class="text-muted"><i class="fas fa-phone-alt me-1 text-secondary"></i><?php echo htmlspecialchars($arr['customer_phone'] ?? '-'); ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark"><i class="fas fa-door-closed text-primary me-1"></i>Kamar <?php echo htmlspecialchars($arr['room_number'] ?? '-'); ?></div>
                                                <span class="badge bg-light text-secondary border"><?php echo htmlspecialchars($arr['type_name'] ?? 'Standar'); ?></span>
                                            </td>
                                            <td>
                                                <div class="small fw-semibold text-dark"><?php echo date('d M Y', strtotime($arr['check_in'])); ?> s.d <?php echo date('d M Y', strtotime($arr['check_out'])); ?></div>
                                                <small class="text-muted"><?php echo $arr['total_nights']; ?> Malam (<?php echo $arr['adults']; ?> Dewasa, <?php echo $arr['children']; ?> Anak)</small>
                                            </td>
                                            <td>
                                                <?php if (($arr['payment_status'] ?? '') === 'paid'): ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1"><i class="fas fa-check-circle me-1"></i>Lunas</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-1"><i class="fas fa-clock me-1"></i>Belum Lunas</span>
                                                <?php endif; ?>
                                                <div class="small fw-bold text-dark mt-1">Rp <?php echo number_format($arr['final_amount'], 0, ',', '.'); ?></div>
                                            </td>
                                            <td class="text-end pe-3">
                                                <div class="btn-group">
                                                    <a href="bookings.php?update_status=checked_in&id=<?php echo $arr['id']; ?>&back=dashboard" 
                                                       class="btn btn-sm btn-success rounded-pill px-3 fw-bold"
                                                       onclick="return confirm('Konfirmasi proses Check-In untuk tamu <?php echo addslashes($arr['full_name']); ?>?')">
                                                        <i class="fas fa-sign-in-alt me-1"></i> Check-In
                                                    </a>
                                                    <a href="bookings.php?action=view&id=<?php echo $arr['id']; ?>" class="btn btn-sm btn-outline-secondary rounded-pill ms-1 px-2" title="Detail Booking">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tab 2: Departures -->
                    <div class="tab-pane fade" id="departures" role="tabpanel">
                        <?php if (empty($departures)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-luggage-cart fs-1 text-muted opacity-50 mb-3 d-block"></i>
                                <h6 class="fw-bold text-dark">Tidak ada tamu yang dijadwalkan check-out hari ini.</h6>
                                <p class="text-muted small">Semua kepulangan sudah selesai diproses.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr class="text-uppercase text-muted" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                                            <th>Kode & Tamu</th>
                                            <th>Kamar & Tipe</th>
                                            <th>Periode Menginap</th>
                                            <th>Tagihan & Status</th>
                                            <th class="text-end pe-3">Aksi Front Desk</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($departures as $dep): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-primary small"><?php echo htmlspecialchars($dep['booking_code']); ?></div>
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($dep['full_name']); ?></div>
                                                <small class="text-muted"><i class="fas fa-phone-alt me-1 text-secondary"></i><?php echo htmlspecialchars($dep['customer_phone'] ?? '-'); ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark"><i class="fas fa-door-closed text-primary me-1"></i>Kamar <?php echo htmlspecialchars($dep['room_number'] ?? '-'); ?></div>
                                                <span class="badge bg-light text-secondary border"><?php echo htmlspecialchars($dep['type_name'] ?? 'Standar'); ?></span>
                                            </td>
                                            <td>
                                                <div class="small fw-semibold text-dark">Check-Out Hari Ini</div>
                                                <small class="text-muted"><?php echo date('d M Y', strtotime($dep['check_in'])); ?> - <?php echo date('d M Y', strtotime($dep['check_out'])); ?></small>
                                            </td>
                                            <td>
                                                <div class="small fw-bold text-dark">Rp <?php echo number_format($dep['final_amount'], 0, ',', '.'); ?></div>
                                                <?php if (($dep['payment_status'] ?? '') === 'paid'): ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1"><i class="fas fa-check-circle me-1"></i>Lunas</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1"><i class="fas fa-exclamation-circle me-1"></i>Perlu Pelunasan</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-3">
                                                <div class="btn-group">
                                                    <a href="bookings.php?update_status=checked_out&id=<?php echo $dep['id']; ?>&back=dashboard" 
                                                       class="btn btn-sm text-white rounded-pill px-3 fw-bold"
                                                       style="background-color: #7c3aed;"
                                                       onclick="return confirm('Konfirmasi proses Check-Out & penyelesaian kamar untuk tamu <?php echo addslashes($dep['full_name']); ?>?')">
                                                        <i class="fas fa-sign-out-alt me-1"></i> Check-Out
                                                    </a>
                                                    <a href="../user/receipt.php?id=<?php echo $dep['id']; ?>" target="_blank" class="btn btn-sm btn-outline-dark rounded-pill ms-1 px-2" title="Cetak Nota">
                                                        <i class="fas fa-print"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tab 3: In-House Guests -->
                    <div class="tab-pane fade" id="inhouse" role="tabpanel">
                        <?php if (empty($in_house)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-bed fs-1 text-muted opacity-50 mb-3 d-block"></i>
                                <h6 class="fw-bold text-dark">Saat ini tidak ada tamu yang sedang menginap.</h6>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr class="text-uppercase text-muted" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                                            <th>No. Kamar & Tipe</th>
                                            <th>Nama Tamu</th>
                                            <th>Telepon</th>
                                            <th>Durasi Menginap</th>
                                            <th>Check-Out Terjadwal</th>
                                            <th class="text-end pe-3">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($in_house as $inh): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-dark fs-6"><i class="fas fa-key text-warning me-1"></i>Kamar <?php echo htmlspecialchars($inh['room_number'] ?? '-'); ?></div>
                                                <span class="badge bg-light text-secondary border"><?php echo htmlspecialchars($inh['type_name'] ?? 'Standar'); ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($inh['full_name']); ?></div>
                                                <small class="text-primary"><?php echo htmlspecialchars($inh['booking_code']); ?></small>
                                            </td>
                                            <td>
                                                <span class="text-dark"><?php echo htmlspecialchars($inh['customer_phone'] ?? '-'); ?></span>
                                            </td>
                                            <td>
                                                <div class="small fw-semibold text-dark"><?php echo $inh['total_nights']; ?> Malam</div>
                                                <small class="text-muted">Masuk: <?php echo date('d/m/Y', strtotime($inh['check_in'])); ?></small>
                                            </td>
                                            <td>
                                                <div class="badge bg-light text-dark border"><i class="far fa-calendar-alt me-1 text-primary"></i><?php echo date('d/m/Y', strtotime($inh['check_out'])); ?></div>
                                            </td>
                                            <td class="text-end pe-3">
                                                <a href="bookings.php?action=view&id=<?php echo $inh['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                                    <i class="fas fa-file-alt me-1"></i> Detail
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

            <!-- Interactive Room Floor Plan & Status Rack (Denah Kamar Interaktif) -->
            <div class="card-custom p-4 mb-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <div>
                        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-th-large text-primary me-2"></i>Rak Status Kamar Interaktif (Room Status Rack)</h6>
                        <small class="text-muted">Pantau status seluruh kamar secara visual per lantai. Klik tombol untuk ubah status secara cepat.</small>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-2 mt-md-0 align-items-center">
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1"><i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i>Tersedia (<?php echo $room_status['available']; ?>)</span>
                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1"><i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i>Terisi (<?php echo $room_status['occupied']; ?>)</span>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1"><i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i>Cleaning (<?php echo $room_status['cleaning']; ?>)</span>
                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-1"><i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i>Repair (<?php echo $room_status['maintenance']; ?>)</span>
                    </div>
                </div>

                <!-- Rooms Grid Grouped by Floor -->
                <?php 
                $floors = [];
                foreach ($all_rooms as $rm) {
                    $fl = $rm['floor'] ?? 1;
                    $floors[$fl][] = $rm;
                }
                ksort($floors);
                ?>

                <?php foreach ($floors as $floor_num => $rooms_in_floor): ?>
                <div class="mb-4">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-dark rounded-pill px-3 py-1 fw-bold">Lantai <?php echo $floor_num; ?></span>
                        <span class="text-muted small"><?php echo count($rooms_in_floor); ?> Total Kamar</span>
                    </div>
                    <div class="row g-3">
                        <?php foreach ($rooms_in_floor as $rm_card): 
                            $r_status = $rm_card['status'];
                            $card_class = 'room-rack-' . $r_status;
                        ?>
                        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                            <div class="room-rack-card <?php echo $card_class; ?>">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <span class="fw-extrabold fs-5 text-dark" style="font-family: 'Outfit', sans-serif;">
                                        #<?php echo htmlspecialchars($rm_card['room_number']); ?>
                                    </span>
                                    <?php if ($r_status === 'available'): ?>
                                        <span class="badge bg-success" style="font-size: 0.65rem;">Available</span>
                                    <?php elseif ($r_status === 'occupied'): ?>
                                        <span class="badge bg-danger" style="font-size: 0.65rem;">Occupied</span>
                                    <?php elseif ($r_status === 'cleaning'): ?>
                                        <span class="badge bg-primary" style="font-size: 0.65rem;">Cleaning</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark" style="font-size: 0.65rem;">Repair</span>
                                    <?php endif; ?>
                                </div>
                                <div class="small fw-semibold text-secondary text-truncate" title="<?php echo htmlspecialchars($rm_card['type_name']); ?>">
                                    <?php echo htmlspecialchars($rm_card['type_name']); ?>
                                </div>
                                <div class="text-muted small" style="font-size: 0.72rem;">
                                    Rp <?php echo number_format($rm_card['base_price'], 0, ',', '.'); ?>/mlm
                                </div>

                                <!-- Quick 1-Click Action per Room State -->
                                <div class="mt-2 pt-2 border-top">
                                    <?php if ($r_status === 'cleaning'): ?>
                                        <a href="rooms.php?quick_status=available&id=<?php echo $rm_card['id']; ?>&back=dashboard" 
                                           class="btn btn-sm btn-success w-100 py-1 fw-bold" style="font-size: 0.72rem;">
                                            <i class="fas fa-check-circle me-1"></i>Set Siap
                                        </a>
                                    <?php elseif ($r_status === 'available'): ?>
                                        <a href="bookings.php?action=create" 
                                           class="btn btn-sm btn-outline-primary w-100 py-1 fw-bold" style="font-size: 0.72rem;">
                                            <i class="fas fa-plus me-1"></i>Book
                                        </a>
                                    <?php elseif ($r_status === 'occupied'): ?>
                                        <a href="bookings.php?filter_room=<?php echo $rm_card['id']; ?>" 
                                           class="btn btn-sm btn-outline-secondary w-100 py-1" style="font-size: 0.72rem;">
                                            <i class="fas fa-user me-1"></i>Lihat Tamu
                                        </a>
                                    <?php else: ?>
                                        <a href="rooms.php?quick_status=available&id=<?php echo $rm_card['id']; ?>&back=dashboard" 
                                           class="btn btn-sm btn-outline-warning w-100 py-1 text-dark" style="font-size: 0.72rem;">
                                            <i class="fas fa-wrench me-1"></i>Selesai
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Receptionist Quick Action Buttons -->
            <div class="card-custom p-4 mb-4">
                <h6 class="fw-bold text-dark mb-3"><i class="fas fa-bolt me-2 text-warning"></i>Akses Pintas Menu Front Desk Resepsionis</h6>
                <div class="row g-3">
                    <div class="col-md-2 col-4">
                        <a href="messages.php" class="quick-action-btn">
                            <i class="fas fa-paper-plane text-warning"></i>
                            <span class="small fw-bold">Pesan ke Owner</span>
                        </a>
                    </div>
                    <div class="col-md-2 col-4">
                        <a href="bookings.php?action=create" class="quick-action-btn">
                            <i class="fas fa-user-plus text-primary"></i>
                            <span class="small fw-bold">Walk-In Booking</span>
                        </a>
                    </div>
                    <div class="col-md-2 col-4">
                        <a href="rooms.php" class="quick-action-btn">
                            <i class="fas fa-door-open text-success"></i>
                            <span class="small fw-bold">Status Kamar</span>
                        </a>
                    </div>
                    <div class="col-md-2 col-4">
                        <a href="bookings.php" class="quick-action-btn">
                            <i class="fas fa-book text-info"></i>
                            <span class="small fw-bold">Daftar Reservasi</span>
                        </a>
                    </div>
                    <div class="col-md-2 col-4">
                        <a href="payments.php" class="quick-action-btn">
                            <i class="fas fa-cash-register text-warning"></i>
                            <span class="small fw-bold">Kasir & Bayar</span>
                        </a>
                    </div>
                    <div class="col-md-2 col-4">
                        <a href="services.php" class="quick-action-btn">
                            <i class="fas fa-concierge-bell text-danger"></i>
                            <span class="small fw-bold">Layanan Kamar</span>
                        </a>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <!-- ========================================== -->
            <!-- EXECUTIVE ADMINISTRATOR DASHBOARD VIEW -->
            <!-- ========================================== -->

            <!-- Hero Welcome Banner -->
            <div class="dashboard-hero d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <h2 class="fw-extrabold mb-1" style="font-family: 'Outfit', sans-serif;">Selamat Datang, Admin!</h2>
                    <p class="mb-0">Ringkasan real-time performa operasional, reservasi kamar, dan pendapatan hotel Anda.</p>
                </div>
                <div class="mt-3 mt-md-0 d-flex gap-2 align-items-center">
                    <div class="bg-white bg-opacity-10 px-3 py-2 rounded-pill text-white border border-white border-opacity-25 small">
                        <i class="far fa-calendar-alt text-warning me-1"></i> <?php echo date('l, d F Y'); ?>
                    </div>
                    <a href="bookings.php?action=create" class="btn btn-warning btn-md rounded-pill px-4 fw-bold shadow-sm">
                        <i class="fas fa-plus-circle me-1"></i> Booking Baru
                    </a>
                </div>
            </div>

            <!-- Top Metric Stat Cards -->
            <div class="row g-3 mb-4">
                <!-- Card 1: Total & Tersedia Kamar -->
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card-widget">
                        <div>
                            <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Status Kamar</span>
                            <h3 class="display-6 fw-extrabold mb-0 mt-1" style="font-family: 'Outfit', sans-serif; color: #0f172a;">
                                <?php echo $stats['available_rooms']; ?> <span class="fs-6 text-muted font-normal">/ <?php echo $stats['total_rooms']; ?></span>
                            </h3>
                            <span class="small text-success fw-semibold"><i class="fas fa-door-open me-1"></i><?php echo $stats['available_rooms']; ?> Kamar Tersedia</span>
                        </div>
                        <div class="stat-icon-wrapper" style="background: #e0e7ff; color: #4338ca;">
                            <i class="fas fa-bed"></i>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Occupancy Rate -->
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card-widget">
                        <div>
                            <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Tingkat Hunian (Occupancy)</span>
                            <h3 class="display-6 fw-extrabold mb-0 mt-1 text-primary" style="font-family: 'Outfit', sans-serif;">
                                <?php echo $occupancy_rate; ?>%
                            </h3>
                            <span class="small text-muted"><i class="fas fa-user-check text-info me-1"></i><?php echo $stats['occupied_rooms']; ?> Kamar Terisi Saat Ini</span>
                        </div>
                        <div class="stat-icon-wrapper" style="background: #e0f2fe; color: #0284c7;">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Total Booking & Pending -->
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card-widget">
                        <div>
                            <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Total Reservasi</span>
                            <h3 class="display-6 fw-extrabold mb-0 mt-1" style="font-family: 'Outfit', sans-serif; color: #0f172a;">
                                <?php echo $stats['total_bookings']; ?>
                            </h3>
                            <span class="small text-warning fw-bold"><i class="fas fa-clock me-1"></i><?php echo $stats['pending_bookings']; ?> Pending Konfirmasi</span>
                        </div>
                        <div class="stat-icon-wrapper" style="background: #fef3c7; color: #d97706;">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Pendapatan Bulan Ini -->
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card-widget">
                        <div>
                            <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Pendapatan Bulan Ini</span>
                            <h3 class="fs-4 fw-extrabold mb-0 mt-1 text-success" style="font-family: 'Outfit', sans-serif;">
                                Rp <?php echo number_format($stats['monthly_revenue'], 0, ',', '.'); ?>
                            </h3>
                            <span class="small text-muted"><i class="fas fa-wallet text-success me-1"></i>Total Rp <?php echo number_format($stats['total_revenue'], 0, ',', '.'); ?></span>
                        </div>
                        <div class="stat-icon-wrapper" style="background: #d1fae5; color: #059669;">
                            <i class="fas fa-coins"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Interactive Charts Section -->
            <div class="row g-4 mb-4">
                <!-- Main Chart: Monthly Revenue & Booking Trend -->
                <div class="col-lg-8">
                    <div class="card-custom p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-chart-area text-primary me-2"></i>Grafik Performa Pendapatan & Reservasi</h6>
                                <small class="text-muted">Tren 6 bulan terakhir berdasarkan data transaksi pembayaran dan booking.</small>
                            </div>
                            <span class="badge bg-light text-dark border"><i class="fas fa-sync-alt me-1 text-secondary"></i>Live DB Sync</span>
                        </div>
                        <div style="position: relative; min-height: 280px; width: 100%;">
                            <canvas id="revenueTrendChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Donut Chart: Room Status Distribution -->
                <div class="col-lg-4">
                    <div class="card-custom p-4 h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-pie-chart text-warning me-2"></i>Distribusi Status Kamar</h6>
                                <span class="badge bg-primary rounded-pill"><?php echo $stats['total_rooms']; ?> Kamar</span>
                            </div>
                            <div style="position: relative; height: 210px; width: 100%;" class="d-flex justify-content-center">
                                <canvas id="roomStatusChart"></canvas>
                            </div>
                        </div>
                        <div class="row text-center g-2 mt-3 pt-3 border-top">
                            <div class="col-6">
                                <div class="p-2 rounded bg-light">
                                    <small class="text-muted d-block">Tersedia</small>
                                    <span class="fw-bold text-success fs-6"><?php echo $room_status['available']; ?> Kamar</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 rounded bg-light">
                                    <small class="text-muted d-block">Terisi</small>
                                    <span class="fw-bold text-danger fs-6"><?php echo $room_status['occupied']; ?> Kamar</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity Summary & Recent 5 Bookings -->
            <div class="row g-4 mb-4">
                <!-- Left: Today's Activity & Booking Status Pie -->
                <div class="col-lg-4">
                    <!-- Today Activity Card -->
                    <div class="card-custom p-4 mb-4" style="background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); color: #ffffff;">
                        <h6 class="fw-bold mb-3"><i class="fas fa-calendar-day me-2 text-warning"></i>Aktivitas Hari Ini (<?php echo date('d M Y'); ?>)</h6>
                        <div class="row text-center g-2">
                            <div class="col-4">
                                <div class="p-2 rounded bg-white bg-opacity-10 backdrop-blur">
                                    <div class="fs-4 fw-extrabold text-white"><?php echo $today_stats['today_checkins'] ?? 0; ?></div>
                                    <small class="text-white-50 fw-semibold" style="font-size: 0.72rem;">Check-In</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 rounded bg-white bg-opacity-10 backdrop-blur">
                                    <div class="fs-4 fw-extrabold text-warning"><?php echo $today_stats['today_checkouts'] ?? 0; ?></div>
                                    <small class="text-white-50 fw-semibold" style="font-size: 0.72rem;">Check-Out</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 rounded bg-white bg-opacity-10 backdrop-blur">
                                    <div class="fs-4 fw-extrabold text-info"><?php echo $today_stats['today_bookings'] ?? 0; ?></div>
                                    <small class="text-white-50 fw-semibold" style="font-size: 0.72rem;">Booking Baru</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Booking Status Breakdown Chart Card -->
                    <div class="card-custom p-4">
                        <h6 class="fw-bold text-dark mb-3"><i class="fas fa-tasks text-info me-2"></i>Status Reservasi Overview</h6>
                        <div style="position: relative; height: 180px; width: 100%;">
                            <canvas id="bookingStatusChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Right: Recent Bookings Table (MAX 5 ITEMS) -->
                <div class="col-lg-8">
                    <div class="card-custom h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="m-0 fw-bold text-dark"><i class="fas fa-history me-2 text-primary"></i>5 Booking Terbaru Hari Ini</h6>
                                    <small class="text-muted">Daftar pemesanan terbaru yang masuk ke dalam sistem.</small>
                                </div>
                                <a href="bookings.php" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                                    Lihat Semua (<?php echo $stats['total_bookings']; ?>) <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr class="text-uppercase text-muted" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                                            <th class="ps-4">Kode & Tamu</th>
                                            <th>Kamar & Tipe</th>
                                            <th>Tanggal Stay</th>
                                            <th>Total Bayar</th>
                                            <th class="pe-4 text-end">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recent_bookings)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-5 text-muted">
                                                    <i class="fas fa-inbox fs-1 d-block mb-2 text-light"></i>
                                                    Belum ada data pemesanan terbaru di database.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recent_bookings as $booking): ?>
                                            <tr>
                                                <td class="ps-4 py-3">
                                                    <div class="d-flex align-items-center gap-3">
                                                        <div class="avatar-badge bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px; font-size: 0.85rem;">
                                                            <?php echo strtoupper(substr($booking['full_name'] ?? 'T', 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <a href="bookings.php?action=view&id=<?php echo $booking['id']; ?>" class="fw-bold text-primary text-decoration-none small d-block">
                                                                <?php echo htmlspecialchars($booking['booking_code']); ?>
                                                            </a>
                                                            <div class="fw-semibold text-dark small"><?php echo htmlspecialchars($booking['full_name'] ?? 'Tamu Tanpa Nama'); ?></div>
                                                            <small class="text-muted" style="font-size: 0.72rem;"><?php echo htmlspecialchars($booking['customer_phone'] ?? ''); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="fw-bold small text-dark"><i class="fas fa-door-closed text-secondary me-1"></i>Kamar <?php echo htmlspecialchars($booking['room_number'] ?? '-'); ?></div>
                                                    <span class="badge bg-light text-secondary border" style="font-size: 0.7rem;"><?php echo htmlspecialchars($booking['type_name'] ?? 'Standar'); ?></span>
                                                </td>
                                                <td>
                                                    <div class="small fw-semibold text-dark"><i class="far fa-calendar-alt me-1 text-primary"></i><?php echo date('d/m/Y', strtotime($booking['check_in'])); ?></div>
                                                    <small class="text-muted" style="font-size: 0.72rem;">s.d <?php echo date('d/m/Y', strtotime($booking['check_out'])); ?> (<?php echo $booking['total_nights']; ?> Malam)</small>
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-dark small">Rp <?php echo number_format($booking['final_amount'], 0, ',', '.'); ?></div>
                                                    <small class="text-muted" style="font-size: 0.7rem;"><?php echo ucfirst($booking['payment_method'] ?? 'cash'); ?></small>
                                                </td>
                                                <td class="pe-4 text-end">
                                                    <?php 
                                                    $st = $booking['status'];
                                                    $st_badge = [
                                                        'pending' => 'badge-pending',
                                                        'confirmed' => 'badge-confirmed',
                                                        'checked_in' => 'badge-checked_in',
                                                        'checked_out' => 'badge-checked_out',
                                                        'cancelled' => 'badge-cancelled'
                                                    ][$st] ?? 'badge-pending';
                                                    ?>
                                                    <span class="badge-status-pill <?php echo $st_badge; ?>">
                                                        <i class="fas fa-circle" style="font-size: 0.45rem;"></i>
                                                        <?php echo ucfirst(str_replace('_', ' ', $st)); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="p-3 bg-light text-center border-top rounded-bottom-4">
                            <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Menampilkan 5 reservasi terbaru secara otomatis dari sistem database.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Action Shortcut Cards for Admin -->
            <div class="card-custom p-4 mb-4">
                <h6 class="fw-bold text-dark mb-3"><i class="fas fa-bolt me-2 text-warning"></i>Akses Pintas & Navigasi Cepat Admin</h6>
                <div class="row g-3">
                    <div class="col-md-2 col-4">
                        <a href="bookings.php?action=create" class="quick-action-btn">
                            <i class="fas fa-plus-circle text-primary"></i>
                            <span class="small fw-bold">New Booking</span>
                        </a>
                    </div>
                    <div class="col-md-2 col-4">
                        <a href="rooms.php" class="quick-action-btn">
                            <i class="fas fa-bed text-success"></i>
                            <span class="small fw-bold">Kelola Kamar</span>
                        </a>
                    </div>
                    <div class="col-md-2 col-4">
                        <a href="customers.php" class="quick-action-btn">
                            <i class="fas fa-users text-warning"></i>
                            <span class="small fw-bold">Data Customer</span>
                        </a>
                    </div>
                    <div class="col-md-2 col-4">
                        <a href="payments.php" class="quick-action-btn">
                            <i class="fas fa-credit-card text-info"></i>
                            <span class="small fw-bold">Pembayaran</span>
                        </a>
                    </div>
                    <div class="col-md-2 col-4">
                        <a href="reports.php" class="quick-action-btn">
                            <i class="fas fa-chart-line text-purple" style="color: #8b5cf6;"></i>
                            <span class="small fw-bold">Laporan</span>
                        </a>
                    </div>
                    <div class="col-md-2 col-4">
                        <a href="settings.php" class="quick-action-btn">
                            <i class="fas fa-cog text-secondary"></i>
                            <span class="small fw-bold">Pengaturan</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Chart initialization script for Admin -->
            <script>
            document.addEventListener("DOMContentLoaded", function () {
                // Chart 1: Revenue & Booking Trend
                const ctxTrend = document.getElementById('revenueTrendChart').getContext('2d');
                new Chart(ctxTrend, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($monthly_labels); ?>,
                        datasets: [
                            {
                                type: 'line',
                                label: 'Pendapatan (Rp)',
                                data: <?php echo json_encode($monthly_revenue_data); ?>,
                                borderColor: '#10b981',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                yAxisID: 'yRevenue'
                            },
                            {
                                type: 'bar',
                                label: 'Jumlah Booking',
                                data: <?php echo json_encode($monthly_booking_data); ?>,
                                backgroundColor: 'rgba(79, 70, 229, 0.75)',
                                hoverBackgroundColor: '#4f46e5',
                                borderRadius: 8,
                                barThickness: 24,
                                yAxisID: 'yBooking'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top', labels: { font: { family: 'Plus Jakarta Sans', weight: '600' } } },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) { label += ': '; }
                                        if (context.dataset.yAxisID === 'yRevenue') {
                                            label += 'Rp ' + context.raw.toLocaleString('id-ID');
                                        } else {
                                            label += context.raw + ' Reservasi';
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { grid: { display: false } },
                            yRevenue: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                grid: { color: '#f1f5f9' },
                                ticks: {
                                    callback: function(value) {
                                        if (value >= 1000000) return 'Rp ' + (value/1000000).toFixed(1) + ' Jt';
                                        if (value >= 1000) return 'Rp ' + (value/1000).toFixed(0) + ' Rb';
                                        return 'Rp ' + value;
                                    }
                                }
                            },
                            yBooking: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                grid: { display: false },
                                ticks: { precision: 0 }
                            }
                        }
                    }
                });

                // Chart 2: Room Status Distribution
                const ctxRoom = document.getElementById('roomStatusChart').getContext('2d');
                new Chart(ctxRoom, {
                    type: 'doughnut',
                    data: {
                        labels: ['Available (Tersedia)', 'Occupied (Terisi)', 'Maintenance (Perbaikan)', 'Cleaning (Pembersihan)'],
                        datasets: [{
                            data: [
                                <?php echo $room_status['available']; ?>,
                                <?php echo $room_status['occupied']; ?>,
                                <?php echo $room_status['maintenance']; ?>,
                                <?php echo $room_status['cleaning']; ?>
                            ],
                            backgroundColor: ['#10b981', '#ef4444', '#f59e0b', '#3b82f6'],
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11, family: 'Plus Jakarta Sans' } } }
                        },
                        cutout: '70%'
                    }
                });

                // Chart 3: Booking Status Breakdown
                const ctxStatus = document.getElementById('bookingStatusChart').getContext('2d');
                new Chart(ctxStatus, {
                    type: 'bar',
                    data: {
                        labels: ['Pending', 'Confirmed', 'Check-In', 'Check-Out', 'Cancelled'],
                        datasets: [{
                            label: 'Jumlah Booking',
                            data: [
                                <?php echo $booking_status_counts['pending']; ?>,
                                <?php echo $booking_status_counts['confirmed']; ?>,
                                <?php echo $booking_status_counts['checked_in']; ?>,
                                <?php echo $booking_status_counts['checked_out']; ?>,
                                <?php echo $booking_status_counts['cancelled']; ?>
                            ],
                            backgroundColor: ['#f59e0b', '#0ea5e9', '#10b981', '#8b5cf6', '#ef4444'],
                            borderRadius: 6
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { ticks: { precision: 0 }, grid: { color: '#f1f5f9' } },
                            y: { grid: { display: false } }
                        }
                    }
                });
            });
            </script>
            <?php endif; ?>

        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>