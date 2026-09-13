<?php
require_once '../config/init.php';
checkAdminAuth();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Process form actions
if ($_POST) {
    $data = [
        'full_name' => sanitizeInput($_POST['full_name']),
        'email' => sanitizeInput($_POST['email']),
        'phone' => sanitizeInput($_POST['phone']),
        'identity_type' => sanitizeInput($_POST['identity_type']),
        'identity_number' => sanitizeInput($_POST['identity_number']),
        'address' => sanitizeInput($_POST['address']),
        'country' => sanitizeInput($_POST['country']),
        'customer_type' => sanitizeInput($_POST['customer_type'])
    ];

    if ($action == 'create') {
        // Check if email already exists
        $existing = $database->getSingle("SELECT id FROM customers WHERE email = ?", [$data['email']]);
        if ($existing) {
            setFlashMessage('error', 'Email sudah terdaftar');
        } else {
            $result = $database->insert('customers', $data);
            if ($result) {
                setFlashMessage('success', 'Customer berhasil ditambahkan');
                redirect('customers.php');
            } else {
                setFlashMessage('error', 'Gagal menambahkan customer');
            }
        }
    } elseif ($action == 'edit' && $id > 0) {
        // Check if email exists for other customers
        $existing = $database->getSingle("SELECT id FROM customers WHERE email = ? AND id != ?", [$data['email'], $id]);
        if ($existing) {
            setFlashMessage('error', 'Email sudah digunakan oleh customer lain');
        } else {
            $result = $database->update('customers', $data, "id = $id");
            if ($result) {
                setFlashMessage('success', 'Customer berhasil diperbarui');
                redirect('customers.php');
            } else {
                setFlashMessage('error', 'Gagal memperbarui customer');
            }
        }
    }
}

// Delete action
if (isset($_GET['delete']) && $id > 0) {
    if (isReceptionist()) {
        setFlashMessage('error', 'Resepsionis tidak memiliki izin menghapus data customer.');
        redirect('customers.php');
    }
    // Check if customer has bookings
    $bookings = $database->getSingle("SELECT COUNT(*) as count FROM bookings WHERE customer_id = ?", [$id]);
    if ($bookings['count'] > 0) {
        setFlashMessage('error', 'Tidak dapat menghapus customer yang memiliki booking');
    } else {
        $result = $database->delete('customers', "id = $id");
        if ($result) {
            setFlashMessage('success', 'Customer berhasil dihapus');
        } else {
            setFlashMessage('error', 'Gagal menghapus customer');
        }
    }
    redirect('customers.php');
}

