<?php
// Start output buffering if not already started
if (!ob_get_level()) {
    ob_start();
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once 'database.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Set default timezone
date_default_timezone_set('Asia/Jakarta');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Application constants
define('APP_NAME', 'Hotel Management System');
define('APP_VERSION', '1.0');
define('BASE_URL', 'http://localhost/hotel-management-system/');
define('UPLOAD_PATH', 'assets/uploads/');

// Hotel settings from database
function getHotelSettings($db) {
    $query = "SELECT * FROM settings WHERE id = 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get hotel settings
$hotel_settings = getHotelSettings($db);

// Define constants from settings
if ($hotel_settings) {
    define('HOTEL_NAME', $hotel_settings['hotel_name']);
    define('HOTEL_PHONE', $hotel_settings['phone']);
    define('HOTEL_EMAIL', $hotel_settings['email']);
    define('HOTEL_ADDRESS', $hotel_settings['address']);
    define('CHECK_IN_TIME', $hotel_settings['check_in_time']);
    define('CHECK_OUT_TIME', $hotel_settings['check_out_time']);
} else {
    // Default values if settings not found
    define('HOTEL_NAME', 'Grand Luxury Hotel');
    define('HOTEL_PHONE', '+62 21 1234567');
    define('HOTEL_EMAIL', 'info@hotel.com');
    define('HOTEL_ADDRESS', 'Jakarta, Indonesia');
    define('CHECK_IN_TIME', '14:00:00');
    define('CHECK_OUT_TIME', '12:00:00');
}

// Security functions
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)));
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Redirect function
function redirect($url) {
    if (!headers_sent()) {
        header("Location: " . $url);
        exit();
    } else {
        echo "<script>window.location.href='" . htmlspecialchars($url, ENT_QUOTES) . "';</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($url, ENT_QUOTES) . "'></noscript>";
        exit();
    }
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Check if user is receptionist
function isReceptionist() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'receptionist';
}

// Check if user is owner
function isOwner() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'owner';
}

// Check if user is regular user
function isUser() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'user';
}

// Authentication check for protected pages
function checkAuth() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        setFlashMessage('warning', 'Silakan login terlebih dahulu untuk mengakses halaman ini.');
        $prefix = (str_contains($_SERVER['PHP_SELF'] ?? '', '/admin/') || str_contains($_SERVER['PHP_SELF'] ?? '', '/user/')) ? '../' : '';
        redirect($prefix . "login.php");
    }
}

// Admin, Receptionist & Owner access check
function checkAdminAuth() {
    checkAuth();
    if (!isAdmin() && !isReceptionist() && !isOwner()) {
        setFlashMessage('error', 'Akses ditolak. Halaman ini hanya untuk manajemen dan staf hotel.');
        $prefix = str_contains($_SERVER['PHP_SELF'] ?? '', '/admin/') ? '../' : '';
        redirect($prefix . "user/index.php");
    }
}

// User access check
function checkUserAuth() {
    checkAuth();
}

// Generate random booking code
function generateBookingCode() {
    $prefix = "BOOK";
    $timestamp = time();
    $random = rand(1000, 9999);
    return $prefix . $timestamp . $random;
}

// Format currency
// function formatCurrency($amount) {
//     return "Rp " . number_format($amount, 0, ',', '.');
// }
// Format currency - DIPERBAIKI LENGKAP
function formatCurrency($amount) {
    // Handle semua kemungkinan null/empty
    if ($amount === null || $amount === '' || $amount === false) {
        return "Rp 0";
    }
    
    // Jika sudah string Rp, hilangkan
    if (is_string($amount) && strpos($amount, 'Rp') !== false) {
        $amount = str_replace(['Rp', '.', ' ', ','], '', $amount);
    }
    
    // Convert ke float
    $amount = floatval($amount);
    
    // Pastikan numeric
    if (!is_numeric($amount)) {
        return "Rp 0";
    }
    
    // Format
    return "Rp " . number_format($amount, 0, ',', '.');
}

// Helper function untuk queries dengan COALESCE
function getNumericValue($value, $default = 0) {
    if ($value === null || $value === '') {
        return $default;
    }
    
    $value = floatval($value);
    return is_numeric($value) ? $value : $default;
}

// Calculate total nights
function calculateNights($check_in, $check_out) {
    $check_in = new DateTime($check_in);
    $check_out = new DateTime($check_out);
    $interval = $check_in->diff($check_out);
    return $interval->days;
}

// Validate date range
function validateDateRange($check_in, $check_out) {
    $today = new DateTime();
    $check_in_date = new DateTime($check_in);
    $check_out_date = new DateTime($check_out);
    
    if ($check_in_date < $today) {
        return "Tanggal check-in tidak boleh kurang dari hari ini";
    }
    
    if ($check_out_date <= $check_in_date) {
        return "Tanggal check-out harus setelah tanggal check-in";
    }
    
    return true;
}

