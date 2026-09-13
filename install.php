<?php
// Database Installer
if (file_exists('config/database.php')) {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
} else {
    die("Database configuration file not found!");
}

$error = '';
$success = '';

// Process installation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Read SQL file
    $sql_file = 'database.sql';
    if (!file_exists($sql_file)) {
        $error = "SQL file not found: $sql_file";
    } else {
        $sql = file_get_contents($sql_file);
        
        // Split SQL by semicolon
        $queries = explode(';', $sql);
        
        try {
            $db->beginTransaction();
            
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    $db->exec($query);
                }
            }
            
            $db->commit();
            $success = "Database installed successfully!";
            
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "Installation failed: " . $e->getMessage();
        }
    }
}

// Check if tables already exist
$tables_exist = false;
try {
    $result = $db->query("SHOW TABLES LIKE 'users'");
    $tables_exist = $result->rowCount() > 0;
} catch (Exception $e) {
    $tables_exist = false;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Database - Hotel Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="fas fa-database me-2"></i>Database Installation</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <?php echo $success; ?>
                                <hr>
                                <a href="login.php" class="btn btn-success">Go to Login</a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($tables_exist && !$success): ?>
                            <div class="alert alert-info">
                                Database tables already exist.
                                <hr>
                                <a href="login.php" class="btn btn-primary">Go to Login</a>
                                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#reinstallModal">
                                    Reinstall Database
                                </button>
                            </div>
                        <?php elseif (!$success): ?>
                            <div class="alert alert-warning">
                                <h5>Before Installation:</h5>
                                <ol>
                                    <li>Make sure MySQL server is running</li>
                                    <li>Create database 'hotel_management' in phpMyAdmin or MySQL</li>
                                    <li>Update database credentials in config/database.php if needed</li>
                                </ol>
                            </div>
                            
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Database Host:</label>
                                    <input type="text" class="form-control" value="localhost" readonly>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Database Name:</label>
                                    <input type="text" class="form-control" value="hotel_management" readonly>
                                </div>
                                
                                <div class="alert alert-danger">
                                    <strong>Warning:</strong> This will delete all existing data and create new tables.
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100" onclick="return confirm('Are you sure? This will overwrite existing data.')">
                                    <i class="fas fa-download me-2"></i>Install Database
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-body">
                        <h5>Default Login Credentials:</h5>
                        <table class="table table-sm">
                            <tr>
                                <th>Role</th>
                                <th>Username</th>
                                <th>Password</th>
                            </tr>
                            <tr>
                                <td>Admin</td>
                                <td>admin</td>
                                <td>password</td>
                            </tr>
                            <tr>
                                <td>User</td>
                                <td>user1</td>
                                <td>password</td>
                            </tr>
                            <tr>
                                <td>User</td>
                                <td>user2</td>
                                <td>password</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reinstall Modal -->
    <div class="modal fade" id="reinstallModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Reinstallation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <strong>Warning!</strong> This will:
                        <ul>
                            <li>Delete all existing data</li>
                            <li>Drop all tables</li>
                            <li>Recreate database structure</li>
                            <li>Insert sample data</li>
                        </ul>
                        This action cannot be undone!
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="force" value="1">
                        <button type="submit" class="btn btn-danger">Reinstall Database</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>