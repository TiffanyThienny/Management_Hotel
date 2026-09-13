<?php
require_once '../config/init.php';
checkAdminAuth();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Process maintenance actions
if ($_POST) {
    $data = [
        'room_id' => intval($_POST['room_id']),
        'issue_type' => sanitizeInput($_POST['issue_type']),
        'description' => sanitizeInput($_POST['description']),
        'reported_by' => $_SESSION['user_id'],
        'status' => sanitizeInput($_POST['status']),
        'priority' => sanitizeInput($_POST['priority']),
        'cost_estimate' => floatval($_POST['cost_estimate']),
        'actual_cost' => floatval($_POST['actual_cost'])
    ];

    if ($action == 'create') {
        $result = $database->insert('maintenance', $data);
        if ($result) {
            // Update room status to maintenance
            $database->update('rooms', ['status' => 'maintenance'], "id = {$data['room_id']}");
            setFlashMessage('success', 'Maintenance request berhasil dibuat');
            redirect('maintenance.php');
        } else {
            setFlashMessage('error', 'Gagal membuat maintenance request');
        }
    } elseif ($action == 'edit' && $id > 0) {
        $result = $database->update('maintenance', $data, "id = $id");
        if ($result) {
            setFlashMessage('success', 'Maintenance request berhasil diperbarui');
            redirect('maintenance.php');
        } else {
            setFlashMessage('error', 'Gagal memperbarui maintenance request');
        }
    }
}

// Update status action
if (isset($_GET['update_status']) && $id > 0) {
    $new_status = sanitizeInput($_GET['update_status']);
    $update_data = ['status' => $new_status];
    
    if ($new_status == 'completed') {
        $update_data['completed_date'] = date('Y-m-d H:i:s');
        
        // Get room_id from maintenance
        $maintenance = $database->getSingle("SELECT room_id FROM maintenance WHERE id = ?", [$id]);
        if ($maintenance) {
            // Update room status back to available
            $database->update('rooms', ['status' => 'available'], "id = {$maintenance['room_id']}");
        }
    }
    
    $result = $database->update('maintenance', $update_data, "id = $id");
    
    if ($result) {
        setFlashMessage('success', 'Status maintenance berhasil diperbarui');
    } else {
        setFlashMessage('error', 'Gagal memperbarui status maintenance');
    }
    redirect('maintenance.php');
}

// Delete action
if (isset($_GET['delete']) && $id > 0) {
    if (isReceptionist()) {
        setFlashMessage('error', 'Resepsionis tidak memiliki izin menghapus data perbaikan.');
        redirect('maintenance.php');
    }
    $result = $database->delete('maintenance', "id = $id");
    if ($result) {
        setFlashMessage('success', 'Maintenance request berhasil dihapus');
    } else {
        setFlashMessage('error', 'Gagal menghapus maintenance request');
    }
    redirect('maintenance.php');
}

