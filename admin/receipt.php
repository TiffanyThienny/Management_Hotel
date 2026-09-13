<?php
require_once '../config/init.php';
require_once '../config/database.php';

checkAdminAuth();

$payment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if ($payment_id > 0) {
    $query = "SELECT p.*, b.id as booking_id, b.booking_code, b.check_in, b.check_out, b.total_nights, b.total_guests, b.adults, b.children, b.special_requests, b.final_amount as booking_total,
                     c.full_name as customer_name, c.email as customer_email, c.phone as customer_phone, c.address as customer_address, c.identity_type, c.identity_number,
                     u.full_name as processed_by
              FROM payments p
              JOIN bookings b ON p.booking_id = b.id
              JOIN customers c ON b.customer_id = c.id
              LEFT JOIN users u ON b.user_id = u.id
              WHERE p.id = ?";
    $payment = $database->getSingle($query, [$payment_id]);
} elseif ($booking_id > 0) {
    $query = "SELECT p.*, b.id as booking_id, b.booking_code, b.check_in, b.check_out, b.total_nights, b.total_guests, b.adults, b.children, b.special_requests, b.final_amount as booking_total,
                     c.full_name as customer_name, c.email as customer_email, c.phone as customer_phone, c.address as customer_address, c.identity_type, c.identity_number,
                     u.full_name as processed_by
              FROM bookings b
              LEFT JOIN payments p ON b.id = p.booking_id
              JOIN customers c ON b.customer_id = c.id
              LEFT JOIN users u ON b.user_id = u.id
              WHERE b.id = ?";
    $payment = $database->getSingle($query, [$booking_id]);
} else {
    $payment = null;
}

if (!$payment) {
    die("Kuitansi / Nota pembayaran tidak ditemukan.");
}

// Fetch booking room details
$details_query = "SELECT bd.*, r.room_number, rt.type_name, rt.base_price
                 FROM booking_details bd
                 JOIN rooms r ON bd.room_id = r.id
                 JOIN room_types rt ON r.room_type_id = rt.id
                 WHERE bd.booking_id = ?";
