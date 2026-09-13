<?php
require_once '../config/init.php';
checkUserAuth();

$page_title = "Portal Tamu - Grand Luxury Hotel";
include '../includes/header.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Tamu Terhormat';

// Recent bookings with complete information
$query_recent = "SELECT b.*, r.room_number, rt.type_name, rt.base_price, rt.image as room_image,
                        p.payment_status, p.payment_method
                 FROM bookings b
                 LEFT JOIN booking_details bd ON b.id = bd.booking_id
                 LEFT JOIN rooms r ON bd.room_id = r.id
                 LEFT JOIN room_types rt ON r.room_type_id = rt.id
                 LEFT JOIN payments p ON b.id = p.booking_id
                 WHERE b.user_id = :uid
                 GROUP BY b.id
                 ORDER BY b.created_at DESC
                 LIMIT 5";
$stmt_rec = $db->prepare($query_recent);
$stmt_rec->execute(['uid' => $user_id]);
$recent_bookings = $stmt_rec->fetchAll(PDO::FETCH_ASSOC);

// Upcoming or Active Stays
$query_upcoming = "SELECT b.*, r.room_number, rt.type_name, rt.base_price, rt.image as room_image,
                          p.payment_status, p.payment_method
                   FROM bookings b
                   LEFT JOIN booking_details bd ON b.id = bd.booking_id
                   LEFT JOIN rooms r ON bd.room_id = r.id
                   LEFT JOIN room_types rt ON r.room_type_id = rt.id
                   LEFT JOIN payments p ON b.id = p.booking_id
                   WHERE b.user_id = :uid
                   AND b.status IN ('confirmed', 'checked_in', 'pending')
                   GROUP BY b.id
                   ORDER BY b.check_in ASC
                   LIMIT 1";
$stmt_up = $db->prepare($query_upcoming);
$stmt_up->execute(['uid' => $user_id]);
$active_stay = $stmt_up->fetch(PDO::FETCH_ASSOC);
?>

