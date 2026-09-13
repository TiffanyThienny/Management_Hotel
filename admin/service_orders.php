<?php
require_once '../config/init.php';
checkAdminAuth();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Process service order actions
if (isset($_GET['update_status']) && $id > 0) {
    $new_status = sanitizeInput($_GET['update_status']);
    $result = $database->update('service_orders', ['status' => $new_status], "id = $id");
    
    if ($result) {
        setFlashMessage('success', 'Status service order berhasil diperbarui');
    } else {
        setFlashMessage('error', 'Gagal memperbarui status service order');
    }
    redirect('service_orders.php');
}

// Process form submission for new service order
if ($_POST && $action == 'create') {
    $data = [
        'booking_id' => intval($_POST['booking_id']),
        'service_id' => intval($_POST['service_id']),
        'quantity' => intval($_POST['quantity']),
        'total_price' => floatval($_POST['total_price']),
        'status' => sanitizeInput($_POST['status']),
        'notes' => sanitizeInput($_POST['notes'])
    ];

    $result = $database->insert('service_orders', $data);
    
    if ($result) {
        setFlashMessage('success', 'Service order berhasil dibuat');
        redirect('service_orders.php');
    } else {
        setFlashMessage('error', 'Gagal membuat service order');
    }
}

$page_title = "Management Service Orders";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/admin-sidebar.php'; ?>

    <!-- Main Content -->
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Management Service Orders</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="?action=create" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Tambah Service Order
                </a>
            </div>
        </div>

        <?php if ($action == 'list'): ?>
        <!-- Service Orders Statistics -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Total Pesanan</span>
                        <h4 class="fw-extrabold mb-0 mt-1 text-primary" style="font-family: 'Outfit', sans-serif;">
                            <?php echo $database->getSingle("SELECT COUNT(*) as count FROM service_orders")['count']; ?>
                        </h4>
                        <small class="text-muted"><i class="fas fa-concierge-bell me-1 text-primary"></i>Semua Pesanan</small>
                    </div>
                    <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #e0e7ff; color: #4338ca; width: 50px; height: 50px;">
                        <i class="fas fa-concierge-bell fs-4"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Menunggu Konfirmasi</span>
                        <h4 class="fw-extrabold mb-0 mt-1 text-warning" style="font-family: 'Outfit', sans-serif;">
                            <?php echo $database->getSingle("SELECT COUNT(*) as count FROM service_orders WHERE status = 'pending'")['count']; ?>
                        </h4>
                        <small class="text-muted"><i class="fas fa-clock me-1 text-warning"></i>Pesanan Baru</small>
                    </div>
                    <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #fef3c7; color: #d97706; width: 50px; height: 50px;">
                        <i class="fas fa-clock fs-4"></i>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Pesanan Selesai</span>
                        <h4 class="fw-extrabold mb-0 mt-1 text-success" style="font-family: 'Outfit', sans-serif;">
                            <?php echo $database->getSingle("SELECT COUNT(*) as count FROM service_orders WHERE status = 'completed'")['count']; ?>
                        </h4>
                        <small class="text-muted"><i class="fas fa-check-circle me-1 text-success"></i>Telah Dilayani</small>
                    </div>
                    <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #d1fae5; color: #059669; width: 50px; height: 50px;">
                        <i class="fas fa-check-circle fs-4"></i>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 h-100 d-flex flex-row align-items-center justify-content-between bg-white border">
                    <div>
                        <span class="text-uppercase fw-bold text-muted small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Total Pendapatan</span>
                        <h4 class="fw-extrabold mb-0 mt-1 text-success" style="font-family: 'Outfit', sans-serif;">
                            <?php echo formatCurrency($database->getSingle("SELECT COALESCE(SUM(total_price), 0) as total FROM service_orders WHERE status = 'completed'")['total']); ?>
                        </h4>
                        <small class="text-muted"><i class="fas fa-wallet me-1 text-success"></i>Revenue Layanan</small>
                    </div>
                    <div class="rounded-3 p-3 d-flex align-items-center justify-content-center" style="background: #e0f2fe; color: #0284c7; width: 50px; height: 50px;">
                        <i class="fas fa-wallet fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Service Orders List -->
        <div class="card shadow">
            <div class="card-header">
                <h5 class="card-title mb-0">Daftar Service Orders</h5>
            </div>
            <div class="card-body">
                <!-- Filter Form -->
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Status</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">Semua Status</option>
                            <option value="pending" <?php echo isset($_GET['status']) && $_GET['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo isset($_GET['status']) && $_GET['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="completed" <?php echo isset($_GET['status']) && $_GET['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo isset($_GET['status']) && $_GET['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Service</label>
                        <select name="service" class="form-select" onchange="this.form.submit()">
                            <option value="">Semua Service</option>
                            <?php
                            $services = $database->getAll("SELECT * FROM services");
                            foreach ($services as $service):
                            ?>
                            <option value="<?php echo $service['id']; ?>" <?php echo isset($_GET['service']) && $_GET['service'] == $service['id'] ? 'selected' : ''; ?>>
                                <?php echo $service['service_name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Dari Tanggal</label>
                        <input type="date" name="start_date" class="form-control datepicker" 
                               value="<?php echo $_GET['start_date'] ?? ''; ?>" onchange="this.form.submit()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">&nbsp;</label>
                        <a href="service_orders.php" class="btn btn-secondary w-100">Reset</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Booking</th>
                                <th>Service</th>
                                <th>Customer</th>
                                <th>Quantity</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Tanggal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Build query with filters
                            $query = "SELECT so.*, s.service_name, s.price, b.booking_code, c.full_name
                                      FROM service_orders so
                                      JOIN services s ON so.service_id = s.id
                                      JOIN bookings b ON so.booking_id = b.id
                                      JOIN customers c ON b.customer_id = c.id
                                      WHERE 1=1";
                            
                            $params = [];
                            
                            if (isset($_GET['status']) && $_GET['status'] != '') {
                                $query .= " AND so.status = ?";
                                $params[] = $_GET['status'];
                            }
                            
                            if (isset($_GET['service']) && $_GET['service'] != '') {
                                $query .= " AND so.service_id = ?";
                                $params[] = $_GET['service'];
                            }
                            
                            if (isset($_GET['start_date']) && $_GET['start_date'] != '') {
                                $query .= " AND DATE(so.created_at) >= ?";
                                $params[] = $_GET['start_date'];
                            }
                            
                            $query .= " ORDER BY so.created_at DESC";
                            
                            $service_orders = $database->getAll($query, $params);
                            
                            foreach ($service_orders as $order):
                            ?>
                            <tr>
                                <td>
                                    <strong>#<?php echo $order['id']; ?></strong>
                                </td>
                                <td>
                                    <a href="booking_detail.php?id=<?php echo $order['booking_id']; ?>" class="text-decoration-none">
                                        <?php echo $order['booking_code']; ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo $order['service_name']; ?></div>
                                    <small class="text-muted"><?php echo formatCurrency($order['price']); ?> per item</small>
                                </td>
                                <td><?php echo $order['full_name']; ?></td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $order['quantity']; ?>x</span>
                                </td>
                                <td>
                                    <strong><?php echo formatCurrency($order['total_price']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge 
                                        <?php 
                                        $status_colors = [
                                            'pending' => 'bg-warning',
                                            'processing' => 'bg-info',
                                            'completed' => 'bg-success',
                                            'cancelled' => 'bg-danger'
                                        ];
                                        echo $status_colors[$order['status']] ?? 'bg-secondary';
                                        ?>
                                    ">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                                </td>
                                <td class="table-actions">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item" href="service_order_detail.php?id=<?php echo $order['id']; ?>">
                                                    <i class="fas fa-eye me-2"></i>Detail
                                                </a>
                                            </li>
                                            <?php if ($order['status'] == 'pending'): ?>
                                                <li>
                                                    <a class="dropdown-item" href="?id=<?php echo $order['id']; ?>&update_status=processing">
                                                        <i class="fas fa-play me-2"></i>Start Processing
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            <?php if ($order['status'] == 'processing'): ?>
                                                <li>
                                                    <a class="dropdown-item" href="?id=<?php echo $order['id']; ?>&update_status=completed">
                                                        <i class="fas fa-check me-2"></i>Mark Completed
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            <?php if (in_array($order['status'], ['pending', 'processing'])): ?>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="?id=<?php echo $order['id']; ?>&update_status=cancelled">
                                                        <i class="fas fa-times me-2"></i>Cancel Order
                                                    </a>
                                                </li>
                                            <?php endif; ?>
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
        <!-- Create Service Order Form -->
        <div class="card shadow">
            <div class="card-header">
                <h5 class="card-title mb-0">Tambah Service Order</h5>
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
                                        SELECT b.id, b.booking_code, c.full_name, r.room_number
                                        FROM bookings b
                                        JOIN customers c ON b.customer_id = c.id
                                        JOIN booking_details bd ON b.id = bd.booking_id
                                        JOIN rooms r ON bd.room_id = r.id
                                        WHERE b.status IN ('confirmed', 'checked_in')
                                        ORDER BY b.created_at DESC
                                    ");
                                    foreach ($bookings as $booking):
                                    ?>
                                    <option value="<?php echo $booking['id']; ?>">
                                        <?php echo $booking['booking_code']; ?> - <?php echo $booking['full_name']; ?> (Kamar <?php echo $booking['room_number']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Service *</label>
                                <select class="form-select" name="service_id" id="service_id" required>
                                    <option value="">Pilih Service</option>
                                    <?php
                                    $services = $database->getAll("SELECT * FROM services WHERE is_available = 1");
                                    foreach ($services as $service):
                                    ?>
                                    <option value="<?php echo $service['id']; ?>" data-price="<?php echo $service['price']; ?>">
                                        <?php echo $service['service_name']; ?> - <?php echo formatCurrency($service['price']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Quantity *</label>
                                <input type="number" class="form-control" name="quantity" id="quantity" value="1" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Harga per Item</label>
                                <input type="text" class="form-control" id="price_per_item" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Total Harga</label>
                                <input type="text" class="form-control" name="total_price" id="total_price" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Status *</label>
                                <select class="form-select" name="status" required>
                                    <option value="pending">Pending</option>
                                    <option value="processing">Processing</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Catatan</label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="Catatan untuk service order..."></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Simpan Service Order
                        </button>
                        <a href="service_orders.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>

        <script>
        $(document).ready(function() {
            function calculateTotal() {
                var serviceId = $('#service_id').val();
                var quantity = parseInt($('#quantity').val()) || 0;
                
                if (serviceId) {
                    var selectedOption = $('#service_id option:selected');
                    var price = selectedOption.data('price') || 0;
                    var total = price * quantity;
                    
                    $('#price_per_item').val('Rp ' + price.toLocaleString());
                    $('#total_price').val('Rp ' + total.toLocaleString());
                } else {
                    $('#price_per_item').val('');
                    $('#total_price').val('');
                }
            }

            $('#service_id, #quantity').on('change', calculateTotal);
            calculateTotal(); // Initialize on load
        });
        </script>
        <?php endif; ?>
    </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>