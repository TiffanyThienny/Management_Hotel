<?php
require_once '../config/init.php';
require_once '../config/database.php';
checkAdminAuth();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Process booking actions
if (isset($_GET['update_status']) && $id > 0) {
    $new_status = sanitizeInput($_GET['update_status']);
    $result = $database->update('bookings', ['status' => $new_status], "id = $id");
    
    if ($result) {
        setFlashMessage('success', 'Status booking berhasil diperbarui');
        
        // If checking in, update room status
        if ($new_status == 'checked_in') {
            $booking = $database->getSingle("SELECT room_id FROM booking_details WHERE booking_id = ?", [$id]);
            if ($booking) {
                $database->update('rooms', ['status' => 'occupied'], "id = {$booking['room_id']}");
            }
        }
        
        // If checking out, update room status and create payment if not exists
        if ($new_status == 'checked_out') {
            $booking = $database->getSingle("SELECT bd.room_id, b.final_amount 
                                           FROM booking_details bd 
                                           JOIN bookings b ON bd.booking_id = b.id 
                                           WHERE bd.booking_id = ?", [$id]);
            if ($booking) {
                $database->update('rooms', ['status' => 'available'], "id = {$booking['room_id']}");
                
                // Check if payment exists
                $payment = $database->getSingle("SELECT id FROM payments WHERE booking_id = ?", [$id]);
                if (!$payment) {
                    $payment_data = [
                        'booking_id' => $id,
                        'amount' => $booking['final_amount'],
                        'payment_method' => 'cash',
                        'payment_status' => 'paid',
                        'payment_date' => date('Y-m-d H:i:s')
                    ];
                    $database->insert('payments', $payment_data);
                }
            }
        }
    } else {
        setFlashMessage('error', 'Gagal memperbarui status booking');
    }
    $redirect_url = (isset($_GET['back']) && $_GET['back'] === 'dashboard') ? 'dashboard.php' : 'bookings.php';
    redirect($redirect_url);
}

// Process form submission for new booking
if ($_POST && $action == 'create') {
    try {
        $db->beginTransaction();
        
        $customer_name = trim(sanitizeInput($_POST['customer_name'] ?? ''));
        $customer_email = trim(sanitizeInput($_POST['customer_email'] ?? ''));
        $customer_phone = trim(sanitizeInput($_POST['customer_phone'] ?? ''));
        $identity_type = sanitizeInput($_POST['identity_type'] ?? 'ktp');
        $identity_number = trim(sanitizeInput($_POST['identity_number'] ?? ''));
        $customer_address = trim(sanitizeInput($_POST['customer_address'] ?? ''));
        
        $room_type_id = intval($_POST['room_type_id'] ?? 0);
        $num_rooms = max(1, intval($_POST['num_rooms'] ?? 1));
        $check_in = sanitizeInput($_POST['check_in'] ?? '');
        $check_out = sanitizeInput($_POST['check_out'] ?? '');
        $adults = intval($_POST['adults'] ?? 1);
        $children = max(0, intval($_POST['children'] ?? 0));
        $special_requests = sanitizeInput($_POST['special_requests'] ?? '');
        
        // 1. Validasi Input Customer
        if (empty($customer_name) || strlen($customer_name) < 3 || !preg_match("/^[a-zA-Z\s\.\'\-]+$/", $customer_name)) {
            throw new Exception('Nama lengkap wajib berupa huruf (minimal 3 karakter).');
        }
        if (empty($customer_email) || !filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Format email tidak valid.');
        }
        if (empty($customer_phone) || !preg_match("/^\+?[0-9\s\-]{8,20}$/", $customer_phone)) {
            throw new Exception('Nomor telepon harus berupa angka valid (minimal 8 digit).');
        }
        
        // 2. Validasi Tanggal Check-in & Check-out
        if (empty($check_in) || empty($check_out)) {
            throw new Exception('Tanggal check-in dan check-out wajib diisi.');
        }
        $today_str = date('Y-m-d');
        if ($check_in < $today_str) {
            throw new Exception('Tanggal check-in tidak boleh sebelum hari ini.');
        }
        if ($check_out <= $check_in) {
            throw new Exception('Tanggal check-out harus lebih besar dari tanggal check-in.');
        }
        
        // 3. Validasi Tipe Kamar & Kapasitas
        if ($room_type_id <= 0) {
            throw new Exception('Silakan pilih tipe kamar.');
        }
        $room_type = $database->getSingle("SELECT * FROM room_types WHERE id = ?", [$room_type_id]);
        if (!$room_type) {
            throw new Exception('Tipe kamar tidak ditemukan.');
        }
        
        if ($adults < 1) {
            throw new Exception('Jumlah tamu dewasa minimal 1 orang.');
        }
        $total_guests = $adults + $children;
        $max_allowed_guests = $room_type['capacity'] * $num_rooms;
        if ($total_guests > $max_allowed_guests) {
            throw new Exception("Kapasitas maksimum untuk {$num_rooms} kamar tipe '{$room_type['type_name']}' adalah {$max_allowed_guests} tamu. Jumlah tamu yang diisi ({$total_guests} orang) melebihi kapasitas.");
        }
        
        // 4. Validasi Ketersediaan Kamar dari Database
        $available_rooms = getAvailableRoomsByType($db, $room_type_id, $check_in, $check_out);
        if (count($available_rooms) < $num_rooms) {
            $avail_cnt = count($available_rooms);
            throw new Exception("Hanya tersedia {$avail_cnt} kamar untuk tipe '{$room_type['type_name']}' pada tanggal tersebut (Anda meminta {$num_rooms} kamar).");
        }
        
        // 5. Simpan / Dapatkan Data Customer
        $customer_query = "SELECT id FROM customers WHERE email = ?";
        $customer_stmt = $db->prepare($customer_query);
        $customer_stmt->execute([$customer_email]);
        
        if ($customer_stmt->rowCount() > 0) {
            $customer = $customer_stmt->fetch(PDO::FETCH_ASSOC);
            $customer_id = $customer['id'];
            $database->update('customers', [
                'full_name' => $customer_name,
                'phone' => $customer_phone,
                'identity_type' => $identity_type,
                'identity_number' => $identity_number,
                'address' => $customer_address
            ], "id = $customer_id");
        } else {
            $customer_data = [
                'full_name' => $customer_name,
                'email' => $customer_email,
                'phone' => $customer_phone,
                'identity_type' => $identity_type,
                'identity_number' => $identity_number,
                'address' => $customer_address
            ];
            $customer_id = $database->insert('customers', $customer_data);
        }
        
        // 6. Hitung Total Malam & Harga
        $nights = calculateNights($check_in, $check_out);
        $total_amount = $nights * $room_type['base_price'] * $num_rooms;
        
        // 7. Simpan Booking Master Record
        $booking_data = [
            'booking_code' => generateBookingCode(),
            'customer_id' => $customer_id,
            'user_id' => $_SESSION['user_id'],
            'check_in' => $check_in,
            'check_out' => $check_out,
            'total_nights' => $nights,
            'total_guests' => $total_guests,
            'total_amount' => $total_amount,
            'final_amount' => $total_amount,
            'status' => 'confirmed',
            'special_requests' => $special_requests,
            'adults' => $adults,
            'children' => $children
        ];
        $booking_id = $database->insert('bookings', $booking_data);
        
        // 8. Simpan Detail Booking & Set Status Kamar Menjadi Occupied
        for ($i = 0; $i < $num_rooms; $i++) {
            $assigned_room_id = $available_rooms[$i]['id'];
            $room_total_price = $nights * $room_type['base_price'];
            
            $detail_data = [
                'booking_id' => $booking_id,
                'room_id' => $assigned_room_id,
                'price_per_night' => $room_type['base_price'],
                'total_price' => $room_total_price
            ];
            $database->insert('booking_details', $detail_data);
            $database->update('rooms', ['status' => 'occupied'], "id = $assigned_room_id");
        }
        
        $db->commit();
        setFlashMessage('success', "Booking berhasil dibuat untuk {$num_rooms} kamar ({$room_type['type_name']})!");
        redirect('bookings.php');
        
    } catch (Exception $e) {
        $db->rollBack();
        setFlashMessage('error', $e->getMessage());
    }
}

$page_title = "Management Booking";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/admin-sidebar.php'; ?>

    <!-- Main Content -->
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Management Booking</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="?action=create" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Buat Booking
                </a>
            </div>
        </div>

        <?php if ($action == 'list'): ?>
        <!-- Booking List -->
        <div class="card shadow">
            <div class="card-header">
                <h5 class="card-title mb-0">Daftar Booking</h5>
            </div>
            <div class="card-body">
                <!-- Filter Form -->
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Status</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">Semua Status</option>
                            <option value="pending" <?php echo isset($_GET['status']) && $_GET['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo isset($_GET['status']) && $_GET['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="checked_in" <?php echo isset($_GET['status']) && $_GET['status'] == 'checked_in' ? 'selected' : ''; ?>>Checked In</option>
                            <option value="checked_out" <?php echo isset($_GET['status']) && $_GET['status'] == 'checked_out' ? 'selected' : ''; ?>>Checked Out</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Dari Tanggal</label>
                        <input type="date" name="start_date" class="form-control datepicker" 
                               value="<?php echo $_GET['start_date'] ?? ''; ?>" onchange="this.form.submit()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Sampai Tanggal</label>
                        <input type="date" name="end_date" class="form-control datepicker" 
                               value="<?php echo $_GET['end_date'] ?? ''; ?>" onchange="this.form.submit()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">&nbsp;</label>
                        <a href="bookings.php" class="btn btn-secondary w-100">Reset</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable">
                        <thead>
                            <tr>
                                <th>Kode Booking</th>
                                <th>Customer</th>
                                <th>Kamar</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Pembayaran</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Build query with filters
                            $query = "SELECT b.*, c.full_name, c.phone, r.room_number, rt.type_name,
                                             p.payment_status, p.payment_method
                                      FROM bookings b
                                      JOIN customers c ON b.customer_id = c.id
                                      JOIN booking_details bd ON b.id = bd.booking_id
                                      JOIN rooms r ON bd.room_id = r.id
                                      JOIN room_types rt ON r.room_type_id = rt.id
                                      LEFT JOIN payments p ON b.id = p.booking_id
                                      WHERE 1=1";
                            
                            $params = [];
                            
                            if (isset($_GET['status']) && $_GET['status'] != '') {
                                $query .= " AND b.status = ?";
                                $params[] = $_GET['status'];
                            }
                            
                            if (isset($_GET['start_date']) && $_GET['start_date'] != '') {
                                $query .= " AND b.check_in >= ?";
                                $params[] = $_GET['start_date'];
                            }
                            
                            if (isset($_GET['end_date']) && $_GET['end_date'] != '') {
                                $query .= " AND b.check_out <= ?";
                                $params[] = $_GET['end_date'];
                            }
                            
                            $query .= " ORDER BY b.created_at DESC";
                            
                            $bookings = $database->getAll($query, $params);
                            
                            foreach ($bookings as $booking):
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo $booking['booking_code']; ?></strong>
                                    <br><small class="text-muted"><?php echo date('d/m/Y', strtotime($booking['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo $booking['full_name']; ?></div>
                                    <small class="text-muted"><?php echo $booking['phone']; ?></small>
                                </td>
                                <td>
                                    <?php echo $booking['room_number']; ?><br>
                                    <small class="text-muted"><?php echo $booking['type_name']; ?></small>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($booking['check_in'])); ?>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($booking['check_out'])); ?>
                                    <br><small class="text-muted"><?php echo $booking['total_nights']; ?> malam</small>
                                </td>
                                <td>
                                    <strong><?php echo formatCurrency($booking['final_amount']); ?></strong>
                                </td>
                                <td>
                                    <span class="booking-status status-<?php echo $booking['status']; ?>">
                                        <?php echo $booking['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($booking['payment_status']): ?>
                                        <span class="payment-status payment-<?php echo $booking['payment_status']; ?>">
                                            <?php echo $booking['payment_status']; ?>
                                        </span>
                                        <br><small class="text-muted"><?php echo $booking['payment_method']; ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-actions">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="booking_detail.php?id=<?php echo $booking['id']; ?>">
                                                <i class="fas fa-eye me-2"></i>Detail
                                            </a></li>
                                            <?php if ($booking['status'] == 'pending'): ?>
                                                <li><a class="dropdown-item" href="?id=<?php echo $booking['id']; ?>&update_status=confirmed" onclick="return confirm('Konfirmasi booking ini?')">
                                                    <i class="fas fa-check me-2"></i>Confirm
                                                </a></li>
                                            <?php endif; ?>
                                            <?php if ($booking['status'] == 'confirmed'): ?>
                                                <li><a class="dropdown-item" href="?id=<?php echo $booking['id']; ?>&update_status=checked_in" onclick="return confirm('Check-in customer?')">
                                                    <i class="fas fa-sign-in-alt me-2"></i>Check-in
                                                </a></li>
                                            <?php endif; ?>
                                            <?php if ($booking['status'] == 'checked_in'): ?>
                                                <li><a class="dropdown-item" href="?id=<?php echo $booking['id']; ?>&update_status=checked_out" onclick="return confirm('Check-out customer?')">
                                                    <i class="fas fa-sign-out-alt me-2"></i>Check-out
                                                </a></li>
                                            <?php endif; ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="?action=delete&id=<?php echo $booking['id']; ?>" onclick="return confirm('Hapus booking ini?')">
                                                <i class="fas fa-trash me-2"></i>Hapus
                                            </a></li>
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

        <?php elseif ($action == 'create'): ?>
        <!-- Create Booking Form -->
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0 fw-bold text-dark"><i class="fas fa-plus-circle me-2 text-primary"></i>Buat Booking Baru</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" id="createBookingForm" class="needs-validation" novalidate>
                    <h6 class="fw-bold mb-3"><i class="fas fa-user-circle me-2 text-primary"></i>Informasi Customer</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Nama Lengkap *</label>
                                <input type="text" class="form-control" name="customer_name" id="customer_name" placeholder="Contoh: Budi Santoso" required pattern="^[a-zA-Z\s\.\'\-]{3,100}$">
                                <small class="text-muted" style="font-size: 0.75rem;">Hanya boleh huruf dan spasi (minimal 3 karakter).</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Email *</label>
                                <input type="email" class="form-control" name="customer_email" id="customer_email" placeholder="nama@email.com" required>
                                <small class="text-muted" style="font-size: 0.75rem;">Contoh: budi@email.com</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">No. Telepon *</label>
                                <input type="tel" class="form-control" name="customer_phone" id="customer_phone" placeholder="Contoh: 081234567890" required pattern="^\+?[0-9\s\-]{8,20}$">
                                <small class="text-muted" style="font-size: 0.75rem;">Minimal 8 digit angka.</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Tipe Identitas</label>
                                <select class="form-select" name="identity_type">
                                    <option value="ktp">KTP</option>
                                    <option value="passport">Passport</option>
                                    <option value="sim">SIM</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">No. Identitas</label>
                                <input type="text" class="form-control" name="identity_number" placeholder="Nomor KTP/Passport">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Alamat</label>
                        <textarea class="form-control" name="customer_address" rows="2" placeholder="Alamat tempat tinggal..."></textarea>
                    </div>

                    <hr class="my-4">
                    <h6 class="fw-bold mb-3"><i class="fas fa-bed me-2 text-primary"></i>Detail Reservasi & Kamar</h6>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Tipe Kamar *</label>
                                <select class="form-select" name="room_type_id" id="room_type_id" required>
                                    <option value="">-- Pilih Tipe Kamar --</option>
                                    <?php
                                    $room_types = $database->getAll("SELECT * FROM room_types WHERE is_available = 1");
                                    foreach ($room_types as $type):
                                    ?>
                                    <option value="<?php echo $type['id']; ?>" data-price="<?php echo $type['base_price']; ?>" data-capacity="<?php echo $type['capacity']; ?>" data-name="<?php echo htmlspecialchars($type['type_name']); ?>">
                                        <?php echo htmlspecialchars($type['type_name']); ?> (Maks. <?php echo $type['capacity']; ?> org/kamar - <?php echo formatCurrency($type['base_price']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Jumlah Kamar *</label>
                                <input type="number" class="form-control" name="num_rooms" id="num_rooms" value="1" min="1" max="10" required>
                                <small class="text-muted" style="font-size: 0.72rem;">Jumlah kamar dipesan</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Check-in *</label>
                                <input type="date" class="form-control datepicker" name="check_in" id="check_in" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Check-out *</label>
                                <input type="date" class="form-control datepicker" name="check_out" id="check_out" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Tamu Dewasa *</label>
                                <input type="number" class="form-control" name="adults" id="adults" value="1" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Tamu Anak-anak</label>
                                <input type="number" class="form-control" name="children" id="children" value="0" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Total Tamu (Otomatis)</label>
                                <input type="number" class="form-control bg-light fw-bold text-primary" name="total_guests" id="total_guests" value="1" readonly>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Durasi (Malam)</label>
                                <input type="text" class="form-control bg-light fw-bold" id="total_nights" value="0" readonly>
                            </div>
                        </div>
                    </div>

                    <!-- Warning Alert for Room Capacity Validation -->
                    <div id="capacity_warning" class="alert alert-danger d-none shadow-sm rounded-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span id="capacity_warning_text"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Permintaan Khusus</label>
                        <textarea class="form-control" name="special_requests" rows="2" placeholder="Permintaan khusus untuk kamar..."></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm bg-light rounded-3">
                                <div class="card-body p-4">
                                    <h6 class="fw-bold mb-3 text-dark"><i class="fas fa-receipt me-2 text-success"></i>Ringkasan Biaya Reservasi</h6>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Harga per malam (1 kamar):</span>
                                        <span class="fw-semibold" id="price_display">Rp 0</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Jumlah kamar dipesan:</span>
                                        <span class="fw-semibold" id="rooms_display">1 kamar</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="text-muted">Durasi menginap:</span>
                                        <span class="fw-semibold" id="nights_display">0 malam</span>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold fs-6 text-dark">Total Biaya:</span>
                                        <span class="fw-extrabold fs-4 text-primary" id="total_display">Rp 0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm" id="submit_booking_btn">
                            <i class="fas fa-save me-1"></i> Buat Booking
                        </button>
                        <a href="bookings.php" class="btn btn-secondary px-4">Batal</a>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function calculateBookingForm() {
            var adults = parseInt($('#adults').val()) || 0;
            var children = parseInt($('#children').val()) || 0;
            var totalGuests = adults + children;
            $('#total_guests').val(totalGuests);

            var numRooms = parseInt($('#num_rooms').val()) || 1;
            if (numRooms < 1) { numRooms = 1; $('#num_rooms').val(1); }

            var checkInVal = $('#check_in').val();
            var checkOutVal = $('#check_out').val();
            var roomTypeOpt = $('#room_type_id option:selected');
            var price = parseFloat(roomTypeOpt.data('price')) || 0;
            var capacity = parseInt(roomTypeOpt.data('capacity')) || 0;
            var typeName = roomTypeOpt.data('name') || '';

            // 1. Live Validation: Kapasitas Tamu vs Jumlah Kamar
            if (capacity > 0 && roomTypeOpt.val() !== "") {
                var maxCapacity = capacity * numRooms;
                if (totalGuests > maxCapacity) {
                    $('#capacity_warning_text').html(
                        '<strong>Peringatan Kapasitas Melebihi Batas!</strong><br>' +
                        'Tipe <strong>' + typeName + '</strong> memiliki kapasitas maksimal <strong>' + capacity + ' orang per kamar</strong>.<br>' +
                        'Untuk ' + numRooms + ' kamar, kapasitas maksimal adalah <strong>' + maxCapacity + ' tamu</strong>. Total tamu yang diisi (<strong>' + totalGuests + ' orang</strong>) melebihi kapasitas.<br>' +
                        '<em>Solusi: Tambah jumlah kamar atau pilih tipe kamar dengan kapasitas lebih besar.</em>'
                    );
                    $('#capacity_warning').removeClass('d-none');
                    $('#submit_booking_btn').prop('disabled', true);
                } else {
                    $('#capacity_warning').addClass('d-none');
                    $('#submit_booking_btn').prop('disabled', false);
                }
            } else {
                $('#capacity_warning').addClass('d-none');
                $('#submit_booking_btn').prop('disabled', false);
            }

            // 2. Calculation: Tanggal & Total Biaya Multi-kamar
            if (checkInVal && checkOutVal) {
                var checkIn = new Date(checkInVal);
                var checkOut = new Date(checkOutVal);

                if (checkOut > checkIn) {
                    var nights = Math.ceil((checkOut - checkIn) / (1000 * 60 * 60 * 24));
                    var total = nights * price * numRooms;

                    $('#total_nights').val(nights);
                    $('#price_display').text('Rp ' + price.toLocaleString('id-ID'));
                    $('#rooms_display').text(numRooms + ' kamar');
                    $('#nights_display').text(nights + ' malam');
                    $('#total_display').text('Rp ' + total.toLocaleString('id-ID'));
                } else {
                    $('#total_nights').val(0);
                    $('#price_display').text('Rp ' + price.toLocaleString('id-ID'));
                    $('#rooms_display').text(numRooms + ' kamar');
                    $('#nights_display').text('0 malam');
                    $('#total_display').text('Rp 0');
                }
            } else {
                $('#total_nights').val(0);
                $('#price_display').text('Rp ' + price.toLocaleString('id-ID'));
                $('#rooms_display').text(numRooms + ' kamar');
                $('#nights_display').text('0 malam');
                $('#total_display').text('Rp 0');
            }
        }

        $(document).ready(function() {
            // Trigger calculation on input and change
            $('#adults, #children, #num_rooms, #check_in, #check_out, #room_type_id').on('input change keyup', calculateBookingForm);
            
            // Client-side validation on form submit
            $('#createBookingForm').on('submit', function(e) {
                var name = $('#customer_name').val().trim();
                var phone = $('#customer_phone').val().trim();
                var nameRegex = /^[a-zA-Z\s\.\'\-]{3,100}$/;
                var phoneRegex = /^\+?[0-9\s\-]{8,20}$/;

                if (!nameRegex.test(name)) {
                    e.preventDefault();
                    Swal.fire('Validasi Gagal', 'Nama lengkap hanya boleh berisi huruf dan spasi (minimal 3 karakter).', 'error');
                    return false;
                }
                if (!phoneRegex.test(phone)) {
                    e.preventDefault();
                    Swal.fire('Validasi Gagal', 'Nomor telepon tidak valid. Masukkan minimal 8 digit angka.', 'error');
                    return false;
                }
            });

            calculateBookingForm();
        });
        </script>
        <?php endif; ?>
    </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>