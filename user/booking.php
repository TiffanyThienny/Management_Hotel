<?php
require_once '../config/init.php';
checkUserAuth();

// AJAX endpoint for real-time availability check
if (isset($_GET['ajax_check_availability'])) {
    header('Content-Type: application/json');
    $type_id = intval($_GET['room_type_id'] ?? 0);
    $c_in = $_GET['check_in'] ?? date('Y-m-d');
    $c_out = $_GET['check_out'] ?? date('Y-m-d', strtotime('+1 day'));
    
    $avail = getAvailableRoomsByType($db, $type_id, $c_in, $c_out);
    echo json_encode([
        'status' => 'success',
        'available_count' => count($avail),
        'rooms' => $avail
    ]);
    exit();
}

$page_title = "Formulir Pemesanan Kamar - Grand Luxury Hotel";
include '../includes/header.php';

// Get parameters
$room_type_id = $_GET['room_type'] ?? '';
$check_in = $_GET['check_in'] ?? date('Y-m-d', strtotime('+1 day'));
$check_out = $_GET['check_out'] ?? date('Y-m-d', strtotime('+3 days'));
$guests = $_GET['guests'] ?? 2;

// Process booking form
if ($_POST) {
    try {
        $db->beginTransaction();
        
        $room_type_post_id = intval($_POST['room_type_id'] ?? 0);
        $num_rooms = max(1, intval($_POST['num_rooms'] ?? 1));
        $check_in_post = sanitizeInput($_POST['check_in'] ?? '');
        $check_out_post = sanitizeInput($_POST['check_out'] ?? '');
        $adults_post = intval($_POST['adults'] ?? 0);
        $children_post = intval($_POST['children'] ?? 0);
        $total_guests_post = intval($_POST['total_guests'] ?? ($adults_post + $children_post));
        $cust_name = trim(sanitizeInput($_POST['customer_name'] ?? ''));
        $cust_email = trim(sanitizeInput($_POST['customer_email'] ?? ''));
        $cust_phone = trim(sanitizeInput($_POST['customer_phone'] ?? ''));
        $id_type = trim(sanitizeInput($_POST['identity_type'] ?? ''));
        $id_number = trim(sanitizeInput($_POST['identity_number'] ?? ''));
        $cust_address = trim(sanitizeInput($_POST['customer_address'] ?? ''));
        $special_req = trim(sanitizeInput($_POST['special_requests'] ?? ''));

        // All fields are mandatory
        if (empty($room_type_post_id) || empty($check_in_post) || empty($check_out_post) || 
            empty($num_rooms) || $adults_post < 1 || 
            empty($cust_name) || empty($cust_email) || empty($cust_phone) || 
            empty($id_type) || empty($id_number) || empty($cust_address) || empty($special_req)) {
            throw new Exception('Semua kolom bertanda bintang (*) wajib diisi lengkap!');
        }

        // Validate dates
        $date_validation = validateDateRange($check_in_post, $check_out_post);
        if ($date_validation !== true) {
            throw new Exception($date_validation);
        }
        
        // Check room availability
        $available_rooms = getAvailableRoomsByType($db, $room_type_post_id, $check_in_post, $check_out_post);
        if (empty($available_rooms) || count($available_rooms) < $num_rooms) {
            $available_count = !empty($available_rooms) ? count($available_rooms) : 0;
            throw new Exception("Maaf, hanya tersedia {$available_count} unit kamar untuk tipe dan tanggal yang dipilih (Jumlah maksimal yang dapat dipesan: {$available_count} kamar).");
        }
        
        // Get room type details
        $room_type = $database->getSingle("SELECT * FROM room_types WHERE id = ?", [$room_type_post_id]);
        if (!$room_type) {
            throw new Exception('Tipe kamar tidak valid.');
        }
        
        // Check capacity
        $total_capacity = $room_type['capacity'] * $num_rooms;
        if ($total_guests_post > $total_capacity) {
            throw new Exception("Jumlah tamu ({$total_guests_post} orang) melebihi kapasitas total ({$total_capacity} orang untuk {$num_rooms} kamar).");
        }
        
        // Create or get customer
        $customer_query = "SELECT id FROM customers WHERE email = ?";
        $customer_stmt = $db->prepare($customer_query);
        $customer_stmt->execute([$cust_email]);
        
        if ($customer_stmt->rowCount() > 0) {
            $customer = $customer_stmt->fetch(PDO::FETCH_ASSOC);
            $customer_id = $customer['id'];
        } else {
            $customer_data = [
                'full_name' => $cust_name,
                'email' => $cust_email,
                'phone' => $cust_phone,
                'identity_type' => $id_type,
                'identity_number' => $id_number,
                'address' => $cust_address
            ];
            
            $customer_query = "INSERT INTO customers (full_name, email, phone, identity_type, identity_number, address) 
                             VALUES (:full_name, :email, :phone, :identity_type, :identity_number, :address)";
            $customer_stmt = $db->prepare($customer_query);
            $customer_stmt->execute($customer_data);
            $customer_id = $db->lastInsertId();
        }
        
        // Calculate total amount
        $nights = calculateNights($check_in_post, $check_out_post);
        $price_per_room_total = $nights * $room_type['base_price'];
        $total_amount = $price_per_room_total * $num_rooms;
        
        // Payment method selection
        $payment_method = sanitizeInput($_POST['payment_method'] ?? 'transfer');
        if (!in_array($payment_method, ['transfer', 'qris', 'credit_card', 'cash'])) {
            $payment_method = 'transfer';
        }

        // Determine booking status
        $booking_status = ($payment_method === 'cash') ? 'confirmed' : 'pending';

        // Create booking
        $booking_data = [
            'booking_code' => generateBookingCode(),
            'customer_id' => $customer_id,
            'user_id' => $_SESSION['user_id'],
            'check_in' => $check_in_post,
            'check_out' => $check_out_post,
            'total_nights' => $nights,
            'total_guests' => $total_guests_post,
            'total_amount' => $total_amount,
            'final_amount' => $total_amount,
            'status' => $booking_status,
            'special_requests' => $special_req,
            'adults' => $adults_post,
            'children' => $children_post
        ];
        
        $booking_query = "INSERT INTO bookings (booking_code, customer_id, user_id, check_in, check_out, total_nights, total_guests, total_amount, final_amount, status, special_requests, adults, children) 
                         VALUES (:booking_code, :customer_id, :user_id, :check_in, :check_out, :total_nights, :total_guests, :total_amount, :final_amount, :status, :special_requests, :adults, :children)";
        $booking_stmt = $db->prepare($booking_query);
        $booking_stmt->execute($booking_data);
        $booking_id = $db->lastInsertId();
        
        // Create booking details & update room status for all allocated rooms
        for ($i = 0; $i < $num_rooms; $i++) {
            $assigned_room_id = $available_rooms[$i]['id'];
            
            $detail_data = [
                'booking_id' => $booking_id,
                'room_id' => $assigned_room_id,
                'price_per_night' => $room_type['base_price'],
                'total_price' => $price_per_room_total
            ];
            
            $detail_query = "INSERT INTO booking_details (booking_id, room_id, price_per_night, total_price) 
                            VALUES (:booking_id, :room_id, :price_per_night, :total_price)";
            $detail_stmt = $db->prepare($detail_query);
            $detail_stmt->execute($detail_data);
            
            // Update room status
            $room_query = "UPDATE rooms SET status = 'occupied' WHERE id = ?";
            $room_stmt = $db->prepare($room_query);
            $room_stmt->execute([$assigned_room_id]);
        }

        // Create initial payment record
        $payment_notes = ($payment_method === 'cash') 
            ? 'Pembayaran tunai / kartu langsung di hotel saat check-in.' 
            : 'Menunggu proses transaksi / verifikasi bukti transfer.';
            
        $payment_data = [
            'booking_id' => $booking_id,
            'amount' => $total_amount,
            'payment_method' => $payment_method,
            'payment_status' => 'pending',
            'transaction_id' => strtoupper($payment_method) . '-' . strtoupper(substr(uniqid(), -8)),
            'bank_name' => ($payment_method === 'transfer') ? 'BCA' : null,
            'notes' => $payment_notes
        ];
        
        $payment_query = "INSERT INTO payments (booking_id, amount, payment_method, payment_status, transaction_id, bank_name, notes) 
                          VALUES (:booking_id, :amount, :payment_method, :payment_status, :transaction_id, :bank_name, :notes)";
        $payment_stmt = $db->prepare($payment_query);
        $payment_stmt->execute($payment_data);
        
        $db->commit();
        
        // Redirect according to payment method
        if ($payment_method === 'cash') {
            setFlashMessage('success', "Reservasi untuk {$num_rooms} kamar berhasil dibuat! Pembayaran dapat dilakukan langsung di hotel saat check-in.");
            redirect("booking_detail.php?id=" . $booking_id);
        } else {
            setFlashMessage('success', "Reservasi untuk {$num_rooms} kamar berhasil dibuat! Silakan selesaikan pembayaran Anda.");
            redirect("payment.php?booking_id=" . $booking_id . "&method=" . $payment_method);
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        setFlashMessage('error', $e->getMessage());
    }
}

// Get room type details if provided
$selected_room_type = null;
if ($room_type_id) {
    $selected_room_type = $database->getSingle("
        SELECT rt.*, 
               (SELECT COUNT(*) FROM rooms r 
                WHERE r.room_type_id = rt.id 
                AND r.status = 'available'
                AND r.id NOT IN (
                    SELECT bd.room_id 
                    FROM booking_details bd 
                    JOIN bookings b ON bd.booking_id = b.id 
                    WHERE b.status IN ('confirmed', 'checked_in')
                    AND (b.check_in < ? AND b.check_out > ?)
                )) as available_rooms
        FROM room_types rt
        WHERE rt.id = ? AND rt.is_available = 1
    ", [$check_out, $check_in, $room_type_id]);
}

// Get all room types with available rooms count for initial dates
$room_types_query = "
    SELECT rt.*, 
           COUNT(r.id) as total_rooms,
           SUM(CASE WHEN r.status = 'available' AND r.id NOT IN (
               SELECT bd.room_id 
               FROM booking_details bd 
               JOIN bookings b ON bd.booking_id = b.id 
               WHERE b.status IN ('confirmed', 'checked_in')
               AND (b.check_in < ? AND b.check_out > ?)
           ) THEN 1 ELSE 0 END) as available_rooms
    FROM room_types rt
    LEFT JOIN rooms r ON rt.id = r.room_type_id
    WHERE rt.is_available = 1
    GROUP BY rt.id
    ORDER BY rt.base_price ASC
";
$room_types = $database->getAll($room_types_query, [$check_out, $check_in]);

// Determine initial available rooms count
$has_initial_room_type = false;
$initial_available = 0;
if ($selected_room_type && isset($selected_room_type['available_rooms'])) {
    $initial_available = max(0, intval($selected_room_type['available_rooms']));
    $has_initial_room_type = true;
} elseif (!empty($room_type_id)) {
    foreach ($room_types as $rt_item) {
        if ($rt_item['id'] == $room_type_id) {
            $initial_available = max(0, intval($rt_item['available_rooms'] ?? 0));
            $has_initial_room_type = true;
            break;
        }
    }
}
?>

<style>
    .booking-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #312e81 100%);
        border-radius: 20px;
        padding: 30px;
        color: #fff;
        margin-bottom: 24px;
        border: 1px solid rgba(255, 255, 255, 0.15);
        box-shadow: 0 10px 30px rgba(0,0,0,0.06);
    }
    .booking-hero h1, .booking-hero h2, .booking-hero h3, .booking-hero h4 {
        color: #ffffff !important;
        font-weight: 800 !important;
    }
    .booking-hero p {
        color: #e2e8f0 !important;
    }
    .booking-form-card {
        background: #ffffff;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        overflow: hidden;
    }
    .step-badge {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #e0e7ff;
        color: #4338ca;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.9rem;
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
    .pricing-summary-card {
        background: linear-gradient(135deg, #f8fafc 0%, #edf2f7 100%);
        border: 1.5px dashed #cbd5e1;
        border-radius: 18px;
        padding: 24px;
    }
    .payment-card-option {
        border: 2px solid #e2e8f0;
        border-radius: 16px;
        padding: 16px;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        background: #ffffff;
        position: relative;
    }
    .payment-card-option:hover {
        border-color: #4f46e5;
        background: #f8fafc;
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(79, 70, 229, 0.08);
    }
    .payment-card-option.active {
        border-color: #4f46e5;
        background: #f5f7ff;
        box-shadow: 0 4px 15px rgba(79, 70, 229, 0.12);
    }
</style>

<div class="container py-4">
    
    <!-- Hero Header -->
    <div class="booking-hero">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-warning text-dark fw-bold px-3 py-1 rounded-pill">
                        <i class="fas fa-hotel me-1"></i> RESERVASI KAMAR
                    </span>
                    <span class="text-white-50 small">Grand Luxury Hotel & Resort</span>
                </div>
                <h2 class="fw-extrabold mb-1" style="font-family: 'Outfit', sans-serif; font-size: 1.85rem;">
                    Formulir Pemesanan Kamar
                </h2>
                <p class="mb-0 text-white-50">
                    Lengkapi seluruh data reservasi di bawah ini untuk proses check-in yang cepat dan nyaman.
                </p>
            </div>
            <div>
                <a href="rooms.php" class="btn btn-outline-light rounded-pill px-4 fw-semibold">
                    <i class="fas fa-th-large me-1"></i> Lihat Semua Kamar
                </a>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="booking-form-card">
                <div class="p-4 p-md-5">
                    
                    <?php if (!$selected_room_type && $room_type_id): ?>
                        <div class="alert alert-warning rounded-4 p-4 text-center border-0 shadow-sm">
                            <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                            <h5 class="fw-bold">Kamar Tidak Tersedia</h5>
                            <p class="text-muted mb-3">Maaf, tipe kamar yang dipilih sedang terisi penuh untuk tanggal yang ditentukan.</p>
                            <a href="rooms.php" class="btn btn-primary rounded-pill px-4 fw-bold">
                                <i class="fas fa-search me-1"></i> Pilih Kamar / Tanggal Lain
                            </a>
                        </div>
                    <?php else: ?>
                    <form method="POST" id="bookingForm">
                        
                        <!-- Step 1: Room & Schedule -->
                        <div class="mb-4">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <span class="step-badge">1</span>
                                <h5 class="fw-bold mb-0 text-dark" style="font-family: 'Outfit', sans-serif;">Pilihan Kamar & Jadwal Menginap</h5>
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark small">Tipe Kamar <span class="text-danger">*</span></label>
                                    <select class="form-select form-control-luxury" name="room_type_id" id="room_type_id" required>
                                        <option value="">-- Pilih Tipe Kamar --</option>
                                        <?php foreach ($room_types as $type): ?>
                                        <option value="<?php echo $type['id']; ?>" 
                                            data-name="<?php echo htmlspecialchars($type['type_name']); ?>"
                                            data-desc="<?php echo htmlspecialchars($type['description']); ?>"
                                            data-price="<?php echo $type['base_price']; ?>"
                                            data-capacity="<?php echo $type['capacity']; ?>"
                                            data-available="<?php echo $type['available_rooms'] ?? 0; ?>"
                                            <?php echo ($has_initial_room_type && $room_type_id == $type['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($type['type_name']); ?> - <?php echo formatCurrency($type['base_price']); ?>/malam (Maks. <?php echo $type['capacity']; ?> org/kamar)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark small">
                                        Jumlah Kamar <span class="text-danger">*</span> 
                                        <span class="badge bg-primary text-white ms-1 px-2 py-1" id="badge_available_rooms" <?php echo !$has_initial_room_type ? 'style="display:none;"' : ''; ?>>
                                            <?php echo $has_initial_room_type ? 'Maks. ' . $initial_available . ' unit' : ''; ?>
                                        </span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light text-primary border-end-0 rounded-start-3"><i class="fas fa-door-open"></i></span>
                                        <select class="form-select form-control-luxury border-start-0 rounded-end-3" name="num_rooms" id="num_rooms" required <?php echo !$has_initial_room_type ? 'disabled' : ''; ?>>
                                            <?php if (!$has_initial_room_type): ?>
                                                <option value="">-- Pilih Tipe Kamar Dahulu --</option>
                                            <?php else: ?>
                                                <?php for ($i = 1; $i <= max(1, $initial_available); $i++): ?>
                                                    <option value="<?php echo $i; ?>" <?php echo ($i == ($_GET['num_rooms'] ?? 1)) ? 'selected' : ''; ?>>
                                                        <?php echo $i; ?> Kamar<?php echo ($i == $initial_available) ? ' (Maksimal ' . $initial_available . ' unit)' : ''; ?>
                                                    </option>
                                                <?php endfor; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <small class="text-muted" id="rooms_hint">
                                        <?php if (!$has_initial_room_type): ?>
                                            <i class="fas fa-info-circle me-1"></i>Pilih tipe kamar terlebih dahulu untuk melihat ketersediaan unit
                                        <?php else: ?>
                                            <i class="fas fa-info-circle text-primary me-1"></i>Tersedia <strong><?php echo $initial_available; ?> unit kamar</strong> di hotel
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark small">Tanggal Check-in <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control form-control-luxury" name="check_in" id="check_in"
                                           value="<?php echo $check_in; ?>" min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark small">Tanggal Check-out <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control form-control-luxury" name="check_out" id="check_out"
                                           value="<?php echo $check_out; ?>" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                                </div>
                            </div>

                            <div class="p-3 bg-light rounded-3 border mt-3 d-flex flex-wrap justify-content-between align-items-center gap-2" id="selected_room_info_box" <?php echo !$has_initial_room_type ? 'style="display:none;"' : ''; ?>>
                                <div>
                                    <strong class="text-dark"><i class="fas fa-bed text-primary me-2"></i><span id="info_room_name"><?php echo htmlspecialchars($selected_room_type['type_name'] ?? ''); ?></span></strong>
                                    <div class="text-muted small" id="info_room_desc"><?php echo htmlspecialchars($selected_room_type['description'] ?? ''); ?></div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-success rounded-pill px-3 py-1" id="selected_room_badge"><?php echo $selected_room_type['available_rooms'] ?? $initial_available; ?> Kamar Tersedia</span>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4 border-light">

                        <!-- Step 2: Guest Count -->
                        <div class="mb-4">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <span class="step-badge">2</span>
                                <h5 class="fw-bold mb-0 text-dark" style="font-family: 'Outfit', sans-serif;">Jumlah Tamu Menginap</h5>
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-dark small">Tamu Dewasa <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control-luxury" name="adults" id="adults" 
                                           value="<?php echo $guests; ?>" min="1" max="30" required placeholder="Minimal 1">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-dark small">Tamu Anak-anak (0-12 thn) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control-luxury" name="children" id="children" 
                                           value="0" min="0" max="20" required placeholder="0 jika tidak ada">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-dark small">Total Tamu <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control-luxury bg-light" name="total_guests" id="total_guests" 
                                           value="<?php echo $guests; ?>" readonly required>
                                    <div class="text-danger small mt-1" id="capacity_warning" style="display: none;">
                                        <i class="fas fa-exclamation-triangle me-1"></i>Jumlah tamu melebihi kapasitas kamar!
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4 border-light">

                        <!-- Step 3: Customer Information -->
                        <div class="mb-4">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <span class="step-badge">3</span>
                                <h5 class="fw-bold mb-0 text-dark" style="font-family: 'Outfit', sans-serif;">Data Identitas Pemesan</h5>
                            </div>
                            
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark small">Nama Lengkap Tamu <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-luxury" name="customer_name" 
                                           value="<?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>" required placeholder="Sesuai KTP/Paspor">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark small">Alamat Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control form-control-luxury" name="customer_email" 
                                           value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>" required placeholder="nama@email.com">
                                </div>
                            </div>
                            
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark small">Nomor Telepon / WhatsApp <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-luxury" name="customer_phone" required placeholder="Contoh: 08123456789">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold text-dark small">Jenis Identitas <span class="text-danger">*</span></label>
                                    <select class="form-select form-control-luxury" name="identity_type" required>
                                        <option value="ktp">KTP</option>
                                        <option value="passport">Passport</option>
                                        <option value="sim">SIM</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold text-dark small">Nomor Identitas <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-luxury" name="identity_number" required placeholder="Nomor NIK / No Paspor">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark small">Alamat Domisili <span class="text-danger">*</span></label>
                                <textarea class="form-control form-control-luxury" name="customer_address" rows="2" required placeholder="Alamat lengkap kota asal tempat tinggal Anda..."></textarea>
                            </div>
                        </div>

                        <hr class="my-4 border-light">

                        <!-- Step 4: Special Request -->
                        <div class="mb-4">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <span class="step-badge">4</span>
                                <h5 class="fw-bold mb-0 text-dark" style="font-family: 'Outfit', sans-serif;">Permintaan Khusus</h5>
                            </div>
                            
                            <div class="mb-2">
                                <label class="form-label fw-bold text-dark small">Permintaan Khusus / Catatan <span class="text-danger">*</span></label>
                                <textarea class="form-control form-control-luxury" name="special_requests" rows="2" required
                                          placeholder="Contoh: Kamar bebas asap rokok, lantai atas, dekat lift, atau ketik 'Tidak ada' jika tidak ada permintaan khusus."></textarea>
                            </div>
                        </div>

                        <hr class="my-4 border-light">

                        <!-- Step 5: Payment Method -->
                        <div class="mb-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="step-badge">5</span>
                                    <h5 class="fw-bold mb-0 text-dark" style="font-family: 'Outfit', sans-serif;">Pilihan Metode Pembayaran</h5>
                                </div>
                                <span class="badge bg-light text-primary border px-3 py-1 rounded-pill small fw-semibold">
                                    <i class="fas fa-shield-alt me-1"></i> Transaksi Aman & Terenkripsi
                                </span>
                            </div>

                            <div class="row g-3 mb-3">
                                <!-- Transfer Bank -->
                                <div class="col-md-6">
                                    <div class="payment-card-option active h-100" onclick="selectPaymentMethod('transfer')">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <div class="form-check mb-0">
                                                <input class="form-check-input" type="radio" name="payment_method" id="pay_transfer" value="transfer" checked>
                                                <label class="form-check-label fw-bold text-dark cursor-pointer ms-1" for="pay_transfer">
                                                    Transfer Bank (Virtual Account)
                                                </label>
                                            </div>
                                            <i class="fas fa-university text-primary fs-5"></i>
                                        </div>
                                        <p class="text-muted small mb-2 ps-4" style="font-size: 0.82rem;">
                                            Transfer manual atau VA instan ke rekening resmi BCA, Mandiri, BNI, BRI.
                                        </p>
                                        <div class="ps-4 d-flex gap-1 flex-wrap">
                                            <span class="badge bg-light text-dark border" style="font-size: 0.72rem;">BCA</span>
                                            <span class="badge bg-light text-dark border" style="font-size: 0.72rem;">Mandiri</span>
                                            <span class="badge bg-light text-dark border" style="font-size: 0.72rem;">BNI</span>
                                            <span class="badge bg-light text-dark border" style="font-size: 0.72rem;">BRI</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- QRIS / E-Wallet -->
                                <div class="col-md-6">
                                    <div class="payment-card-option h-100" onclick="selectPaymentMethod('qris')">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <div class="form-check mb-0">
                                                <input class="form-check-input" type="radio" name="payment_method" id="pay_qris" value="qris">
                                                <label class="form-check-label fw-bold text-dark cursor-pointer ms-1" for="pay_qris">
                                                    QRIS / E-Wallet Instan
                                                </label>
                                            </div>
                                            <i class="fas fa-qrcode text-success fs-5"></i>
                                        </div>
                                        <p class="text-muted small mb-2 ps-4" style="font-size: 0.82rem;">
                                            Scan QRIS langsung menggunakan GoPay, OVO, DANA, ShopeePay, LinkAja.
                                        </p>
                                        <div class="ps-4 d-flex gap-1 flex-wrap">
                                            <span class="badge bg-success text-white" style="font-size: 0.72rem;">QRIS</span>
                                            <span class="badge bg-light text-dark border" style="font-size: 0.72rem;">GoPay</span>
                                            <span class="badge bg-light text-dark border" style="font-size: 0.72rem;">OVO</span>
                                            <span class="badge bg-light text-dark border" style="font-size: 0.72rem;">DANA</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Kartu Kredit / Debit -->
                                <div class="col-md-6">
                                    <div class="payment-card-option h-100" onclick="selectPaymentMethod('credit_card')">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <div class="form-check mb-0">
                                                <input class="form-check-input" type="radio" name="payment_method" id="pay_cc" value="credit_card">
                                                <label class="form-check-label fw-bold text-dark cursor-pointer ms-1" for="pay_cc">
                                                    Kartu Kredit / Debit Online
                                                </label>
                                            </div>
                                            <i class="fas fa-credit-card text-warning fs-5"></i>
                                        </div>
                                        <p class="text-muted small mb-2 ps-4" style="font-size: 0.82rem;">
                                            Pembayaran online instan via jaringan Visa, MasterCard, atau JCB.
                                        </p>
                                        <div class="ps-4 d-flex gap-1 flex-wrap">
                                            <span class="badge bg-light text-dark border" style="font-size: 0.72rem;"><i class="fab fa-cc-visa text-primary me-1"></i>Visa</span>
                                            <span class="badge bg-light text-dark border" style="font-size: 0.72rem;"><i class="fab fa-cc-mastercard text-danger me-1"></i>MasterCard</span>
                                            <span class="badge bg-light text-dark border" style="font-size: 0.72rem;"><i class="fab fa-cc-jcb text-info me-1"></i>JCB</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Bayar di Hotel -->
                                <div class="col-md-6">
                                    <div class="payment-card-option h-100" onclick="selectPaymentMethod('cash')">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <div class="form-check mb-0">
                                                <input class="form-check-input" type="radio" name="payment_method" id="pay_cash" value="cash">
                                                <label class="form-check-label fw-bold text-dark cursor-pointer ms-1" for="pay_cash">
                                                    Bayar di Hotel (Pay at Hotel)
                                                </label>
                                            </div>
                                            <i class="fas fa-hotel text-info fs-5"></i>
                                        </div>
                                        <p class="text-muted small mb-2 ps-4" style="font-size: 0.82rem;">
                                            Pesan tanpa bayar di awal. Pelunasan tunai/debit saat tiba di meja resepsionis.
                                        </p>
                                        <div class="ps-4">
                                            <span class="badge bg-info text-dark" style="font-size: 0.72rem;"><i class="fas fa-check-circle me-1"></i>Tanpa DP / Bebas Biaya Awal</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4 border-light">

                        <!-- Step 6: Price Breakdown & Final Confirmation -->
                        <div class="mb-4">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <span class="step-badge">6</span>
                                <h5 class="fw-bold mb-0 text-dark" style="font-family: 'Outfit', sans-serif;">Ringkasan Biaya Reservasi</h5>
                            </div>

                            <div class="pricing-summary-card mb-4">
                                <div class="row align-items-center g-3">
                                    <div class="col-md-6 border-md-end">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted small">Tarif Kamar:</span>
                                            <strong class="text-dark" id="price_display">Rp 0 / malam</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted small">Jumlah Kamar:</span>
                                            <strong class="text-dark" id="rooms_display">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted small">Durasi Menginap:</span>
                                            <strong class="text-dark" id="nights_display">0 malam</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted small">Total Tamu:</span>
                                            <strong class="text-dark" id="guests_display">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted small">Metode Pembayaran:</span>
                                            <span class="badge bg-primary text-white" id="pay_method_summary">Transfer Bank</span>
                                        </div>
                                        <hr class="my-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-dark fs-6">TOTAL TAGIHAN:</span>
                                            <span class="fw-extrabold fs-4 text-primary" id="total_display" style="font-family: 'Outfit', sans-serif;">Rp 0</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-2">
                                            <h6 class="fw-bold text-dark small mb-2"><i class="fas fa-shield-alt text-success me-1"></i>Ketentuan & Kebijakan:</h6>
                                            <ul class="text-muted small mb-0 ps-3" style="line-height: 1.6; font-size: 0.8rem;">
                                                <li>Check-in mulai pukul <strong><?php echo CHECK_IN_TIME; ?> WIB</strong></li>
                                                <li>Check-out maksimal pukul <strong><?php echo CHECK_OUT_TIME; ?> WIB</strong></li>
                                                <li>E-voucher instan diterbitkan otomatis setelah konfirmasi</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-grid">
                            <button type="submit" id="btnSubmit" class="btn btn-primary rounded-pill py-3 fw-bold shadow fs-5" <?php echo !$has_initial_room_type ? 'disabled' : ''; ?>>
                                <span id="btnSubmitText"><i class="fas fa-university me-2"></i> Konfirmasi & Lanjut Pembayaran Transfer</span>
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    var currentAvailableRooms = <?php echo $has_initial_room_type ? $initial_available : 0; ?>;

    function resetAvailability() {
        currentAvailableRooms = 0;
        $('#badge_available_rooms').hide().text('');
        $('#selected_room_info_box').hide();
        $('#selected_room_badge').text('-');
        
        var $select = $('#num_rooms');
        $select.empty().append('<option value="">-- Pilih Tipe Kamar Dahulu --</option>').prop('disabled', true);
        $('#rooms_hint').html('<i class="fas fa-info-circle me-1"></i>Pilih tipe kamar terlebih dahulu untuk melihat ketersediaan unit');
        $('#btnSubmit').prop('disabled', true);

        $('#price_display').text('Rp 0 / malam');
        $('#rooms_display').text('-');
        $('#guests_display').text('-');
        $('#total_display').text('Rp 0');
        $('#capacity_warning').hide();
    }

    function applyAvailability(count) {
        currentAvailableRooms = parseInt(count);
        if (isNaN(currentAvailableRooms) || currentAvailableRooms < 0) currentAvailableRooms = 0;

        var $roomType = $('#room_type_id option:selected');
        var typeName = $roomType.data('name');
        var typeDesc = $roomType.data('desc');

        if (typeName) {
            $('#info_room_name').text(typeName);
            $('#info_room_desc').text(typeDesc || '');
            $('#selected_room_info_box').show();
        }

        $('#badge_available_rooms').show().text('Maks. ' + currentAvailableRooms + ' unit');
        $('#selected_room_badge').text(currentAvailableRooms + ' Kamar Tersedia');
        
        var $select = $('#num_rooms');
        var prevVal = parseInt($select.val()) || 1;
        $select.prop('disabled', false).empty();

        if (currentAvailableRooms <= 0) {
            $select.append('<option value="0">Kamar Penuh</option>');
            $('#rooms_hint').html('<span class="text-danger fw-bold"><i class="fas fa-times-circle me-1"></i>Maaf, tipe kamar ini penuh pada tanggal yang dipilih.</span>');
            $('#btnSubmit').prop('disabled', true);
        } else {
            for (var i = 1; i <= currentAvailableRooms; i++) {
                var label = i + ' Kamar' + (i === currentAvailableRooms ? ' (Maksimal ' + currentAvailableRooms + ' unit)' : '');
                $select.append($('<option>', {
                    value: i,
                    text: label
                }));
            }
            if (prevVal > currentAvailableRooms) {
                $select.val(currentAvailableRooms);
            } else if (prevVal >= 1) {
                $select.val(prevVal);
            } else {
                $select.val(1);
            }
            $('#rooms_hint').html('<i class="fas fa-info-circle text-primary me-1"></i>Tersedia <strong>' + currentAvailableRooms + ' unit kamar</strong> di hotel');
            $('#btnSubmit').prop('disabled', false);
        }
        
        calculateTotal();
        calculateTotalGuests();
    }

    function updateAvailability() {
        var roomTypeId = $('#room_type_id').val();
        var checkIn = $('#check_in').val();
        var checkOut = $('#check_out').val();

        if (!roomTypeId) {
            resetAvailability();
            return;
        }
        if (!checkIn || !checkOut) return;

        // First apply synchronously from selected option data-available if present
        var optAvail = $('#room_type_id option:selected').data('available');
        if (typeof optAvail !== 'undefined') {
            applyAvailability(optAvail);
        }

        // Then verify asynchronously with database
        $.ajax({
            url: 'booking.php',
            type: 'GET',
            data: {
                ajax_check_availability: 1,
                room_type_id: roomTypeId,
                check_in: checkIn,
                check_out: checkOut
            },
            dataType: 'json',
            success: function(response) {
                if (response && response.status === 'success') {
                    applyAvailability(response.available_count);
                }
            }
        });
    }

    function calculateTotalGuests() {
        var roomTypeId = $('#room_type_id').val();
        if (!roomTypeId || currentAvailableRooms <= 0) {
            $('#guests_display').text('-');
            return;
        }

        var adults = parseInt($('#adults').val()) || 0;
        var children = parseInt($('#children').val()) || 0;
        var total = adults + children;
        var numRooms = parseInt($('#num_rooms').val()) || 1;

        $('#total_guests').val(total);
        $('#guests_display').text(total + ' orang (' + numRooms + ' kamar)');
        
        var roomType = $('#room_type_id');
        var capacityPerRoom = roomType.find('option:selected').data('capacity') || 2;
        var totalCapacity = capacityPerRoom * numRooms;

        if (totalCapacity > 0 && total > totalCapacity) {
            $('#capacity_warning').html('<i class="fas fa-exclamation-triangle me-1"></i>Jumlah tamu (' + total + ' org) melebihi kapasitas total ' + numRooms + ' kamar (maks. ' + totalCapacity + ' org)!').show();
        } else {
            $('#capacity_warning').hide();
        }
    }

    function calculateTotal() {
        var roomTypeId = $('#room_type_id').val();
        var checkIn = new Date($('#check_in').val());
        var checkOut = new Date($('#check_out').val());
        var numRooms = parseInt($('#num_rooms').val()) || 0;

        if (!roomTypeId || numRooms <= 0 || currentAvailableRooms <= 0) {
            $('#price_display').text('Rp 0 / malam');
            $('#rooms_display').text('-');
            $('#nights_display').text('0 malam');
            $('#total_display').text('Rp 0');
            return;
        }

        var roomType = $('#room_type_id');
        var price = roomType.find('option:selected').data('price') || 0;

        $('#rooms_display').text(numRooms + ' kamar');

        if (checkIn && checkOut && checkOut > checkIn) {
            var nights = Math.ceil((checkOut - checkIn) / (1000 * 60 * 60 * 24));
            var total = nights * price * numRooms;
            
            $('#price_display').text('Rp ' + price.toLocaleString('id-ID') + ' / malam');
            $('#nights_display').text(nights + ' malam');
            $('#total_display').text('Rp ' + total.toLocaleString('id-ID'));
        } else {
            $('#price_display').text('Rp ' + price.toLocaleString('id-ID') + ' / malam');
            $('#nights_display').text('0 malam');
            $('#total_display').text('Rp 0');
        }
    }

    $('#num_rooms').on('change', function() {
        calculateTotal();
        calculateTotalGuests();
    });

    $('#adults, #children').on('input change', calculateTotalGuests);
    
    $('#room_type_id').on('change', function() {
        var val = $(this).val();
        if (!val) {
            resetAvailability();
            return;
        }
        var opt = this.options[this.selectedIndex];
        var optAvail = opt ? opt.getAttribute('data-available') : null;
        if (optAvail !== null && typeof optAvail !== 'undefined') {
            applyAvailability(optAvail);
        }
        updateAvailability();
    });

    $('#check_in').on('change', function() {
        var checkIn = new Date($(this).val());
        var checkOut = new Date($('#check_out').val());
        
        if (checkOut <= checkIn) {
            var nextDay = new Date(checkIn);
            nextDay.setDate(nextDay.getDate() + 1);
            $('#check_out').val(nextDay.toISOString().split('T')[0]);
        }
        $('#check_out').attr('min', $(this).val());
        updateAvailability();
    });

    $('#check_out').on('change', function() {
        updateAvailability();
    });

    // Payment method selector
    window.selectPaymentMethod = function(val) {
        $('input[name="payment_method"][value="' + val + '"]').prop('checked', true);
        $('.payment-card-option').removeClass('active');
        $('input[name="payment_method"][value="' + val + '"]').closest('.payment-card-option').addClass('active');
        
        if (val === 'cash') {
            $('#btnSubmitText').html('<i class="fas fa-check-circle me-2"></i> Konfirmasi Reservasi (Bayar di Hotel)');
            $('#pay_method_summary').removeClass('bg-primary bg-success bg-warning').addClass('bg-info text-dark').text('Bayar di Hotel');
        } else if (val === 'qris') {
            $('#btnSubmitText').html('<i class="fas fa-qrcode me-2"></i> Lanjutkan Pembayaran QRIS');
            $('#pay_method_summary').removeClass('bg-primary bg-info bg-warning').addClass('bg-success text-white').text('QRIS / E-Wallet');
        } else if (val === 'credit_card') {
            $('#btnSubmitText').html('<i class="fas fa-credit-card me-2"></i> Lanjutkan Pembayaran Kartu');
            $('#pay_method_summary').removeClass('bg-primary bg-info bg-success').addClass('bg-warning text-dark').text('Kartu Kredit/Debit');
        } else {
            $('#btnSubmitText').html('<i class="fas fa-university me-2"></i> Konfirmasi & Lanjut Pembayaran Transfer');
            $('#pay_method_summary').removeClass('bg-info bg-success bg-warning').addClass('bg-primary text-white').text('Transfer Bank');
        }
    };

    $('input[name="payment_method"]').on('change', function() {
        selectPaymentMethod($(this).val());
    });

    // Run initial sync directly from DOM option
    var sel = document.getElementById('room_type_id');
    if (sel && sel.value !== '') {
        var opt = sel.options[sel.selectedIndex];
        var optAvail = opt ? opt.getAttribute('data-available') : null;
        if (optAvail !== null && typeof optAvail !== 'undefined') {
            applyAvailability(optAvail);
        }
        updateAvailability();
    } else {
        resetAvailability();
    }
});
</script>

<?php include '../includes/footer.php'; ?>