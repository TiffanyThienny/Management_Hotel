<?php
require_once '../config/init.php';
checkUserAuth();

$page_title = "Kamar & Suite Tersedia - Grand Luxury Hotel";
include '../includes/header.php';

// Get filter parameters
$check_in = $_GET['check_in'] ?? date('Y-m-d', strtotime('+1 day'));
$check_out = $_GET['check_out'] ?? date('Y-m-d', strtotime('+3 days'));
$room_type = $_GET['room_type'] ?? '';
$guests = $_GET['guests'] ?? 2;

// Get available room types with availability count
$room_types_query = "
    SELECT rt.*, 
           COUNT(r.id) as total_rooms,
           SUM(CASE WHEN r.status = 'available' AND r.id NOT IN (
               SELECT bd.room_id 
               FROM booking_details bd 
               JOIN bookings b ON bd.booking_id = b.id 
               WHERE b.status IN ('confirmed', 'checked_in')
               AND (b.check_in <= ? AND b.check_out >= ?)
           ) THEN 1 ELSE 0 END) as available_rooms
    FROM room_types rt
    LEFT JOIN rooms r ON rt.id = r.room_type_id
    WHERE rt.is_available = 1
    GROUP BY rt.id
    HAVING available_rooms > 0 OR ? = ''
    ORDER BY rt.base_price ASC
";

$room_types = $database->getAll($room_types_query, [$check_out, $check_in, $room_type]);

