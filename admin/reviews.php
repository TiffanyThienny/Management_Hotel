<?php
require_once '../config/init.php';
checkAdminAuth();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Restrict receptionist to read-only for reviews
if (isReceptionist() && (isset($_GET['approve']) || isset($_GET['reject']) || isset($_GET['delete']))) {
    setFlashMessage('error', 'Resepsionis hanya memiliki hak akses melihat ulasan tamu.');
    redirect('reviews.php');
}

// Process review actions
if (isset($_GET['approve']) && $id > 0) {
    $result = $database->update('reviews', ['is_approved' => 1], "id = $id");
    if ($result) {
        setFlashMessage('success', 'Review berhasil disetujui');
    } else {
        setFlashMessage('error', 'Gagal menyetujui review');
    }
    redirect('reviews.php');
}

if (isset($_GET['reject']) && $id > 0) {
    $result = $database->update('reviews', ['is_approved' => 0], "id = $id");
    if ($result) {
        setFlashMessage('success', 'Review berhasil ditolak');
    } else {
        setFlashMessage('error', 'Gagal menolak review');
    }
    redirect('reviews.php');
}

// Delete action
if (isset($_GET['delete']) && $id > 0) {
    $result = $database->delete('reviews', "id = $id");
    if ($result) {
        setFlashMessage('success', 'Review berhasil dihapus');
    } else {
        setFlashMessage('error', 'Gagal menghapus review');
    }
    redirect('reviews.php');
}

