<?php
require_once '../config/init.php';
checkUserAuth();

$page_title = "Riwayat Reservasi & Nota - Grand Luxury Hotel";
include '../includes/header.php';

$user_id = $_SESSION['user_id'];

// Get user bookings with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$current_status = $_GET['status'] ?? '';
$current_search = $_GET['search'] ?? '';

// Build query with safe LEFT JOINs
$query = "SELECT b.*, r.room_number, rt.type_name, rt.base_price, rt.image as room_image,
                 p.payment_status, p.payment_method, p.amount as paid_amount,
                 c.full_name as customer_name
          FROM bookings b
          LEFT JOIN booking_details bd ON b.id = bd.booking_id
          LEFT JOIN rooms r ON bd.room_id = r.id
          LEFT JOIN room_types rt ON r.room_type_id = rt.id
          LEFT JOIN customers c ON b.customer_id = c.id
          LEFT JOIN payments p ON b.id = p.booking_id
          WHERE b.user_id = ?";

$count_query = "SELECT COUNT(DISTINCT b.id) as total 
                FROM bookings b 
                LEFT JOIN booking_details bd ON b.id = bd.booking_id
                LEFT JOIN rooms r ON bd.room_id = r.id
                LEFT JOIN room_types rt ON r.room_type_id = rt.id
                WHERE b.user_id = ?";

$params = [$user_id];
$count_params = [$user_id];

// Apply status filter
if ($current_status !== '') {
    $query .= " AND b.status = ?";
    $count_query .= " AND b.status = ?";
    $params[] = $current_status;
    $count_params[] = $current_status;
}

// Apply search filter
if ($current_search !== '') {
    $search = "%{$current_search}%";
    $query .= " AND (b.booking_code LIKE ? OR r.room_number LIKE ? OR rt.type_name LIKE ?)";
    $count_query .= " AND (b.booking_code LIKE ? OR r.room_number LIKE ? OR rt.type_name LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $count_params[] = $search;
    $count_params[] = $search;
    $count_params[] = $search;
}

$query .= " GROUP BY b.id ORDER BY b.created_at DESC LIMIT " . intval($limit) . " OFFSET " . intval($offset);

$bookings = $database->getAll($query, $params);
if ($bookings === false) {
    $bookings = [];
}

$count_res = $database->getSingle($count_query, $count_params);
$total_count = $count_res ? (int)$count_res['total'] : 0;
$total_pages = max(1, ceil($total_count / $limit));