$page_title = "Management Customer";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/admin-sidebar.php'; ?>

    <!-- Main Content -->
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Management Customer</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="?action=create" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Tambah Customer
                </a>
            </div>
        </div>

        <?php if ($action == 'list'): ?>
        <!-- Customer Statistics -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Total Pelanggan</span>
                        <h4 class="fw-extrabold mb-0 mt-1 text-primary" style="font-family: 'Outfit', sans-serif;">
                            <?php echo $database->getSingle("SELECT COUNT(*) as count FROM customers")['count']; ?>
                        </h4>
                        <small class="text-muted"><i class="fas fa-users me-1 text-primary"></i>Semua Customer</small>
                    </div>
                    <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #e0e7ff; color: #4338ca; width: 50px; height: 50px;">
                        <i class="fas fa-users fs-4"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Member Regular</span>
                        <h4 class="fw-extrabold mb-0 mt-1 text-success" style="font-family: 'Outfit', sans-serif;">
                            <?php echo $database->getSingle("SELECT COUNT(*) as count FROM customers WHERE customer_type = 'regular'")['count']; ?>
                        </h4>
                        <small class="text-muted"><i class="fas fa-user-check me-1 text-success"></i>Tamu Standar</small>
                    </div>
                    <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #d1fae5; color: #059669; width: 50px; height: 50px;">
                        <i class="fas fa-user-check fs-4"></i>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Member VIP</span>
                        <h4 class="fw-extrabold mb-0 mt-1 text-warning" style="font-family: 'Outfit', sans-serif;">
                            <?php echo $database->getSingle("SELECT COUNT(*) as count FROM customers WHERE customer_type = 'vip'")['count']; ?>
                        </h4>
                        <small class="text-muted"><i class="fas fa-crown me-1 text-warning"></i>Prioritas Utama</small>
                    </div>
                    <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #fef3c7; color: #d97706; width: 50px; height: 50px;">
                        <i class="fas fa-crown fs-4"></i>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Member Corporate</span>
                        <h4 class="fw-extrabold mb-0 mt-1 text-info" style="font-family: 'Outfit', sans-serif;">
                            <?php echo $database->getSingle("SELECT COUNT(*) as count FROM customers WHERE customer_type = 'corporate'")['count']; ?>
                        </h4>
                        <small class="text-muted"><i class="fas fa-building me-1 text-info"></i>Mitra Perusahaan</small>
                    </div>
                    <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #e0f2fe; color: #0284c7; width: 50px; height: 50px;">
                        <i class="fas fa-building fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer List -->
        <div class="card shadow">
            <div class="card-header">
                <h5 class="card-title mb-0">Daftar Customer</h5>
            </div>
            <div class="card-body">
                <!-- Filter Form -->
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Cari</label>
                        <input type="text" name="search" class="form-control" 
                               value="<?php echo $_GET['search'] ?? ''; ?>" 
                               placeholder="Nama, email, atau telepon...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Tipe Customer</label>
                        <select name="type" class="form-select" onchange="this.form.submit()">
                            <option value="">Semua Tipe</option>
                            <option value="regular" <?php echo isset($_GET['type']) && $_GET['type'] == 'regular' ? 'selected' : ''; ?>>Regular</option>
                            <option value="vip" <?php echo isset($_GET['type']) && $_GET['type'] == 'vip' ? 'selected' : ''; ?>>VIP</option>
                            <option value="corporate" <?php echo isset($_GET['type']) && $_GET['type'] == 'corporate' ? 'selected' : ''; ?>>Corporate</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">&nbsp;</label>
                        <a href="customers.php" class="btn btn-secondary w-100">Reset</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Kontak</th>
                                <th>Identitas</th>
                                <th>Tipe</th>
                                <th>Total Booking</th>
                                <th>Terdaftar</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Build query with filters
                            $query = "SELECT c.*, 
                                     (SELECT COUNT(*) FROM bookings WHERE customer_id = c.id) as total_bookings
                                      FROM customers c 
                                      WHERE 1=1";
                            
                            $params = [];
                            
                            if (isset($_GET['search']) && $_GET['search'] != '') {
                                $search = "%{$_GET['search']}%";
                                $query .= " AND (c.full_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
                                $params[] = $search;
                                $params[] = $search;
                                $params[] = $search;
                            }
                            
                            if (isset($_GET['type']) && $_GET['type'] != '') {
                                $query .= " AND c.customer_type = ?";
                                $params[] = $_GET['type'];
                            }
                            
                            $query .= " ORDER BY c.created_at DESC";
                            
                            $customers = $database->getAll($query, $params);
                            
                            foreach ($customers as $customer):
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?php echo $customer['full_name']; ?></div>
                                    <?php if ($customer['customer_type'] == 'vip'): ?>
                                        <span class="badge bg-warning">VIP</span>
                                    <?php elseif ($customer['customer_type'] == 'corporate'): ?>
                                        <span class="badge bg-info">Corporate</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><i class="fas fa-envelope me-2 text-muted"></i><?php echo $customer['email']; ?></div>
                                    <div><i class="fas fa-phone me-2 text-muted"></i><?php echo $customer['phone']; ?></div>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo strtoupper($customer['identity_type']); ?></small><br>
                                    <small><?php echo $customer['identity_number']; ?></small>
                                </td>
                                <td>
                                    <span class="badge 
                                        <?php echo $customer['customer_type'] == 'vip' ? 'bg-warning' : 
                                              ($customer['customer_type'] == 'corporate' ? 'bg-info' : 'bg-secondary'); ?>">
                                        <?php echo ucfirst($customer['customer_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $customer['total_bookings']; ?> booking</span>
                                </td>
                                <td>
                                    <small><?php echo date('d/m/Y', strtotime($customer['created_at'])); ?></small>
                                </td>
                                <td class="table-actions">
                                    <a href="?action=edit&id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="customer_detail.php?id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-info" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($customer['total_bookings'] == 0): ?>
                                    <form method="GET" style="display: inline;" onsubmit="return confirm('Hapus customer ini?')">
                                        <input type="hidden" name="id" value="<?php echo $customer['id']; ?>">
                                        <input type="hidden" name="delete" value="1">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php elseif ($action == 'create' || $action == 'edit'): ?>
        <!-- Customer Form -->
        <?php
        $customer = [];
        if ($action == 'edit' && $id > 0) {
            $customer = $database->getSingle("SELECT * FROM customers WHERE id = ?", [$id]);
            if (!$customer) {
                setFlashMessage('error', 'Customer tidak ditemukan');
                redirect('customers.php');
            }
        }
        ?>
        <div class="card shadow">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <?php echo $action == 'create' ? 'Tambah Customer' : 'Edit Customer'; ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nama Lengkap *</label>
                                <input type="text" class="form-control" name="full_name" 
                                       value="<?php echo $customer['full_name'] ?? ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?php echo $customer['email'] ?? ''; ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">No. Telepon *</label>
                                <input type="text" class="form-control" name="phone" 
                                       value="<?php echo $customer['phone'] ?? ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Tipe Identitas</label>
                                <select class="form-select" name="identity_type">
                                    <option value="ktp" <?php echo isset($customer['identity_type']) && $customer['identity_type'] == 'ktp' ? 'selected' : ''; ?>>KTP</option>
                                    <option value="passport" <?php echo isset($customer['identity_type']) && $customer['identity_type'] == 'passport' ? 'selected' : ''; ?>>Passport</option>
                                    <option value="sim" <?php echo isset($customer['identity_type']) && $customer['identity_type'] == 'sim' ? 'selected' : ''; ?>>SIM</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">No. Identitas</label>
                                <input type="text" class="form-control" name="identity_number" 
                                       value="<?php echo $customer['identity_number'] ?? ''; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Alamat</label>
                                <textarea class="form-control" name="address" rows="3"><?php echo $customer['address'] ?? ''; ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Negara</label>
                                <input type="text" class="form-control" name="country" 
                                       value="<?php echo $customer['country'] ?? 'Indonesia'; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Tipe Customer</label>
                                <select class="form-select" name="customer_type">
                                    <option value="regular" <?php echo isset($customer['customer_type']) && $customer['customer_type'] == 'regular' ? 'selected' : ''; ?>>Regular</option>
                                    <option value="vip" <?php echo isset($customer['customer_type']) && $customer['customer_type'] == 'vip' ? 'selected' : ''; ?>>VIP</option>
                                    <option value="corporate" <?php echo isset($customer['customer_type']) && $customer['customer_type'] == 'corporate' ? 'selected' : ''; ?>>Corporate</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Simpan
                        </button>
                        <a href="customers.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>