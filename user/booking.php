<?php
require_once '../config/init.php';
checkUserAuth();

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
        
        // Validate dates
        $date_validation = validateDateRange($_POST['check_in'], $_POST['check_out']);
        if ($date_validation !== true) {
            throw new Exception($date_validation);
        }
        
        // Check room availability
        $available_rooms = getAvailableRoomsByType($db, $_POST['room_type_id'], $_POST['check_in'], $_POST['check_out']);
        if (empty($available_rooms)) {
            throw new Exception('Maaf, tidak ada kamar tersedia untuk tipe dan tanggal yang dipilih.');
        }
        
        // Get room type details
        $room_type = $database->getSingle("SELECT * FROM room_types WHERE id = ?", [$_POST['room_type_id']]);
        if (!$room_type) {
            throw new Exception('Tipe kamar tidak valid.');
        }
        
        // Check capacity
        if ($_POST['total_guests'] > $room_type['capacity']) {
            throw new Exception('Jumlah tamu melebihi kapasitas kamar.');
        }
        
        // Create or get customer
        $customer_query = "SELECT id FROM customers WHERE email = ?";
        $customer_stmt = $db->prepare($customer_query);
        $customer_stmt->execute([$_POST['customer_email']]);
        
        if ($customer_stmt->rowCount() > 0) {
            $customer = $customer_stmt->fetch(PDO::FETCH_ASSOC);
            $customer_id = $customer['id'];
        } else {
            $customer_data = [
                'full_name' => sanitizeInput($_POST['customer_name']),
                'email' => sanitizeInput($_POST['customer_email']),
                'phone' => sanitizeInput($_POST['customer_phone']),
                'identity_type' => sanitizeInput($_POST['identity_type']),
                'identity_number' => sanitizeInput($_POST['identity_number']),
                'address' => sanitizeInput($_POST['customer_address'])
            ];
            
            $customer_query = "INSERT INTO customers (full_name, email, phone, identity_type, identity_number, address) 
                             VALUES (:full_name, :email, :phone, :identity_type, :identity_number, :address)";
            $customer_stmt = $db->prepare($customer_query);
            $customer_stmt->execute($customer_data);
            $customer_id = $db->lastInsertId();
        }
        
        // Calculate total amount
        $nights = calculateNights($_POST['check_in'], $_POST['check_out']);
        $total_amount = $nights * $room_type['base_price'];
        
        // Create booking
        $booking_data = [
            'booking_code' => generateBookingCode(),
            'customer_id' => $customer_id,
            'user_id' => $_SESSION['user_id'],
            'check_in' => sanitizeInput($_POST['check_in']),
            'check_out' => sanitizeInput($_POST['check_out']),
            'total_nights' => $nights,
            'total_guests' => intval($_POST['total_guests']),
            'total_amount' => $total_amount,
            'final_amount' => $total_amount,
            'special_requests' => sanitizeInput($_POST['special_requests']),
            'adults' => intval($_POST['adults']),
            'children' => intval($_POST['children'])
        ];
        
        $booking_query = "INSERT INTO bookings (booking_code, customer_id, user_id, check_in, check_out, total_nights, total_guests, total_amount, final_amount, special_requests, adults, children) 
                         VALUES (:booking_code, :customer_id, :user_id, :check_in, :check_out, :total_nights, :total_guests, :total_amount, :final_amount, :special_requests, :adults, :children)";
        $booking_stmt = $db->prepare($booking_query);
        $booking_stmt->execute($booking_data);
        $booking_id = $db->lastInsertId();
        
        // Get first available room
        $room_id = $available_rooms[0]['id'];
        
        // Create booking detail
        $detail_data = [
            'booking_id' => $booking_id,
            'room_id' => $room_id,
            'price_per_night' => $room_type['base_price'],
            'total_price' => $total_amount
        ];
        
        $detail_query = "INSERT INTO booking_details (booking_id, room_id, price_per_night, total_price) 
                        VALUES (:booking_id, :room_id, :price_per_night, :total_price)";
        $detail_stmt = $db->prepare($detail_query);
        $detail_stmt->execute($detail_data);
        
        // Update room status
        $room_query = "UPDATE rooms SET status = 'occupied' WHERE id = ?";
        $room_stmt = $db->prepare($room_query);
        $room_stmt->execute([$room_id]);
        
        $db->commit();
        
        // Redirect to booking confirmation
        setFlashMessage('success', 'Reservasi berhasil dibuat! Silakan lanjutkan ke pembayaran.');
        redirect("payment.php?booking_id=" . $booking_id);
        
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
                    AND (b.check_in <= ? AND b.check_out >= ?)
                )) as available_rooms
        FROM room_types rt
        WHERE rt.id = ? AND rt.is_available = 1
    ", [$check_out, $check_in, $room_type_id]);
}