// Get booking statistics for filter pills
$status_stats = $database->getAll("
    SELECT status, COUNT(*) as count 
    FROM bookings 
    WHERE user_id = ?
    GROUP BY status
", [$user_id]);

$status_counts = [
    'total' => 0,
    'pending' => 0,
    'confirmed' => 0,
    'checked_in' => 0,
    'checked_out' => 0,
    'cancelled' => 0
];
if ($status_stats) {
    foreach ($status_stats as $stat) {
        if (isset($status_counts[$stat['status']])) {
            $status_counts[$stat['status']] = (int)$stat['count'];
        }
        $status_counts['total'] += (int)$stat['count'];
    }
}
?>

<style>
    .card-luxury {
        background: #ffffff;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        overflow: hidden;
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

    .filter-pill {
        padding: 8px 18px;
        border-radius: 50rem;
        font-size: 0.85rem;
        font-weight: 600;
        text-decoration: none;
        color: #64748b;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        transition: all 0.25s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .filter-pill:hover, .filter-pill.active {
        color: #ffffff;
        background: #4f46e5;
        border-color: #4f46e5;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
    }
</style>

<div class="container py-4">
    
    <!-- Page Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="index.php" class="text-decoration-none text-muted small"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
                <span class="text-muted small">/</span>
                <span class="badge bg-light text-secondary border">Riwayat Reservasi</span>
            </div>
            <h2 class="fw-extrabold text-dark mb-0" style="font-family: 'Outfit', sans-serif;">
                Riwayat Pemesanan & E-Tiket
            </h2>
            <small class="text-muted">Pantau seluruh histori reservasi kamar hotel dan cetak kuitansi nota resmi Anda.</small>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="booking.php" class="btn btn-warning btn-md rounded-pill px-4 fw-bold shadow-sm">
                <i class="fas fa-plus-circle me-1"></i> Pesan Kamar Baru
            </a>
        </div>
    </div>

    <!-- Status Filter Pills -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="history.php" class="filter-pill <?php echo empty($current_status) ? 'active' : ''; ?>">
            Semua (<?php echo $status_counts['total']; ?>)
        </a>
        <a href="history.php?status=confirmed" class="filter-pill <?php echo ($current_status === 'confirmed') ? 'active' : ''; ?>">
            <i class="fas fa-check-circle"></i> Confirmed (<?php echo $status_counts['confirmed']; ?>)
        </a>
        <a href="history.php?status=checked_in" class="filter-pill <?php echo ($current_status === 'checked_in') ? 'active' : ''; ?>">
            <i class="fas fa-door-open"></i> Sedang Menginap (<?php echo $status_counts['checked_in']; ?>)
        </a>
        <a href="history.php?status=pending" class="filter-pill <?php echo ($current_status === 'pending') ? 'active' : ''; ?>">
            <i class="fas fa-clock"></i> Pending (<?php echo $status_counts['pending']; ?>)
        </a>
        <a href="history.php?status=checked_out" class="filter-pill <?php echo ($current_status === 'checked_out') ? 'active' : ''; ?>">
            <i class="fas fa-flag-checkered"></i> Selesai (<?php echo $status_counts['checked_out']; ?>)
        </a>
        <a href="history.php?status=cancelled" class="filter-pill <?php echo ($current_status === 'cancelled') ? 'active' : ''; ?>">
            <i class="fas fa-times-circle"></i> Dibatalkan (<?php echo $status_counts['cancelled']; ?>)
        </a>
    </div>

    <!-- Search Form -->
    <div class="card-luxury p-3 mb-4">
        <form method="GET" action="" class="row g-2 align-items-center">
            <?php if (!empty($current_status)): ?>
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($current_status); ?>">
            <?php endif; ?>
            <div class="col-md-9">
                <div class="input-group">
                    <span class="input-group-text bg-light border-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-0 bg-light rounded-end-3" 
                           placeholder="Cari berdasarkan kode booking, nomor kamar, atau tipe kamar..." 
                           value="<?php echo htmlspecialchars($current_search); ?>">
                </div>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold w-100">
                    Cari Data
                </button>
                <?php if (!empty($current_search) || !empty($current_status)): ?>
                    <a href="history.php" class="btn btn-outline-secondary rounded-pill px-3" title="Reset">
                        <i class="fas fa-sync-alt"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Bookings List Table -->
    <div class="card-luxury mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr class="text-uppercase text-muted" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                        <th class="ps-4">Kode & Kamar</th>
                        <th>Tanggal Menginap</th>
                        <th>Tamu & Durasi</th>
                        <th>Total Biaya</th>
                        <th>Status</th>
                        <th class="pe-4 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <i class="fas fa-calendar-times fs-1 text-muted opacity-50 mb-3 d-block"></i>
                                <h6 class="fw-bold text-dark">Tidak ada data pemesanan ditemukan.</h6>
                                <p class="text-muted small">Coba ubah filter pencarian atau lakukan pemesanan kamar baru.</p>
                                <a href="booking.php" class="btn btn-primary btn-sm rounded-pill px-4 mt-2 fw-semibold">
                                    Pesan Kamar Sekarang
                                </a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $b): 
                            $st = $b['status'];
                            $st_badge = [
                                'pending' => 'badge-pending',
                                'confirmed' => 'badge-confirmed',
                                'checked_in' => 'badge-checked_in',
                                'checked_out' => 'badge-checked_out',
                                'cancelled' => 'badge-cancelled'
                            ][$st] ?? 'badge-pending';
                        ?>
                        <tr>
                            <td class="ps-4 py-3">
                                <a href="booking_detail.php?id=<?php echo $b['id']; ?>" class="fw-bold text-primary text-decoration-none d-block">
                                    #<?php echo htmlspecialchars($b['booking_code']); ?>
                                </a>
                                <div class="fw-semibold text-dark small"><?php echo htmlspecialchars($b['type_name'] ?? 'Kamar Hotel'); ?></div>
                                <small class="text-muted"><i class="fas fa-door-closed text-secondary me-1"></i>Kamar #<?php echo htmlspecialchars($b['room_number'] ?? '-'); ?></small>
                            </td>
                            <td>
                                <div class="small fw-semibold text-dark"><i class="far fa-calendar-alt text-primary me-1"></i><?php echo date('d M Y', strtotime($b['check_in'])); ?></div>
                                <small class="text-muted">s.d <?php echo date('d M Y', strtotime($b['check_out'])); ?></small>
                            </td>
                            <td>
                                <div class="small fw-semibold text-dark"><?php echo $b['total_nights']; ?> Malam</div>
                                <small class="text-muted"><?php echo $b['adults']; ?> Dewasa, <?php echo $b['children']; ?> Anak</small>
                            </td>
                            <td>
                                <div class="fw-bold text-dark small">Rp <?php echo number_format($b['final_amount'], 0, ',', '.'); ?></div>
                                <?php if (($b['payment_status'] ?? '') === 'paid'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size: 0.65rem;"><i class="fas fa-check-circle me-1"></i>Lunas</span>
                                <?php else: ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25" style="font-size: 0.65rem;"><i class="fas fa-clock me-1"></i>Belum Lunas</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge-status-pill <?php echo $st_badge; ?>">
                                    <i class="fas fa-circle" style="font-size: 0.45rem;"></i>
                                    <?php echo strtoupper(str_replace('_', ' ', $st)); ?>
                                </span>
                            </td>
                            <td class="pe-4 text-end">
                                <div class="btn-group">
                                    <a href="booking_detail.php?id=<?php echo $b['id']; ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold">
                                        <i class="fas fa-ticket-alt me-1"></i> Detail
                                    </a>
                                    <a href="receipt.php?booking_id=<?php echo $b['id']; ?>" target="_blank" class="btn btn-sm btn-outline-dark rounded-pill ms-1 px-3 fw-semibold" title="Cetak Nota Resmi">
                                        <i class="fas fa-print me-1"></i> Nota
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="p-3 border-top d-flex justify-content-between align-items-center">
            <small class="text-muted">Menampilkan halaman <?php echo $page; ?> dari <?php echo $total_pages; ?> (Total <?php echo $total_count; ?> booking)</small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($page > 1): ?>
                        <li class="page-item"><a class="page-link rounded-pill px-3 me-1" href="?page=<?php echo $page-1; ?><?php echo !empty($current_status) ? '&status='.$current_status : ''; ?>">Prev</a></li>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link rounded-circle mx-1" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;" href="?page=<?php echo $i; ?><?php echo !empty($current_status) ? '&status='.$current_status : ''; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item"><a class="page-link rounded-pill px-3 ms-1" href="?page=<?php echo $page+1; ?><?php echo !empty($current_status) ? '&status='.$current_status : ''; ?>">Next</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php include '../includes/footer.php'; ?>