$page_title = "Management Reviews";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/admin-sidebar.php'; ?>

    <!-- Main Content -->
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Management Reviews</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <a href="?status=pending" class="btn btn-outline-warning <?php echo isset($_GET['status']) && $_GET['status'] == 'pending' ? 'active' : ''; ?>">
                        <i class="fas fa-clock me-1"></i> Pending
                    </a>
                    <a href="?status=approved" class="btn btn-outline-success <?php echo isset($_GET['status']) && $_GET['status'] == 'approved' ? 'active' : ''; ?>">
                        <i class="fas fa-check me-1"></i> Approved
                    </a>
                    <a href="reviews.php" class="btn btn-outline-primary <?php echo !isset($_GET['status']) ? 'active' : ''; ?>">
                        <i class="fas fa-list me-1"></i> Semua
                    </a>
                </div>
            </div>
        </div>

        <!-- Review Statistics -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Total Ulasan</span>
                        <h4 class="fw-extrabold mb-0 mt-1 text-primary" style="font-family: 'Outfit', sans-serif;">
                            <?php echo $database->getSingle("SELECT COUNT(*) as count FROM reviews")['count']; ?>
                        </h4>
                        <small class="text-muted"><i class="fas fa-comments me-1 text-primary"></i>Semua Testimoni</small>
                    </div>
                    <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #e0e7ff; color: #4338ca; width: 50px; height: 50px;">
                        <i class="fas fa-star fs-4"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Ulasan Disetujui</span>
                        <h4 class="fw-extrabold mb-0 mt-1 text-success" style="font-family: 'Outfit', sans-serif;">
                            <?php echo $database->getSingle("SELECT COUNT(*) as count FROM reviews WHERE is_approved = 1")['count']; ?>
                        </h4>
                        <small class="text-muted"><i class="fas fa-check-circle me-1 text-success"></i>Tampil di Website</small>
                    </div>
                    <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #d1fae5; color: #059669; width: 50px; height: 50px;">
                        <i class="fas fa-check-circle fs-4"></i>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Menunggu Review</span>
                        <h4 class="fw-extrabold mb-0 mt-1 text-warning" style="font-family: 'Outfit', sans-serif;">
                            <?php echo $database->getSingle("SELECT COUNT(*) as count FROM reviews WHERE is_approved = 0")['count']; ?>
                        </h4>
                        <small class="text-muted"><i class="fas fa-clock me-1 text-warning"></i>Perlu Moderasi</small>
                    </div>
                    <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #fef3c7; color: #d97706; width: 50px; height: 50px;">
                        <i class="fas fa-clock fs-4"></i>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Rata-Rata Rating</span>
                        <h4 class="fw-extrabold mb-0 mt-1 text-info" style="font-family: 'Outfit', sans-serif;">
                            <?php echo number_format($database->getSingle("SELECT AVG(overall_rating) as avg_rating FROM reviews WHERE is_approved = 1")['avg_rating'] ?? 0, 1); ?> <span class="fs-6 text-muted">/ 5.0</span>
                        </h4>
                        <small class="text-muted"><i class="fas fa-medal me-1 text-info"></i>Skor Kepuasan</small>
                    </div>
                    <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #e0f2fe; color: #0284c7; width: 50px; height: 50px;">
                        <i class="fas fa-award fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reviews List -->
        <div class="card shadow">
            <div class="card-header">
                <h5 class="card-title mb-0">Daftar Reviews</h5>
            </div>
            <div class="card-body">
                <!-- Filter Form -->
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Rating</label>
                        <select name="rating" class="form-select" onchange="this.form.submit()">
                            <option value="">Semua Rating</option>
                            <option value="5" <?php echo isset($_GET['rating']) && $_GET['rating'] == '5' ? 'selected' : ''; ?>>⭐ 5 Bintang</option>
                            <option value="4" <?php echo isset($_GET['rating']) && $_GET['rating'] == '4' ? 'selected' : ''; ?>>⭐ 4 Bintang</option>
                            <option value="3" <?php echo isset($_GET['rating']) && $_GET['rating'] == '3' ? 'selected' : ''; ?>>⭐ 3 Bintang</option>
                            <option value="2" <?php echo isset($_GET['rating']) && $_GET['rating'] == '2' ? 'selected' : ''; ?>>⭐ 2 Bintang</option>
                            <option value="1" <?php echo isset($_GET['rating']) && $_GET['rating'] == '1' ? 'selected' : ''; ?>>⭐ 1 Bintang</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Status</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">Semua Status</option>
                            <option value="approved" <?php echo isset($_GET['status']) && $_GET['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="pending" <?php echo isset($_GET['status']) && $_GET['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Dari Tanggal</label>
                        <input type="date" name="start_date" class="form-control datepicker" 
                               value="<?php echo $_GET['start_date'] ?? ''; ?>" onchange="this.form.submit()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">&nbsp;</label>
                        <a href="reviews.php" class="btn btn-secondary w-100">Reset</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable">
                        <thead>
                            <tr>
                                <th>Review</th>
                                <th>Rating</th>
                                <th>Customer & Kamar</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Build query with filters
                            $query = "SELECT r.*, u.full_name as user_name, rm.room_number, rt.type_name, b.booking_code
                                      FROM reviews r
                                      JOIN users u ON r.user_id = u.id
                                      JOIN rooms rm ON r.room_id = rm.id
                                      JOIN room_types rt ON rm.room_type_id = rt.id
                                      JOIN bookings b ON r.booking_id = b.id
                                      WHERE 1=1";
                            
                            $params = [];
                            
                            if (isset($_GET['rating']) && $_GET['rating'] != '') {
                                $query .= " AND r.overall_rating = ?";
                                $params[] = $_GET['rating'];
                            }
                            
                            if (isset($_GET['status']) && $_GET['status'] != '') {
                                if ($_GET['status'] == 'approved') {
                                    $query .= " AND r.is_approved = 1";
                                } elseif ($_GET['status'] == 'pending') {
                                    $query .= " AND r.is_approved = 0";
                                }
                            }
                            
                            if (isset($_GET['start_date']) && $_GET['start_date'] != '') {
                                $query .= " AND DATE(r.created_at) >= ?";
                                $params[] = $_GET['start_date'];
                            }
                            
                            $query .= " ORDER BY r.created_at DESC";
                            
                            $reviews = $database->getAll($query, $params);
                            
                            foreach ($reviews as $review):
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold mb-1"><?php echo $review['user_name']; ?></div>
                                    <div class="text-muted mb-2">
                                        <?php 
                                        if (strlen($review['comment']) > 150) {
                                            echo substr($review['comment'], 0, 150) . '...';
                                        } else {
                                            echo $review['comment'];
                                        }
                                        ?>
                                    </div>
                                    <?php if ($review['comment'] && strlen($review['comment']) > 150): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" data-bs-target="#reviewModal<?php echo $review['id']; ?>">
                                            Baca Selengkapnya
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-warning mb-2">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?php echo $i <= $review['overall_rating'] ? '' : '-empty'; ?>"></i>
                                        <?php endfor; ?>
                                        <small class="text-muted">(<?php echo $review['overall_rating']; ?>.0)</small>
                                    </div>
                                    <div class="small text-muted">
                                        <div>Kebersihan: <?php echo $review['rating_cleanliness']; ?>/5</div>
                                        <div>Kenyamanan: <?php echo $review['rating_comfort']; ?>/5</div>
                                        <div>Lokasi: <?php echo $review['rating_location']; ?>/5</div>
                                        <div>Pelayanan: <?php echo $review['rating_service']; ?>/5</div>
                                        <div>Fasilitas: <?php echo $review['rating_facilities']; ?>/5</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo $review['room_number']; ?></div>
                                    <small class="text-muted"><?php echo $review['type_name']; ?></small>
                                    <div class="mt-2">
                                        <small>Booking: <?php echo $review['booking_code']; ?></small>
                                    </div>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($review['created_at'])); ?>
                                    <br><small class="text-muted"><?php echo date('H:i', strtotime($review['created_at'])); ?></small>
                                </td>
                                <td>
                                    <?php if ($review['is_approved']): ?>
                                        <span class="badge bg-success">Approved</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-actions">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <?php if (!$review['is_approved']): ?>
                                                <li><a class="dropdown-item text-success" href="?id=<?php echo $review['id']; ?>&approve=1" onclick="return confirm('Setujui review ini?')">
                                                    <i class="fas fa-check me-2"></i>Approve
                                                </a></li>
                                                <li><a class="dropdown-item text-warning" href="?id=<?php echo $review['id']; ?>&reject=1" onclick="return confirm('Tolak review ini?')">
                                                    <i class="fas fa-times me-2"></i>Reject
                                                </a></li>
                                            <?php else: ?>
                                                <li><a class="dropdown-item text-warning" href="?id=<?php echo $review['id']; ?>&reject=1" onclick="return confirm('Batalkan persetujuan review ini?')">
                                                    <i class="fas fa-undo me-2"></i>Unapprove
                                                </a></li>
                                            <?php endif; ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="?id=<?php echo $review['id']; ?>&delete=1" onclick="return confirm('Hapus review ini?')">
                                                <i class="fas fa-trash me-2"></i>Hapus
                                            </a></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>

                            <!-- Modal for full review -->
                            <div class="modal fade" id="reviewModal<?php echo $review['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Review Lengkap</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <strong>Oleh:</strong> <?php echo $review['user_name']; ?><br>
                                                <strong>Kamar:</strong> <?php echo $review['room_number']; ?> (<?php echo $review['type_name']; ?>)<br>
                                                <strong>Tanggal:</strong> <?php echo date('d/m/Y H:i', strtotime($review['created_at'])); ?>
                                            </div>
                                            <div class="mb-3">
                                                <strong>Rating:</strong>
                                                <div class="text-warning">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star<?php echo $i <= $review['overall_rating'] ? '' : '-empty'; ?>"></i>
                                                    <?php endfor; ?>
                                                    (<?php echo $review['overall_rating']; ?>.0)
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <strong>Detail Rating:</strong>
                                                <div class="row small">
                                                    <div class="col-6">Kebersihan: <?php echo $review['rating_cleanliness']; ?>/5</div>
                                                    <div class="col-6">Kenyamanan: <?php echo $review['rating_comfort']; ?>/5</div>
                                                    <div class="col-6">Lokasi: <?php echo $review['rating_location']; ?>/5</div>
                                                    <div class="col-6">Pelayanan: <?php echo $review['rating_service']; ?>/5</div>
                                                    <div class="col-6">Fasilitas: <?php echo $review['rating_facilities']; ?>/5</div>
                                                </div>
                                            </div>
                                            <div>
                                                <strong>Komentar:</strong>
                                                <p class="mt-2"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Rating Summary -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Distribusi Rating</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $rating_distribution = $database->getAll("
                            SELECT overall_rating, COUNT(*) as count 
                            FROM reviews 
                            WHERE is_approved = 1 
                            GROUP BY overall_rating 
                            ORDER BY overall_rating DESC
                        ");
                        
                        $total_approved = $database->getSingle("SELECT COUNT(*) as count FROM reviews WHERE is_approved = 1")['count'];
                        
                        foreach ($rating_distribution as $dist):
                            $percentage = $total_approved > 0 ? ($dist['count'] / $total_approved) * 100 : 0;
                        ?>
                        <div class="row align-items-center mb-2">
                            <div class="col-2">
                                <div class="text-warning">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?php echo $i <= $dist['overall_rating'] ? '' : '-empty'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                            </div>
                            <div class="col-2 text-end">
                                <small><?php echo $dist['count']; ?></small>
                            </div>
                            <div class="col-2 text-end">
                                <small><?php echo number_format($percentage, 1); ?>%</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Rating Rata-rata per Kategori</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $avg_ratings = $database->getSingle("
                            SELECT 
                                AVG(rating_cleanliness) as avg_cleanliness,
                                AVG(rating_comfort) as avg_comfort,
                                AVG(rating_location) as avg_location,
                                AVG(rating_service) as avg_service,
                                AVG(rating_facilities) as avg_facilities
                            FROM reviews 
                            WHERE is_approved = 1
                        ");
                        
                        $categories = [
                            'Kebersihan' => $avg_ratings['avg_cleanliness'] ?? 0,
                            'Kenyamanan' => $avg_ratings['avg_comfort'] ?? 0,
                            'Lokasi' => $avg_ratings['avg_location'] ?? 0,
                            'Pelayanan' => $avg_ratings['avg_service'] ?? 0,
                            'Fasilitas' => $avg_ratings['avg_facilities'] ?? 0
                        ];
                        
                        foreach ($categories as $category => $rating):
                            $percentage = ($rating / 5) * 100;
                        ?>
                        <div class="row align-items-center mb-2">
                            <div class="col-4">
                                <small><?php echo $category; ?></small>
                            </div>
                            <div class="col-5">
                                <div class="progress" style="height: 15px;">
                                    <div class="progress-bar bg-info" style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                            </div>
                            <div class="col-3 text-end">
                                <small><?php echo number_format($rating, 1); ?>/5</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>