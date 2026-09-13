<?php
require_once 'config/init.php';

$page_title = HOTEL_NAME . " - Hotel Terbaik & Pengalaman Menginap Mewah";

// Sample room images map by room type name or fallback
$room_images = [
    'Standard' => 'https://images.unsplash.com/photo-1566665797739-1674de7a421a?auto=format&fit=crop&w=800&q=80',
    'Superior' => 'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=800&q=80',
    'Deluxe'   => 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?auto=format&fit=crop&w=800&q=80',
    'Suite'    => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=800&q=80',
    'Presidential' => 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?auto=format&fit=crop&w=800&q=80',
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.8), rgba(30, 41, 59, 0.85)), 
                        url('https://images.unsplash.com/photo-1566073771259-6a8506099945?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80') center/cover no-repeat;
            padding: 160px 0 120px;
            color: white;
            position: relative;
        }
        .hero-badge {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 0.5rem 1.25rem;
            border-radius: 50rem;
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .search-bar-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 1.75rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            margin-top: -50px;
            position: relative;
            z-index: 10;
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        .room-img-wrapper {
            position: relative;
            height: 220px;
            overflow: hidden;
            border-top-left-radius: 16px;
            border-top-right-radius: 16px;
        }
        .room-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        .room-card:hover .room-img-wrapper img {
            transform: scale(1.08);
        }
        .room-badge-price {
            position: absolute;
            bottom: 12px;
            right: 12px;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(8px);
            color: #fcd34d;
            padding: 0.35rem 0.85rem;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.9rem;
        }
        .feature-box {
            background: white;
            border-radius: 20px;
            padding: 2.5rem 1.75rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        .feature-box:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(79, 70, 229, 0.12);
            border-color: #c7d2fe;
        }
        .feature-icon-circle {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #4f46e5, #0ea5e9);
            color: white;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin: 0 auto 1.5rem;
            box-shadow: 0 8px 16px rgba(79, 70, 229, 0.25);
        }
    </style>
</head>
<body>
    <!-- Navbar Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <i class="fas fa-hotel me-2 fs-3 text-warning"></i>
                <span class="fw-bold fs-4"><?php echo HOTEL_NAME; ?></span>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto ms-lg-4">
                    <li class="nav-item"><a class="nav-link active" href="#home">Beranda</a></li>
                    <li class="nav-item"><a class="nav-link" href="#rooms">Pilihan Kamar</a></li>
                    <li class="nav-item"><a class="nav-link" href="#facilities">Fasilitas & Layanan</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">Tentang Kami</a></li>
                </ul>
                <div class="d-flex align-items-center gap-2">
                    <?php if (isLoggedIn()): ?>
                        <?php 
                        $user_display = (isAdmin() || isReceptionist()) ? 'Admin' : htmlspecialchars($_SESSION['full_name'] ?? 'User');
                        $user_initial = strtoupper(substr($user_display, 0, 1));
                        $user_role = htmlspecialchars($_SESSION['role'] ?? 'user');
                        ?>
                        <div class="dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2 text-white p-1 pe-3 rounded-pill" href="#" id="indexUserDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.18);">
                                <div class="avatar-badge" style="width: 34px; height: 34px; font-size: 0.85rem; border-radius: 50%;">
                                    <?php echo $user_initial; ?>
                                </div>
                                <div class="text-start" style="line-height: 1.15;">
                                    <span class="fw-bold d-block small text-white" style="font-size: 0.85rem;"><?php echo $user_display; ?></span>
                                    <span class="badge bg-warning text-dark text-uppercase fw-bold" style="font-size: 0.62rem; padding: 0.15rem 0.45rem;"><?php echo $user_role; ?></span>
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 mt-2 py-2" style="min-width: 220px;">
                                <li><span class="dropdown-item-text small text-muted">Login sebagai <strong><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></strong></span></li>
                                <li><hr class="dropdown-divider"></li>
                                <?php if (isAdmin() || isReceptionist()): ?>
                                    <li><a class="dropdown-item py-2" href="admin/dashboard.php"><i class="fas fa-tachometer-alt me-2 text-primary"></i> Dashboard Admin</a></li>
                                    <li><a class="dropdown-item py-2" href="admin/settings.php"><i class="fas fa-cog me-2 text-secondary"></i> Pengaturan Sistem</a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item py-2" href="user/index.php"><i class="fas fa-tachometer-alt me-2 text-primary"></i> Dashboard Tamu</a></li>
                                    <li><a class="dropdown-item py-2" href="user/profile.php"><i class="fas fa-user me-2 text-info"></i> Profil Saya</a></li>
                                    <li><a class="dropdown-item py-2" href="user/history.php"><i class="fas fa-history me-2 text-warning"></i> Riwayat Reservasi</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item py-2 text-danger fw-semibold" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a class="btn btn-outline-light btn-sm px-3" href="login.php">
                            <i class="fas fa-sign-in-alt me-1"></i> Masuk
                        </a>
                        <a class="btn btn-warning btn-sm px-3 fw-bold" href="register.php">
                            <i class="fas fa-user-plus me-1"></i> Registrasi
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section text-center text-lg-start">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <div class="hero-badge mb-3">
                        <i class="fas fa-star text-warning"></i>
                        <span>Hotel Bintang 5 Luxury Resort & Spa</span>
                    </div>
                    <h1 class="display-3 fw-extrabold mb-4 text-white" style="font-family: 'Outfit', sans-serif;">
                        Nikmati Keindahan & Kemewahan Sejati
                    </h1>
                    <p class="lead text-light mb-5 fs-5 opacity-90">
                        Selamat datang di <?php echo HOTEL_NAME; ?>. Rasakan kenyamanan menginap berkelas dunia dengan fasilitas elegan, pelayanan bintang 5, dan pemandangan luar biasa.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="#rooms" class="btn btn-warning btn-lg px-4 py-3 fw-bold">
                            <i class="fas fa-calendar-check me-2"></i> Reservasi Kamar
                        </a>
                        <?php if (isLoggedIn()): ?>
                            <?php if (isAdmin() || isReceptionist()): ?>
                                <a href="admin/dashboard.php" class="btn btn-outline-light btn-lg px-4 py-3">
                                    <i class="fas fa-tachometer-alt me-2 text-warning"></i> Dashboard Admin
                                </a>
                            <?php else: ?>
                                <a href="user/index.php" class="btn btn-outline-light btn-lg px-4 py-3">
                                    <i class="fas fa-user-circle me-2 text-warning"></i> Dashboard Saya
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="#about" class="btn btn-outline-light btn-lg px-4 py-3">
                                <i class="fas fa-compass me-2"></i> Jelajahi Hotel
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Floating Search & Quick Check -->
    <div class="container">
        <div class="search-bar-card">
            <form action="<?php echo isLoggedIn() ? 'user/rooms.php' : 'register.php'; ?>" method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary">Tipe Kamar</label>
                    <select class="form-select border-0 bg-light">
                        <option value="">Semua Tipe Kamar</option>
                        <option value="Standard">Standard Room</option>
                        <option value="Deluxe">Deluxe Suite</option>
                        <option value="Presidential">Presidential Suite</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary">Tanggal Check-In</label>
                    <input type="date" class="form-control border-0 bg-light" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary">Tanggal Check-Out</label>
                    <input type="date" class="form-control border-0 bg-light" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100 py-3 fw-bold">
                        <i class="fas fa-search me-2"></i> Cek Ketersediaan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Pilihan Kamar -->
    <section id="rooms" class="py-5 mt-4">
        <div class="container py-4">
            <div class="text-center max-w-2xl mx-auto mb-5">
                <span class="text-primary font-monospace text-uppercase fw-bold">Akomodasi Pilihan</span>
                <h2 class="display-6 fw-bold mt-1">Kamar & Suite Mewah Kami</h2>
                <p class="text-muted">Desain interior elegan dengan pemandangan menakjubkan dan kenyamanan tanpa kompromi.</p>
            </div>

            <div class="row g-4">
                <?php
                $room_types = $database->getAll("
                    SELECT rt.*, COUNT(r.id) as total_rooms
                    FROM room_types rt 
                    LEFT JOIN rooms r ON rt.id = r.room_type_id 
                    WHERE rt.is_available = 1 
                    GROUP BY rt.id 
                    ORDER BY rt.base_price ASC 
                    LIMIT 4
                ");
                
                foreach ($room_types as $index => $room):
                    $imgUrl = getRoomImageUrl($room['image'] ?? '');
                ?>
                <div class="col-lg-3 col-md-6">
                    <div class="glass-card room-card h-100 d-flex flex-column">
                        <div class="room-img-wrapper">
                            <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="<?php echo htmlspecialchars($room['type_name']); ?>">
                            <div class="room-badge-price">
                                <?php echo formatCurrency($room['base_price']); ?> <span class="small opacity-75">/ mlg</span>
                            </div>
                        </div>
                        <div class="p-4 d-flex flex-column flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-indigo-100 text-indigo-800 rounded-pill px-3 py-1 text-primary bg-light fw-semibold" style="font-size: 0.75rem;">
                                    <?php echo $room['bed_type'] ?? 'King Bed'; ?>
                                </span>
                                <div class="text-warning small">
                                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                                </div>
                            </div>
                            <h5 class="fw-bold mb-2" style="font-family: 'Outfit', sans-serif;"><?php echo htmlspecialchars($room['type_name']); ?></h5>
                            <p class="text-muted small mb-3 flex-grow-1"><?php echo htmlspecialchars(substr($room['description'], 0, 90)) . '...'; ?></p>
                            
                            <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                                <span class="small text-muted"><i class="fas fa-user-friends me-1 text-primary"></i> Max <?php echo $room['capacity']; ?> Orang</span>
                                <a href="<?php echo isLoggedIn() ? 'user/rooms.php' : 'login.php'; ?>" class="btn btn-outline-primary btn-sm px-3 rounded-pill fw-bold">
                                    Pesan <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Fasilitas & Layanan -->
    <section id="facilities" class="py-5 bg-light">
        <div class="container py-4">
            <div class="text-center mb-5">
                <span class="text-primary text-uppercase fw-bold" style="letter-spacing: 0.05em;">Layanan Eksklusif</span>
                <h2 class="display-6 fw-bold mt-1">Fasilitas Kelas Satu</h2>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-box">
                        <div class="feature-icon-circle"><i class="fas fa-swimming-pool"></i></div>
                        <h4 class="fw-bold mb-2">Infinity Pool</h4>
                        <p class="text-muted small">Kolam renang rooftop dengan pemandangan 360 derajat lanskap kota dan matahari terbenam.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-box">
                        <div class="feature-icon-circle"><i class="fas fa-spa"></i></div>
                        <h4 class="fw-bold mb-2">Luxury Spa & Wellness</h4>
                        <p class="text-muted small">Pijat refleksi dan perawatan tubuh dari terapis profesional untuk menyegarkan pikiran Anda.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-box">
                        <div class="feature-icon-circle"><i class="fas fa-utensils"></i></div>
                        <h4 class="fw-bold mb-2">Fine Dining Restaurant</h4>
                        <p class="text-muted small">Hidangan lezat nusantara dan internasional kreasi Chef bintang lima khas hotel kami.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5 mt-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h4 class="fw-bold text-white mb-3"><i class="fas fa-hotel text-warning me-2"></i><?php echo HOTEL_NAME; ?></h4>
                    <p class="text-muted small">Hotel dan resort kemewahan bintang lima di pusat kota dengan fasilitas modern terlengkap untuk pengalaman menginap tak terlupakan.</p>
                </div>
                <div class="col-lg-4">
                    <h5 class="fw-bold text-white mb-3">Akses Cepat</h5>
                    <ul class="list-unstyled small text-muted">
                        <li class="mb-2"><a href="login.php" class="text-decoration-none text-light"><i class="fas fa-user-shield me-2 text-warning"></i>Login Administrator</a></li>
                        <li class="mb-2"><a href="register.php" class="text-decoration-none text-light"><i class="fas fa-user-plus me-2 text-warning"></i>Pendaftaran Tamu</a></li>
                    </ul>
                </div>
                <div class="col-lg-4">
                    <h5 class="fw-bold text-white mb-3">Hubungi Kami</h5>
                    <p class="small text-muted mb-1"><i class="fas fa-map-marker-alt me-2 text-warning"></i>Jl. Luxury Resort No. 88, Jakarta</p>
                    <p class="small text-muted mb-1"><i class="fas fa-phone me-2 text-warning"></i>+62 21 555 8888</p>
                    <p class="small text-muted"><i class="fas fa-envelope me-2 text-warning"></i>info@grandluxuryhotel.com</p>
                </div>
            </div>
            <hr class="my-4 border-secondary">
            <div class="text-center small text-muted">
                &copy; <?php echo date('Y'); ?> <?php echo HOTEL_NAME; ?>. All rights reserved.
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>