// Get all room types for dropdown
$room_types = $database->getAll("SELECT * FROM room_types WHERE is_available = 1 ORDER BY base_price ASC");
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
                    Lengkapi data reservasi Anda untuk proses check-in yang cepat dan nyaman.
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
                                    <select class="form-select form-control-luxury" name="room_type_id" id="room_type_id" required 
                                            <?php echo $selected_room_type ? 'disabled' : ''; ?>>
                                        <option value="">-- Pilih Tipe Kamar --</option>
                                        <?php foreach ($room_types as $type): ?>
                                        <option value="<?php echo $type['id']; ?>" 
                                            data-price="<?php echo $type['base_price']; ?>"
                                            data-capacity="<?php echo $type['capacity']; ?>"
                                            <?php echo ($selected_room_type && $selected_room_type['id'] == $type['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($type['type_name']); ?> - <?php echo formatCurrency($type['base_price']); ?>/malam (Maks. <?php echo $type['capacity']; ?> org)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($selected_room_type): ?>
                                        <input type="hidden" name="room_type_id" value="<?php echo $selected_room_type['id']; ?>">
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold text-dark small">Tanggal Check-in <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control form-control-luxury" name="check_in" 
                                           value="<?php echo $check_in; ?>" min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold text-dark small">Tanggal Check-out <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control form-control-luxury" name="check_out" 
                                           value="<?php echo $check_out; ?>" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                                </div>
                            </div>

                            <?php if ($selected_room_type): ?>
                            <div class="p-3 bg-light rounded-3 border mt-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div>
                                    <strong class="text-dark"><i class="fas fa-bed text-primary me-2"></i><?php echo htmlspecialchars($selected_room_type['type_name']); ?></strong>
                                    <div class="text-muted small"><?php echo htmlspecialchars($selected_room_type['description']); ?></div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-success rounded-pill px-3 py-1"><?php echo $selected_room_type['available_rooms']; ?> Kamar Tersedia</span>
                                </div>
                            </div>
                            <?php endif; ?>
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
                                           value="<?php echo $guests; ?>" min="1" max="10" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-dark small">Tamu Anak-anak (0-12 thn)</label>
                                    <input type="number" class="form-control form-control-luxury" name="children" id="children" 
                                           value="0" min="0" max="5">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-dark small">Total Tamu</label>
                                    <input type="number" class="form-control form-control-luxury bg-light" name="total_guests" id="total_guests" 
                                           value="<?php echo $guests; ?>" readonly>
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
                                    <label class="form-label fw-bold text-dark small">Jenis Identitas</label>
                                    <select class="form-select form-control-luxury" name="identity_type">
                                        <option value="ktp">KTP</option>
                                        <option value="passport">Passport</option>
                                        <option value="sim">SIM</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold text-dark small">Nomor Identitas</label>
                                    <input type="text" class="form-control form-control-luxury" name="identity_number" placeholder="Nomor NIK / No Paspor">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark small">Alamat Domisili</label>
                                <textarea class="form-control form-control-luxury" name="customer_address" rows="2" placeholder="Alamat lengkap kota asal..."></textarea>
                            </div>
                        </div>

                        <hr class="my-4 border-light">

                        <!-- Step 4: Special Request & Price Breakdown -->
                        <div class="mb-4">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <span class="step-badge">4</span>
                                <h5 class="fw-bold mb-0 text-dark" style="font-family: 'Outfit', sans-serif;">Permintaan Khusus & Ringkasan Biaya</h5>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold text-dark small">Permintaan Khusus (Opsional)</label>
                                <textarea class="form-control form-control-luxury" name="special_requests" rows="2" 
                                          placeholder="Contoh: Kamar bebas asap rokok, lantai atas, check-in lebih awal jika memungkinkan..."></textarea>
                            </div>

                            <div class="pricing-summary-card mb-4">
                                <div class="row align-items-center g-3">
                                    <div class="col-md-6 border-md-end">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted small">Tarif per Malam:</span>
                                            <strong class="text-dark" id="price_display">Rp 0</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted small">Durasi Menginap:</span>
                                            <strong class="text-dark" id="nights_display">0 malam</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted small">Total Tamu:</span>
                                            <strong class="text-dark" id="guests_display">0 orang</strong>
                                        </div>
                                        <hr class="my-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-dark fs-6">TOTAL PEMBAYARAN:</span>
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
                            <button type="submit" class="btn btn-primary rounded-pill py-3 fw-bold shadow fs-5">
                                <i class="fas fa-check-circle me-2"></i> Konfirmasi & Lanjut ke Pembayaran
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
    function calculateTotalGuests() {
        var adults = parseInt($('#adults').val()) || 0;
        var children = parseInt($('#children').val()) || 0;
        var total = adults + children;
        $('#total_guests').val(total);
        $('#guests_display').text(total + ' orang');
        
        var roomType = $('#room_type_id');
        var capacity = roomType.find('option:selected').data('capacity') || 0;
        if (capacity > 0 && total > capacity) {
            $('#capacity_warning').show();
        } else {
            $('#capacity_warning').hide();
        }
    }

    function calculateTotal() {
        var checkIn = new Date($('input[name="check_in"]').val());
        var checkOut = new Date($('input[name="check_out"]').val());
        var roomType = $('#room_type_id');
        var price = roomType.find('option:selected').data('price') || 0;
        
        if (checkIn && checkOut && checkOut > checkIn) {
            var nights = Math.ceil((checkOut - checkIn) / (1000 * 60 * 60 * 24));
            var total = nights * price;
            
            $('#price_display').text('Rp ' + price.toLocaleString('id-ID'));
            $('#nights_display').text(nights + ' malam');
            $('#total_display').text('Rp ' + total.toLocaleString('id-ID'));
        } else {
            $('#price_display').text('Rp 0');
            $('#nights_display').text('0 malam');
            $('#total_display').text('Rp 0');
        }
    }

    $('#adults, #children').on('input change', calculateTotalGuests);
    $('input[name="check_in"], input[name="check_out"]').on('change', calculateTotal);
    $('#room_type_id').on('change', function() {
        calculateTotal();
        calculateTotalGuests();
    });

    $('input[name="check_in"]').on('change', function() {
        var checkIn = new Date($(this).val());
        var checkOut = new Date($('input[name="check_out"]').val());
        
        if (checkOut <= checkIn) {
            var nextDay = new Date(checkIn);
            nextDay.setDate(nextDay.getDate() + 1);
            $('input[name="check_out"]').val(nextDay.toISOString().split('T')[0]);
        }
        $('input[name="check_out"]').attr('min', $(this).val());
        calculateTotal();
    });

    calculateTotalGuests();
    calculateTotal();
});
</script>

<?php include '../includes/footer.php'; ?>