$page_title = "Maintenance Management";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/admin-sidebar.php'; ?>

    <!-- Main Content -->
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Maintenance Management</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="?action=create" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Buat Request
                </a>
            </div>
        </div>

        <?php if ($action == 'list'): ?>
        <!-- Maintenance Statistics -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Laporan Baru</span>
                        <h4 class="fw-extrabold mb-0 mt-1 text-primary" style="font-family: 'Outfit', sans-serif;">
                            <?php echo $database->getSingle("SELECT COUNT(*) as count FROM maintenance WHERE status = 'reported'")['count']; ?>
                        </h4>
                        <small class="text-muted"><i class="fas fa-clipboard-list me-1 text-primary"></i>Perlu Tindakan</small>
                    </div>
                    <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #e0e7ff; color: #4338ca; width: 50px; height: 50px;">
                        <i class="fas fa-tools fs-4"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Sedang Dikerjakan</span>
                        <h4 class="fw-extrabold mb-0 mt-1 text-warning" style="font-family: 'Outfit', sans-serif;">
                            <?php echo $database->getSingle("SELECT COUNT(*) as count FROM maintenance WHERE status = 'in_progress'")['count']; ?>
                        </h4>
                        <small class="text-muted"><i class="fas fa-spinner me-1 text-warning"></i>Dalam Perbaikan</small>
                    </div>
                    <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #fef3c7; color: #d97706; width: 50px; height: 50px;">
                        <i class="fas fa-wrench fs-4"></i>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Perbaikan Selesai</span>
                        <h4 class="fw-extrabold mb-0 mt-1 text-success" style="font-family: 'Outfit', sans-serif;">
                            <?php echo $database->getSingle("SELECT COUNT(*) as count FROM maintenance WHERE status = 'completed'")['count']; ?>
                        </h4>
                        <small class="text-muted"><i class="fas fa-check-circle me-1 text-success"></i>Siap Digunakan</small>
                    </div>
                    <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #d1fae5; color: #059669; width: 50px; height: 50px;">
                        <i class="fas fa-check-circle fs-4"></i>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Total Biaya Maintenance</span>
                        <h4 class="fw-extrabold mb-0 mt-1 text-danger" style="font-family: 'Outfit', sans-serif;">
                            <?php echo formatCurrency($database->getSingle("SELECT COALESCE(SUM(actual_cost), 0) as total FROM maintenance WHERE status = 'completed'")['total']); ?>
                        </h4>
                        <small class="text-muted"><i class="fas fa-receipt me-1 text-danger"></i>Pengeluaran Perbaikan</small>
                    </div>
                    <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #fee2e2; color: #dc2626; width: 50px; height: 50px;">
                        <i class="fas fa-coins fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Maintenance Requests List -->
        <div class="card shadow">
            <div class="card-header">
                <h5 class="card-title mb-0">Daftar Maintenance Request</h5>
            </div>
            <div class="card-body">
                <!-- Filter Form -->
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Status</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">Semua Status</option>
                            <option value="reported" <?php echo isset($_GET['status']) && $_GET['status'] == 'reported' ? 'selected' : ''; ?>>Reported</option>
                            <option value="in_progress" <?php echo isset($_GET['status']) && $_GET['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo isset($_GET['status']) && $_GET['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Priority</label>
                        <select name="priority" class="form-select" onchange="this.form.submit()">
                            <option value="">Semua Priority</option>
                            <option value="low" <?php echo isset($_GET['priority']) && $_GET['priority'] == 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo isset($_GET['priority']) && $_GET['priority'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo isset($_GET['priority']) && $_GET['priority'] == 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="urgent" <?php echo isset($_GET['priority']) && $_GET['priority'] == 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Dari Tanggal</label>
                        <input type="date" name="start_date" class="form-control datepicker" 
                               value="<?php echo $_GET['start_date'] ?? ''; ?>" onchange="this.form.submit()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">&nbsp;</label>
                        <a href="maintenance.php" class="btn btn-secondary w-100">Reset</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable">
                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Kamar</th>
                                <th>Issue</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Cost</th>
                                <th>Tanggal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Build query with filters
                            $query = "SELECT m.*, r.room_number, rt.type_name, u.full_name as reported_by_name
                                      FROM maintenance m
                                      JOIN rooms r ON m.room_id = r.id
                                      JOIN room_types rt ON r.room_type_id = rt.id
                                      JOIN users u ON m.reported_by = u.id
                                      WHERE 1=1";
                            
                            $params = [];
                            
                            if (isset($_GET['status']) && $_GET['status'] != '') {
                                $query .= " AND m.status = ?";
                                $params[] = $_GET['status'];
                            }
                            
                            if (isset($_GET['priority']) && $_GET['priority'] != '') {
                                $query .= " AND m.priority = ?";
                                $params[] = $_GET['priority'];
                            }
                            
                            if (isset($_GET['start_date']) && $_GET['start_date'] != '') {
                                $query .= " AND DATE(m.created_at) >= ?";
                                $params[] = $_GET['start_date'];
                            }
                            
                            $query .= " ORDER BY 
                                CASE m.priority 
                                    WHEN 'urgent' THEN 1
                                    WHEN 'high' THEN 2
                                    WHEN 'medium' THEN 3
                                    WHEN 'low' THEN 4
                                END,
                                m.created_at DESC";
                            
                            $maintenance_requests = $database->getAll($query, $params);
                            
                            foreach ($maintenance_requests as $request):
                            ?>
                            <tr>
                                <td>
                                    <strong>#<?php echo $request['id']; ?></strong>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo $request['room_number']; ?></div>
                                    <small class="text-muted"><?php echo $request['type_name']; ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo $request['issue_type']; ?></div>
                                    <small class="text-muted">
                                        <?php 
                                        if (strlen($request['description']) > 50) {
                                            echo substr($request['description'], 0, 50) . '...';
                                        } else {
                                            echo $request['description'];
                                        }
                                        ?>
                                    </small>
                                    <br>
                                    <small class="text-muted">Reported by: <?php echo $request['reported_by_name']; ?></small>
                                </td>
                                <td>
                                    <?php 
                                    $priority_colors = [
                                        'urgent' => 'danger',
                                        'high' => 'warning',
                                        'medium' => 'info',
                                        'low' => 'secondary'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $priority_colors[$request['priority']] ?? 'secondary'; ?>">
                                        <?php echo ucfirst($request['priority']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $status_colors = [
                                        'reported' => 'primary',
                                        'in_progress' => 'warning',
                                        'completed' => 'success'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $status_colors[$request['status']] ?? 'secondary'; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
                                    </span>
                                    <?php if ($request['completed_date']): ?>
                                        <br><small class="text-muted">Completed: <?php echo date('d/m/Y', strtotime($request['completed_date'])); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($request['actual_cost'] > 0): ?>
                                        <strong><?php echo formatCurrency($request['actual_cost']); ?></strong>
                                        <?php if ($request['cost_estimate'] > 0): ?>
                                            <br><small class="text-muted">Est: <?php echo formatCurrency($request['cost_estimate']); ?></small>
                                        <?php endif; ?>
                                    <?php elseif ($request['cost_estimate'] > 0): ?>
                                        <small>Est: <?php echo formatCurrency($request['cost_estimate']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?php echo date('d/m/Y', strtotime($request['created_at'])); ?></small>
                                    <br><small class="text-muted"><?php echo date('H:i', strtotime($request['created_at'])); ?></small>
                                </td>
                                <td class="table-actions">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item" href="?action=edit&id=<?php echo $request['id']; ?>">
                                                    <i class="fas fa-edit me-2"></i>Edit
                                                </a>
                                            </li>
                                            <?php if ($request['status'] != 'completed'): ?>
                                                <?php if ($request['status'] == 'reported'): ?>
                                                    <li>
                                                        <a class="dropdown-item text-warning" href="?id=<?php echo $request['id']; ?>&update_status=in_progress" 
                                                           onclick="return confirm('Mulai proses maintenance?')">
                                                            <i class="fas fa-play me-2"></i>Start Progress
                                                        </a>
                                                    </li>
                                                <?php elseif ($request['status'] == 'in_progress'): ?>
                                                    <li>
                                                        <a class="dropdown-item text-success" href="?id=<?php echo $request['id']; ?>&update_status=completed" 
                                                           onclick="return confirm('Selesaikan maintenance?')">
                                                            <i class="fas fa-check me-2"></i>Mark as Completed
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item text-danger" href="?id=<?php echo $request['id']; ?>&delete=1" 
                                                   onclick="return confirm('Hapus maintenance request?')">
                                                    <i class="fas fa-trash me-2"></i>Delete
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php elseif ($action == 'create' || $action == 'edit'): ?>
        <!-- Maintenance Form -->
        <?php
        $maintenance = [];
        if ($action == 'edit' && $id > 0) {
            $maintenance = $database->getSingle("SELECT * FROM maintenance WHERE id = ?", [$id]);
            if (!$maintenance) {
                setFlashMessage('error', 'Maintenance request tidak ditemukan');
                redirect('maintenance.php');
            }
        }

        // Get available rooms (rooms that are not in maintenance)
        $rooms = $database->getAll("
            SELECT r.id, r.room_number, rt.type_name 
            FROM rooms r 
            JOIN room_types rt ON r.room_type_id = rt.id 
            WHERE r.status != 'maintenance' OR r.id = ?
            ORDER BY r.room_number
        ", [$maintenance['room_id'] ?? 0]);
        ?>
        <div class="card shadow">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <?php echo $action == 'create' ? 'Buat Maintenance Request' : 'Edit Maintenance Request'; ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Kamar *</label>
                                <select class="form-select" name="room_id" required>
                                    <option value="">Pilih Kamar</option>
                                    <?php foreach ($rooms as $room): ?>
                                    <option value="<?php echo $room['id']; ?>" 
                                        <?php echo isset($maintenance['room_id']) && $maintenance['room_id'] == $room['id'] ? 'selected' : ''; ?>>
                                        <?php echo $room['room_number']; ?> (<?php echo $room['type_name']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Tipe Issue *</label>
                                <input type="text" class="form-control" name="issue_type" 
                                       value="<?php echo $maintenance['issue_type'] ?? ''; ?>" 
                                       placeholder="e.g., AC Repair, Plumbing, Furniture, etc." required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Priority *</label>
                                <select class="form-select" name="priority" required>
                                    <option value="low" <?php echo isset($maintenance['priority']) && $maintenance['priority'] == 'low' ? 'selected' : ''; ?>>Low</option>
                                    <option value="medium" <?php echo isset($maintenance['priority']) && $maintenance['priority'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="high" <?php echo isset($maintenance['priority']) && $maintenance['priority'] == 'high' ? 'selected' : ''; ?>>High</option>
                                    <option value="urgent" <?php echo isset($maintenance['priority']) && $maintenance['priority'] == 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Status *</label>
                                <select class="form-select" name="status" required>
                                    <option value="reported" <?php echo isset($maintenance['status']) && $maintenance['status'] == 'reported' ? 'selected' : ''; ?>>Reported</option>
                                    <option value="in_progress" <?php echo isset($maintenance['status']) && $maintenance['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="completed" <?php echo isset($maintenance['status']) && $maintenance['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Deskripsi Detail *</label>
                        <textarea class="form-control" name="description" rows="4" required><?php echo $maintenance['description'] ?? ''; ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Estimasi Biaya</label>
                                <input type="number" class="form-control" name="cost_estimate" 
                                       value="<?php echo $maintenance['cost_estimate'] ?? ''; ?>" min="0" step="1000">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Biaya Aktual</label>
                                <input type="number" class="form-control" name="actual_cost" 
                                       value="<?php echo $maintenance['actual_cost'] ?? ''; ?>" min="0" step="1000">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Simpan
                        </button>
                        <a href="maintenance.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </main>
    </div>
</div>

<script>
$(document).ready(function() {
    // Auto-fill date for completed status
    $('select[name="status"]').on('change', function() {
        if ($(this).val() === 'completed') {
            const now = new Date().toISOString().slice(0, 16);
            // You can add a hidden field for completed_date if needed
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>