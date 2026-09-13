<?php
require_once '../config/init.php';
require_once '../config/database.php';
checkAdminAuth();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Restrict receptionist to view & take orders only
if (isReceptionist() && ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['delete']) || (isset($_GET['action']) && in_array($_GET['action'], ['create', 'edit'])))) {
    setFlashMessage('error', 'Resepsionis hanya dapat melihat daftar layanan dan melayani pesanan tamu.');
    redirect('services.php');
}

// Process form actions (Create & Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $service_name = trim(sanitizeInput($_POST['service_name'] ?? ''));
    $description = trim(sanitizeInput($_POST['description'] ?? ''));
    $price = floatval($_POST['price'] ?? 0);
    $category = sanitizeInput($_POST['category'] ?? 'other');
    $is_available = isset($_POST['is_available']) ? 1 : 0;

    if (empty($service_name)) {
        setFlashMessage('error', 'Nama layanan wajib diisi.');
    } elseif ($price < 0) {
        setFlashMessage('error', 'Harga layanan tidak boleh negatif.');
    } else {
        $data = [
            'service_name' => $service_name,
            'description' => $description,
            'price' => $price,
            'category' => $category,
            'is_available' => $is_available
        ];

        if ($action == 'create') {
            $result = $database->insert('services', $data);
            if ($result) {
                setFlashMessage('success', 'Layanan berhasil ditambahkan.');
                redirect('services.php');
            } else {
                setFlashMessage('error', 'Gagal menambahkan layanan.');
            }
        } elseif ($action == 'edit' && $id > 0) {
            $result = $database->update('services', $data, "id = $id");
            if ($result !== false) {
                setFlashMessage('success', 'Layanan berhasil diperbarui.');
                redirect('services.php');
            } else {
                setFlashMessage('error', 'Gagal memperbarui layanan.');
            }
        }
    }
}

// Delete action - Allow deleting ALL services safely
if (isset($_GET['delete']) && $id > 0) {
    try {
        $db->beginTransaction();
        // Delete related service orders first
        $database->delete('service_orders', "service_id = $id");
        // Delete the service
        $result = $database->delete('services', "id = $id");
        $db->commit();

        if ($result) {
            setFlashMessage('success', 'Layanan beserta riwayat pesanan terkait berhasil dihapus.');
        } else {
            setFlashMessage('error', 'Gagal menghapus layanan.');
        }
    } catch (Exception $e) {
        $db->rollBack();
        setFlashMessage('error', 'Gagal menghapus layanan: ' . $e->getMessage());
    }
    redirect('services.php');
}

