<?php
require_once '../config/init.php';
checkAdminAuth();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Restrict receptionist to status updates only (no create/edit/delete)
if (isReceptionist() && ($_POST || isset($_GET['delete']) || (isset($_GET['action']) && in_array($_GET['action'], ['create', 'edit'])))) {
    setFlashMessage('error', 'Resepsionis hanya memiliki hak akses memantau kamar dan mengubah status kesiapan kamar.');
    redirect('rooms.php');
}

// Process actions
if ($_POST && isAdmin()) {
    $data = [
        'room_number' => sanitizeInput($_POST['room_number']),
        'room_type_id' => intval($_POST['room_type_id']),
        'floor' => intval($_POST['floor']),
        'view_type' => sanitizeInput($_POST['view_type']),
        'status' => sanitizeInput($_POST['status']),
        'features' => sanitizeInput($_POST['features'])
    ];

    if ($action == 'create') {
        $result = $database->insert('rooms', $data);
        if ($result) {
            setFlashMessage('success', 'Kamar berhasil ditambahkan');
            redirect('rooms.php');
        } else {
            setFlashMessage('error', 'Gagal menambahkan kamar');
        }
    } elseif ($action == 'edit' && $id > 0) {
        $result = $database->update('rooms', $data, "id = $id");
        if ($result) {
            setFlashMessage('success', 'Kamar berhasil diperbarui');
            redirect('rooms.php');
        } else {
            setFlashMessage('error', 'Gagal memperbarui kamar');
        }
    }
}

// Quick status change
if (isset($_GET['quick_status']) && $id > 0) {
    $new_status = sanitizeInput($_GET['quick_status']);
    if (in_array($new_status, ['available', 'occupied', 'cleaning', 'maintenance'])) {
        $result = $database->update('rooms', ['status' => $new_status], "id = $id");
        if ($result) {
            setFlashMessage('success', "Status kamar berhasil diubah menjadi " . ucfirst($new_status));
        } else {
            setFlashMessage('error', 'Gagal memperbarui status kamar');
        }
    }
    $ref = !empty($_GET['ref']) ? $_GET['ref'] : 'rooms.php';
    redirect($ref);
}

// Delete action
if (isset($_GET['delete']) && $id > 0) {
    $result = $database->delete('rooms', "id = $id");
    if ($result) {
        setFlashMessage('success', 'Kamar berhasil dihapus');
    } else {
        setFlashMessage('error', 'Gagal menghapus kamar');
    }
    redirect('rooms.php');
}