<style>
    /* User Dashboard Styling */
    .user-hero-card {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #312e81 100%);
        border-radius: 20px;
        padding: 34px;
        border: 1px solid rgba(255, 255, 255, 0.15);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
        position: relative;
        overflow: hidden;
    }

    /* Force pure white text with high contrast for hero banner */
    .user-hero-card h1,
    .user-hero-card h2,
    .user-hero-card h3,
    .user-hero-card h4,
    .user-hero-card .hero-title {
        color: #ffffff !important;
        font-weight: 800 !important;
        letter-spacing: -0.02em;
    }

    .user-hero-card p,
    .user-hero-card .hero-subtitle {
        color: #e2e8f0 !important;
        font-size: 1rem;
        line-height: 1.6;
    }

    .card-luxury {
        background: #ffffff;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .card-luxury-header {
        padding: 20px 24px;
        background: #ffffff;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .stay-spotlight-card {
        background: #ffffff;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .stay-spotlight-card:hover {
        border-color: #4f46e5;
        box-shadow: 0 10px 25px rgba(79, 70, 229, 0.08);
    }

    .guest-action-card {
        background: #ffffff;
        border: 1.5px solid #e2e8f0;
        border-radius: 18px;
        padding: 24px 20px;
        text-decoration: none;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 16px;
        transition: all 0.25s ease;
        box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        height: 100%;
    }

    .guest-action-card:hover {
        background: #ffffff;
        border-color: #4f46e5;
        transform: translateY(-4px);
        box-shadow: 0 10px 22px rgba(79, 70, 229, 0.1);
        color: #4f46e5;
    }

    .guest-action-card .icon-circle {
        width: 54px;
        height: 54px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
        transition: transform 0.25s ease;
    }

    .guest-action-card:hover .icon-circle {
        transform: scale(1.1);
    }

    .badge-status-pill {
        font-size: 0.75rem;
        font-weight: 700;
        padding: 6px 14px;
        border-radius: 50rem;
        letter-spacing: 0.03em;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .badge-pending { background-color: #fef3c7; color: #d97706; }
    .badge-confirmed { background-color: #e0f2fe; color: #0284c7; }
    .badge-checked_in { background-color: #d1fae5; color: #059669; }
    .badge-checked_out { background-color: #f3e8ff; color: #7c3aed; }
    .badge-cancelled { background-color: #fee2e2; color: #dc2626; }
</style>

<div class="container py-4">
    
    <!-- Hero Welcome Banner with Sharp White Typography -->
    <div class="user-hero-card mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center position-relative" style="z-index: 2;">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-warning text-dark fw-bold px-3 py-1 rounded-pill" style="letter-spacing: 0.05em;">
                        <i class="fas fa-crown me-1"></i> GUEST EXPERIENCE
                    </span>
                    <span class="text-white-50 small">Grand Luxury Hotel & Resort</span>
                </div>
                <h2 class="hero-title fw-extrabold mb-1" style="font-family: 'Outfit', sans-serif; font-size: 2rem;">
                    Selamat Datang, <?php echo htmlspecialchars($user_name); ?>! 👋
                </h2>
                <p class="hero-subtitle mb-0" style="max-width: 640px;">
                    Kelola reservasi menginap Anda, nikmati layanan kamar eksklusif, dan cetak nota kuitansi resmi dengan mudah.
                </p>
            </div>
            <div class="mt-3 mt-md-0 d-flex gap-2 align-items-center flex-wrap">
                <a href="rooms.php" class="btn btn-outline-light btn-md rounded-pill px-4 fw-semibold">
                    <i class="fas fa-bed me-1"></i> Pilihan Kamar
                </a>
                <a href="booking.php" class="btn btn-warning btn-md rounded-pill px-4 fw-bold shadow-sm">
                    <i class="fas fa-calendar-plus me-1"></i> Pesan Kamar Baru
                </a>
            </div>
        </div>
    </div>

    <!-- Active / Next Stay Spotlight Card -->
    <?php if (!empty($active_stay)): ?>
    <div class="stay-spotlight-card mb-4">
        <div class="p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary text-white rounded-pill px-3 py-1 fw-bold">
                        <i class="fas fa-key me-1"></i> RESERVASI AKTIF SAYA
                    </span>
                    <span class="text-muted small">Kode Booking: <strong>#<?php echo htmlspecialchars($active_stay['booking_code']); ?></strong></span>
                </div>
                <div>
                    <?php 
                    $st = $active_stay['status'];
                    $st_badge = [
                        'pending' => 'badge-pending',
                        'confirmed' => 'badge-confirmed',
                        'checked_in' => 'badge-checked_in',
                        'checked_out' => 'badge-checked_out'
                    ][$st] ?? 'badge-pending';
                    ?>
                    <span class="badge-status-pill <?php echo $st_badge; ?>">
                        <i class="fas fa-circle" style="font-size: 0.45rem;"></i>
                        <?php echo strtoupper(str_replace('_', ' ', $st)); ?>
                    </span>
                </div>
            </div>

            <div class="row g-4 align-items-center">
                <div class="col-md-3">
                    <div class="rounded-4 overflow-hidden shadow-sm" style="height: 140px; background: #0f172a;">
                        <img src="<?php echo htmlspecialchars(getRoomImageUrl($active_stay['room_image'] ?? '')); ?>" alt="Kamar" style="width: 100%; height: 100%; object-fit: cover;">
                    </div>
                </div>
                <div class="col-md-5">
                    <h4 class="fw-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">
                        <?php echo htmlspecialchars($active_stay['type_name'] ?? 'Kamar Standar'); ?> &bull; Kamar #<?php echo htmlspecialchars($active_stay['room_number'] ?? '-'); ?>
                    </h4>
                    <div class="row g-2 mt-1">
                        <div class="col-6">
                            <span class="text-muted d-block small" style="font-size: 0.75rem;">CHECK-IN</span>
                            <strong class="text-success small"><i class="far fa-calendar-check me-1"></i><?php echo date('d M Y', strtotime($active_stay['check_in'])); ?></strong>
                            <div class="text-muted" style="font-size: 0.7rem;">Pukul <?php echo CHECK_IN_TIME; ?> WIB</div>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block small" style="font-size: 0.75rem;">CHECK-OUT</span>
                            <strong class="text-danger small"><i class="far fa-calendar-times me-1"></i><?php echo date('d M Y', strtotime($active_stay['check_out'])); ?></strong>
                            <div class="text-muted" style="font-size: 0.7rem;">Pukul <?php echo CHECK_OUT_TIME; ?> WIB</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="mb-3">
                        <span class="text-muted small d-block">Total Biaya:</span>
                        <h4 class="fw-bold text-primary mb-0" style="font-family: 'Outfit', sans-serif;">
                            Rp <?php echo number_format($active_stay['final_amount'], 0, ',', '.'); ?>
                        </h4>
                    </div>
                    <div class="d-flex gap-2 justify-content-md-end flex-wrap">
                        <a href="booking_detail.php?id=<?php echo $active_stay['id']; ?>" class="btn btn-primary btn-sm rounded-pill px-3 fw-semibold">
                            <i class="fas fa-ticket-alt me-1"></i> Detail & E-Tiket
                        </a>
                        <a href="receipt.php?booking_id=<?php echo $active_stay['id']; ?>" target="_blank" class="btn btn-outline-dark btn-sm rounded-pill px-3 fw-semibold">
                            <i class="fas fa-print me-1"></i> Cetak Nota
                        </a>
                    </div>
                </div>
            </div>

            <!-- In-stay Perks & Hotel Info Bar -->
            <div class="row g-2 mt-3 pt-3 border-top text-muted small">
                <div class="col-md-4 d-flex align-items-center gap-2">
                    <i class="fas fa-wifi text-primary fs-5"></i>
                    <div>
                        <strong class="text-dark d-block" style="font-size: 0.78rem;">WiFi Hotel: GrandLuxury-Guest</strong>
                        <span style="font-size: 0.72rem;">Password: <code>GrandLuxury2024</code></span>
                    </div>
                </div>
                <div class="col-md-4 d-flex align-items-center gap-2">
                    <i class="fas fa-utensils text-warning fs-5"></i>
                    <div>
                        <strong class="text-dark d-block" style="font-size: 0.78rem;">Jam Sarapan / Restoran</strong>
                        <span style="font-size: 0.72rem;">06:00 - 10:00 WIB di Lantai 2</span>
                    </div>
                </div>
                <div class="col-md-4 d-flex align-items-center gap-2">
                    <i class="fas fa-headset text-danger fs-5"></i>
                    <div>
                        <strong class="text-dark d-block" style="font-size: 0.78rem;">Layanan Resepsionis 24 Jam</strong>
                        <span style="font-size: 0.72rem;">Tekan 0 di telepon kamar atau chat WA</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- User-Friendly Menu Grid (5 Action Cards) -->
    <div class="mb-4">
        <h5 class="fw-bold text-dark mb-3" style="font-family: 'Outfit', sans-serif;">
            <i class="fas fa-compass text-primary me-2"></i>Layanan Tamu
        </h5>
        <div class="row g-3">
            <!-- 1. Pesan Kamar Baru -->
            <div class="col-lg-4 col-md-6">
                <a href="booking.php" class="guest-action-card">
                    <div class="icon-circle" style="background: #e0e7ff; color: #4338ca;">
                        <i class="fas fa-calendar-plus"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Pesan Kamar Baru</h6>
                        <small class="text-muted">Pilih tanggal dan reservasi kamar hotel secara online.</small>
                    </div>
                </a>
            </div>

            <!-- 2. Katalog Kamar & Suite -->
            <div class="col-lg-4 col-md-6">
                <a href="rooms.php" class="guest-action-card">
                    <div class="icon-circle" style="background: #d1fae5; color: #059669;">
                        <i class="fas fa-bed"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Pilihan Kamar & Suite</h6>
                        <small class="text-muted">Lihat foto kamar, fasilitas, kapasitas, dan tarif menginap.</small>
                    </div>
                </a>
            </div>

            <!-- 3. Riwayat & Nota Kuitansi -->
            <div class="col-lg-4 col-md-6">
                <a href="history.php" class="guest-action-card">
                    <div class="icon-circle" style="background: #fef3c7; color: #d97706;">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Riwayat & Nota Resmi</h6>
                        <small class="text-muted">Cek status reservasi & download bukti nota pembayaran.</small>
                    </div>
                </a>
            </div>

            <!-- 4. Profil Saya -->
            <div class="col-lg-6 col-md-6">
                <a href="profile.php" class="guest-action-card">
                    <div class="icon-circle" style="background: #e0f2fe; color: #0284c7;">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Profil & Keamanan Akun</h6>
                        <small class="text-muted">Perbarui data identitas tamu dan ganti password akun Anda.</small>
                    </div>
                </a>
            </div>

            <!-- 5. Front Desk & Room Service -->
            <div class="col-lg-6 col-md-12">
                <a href="#frontDeskModal" data-bs-toggle="modal" class="guest-action-card">
                    <div class="icon-circle" style="background: #fee2e2; color: #dc2626;">
                        <i class="fas fa-concierge-bell"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Bantuan Resepsionis 24 Jam</h6>
                        <small class="text-muted">Hubungi staf via WhatsApp, telepon, atau minta layanan kamar.</small>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Recent Bookings List (Simple Ticket Card View) -->
    <div class="card-luxury mb-4">
        <div class="card-luxury-header">
            <div>
                <h5 class="fw-bold mb-0 text-dark" style="font-family: 'Outfit', sans-serif;">
                    <i class="fas fa-history text-primary me-2"></i>Daftar Pemesanan Kamar Saya
                </h5>
                <small class="text-muted">Histori reservasi yang pernah Anda buat di Grand Luxury Hotel</small>
            </div>
            <a href="history.php" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold">
                Lihat Semua <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="p-3">
            <?php if (empty($recent_bookings)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-calendar-plus fa-3x mb-3 text-muted opacity-50"></i>
                    <h5 class="fw-bold text-dark">Belum Ada Pemesanan Kamar</h5>
                    <p class="small text-muted mb-3">Rencanakan liburan mewah Anda sekarang bersama Grand Luxury Hotel.</p>
                    <a href="booking.php" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                        <i class="fas fa-calendar-check me-1"></i> Pesan Kamar Sekarang
                    </a>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($recent_bookings as $b): 
                        $st = $b['status'];
                        $st_badge = [
                            'pending' => 'badge-pending',
                            'confirmed' => 'badge-confirmed',
                            'checked_in' => 'badge-checked_in',
                            'checked_out' => 'badge-checked_out',
                            'cancelled' => 'badge-cancelled'
                        ][$st] ?? 'badge-pending';
                    ?>
                    <div class="col-12">
                        <div class="p-3 bg-light rounded-4 border d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-3 overflow-hidden d-none d-sm-block shadow-sm" style="width: 70px; height: 70px; background: #0f172a; flex-shrink: 0;">
                                    <img src="<?php echo htmlspecialchars(getRoomImageUrl($b['room_image'] ?? '')); ?>" alt="Room" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <h6 class="fw-bold text-dark mb-0">
                                            <?php echo htmlspecialchars($b['type_name'] ?? 'Kamar Hotel'); ?> (Kamar #<?php echo htmlspecialchars($b['room_number'] ?? '-'); ?>)
                                        </h6>
                                        <span class="badge-status-pill <?php echo $st_badge; ?>" style="font-size: 0.7rem; padding: 3px 10px;">
                                            <?php echo strtoupper(str_replace('_', ' ', $st)); ?>
                                        </span>
                                    </div>
                                    <div class="text-muted small">
                                        <i class="far fa-calendar-alt text-primary me-1"></i> <?php echo date('d M Y', strtotime($b['check_in'])); ?> s.d <?php echo date('d M Y', strtotime($b['check_out'])); ?>
                                        &bull; <span class="fw-semibold text-dark"><?php echo $b['total_nights']; ?> Malam</span>
                                    </div>
                                    <div class="text-muted small" style="font-size: 0.72rem;">
                                        Kode: <strong class="text-primary">#<?php echo htmlspecialchars($b['booking_code']); ?></strong>
                                    </div>
                                </div>
                            </div>

                            <div class="text-end d-flex align-items-center gap-3 flex-wrap">
                                <div>
                                    <span class="text-muted small d-block" style="font-size: 0.72rem;">Total Biaya:</span>
                                    <strong class="text-dark fs-6">Rp <?php echo number_format($b['final_amount'], 0, ',', '.'); ?></strong>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="booking_detail.php?id=<?php echo $b['id']; ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold">
                                        Detail
                                    </a>
                                    <a href="receipt.php?booking_id=<?php echo $b['id']; ?>" target="_blank" class="btn btn-sm btn-outline-dark rounded-pill px-3 fw-semibold" title="Cetak Nota">
                                        <i class="fas fa-print me-1"></i> Nota
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Interactive Front Desk & Concierge Modal -->
<div class="modal fade" id="frontDeskModal" tabindex="-1" aria-labelledby="frontDeskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
            <!-- Modal Header with Luxury Gradient -->
            <div class="modal-header border-0 p-4 text-white" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #312e81 100%);">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-warning text-dark fw-bold px-3 py-1 rounded-pill">
                            <i class="fas fa-concierge-bell me-1"></i> FRONT DESK 24/7
                        </span>
                        <span class="text-white-50 small">Grand Luxury Concierge</span>
                    </div>
                    <h4 class="modal-title fw-bold text-white mb-0" id="frontDeskModalLabel" style="font-family: 'Outfit', sans-serif;">
                        Pusat Bantuan & Layanan Resepsionis
                    </h4>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4 bg-light">
                <div class="row g-3 mb-4">
                    <!-- WhatsApp Direct -->
                    <div class="col-md-6">
                        <div class="p-3 bg-white rounded-3 border h-100 d-flex flex-column justify-content-between shadow-sm">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="rounded-3 p-3 bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="fab fa-whatsapp fs-3"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">WhatsApp Resepsionis</h6>
                                    <small class="text-muted">Respon cepat via pesan teks / chat</small>
                                </div>
                            </div>
                            <a href="https://wa.me/6281234567890?text=Halo%20Front%20Desk%20Grand%20Luxury%20Hotel%2C%20saya%20<?php echo urlencode($user_name); ?>%20ingin%20menanyakan%20layanan%20kamar..." 
                               target="_blank" class="btn btn-success rounded-pill w-100 fw-bold shadow-sm">
                                <i class="fab fa-whatsapp me-1"></i> Chat WhatsApp Sekarang
                            </a>
                        </div>
                    </div>

                    <!-- Phone Call Direct -->
                    <div class="col-md-6">
                        <div class="p-3 bg-white rounded-3 border h-100 d-flex flex-column justify-content-between shadow-sm">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="rounded-3 p-3 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="fas fa-phone-alt fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">Hotline Telepon Langsung</h6>
                                    <small class="text-muted"><?php echo htmlspecialchars(HOTEL_PHONE); ?> (Bebas Pulsa Kamar)</small>
                                </div>
                            </div>
                            <a href="tel:<?php echo htmlspecialchars(HOTEL_PHONE); ?>" class="btn btn-primary rounded-pill w-100 fw-bold shadow-sm">
                                <i class="fas fa-phone-alt me-1"></i> Hubungi Front Desk
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Quick Concierge Request Form -->
                <div class="bg-white p-4 rounded-3 border shadow-sm">
                    <h6 class="fw-bold text-dark mb-1" style="font-family: 'Outfit', sans-serif;">
                        <i class="fas fa-paper-plane text-warning me-2"></i>Kirim Permintaan Layanan Kamar
                    </h6>
                    <small class="text-muted d-block mb-3">Petugas front desk kami akan memproses kebutuhan Anda secara langsung ke staf terkait.</small>

                    <form id="frontDeskQuickForm" onsubmit="handleFrontDeskSubmit(event)">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark">Jenis Kebutuhan / Layanan</label>
                                <select class="form-select form-control-luxury" id="serviceType" required>
                                    <option value="Room Service & Makanan">🍽️ Room Service / Makanan & Minuman</option>
                                    <option value="Housekeeping / Pembersihan Kamar">🧹 Housekeeping / Pembersihan Kamar</option>
                                    <option value="Extra Pillow & Amenities">🛏️ Extra Pillow, Handuk & Amenities</option>
                                    <option value="Permintaan Late Check-out">⏰ Konfirmasi Late Check-out</option>
                                    <option value="Shuttle & Transportasi">🚕 Shuttle / Transportasi Bandara</option>
                                    <option value="Lainnya">💬 Pertanyaan / Bantuan Lainnya</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark">Nomor Kamar (Jika Sudah Check-in)</label>
                                <input type="text" class="form-control form-control-luxury" id="roomNumberInput" placeholder="Contoh: Kamar 204 atau 'Belum Check-in'">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark">Rincian Permintaan / Catatan Tambahan</label>
                            <textarea class="form-control form-control-luxury" id="serviceNotes" rows="2" placeholder="Tuliskan detail permintaan Anda..." required></textarea>
                        </div>

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <small class="text-muted"><i class="fas fa-clock text-primary me-1"></i>Layanan aktif 24 jam &bull; Lobby Utama Lantai 1</small>
                            <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold shadow-sm" id="btnSubmitRequest">
                                <i class="fas fa-paper-plane me-1"></i> Kirim Permintaan
                            </button>
                        </div>
                    </form>

                    <!-- Alert message on submit -->
                    <div id="frontDeskSuccessMsg" class="alert alert-success border-0 rounded-3 mt-3 d-none align-items-center gap-2">
                        <i class="fas fa-check-circle fs-5 text-success"></i>
                        <div class="flex-grow-1 small fw-semibold text-dark">
                            Permintaan Anda telah berhasil diteruskan ke Staf Front Desk & Resepsionis! Kami akan segera menindaklanjuti.
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-white border-0 px-4 py-3">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
function handleFrontDeskSubmit(e) {
    e.preventDefault();
    var btn = document.getElementById('btnSubmitRequest');
    var successMsg = document.getElementById('frontDeskSuccessMsg');
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Mengirim...';
    
    setTimeout(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check me-1"></i> Terkirim';
        successMsg.classList.remove('d-none');
        successMsg.classList.add('d-flex');
        document.getElementById('serviceNotes').value = '';
    }, 800);
}
</script>

<?php include '../includes/footer.php'; ?>