<?php
require_once '../config/init.php';
checkUserAuth();

$page_title = "Profil Tamu - Grand Luxury Hotel";
include '../includes/header.php';

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get user data
$user = $database->getSingle("SELECT * FROM users WHERE id = ?", [$user_id]);

// Process profile update
if ($_POST && isset($_POST['update_profile'])) {
    $data = [
        'full_name' => sanitizeInput($_POST['full_name']),
        'email' => sanitizeInput($_POST['email']),
        'phone' => sanitizeInput($_POST['phone']),
        'address' => sanitizeInput($_POST['address'])
    ];

    // Check if email is already used by other users
    $existing = $database->getSingle("SELECT id FROM users WHERE email = ? AND id != ?", [$data['email'], $user_id]);
    if ($existing) {
        $error = "Email sudah digunakan oleh user lain";
    } else {
        $result = $database->update('users', $data, "id = $user_id");
        if ($result) {
            // Update session
            $_SESSION['full_name'] = $data['full_name'];
            $_SESSION['email'] = $data['email'];
            $success = "Profil berhasil diperbarui";
            $user = $database->getSingle("SELECT * FROM users WHERE id = ?", [$user_id]); // Refresh user data
        } else {
            $error = "Gagal memperbarui profil";
        }
    }
}

// Process password change
if ($_POST && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $error = "Password baru dan konfirmasi password tidak cocok";
    } elseif (strlen($new_password) < 6) {
        $error = "Password minimal 6 karakter";
    } else {
        // Verify current password
        if (password_verify($current_password, $user['password'])) {
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $result = $database->update('users', ['password' => $new_password_hash], "id = $user_id");
            if ($result) {
                $success = "Password berhasil diubah dengan aman";
            } else {
                $error = "Gagal mengubah password";
            }
        } else {
            $error = "Password saat ini tidak sesuai";
        }
    }
}