$page_title = "Management Kamar";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/admin-sidebar.php'; ?>

    <!-- Main Content -->
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2"><?php echo isReceptionist() ? 'Status & Kesiapan Kamar' : (isOwner() ? 'Monitoring Status Kamar' : 'Management Kamar'); ?></h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <?php if (isAdmin()): ?>
                <a href="?action=create" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Tambah Kamar
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($action == 'list'): ?>
        <!-- Room List -->
        <div class="card shadow">
            <div class="card-header">
                <h5 class="card-title mb-0">Daftar Kamar</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable">
                        <thead>
                            <tr>
                                <th>No. Kamar</th>
                                <th>Tipe Kamar</th>
                                <th>Lantai</th>
                                <th>View</th>
                                <th>Status</th>
                                <th>Harga/Malam</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $query = "SELECT r.*, rt.type_name, rt.base_price 
                                      FROM rooms r 
                                      JOIN room_types rt ON r.room_type_id = rt.id 
                                      ORDER BY r.floor, r.room_number";
                            $rooms = $database->getAll($query);
                            
                            foreach ($rooms as $room):
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo $room['room_number']; ?></strong>
                                </td>
                                <td><?php echo $room['type_name']; ?></td>
                                <td>Lantai <?php echo $room['floor']; ?></td>
                                <td>
                                    <?php 
                                    $view_labels = [
                                        'city' => 'City View',
                                        'garden' => 'Garden View', 
                                        'pool' => 'Pool View',
                                        'sea' => 'Sea View'
                                    ];
                                    echo $view_labels[$room['view_type']] ?? $room['view_type'];
                                    ?>
                                </td>
                                <td>
                                    <span class="badge 
                                        <?php echo $room['status'] == 'available' ? 'bg-success' : 
                                              ($room['status'] == 'occupied' ? 'bg-danger' : 'bg-warning'); ?>">
                                        <?php echo ucfirst($room['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo formatCurrency($room['base_price']); ?></td>
                                <td class="table-actions">
                                    <?php if (isAdmin()): ?>
                                        <a href="?action=edit&id=<?php echo $room['id']; ?>" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="GET" style="display: inline;" onsubmit="return confirm('Hapus kamar ini?')">
                                            <input type="hidden" name="id" value="<?php echo $room['id']; ?>">
                                            <input type="hidden" name="delete" value="1">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php elseif (isReceptionist()): ?>
                                        <?php if ($room['status'] === 'cleaning'): ?>
                                            <a href="?quick_status=available&id=<?php echo $room['id']; ?>" class="btn btn-sm btn-success px-2 py-1 fw-bold">
                                                <i class="fas fa-check-circle me-1"></i>Set Siap
                                            </a>
                                        <?php elseif ($room['status'] === 'available'): ?>
                                            <a href="bookings.php?action=create" class="btn btn-sm btn-outline-primary px-2 py-1">
                                                <i class="fas fa-plus me-1"></i>Book
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border">Lihat Status</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">Monitoring</span>
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
        <!-- Room Form -->
        <?php
        $room = [];
        if ($action == 'edit' && $id > 0) {
            $room = $database->getSingle("SELECT * FROM rooms WHERE id = ?", [$id]);
            if (!$room) {
                setFlashMessage('error', 'Kamar tidak ditemukan');
                redirect('rooms.php');
            }
        }

        $room_types = $database->getAll("SELECT * FROM room_types WHERE is_available = 1");
        ?>
        <div class="card shadow">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <?php echo $action == 'create' ? 'Tambah Kamar' : 'Edit Kamar'; ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nomor Kamar *</label>
                                <input type="text" class="form-control" name="room_number" 
                                       value="<?php echo $room['room_number'] ?? ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Tipe Kamar *</label>
                                <select class="form-select" name="room_type_id" required>
                                    <option value="">Pilih Tipe Kamar</option>
                                    <?php foreach ($room_types as $type): ?>
                                    <option value="<?php echo $type['id']; ?>" 
                                        <?php echo isset($room['room_type_id']) && $room['room_type_id'] == $type['id'] ? 'selected' : ''; ?>>
                                        <?php echo $type['type_name']; ?> (<?php echo formatCurrency($type['base_price']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Lantai *</label>
                                <input type="number" class="form-control" name="floor" 
                                       value="<?php echo $room['floor'] ?? ''; ?>" min="1" max="20" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">View</label>
                                <select class="form-select" name="view_type">
                                    <option value="city" <?php echo isset($room['view_type']) && $room['view_type'] == 'city' ? 'selected' : ''; ?>>City View</option>
                                    <option value="garden" <?php echo isset($room['view_type']) && $room['view_type'] == 'garden' ? 'selected' : ''; ?>>Garden View</option>
                                    <option value="pool" <?php echo isset($room['view_type']) && $room['view_type'] == 'pool' ? 'selected' : ''; ?>>Pool View</option>
                                    <option value="sea" <?php echo isset($room['view_type']) && $room['view_type'] == 'sea' ? 'selected' : ''; ?>>Sea View</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="available" <?php echo isset($room['status']) && $room['status'] == 'available' ? 'selected' : ''; ?>>Available</option>
                                    <option value="occupied" <?php echo isset($room['status']) && $room['status'] == 'occupied' ? 'selected' : ''; ?>>Occupied</option>
                                    <option value="maintenance" <?php echo isset($room['status']) && $room['status'] == 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                    <option value="cleaning" <?php echo isset($room['status']) && $room['status'] == 'cleaning' ? 'selected' : ''; ?>>Cleaning</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Fitur Tambahan</label>
                        <textarea class="form-control" name="features" rows="3"><?php echo $room['features'] ?? ''; ?></textarea>
                        <div class="form-text">Fitur khusus kamar (opsional)</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Simpan
                        </button>
                        <a href="rooms.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>