$room_details = $database->getAll($details_query, [$payment['booking_id']]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kuitansi Pembayaran - <?php echo htmlspecialchars($payment['booking_code']); ?></title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            padding: 30px 0;
        }

        .receipt-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            padding: 40px;
            max-width: 800px;
            margin: 0 auto;
        }

        .receipt-header {
            border-bottom: 2px dashed #cbd5e1;
            padding-bottom: 24px;
            margin-bottom: 24px;
        }

        .hotel-brand {
            font-family: 'Outfit', sans-serif;
            font-size: 1.6rem;
            font-weight: 800;
            color: #0f172a;
        }

        .badge-paid {
            background-color: #d1fae5;
            color: #059669;
            font-weight: 700;
            padding: 8px 18px;
            border-radius: 50rem;
            font-size: 0.85rem;
            text-uppercase: uppercase;
            letter-spacing: 0.05em;
        }

        .table-receipt th {
            background-color: #f1f5f9;
            color: #475569;
            font-size: 0.75rem;
            text-uppercase: uppercase;
            letter-spacing: 0.05em;
        }

        .total-box {
            background-color: #f8fafc;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            padding: 20px;
        }

        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .receipt-card {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                max-width: 100% !important;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Action Toolbar (No Print) -->
    <div class="d-flex justify-content-between align-items-center mb-4 max-w-800 mx-auto no-print" style="max-width: 800px;">
        <a href="javascript:history.back()" class="btn btn-outline-secondary btn-md rounded-pill px-4">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-primary btn-md rounded-pill px-4 fw-bold shadow-sm">
                <i class="fas fa-print me-1"></i> Cetak / Download Nota PDF
            </button>
        </div>
    </div>

    <!-- Printable Receipt Card -->
    <div class="receipt-card">
        <!-- Header -->
        <div class="receipt-header d-flex justify-content-between align-items-start">
            <div>
                <div class="hotel-brand text-primary">
                    <i class="fas fa-hotel text-warning me-2"></i><?php echo HOTEL_NAME; ?>
                </div>
                <p class="text-muted small mb-1 mt-1"><i class="fas fa-map-marker-alt me-1"></i> <?php echo HOTEL_ADDRESS; ?></p>
                <p class="text-muted small mb-0"><i class="fas fa-phone me-1"></i> <?php echo HOTEL_PHONE; ?> &bull; <i class="fas fa-envelope me-1"></i> <?php echo HOTEL_EMAIL; ?></p>
            </div>
            <div class="text-end">
                <span class="badge-paid d-inline-block mb-2">
                    <i class="fas fa-check-circle me-1"></i> <?php echo strtoupper($payment['payment_status'] ?? 'PAID'); ?>
                </span>
                <div class="fw-extrabold text-dark fs-5" style="font-family: 'Outfit', sans-serif;">NOTA RESMI #<?php echo $payment['id']; ?></div>
                <small class="text-muted d-block">Tanggal: <?php echo date('d F Y', strtotime($payment['payment_date'] ?? $payment['created_at'])); ?></small>
                <small class="text-muted d-block">Ref: <?php echo htmlspecialchars($payment['transaction_id'] ?? 'TRX-' . str_pad($payment['id'], 6, '0', STR_PAD_LEFT)); ?></small>
            </div>
        </div>

        <!-- Customer & Booking Info -->
        <div class="row mb-4">
            <div class="col-6">
                <small class="text-uppercase fw-bold text-muted d-block mb-1" style="font-size: 0.7rem; letter-spacing: 0.05em;">Diterbitkan Untuk:</small>
                <div class="fw-bold fs-6 text-dark"><?php echo htmlspecialchars($payment['customer_name']); ?></div>
                <div class="small text-muted"><?php echo htmlspecialchars($payment['customer_email']); ?></div>
                <div class="small text-muted"><?php echo htmlspecialchars($payment['customer_phone']); ?></div>
                <div class="small text-muted"><?php echo htmlspecialchars($payment['customer_address'] ?? ''); ?></div>
            </div>
            <div class="col-6 text-end">
                <small class="text-uppercase fw-bold text-muted d-block mb-1" style="font-size: 0.7rem; letter-spacing: 0.05em;">Rincian Reservasi:</small>
                <div class="fw-bold fs-6 text-primary"><?php echo htmlspecialchars($payment['booking_code']); ?></div>
                <div class="small text-dark fw-semibold">Check-in: <?php echo date('d/m/Y', strtotime($payment['check_in'])); ?></div>
                <div class="small text-dark fw-semibold">Check-out: <?php echo date('d/m/Y', strtotime($payment['check_out'])); ?></div>
                <div class="small text-muted">Durasi: <?php echo $payment['total_nights']; ?> Malam &bull; <?php echo $payment['total_guests']; ?> Tamu</div>
            </div>
        </div>

        <!-- Room Items Table -->
        <div class="table-responsive mb-4">
            <table class="table table-receipt align-middle">
                <thead>
                    <tr>
                        <th class="ps-3">Item / Tipe Kamar</th>
                        <th class="text-center">No. Kamar</th>
                        <th class="text-center">Durasi</th>
                        <th class="text-end">Harga / Malam</th>
                        <th class="pe-3 text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($room_details)): ?>
                        <?php foreach ($room_details as $item): ?>
                        <tr>
                            <td class="ps-3">
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($item['type_name']); ?></div>
                            </td>
                            <td class="text-center fw-semibold">Kamar <?php echo htmlspecialchars($item['room_number']); ?></td>
                            <td class="text-center"><?php echo $payment['total_nights']; ?> Malam</td>
                            <td class="text-end">Rp <?php echo number_format($item['price_per_night'], 0, ',', '.'); ?></td>
                            <td class="pe-3 text-end fw-bold">Rp <?php echo number_format($item['total_price'], 0, ',', '.'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td class="ps-3">
                                <div class="fw-bold text-dark">Reservasi Hotel (<?php echo htmlspecialchars($payment['booking_code']); ?>)</div>
                            </td>
                            <td class="text-center">-</td>
                            <td class="text-center"><?php echo $payment['total_nights']; ?> Malam</td>
                            <td class="text-end">Rp <?php echo number_format($payment['amount'] / max(1, $payment['total_nights']), 0, ',', '.'); ?></td>
                            <td class="pe-3 text-end fw-bold">Rp <?php echo number_format($payment['amount'], 0, ',', '.'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Total Box & Payment Summary -->
        <div class="row mb-4">
            <div class="col-md-6 mb-3 mb-md-0">
                <div class="p-3 bg-light rounded-3">
                    <small class="text-uppercase fw-bold text-muted d-block mb-1" style="font-size: 0.7rem;">Metode Pembayaran:</small>
                    <div class="fw-bold text-dark"><i class="fas fa-credit-card me-1 text-primary"></i> <?php echo strtoupper($payment['payment_method'] ?? 'CASH'); ?></div>
                    <?php if (!empty($payment['bank_name'])): ?>
                        <small class="text-muted d-block">Bank: <?php echo htmlspecialchars($payment['bank_name']); ?> (<?php echo htmlspecialchars($payment['account_number'] ?? ''); ?>)</small>
                    <?php endif; ?>
                    <small class="text-muted d-block mt-1">Diproses oleh: <?php echo htmlspecialchars($payment['processed_by'] ?? 'Sistem Hotel'); ?></small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="total-box">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Subtotal Biaya Kamar:</span>
                        <span class="fw-semibold">Rp <?php echo number_format($payment['amount'], 0, ',', '.'); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Pajak & Layanan (Termasuk):</span>
                        <span class="fw-semibold">Rp 0</span>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-dark fs-6">TOTAL DIBAYAR:</span>
                        <span class="fw-extrabold fs-4 text-success" style="font-family: 'Outfit', sans-serif;">
                            Rp <?php echo number_format($payment['amount'], 0, ',', '.'); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Note -->
        <div class="text-center border-top pt-3 mt-4 text-muted small">
            <p class="mb-1 fw-semibold">Terima kasih atas kunjungan Anda di <?php echo HOTEL_NAME; ?>!</p>
            <p class="mb-0 text-muted" style="font-size: 0.75rem;">Kuitansi/Nota ini sah dan diterbitkan secara elektronik oleh sistem manajemen hotel.</p>
        </div>
    </div>
</div>

</body>
</html>