// Get user statistics
$user_stats = $database->getSingle("
    SELECT 
        COUNT(*) as total_bookings,
        SUM(CASE WHEN status = 'checked_out' THEN 1 ELSE 0 END) as completed_stays,
        COALESCE(SUM(final_amount), 0) as total_spent
    FROM bookings 
    WHERE user_id = ?
", [$user_id]);
?>

<style>
    .profile-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #312e81 100%);
        border-radius: 20px;
        padding: 30px;
        color: #fff;
        margin-bottom: 24px;
        border: 1px solid rgba(255, 255, 255, 0.15);
        box-shadow: 0 10px 30px rgba(0,0,0,0.06);
    }
    .profile-hero h1, .profile-hero h2, .profile-hero h3, .profile-hero h4 {
        color: #ffffff !important;
        font-weight: 800 !important;
    }
    .profile-hero p {
        color: #e2e8f0 !important;
    }
    .profile-card {
        background: #ffffff;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        overflow: hidden;
        transition: all 0.3s ease;
    }
    .profile-card:hover {
        box-shadow: 0 8px 24px rgba(0,0,0,0.06);
    }
    .profile-card-header {
        padding: 20px 24px;
        background: #ffffff;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .avatar-circle-lg {
        width: 88px;
        height: 88px;
        border-radius: 24px;
        background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        color: #ffffff;
        font-size: 2.2rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
        margin: 0 auto 16px auto;
    }
    .stat-chip {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 14px 16px;
        text-align: center;
        transition: all 0.2s ease;
    }
    .stat-chip:hover {
        background: #ffffff;
        border-color: #cbd5e1;
        transform: translateY(-2px);
    }
    .form-control-luxury {
        border-radius: 12px;
        border: 1.5px solid #e2e8f0;
        padding: 11px 16px;
        font-size: 0.95rem;
        transition: all 0.2s ease;
    }
    .form-control-luxury:focus {
        border-color: #4f46e5;
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12);
    }
    .form-control-luxury:read-only {
        background-color: #f8fafc;
        color: #64748b;
        cursor: not-allowed;
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
    
    <!-- Profile Hero Header -->
    <div class="profile-hero">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-warning text-dark fw-bold px-3 py-1 rounded-pill">
                        <i class="fas fa-id-card me-1"></i> AKUN SAYA
                    </span>
                    <span class="text-white-50 small">Member Sejak <?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                </div>
                <h2 class="fw-extrabold mb-1" style="font-family: 'Outfit', sans-serif; font-size: 1.85rem;">
                    Profil & Keamanan Akun
                </h2>
                <p class="mb-0 text-white-50">
                    Kelola informasi pribadi, kontak reservasi, dan tingkatkan keamanan akun Anda di Grand Luxury Hotel.
                </p>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-light rounded-pill px-4 fw-semibold">
                    <i class="fas fa-arrow-left me-1"></i> Kembali ke Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4 p-3 d-flex align-items-center gap-2" role="alert">
        <i class="fas fa-check-circle fs-5 text-success"></i>
        <div class="flex-grow-1 fw-semibold text-dark"><?php echo $success; ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4 p-3 d-flex align-items-center gap-2" role="alert">
        <i class="fas fa-exclamation-triangle fs-5 text-danger"></i>
        <div class="flex-grow-1 fw-semibold text-dark"><?php echo $error; ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left Sidebar: Guest Identity & Summary -->
        <div class="col-lg-4">
            
            <!-- Guest Profile Card -->
            <div class="profile-card mb-4">
                <div class="p-4 text-center">
                    <div class="avatar-circle-lg">
                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                    </div>
                    <h4 class="fw-bold mb-1" style="font-family: 'Outfit', sans-serif; color: #0f172a;">
                        <?php echo htmlspecialchars($user['full_name']); ?>
                    </h4>
                    <span class="badge bg-indigo text-white px-3 py-1 rounded-pill mb-2 fw-semibold" style="background: #4f46e5;">
                        <i class="fas fa-user-check me-1"></i> Tamu Terdaftar
                    </span>
                    <p class="text-muted small mb-0"><i class="fas fa-envelope me-1 text-primary"></i><?php echo htmlspecialchars($user['email']); ?></p>
                </div>

                <div class="p-4 pt-0">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="stat-chip">
                                <div class="fw-extrabold fs-4 text-primary" style="font-family: 'Outfit', sans-serif;">
                                    <?php echo $user_stats['total_bookings'] ?? 0; ?>
                                </div>
                                <span class="text-muted small fw-semibold" style="font-size: 0.75rem;">Total Reservasi</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-chip">
                                <div class="fw-extrabold fs-4 text-success" style="font-family: 'Outfit', sans-serif;">
                                    <?php echo $user_stats['completed_stays'] ?? 0; ?>
                                </div>
                                <span class="text-muted small fw-semibold" style="font-size: 0.75rem;">Stay Selesai</span>
                            </div>
                        </div>
                    </div>

                    <div class="stat-chip mb-3 bg-light">
                        <span class="text-muted small fw-semibold d-block mb-1" style="font-size: 0.75rem;">TOTAL PENGELUARAN MENGINAP</span>
                        <div class="fw-extrabold fs-5 text-warning" style="font-family: 'Outfit', sans-serif; color: #d97706 !important;">
                            <?php echo formatCurrency($user_stats['total_spent'] ?? 0); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Access Nav -->
            <div class="profile-card">
                <div class="profile-card-header">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-compass me-2 text-primary"></i>Navigasi Cepat</h6>
                </div>
                <div class="p-2">
                    <a href="index.php" class="d-flex align-items-center justify-content-between p-3 rounded-3 text-decoration-none text-dark hover-bg-light mb-1">
                        <span class="fw-semibold"><i class="fas fa-th-large me-2 text-primary"></i>Dashboard Tamu</span>
                        <i class="fas fa-chevron-right text-muted small"></i>
                    </a>
                    <a href="booking.php" class="d-flex align-items-center justify-content-between p-3 rounded-3 text-decoration-none text-dark hover-bg-light mb-1">
                        <span class="fw-semibold"><i class="fas fa-calendar-plus me-2 text-success"></i>Pesan Kamar Baru</span>
                        <i class="fas fa-chevron-right text-muted small"></i>
                    </a>
                    <a href="history.php" class="d-flex align-items-center justify-content-between p-3 rounded-3 text-decoration-none text-dark hover-bg-light mb-1">
                        <span class="fw-semibold"><i class="fas fa-history me-2 text-info"></i>Riwayat Reservasi</span>
                        <i class="fas fa-chevron-right text-muted small"></i>
                    </a>
                    <a href="rooms.php" class="d-flex align-items-center justify-content-between p-3 rounded-3 text-decoration-none text-dark hover-bg-light mb-1">
                        <span class="fw-semibold"><i class="fas fa-bed me-2 text-purple"></i>Daftar Tipe Kamar</span>
                        <i class="fas fa-chevron-right text-muted small"></i>
                    </a>
                    <hr class="my-2 border-light">
                    <a href="../logout.php" class="d-flex align-items-center justify-content-between p-3 rounded-3 text-decoration-none text-danger hover-bg-light">
                        <span class="fw-bold"><i class="fas fa-sign-out-alt me-2"></i>Keluar (Logout)</span>
                        <i class="fas fa-arrow-right small"></i>
                    </a>
                </div>
            </div>

        </div>

        <!-- Right Content: Profile Form, Security & Recent Activity -->
        <div class="col-lg-8">
            
            <!-- Update Profile Form -->
            <div class="profile-card mb-4">
                <div class="profile-card-header">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark" style="font-family: 'Outfit', sans-serif;">
                            <i class="fas fa-user-edit me-2 text-primary"></i>Informasi Data Tamu
                        </h5>
                        <small class="text-muted">Perbarui data profil untuk kemudahan proses check-in di hotel</small>
                    </div>
                </div>
                <div class="p-4">
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark small">Username Akun</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="fas fa-at text-muted"></i></span>
                                    <input type="text" class="form-control form-control-luxury rounded-end-3" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                                </div>
                                <div class="form-text text-muted small"><i class="fas fa-info-circle me-1"></i>Username akun bersifat permanen</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark small">Peran / Hak Akses</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="fas fa-shield-alt text-muted"></i></span>
                                    <input type="text" class="form-control form-control-luxury rounded-end-3 text-capitalize fw-bold text-primary" value="<?php echo htmlspecialchars($user['role']); ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark small">Nama Lengkap <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0 rounded-start-3"><i class="fas fa-user text-muted"></i></span>
                                    <input type="text" class="form-control form-control-luxury rounded-end-3" name="full_name" 
                                           value="<?php echo htmlspecialchars($user['full_name']); ?>" required placeholder="Nama lengkap sesuai KTP/Paspor">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark small">Alamat Email <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0 rounded-start-3"><i class="fas fa-envelope text-muted"></i></span>
                                    <input type="email" class="form-control form-control-luxury rounded-end-3" name="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required placeholder="nama@email.com">
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark small">Nomor Telepon / WhatsApp</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0 rounded-start-3"><i class="fas fa-phone text-muted"></i></span>
                                    <input type="text" class="form-control form-control-luxury rounded-end-3" name="phone" 
                                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="Contoh: 08123456789">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark small">Tanggal Bergabung</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="fas fa-calendar-alt text-muted"></i></span>
                                    <input type="text" class="form-control form-control-luxury rounded-end-3" 
                                           value="<?php echo date('d F Y, H:i', strtotime($user['created_at'])); ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold text-dark small">Alamat Domisili</label>
                            <textarea class="form-control form-control-luxury" name="address" rows="3" placeholder="Alamat lengkap kota domisili..."><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm">
                                <i class="fas fa-save me-1"></i> Simpan Perubahan Profil
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Password Security Form -->
            <div class="profile-card mb-4">
                <div class="profile-card-header">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark" style="font-family: 'Outfit', sans-serif;">
                            <i class="fas fa-lock me-2 text-warning"></i>Keamanan & Ubah Password
                        </h5>
                        <small class="text-muted">Gunakan password yang kuat dengan kombinasi huruf, angka, dan simbol</small>
                    </div>
                </div>
                <div class="p-4">
                    <form method="POST">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark small">Password Saat Ini <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 rounded-start-3"><i class="fas fa-key text-muted"></i></span>
                                <input type="password" class="form-control form-control-luxury rounded-end-3" name="current_password" required placeholder="Masukkan password saat ini">
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark small">Password Baru <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0 rounded-start-3"><i class="fas fa-shield-alt text-muted"></i></span>
                                    <input type="password" class="form-control form-control-luxury rounded-end-3" name="new_password" required minlength="6" placeholder="Minimal 6 karakter">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark small">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0 rounded-start-3"><i class="fas fa-check-double text-muted"></i></span>
                                    <input type="password" class="form-control form-control-luxury rounded-end-3" name="confirm_password" required minlength="6" placeholder="Ulangi password baru">
                                </div>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-warning rounded-pill px-4 py-2 fw-bold shadow-sm">
                                <i class="fas fa-shield-alt me-1"></i> Perbarui Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Recent Activity Timeline -->
            <div class="profile-card">
                <div class="profile-card-header">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark" style="font-family: 'Outfit', sans-serif;">
                            <i class="fas fa-clock me-2 text-info"></i>Aktivitas Reservasi Terbaru
                        </h5>
                        <small class="text-muted">Riwayat pemesanan terakhir Anda</small>
                    </div>
                    <a href="history.php" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold">
                        Lihat Semua <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="p-0">
                    <?php
                    $recent_bookings = $database->getAll("
                        SELECT b.id, b.booking_code, b.status, b.created_at, b.final_amount, r.room_number, rt.type_name
                        FROM bookings b
                        JOIN booking_details bd ON b.id = bd.booking_id
                        JOIN rooms r ON bd.room_id = r.id
                        JOIN room_types rt ON r.room_type_id = rt.id
                        WHERE b.user_id = ?
                        ORDER BY b.created_at DESC
                        LIMIT 4
                    ", [$user_id]);
                    ?>

                    <?php if (!empty($recent_bookings)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_bookings as $booking): ?>
                        <div class="list-group-item p-3 px-4 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-3 p-2 bg-light text-primary d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                    <i class="fas fa-receipt fs-5"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark">
                                        Booking #<?php echo htmlspecialchars($booking['booking_code']); ?>
                                    </h6>
                                    <div class="text-muted small">
                                        <?php echo htmlspecialchars($booking['type_name']); ?> (Kamar <?php echo htmlspecialchars($booking['room_number']); ?>) &bull; 
                                        <span class="text-primary fw-semibold"><?php echo formatCurrency($booking['final_amount']); ?></span>
                                    </div>
                                    <div class="text-muted small" style="font-size: 0.75rem;">
                                        <i class="fas fa-calendar-alt me-1"></i><?php echo date('d M Y, H:i', strtotime($booking['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="badge-status-pill badge-<?php echo $booking['status']; ?> mb-2 d-inline-block">
                                    <?php echo strtoupper($booking['status']); ?>
                                </span>
                                <div>
                                    <a href="booking_detail.php?id=<?php echo $booking['id']; ?>" class="btn btn-xs btn-outline-secondary rounded-pill px-2 py-1 text-decoration-none small" style="font-size: 0.75rem;">
                                        Detail <i class="fas fa-chevron-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-calendar-times fa-3x mb-3 text-muted opacity-50"></i>
                        <p class="mb-2 fw-semibold">Belum Ada Riwayat Reservasi</p>
                        <a href="booking.php" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold">
                            Pesan Kamar Sekarang
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>