// Get room availability
function getAvailableRooms($db, $room_type_id, $check_in, $check_out) {
    $query = "SELECT r.*, rt.type_name, rt.base_price 
              FROM rooms r 
              JOIN room_types rt ON r.room_type_id = rt.id 
              WHERE r.room_type_id = :room_type_id 
              AND r.status = 'available' 
              AND r.id NOT IN (
                  SELECT bd.room_id 
                  FROM booking_details bd 
                  JOIN bookings b ON bd.booking_id = b.id 
                  WHERE b.status IN ('confirmed', 'checked_in') 
                  AND (
                      (b.check_in <= :check_out AND b.check_out >= :check_in)
                  )
              )";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':room_type_id', $room_type_id);
    $stmt->bindParam(':check_in', $check_in);
    $stmt->bindParam(':check_out', $check_out);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get room type with facilities
function getRoomTypeWithFacilities($db, $room_type_id) {
    $query = "SELECT rt.*, GROUP_CONCAT(f.name) as facilities 
              FROM room_types rt 
              LEFT JOIN room_facilities rf ON rt.id = rf.room_type_id 
              LEFT JOIN facilities f ON rf.facility_id = f.id 
              WHERE rt.id = :room_type_id 
              GROUP BY rt.id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':room_type_id', $room_type_id);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get booking details with room information
function getBookingDetails($db, $booking_id) {
    $query = "SELECT b.*, bd.room_id, bd.price_per_night, r.room_number, rt.type_name,
                     c.full_name, c.email, c.phone, u.username,
                     p.payment_status, p.payment_method, p.amount as paid_amount
              FROM bookings b
              JOIN booking_details bd ON b.id = bd.booking_id
              JOIN rooms r ON bd.room_id = r.id
              JOIN room_types rt ON r.room_type_id = rt.id
              JOIN customers c ON b.customer_id = c.id
              LEFT JOIN users u ON b.user_id = u.id
              LEFT JOIN payments p ON b.id = p.booking_id
              WHERE b.id = :booking_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':booking_id', $booking_id);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Flash message system
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

function displayFlashMessage() {
    $flash = getFlashMessage();
    if ($flash) {
        $alertClass = $flash['type'] === 'error' ? 'alert-danger' : 
                     ($flash['type'] === 'success' ? 'alert-success' : 'alert-info');
        echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">
                ' . $flash['message'] . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
    }
}

// Room Image URL Helper
function getRoomImageUrl($imagePath = null) {
    if (empty($imagePath)) {
        return 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?auto=format&fit=crop&w=800&q=80';
    }
    if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
        return $imagePath;
    }
    $is_subfolder = str_contains($_SERVER['PHP_SELF'] ?? '', '/admin/') || str_contains($_SERVER['PHP_SELF'] ?? '', '/user/');
    $prefix = $is_subfolder ? '../' : '';
    return $prefix . ltrim($imagePath, '/');
}
function registerUser($userData, $db) {
    // Check if username or email already exists
    $checkQuery = "SELECT id FROM users WHERE username = :username OR email = :email";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':username', $userData['username']);
    $checkStmt->bindParam(':email', $userData['email']);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        return "Username atau email sudah terdaftar";
    }
    
    // Hash password
    $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
    
    // Insert user
    $query = "INSERT INTO users (username, password, email, full_name, phone, address, role) 
              VALUES (:username, :password, :email, :full_name, :phone, :address, 'user')";
    
    $stmt = $db->prepare($query);
    
    try {
        $stmt->execute([
            'username' => $userData['username'],
            'password' => $hashedPassword,
            'email' => $userData['email'],
            'full_name' => $userData['full_name'],
            'phone' => $userData['phone'] ?? null,
            'address' => $userData['address'] ?? null
        ]);
        return true;
    } catch (PDOException $e) {
        return "Error: " . $e->getMessage();
    }
}

// User Login Function
function loginUser($username, $password, $db) {
    $query = "SELECT * FROM users WHERE username = :username AND is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();

    if ($stmt->rowCount() == 1) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            
            // Update last login
            $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = :id";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(':id', $user['id']);
            $updateStmt->execute();
            
            return true;
        }
    }
    return false;
}

