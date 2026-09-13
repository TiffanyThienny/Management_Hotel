<?php
require_once '../config/init.php';
checkAdminAuth();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Restrict receptionist to read-only
if (isReceptionist() && ($_POST || isset($_GET['delete']) || (isset($_GET['action']) && in_array($_GET['action'], ['create', 'edit'])))) {
    setFlashMessage('error', 'Resepsionis hanya memiliki hak akses melihat informasi tipe kamar.');
    redirect('room_types.php');
}

// Process form actions
if ($_POST && isAdmin()) {
    // Get existing image if edit
    $existing_room_type = ($action == 'edit' && $id > 0) ? $database->getSingle("SELECT * FROM room_types WHERE id = ?", [$id]) : null;
    $image_path = $existing_room_type['image'] ?? null;

    // Handle file upload
    if (isset($_FILES['room_image']) && $_FILES['room_image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['room_image']['tmp_name'];
        $file_name = $_FILES['room_image']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        
        if (in_array($file_ext, $allowed_exts)) {
            $upload_dir = '../assets/uploads/rooms/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $new_filename = 'room_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
            $destination = $upload_dir . $new_filename;
            if (move_uploaded_file($file_tmp, $destination)) {
                $image_path = 'assets/uploads/rooms/' . $new_filename;
            }
        }
    } elseif (!empty($_POST['image_url'])) {
        $image_path = trim($_POST['image_url']);
    }

    $data = [
        'type_name' => sanitizeInput($_POST['type_name']),
        'description' => sanitizeInput($_POST['description']),
        'base_price' => floatval($_POST['base_price']),
        'capacity' => intval($_POST['capacity']),
        'size' => sanitizeInput($_POST['size']),
        'bed_type' => sanitizeInput($_POST['bed_type']),
        'image' => $image_path,
        'is_available' => isset($_POST['is_available']) ? 1 : 0
    ];

    if ($action == 'create') {
        $result = $database->insert('room_types', $data);
        if ($result) {
            // Process facilities
            if (isset($_POST['facilities'])) {
                foreach ($_POST['facilities'] as $facility_id) {
                    $database->insert('room_facilities', [
                        'room_type_id' => $result,
                        'facility_id' => intval($facility_id)
                    ]);
                }
            }
            setFlashMessage('success', 'Tipe kamar berhasil ditambahkan');
            redirect('room_types.php');
        } else {
            setFlashMessage('error', 'Gagal menambahkan tipe kamar');
        }
    } elseif ($action == 'edit' && $id > 0) {
        $result = $database->update('room_types', $data, "id = $id");
        if ($result) {
            // Update facilities
            $database->delete('room_facilities', "room_type_id = $id");
            if (isset($_POST['facilities'])) {
                foreach ($_POST['facilities'] as $facility_id) {
                    $database->insert('room_facilities', [
                        'room_type_id' => $id,
                        'facility_id' => intval($facility_id)
                    ]);
                }
            }
            setFlashMessage('success', 'Tipe kamar berhasil diperbarui');
            redirect('room_types.php');
        } else {
            setFlashMessage('error', 'Gagal memperbarui tipe kamar');
        }
    }
}

// Delete action
if (isset($_GET['delete']) && $id > 0) {
    // Check if room type has rooms
    $rooms = $database->getSingle("SELECT COUNT(*) as count FROM rooms WHERE room_type_id = ?", [$id]);
    if ($rooms['count'] > 0) {
        setFlashMessage('error', 'Tidak dapat menghapus tipe kamar yang memiliki kamar');
    } else {
        // Delete facilities first
        $database->delete('room_facilities', "room_type_id = $id");
        $result = $database->delete('room_types', "id = $id");
        if ($result) {
            setFlashMessage('success', 'Tipe kamar berhasil dihapus');
        } else {
            setFlashMessage('error', 'Gagal menghapus tipe kamar');
        }
    }
    redirect('room_types.php');
}

$page_title = "Management Tipe Kamar";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/admin-sidebar.php'; ?>

    <!-- Main Content -->
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2"><?php echo isReceptionist() ? 'Informasi Tipe Kamar' : (isOwner() ? 'Tipe & Tarif Kamar' : 'Management Tipe Kamar'); ?></h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <?php if (isAdmin()): ?>
                <a href="?action=create" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Tambah Tipe Kamar
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($action == 'list'): ?>
        <!-- Room Types Statistics -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Tipe Kamar</span>
                        <h4 class="fw-extrabold mb-0 mt-1 text-primary" style="font-family: 'Outfit', sans-serif;">
                            <?php echo $database->getSingle("SELECT COUNT(*) as count FROM room_types")['count']; ?>
                        </h4>
                        <small class="text-muted"><i class="fas fa-layer-group me-1 text-primary"></i>Kategori Ruangan</small>
                    </div>
                    <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #e0e7ff; color: #4338ca; width: 50px; height: 50px;">
                        <i class="fas fa-layer-group fs-4"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Total Unit Kamar</span>
                        <h4 class="fw-extrabold mb-0 mt-1 text-success" style="font-family: 'Outfit', sans-serif;">
                            <?php echo $database->getSingle("SELECT COUNT(*) as count FROM rooms")['count']; ?>
                        </h4>
                        <small class="text-muted"><i class="fas fa-door-open me-1 text-success"></i>Kamar Terdaftar</small>
                    </div>
                    <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #d1fae5; color: #059669; width: 50px; height: 50px;">
                        <i class="fas fa-door-open fs-4"></i>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Harga Terendah</span>
                        <h4 class="fw-extrabold mb-0 mt-1 text-info" style="font-family: 'Outfit', sans-serif;">
                            <?php echo formatCurrency($database->getSingle("SELECT COALESCE(MIN(base_price), 0) as min_p FROM room_types")['min_p']); ?>
                        </h4>
                        <small class="text-muted"><i class="fas fa-tag me-1 text-info"></i>Tarif Mulai Dari</small>
                    </div>
                    <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #e0f2fe; color: #0284c7; width: 50px; height: 50px;">
                        <i class="fas fa-tag fs-4"></i>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Tarif Premium</span>
                        <h4 class="fw-extrabold mb-0 mt-1 text-warning" style="font-family: 'Outfit', sans-serif;">
                            <?php echo formatCurrency($database->getSingle("SELECT COALESCE(MAX(base_price), 0) as max_p FROM room_types")['max_p']); ?>
                        </h4>
                        <small class="text-muted"><i class="fas fa-crown me-1 text-warning"></i>Suite / Top Tier</small>
                    </div>
                    <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #fef3c7; color: #d97706; width: 50px; height: 50px;">
                        <i class="fas fa-crown fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Room Types List -->
        <div class="card shadow">
            <div class="card-header">
                <h5 class="card-title mb-0">Daftar Tipe Kamar</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable">
                        <thead>
                            <tr>
                                <th>Tipe Kamar</th>
                                <th>Harga/Malam</th>
                                <th>Kapasitas</th>
                                <th>Ukuran</th>
                                <th>Tipe Bed</th>
                                <th>Status</th>
                                <th>Jumlah Kamar</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $query = "SELECT rt.*, 
                                     (SELECT COUNT(*) FROM rooms WHERE room_type_id = rt.id) as room_count,
                                     GROUP_CONCAT(f.name) as facilities
                                      FROM room_types rt
                                      LEFT JOIN room_facilities rf ON rt.id = rf.room_type_id
                                      LEFT JOIN facilities f ON rf.facility_id = f.id
                                      GROUP BY rt.id
                                      ORDER BY rt.base_price ASC";
                            $room_types = $database->getAll($query);
                            
                            foreach ($room_types as $type):
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-3 overflow-hidden shadow-sm flex-shrink-0 border" style="width: 65px; height: 50px; background: #e2e8f0;">
                                            <img src="<?php echo htmlspecialchars(getRoomImageUrl($type['image'] ?? '')); ?>" alt="<?php echo htmlspecialchars($type['type_name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?php echo $type['type_name']; ?></div>
                                            <small class="text-muted d-block text-truncate" style="max-width: 250px;"><?php echo $type['description']; ?></small>
                                            <?php if ($type['facilities']): ?>
                                                <div class="mt-1">
                                                    <?php 
                                                    $facilities = explode(',', $type['facilities']);
                                                    $display_facilities = array_slice($facilities, 0, 3);
                                                    foreach ($display_facilities as $facility): 
                                                    ?>
                                                        <span class="badge bg-light text-dark border me-1" style="font-size: 0.68rem;"><?php echo $facility; ?></span>
                                                    <?php endforeach; ?>
                                                    <?php if (count($facilities) > 3): ?>
                                                        <span class="badge bg-secondary" style="font-size: 0.68rem;">+<?php echo count($facilities) - 3; ?> more</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo formatCurrency($type['base_price']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $type['capacity']; ?> orang</span>
                                </td>
                                <td><?php echo $type['size'] ?? '-'; ?></td>
                                <td><?php echo $type['bed_type'] ?? '-'; ?></td>
                                <td>
                                    <?php if ($type['is_available']): ?>
                                        <span class="badge bg-success">Available</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Not Available</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $type['room_count']; ?> kamar</span>
                                </td>
                                <td class="table-actions">
                                    <?php if (isAdmin()): ?>
                                        <a href="?action=edit&id=<?php echo $type['id']; ?>" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($type['room_count'] == 0): ?>
                                        <form method="GET" style="display: inline;" onsubmit="return confirm('Hapus tipe kamar ini?')">
                                            <input type="hidden" name="id" value="<?php echo $type['id']; ?>">
                                            <input type="hidden" name="delete" value="1">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border"><i class="fas fa-eye me-1"></i>View Only</span>
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
        <!-- Room Type Form -->
        <?php
        $room_type = [];
        $selected_facilities = [];
        
        if ($action == 'edit' && $id > 0) {
            $room_type = $database->getSingle("SELECT * FROM room_types WHERE id = ?", [$id]);
            if (!$room_type) {
                setFlashMessage('error', 'Tipe kamar tidak ditemukan');
                redirect('room_types.php');
            }
            
            // Get selected facilities
            $facilities = $database->getAll("SELECT facility_id FROM room_facilities WHERE room_type_id = ?", [$id]);
            foreach ($facilities as $facility) {
                $selected_facilities[] = $facility['facility_id'];
            }
        }

        $facilities = $database->getAll("SELECT * FROM facilities WHERE is_available = 1 ORDER BY category, name");
        $facilities_by_category = [];
        foreach ($facilities as $facility) {
            $facilities_by_category[$facility['category']][] = $facility;
        }
        ?>
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0 fw-bold">
                    <i class="fas <?php echo $action == 'create' ? 'fa-plus-circle text-primary' : 'fa-edit text-warning'; ?> me-2"></i>
                    <?php echo $action == 'create' ? 'Tambah Tipe Kamar Baru' : 'Edit Tipe Kamar'; ?>
                </h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nama Tipe Kamar *</label>
                                <input type="text" class="form-control" name="type_name" 
                                       value="<?php echo $room_type['type_name'] ?? ''; ?>" placeholder="Contoh: Deluxe Ocean View" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Harga per Malam *</label>
                                <input type="number" class="form-control" name="base_price" 
                                       value="<?php echo $room_type['base_price'] ?? ''; ?>" min="0" placeholder="Rp" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Kapasitas Maksimal *</label>
                                <input type="number" class="form-control" name="capacity" 
                                       value="<?php echo $room_type['capacity'] ?? '2'; ?>" min="1" max="10" required>
                            </div>
                        </div>
                    </div>

                    <!-- Upload Foto Tipe Kamar -->
                    <div class="card bg-light border rounded-4 p-3 mb-4 shadow-sm">
                        <div class="row align-items-center g-3">
                            <div class="col-md-3 text-center">
                                <div class="position-relative mx-auto rounded-4 overflow-hidden border shadow-sm" style="width: 100%; max-width: 180px; height: 120px; background: #e2e8f0;">
                                    <img id="image_preview" src="<?php echo htmlspecialchars(getRoomImageUrl($room_type['image'] ?? '')); ?>" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                                <small class="text-muted d-block mt-1 font-monospace" style="font-size: 0.75rem;">Preview Foto Kamar</small>
                            </div>
                            <div class="col-md-9">
                                <label class="form-label fw-bold"><i class="fas fa-camera text-primary me-1"></i> Foto / Gambar Tipe Kamar</label>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1">Upload File Foto (JPG, PNG, WEBP)</label>
                                        <input type="file" class="form-control" name="room_image" id="room_image_file" accept="image/png, image/jpeg, image/webp, image/gif" onchange="previewUploadedImage(this)">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1">Atau Masukkan URL Gambar (Opsional)</label>
                                        <input type="url" class="form-control" name="image_url" id="room_image_url" 
                                               value="<?php echo (isset($room_type['image']) && str_starts_with($room_type['image'], 'http')) ? htmlspecialchars($room_type['image']) : ''; ?>" 
                                               placeholder="https://images.unsplash.com/..." oninput="previewUrlImage(this.value)">
                                    </div>
                                </div>
                                <div class="form-text small text-muted mt-2">
                                    <i class="fas fa-info-circle me-1 text-primary"></i> Foto ini otomatis ditampilkan di katalog kamar, halaman booking user, dan website utama.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Deskripsi Tipe Kamar</label>
                                <textarea class="form-control" name="description" rows="3" placeholder="Deskripsi lengkap mengenai tipe kamar, pemandangan, kenyamanan..."><?php echo $room_type['description'] ?? ''; ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Ukuran Kamar</label>
                                <input type="text" class="form-control" name="size" 
                                       value="<?php echo $room_type['size'] ?? ''; ?>" placeholder="e.g., 35 m²">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Tipe Bed</label>
                                <input type="text" class="form-control" name="bed_type" 
                                       value="<?php echo $room_type['bed_type'] ?? ''; ?>" placeholder="e.g., King Bed / Twin Bed">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Fasilitas Kamar</label>
                        <div class="border rounded-4 p-3 bg-white">
                            <?php foreach ($facilities_by_category as $category => $category_facilities): ?>
                                <h6 class="mt-2 mb-2 text-capitalize fw-bold text-dark">
                                    <i class="fas fa-<?php echo $category; ?> me-2 text-primary"></i>
                                    Fasilitas <?php echo ucfirst($category); ?>
                                </h6>
                                <div class="row">
                                    <?php foreach ($category_facilities as $facility): ?>
                                    <div class="col-md-4 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="facilities[]" 
                                                   value="<?php echo $facility['id']; ?>" 
                                                   id="facility_<?php echo $facility['id']; ?>"
                                                   <?php echo in_array($facility['id'], $selected_facilities) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="facility_<?php echo $facility['id']; ?>">
                                                <i class="fas fa-<?php echo $facility['icon']; ?> me-2 text-muted"></i>
                                                <?php echo $facility['name']; ?>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_available" 
                                   id="is_available" <?php echo isset($room_type['is_available']) && !$room_type['is_available'] ? '' : 'checked'; ?>>
                            <label class="form-check-label fw-semibold" for="is_available">
                                Tipe kamar aktif dan tersedia untuk reservasi
                            </label>
                        </div>
                    </div>

                    <div class="d-flex gap-2 pt-2 border-top">
                        <button type="submit" class="btn btn-primary px-4 py-2 fw-bold">
                            <i class="fas fa-save me-1"></i> Simpan Tipe Kamar
                        </button>
                        <a href="room_types.php" class="btn btn-outline-secondary px-4 py-2">Batal</a>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function previewUploadedImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    var preview = document.getElementById('image_preview');
                    if (preview) preview.src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function previewUrlImage(url) {
            if (url && (url.startsWith('http://') || url.startsWith('https://'))) {
                var preview = document.getElementById('image_preview');
                if (preview) preview.src = url;
            }
        }
        </script>
        <?php endif; ?>
    </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>