$page_title = "Management Services";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Navigation -->
        <?php include '../includes/admin-sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-3 mb-4 border-bottom">
                <div>
                    <h2 class="fw-bold mb-1"><?php echo isReceptionist() ? 'Katalog Layanan & Room Service' : (isOwner() ? 'Layanan & Fasilitas Hotel' : 'Management Services & Layanan Hotel'); ?></h2>
                    <p class="text-muted small mb-0">Daftar fasilitas berbayar, makanan, spa, laundry, dan transportasi hotel.</p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <?php if (isAdmin()): ?>
                        <?php if ($action != 'create'): ?>
                            <a href="?action=create" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold shadow-sm">
                                <i class="fas fa-plus me-1"></i> Tambah Service Baru
                            </a>
                        <?php else: ?>
                            <a href="services.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                                <i class="fas fa-arrow-left me-1"></i> Kembali ke Daftar
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($action == 'list'): ?>
            <!-- Services List -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-bold text-dark"><i class="fas fa-concierge-bell me-2 text-primary"></i>Daftar Layanan Hotel</h5>
                </div>
                <div class="card-body p-4">
                    <!-- Service Statistics Cards (Redesigned) -->
                    <div class="row g-3 mb-4">
                        <div class="col-xl-3 col-md-6">
                            <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                                <div>
                                    <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Total Layanan</span>
                                    <h4 class="fw-extrabold mb-0 mt-1 text-dark" style="font-family: 'Outfit', sans-serif;">
                                        <?php echo $database->getSingle("SELECT COUNT(*) as count FROM services")['count']; ?>
                                    </h4>
                                    <small class="text-muted"><i class="fas fa-concierge-bell me-1 text-primary"></i>Semua Kategori</small>
                                </div>
                                <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #e0e7ff; color: #4338ca; width: 50px; height: 50px;">
                                    <i class="fas fa-concierge-bell fs-4"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6">
                            <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                                <div>
                                    <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Layanan Aktif</span>
                                    <h4 class="fw-extrabold mb-0 mt-1 text-success" style="font-family: 'Outfit', sans-serif;">
                                        <?php echo $database->getSingle("SELECT COUNT(*) as count FROM services WHERE is_available = 1")['count']; ?>
                                    </h4>
                                    <small class="text-muted"><i class="fas fa-check-circle me-1 text-success"></i>Siap Dipesan</small>
                                </div>
                                <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #d1fae5; color: #059669; width: 50px; height: 50px;">
                                    <i class="fas fa-check-circle fs-4"></i>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                                <div>
                                    <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Total Pesanan</span>
                                    <h4 class="fw-extrabold mb-0 mt-1 text-info" style="font-family: 'Outfit', sans-serif;">
                                        <?php echo $database->getSingle("SELECT COUNT(*) as count FROM service_orders")['count']; ?>
                                    </h4>
                                    <small class="text-muted"><i class="fas fa-shopping-cart me-1 text-info"></i>Pesanan Masuk</small>
                                </div>
                                <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #e0f2fe; color: #0284c7; width: 50px; height: 50px;">
                                    <i class="fas fa-shopping-cart fs-4"></i>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                                <div>
                                    <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Pendapatan Layanan</span>
                                    <h4 class="fw-extrabold mb-0 mt-1 text-warning" style="font-family: 'Outfit', sans-serif;">
                                        <?php echo formatCurrency($database->getSingle("SELECT COALESCE(SUM(total_price), 0) as total FROM service_orders")['total']); ?>
                                    </h4>
                                    <small class="text-muted"><i class="fas fa-coins me-1 text-warning"></i>Total Transaksi</small>
                                </div>
                                <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #fef3c7; color: #d97706; width: 50px; height: 50px;">
                                    <i class="fas fa-coins fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover datatable align-middle">
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th>Kategori</th>
                                    <th>Harga</th>
                                    <th>Status</th>
                                    <th>Total Pesanan</th>
                                    <th>Pendapatan</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $query = "SELECT s.*, 
                                         (SELECT COUNT(*) FROM service_orders WHERE service_id = s.id) as order_count,
                                         (SELECT COALESCE(SUM(total_price), 0) FROM service_orders WHERE service_id = s.id) as total_revenue
                                          FROM services s
                                          ORDER BY s.category, s.service_name";
                                $services = $database->getAll($query);
                                
                                foreach ($services as $service):
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($service['service_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($service['description'] ?? ''); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge 
                                            <?php 
                                            $category_colors = [
                                                'food' => 'bg-danger text-white',
                                                'laundry' => 'bg-primary text-white',
                                                'transport' => 'bg-success text-white',
                                                'spa' => 'bg-warning text-dark',
                                                'other' => 'bg-secondary text-white'
                                            ];
                                            echo $category_colors[$service['category']] ?? 'bg-secondary text-white';
                                            ?> px-2 py-1 rounded-pill" style="font-size: 0.72rem;">
                                            <?php 
                                            $category_labels = [
                                                'food' => 'Food & Beverage',
                                                'laundry' => 'Laundry',
                                                'transport' => 'Transport',
                                                'spa' => 'Spa & Wellness',
                                                'other' => 'Other'
                                            ];
                                            echo $category_labels[$service['category']] ?? ucfirst($service['category']);
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong class="text-dark"><?php echo formatCurrency($service['price']); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($service['is_available']): ?>
                                            <span class="badge bg-success px-2 py-1 rounded-pill" style="font-size: 0.72rem;">Available</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger px-2 py-1 rounded-pill" style="font-size: 0.72rem;">Not Available</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?php echo $service['order_count']; ?> pesanan</span>
                                    </td>
                                    <td>
                                        <strong class="text-success"><?php echo formatCurrency($service['total_revenue']); ?></strong>
                                    </td>
                                    <td class="table-actions text-center">
                                        <div class="d-flex gap-1 justify-content-center">
                                            <?php if (isAdmin()): ?>
                                                <!-- EDIT BUTTON FOR ALL SERVICES -->
                                                <a href="?action=edit&id=<?php echo $service['id']; ?>" class="btn btn-sm btn-warning" title="Edit Service">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <!-- DELETE BUTTON FOR ALL SERVICES -->
                                                <a href="?delete=1&id=<?php echo $service['id']; ?>" class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Apakah Anda yakin ingin menghapus layanan \'<?php echo addslashes($service['service_name']); ?>\'?')" title="Hapus Service">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php elseif (isReceptionist()): ?>
                                                <a href="service_orders.php?action=create&service_id=<?php echo $service['id']; ?>" class="btn btn-sm btn-primary rounded-pill px-3">
                                                    <i class="fas fa-cart-plus me-1"></i> Pesan
                                                </a>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border">Katalog</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Service Orders -->
            <div class="card border-0 shadow-sm rounded-4 mt-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-bold text-dark"><i class="fas fa-history me-2 text-info"></i>Pesanan Service Terbaru</h5>
                    <a href="service_orders.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">Lihat Semua Pesanan</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Order ID</th>
                                    <th>Service</th>
                                    <th>Booking</th>
                                    <th>Jumlah</th>
                                    <th>Total Biaya</th>
                                    <th>Status</th>
                                    <th class="pe-4 text-end">Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $query = "SELECT so.*, s.service_name, b.booking_code, c.full_name
                                          FROM service_orders so
                                          JOIN services s ON so.service_id = s.id
                                          JOIN bookings b ON so.booking_id = b.id
                                          JOIN customers c ON b.customer_id = c.id
                                          ORDER BY so.created_at DESC
                                          LIMIT 10";
                                $orders = $database->getAll($query);
                                
                                if (empty($orders)):
                                ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">Belum ada pesanan layanan.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-primary">#<?php echo $order['id']; ?></td>
                                        <td class="fw-semibold text-dark"><?php echo htmlspecialchars($order['service_name']); ?></td>
                                        <td>
                                            <a href="booking_detail.php?id=<?php echo $order['booking_id']; ?>" class="text-decoration-none fw-bold small">
                                                <?php echo htmlspecialchars($order['booking_code']); ?>
                                            </a>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($order['full_name']); ?></small>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?php echo $order['quantity']; ?>x</span></td>
                                        <td class="fw-bold text-dark"><?php echo formatCurrency($order['total_price']); ?></td>
                                        <td>
                                            <span class="badge 
                                                <?php 
                                                $status_colors = [
                                                    'pending' => 'bg-warning text-dark',
                                                    'processing' => 'bg-info text-white',
                                                    'completed' => 'bg-success text-white',
                                                    'cancelled' => 'bg-danger text-white'
                                                ];
                                                echo $status_colors[$order['status']] ?? 'bg-secondary text-white';
                                                ?> px-2 py-1 rounded-pill" style="font-size: 0.72rem;">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td class="pe-4 text-end text-muted small">
                                            <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php elseif ($action == 'create' || $action == 'edit'): ?>
            <!-- Service Form -->
            <?php
            $service = [];
            if ($action == 'edit' && $id > 0) {
                $service = $database->getSingle("SELECT * FROM services WHERE id = ?", [$id]);
                if (!$service) {
                    setFlashMessage('error', 'Service tidak ditemukan.');
                    redirect('services.php');
                }
            }
            ?>
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <i class="fas <?php echo $action == 'create' ? 'fa-plus-circle text-primary' : 'fa-edit text-warning'; ?> me-2"></i>
                        <?php echo $action == 'create' ? 'Tambah Service Baru' : 'Edit Service: ' . htmlspecialchars($service['service_name'] ?? ''); ?>
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="services.php?action=<?php echo $action; ?><?php echo $id > 0 ? '&id=' . $id : ''; ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Nama Service *</label>
                                    <input type="text" class="form-control" name="service_name" 
                                           value="<?php echo htmlspecialchars($service['service_name'] ?? ''); ?>" placeholder="Contoh: Airport Transfer - Car" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Kategori *</label>
                                    <select class="form-select" name="category" required>
                                        <option value="food" <?php echo isset($service['category']) && $service['category'] == 'food' ? 'selected' : ''; ?>>Food & Beverage</option>
                                        <option value="laundry" <?php echo isset($service['category']) && $service['category'] == 'laundry' ? 'selected' : ''; ?>>Laundry</option>
                                        <option value="transport" <?php echo isset($service['category']) && $service['category'] == 'transport' ? 'selected' : ''; ?>>Transport</option>
                                        <option value="spa" <?php echo isset($service['category']) && $service['category'] == 'spa' ? 'selected' : ''; ?>>Spa & Wellness</option>
                                        <option value="other" <?php echo isset($service['category']) && $service['category'] == 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Harga (Rp) *</label>
                                    <input type="number" class="form-control" name="price" 
                                           value="<?php echo htmlspecialchars($service['price'] ?? ''); ?>" min="0" placeholder="Contoh: 150000" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Deskripsi Layanan</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Jelaskan detail fasilitas/layanan ini..."><?php echo htmlspecialchars($service['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_available" id="is_available" 
                                       <?php echo (!isset($service['is_available']) || $service['is_available'] == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="is_available">
                                    Service ini aktif & tersedia untuk dipesan oleh tamu
                                </label>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                                <i class="fas fa-save me-1"></i> <?php echo $action == 'create' ? 'Simpan Service' : 'Perbarui Service'; ?>
                            </button>
                            <a href="services.php" class="btn btn-secondary px-4">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>