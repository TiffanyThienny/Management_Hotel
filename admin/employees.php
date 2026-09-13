<?php
require_once '../config/init.php';
checkAdminAuth();

// Only super admin can access employee management
if (!isAdmin()) {
    setFlashMessage('error', 'Akses ditolak. Manajemen karyawan hanya dapat diakses oleh Administrator.');
    redirect('dashboard.php');
}

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Process form actions
if ($_POST) {
    $data = [
        'employee_code' => sanitizeInput($_POST['employee_code']),
        'full_name' => sanitizeInput($_POST['full_name']),
        'position' => sanitizeInput($_POST['position']),
        'department' => sanitizeInput($_POST['department']),
        'phone' => sanitizeInput($_POST['phone']),
        'email' => sanitizeInput($_POST['email']),
        'address' => sanitizeInput($_POST['address']),
        'hire_date' => sanitizeInput($_POST['hire_date']),
        'salary' => floatval($_POST['salary']),
        'status' => sanitizeInput($_POST['status']),
        'emergency_contact' => sanitizeInput($_POST['emergency_contact'])
    ];

    if ($action == 'create') {
        // Check if employee code already exists
        $existing = $database->getSingle("SELECT id FROM employees WHERE employee_code = ?", [$data['employee_code']]);
        if ($existing) {
            setFlashMessage('error', 'Kode karyawan sudah digunakan');
        } else {
            $result = $database->insert('employees', $data);
            if ($result) {
                setFlashMessage('success', 'Karyawan berhasil ditambahkan');
                redirect('employees.php');
            } else {
                setFlashMessage('error', 'Gagal menambahkan karyawan');
            }
        }
    } elseif ($action == 'edit' && $id > 0) {
        // Check if employee code exists for other employees
        $existing = $database->getSingle("SELECT id FROM employees WHERE employee_code = ? AND id != ?", [$data['employee_code'], $id]);
        if ($existing) {
            setFlashMessage('error', 'Kode karyawan sudah digunakan oleh karyawan lain');
        } else {
            $result = $database->update('employees', $data, "id = $id");
            if ($result) {
                setFlashMessage('success', 'Karyawan berhasil diperbarui');
                redirect('employees.php');
            } else {
                setFlashMessage('error', 'Gagal memperbarui karyawan');
            }
        }
    }
}

// Delete action
if (isset($_GET['delete']) && $id > 0) {
    $result = $database->delete('employees', "id = $id");
    if ($result) {
        setFlashMessage('success', 'Karyawan berhasil dihapus');
    } else {
        setFlashMessage('error', 'Gagal menghapus karyawan');
    }
    redirect('employees.php');
}

// Generate employee code
function generateEmployeeCode() {
    return 'EMP' . date('Ym') . rand(100, 999);
}

