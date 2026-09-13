<?php
require_once '../config/init.php';
checkAdminAuth();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Process payment actions
if (isset($_GET['update_status']) && $id > 0) {
    $new_status = sanitizeInput($_GET['update_status']);
    $update_data = ['payment_status' => $new_status];
    
    if ($new_status == 'paid') {
        $update_data['payment_date'] = date('Y-m-d H:i:s');
    }
    
    $result = $database->update('payments', $update_data, "id = $id");
    
    if ($result) {
        setFlashMessage('success', 'Status pembayaran berhasil diperbarui');
        
        // If payment is paid, update booking status to confirmed
        if ($new_status == 'paid') {
            $payment = $database->getSingle("SELECT booking_id FROM payments WHERE id = ?", [$id]);
            if ($payment) {
                $database->update('bookings', ['status' => 'confirmed'], "id = {$payment['booking_id']}");
            }
        }
    } else {
        setFlashMessage('error', 'Gagal memperbarui status pembayaran');
    }
    redirect('payments.php');
}

// Process form submission for manual payment
if ($_POST && $action == 'create') {
    $data = [
        'booking_id' => intval($_POST['booking_id']),
        'amount' => floatval($_POST['amount']),
        'payment_method' => sanitizeInput($_POST['payment_method']),
        'payment_status' => sanitizeInput($_POST['payment_status']),
        'transaction_id' => sanitizeInput($_POST['transaction_id']),
        'bank_name' => sanitizeInput($_POST['bank_name']),
        'account_number' => sanitizeInput($_POST['account_number']),
        'notes' => sanitizeInput($_POST['notes'])
    ];
    
    if ($data['payment_status'] == 'paid') {
        $data['payment_date'] = date('Y-m-d H:i:s');
    }
    
    $result = $database->insert('payments', $data);
    
    if ($result) {
        // Update booking status if payment is paid
        if ($data['payment_status'] == 'paid') {
            $database->update('bookings', ['status' => 'confirmed'], "id = {$data['booking_id']}");
        }
        
        setFlashMessage('success', 'Pembayaran berhasil dicatat');
        redirect('payments.php');
    } else {
        setFlashMessage('error', 'Gagal mencatat pembayaran');
    }
}

