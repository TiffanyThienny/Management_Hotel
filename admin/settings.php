<?php
require_once '../config/init.php';
checkAdminAuth();

// Only super admin can access settings
if (!isAdmin()) {
    setFlashMessage('error', 'Akses ditolak. Hanya administrator yang dapat mengakses pengaturan.');
    redirect('dashboard.php');
}

// Process settings update
if ($_POST) {
    $data = [
        'hotel_name' => sanitizeInput($_POST['hotel_name']),
        'address' => sanitizeInput($_POST['address']),
        'phone' => sanitizeInput($_POST['phone']),
        'email' => sanitizeInput($_POST['email']),
        'website' => sanitizeInput($_POST['website']),
        'check_in_time' => sanitizeInput($_POST['check_in_time']),
        'check_out_time' => sanitizeInput($_POST['check_out_time']),
        'tax_rate' => floatval($_POST['tax_rate']),
        'currency' => sanitizeInput($_POST['currency'])
    ];

    $result = $database->update('settings', $data, "id = 1");
    
    if ($result) {
        setFlashMessage('success', 'Pengaturan berhasil diperbarui');
        // Refresh page to show updated settings
        redirect('settings.php');
    } else {
        setFlashMessage('error', 'Gagal memperbarui pengaturan');
    }
}

// Get current settings
$settings = $database->getSingle("SELECT * FROM settings WHERE id = 1");

