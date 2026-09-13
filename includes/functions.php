<?php
// General utility functions
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../config/database.php';

if (!function_exists('getRoomTypes')) {
function getRoomTypes($db) {
    $query = "SELECT * FROM room_types WHERE is_available = 1 ORDER BY base_price ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
}

if (!function_exists('getRoomType')) {
function getRoomType($db, $room_type_id) {
    $query = "SELECT * FROM room_types WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $room_type_id);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
}

if (!function_exists('getRoom')) {
function getRoom($db, $room_id) {
    $query = "SELECT r.*, rt.type_name, rt.base_price, rt.capacity 
              FROM rooms r 
              JOIN room_types rt ON r.room_type_id = rt.id 
              WHERE r.id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $room_id);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
}

if (!function_exists('getAvailableRoomsByType')) {
function getAvailableRoomsByType($db, $room_type_id, $check_in, $check_out) {
    $query = "SELECT r.* 
              FROM rooms r 
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
}

if (!function_exists('createBooking')) {
function createBooking($bookingData, $db) {
    try {
        $db->beginTransaction();
        
        // Generate booking code
        $booking_code = generateBookingCode();
        
        // Check if customer exists
        $customerQuery = "SELECT id FROM customers WHERE email = :email";
        $customerStmt = $db->prepare($customerQuery);
        $customerStmt->bindParam(':email', $bookingData['customer_email']);
        $customerStmt->execute();
        
        if ($customerStmt->rowCount() > 0) {
            $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
            $customer_id = $customer['id'];
        } else {
            // Create new customer
            $customerData = [
                'full_name' => $bookingData['customer_name'],
                'email' => $bookingData['customer_email'],
                'phone' => $bookingData['customer_phone'],
                'identity_type' => $bookingData['identity_type'],
                'identity_number' => $bookingData['identity_number'],
                'address' => $bookingData['customer_address']
            ];
            
            $customerQuery = "INSERT INTO customers (full_name, email, phone, identity_type, identity_number, address) 
                             VALUES (:full_name, :email, :phone, :identity_type, :identity_number, :address)";
            $customerStmt = $db->prepare($customerQuery);
            $customerStmt->execute($customerData);
            $customer_id = $db->lastInsertId();
        }
        
        // Calculate total amount
        $room = getRoom($db, $bookingData['room_id']);
        $nights = calculateNights($bookingData['check_in'], $bookingData['check_out']);
        $total_amount = $nights * $room['base_price'];
        
        // Create booking
        $bookingQuery = "INSERT INTO bookings (booking_code, customer_id, user_id, check_in, check_out, total_nights, total_guests, total_amount, final_amount, special_requests, adults, children) 
                        VALUES (:booking_code, :customer_id, :user_id, :check_in, :check_out, :total_nights, :total_guests, :total_amount, :final_amount, :special_requests, :adults, :children)";
        
        $bookingStmt = $db->prepare($bookingQuery);
        $bookingStmt->execute([
            'booking_code' => $booking_code,
            'customer_id' => $customer_id,
            'user_id' => $bookingData['user_id'],
            'check_in' => $bookingData['check_in'],
            'check_out' => $bookingData['check_out'],
            'total_nights' => $nights,
            'total_guests' => $bookingData['total_guests'],
            'total_amount' => $total_amount,
            'final_amount' => $total_amount,
            'special_requests' => $bookingData['special_requests'],
            'adults' => $bookingData['adults'],
            'children' => $bookingData['children']
        ]);
        
        $booking_id = $db->lastInsertId();
        
        // Create booking details
        $detailQuery = "INSERT INTO booking_details (booking_id, room_id, price_per_night, total_price) 
                       VALUES (:booking_id, :room_id, :price_per_night, :total_price)";
        $detailStmt = $db->prepare($detailQuery);
        $detailStmt->execute([
            'booking_id' => $booking_id,
            'room_id' => $bookingData['room_id'],
            'price_per_night' => $room['base_price'],
            'total_price' => $total_amount
        ]);
        
        // Update room status to occupied
        $roomQuery = "UPDATE rooms SET status = 'occupied' WHERE id = :room_id";
        $roomStmt = $db->prepare($roomQuery);
        $roomStmt->bindParam(':room_id', $bookingData['room_id']);
        $roomStmt->execute();
        
        $db->commit();
        return $booking_id;
        
    } catch (Exception $e) {
        $db->rollBack();
        return false;
    }
}
}

if (!function_exists('getBookingStatistics')) {
function getBookingStatistics($db) {
    $stats = [];
     
    // Total rooms
    $query = "SELECT COUNT(*) as count FROM rooms";
    $stmt = $db->query($query);
    $stats['total_rooms'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Available rooms
    $query = "SELECT COUNT(*) as count FROM rooms WHERE status = 'available'";
    $stmt = $db->query($query);
    $stats['available_rooms'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Total bookings
    $query = "SELECT COUNT(*) as count FROM bookings";
    $stmt = $db->query($query);
    $stats['total_bookings'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Pending bookings
    $query = "SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'";
    $stmt = $db->query($query);
    $stats['pending_bookings'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Today's check-ins
    $query = "SELECT COUNT(*) as count FROM bookings WHERE check_in = CURDATE() AND status IN ('confirmed', 'checked_in')";
    $stmt = $db->query($query);
    $stats['today_checkins'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Today's check-outs
    $query = "SELECT COUNT(*) as count FROM bookings WHERE check_out = CURDATE() AND status IN ('checked_in', 'checked_out')";
    $stmt = $db->query($query);
    $stats['today_checkouts'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    
    // Total revenue (this month)
    $query = "SELECT COALESCE(SUM(amount), 0) as total FROM payments 
              WHERE payment_status = 'paid' 
              AND MONTH(payment_date) = MONTH(CURDATE()) 
              AND YEAR(payment_date) = YEAR(CURDATE())";
    $stmt = $db->query($query);
    $stats['monthly_revenue'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    return $stats;
}
}

if (!function_exists('sendNotification')) {
function sendNotification($user_id, $message, $db, $type = 'info') {
    $query = "INSERT INTO notifications (user_id, message, type, is_read) 
              VALUES (:user_id, :message, :type, 0)";
    $stmt = $db->prepare($query);
    return $stmt->execute([
        'user_id' => $user_id,
        'message' => $message,
        'type' => $type
    ]);
}
}

if (!function_exists('getUnreadNotifications')) {
function getUnreadNotifications($user_id, $db) {
    $query = "SELECT * FROM notifications 
              WHERE user_id = :user_id AND is_read = 0 
              ORDER BY created_at DESC 
              LIMIT 10";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
}

if (!function_exists('logActivity')) {
function logActivity($user_id, $action, $description, $db) {
    $query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
              VALUES (:user_id, :action, :description, :ip_address)";
    $stmt = $db->prepare($query);
    return $stmt->execute([
        'user_id' => $user_id,
        'action' => $action,
        'description' => $description,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);
}
}
?>