$page_title = "Management Pembayaran";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/admin-sidebar.php'; ?>

    <!-- Main Content -->
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Management Pembayaran</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="?action=create" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Input Pembayaran
                </a>
            </div>
        </div>

        <?php if ($action == 'list'): ?>
        <!-- Payment List -->
        <div class="card shadow">
            <div class="card-header">
                <h5 class="card-title mb-0">Daftar Pembayaran</h5>
            </div>
            <div class="card-body">
                <!-- Filter Form -->
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Status</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">Semua Status</option>
                            <option value="pending" <?php echo isset($_GET['status']) && $_GET['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="paid" <?php echo isset($_GET['status']) && $_GET['status'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="failed" <?php echo isset($_GET['status']) && $_GET['status'] == 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Metode</label>
                        <select name="method" class="form-select" onchange="this.form.submit()">
                            <option value="">Semua Metode</option>
                            <option value="cash" <?php echo isset($_GET['method']) && $_GET['method'] == 'cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="transfer" <?php echo isset($_GET['method']) && $_GET['method'] == 'transfer' ? 'selected' : ''; ?>>Transfer</option>
                            <option value="credit_card" <?php echo isset($_GET['method']) && $_GET['method'] == 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                            <option value="qris" <?php echo isset($_GET['method']) && $_GET['method'] == 'qris' ? 'selected' : ''; ?>>QRIS</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Dari Tanggal</label>
                        <input type="date" name="start_date" class="form-control datepicker" 
                               value="<?php echo $_GET['start_date'] ?? ''; ?>" onchange="this.form.submit()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">&nbsp;</label>
                        <a href="payments.php" class="btn btn-secondary w-100">Reset</a>
                    </div>
                </form>

                <!-- Payment Statistics -->
                <div class="row g-3 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                            <div>
                                <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Total Pendapatan</span>
                                <h4 class="fw-extrabold mb-0 mt-1 text-success" style="font-family: 'Outfit', sans-serif;">
                                    <?php echo formatCurrency($database->getSingle("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_status = 'paid'")['total']); ?>
                                </h4>
                                <small class="text-muted"><i class="fas fa-coins me-1 text-success"></i>Pembayaran Lunas</small>
                            </div>
                            <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #d1fae5; color: #059669; width: 50px; height: 50px;">
                                <i class="fas fa-wallet fs-4"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                            <div>
                                <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Pembayaran Sukses</span>
                                <h4 class="fw-extrabold mb-0 mt-1 text-primary" style="font-family: 'Outfit', sans-serif;">
                                    <?php echo $database->getSingle("SELECT COUNT(*) as count FROM payments WHERE payment_status = 'paid'")['count']; ?>
                                </h4>
                                <small class="text-muted"><i class="fas fa-check-circle me-1 text-primary"></i>Transaksi Berhasil</small>
                            </div>
                            <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #e0f2fe; color: #0284c7; width: 50px; height: 50px;">
                                <i class="fas fa-check-circle fs-4"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                            <div>
                                <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Menunggu Pembayaran</span>
                                <h4 class="fw-extrabold mb-0 mt-1 text-warning" style="font-family: 'Outfit', sans-serif;">
                                    <?php echo $database->getSingle("SELECT COUNT(*) as count FROM payments WHERE payment_status = 'pending'")['count']; ?>
                                </h4>
                                <small class="text-muted"><i class="fas fa-clock me-1 text-warning"></i>Pending Konfirmasi</small>
                            </div>
                            <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #fef3c7; color: #d97706; width: 50px; height: 50px;">
                                <i class="fas fa-clock fs-4"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                            <div>
                                <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Pembayaran Gagal</span>
                                <h4 class="fw-extrabold mb-0 mt-1 text-danger" style="font-family: 'Outfit', sans-serif;">
                                    <?php echo $database->getSingle("SELECT COUNT(*) as count FROM payments WHERE payment_status = 'failed'")['count']; ?>
                                </h4>
                                <small class="text-muted"><i class="fas fa-times-circle me-1 text-danger"></i>Gagal / Batal</small>
                            </div>
                            <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #fee2e2; color: #dc2626; width: 50px; height: 50px;">
                                <i class="fas fa-times-circle fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Booking</th>
                                <th>Customer</th>
                                <th>Jumlah</th>
                                <th>Metode</th>
                                <th>Status</th>
                                <th>Tanggal</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Build query with filters
                            $query = "SELECT p.*, b.booking_code, c.full_name, b.final_amount
                                      FROM payments p
                                      JOIN bookings b ON p.booking_id = b.id
                                      JOIN customers c ON b.customer_id = c.id
                                      WHERE 1=1";
                            
                            $params = [];
                            
                            if (isset($_GET['status']) && $_GET['status'] != '') {
                                $query .= " AND p.payment_status = ?";
                                $params[] = $_GET['status'];
                            }
                            
                            if (isset($_GET['method']) && $_GET['method'] != '') {
                                $query .= " AND p.payment_method = ?";
                                $params[] = $_GET['method'];
                            }
                            
                            if (isset($_GET['start_date']) && $_GET['start_date'] != '') {
                                $query .= " AND DATE(p.created_at) >= ?";
                                $params[] = $_GET['start_date'];
                            }
                            
                            $query .= " ORDER BY p.created_at DESC";
                            
                            $payments = $database->getAll($query, $params);
                            
                            foreach ($payments as $payment):
                            ?>
                            <tr>
                                <td>
                                    <strong>#<?php echo $payment['id']; ?></strong>
                                    <?php if ($payment['transaction_id']): ?>
                                        <br><small class="text-muted">Ref: <?php echo $payment['transaction_id']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="booking_detail.php?id=<?php echo $payment['booking_id']; ?>" class="fw-bold text-primary text-decoration-none">
                                        <?php echo $payment['booking_code']; ?>
                                    </a>
                                </td>
                                <td><span class="fw-semibold text-dark"><?php echo htmlspecialchars($payment['full_name']); ?></span></td>
                                <td>
                                    <strong><?php echo formatCurrency($payment['amount']); ?></strong>
                                    <?php if ($payment['amount'] < $payment['final_amount']): ?>
                                        <br><small class="text-warning">Partial</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?php 
                                        $method_labels = [
                                            'cash' => 'Cash',
                                            'transfer' => 'Transfer',
                                            'credit_card' => 'Credit Card',
                                            'debit_card' => 'Debit Card',
                                            'qris' => 'QRIS',
                                            'ovo' => 'OVO',
                                            'gopay' => 'GoPay'
                                        ];
                                        echo $method_labels[$payment['payment_method']] ?? $payment['payment_method'];
                                        ?>
                                    </span>
                                    <?php if ($payment['bank_name']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($payment['bank_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $st = $payment['payment_status'];
                                    $st_badge = [
                                        'paid' => 'bg-success text-white',
                                        'pending' => 'bg-warning text-dark',
                                        'failed' => 'bg-danger text-white',
                                        'refunded' => 'bg-secondary text-white'
                                    ][$st] ?? 'bg-secondary text-white';
                                    ?>
                                    <span class="badge <?php echo $st_badge; ?> px-3 py-2 rounded-pill fw-bold" style="font-size: 0.72rem;">
                                        <?php echo strtoupper($st); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($payment['payment_date']): ?>
                                        <div class="small fw-semibold text-dark"><?php echo date('d/m/Y H:i', strtotime($payment['payment_date'])); ?></div>
                                    <?php else: ?>
                                        <small class="text-muted">-</small>
                                    <?php endif; ?>
                                    <small class="text-muted" style="font-size: 0.72rem;">Created: <?php echo date('d/m/Y', strtotime($payment['created_at'])); ?></small>
                                </td>
                                <td class="table-actions text-center">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="fas fa-cog me-1"></i> Aksi
                                        </button>
                                        <ul class="dropdown-menu shadow-lg border-0 rounded-3 mt-1">
                                            <li><a class="dropdown-item py-2" href="payment_detail.php?id=<?php echo $payment['id']; ?>">
                                                <i class="fas fa-eye me-2 text-primary"></i>Detail Transaksi
                                            </a></li>
                                            <?php if ($payment['payment_status'] == 'pending'): ?>
                                                <li><a class="dropdown-item py-2 text-success" href="?id=<?php echo $payment['id']; ?>&update_status=paid" onclick="return confirm('Konfirmasi pembayaran ini sebagai LUNAS?')">
                                                    <i class="fas fa-check-circle me-2"></i>Konfirmasi Lunas
                                                </a></li>
                                                <li><a class="dropdown-item py-2 text-danger" href="?id=<?php echo $payment['id']; ?>&update_status=failed" onclick="return confirm('Tandai sebagai GAGAL?')">
                                                    <i class="fas fa-times-circle me-2"></i>Tandai Gagal
                                                </a></li>
                                            <?php endif; ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item py-2 fw-semibold" href="receipt.php?id=<?php echo $payment['id']; ?>" target="_blank">
                                                <i class="fas fa-receipt me-2 text-warning"></i>Cetak / Download Nota PDF
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
        <!-- Create Payment Form -->
        <div class="card shadow">
            <div class="card-header">
                <h5 class="card-title mb-0">Input Pembayaran Manual</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Booking *</label>
                                <select class="form-select" name="booking_id" id="booking_id" required>
                                    <option value="">Pilih Booking</option>
                                    <?php
                                    $bookings = $database->getAll("
                                        SELECT b.id, b.booking_code, c.full_name, b.final_amount,
                                               (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE booking_id = b.id AND payment_status != 'refunded') as paid_amount
                                        FROM bookings b
                                        JOIN customers c ON b.customer_id = c.id
                                        WHERE b.status IN ('confirmed', 'checked_in')
                                        ORDER BY b.created_at DESC
                                    ");
                                    foreach ($bookings as $booking):
                                        $remaining = $booking['final_amount'] - $booking['paid_amount'];
                                    ?>
                                    <option value="<?php echo $booking['id']; ?>" data-amount="<?php echo $remaining; ?>">
                                        <?php echo $booking['booking_code']; ?> - <?php echo $booking['full_name']; ?> 
                                        (Total: <?php echo formatCurrency($booking['final_amount']); ?>, 
                                        Paid: <?php echo formatCurrency($booking['paid_amount']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Jumlah Pembayaran *</label>
                                <input type="number" class="form-control" name="amount" id="amount" required>
                                <div class="form-text">Sisa yang harus dibayar: <span id="remaining_amount">Rp 0</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Metode Pembayaran *</label>
                                <select class="form-select" name="payment_method" required>
                                    <option value="cash">Cash</option>
                                    <option value="transfer">Transfer Bank</option>
                                    <option value="credit_card">Credit Card</option>
                                    <option value="debit_card">Debit Card</option>
                                    <option value="qris">QRIS</option>
                                    <option value="ovo">OVO</option>
                                    <option value="gopay">GoPay</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Status Pembayaran *</label>
                                <select class="form-select" name="payment_status" required>
                                    <option value="pending">Pending</option>
                                    <option value="paid">Paid</option>
                                    <option value="failed">Failed</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">ID Transaksi</label>
                                <input type="text" class="form-control" name="transaction_id" placeholder="Nomor referensi transaksi...">
                            </div>
                        </div>
                    </div>

                    <div class="row" id="bank_info" style="display: none;">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nama Bank</label>
                                <input type="text" class="form-control" name="bank_name" placeholder="Nama bank...">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">No. Rekening</label>
                                <input type="text" class="form-control" name="account_number" placeholder="Nomor rekening...">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Catatan</label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="Catatan tambahan..."></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Simpan Pembayaran
                        </button>
                        <a href="payments.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>

        <script>
        $(document).ready(function() {
            // Show bank info when transfer is selected
            $('select[name="payment_method"]').on('change', function() {
                if ($(this).val() === 'transfer') {
                    $('#bank_info').show();
                } else {
                    $('#bank_info').hide();
                }
            });

            // Update amount when booking is selected
            $('#booking_id').on('change', function() {
                var selectedOption = $(this).find('option:selected');
                var remaining = selectedOption.data('amount') || 0;
                $('#amount').val(remaining);
                $('#remaining_amount').text('Rp ' + remaining.toLocaleString());
            });

            // Trigger change on load
            $('select[name="payment_method"]').trigger('change');
            $('#booking_id').trigger('change');
        });
        </script>
        <?php endif; ?>
    </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>