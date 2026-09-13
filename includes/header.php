<?php
require_once __DIR__ . '/../config/init.php';

$flash_message = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . HOTEL_NAME : HOTEL_NAME; ?></title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
    <!-- Datepicker CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container-fluid px-lg-4">
            <a class="navbar-brand d-flex align-items-center" href="<?php echo (isLoggedIn() && !isAdmin() && !isOwner() && !isReceptionist()) ? 'index.php' : '../index.php'; ?>">
                <i class="fas fa-hotel me-2 fs-3 text-warning"></i>
                <span class="fw-bold fs-5"><?php echo HOTEL_NAME; ?></span>
            </a>
            
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#headerNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="headerNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
                    <?php if (isLoggedIn() && !isAdmin() && !isOwner() && !isReceptionist()): ?>
                        <li class="nav-item">
                            <a class="nav-link text-white d-flex align-items-center gap-1" href="index.php">
                                <i class="fas fa-th-large text-warning me-1"></i> 
                                <span>Dashboard Saya</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white d-flex align-items-center gap-1" href="rooms.php">
                                <i class="fas fa-bed text-info me-1"></i> 
                                <span>Pilihan Kamar</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white d-flex align-items-center gap-1" href="history.php">
                                <i class="fas fa-receipt text-warning me-1"></i> 
                                <span>Riwayat & Nota</span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link text-white d-flex align-items-center gap-1" href="../index.php">
                                <i class="fas fa-home text-warning me-1"></i> 
                                <span>Beranda Utama</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav align-items-lg-center gap-2">
                    <?php if (isLoggedIn()): ?>
                        <?php 
                        if (isAdmin()) {
                            $user_display = 'Administrator';
                            $role_badge_class = 'bg-danger text-white';
                        } elseif (isOwner()) {
                            $user_display = htmlspecialchars($_SESSION['full_name'] ?? 'Hotel Owner');
                            $role_badge_class = 'bg-warning text-dark fw-bold';
                        } elseif (isReceptionist()) {
                            $user_display = htmlspecialchars($_SESSION['full_name'] ?? 'Resepsionis');
                            $role_badge_class = 'bg-info text-dark';
                        } else {
                            $user_display = htmlspecialchars($_SESSION['full_name'] ?? 'User');
                            $role_badge_class = 'bg-primary text-white';
                        }
                        ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2 text-white p-1 pe-3 rounded-pill" href="#" id="userMenuDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.18);">
                                <div class="avatar-badge" style="width: 34px; height: 34px; font-size: 0.85rem; border-radius: 50%;">
                                    <?php echo strtoupper(substr($user_display, 0, 1)); ?>
                                </div>
                                <div class="d-none d-md-block text-start" style="line-height: 1.15;">
                                    <span class="fw-bold d-block small text-white" style="font-size: 0.85rem;"><?php echo $user_display; ?></span>
                                    <span class="badge <?php echo $role_badge_class; ?> text-uppercase fw-bold" style="font-size: 0.62rem; padding: 0.15rem 0.45rem;"><?php echo htmlspecialchars($_SESSION['role'] ?? 'user'); ?></span>
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 mt-2 py-2" style="min-width: 220px;">
                                <li><span class="dropdown-item-text small text-muted">Login sebagai <strong><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></strong></span></li>
                                <li><hr class="dropdown-divider"></li>
                                <?php if (isOwner()): ?>
                                    <li><a class="dropdown-item py-2" href="../index.php"><i class="fas fa-globe me-2 text-warning"></i> Lihat Halaman Utama</a></li>
                                    <li><a class="dropdown-item py-2" href="../admin/dashboard.php"><i class="fas fa-crown me-2 text-warning"></i> Dashboard Owner</a></li>
                                    <li><a class="dropdown-item py-2" href="../admin/reports.php"><i class="fas fa-chart-line me-2 text-primary"></i> Laporan Finansial</a></li>
                                    <li><a class="dropdown-item py-2" href="../admin/messages.php"><i class="fas fa-envelope me-2 text-info"></i> Pesan dari Staf</a></li>
                                    <li><a class="dropdown-item py-2" href="../admin/room-types.php"><i class="fas fa-door-open me-2 text-success"></i> Tipe & Status Kamar</a></li>
                                <?php elseif (isAdmin()): ?>
                                    <li><a class="dropdown-item py-2" href="../index.php"><i class="fas fa-globe me-2 text-warning"></i> Lihat Halaman Utama</a></li>
                                    <li><a class="dropdown-item py-2" href="../admin/dashboard.php"><i class="fas fa-tachometer-alt me-2 text-primary"></i> Dashboard Admin</a></li>
                                    <li><a class="dropdown-item py-2" href="../admin/reports.php"><i class="fas fa-chart-line me-2 text-purple" style="color: #8b5cf6;"></i> Laporan Keuangan</a></li>
                                    <li><a class="dropdown-item py-2" href="../admin/messages.php"><i class="fas fa-envelope me-2 text-info"></i> Pesan Masuk Staf</a></li>
                                    <li><a class="dropdown-item py-2" href="../admin/settings.php"><i class="fas fa-cog me-2 text-secondary"></i> Pengaturan Sistem</a></li>
                                <?php elseif (isReceptionist()): ?>
                                    <li><a class="dropdown-item py-2" href="../index.php"><i class="fas fa-globe me-2 text-warning"></i> Lihat Halaman Utama</a></li>
                                    <li><a class="dropdown-item py-2" href="../admin/dashboard.php"><i class="fas fa-concierge-bell me-2 text-info"></i> Front Desk Hub</a></li>
                                    <li><a class="dropdown-item py-2" href="../admin/messages.php"><i class="fas fa-paper-plane me-2 text-warning"></i> Pesan ke Owner</a></li>
                                    <li><a class="dropdown-item py-2" href="../admin/bookings.php"><i class="fas fa-calendar-check me-2 text-primary"></i> Pemesanan Tamu</a></li>
                                    <li><a class="dropdown-item py-2" href="../admin/rooms.php"><i class="fas fa-bed me-2 text-success"></i> Status Kamar</a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item py-2" href="index.php"><i class="fas fa-th-large me-2 text-primary"></i> Dashboard Tamu</a></li>
                                    <li><a class="dropdown-item py-2" href="booking.php"><i class="fas fa-calendar-plus me-2 text-success"></i> Pesan Kamar Baru</a></li>
                                    <li><a class="dropdown-item py-2" href="rooms.php"><i class="fas fa-bed me-2 text-info"></i> Katalog Kamar</a></li>
                                    <li><a class="dropdown-item py-2" href="history.php"><i class="fas fa-receipt me-2 text-warning"></i> Riwayat & Nota</a></li>
                                    <li><a class="dropdown-item py-2" href="profile.php"><i class="fas fa-user-circle me-2 text-purple" style="color: #7c3aed;"></i> Profil Saya</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item py-2 text-danger fw-semibold" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="btn btn-outline-light btn-sm px-3" href="../login.php"><i class="fas fa-sign-in-alt me-1"></i> Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-warning btn-sm px-3 fw-bold" href="../register.php"><i class="fas fa-user-plus me-1"></i> Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Floating Toast Notification (Overlay, does not take document space) -->
    <?php if ($flash_message): ?>
        <?php 
        $alert_type = $flash_message['type'] === 'error' ? 'danger' : $flash_message['type'];
        $alert_icons = [
            'success' => 'fas fa-check-circle text-success',
            'danger' => 'fas fa-exclamation-circle text-danger',
            'warning' => 'fas fa-exclamation-triangle text-warning',
            'info' => 'fas fa-info-circle text-info'
        ];
        $icon = $alert_icons[$alert_type] ?? 'fas fa-bell text-primary';
        $border_colors = [
            'success' => '#10b981',
            'danger' => '#ef4444',
            'warning' => '#f59e0b',
            'info' => '#3b82f6'
        ];
        $accent_color = $border_colors[$alert_type] ?? '#4f46e5';
        $type_labels = [
            'success' => 'Berhasil',
            'danger' => 'Peringatan / Gagal',
            'warning' => 'Pemberitahuan',
            'info' => 'Informasi'
        ];
        $label = $type_labels[$alert_type] ?? 'Notifikasi';
        ?>
        <div id="floating-toast-container" style="position: fixed; top: 24px; right: 24px; z-index: 99999; max-width: 400px; width: calc(100% - 48px); pointer-events: none;">
            <div id="floating-toast" class="shadow-lg border-0 rounded-4 overflow-hidden" role="alert" aria-live="assertive" aria-atomic="true" style="pointer-events: auto; background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border-left: 5px solid <?php echo $accent_color; ?> !important; box-shadow: 0 16px 36px rgba(15, 23, 42, 0.18) !important; animation: toastSlideIn 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;">
                <div class="d-flex align-items-center p-3">
                    <div class="me-3 fs-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                        <i class="<?php echo $icon; ?>"></i>
                    </div>
                    <div class="flex-grow-1 pe-2">
                        <div class="text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.06em; color: <?php echo $accent_color; ?>;"><?php echo $label; ?></div>
                        <div class="fw-semibold text-dark" style="font-size: 0.88rem; line-height: 1.35; margin-top: 2px;"><?php echo $flash_message['message']; ?></div>
                    </div>
                    <button type="button" class="btn-close ms-auto p-2" onclick="dismissFloatingToast()" aria-label="Close" style="opacity: 0.5; font-size: 0.8rem;"></button>
                </div>
                <div class="toast-progress" style="height: 3px; background: <?php echo $accent_color; ?>; width: 100%; animation: toastProgress 3.5s linear forwards;"></div>
            </div>
        </div>

        <script>
            function dismissFloatingToast() {
                var toast = document.getElementById('floating-toast');
                var container = document.getElementById('floating-toast-container');
                if (toast) {
                    toast.style.animation = 'toastSlideOut 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards';
                    setTimeout(function() {
                        if (container) container.remove();
                    }, 300);
                }
            }

            // Auto-dismiss floating toast after 3.5 seconds
            setTimeout(function() {
                dismissFloatingToast();
            }, 3500);
        </script>
    <?php endif; ?>