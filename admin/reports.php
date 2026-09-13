<?php
require_once '../config/init.php';
checkAdminAuth();

// Only super admin and owner can access financial reports
if (!isAdmin() && !isOwner()) {
    setFlashMessage('error', 'Akses ditolak. Laporan keuangan hanya dapat diakses oleh Administrator dan Owner.');
    redirect('dashboard.php');
}

$page_title = "Laporan & Analytics";
include '../includes/header.php';

// Default date range (current month)
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Get revenue statistics
$revenue_stats = $database->getSingle("
    SELECT 
        COUNT(DISTINCT b.id) as total_bookings,
        COUNT(DISTINCT p.id) as total_transactions,
        COALESCE(SUM(p.amount), 0) as total_revenue,
        COALESCE(AVG(p.amount), 0) as avg_transaction,
        COALESCE(SUM(CASE WHEN p.payment_method = 'cash' THEN p.amount ELSE 0 END), 0) as cash_revenue,
        COALESCE(SUM(CASE WHEN p.payment_method = 'transfer' THEN p.amount ELSE 0 END), 0) as transfer_revenue,
        COALESCE(SUM(CASE WHEN p.payment_method = 'credit_card' THEN p.amount ELSE 0 END), 0) as credit_card_revenue
    FROM bookings b
    LEFT JOIN payments p ON b.id = p.booking_id
    WHERE p.payment_status = 'paid'
    AND DATE(p.payment_date) BETWEEN ? AND ?
", [$start_date, $end_date]);

// Get booking statistics
$booking_stats = $database->getAll("
    SELECT 
        DATE(b.created_at) as date,
        COUNT(*) as booking_count,
        SUM(b.final_amount) as daily_revenue
    FROM bookings b
    WHERE DATE(b.created_at) BETWEEN ? AND ?
    GROUP BY DATE(b.created_at)
    ORDER BY date
", [$start_date, $end_date]);

// Get room type performance
$room_performance = $database->getAll("
    SELECT 
        rt.type_name,
        COUNT(b.id) as booking_count,
        SUM(b.final_amount) as total_revenue,
        AVG(b.final_amount) as avg_revenue,
        COUNT(DISTINCT r.id) as room_count,
        ROUND((COUNT(b.id) / COUNT(DISTINCT r.id)), 2) as occupancy_rate
    FROM room_types rt
    LEFT JOIN rooms r ON rt.id = r.room_type_id
    LEFT JOIN booking_details bd ON r.id = bd.room_id
    LEFT JOIN bookings b ON bd.booking_id = b.id AND DATE(b.created_at) BETWEEN ? AND ?
    GROUP BY rt.id, rt.type_name
    ORDER BY total_revenue DESC
", [$start_date, $end_date]);

// Get customer statistics
$customer_stats = $database->getAll("
    SELECT 
        c.customer_type,
        COUNT(DISTINCT b.id) as booking_count,
        SUM(b.final_amount) as total_revenue,
        COUNT(DISTINCT c.id) as customer_count
    FROM customers c
    LEFT JOIN bookings b ON c.id = b.customer_id AND DATE(b.created_at) BETWEEN ? AND ?
    GROUP BY c.customer_type
    ORDER BY total_revenue DESC
", [$start_date, $end_date]);

// Get daily occupancy
$daily_occupancy = $database->getAll("
    SELECT 
        dates.date,
        COUNT(DISTINCT b.id) as occupied_rooms,
        (SELECT COUNT(*) FROM rooms) as total_rooms,
        ROUND((COUNT(DISTINCT b.id) / (SELECT COUNT(*) FROM rooms)) * 100, 2) as occupancy_rate
    FROM (
        SELECT DATE_ADD(?, INTERVAL seq.seq DAY) as date
        FROM (
            SELECT @row := @row + 1 as seq 
            FROM (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3) a,
                 (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3) b,
                 (SELECT 0 UNION SELECT 1 UNION SELECT 2 UNION SELECT 3) c,
                 (SELECT @row := -1) r
        ) seq
        WHERE DATE_ADD(?, INTERVAL seq.seq DAY) <= ?
    ) dates
    LEFT JOIN bookings b ON dates.date BETWEEN b.check_in AND DATE_SUB(b.check_out, INTERVAL 1 DAY)
        AND b.status IN ('confirmed', 'checked_in')
    GROUP BY dates.date
    ORDER BY dates.date
", [$start_date, $start_date, $end_date]);

// Get top customers
$top_customers = $database->getAll("
    SELECT 
        c.full_name,
        c.email,
        c.customer_type,
        COUNT(b.id) as total_bookings,
        SUM(b.final_amount) as total_spent,
        MAX(b.created_at) as last_booking
    FROM customers c
    LEFT JOIN bookings b ON c.id = b.customer_id AND DATE(b.created_at) BETWEEN ? AND ?
    WHERE b.id IS NOT NULL
    GROUP BY c.id, c.full_name, c.email, c.customer_type
    ORDER BY total_spent DESC
    LIMIT 10
", [$start_date, $end_date]);

// Get payment method distribution
$payment_distribution = $database->getAll("
    SELECT 
        payment_method,
        COUNT(*) as transaction_count,
        SUM(amount) as total_amount,
        ROUND((COUNT(*) / (SELECT COUNT(*) FROM payments WHERE payment_status = 'paid' AND DATE(payment_date) BETWEEN ? AND ?)) * 100, 2) as percentage
    FROM payments
    WHERE payment_status = 'paid'
    AND DATE(payment_date) BETWEEN ? AND ?
    GROUP BY payment_method
    ORDER BY total_amount DESC
", [$start_date, $end_date, $start_date, $end_date]);
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/admin-sidebar.php'; ?>

    <!-- Main Content -->
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-3 mb-4 border-bottom">
            <div>
                <h2 class="fw-bold mb-1"><i class="fas fa-chart-line text-primary me-2"></i>Laporan Finansial & Operasional</h2>
                <p class="text-muted small mb-0">Analisis komprehensif pendapatan, tingkat okupansi kamar, dan kinerja bisnis hotel.</p>
            </div>
            <div class="btn-toolbar mb-2 mb-md-0 gap-2">
                <a href="export-report.php?start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="btn btn-success rounded-pill px-3 fw-bold shadow-sm">
                    <i class="fas fa-file-excel me-1"></i> Download Excel / CSV
                </a>
                <button class="btn btn-outline-dark rounded-pill px-3 fw-bold shadow-sm" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> Cetak / Simpan PDF
                </button>
            </div>
        </div>

        <!-- Date Filter -->
        <div class="card shadow mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">Filter Periode</h6>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Dari Tanggal</label>
                        <input type="date" class="form-control" name="start_date" 
                               value="<?php echo $start_date; ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Sampai Tanggal</label>
                        <input type="date" class="form-control" name="end_date" 
                               value="<?php echo $end_date; ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <a href="reports.php" class="btn btn-secondary w-100">Reset</a>
                    </div>
                    <div class="col-12">
                        <div class="btn-group">
                            <a href="reports.php?start_date=<?php echo date('Y-m-01'); ?>&end_date=<?php echo date('Y-m-t'); ?>" 
                               class="btn btn-outline-primary btn-sm">Bulan Ini</a>
                            <a href="reports.php?start_date=<?php echo date('Y-m-d', strtotime('-30 days')); ?>&end_date=<?php echo date('Y-m-d'); ?>" 
                               class="btn btn-outline-primary btn-sm">30 Hari Terakhir</a>
                            <a href="reports.php?start_date=<?php echo date('Y-01-01'); ?>&end_date=<?php echo date('Y-12-31'); ?>" 
                               class="btn btn-outline-primary btn-sm">Tahun Ini</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Pendapatan</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo formatCurrency($revenue_stats['total_revenue']); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-money-bill fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Total Booking</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo $revenue_stats['total_bookings']; ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Transaksi Sukses</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo $revenue_stats['total_transactions']; ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Rata-rata Transaksi</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo formatCurrency($revenue_stats['avg_transaction']); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Trend Pendapatan & Booking</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="revenueChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Distribusi Metode Pembayaran</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="paymentChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Room Performance -->
        <div class="card shadow mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">Performance Tipe Kamar</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Tipe Kamar</th>
                                <th>Jumlah Kamar</th>
                                <th>Booking</th>
                                <th>Total Revenue</th>
                                <th>Rata-rata</th>
                                <th>Occupancy Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($room_performance as $room): ?>
                            <tr>
                                <td><?php echo $room['type_name']; ?></td>
                                <td><?php echo $room['room_count']; ?></td>
                                <td><?php echo $room['booking_count']; ?></td>
                                <td><?php echo formatCurrency($room['total_revenue']); ?></td>
                                <td><?php echo formatCurrency($room['avg_revenue']); ?></td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar" role="progressbar" 
                                             style="width: <?php echo $room['occupancy_rate'] * 100; ?>%"
                                             aria-valuenow="<?php echo $room['occupancy_rate'] * 100; ?>" 
                                             aria-valuemin="0" aria-valuemax="100">
                                            <?php echo number_format($room['occupancy_rate'] * 100, 1); ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Customer Analysis -->
        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="card shadow h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Analisis Customer</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="customerChart" width="400" height="250"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow h-100">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Top 10 Customers</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Tipe</th>
                                        <th>Booking</th>
                                        <th>Total Spent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_customers as $customer): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo $customer['full_name']; ?></div>
                                            <small class="text-muted"><?php echo $customer['email']; ?></small>
                                        </td>
                                        <td>
                                            <span class="badge 
                                                <?php echo $customer['customer_type'] == 'vip' ? 'bg-warning' : 
                                                      ($customer['customer_type'] == 'corporate' ? 'bg-info' : 'bg-secondary'); ?>">
                                                <?php echo ucfirst($customer['customer_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $customer['total_bookings']; ?></td>
                                        <td><?php echo formatCurrency($customer['total_spent']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Daily Occupancy -->
        <div class="card shadow mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">Daily Occupancy Rate</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Kamar Terisi</th>
                                <th>Total Kamar</th>
                                <th>Occupancy Rate</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daily_occupancy as $day): ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($day['date'])); ?></td>
                                <td><?php echo $day['occupied_rooms']; ?></td>
                                <td><?php echo $day['total_rooms']; ?></td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar 
                                            <?php echo $day['occupancy_rate'] >= 80 ? 'bg-success' : 
                                                  ($day['occupancy_rate'] >= 50 ? 'bg-warning' : 'bg-danger'); ?>" 
                                             role="progressbar" 
                                             style="width: <?php echo $day['occupancy_rate']; ?>%">
                                            <?php echo $day['occupancy_rate']; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($day['occupancy_rate'] >= 80): ?>
                                        <span class="badge bg-success">High</span>
                                    <?php elseif ($day['occupancy_rate'] >= 50): ?>
                                        <span class="badge bg-warning">Medium</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Low</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Payment Distribution -->
        <div class="card shadow">
            <div class="card-header">
                <h6 class="card-title mb-0">Distribusi Pembayaran</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Metode</th>
                                        <th>Transaksi</th>
                                        <th>Total Amount</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payment_distribution as $payment): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            $method_labels = [
                                                'cash' => 'Cash',
                                                'transfer' => 'Transfer',
                                                'credit_card' => 'Credit Card',
                                                'debit_card' => 'Debit Card',
                                                'qris' => 'QRIS'
                                            ];
                                            echo $method_labels[$payment['payment_method']] ?? $payment['payment_method'];
                                            ?>
                                        </td>
                                        <td><?php echo $payment['transaction_count']; ?></td>
                                        <td><?php echo formatCurrency($payment['total_amount']); ?></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: <?php echo $payment['percentage']; ?>%">
                                                    <?php echo $payment['percentage']; ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <h6>Revenue by Payment Method</h6>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Cash
                                <span class="badge bg-primary rounded-pill">
                                    <?php echo formatCurrency($revenue_stats['cash_revenue']); ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Transfer
                                <span class="badge bg-success rounded-pill">
                                    <?php echo formatCurrency($revenue_stats['transfer_revenue']); ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Credit Card
                                <span class="badge bg-info rounded-pill">
                                    <?php echo formatCurrency($revenue_stats['credit_card_revenue']); ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Revenue and Booking Chart
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
const revenueChart = new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: [<?php echo implode(',', array_map(function($item) { 
            return "'" . date('d M', strtotime($item['date'])) . "'"; 
        }, $booking_stats)); ?>],
        datasets: [{
            label: 'Daily Revenue',
            data: [<?php echo implode(',', array_column($booking_stats, 'daily_revenue')); ?>],
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1,
            yAxisID: 'y'
        }, {
            label: 'Booking Count',
            data: [<?php echo implode(',', array_column($booking_stats, 'booking_count')); ?>],
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'rgba(255, 99, 132, 0.2)',
            tension: 0.1,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        stacked: false,
        plugins: {
            title: {
                display: true,
                text: 'Daily Revenue vs Booking Count'
            }
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Revenue (Rp)'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Booking Count'
                },
                grid: {
                    drawOnChartArea: false,
                },
            },
        }
    }
});

// Payment Method Chart
const paymentCtx = document.getElementById('paymentChart').getContext('2d');
const paymentChart = new Chart(paymentCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php echo implode(',', array_map(function($item) { 
            $labels = ['cash' => 'Cash', 'transfer' => 'Transfer', 'credit_card' => 'Credit Card', 'debit_card' => 'Debit Card', 'qris' => 'QRIS'];
            return "'" . ($labels[$item['payment_method']] ?? $item['payment_method']) . "'"; 
        }, $payment_distribution)); ?>],
        datasets: [{
            data: [<?php echo implode(',', array_column($payment_distribution, 'total_amount')); ?>],
            backgroundColor: [
                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
            },
            title: {
                display: true,
                text: 'Payment Method Distribution'
            }
        }
    }
});

// Customer Analysis Chart
const customerCtx = document.getElementById('customerChart').getContext('2d');
const customerChart = new Chart(customerCtx, {
    type: 'bar',
    data: {
        labels: [<?php echo implode(',', array_map(function($item) { 
            return "'" . ucfirst($item['customer_type']) . "'"; 
        }, $customer_stats)); ?>],
        datasets: [{
            label: 'Number of Customers',
            data: [<?php echo implode(',', array_column($customer_stats, 'customer_count')); ?>],
            backgroundColor: 'rgba(54, 162, 235, 0.5)',
            borderColor: 'rgb(54, 162, 235)',
            borderWidth: 1
        }, {
            label: 'Total Revenue',
            data: [<?php echo implode(',', array_column($customer_stats, 'total_revenue')); ?>],
            backgroundColor: 'rgba(255, 99, 132, 0.5)',
            borderColor: 'rgb(255, 99, 132)',
            borderWidth: 1,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Customer Analysis by Type'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Number of Customers'
                }
            }
        }
    }
});

// Export to Excel / CSV Client Fallback
function exportToExcel() {
    window.location.href = 'export-report.php?start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>';
}
</script>

<?php include '../includes/footer.php'; ?>