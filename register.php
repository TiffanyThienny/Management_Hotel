<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: user/index.php");
    exit();
}

$error = '';
$success = '';

// Process registration
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim(htmlspecialchars($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email = trim(htmlspecialchars($_POST['email'] ?? ''));
    $full_name = trim(htmlspecialchars($_POST['full_name'] ?? ''));
    $phone = trim(htmlspecialchars($_POST['phone'] ?? ''));
    $address = trim(htmlspecialchars($_POST['address'] ?? ''));
    
    // Validation
    if (empty($username) || empty($password) || empty($confirm_password) || empty($email) || empty($full_name) || empty($phone) || empty($address)) {
        $error = "Semua field wajib diisi!";
    } elseif ($password !== $confirm_password) {
        $error = "Password dan konfirmasi password tidak cocok!";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter!";
    } else {
        // Check if username or email exists
        $check_query = "SELECT id FROM users WHERE username = :username OR email = :email";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute(['username' => $username, 'email' => $email]);
        
        if ($check_stmt->rowCount() > 0) {
            $error = "Username atau email sudah terdaftar!";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $query = "INSERT INTO users (username, password, email, full_name, phone, address, role) 
                      VALUES (:username, :password, :email, :full_name, :phone, :address, 'user')";
            
            $stmt = $db->prepare($query);
            
            try {
                $stmt->execute([
                    'username' => $username,
                    'password' => $hashed_password,
                    'email' => $email,
                    'full_name' => $full_name,
                    'phone' => $phone,
                    'address' => $address
                ]);
                
                $success = "Registrasi akun Anda berhasil! Silakan login untuk melakukan pemesanan.";
            } catch (PDOException $e) {
                $error = "Gagal mendaftar: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Akun - Grand Luxury Hotel</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --brand-primary: #4f46e5;
            --brand-gradient: linear-gradient(135deg, #4f46e5 0%, #0ea5e9 100%);
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.88), rgba(30, 41, 59, 0.92)), 
                        url('https://images.unsplash.com/photo-1566073771259-6a8506099945?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80') center/cover no-repeat fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 1rem;
        }
        .register-wrapper {
            width: 100%;
            max-width: 580px;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            padding: 2.5rem 2.25rem;
        }
        .brand-logo {
            width: 56px;
            height: 56px;
            background: var(--brand-gradient);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 10px 20px rgba(79, 70, 229, 0.35);
        }
        .form-control {
            padding: 0.75rem 1rem;
            border-radius: 10px;
            border: 1.5px solid #e2e8f0;
            font-size: 0.925rem;
        }
        .form-control:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
        }
        .input-group .form-control {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        .input-group .toggle-btn {
            border-top-right-radius: 10px;
            border-bottom-right-radius: 10px;
            border: 1.5px solid #e2e8f0;
            border-left: none;
            background: #f8fafc;
            color: #64748b;
            padding: 0.75rem 0.95rem;
            transition: all 0.2s ease;
        }
        .input-group .toggle-btn:hover {
            background: #f1f5f9;
            color: #4f46e5;
        }
        .btn-submit {
            background: var(--brand-gradient);
            border: none;
            color: white;
            font-weight: 700;
            padding: 0.85rem;
            border-radius: 12px;
            box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
            transition: all 0.2s ease;
        }
        .btn-submit:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 14px 24px rgba(79, 70, 229, 0.4);
        }
    </style>
</head>
<body>
    <div class="register-wrapper">
        <div class="glass-card text-center">
            <div class="brand-logo">
                <i class="fas fa-user-plus"></i>
            </div>

            <h3 class="fw-bold mb-1" style="font-family: 'Outfit', sans-serif;">Pendaftaran Tamu</h3>
            <p class="text-muted small mb-4">Buat akun untuk memesan kamar & menikmati fasilitas hotel</p>

            <?php if ($error): ?>
                <div class="alert alert-danger text-start py-2 px-3 small rounded-3" role="alert">
                    <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success text-start py-2 px-3 small rounded-3" role="alert">
                    <i class="fas fa-check-circle me-1"></i> <?php echo $success; ?>
                    <div class="mt-2">
                        <a href="login.php" class="btn btn-sm btn-success fw-bold">Masuk Sekarang</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$success): ?>
            <form method="POST" action="" class="text-start">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Nama Lengkap *</label>
                        <input type="text" class="form-control" name="full_name" required placeholder="Contoh: Lala Safitri" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Username *</label>
                        <input type="text" class="form-control" name="username" required placeholder="Contoh: lala123" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Email *</label>
                        <input type="email" class="form-control" name="email" required placeholder="nama@email.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">No. Telepon / WhatsApp *</label>
                        <input type="text" class="form-control" name="phone" required placeholder="08xxxxxxxxxx" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Password *</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" required placeholder="Min. 6 karakter">
                            <button class="btn toggle-btn" type="button" onclick="togglePasswordVisibility('password', 'eyeIconPassword')" title="Tampilkan / Sembunyikan Password">
                                <i class="fas fa-eye" id="eyeIconPassword"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-secondary">Konfirmasi Password *</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required placeholder="Ulangi password">
                            <button class="btn toggle-btn" type="button" onclick="togglePasswordVisibility('confirm_password', 'eyeIconConfirm')" title="Tampilkan / Sembunyikan Konfirmasi Password">
                                <i class="fas fa-eye" id="eyeIconConfirm"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold text-secondary">Alamat Lengkap *</label>
                        <textarea class="form-control" name="address" rows="2" required placeholder="Masukkan alamat lengkap tempat tinggal Anda"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-submit w-100 mt-4">
                    <i class="fas fa-paper-plane me-2"></i>Daftar Sekarang
                </button>
            </form>
            <?php endif; ?>

            <div class="mt-4 text-center">
                <small class="text-muted">
                    Sudah memiliki akun? <a href="login.php" class="text-primary fw-semibold text-decoration-none">Masuk di sini</a>
                </small>
                <div class="mt-2">
                    <a href="index.php" class="text-muted small text-decoration-none"><i class="fas fa-arrow-left me-1"></i> Kembali ke Beranda</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePasswordVisibility(inputId, iconId) {
            const inputField = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            
            if (inputField.type === 'password') {
                inputField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                inputField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>