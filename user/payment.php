<?php
require_once '../config/init.php';
checkUserAuth();

$page_title = "Konfirmasi & Pembayaran Reservasi - Grand Luxury Hotel";
include '../includes/header.php';

$user_id = $_SESSION['user_id'];
$booking_id = $_GET['booking_id'] ?? 0;

if (!$booking_id) {
    setFlashMessage('error', 'Booking ID tidak valid');
    redirect('history.php');
}

// Get booking details
$booking = $database->getSingle("
    SELECT b.*, 
           GROUP_CONCAT(DISTINCT r.room_number ORDER BY r.room_number SEPARATOR ', ') as room_number,
           COUNT(DISTINCT bd.id) as total_rooms_booked,
           rt.type_name, c.full_name, c.email, c.phone,
           (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE booking_id = b.id AND payment_status = 'paid') as paid_amount
    FROM bookings b
    JOIN booking_details bd ON b.id = bd.booking_id
    JOIN rooms r ON bd.room_id = r.id
    JOIN room_types rt ON r.room_type_id = rt.id
    JOIN customers c ON b.customer_id = c.id
    WHERE b.id = ? AND b.user_id = ?
    GROUP BY b.id
", [$booking_id, $user_id]);

if (!$booking) {
    setFlashMessage('error', 'Booking tidak ditemukan');
    redirect('history.php');
}

// Check if booking can be paid
if (!in_array($booking['status'], ['pending', 'confirmed'])) {
    setFlashMessage('error', 'Booking ini tidak dapat dilakukan pembayaran');
    redirect('history.php');
}

// Calculate remaining amount
$remaining_amount = max(0, $booking['final_amount'] - $booking['paid_amount']);

// Process payment
if ($_POST) {
    try {
        $amount = floatval($_POST['amount'] ?? 0);
        $payment_method = sanitizeInput($_POST['payment_method'] ?? '');
        $bank_name = trim(sanitizeInput($_POST['bank_name'] ?? ''));
        $account_number = trim(sanitizeInput($_POST['account_number'] ?? ''));
        $transaction_id = trim(sanitizeInput($_POST['transaction_id'] ?? ''));
        $notes = trim(sanitizeInput($_POST['notes'] ?? ''));

        // All fields are mandatory
        if ($amount <= 0 || empty($payment_method) || empty($transaction_id) || empty($notes)) {
            throw new Exception('Semua kolom bertanda bintang (*) wajib diisi lengkap!');
        }

        if (in_array($payment_method, ['transfer', 'credit_card']) && (empty($bank_name) || empty($account_number))) {
            throw new Exception('Nama bank dan nomor rekening/kartu pengirim wajib diisi!');
        }

        if ($amount > $remaining_amount && $remaining_amount > 0) {
            throw new Exception('Jumlah pembayaran melebihi sisa yang harus dibayar.');
        }

        $data = [
            'booking_id' => $booking_id,
            'amount' => $amount,
            'payment_method' => $payment_method,
            'payment_status' => 'pending', // Will be verified by admin / receptionist
            'transaction_id' => $transaction_id,
            'bank_name' => $bank_name,
            'account_number' => $account_number,
            'notes' => $notes
        ];

        $result = $database->insert('payments', $data);
        
        if ($result) {
            // Update booking status to confirmed if full payment submitted
            if ($amount >= $remaining_amount) {
                $database->update('bookings', ['status' => 'confirmed'], "id = $booking_id");
            }
            
            setFlashMessage('success', 'Konfirmasi pembayaran berhasil dikirim! Tim reservasi kami akan memverifikasinya segera.');
            redirect('history.php');
        } else {
            throw new Exception('Gagal menyimpan data pembayaran.');
        }
        
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }
}
?>

<style>
    .payment-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #312e81 100%);
        border-radius: 20px;
        padding: 30px;
        color: #fff;
        margin-bottom: 24px;
        border: 1px solid rgba(255, 255, 255, 0.15);
        box-shadow: 0 10px 30px rgba(0,0,0,0.06);
    }
    .payment-hero h1, .payment-hero h2, .payment-hero h3, .payment-hero h4 {
        color: #ffffff !important;
        font-weight: 800 !important;
    }
    .payment-hero p {
        color: #e2e8f0 !important;
    }
    .payment-box-card {
        background: #ffffff;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        overflow: hidden;
        margin-bottom: 24px;
    }
    .payment-box-header {
        padding: 18px 24px;
        background: #ffffff;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: space-between;
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
    .instruction-box {
        background: #f8fafc;
        border: 1.5px solid #e2e8f0;
        border-radius: 16px;
        padding: 20px;
    }
</style>

<div class="container py-4">
    
    <!-- Hero Banner -->
    <div class="payment-hero">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-warning text-dark fw-bold px-3 py-1 rounded-pill">
                        <i class="fas fa-lock me-1"></i> PEMBAYARAN AMAN
                    </span>
                    <span class="text-white-50 small">Booking #<?php echo htmlspecialchars($booking['booking_code']); ?></span>
                </div>
                <h2 class="fw-extrabold mb-1" style="font-family: 'Outfit', sans-serif; font-size: 1.85rem;">
                    Portal Pembayaran Reservasi
                </h2>
                <p class="mb-0 text-white-50">
                    Selesaikan proses pelunasan untuk mengunci reservasi kamar dan fasilitas hotel Anda.
                </p>
            </div>
            <div>
                <a href="booking_detail.php?id=<?php echo $booking_id; ?>" class="btn btn-outline-light rounded-pill px-4 fw-semibold">
                    <i class="fas fa-arrow-left me-1"></i> Detail Booking
                </a>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left: Payment Form & Method Instructions -->
        <div class="col-lg-7">
            
            <div class="payment-box-card">
                <div class="payment-box-header">
                    <h5 class="fw-bold mb-0 text-dark" style="font-family: 'Outfit', sans-serif;">
                        <i class="fas fa-wallet me-2 text-primary"></i>Formulir Transaksi
                    </h5>
                    <span class="badge bg-indigo text-white px-3 py-1 rounded-pill" style="background: #4f46e5;">Langkah Terakhir</span>
                </div>
                <div class="p-4">
                    <form method="POST" id="paymentForm">
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark small">Nominal Pembayaran <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 rounded-start-3 fw-bold">Rp</span>
                                    <input type="number" class="form-control form-control-luxury rounded-end-3 fw-bold fs-5 text-primary" name="amount" 
                                           id="amount" value="<?php echo $remaining_amount; ?>" 
                                           min="1" max="<?php echo $remaining_amount; ?>" required>
                                </div>
                                <div class="form-text text-muted small">
                                    Sisa yang harus dibayar: <strong><?php echo formatCurrency($remaining_amount); ?></strong>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark small">Metode Pembayaran <span class="text-danger">*</span></label>
                                <?php $selected_method = $_GET['method'] ?? 'transfer'; ?>
                                <select class="form-select form-control-luxury" name="payment_method" id="payment_method" required>
                                    <option value="">-- Pilih Metode Pembayaran --</option>
                                    <option value="transfer" <?php echo ($selected_method === 'transfer') ? 'selected' : ''; ?>>Transfer Bank (BCA / Mandiri / BNI / BRI)</option>
                                    <option value="credit_card" <?php echo ($selected_method === 'credit_card') ? 'selected' : ''; ?>>Kartu Kredit / Debit Online</option>
                                    <option value="cash" <?php echo ($selected_method === 'cash') ? 'selected' : ''; ?>>Bayar Tunai di Resepsionis</option>
                                </select>
                            </div>
                        </div>

                        <!-- Bank Information (shown only for transfer) -->
                        <div id="bank_info" class="p-3 bg-light rounded-3 border mb-3" style="display: none;">
                            <h6 class="fw-bold text-dark small mb-2"><i class="fas fa-university text-primary me-1"></i>Informasi Rekening Pengirim</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-dark">Nama Bank Pengirim <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-luxury" name="bank_name" id="bank_name" placeholder="Contoh: BCA, Mandiri, BRI, CIMB">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-dark">Nomor Rekening Pengirim <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-luxury" name="account_number" id="account_number" placeholder="Nomor rekening Anda">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark small">ID Transaksi / Nomor Referensi Pembayaran <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-luxury" name="transaction_id" id="transaction_id" placeholder="Contoh: TRXBCA9871239 / No. Resi Pembayaran" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark small">Catatan / Keterangan Pembayaran <span class="text-danger">*</span></label>
                            <textarea class="form-control form-control-luxury" name="notes" id="notes" rows="2" placeholder="Tuliskan keterangan pembayaran (contoh: 'Transfer via m-BCA a.n Budi' atau 'Tidak ada')..." required></textarea>
                        </div>

                        <!-- Payment Instructions Box -->
                        <div class="instruction-box mb-4">
                            <div id="transfer_instructions" style="display: none;">
                                <h6 class="fw-bold text-dark mb-2"><i class="fas fa-info-circle text-primary me-2"></i>Rekening Resmi Grand Luxury Hotel:</h6>
                                <div class="row g-2 mb-2">
                                    <div class="col-12 p-2 bg-white rounded-3 border d-flex justify-content-between align-items-center">
                                        <div><strong>Bank BCA</strong> &bull; 1234-567-890</div>
                                        <span class="badge bg-primary">a.n. PT Grand Luxury Resort</span>
                                    </div>
                                    <div class="col-12 p-2 bg-white rounded-3 border d-flex justify-content-between align-items-center">
                                        <div><strong>Bank Mandiri</strong> &bull; 0987-654-321</div>
                                        <span class="badge bg-primary">a.n. PT Grand Luxury Resort</span>
                                    </div>
                                    <div class="col-12 p-2 bg-white rounded-3 border d-flex justify-content-between align-items-center">
                                        <div><strong>Bank BNI</strong> &bull; 1122-334-455</div>
                                        <span class="badge bg-primary">a.n. PT Grand Luxury Resort</span>
                                    </div>
                                </div>
                                <small class="text-muted"><i class="fas fa-exclamation-circle me-1"></i>Sertakan kode booking <strong>#<?php echo htmlspecialchars($booking['booking_code']); ?></strong> pada berita transfer.</small>
                            </div>

                            <div id="credit_card_instructions" style="display: none;">
                                <h6 class="fw-bold text-dark mb-2"><i class="fas fa-credit-card text-warning me-2"></i>Pembayaran Kartu Kredit / Debit</h6>
                                <p class="text-muted small mb-2">Gunakan kartu berlogo Visa, MasterCard, atau JCB Anda untuk konfirmasi pelunasan.</p>
                                <div class="p-3 bg-white rounded-3 border">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="badge bg-warning text-dark"><i class="fas fa-lock me-1"></i>3D Secure Protected</span>
                                        <span class="text-muted small">Transaksi terenkripsi aman</span>
                                    </div>
                                    <small class="text-muted">Masukkan 16 digit nomor kartu / ID otorisasi transaksi pada kolom ID Transaksi di atas.</small>
                                </div>
                            </div>

                            <div id="cash_instructions" style="display: none;">
                                <h6 class="fw-bold text-dark mb-2"><i class="fas fa-hotel text-primary me-2"></i>Pembayaran Tunai di Front Desk</h6>
                                <p class="text-muted small mb-0">Anda dapat melakukan pelunasan langsung saat tiba di hotel sebelum proses serah terima kunci kamar.</p>
                            </div>

                            <div id="default_instructions">
                                <p class="text-muted small mb-0"><i class="fas fa-hand-point-up me-1 text-primary"></i>Pilih salah satu metode pembayaran di atas untuk melihat instruksi.</p>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary rounded-pill py-3 fw-bold shadow fs-6">
                                <i class="fas fa-paper-plane me-2"></i> Kirim Konfirmasi Pembayaran
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>

        <!-- Right: Booking Summary Breakdown -->
        <div class="col-lg-5">
            
            <div class="payment-box-card">
                <div class="payment-box-header">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-receipt me-2 text-primary"></i>Ringkasan Reservasi</h6>
                </div>
                <div class="p-4">
                    <div class="p-3 bg-light rounded-3 mb-3">
                        <div class="text-muted small">Tipe & Kamar:</div>
                        <h6 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($booking['type_name']); ?> - Room <?php echo htmlspecialchars($booking['room_number']); ?></h6>
                    </div>

                    <div class="row g-2 mb-3 small">
                        <div class="col-6">
                            <div class="p-2 border rounded-3 bg-white">
                                <span class="text-muted d-block" style="font-size: 0.72rem;">CHECK-IN</span>
                                <strong class="text-success"><?php echo date('d M Y', strtotime($booking['check_in'])); ?></strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 border rounded-3 bg-white">
                                <span class="text-muted d-block" style="font-size: 0.72rem;">CHECK-OUT</span>
                                <strong class="text-danger"><?php echo date('d M Y', strtotime($booking['check_out'])); ?></strong>
                            </div>
                        </div>
                    </div>

                    <ul class="list-group list-group-flush small mb-3">
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Durasi Menginap:</span>
                            <strong><?php echo $booking['total_nights']; ?> Malam</strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Jumlah Tamu:</span>
                            <strong><?php echo $booking['total_guests']; ?> Orang</strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Total Tagihan:</span>
                            <strong class="text-dark"><?php echo formatCurrency($booking['final_amount']); ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Sudah Dibayar:</span>
                            <strong class="text-success"><?php echo formatCurrency($booking['paid_amount']); ?></strong>
                        </li>
                    </ul>

                    <div class="p-3 bg-warning-subtle rounded-3 d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-warning-emphasis fw-bold small d-block">SISA TAGIHAN:</span>
                            <h4 class="fw-extrabold text-danger mb-0" style="font-family: 'Outfit', sans-serif;">
                                <?php echo formatCurrency($remaining_amount); ?>
                            </h4>
                        </div>
                        <span class="badge bg-danger rounded-pill px-3 py-2">Belum Lunas</span>
                    </div>
                </div>
            </div>

            <!-- Existing Payments List -->
            <?php
            $existing_payments = $database->getAll("
                SELECT * FROM payments 
                WHERE booking_id = ? 
                ORDER BY created_at DESC
            ", [$booking_id]);
            ?>
            <?php if (!empty($existing_payments)): ?>
            <div class="payment-box-card">
                <div class="payment-box-header">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-history me-2 text-muted"></i>Riwayat Pembayaran Sebelumnya</h6>
                </div>
                <div class="p-3">
                    <div class="list-group list-group-flush small">
                        <?php foreach ($existing_payments as $pay): ?>
                        <div class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center">
                            <div>
                                <strong class="text-dark"><?php echo formatCurrency($pay['amount']); ?></strong>
                                <div class="text-muted" style="font-size: 0.72rem;">
                                    <?php echo strtoupper($pay['payment_method']); ?> &bull; <?php echo date('d M Y, H:i', strtotime($pay['created_at'])); ?>
                                </div>
                            </div>
                            <span class="badge <?php echo $pay['payment_status'] == 'paid' ? 'bg-success' : 'bg-warning text-dark'; ?> rounded-pill px-2 py-1">
                                <?php echo strtoupper($pay['payment_status']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

</div>

<script>
$(document).ready(function() {
    $('#payment_method').on('change', function() {
        var method = $(this).val();
        $('#transfer_instructions, #credit_card_instructions, #cash_instructions, #default_instructions').hide();
        $('#bank_info').hide();
        
        if (method === 'transfer') {
            $('#transfer_instructions').show();
            $('#bank_info').show();
        } else if (method === 'credit_card') {
            $('#credit_card_instructions').show();
        } else if (method === 'cash') {
            $('#cash_instructions').show();
        } else if (method) {
            $('#default_instructions').show().html('<p class="text-muted small mb-0">Ikuti petunjuk pada gateway pembayaran Anda.</p>');
        } else {
            $('#default_instructions').show();
        }
    });

    $('#paymentForm').on('submit', function(e) {
        var method = $('#payment_method').val();
        var amount = parseFloat($('#amount').val()) || 0;
        var remaining = <?php echo $remaining_amount; ?>;
        var transactionId = $.trim($('#transaction_id').val());
        var notes = $.trim($('#notes').val());
        
        if (!method) {
            alert('Harap pilih metode pembayaran.');
            e.preventDefault();
            return;
        }

        if (amount <= 0) {
            alert('Nominal pembayaran harus lebih dari 0.');
            e.preventDefault();
            return;
        }

        if (remaining > 0 && amount > remaining) {
            alert('Jumlah pembayaran tidak boleh melebihi sisa tagihan.');
            e.preventDefault();
            return;
        }

        if (method === 'transfer') {
            var bankName = $.trim($('#bank_name').val());
            var accountNumber = $.trim($('#account_number').val());
            if (!bankName || !accountNumber) {
                alert('Harap masukkan nama bank dan nomor rekening pengirim.');
                e.preventDefault();
                return;
            }
        }

        if (!transactionId) {
            alert('Harap masukkan ID Transaksi / Nomor Referensi Pembayaran.');
            e.preventDefault();
            return;
        }

        if (!notes) {
            alert('Harap isi Catatan / Keterangan Pembayaran.');
            e.preventDefault();
            return;
        }
    });

    $('#payment_method').trigger('change');
});
</script>

<?php include '../includes/footer.php'; ?>