$page_title = "Pengaturan Sistem";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/admin-sidebar.php'; ?>

    <!-- Main Content -->
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Pengaturan Sistem</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <button type="button" class="btn btn-success" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> Print
                </button>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-lg-8">
                <!-- Hotel Settings Form -->
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-hotel me-2"></i>Informasi Hotel</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="settingsForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Nama Hotel *</label>
                                        <input type="text" class="form-control" name="hotel_name" 
                                               value="<?php echo $settings['hotel_name'] ?? ''; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Email Hotel *</label>
                                        <input type="email" class="form-control" name="email" 
                                               value="<?php echo $settings['email'] ?? ''; ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Telepon *</label>
                                        <input type="text" class="form-control" name="phone" 
                                               value="<?php echo $settings['phone'] ?? ''; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Website</label>
                                        <input type="url" class="form-control" name="website" 
                                               value="<?php echo $settings['website'] ?? ''; ?>" placeholder="https://...">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Alamat Lengkap *</label>
                                <textarea class="form-control" name="address" rows="3" required><?php echo $settings['address'] ?? ''; ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Check-in Time *</label>
                                        <input type="time" class="form-control" name="check_in_time" 
                                               value="<?php echo $settings['check_in_time'] ?? '14:00'; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Check-out Time *</label>
                                        <input type="time" class="form-control" name="check_out_time" 
                                               value="<?php echo $settings['check_out_time'] ?? '12:00'; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Tax Rate (%) *</label>
                                        <input type="number" class="form-control" name="tax_rate" 
                                               value="<?php echo $settings['tax_rate'] ?? '10'; ?>" min="0" max="100" step="0.01" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Currency *</label>
                                        <select class="form-control" name="currency" required>
                                            <option value="IDR" <?php echo ($settings['currency'] ?? 'IDR') == 'IDR' ? 'selected' : ''; ?>>IDR (Rupiah)</option>
                                            <option value="USD" <?php echo ($settings['currency'] ?? 'IDR') == 'USD' ? 'selected' : ''; ?>>USD (US Dollar)</option>
                                            <option value="EUR" <?php echo ($settings['currency'] ?? 'IDR') == 'EUR' ? 'selected' : ''; ?>>EUR (Euro)</option>
                                            <option value="SGD" <?php echo ($settings['currency'] ?? 'IDR') == 'SGD' ? 'selected' : ''; ?>>SGD (Singapore Dollar)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label class="form-label">Logo URL</label>
                                        <input type="url" class="form-control" name="logo_url" 
                                               value="<?php echo $settings['logo_url'] ?? ''; ?>" placeholder="https://.../logo.png">
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Simpan Pengaturan
                                </button>
                                <button type="reset" class="btn btn-secondary">Reset</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- System Configuration -->
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-cogs me-2"></i>Konfigurasi Sistem</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Maintenance Mode</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="maintenanceMode">
                                        <label class="form-check-label" for="maintenanceMode">
                                            Aktifkan maintenance mode
                                        </label>
                                    </div>
                                    <small class="text-muted">Saat aktif, user tidak dapat melakukan booking</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Auto-confirm Booking</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="autoConfirm" checked>
                                        <label class="form-check-label" for="autoConfirm">
                                            Auto konfirmasi booking setelah pembayaran
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email Notifications</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="emailNotifications" checked>
                                        <label class="form-check-label" for="emailNotifications">
                                            Kirim notifikasi email ke user
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">SMS Notifications</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="smsNotifications">
                                        <label class="form-check-label" for="smsNotifications">
                                            Kirim notifikasi SMS ke user
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Max Booking Days</label>
                                    <input type="number" class="form-control" value="30" min="1" max="365">
                                    <small class="text-muted">Maksimal hari untuk booking di muka</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Cancellation Period (hours)</label>
                                    <input type="number" class="form-control" value="24" min="1" max="72">
                                    <small class="text-muted">Waktu minimal untuk pembatalan booking</small>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn btn-warning">
                            <i class="fas fa-sync-alt me-1"></i> Simpan Konfigurasi
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- System Information -->
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0"><i class="fas fa-info-circle me-2"></i>Informasi Sistem</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Versi Aplikasi:</strong><br>
                            <span class="text-muted"><?php echo APP_VERSION; ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>PHP Version:</strong><br>
                            <span class="text-muted"><?php echo PHP_VERSION; ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Server Software:</strong><br>
                            <span class="text-muted"><?php echo $_SERVER['SERVER_SOFTWARE']; ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Database:</strong><br>
                            <span class="text-muted">MySQL</span>
                        </div>
                        <div class="mb-3">
                            <strong>Last Updated:</strong><br>
                            <span class="text-muted"><?php echo date('d F Y H:i:s'); ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>Server Timezone:</strong><br>
                            <span class="text-muted"><?php echo date_default_timezone_get(); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0"><i class="fas fa-chart-bar me-2"></i>Statistik Cepat</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $stats = getBookingStatistics($db);
                        ?>
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <div class="text-primary fw-bold h5"><?php echo $stats['total_rooms']; ?></div>
                                <small class="text-muted">Total Kamar</small>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="text-success fw-bold h5"><?php echo $stats['available_rooms']; ?></div>
                                <small class="text-muted">Kamar Tersedia</small>
                            </div>
                            <div class="col-6">
                                <div class="text-info fw-bold h5"><?php echo $stats['total_bookings']; ?></div>
                                <small class="text-muted">Total Booking</small>
                            </div>
                            <div class="col-6">
                                <div class="text-warning fw-bold h5"><?php echo $stats['pending_bookings']; ?></div>
                                <small class="text-muted">Pending</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Backup & Restore -->
                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="card-title mb-0"><i class="fas fa-database me-2"></i>Backup & Restore</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-success" onclick="backupDatabase()">
                                <i class="fas fa-download me-1"></i> Backup Database
                            </button>
                            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#restoreModal">
                                <i class="fas fa-upload me-1"></i> Restore Database
                            </button>
                            <button type="button" class="btn btn-danger" onclick="clearCache()">
                                <i class="fas fa-broom me-1"></i> Clear Cache
                            </button>
                        </div>
                        
                        <div class="mt-3">
                            <small class="text-muted">Last Backup:</small><br>
                            <strong><?php 
                                $backup_file = 'backup/database_backup_' . date('Y-m-d') . '.sql';
                                if (file_exists($backup_file)) {
                                    echo date('d M Y H:i', filemtime($backup_file));
                                } else {
                                    echo 'Never';
                                }
                            ?></strong>
                        </div>
                    </div>
                </div>

                <!-- User Management -->
                <div class="card shadow mt-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0"><i class="fas fa-users me-2"></i>User Management</h6>
                    </div>
                    <div class="card-body">
                        <a href="users.php" class="btn btn-outline-primary w-100 mb-2">
                            <i class="fas fa-user-cog me-1"></i> Manage Users
                        </a>
                        <a href="roles.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-user-tag me-1"></i> Manage Roles
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>
    </div>
</div>

<!-- Restore Database Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Restore Database</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Peringatan:</strong> Restore database akan mengganti semua data dengan backup.
                    Pastikan Anda sudah membackup data terbaru!
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Pilih File Backup</label>
                    <input type="file" class="form-control" accept=".sql,.backup">
                    <div class="form-text">Hanya file .sql atau .backup yang diperbolehkan</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger">Restore Database</button>
            </div>
        </div>
    </div>
</div>

<script>
function backupDatabase() {
    if (confirm('Buat backup database sekarang?')) {
        window.location.href = 'backup_database.php';
    }
}

function clearCache() {
    if (confirm('Clear semua cache sistem?')) {
        // AJAX request to clear cache
        $.ajax({
            url: 'clear_cache.php',
            method: 'GET',
            success: function(response) {
                alert('Cache berhasil dihapus');
                location.reload();
            },
            error: function() {
                alert('Error clearing cache');
            }
        });
    }
}

// Form validation
document.getElementById('settingsForm').addEventListener('submit', function(e) {
    const taxRate = parseFloat(document.querySelector('input[name="tax_rate"]').value);
    
    if (taxRate < 0 || taxRate > 100) {
        e.preventDefault();
        alert('Tax rate harus antara 0-100%');
        return false;
    }
    
    return true;
});
</script>

<?php include '../includes/footer.php'; ?>