// Get facilities for display
$facilities = $database->getAll("
    SELECT f.*, rf.room_type_id
    FROM facilities f
    JOIN room_facilities rf ON f.id = rf.facility_id
    ORDER BY f.category, f.name
");
?>

<style>
    .rooms-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #312e81 100%);
        border-radius: 20px;
        padding: 32px;
        color: #fff;
        margin-bottom: 24px;
        border: 1px solid rgba(255, 255, 255, 0.15);
        box-shadow: 0 10px 30px rgba(0,0,0,0.06);
    }
    .rooms-hero h1, .rooms-hero h2, .rooms-hero h3, .rooms-hero h4 {
        color: #ffffff !important;
        font-weight: 800 !important;
    }
    .rooms-hero p {
        color: #e2e8f0 !important;
    }
    .filter-card {
        background: #ffffff;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        padding: 24px;
        margin-bottom: 24px;
    }
    .room-luxury-card {
        background: #ffffff;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .room-luxury-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 16px 32px rgba(0,0,0,0.08);
        border-color: #cbd5e1;
    }
    .room-img-wrapper {
        position: relative;
        height: 220px;
        overflow: hidden;
        background: #0f172a;
    }
    .room-img-wrapper img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }
    .room-luxury-card:hover .room-img-wrapper img {
        transform: scale(1.05);
    }
    .room-price-tag {
        background: #ffffff;
        border-radius: 16px;
        padding: 16px;
        border: 1px solid #e2e8f0;
        text-align: center;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    }
    .form-control-luxury {
        border-radius: 12px;
        border: 1.5px solid #e2e8f0;
        padding: 10px 14px;
        font-size: 0.95rem;
    }
    .form-control-luxury:focus {
        border-color: #4f46e5;
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
    }
    .amenity-chip {
        font-size: 0.78rem;
        background: #f1f5f9;
        color: #334155;
        padding: 5px 10px;
        border-radius: 8px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
</style>

<div class="container py-4">
    
    <!-- Hero Banner -->
    <div class="rooms-hero">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-warning text-dark fw-bold px-3 py-1 rounded-pill">
                        <i class="fas fa-gem me-1"></i> AKOMODASI MEWAH
                    </span>
                    <span class="text-white-50 small">Pilihan Kamar & Suite Eksklusif</span>
                </div>
                <h2 class="fw-extrabold mb-1" style="font-family: 'Outfit', sans-serif; font-size: 1.85rem;">
                    Katalog Kamar & Suite
                </h2>
                <p class="mb-0 text-white-50">
                    Pilih tipe kamar yang sesuai dengan preferensi kenyamanan dan kebutuhan menginap Anda.
                </p>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-light rounded-pill px-4 fw-semibold">
                    <i class="fas fa-arrow-left me-1"></i> Dashboard Tamu
                </a>
            </div>
        </div>
    </div>

    <!-- Filter & Search Form -->
    <div class="filter-card">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label class="form-label fw-bold text-dark small mb-1"><i class="fas fa-calendar-check me-1 text-primary"></i>Check-in</label>
                <input type="date" class="form-control form-control-luxury" name="check_in" 
                       value="<?php echo $check_in; ?>" min="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label fw-bold text-dark small mb-1"><i class="fas fa-calendar-times me-1 text-danger"></i>Check-out</label>
                <input type="date" class="form-control form-control-luxury" name="check_out" 
                       value="<?php echo $check_out; ?>" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label fw-bold text-dark small mb-1"><i class="fas fa-bed me-1 text-indigo"></i>Tipe Kamar</label>
                <select class="form-select form-control-luxury" name="room_type">
                    <option value="">Semua Tipe Kamar</option>
                    <?php
                    $all_room_types = $database->getAll("SELECT * FROM room_types WHERE is_available = 1");
                    foreach ($all_room_types as $type):
                    ?>
                    <option value="<?php echo $type['id']; ?>" <?php echo $room_type == $type['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($type['type_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <label class="form-label fw-bold text-dark small mb-1"><i class="fas fa-users me-1 text-info"></i>Jumlah Tamu</label>
                <select class="form-select form-control-luxury" name="guests">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo $guests == $i ? 'selected' : ''; ?>>
                        <?php echo $i; ?> Tamu
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-lg-1 col-md-2">
                <button type="submit" class="btn btn-primary rounded-3 w-100 py-2 fw-bold shadow-sm" title="Cari Kamar">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
    </div>

    <!-- Results Status Ribbon -->
    <div class="d-flex flex-wrap justify-content-between align-items-center bg-light p-3 rounded-4 border mb-4">
        <div>
            <span class="fw-bold text-dark"><i class="fas fa-info-circle text-primary me-2"></i>Periode:</span>
            <span class="text-secondary"><?php echo date('d M Y', strtotime($check_in)); ?> &mdash; <?php echo date('d M Y', strtotime($check_out)); ?></span>
            <span class="badge bg-primary text-white rounded-pill ms-2"><?php echo calculateNights($check_in, $check_out); ?> Malam</span>
        </div>
        <div class="text-muted small">
            Ditemukan <strong class="text-dark"><?php echo count($room_types); ?></strong> tipe kamar sesuai kriteria
        </div>
    </div>

    <!-- Room Types Grid -->
    <div class="row g-4 mb-5">
        <?php if (!empty($room_types)): ?>
            <?php foreach ($room_types as $rt_item): ?>
            <div class="col-lg-6">
                <div class="room-luxury-card">
                    <div class="room-img-wrapper">
                        <img src="<?php echo htmlspecialchars(getRoomImageUrl($rt_item['image'] ?? '')); ?>" alt="<?php echo htmlspecialchars($rt_item['type_name']); ?>">
                        <div class="position-absolute top-0 start-0 p-3">
                            <span class="badge bg-dark bg-opacity-75 text-white backdrop-blur rounded-pill px-3 py-1 small">
                                <i class="fas fa-tag me-1 text-warning"></i> Premium Room
                            </span>
                        </div>
                        <div class="position-absolute top-0 end-0 p-3">
                            <span class="badge bg-success rounded-pill px-3 py-2 shadow-sm fw-bold">
                                <i class="fas fa-check-circle me-1"></i> <?php echo $rt_item['available_rooms']; ?> Kamar Tersedia
                            </span>
                        </div>
                    </div>

                    <div class="p-4 d-flex flex-column flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h4 class="fw-bold mb-1" style="font-family: 'Outfit', sans-serif; color: #0f172a;">
                                    <?php echo htmlspecialchars($rt_item['type_name']); ?>
                                </h4>
                                <div class="text-muted small mb-2 d-flex align-items-center gap-3">
                                    <span><i class="fas fa-users text-primary me-1"></i> <?php echo $rt_item['capacity']; ?> Orang</span>
                                    <span><i class="fas fa-expand-arrows-alt text-success me-1"></i> <?php echo $rt_item['size'] ?? '32 m²'; ?></span>
                                    <span><i class="fas fa-bed text-warning me-1"></i> <?php echo $rt_item['bed_type'] ?? 'King / Twin'; ?></span>
                                </div>
                            </div>
                        </div>

                        <p class="text-muted small mb-3 flex-grow-1" style="line-height: 1.6;">
                            <?php echo htmlspecialchars($rt_item['description'] ?? 'Nikmati kenyamanan berkelas tinggi dengan berbagai fasilitas eksklusif untuk menyempurnakan liburan atau perjalanan bisnis Anda.'); ?>
                        </p>

                        <!-- Room Facilities List -->
                        <div class="mb-4">
                            <span class="text-muted fw-bold d-block small mb-2 text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Fasilitas Termasuk:</span>
                            <div class="d-flex flex-wrap gap-1">
                                <?php
                                $room_facs = array_filter($facilities, function($fac) use ($rt_item) {
                                    return $fac['room_type_id'] == $rt_item['id'];
                                });
                                $display_facs = array_slice($room_facs, 0, 4);
                                foreach ($display_facs as $fac):
                                ?>
                                <span class="amenity-chip">
                                    <i class="fas fa-<?php echo htmlspecialchars($fac['icon'] ?? 'check'); ?> text-primary"></i>
                                    <?php echo htmlspecialchars($fac['name']); ?>
                                </span>
                                <?php endforeach; ?>
                                <?php if (count($room_facs) > 4): ?>
                                    <span class="amenity-chip bg-secondary text-white">+<?php echo count($room_facs) - 4; ?> lainnya</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Price & Action Box -->
                        <?php
                        $nights = calculateNights($check_in, $check_out);
                        $total_price = $nights * $rt_item['base_price'];
                        ?>
                        <div class="room-price-tag d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="text-start">
                                <span class="text-muted small d-block">Harga per Malam</span>
                                <span class="fw-extrabold fs-4 text-primary" style="font-family: 'Outfit', sans-serif;">
                                    <?php echo formatCurrency($rt_item['base_price']); ?>
                                </span>
                                <small class="text-muted d-block" style="font-size: 0.75rem;">Total (<?php echo $nights; ?> malam): <strong><?php echo formatCurrency($total_price); ?></strong></small>
                            </div>
                            <div>
                                <a href="booking.php?room_type=<?php echo $rt_item['id']; ?>&check_in=<?php echo $check_in; ?>&check_out=<?php echo $check_out; ?>&guests=<?php echo $guests; ?>" 
                                   class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm">
                                    <i class="fas fa-calendar-check me-1"></i> Pesan Sekarang
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="bg-white rounded-4 p-5 text-center border shadow-sm">
                    <i class="fas fa-bed fa-3x text-muted opacity-50 mb-3"></i>
                    <h4 class="fw-bold text-dark">Tidak Ada Kamar Tersedia</h4>
                    <p class="text-muted mb-4">Mohon maaf, tidak ada kamar yang tersedia untuk tanggal dan kriteria pencarian yang Anda pilih.</p>
                    <a href="rooms.php" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="fas fa-redo me-1"></i> Reset Pencarian
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Hotel Luxury Facilities Spotlight -->
    <div class="bg-white rounded-4 border p-4 shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="fw-bold mb-0 text-dark" style="font-family: 'Outfit', sans-serif;">
                    <i class="fas fa-concierge-bell me-2 text-warning"></i>Fasilitas Unggulan Resor
                </h5>
                <small class="text-muted">Nikmati fasilitas kelas dunia selama menginap di Grand Luxury Hotel</small>
            </div>
        </div>

        <div class="row g-3">
            <?php
            $hotel_facilities = $database->getAll("
                SELECT * FROM facilities 
                WHERE category IN ('hotel', 'service') 
                AND is_available = 1
                ORDER BY category, name
                LIMIT 8
            ");
            foreach ($hotel_facilities as $facility):
            ?>
            <div class="col-xl-3 col-md-6">
                <div class="d-flex align-items-center gap-3 p-3 rounded-3 bg-light border">
                    <div class="rounded-3 p-2 bg-white text-primary shadow-sm d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="fas fa-<?php echo htmlspecialchars($facility['icon'] ?? 'star'); ?> fs-5"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-dark small"><?php echo htmlspecialchars($facility['name']); ?></h6>
                        <small class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($facility['description'] ?? 'Layanan terbaik'); ?></small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<script>
$(document).ready(function() {
    $('input[name="check_in"]').on('change', function() {
        var checkIn = new Date($(this).val());
        var checkOut = new Date($('input[name="check_out"]').val());
        
        if (checkOut <= checkIn) {
            var nextDay = new Date(checkIn);
            nextDay.setDate(nextDay.getDate() + 1);
            $('input[name="check_out"]').val(nextDay.toISOString().split('T')[0]);
        }
        $('input[name="check_out"]').attr('min', $(this).val());
    });
});
</script>

<?php include '../includes/footer.php'; ?>