// Log Activity Function
if (!function_exists('logActivity')) {
function logActivity($user_id, $action, $description, $db) {
    try {
        $query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                  VALUES (:user_id, :action, :description, :ip_address)";
        $stmt = $db->prepare($query);
        return $stmt->execute([
            'user_id' => $user_id,
            'action' => $action,
            'description' => $description,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);
    } catch (Exception $e) {
        return true;
    }
}
}

if (!function_exists('getBookingStatistics')) {
function getBookingStatistics($db) {
    $stats = [
        'total_rooms' => 0,
        'available_rooms' => 0,
        'occupied_rooms' => 0,
        'maintenance_rooms' => 0,
        'total_bookings' => 0,
        'pending_bookings' => 0,
        'confirmed_bookings' => 0,
        'checked_in_bookings' => 0,
        'cancelled_bookings' => 0,
        'today_checkins' => 0,
        'today_checkouts' => 0,
        'today_bookings' => 0,
        'total_revenue' => 0,
        'monthly_revenue' => 0,
        'today_revenue' => 0,
        'occupancy_rate' => 0
    ];
    
    try {
        $query = "SELECT COUNT(*) as cnt FROM rooms";
        $stmt = $db->query($query);
        $stats['total_rooms'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        
        $query = "SELECT COUNT(*) as cnt FROM rooms WHERE status = 'available'";
        $stmt = $db->query($query);
        $stats['available_rooms'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

        $query = "SELECT COUNT(*) as cnt FROM rooms WHERE status = 'occupied'";
        $stmt = $db->query($query);
        $stats['occupied_rooms'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

        $query = "SELECT COUNT(*) as cnt FROM rooms WHERE status = 'maintenance'";
        $stmt = $db->query($query);
        $stats['maintenance_rooms'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        
        $query = "SELECT COUNT(*) as cnt FROM bookings";
        $stmt = $db->query($query);
        $stats['total_bookings'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        
        $query = "SELECT COUNT(*) as cnt FROM bookings WHERE status = 'pending'";
        $stmt = $db->query($query);
        $stats['pending_bookings'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        
        $query = "SELECT COUNT(*) as cnt FROM bookings WHERE status = 'confirmed'";
        $stmt = $db->query($query);
        $stats['confirmed_bookings'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

        $query = "SELECT COUNT(*) as cnt FROM bookings WHERE status = 'checked_in'";
        $stmt = $db->query($query);
        $stats['checked_in_bookings'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        
        $query = "SELECT COUNT(*) as cnt FROM bookings WHERE status = 'cancelled'";
        $stmt = $db->query($query);
        $stats['cancelled_bookings'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

        $query = "SELECT COUNT(*) as cnt FROM bookings WHERE check_in = CURDATE() AND status IN ('confirmed', 'checked_in')";
        $stmt = $db->query($query);
        $stats['today_checkins'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        
        $query = "SELECT COUNT(*) as cnt FROM bookings WHERE check_out = CURDATE() AND status IN ('checked_in', 'checked_out')";
        $stmt = $db->query($query);
        $stats['today_checkouts'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

        $query = "SELECT COUNT(*) as cnt FROM bookings WHERE DATE(created_at) = CURDATE()";
        $stmt = $db->query($query);
        $stats['today_bookings'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        
        $query = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_status = 'paid'";
        $stmt = $db->query($query);
        $stats['total_revenue'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $query = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_status = 'paid' AND MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())";
        $stmt = $db->query($query);
        $stats['monthly_revenue'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        
        $today = date('Y-m-d');
        $query = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_status = 'paid' AND DATE(payment_date) = :today";
        $stmt = $db->prepare($query);
        $stmt->execute(['today' => $today]);
        $stats['today_revenue'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        
        $stats['occupancy_rate'] = $stats['total_rooms'] > 0 ? 
            round(($stats['occupied_rooms'] / $stats['total_rooms']) * 100, 1) : 0;

    } catch (PDOException $e) {
        error_log("Error in getBookingStatistics: " . $e->getMessage());
    }
    
    return $stats;
}
}

if (!function_exists('getAvailableRoomsByType')) {
function getAvailableRoomsByType($db, $room_type_id, $check_in, $check_out) {
    try {
        $query = "SELECT r.id, r.room_number, r.floor, r.status
                  FROM rooms r
                  WHERE r.room_type_id = :room_type_id
                    AND r.status = 'available'
                    AND r.id NOT IN (
                        SELECT bd.room_id
                        FROM booking_details bd
                        JOIN bookings b ON bd.booking_id = b.id
                        WHERE b.status IN ('confirmed', 'checked_in')
                          AND (
                              (b.check_in < :check_out AND b.check_out > :check_in)
                          )
                    )";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':room_type_id', $room_type_id, PDO::PARAM_INT);
        $stmt->bindParam(':check_in', $check_in);
        $stmt->bindParam(':check_out', $check_out);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error in getAvailableRoomsByType: " . $e->getMessage());
        return [];
    }
}
}
?>