$page_title = "Management Karyawan";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/admin-sidebar.php'; ?>

    <!-- Main Content -->
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Management Karyawan</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="?action=create" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Tambah Karyawan
                </a>
            </div>
        </div>

        <?php if ($action == 'list'): ?>
        <!-- Employee Statistics -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Total Karyawan</span>
                        <h4 class="fw-extrabold mb-0 mt-1 text-primary" style="font-family: 'Outfit', sans-serif;">
                            <?php echo $database->getSingle("SELECT COUNT(*) as count FROM employees")['count']; ?>
                        </h4>
                        <small class="text-muted"><i class="fas fa-user-tie me-1 text-primary"></i>Semua Staf</small>
                    </div>
                    <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #e0e7ff; color: #4338ca; width: 50px; height: 50px;">
                        <i class="fas fa-user-tie fs-4"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Karyawan Aktif</span>
                        <h4 class="fw-extrabold mb-0 mt-1 text-success" style="font-family: 'Outfit', sans-serif;">
                            <?php echo $database->getSingle("SELECT COUNT(*) as count FROM employees WHERE status = 'active'")['count']; ?>
                        </h4>
                        <small class="text-muted"><i class="fas fa-check-circle me-1 text-success"></i>Sedang Bertugas</small>
                    </div>
                    <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #d1fae5; color: #059669; width: 50px; height: 50px;">
                        <i class="fas fa-user-check fs-4"></i>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Departemen</span>
                        <h4 class="fw-extrabold mb-0 mt-1 text-warning" style="font-family: 'Outfit', sans-serif;">
                            <?php echo $database->getSingle("SELECT COUNT(DISTINCT department) as count FROM employees")['count']; ?>
                        </h4>
                        <small class="text-muted"><i class="fas fa-sitemap me-1 text-warning"></i>Divisi Operasional</small>
                    </div>
                    <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #fef3c7; color: #d97706; width: 50px; height: 50px;">
                        <i class="fas fa-building fs-4"></i>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Payroll Bulanan</span>
                        <h4 class="fw-extrabold mb-0 mt-1 text-info" style="font-family: 'Outfit', sans-serif;">
                            <?php echo formatCurrency($database->getSingle("SELECT COALESCE(SUM(salary), 0) as total FROM employees WHERE status = 'active'")['total']); ?>
                        </h4>
                        <small class="text-muted"><i class="fas fa-coins me-1 text-info"></i>Total Gaji Aktif</small>
                    </div>
                    <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #e0f2fe; color: #0284c7; width: 50px; height: 50px;">
                        <i class="fas fa-money-bill-wave fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Employees List -->
        <div class="card shadow">
            <div class="card-header">
                <h5 class="card-title mb-0">Daftar Karyawan</h5>
            </div>
            <div class="card-body">
                <!-- Filter Form -->
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Cari Karyawan</label>
                        <input type="text" name="search" class="form-control" 
                               value="<?php echo $_GET['search'] ?? ''; ?>" 
                               placeholder="Nama, kode, atau posisi...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Department</label>
                        <select name="department" class="form-select" onchange="this.form.submit()">
                            <option value="">Semua Department</option>
                            <option value="front_office" <?php echo isset($_GET['department']) && $_GET['department'] == 'front_office' ? 'selected' : ''; ?>>Front Office</option>
                            <option value="housekeeping" <?php echo isset($_GET['department']) && $_GET['department'] == 'housekeeping' ? 'selected' : ''; ?>>Housekeeping</option>
                            <option value="fbs" <?php echo isset($_GET['department']) && $_GET['department'] == 'fbs' ? 'selected' : ''; ?>>F&B Service</option>
                            <option value="management" <?php echo isset($_GET['department']) && $_GET['department'] == 'management' ? 'selected' : ''; ?>>Management</option>
                            <option value="security" <?php echo isset($_GET['department']) && $_GET['department'] == 'security' ? 'selected' : ''; ?>>Security</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Status</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">Semua Status</option>
                            <option value="active" <?php echo isset($_GET['status']) && $_GET['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo isset($_GET['status']) && $_GET['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">&nbsp;</label>
                        <a href="employees.php" class="btn btn-secondary w-100">Reset</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama</th>
                                <th>Posisi</th>
                                <th>Department</th>
                                <th>Kontak</th>
                                <th>Gaji</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Build query with filters
                            $query = "SELECT * FROM employees WHERE 1=1";
                            $params = [];

                            if (isset($_GET['search']) && $_GET['search'] != '') {
                                $search = "%{$_GET['search']}%";
                                $query .= " AND (full_name LIKE ? OR employee_code LIKE ? OR position LIKE ? OR email LIKE ?)";
                                $params[] = $search;
                                $params[] = $search;
                                $params[] = $search;
                                $params[] = $search;
                            }
                            
                            if (isset($_GET['department']) && $_GET['department'] != '') {
                                $query .= " AND department = ?";
                                $params[] = $_GET['department'];
                            }
                            
                            if (isset($_GET['status']) && $_GET['status'] != '') {
                                $query .= " AND status = ?";
                                $params[] = $_GET['status'];
                            }
                            
                            $query .= " ORDER BY department, position";
                            
                            $employees = $database->getAll($query, $params);
                            
                            foreach ($employees as $employee):
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo $employee['employee_code']; ?></strong>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo $employee['full_name']; ?></div>
                                    <small class="text-muted">ID: <?php echo $employee['id']; ?></small>
                                </td>
                                <td><?php echo $employee['position']; ?></td>
                                <td>
                                    <span class="badge 
                                        <?php 
                                        $dept_colors = [
                                            'front_office' => 'bg-primary',
                                            'housekeeping' => 'bg-success', 
                                            'fbs' => 'bg-warning',
                                            'management' => 'bg-info',
                                            'security' => 'bg-secondary'
                                        ];
                                        echo $dept_colors[$employee['department']] ?? 'bg-secondary';
                                        ?>
                                    ">
                                        <?php 
                                        $dept_labels = [
                                            'front_office' => 'Front Office',
                                            'housekeeping' => 'Housekeeping',
                                            'fbs' => 'F&B Service', 
                                            'management' => 'Management',
                                            'security' => 'Security'
                                        ];
                                        echo $dept_labels[$employee['department']] ?? $employee['department'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div><i class="fas fa-phone me-2 text-muted"></i><?php echo $employee['phone']; ?></div>
                                    <div><i class="fas fa-envelope me-2 text-muted"></i><?php echo $employee['email']; ?></div>
                                </td>
                                <td>
                                    <strong><?php echo formatCurrency($employee['salary']); ?></strong>
                                    <br><small class="text-muted">per bulan</small>
                                </td>
                                <td>
                                    <?php if ($employee['status'] == 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                    <br><small class="text-muted">Hire: <?php echo date('M Y', strtotime($employee['hire_date'])); ?></small>
                                </td>
                                <td class="table-actions">
                                    <a href="?action=edit&id=<?php echo $employee['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="GET" style="display: inline;" onsubmit="return confirm('Hapus karyawan ini?')">
                                        <input type="hidden" name="id" value="<?php echo $employee['id']; ?>">
                                        <input type="hidden" name="delete" value="1">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php elseif ($action == 'create' || $action == 'edit'): ?>
        <!-- Employee Form -->
        <?php
        $employee = [];
        if ($action == 'edit' && $id > 0) {
            $employee = $database->getSingle("SELECT * FROM employees WHERE id = ?", [$id]);
            if (!$employee) {
                setFlashMessage('error', 'Karyawan tidak ditemukan');
                redirect('employees.php');
            }
        }
        ?>
        <div class="card shadow">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <?php echo $action == 'create' ? 'Tambah Karyawan' : 'Edit Karyawan'; ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Kode Karyawan *</label>
                                <input type="text" class="form-control" name="employee_code" 
                                       value="<?php echo $employee['employee_code'] ?? generateEmployeeCode(); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nama Lengkap *</label>
                                <input type="text" class="form-control" name="full_name" 
                                       value="<?php echo $employee['full_name'] ?? ''; ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Posisi *</label>
                                <input type="text" class="form-control" name="position" 
                                       value="<?php echo $employee['position'] ?? ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Department *</label>
                                <select class="form-select" name="department" required>
                                    <option value="front_office" <?php echo isset($employee['department']) && $employee['department'] == 'front_office' ? 'selected' : ''; ?>>Front Office</option>
                                    <option value="housekeeping" <?php echo isset($employee['department']) && $employee['department'] == 'housekeeping' ? 'selected' : ''; ?>>Housekeeping</option>
                                    <option value="fbs" <?php echo isset($employee['department']) && $employee['department'] == 'fbs' ? 'selected' : ''; ?>>Food & Beverage Service</option>
                                    <option value="management" <?php echo isset($employee['department']) && $employee['department'] == 'management' ? 'selected' : ''; ?>>Management</option>
                                    <option value="security" <?php echo isset($employee['department']) && $employee['department'] == 'security' ? 'selected' : ''; ?>>Security</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?php echo $employee['email'] ?? ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">No. Telepon *</label>
                                <input type="text" class="form-control" name="phone" 
                                       value="<?php echo $employee['phone'] ?? ''; ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Tanggal Mulai *</label>
                                <input type="date" class="form-control" name="hire_date" 
                                       value="<?php echo $employee['hire_date'] ?? date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Gaji per Bulan *</label>
                                <input type="number" class="form-control" name="salary" 
                                       value="<?php echo $employee['salary'] ?? ''; ?>" min="0" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea class="form-control" name="address" rows="3"><?php echo $employee['address'] ?? ''; ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Kontak Darurat</label>
                                <input type="text" class="form-control" name="emergency_contact" 
                                       value="<?php echo $employee['emergency_contact'] ?? ''; ?>" placeholder="Nama dan nomor telepon...">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" required>
                                    <option value="active" <?php echo isset($employee['status']) && $employee['status'] == 'active' ? 'selected' : 'selected'; ?>>Active</option>
                                    <option value="inactive" <?php echo isset($employee['status']) && $employee['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="suspended" <?php echo isset($employee['status']) && $employee['status'] == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Simpan
                        </button>
                        <a href="employees.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>