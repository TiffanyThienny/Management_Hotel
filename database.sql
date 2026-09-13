-- Hotel Management System Database - REVISED & FIXED
CREATE DATABASE IF NOT EXISTS hotel_management;
USE hotel_management;

-- 1. USERS TABLE (FIXED - remove unused columns)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    role ENUM('admin','user','receptionist') DEFAULT 'user',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. ROOM TYPES TABLE (FIXED - remove image_url)
CREATE TABLE room_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type_name VARCHAR(50) NOT NULL,
    description TEXT,
    base_price DECIMAL(10,2) NOT NULL,
    capacity INT NOT NULL,
    size VARCHAR(20),
    bed_type VARCHAR(50),
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. ROOMS TABLE (OK)
CREATE TABLE rooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_number VARCHAR(10) UNIQUE NOT NULL,
    room_type_id INT,
    floor INT,
    view_type ENUM('city','garden','pool','sea') DEFAULT 'city',
    status ENUM('available','occupied','maintenance','cleaning') DEFAULT 'available',
    features TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_type_id) REFERENCES room_types(id) ON DELETE CASCADE
);

-- 4. CUSTOMERS TABLE (FIXED - remove date_of_birth)
CREATE TABLE customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20) NOT NULL,
    identity_type ENUM('ktp','passport','sim') DEFAULT 'ktp',
    identity_number VARCHAR(50),
    address TEXT,
    country VARCHAR(50) DEFAULT 'Indonesia',
    customer_type ENUM('regular','vip','corporate') DEFAULT 'regular',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. BOOKINGS TABLE (OK)
CREATE TABLE bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_code VARCHAR(20) UNIQUE NOT NULL,
    customer_id INT,
    user_id INT,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    total_nights INT,
    total_guests INT DEFAULT 1,
    total_amount DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    final_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending','confirmed','checked_in','checked_out','cancelled','no_show') DEFAULT 'pending',
    special_requests TEXT,
    adults INT DEFAULT 1,
    children INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 6. BOOKING DETAILS TABLE (OK)
CREATE TABLE booking_details (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT,
    room_id INT,
    price_per_night DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

-- 7. PAYMENTS TABLE (OK)
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash','credit_card','debit_card','transfer','qris','ovo','gopay') DEFAULT 'cash',
    payment_status ENUM('pending','paid','failed','refunded','partial') DEFAULT 'pending',
    payment_date TIMESTAMP NULL,
    transaction_id VARCHAR(100),
    bank_name VARCHAR(50),
    account_number VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);

-- 8. FACILITIES TABLE (OK)
CREATE TABLE facilities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    category ENUM('room','hotel','service') DEFAULT 'room',
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 9. ROOM FACILITIES TABLE (OK)
CREATE TABLE room_facilities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_type_id INT,
    facility_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_type_id) REFERENCES room_types(id) ON DELETE CASCADE,
    FOREIGN KEY (facility_id) REFERENCES facilities(id) ON DELETE CASCADE
);

-- 10. REVIEWS TABLE (FIXED - simplified untuk match coding)
CREATE TABLE reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT,
    user_id INT,
    room_id INT,
    rating_cleanliness INT CHECK (rating_cleanliness >= 1 AND rating_cleanliness <= 5),
    rating_comfort INT CHECK (rating_comfort >= 1 AND rating_comfort <= 5),
    rating_location INT CHECK (rating_location >= 1 AND rating_location <= 5),
    rating_service INT CHECK (rating_service >= 1 AND rating_service <= 5),
    rating_facilities INT CHECK (rating_facilities >= 1 AND rating_facilities <= 5),
    overall_rating DECIMAL(2,1),
    comment TEXT,
    is_approved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

-- 11. EMPLOYEES TABLE (OK - tetap dipertahankan)
CREATE TABLE employees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_code VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    position VARCHAR(50),
    department ENUM('front_office','housekeeping','fbs','management','security') DEFAULT 'front_office',
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    hire_date DATE,
    salary DECIMAL(10,2),
    status ENUM('active','inactive','suspended') DEFAULT 'active',
    emergency_contact VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 12. SERVICES TABLE (OK)
CREATE TABLE services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    service_name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    category ENUM('food','laundry','transport','spa','other') DEFAULT 'other',
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 13. SERVICE_ORDERS TABLE (OK)
CREATE TABLE service_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT,
    service_id INT,
    quantity INT DEFAULT 1,
    total_price DECIMAL(10,2) NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending','processing','completed','cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
);

-- 14. SETTINGS TABLE (OK)
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hotel_name VARCHAR(100) NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    website VARCHAR(255),
    check_in_time TIME DEFAULT '14:00:00',
    check_out_time TIME DEFAULT '12:00:00',
    tax_rate DECIMAL(5,2) DEFAULT 10.00,
    currency VARCHAR(10) DEFAULT 'IDR',
    logo_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 15. MAINTENANCE TABLE (OK)
CREATE TABLE maintenance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT,
    issue_type VARCHAR(100),
    description TEXT,
    reported_by INT,
    status ENUM('reported','in_progress','completed') DEFAULT 'reported',
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    start_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_date TIMESTAMP NULL,
    cost_estimate DECIMAL(10,2) DEFAULT 0,
    actual_cost DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================================
-- INSERT DATA - SAMA seperti sebelumnya, tapi sesuaikan dengan struktur baru
-- ============================================================================

-- 1. USERS DATA (12 users - FIXED: remove date_of_birth)
INSERT INTO users (username, password, email, full_name, phone, address, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@hotel.com', 'Administrator System', '+62 21 1234567', 'Jl. Admin No. 1, Jakarta', 'admin'),
('reception', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'reception@hotel.com', 'Sari Indah', '+62 21 1234568', 'Jl. Reception No. 2, Jakarta', 'receptionist'),
('user1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user1@email.com', 'Budi Santoso', '+62 812 3456 7890', 'Jl. Merdeka No. 123, Jakarta', 'user'),
('user2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user2@email.com', 'Siti Rahayu', '+62 813 4567 8901', 'Jl. Sudirman No. 456, Jakarta', 'user'),
('user3', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user3@email.com', 'Ahmad Wijaya', '+62 814 5678 9012', 'Jl. Thamrin No. 789, Jakarta', 'user'),
('user4', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user4@email.com', 'Dewi Lestari', '+62 815 6789 0123', 'Jl. Gatot Subroto No. 101, Jakarta', 'user'),
('user5', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user5@email.com', 'Rudi Hermawan', '+62 816 7890 1234', 'Jl. HR Rasuna Said No. 202, Jakarta', 'user'),
('user6', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user6@email.com', 'Maya Sari', '+62 817 8901 2345', 'Jl. Jenderal Sudirman No. 303, Jakarta', 'user'),
('user7', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user7@email.com', 'Joko Prasetyo', '+62 818 9012 3456', 'Jl. MH Thamrin No. 404, Jakarta', 'user'),
('user8', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user8@email.com', 'Linda Wati', '+62 819 0123 4567', 'Jl. S Parman No. 505, Jakarta', 'user'),
('user9', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user9@email.com', 'Hendra Gunawan', '+62 820 1234 5678', 'Jl. Kyai Tapa No. 606, Jakarta', 'user'),
('user10', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user10@email.com', 'Rina Melati', '+62 821 2345 6789', 'Jl. Tomang No. 707, Jakarta', 'user');

-- 2. ROOM TYPES DATA (8 types - FIXED: remove image_url)
INSERT INTO room_types (type_name, description, base_price, capacity, size, bed_type) VALUES 
('Standard Single', 'Kamar single standar nyaman untuk solo traveler dengan fasilitas dasar', 250000, 1, '18 m²', 'Single Bed'),
('Standard Double', 'Kamar double standar dengan tempat tidur double untuk pasangan', 350000, 2, '22 m²', 'Double Bed'),
('Deluxe King', 'Kamar deluxe dengan king size bed dan view kota', 550000, 2, '28 m²', 'King Size Bed'),
('Deluxe Twin', 'Kamar deluxe dengan twin bed, cocok untuk teman atau saudara', 500000, 2, '26 m²', 'Twin Bed'),
('Executive Suite', 'Suite executive dengan living area terpisah dan fasilitas premium', 850000, 3, '45 m²', 'King Bed + Sofa Bed'),
('Family Room', 'Kamar keluarga luas dengan 2 double bed untuk 4 orang', 750000, 4, '35 m²', '2 Double Beds'),
('Presidential Suite', 'Suite presiden mewah dengan living room, dining area, dan kitchenette', 1500000, 4, '80 m²', 'King Bed + 2 Single Beds'),
('Honeymoon Suite', 'Suite romantis khusus untuk pasangan dengan jacuzzi dan view terbaik', 1200000, 2, '50 m²', 'King Size Canopy Bed');

-- 3. ROOMS DATA (30 rooms - OK)
INSERT INTO rooms (room_number, room_type_id, floor, view_type, status) VALUES 
-- Floor 1 - Standard Rooms
('101', 1, 1, 'garden', 'available'),
('102', 1, 1, 'garden', 'available'),
('103', 2, 1, 'city', 'available'),
('104', 2, 1, 'city', 'occupied'),
('105', 2, 1, 'garden', 'available'),
('106', 2, 1, 'garden', 'maintenance'),

-- Floor 2 - Standard & Deluxe
('201', 2, 2, 'city', 'available'),
('202', 2, 2, 'city', 'available'),
('203', 3, 2, 'city', 'available'),
('204', 3, 2, 'pool', 'occupied'),
('205', 3, 2, 'pool', 'available'),
('206', 4, 2, 'city', 'available'),

-- Floor 3 - Deluxe
('301', 3, 3, 'city', 'available'),
('302', 3, 3, 'city', 'available'),
('303', 4, 3, 'pool', 'available'),
('304', 4, 3, 'pool', 'available'),
('305', 4, 3, 'city', 'occupied'),
('306', 4, 3, 'city', 'available'),

-- Floor 4 - Executive & Family
('401', 5, 4, 'city', 'available'),
('402', 5, 4, 'city', 'available'),
('403', 5, 4, 'pool', 'available'),
('404', 6, 4, 'city', 'available'),
('405', 6, 4, 'city', 'available'),
('406', 6, 4, 'pool', 'occupied'),

-- Floor 5 - Premium Suites
('501', 7, 5, 'city', 'available'),
('502', 7, 5, 'city', 'available'),
('503', 8, 5, 'pool', 'available'),
('504', 8, 5, 'pool', 'available'),
('505', 8, 5, 'city', 'available'),

-- Floor 6 - Additional Rooms
('601', 3, 6, 'city', 'available'),
('602', 4, 6, 'city', 'available'),
('603', 5, 6, 'pool', 'available');


-- 4. CUSTOMERS DATA (15 customers - FIXED: remove date_of_birth)
INSERT INTO customers (full_name, email, phone, identity_type, identity_number, address, country, customer_type) VALUES 
('Budi Santoso', 'budi.santoso@email.com', '+62 812 3456 7890', 'ktp', '3171234567890001', 'Jl. Merdeka No. 123, Jakarta Pusat', 'Indonesia', 'regular'),
('Siti Rahayu', 'siti.rahayu@email.com', '+62 813 4567 8901', 'ktp', '3171234567890002', 'Jl. Sudirman No. 456, Jakarta Selatan', 'Indonesia', 'vip'),
('Ahmad Wijaya', 'ahmad.wijaya@email.com', '+62 814 5678 9012', 'ktp', '3171234567890003', 'Jl. Thamrin No. 789, Jakarta Pusat', 'Indonesia', 'regular'),
('Dewi Lestari', 'dewi.lestari@email.com', '+62 815 6789 0123', 'ktp', '3171234567890004', 'Jl. Gatot Subroto No. 101, Jakarta Selatan', 'Indonesia', 'corporate'),
('Rudi Hermawan', 'rudi.hermawan@email.com', '+62 816 7890 1234', 'ktp', '3171234567890005', 'Jl. HR Rasuna Said No. 202, Jakarta Selatan', 'Indonesia', 'regular'),
('Maya Sari', 'maya.sari@email.com', '+62 817 8901 2345', 'ktp', '3171234567890006', 'Jl. Jenderal Sudirman No. 303, Jakarta Pusat', 'Indonesia', 'vip'),
('Joko Prasetyo', 'joko.prasetyo@email.com', '+62 818 9012 3456', 'ktp', '3171234567890007', 'Jl. MH Thamrin No. 404, Jakarta Pusat', 'Indonesia', 'regular'),
('Linda Wati', 'linda.wati@email.com', '+62 819 0123 4567', 'ktp', '3171234567890008', 'Jl. S Parman No. 505, Jakarta Barat', 'Indonesia', 'regular'),
('Hendra Gunawan', 'hendra.gunawan@email.com', '+62 820 1234 5678', 'ktp', '3171234567890009', 'Jl. Kyai Tapa No. 606, Jakarta Barat', 'Indonesia', 'corporate'),
('Rina Melati', 'rina.melati@email.com', '+62 821 2345 6789', 'ktp', '3171234567890010', 'Jl. Tomang No. 707, Jakarta Barat', 'Indonesia', 'regular'),
('John Smith', 'john.smith@email.com', '+1 555 123 4567', 'passport', 'P12345678', '123 Main St, New York, USA', 'USA', 'vip'),
('Maria Garcia', 'maria.garcia@email.com', '+34 912 345 678', 'passport', 'P87654321', 'Calle Mayor 45, Madrid, Spain', 'Spain', 'regular'),
('Chen Wei', 'chen.wei@email.com', '+86 138 0013 8000', 'passport', 'P11223344', 'Beijing CBD, China', 'China', 'corporate'),
('Tanaka Yuki', 'tanaka.yuki@email.com', '+81 90 1234 5678', 'passport', 'P55667788', 'Shibuya, Tokyo, Japan', 'Japan', 'regular'),
('Kim Min Ho', 'kim.minho@email.com', '+82 10 1234 5678', 'passport', 'P99887766', 'Gangnam, Seoul, Korea', 'South Korea', 'vip');

-- 5-15. DATA LAINNYA tetap SAMA seperti sebelumnya...
-- ... (booking, booking_details, payments, facilities, room_facilities, reviews, employees, services, service_orders, settings, maintenance) ...
-- 5. BOOKINGS DATA (25 bookings)
INSERT INTO bookings (booking_code, customer_id, user_id, check_in, check_out, total_nights, total_guests, total_amount, final_amount, status, adults, children) VALUES 
('BOOK001', 1, 3, '2024-01-15', '2024-01-17', 2, 2, 700000, 700000, 'checked_out', 2, 0),
('BOOK002', 2, 4, '2024-01-16', '2024-01-19', 3, 2, 1650000, 1650000, 'checked_out', 2, 0),
('BOOK003', 3, 5, '2024-01-18', '2024-01-20', 2, 3, 1700000, 1700000, 'checked_out', 3, 0),
('BOOK004', 4, 6, '2024-01-20', '2024-01-22', 2, 2, 1100000, 1100000, 'checked_out', 2, 0),
('BOOK005', 5, 7, '2024-01-25', '2024-01-27', 2, 4, 1500000, 1500000, 'checked_out', 4, 0),
('BOOK006', 6, 8, '2024-02-01', '2024-02-03', 2, 2, 1100000, 1100000, 'checked_out', 2, 0),
('BOOK007', 7, 9, '2024-02-05', '2024-02-08', 3, 2, 1050000, 1050000, 'checked_out', 2, 0),
('BOOK008', 8, 10, '2024-02-10', '2024-02-12', 2, 2, 700000, 700000, 'checked_out', 2, 0),
('BOOK009', 9, 11, '2024-02-15', '2024-02-17', 2, 3, 1700000, 1700000, 'checked_out', 3, 0),
('BOOK010', 10, 12, '2024-02-20', '2024-02-22', 2, 2, 1100000, 1100000, 'checked_out', 2, 0),
('BOOK011', 11, 3, '2024-03-01', '2024-03-05', 4, 2, 2200000, 2200000, 'checked_in', 2, 0),
('BOOK012', 12, 4, '2024-03-02', '2024-03-04', 2, 2, 1100000, 1100000, 'checked_in', 2, 0),
('BOOK013', 13, 5, '2024-03-03', '2024-03-06', 3, 4, 2250000, 2250000, 'checked_in', 4, 0),
('BOOK014', 14, 6, '2024-03-05', '2024-03-07', 2, 2, 2400000, 2400000, 'confirmed', 2, 0),
('BOOK015', 15, 7, '2024-03-08', '2024-03-10', 2, 2, 1100000, 1100000, 'confirmed', 2, 0),
('BOOK016', 1, 8, '2024-03-12', '2024-03-15', 3, 2, 1650000, 1650000, 'pending', 2, 0),
('BOOK017', 2, 9, '2024-03-14', '2024-03-16', 2, 3, 1700000, 1700000, 'pending', 3, 0),
('BOOK018', 3, 10, '2024-03-18', '2024-03-20', 2, 2, 700000, 700000, 'pending', 2, 0),
('BOOK019', 4, 11, '2024-03-22', '2024-03-25', 3, 4, 2250000, 2250000, 'pending', 4, 0),
('BOOK020', 5, 12, '2024-03-25', '2024-03-27', 2, 2, 1100000, 1100000, 'pending', 2, 0);

-- 6. BOOKING DETAILS DATA
INSERT INTO booking_details (booking_id, room_id, price_per_night, total_price) VALUES 
(1, 3, 350000, 700000),
(2, 4, 550000, 1650000),
(3, 19, 850000, 1700000),
(4, 5, 550000, 1100000),
(5, 20, 750000, 1500000),
(6, 6, 550000, 1100000),
(7, 7, 350000, 1050000),
(8, 8, 350000, 700000),
(9, 21, 850000, 1700000),
(10, 9, 550000, 1100000),
(11, 10, 550000, 2200000),
(12, 11, 550000, 1100000),
(13, 22, 750000, 2250000),
(14, 23, 1200000, 2400000),
(15, 12, 550000, 1100000),
(16, 13, 550000, 1650000),
(17, 24, 850000, 1700000),
(18, 14, 350000, 700000),
(19, 25, 750000, 2250000),
(20, 15, 550000, 1100000);

-- 7. PAYMENTS DATA
INSERT INTO payments (booking_id, amount, payment_method, payment_status, payment_date, transaction_id) VALUES 
(1, 700000, 'transfer', 'paid', '2024-01-14 10:30:00', 'TRX001'),
(2, 1650000, 'credit_card', 'paid', '2024-01-15 14:20:00', 'TRX002'),
(3, 1700000, 'qris', 'paid', '2024-01-17 09:15:00', 'TRX003'),
(4, 1100000, 'cash', 'paid', '2024-01-19 16:45:00', 'TRX004'),
(5, 1500000, 'transfer', 'paid', '2024-01-24 11:30:00', 'TRX005'),
(6, 1100000, 'debit_card', 'paid', '2024-01-31 13:20:00', 'TRX006'),
(7, 1050000, 'qris', 'paid', '2024-02-04 15:10:00', 'TRX007'),
(8, 700000, 'cash', 'paid', '2024-02-09 10:45:00', 'TRX008'),
(9, 1700000, 'credit_card', 'paid', '2024-02-14 12:30:00', 'TRX009'),
(10, 1100000, 'transfer', 'paid', '2024-02-19 14:15:00', 'TRX010'),
(11, 2200000, 'credit_card', 'paid', '2024-02-28 16:20:00', 'TRX011'),
(12, 1100000, 'qris', 'paid', '2024-03-01 09:45:00', 'TRX012'),
(13, 2250000, 'transfer', 'paid', '2024-03-02 11:30:00', 'TRX013'),
(14, 1200000, 'credit_card', 'paid', '2024-03-04 13:15:00', 'TRX014'),
(15, 550000, 'qris', 'pending', NULL, 'TRX015'),
(16, 825000, 'transfer', 'pending', NULL, 'TRX016'),
(17, 850000, 'cash', 'pending', NULL, 'TRX017'),
(18, 350000, 'qris', 'pending', NULL, 'TRX018'),
(19, 1125000, 'credit_card', 'pending', NULL, 'TRX019'),
(20, 550000, 'transfer', 'pending', NULL, 'TRX020');

-- 8. FACILITIES DATA
INSERT INTO facilities (name, description, icon, category) VALUES 
-- Room Facilities
('AC', 'Air Conditioner dengan remote control', 'snowflake', 'room'),
('TV LED 32"', 'Television LED 32 inch dengan channel kabel', 'tv', 'room'),
('WiFi Gratis', 'Free WiFi high speed unlimited', 'wifi', 'room'),
('Mini Bar', 'Mini bar dengan minuman dan snack', 'glass-martini', 'room'),
('Bathub', 'Bathub mewah dengan shower terpisah', 'bath', 'room'),
('Kamar Mandi Lux', 'Kamar mandi lengkap dengan amenities', 'shower', 'room'),
('Safe Deposit Box', 'Safe deposit box elektronik', 'lock', 'room'),
('Kettle & Coffee', 'Electric kettle dengan kopi/teh gratis', 'coffee', 'room'),
('Working Desk', 'Meja kerja ergonomis', 'desk', 'room'),

-- Hotel Facilities
('Kolam Renang', 'Swimming Pool outdoor dengan view', 'swimming-pool', 'hotel'),
('Fitness Center', 'Gym lengkap 24 jam', 'dumbbell', 'hotel'),
('Spa & Massage', 'Spa & Massage therapy', 'spa', 'hotel'),
('Restaurant', 'All-day dining restaurant', 'utensils', 'hotel'),
('Bar & Lounge', 'Bar dengan live music', 'cocktail', 'hotel'),
('Meeting Room', 'Ruangan meeting untuk bisnis', 'users', 'hotel'),
('Business Center', 'Business center dengan printer', 'print', 'hotel'),
('Parkir Gratis', 'Free parking area yang luas', 'parking', 'hotel'),

-- Services
('Room Service 24jam', '24 hours room service', 'concierge-bell', 'service'),
('Laundry Service', 'Laundry dan dry cleaning', 'tshirt', 'service'),
('Airport Transfer', 'Airport pick-up & drop-off', 'shuttle-van', 'service'),
('Tour Desk', 'Tour booking service', 'map-marked', 'service'),
('Car Rental', 'Car rental service', 'car', 'service'),
('Concierge', 'Concierge service', 'bell-concierge', 'service');

-- 9. ROOM FACILITIES DATA
INSERT INTO room_facilities (room_type_id, facility_id) VALUES
-- Standard Single (1)
(1, 1), (1, 2), (1, 3), (1, 6), (1, 8), (1, 9),

-- Standard Double (2)  
(2, 1), (2, 2), (2, 3), (2, 6), (2, 8), (2, 9),

-- Deluxe King (3)
(3, 1), (3, 2), (3, 3), (3, 4), (3, 5), (3, 6), (3, 7), (3, 8), (3, 9),

-- Deluxe Twin (4)
(4, 1), (4, 2), (4, 3), (4, 4), (4, 5), (4, 6), (4, 7), (4, 8), (4, 9),

-- Executive Suite (5)
(5, 1), (5, 2), (5, 3), (5, 4), (5, 5), (5, 6), (5, 7), (5, 8), (5, 9),

-- Family Room (6)
(6, 1), (6, 2), (6, 3), (6, 4), (6, 5), (6, 6), (6, 7), (6, 8), (6, 9),

-- Presidential Suite (7)
(7, 1), (7, 2), (7, 3), (7, 4), (7, 5), (7, 6), (7, 7), (7, 8), (7, 9),

-- Honeymoon Suite (8)
(8, 1), (8, 2), (8, 3), (8, 4), (8, 5), (8, 6), (8, 7), (8, 8), (8, 9);

-- 10. REVIEWS DATA
INSERT INTO reviews (booking_id, user_id, room_id, rating_cleanliness, rating_comfort, rating_location, rating_service, rating_facilities, overall_rating, comment, is_approved) VALUES 
(1, 3, 3, 4, 5, 5, 4, 4, 4.4, 'Kamar nyaman dan bersih. Pelayanan bagus!', TRUE),
(2, 4, 4, 5, 5, 5, 5, 5, 5.0, 'Pengalaman menginap yang luar biasa!', TRUE),
(3, 5, 19, 4, 4, 5, 5, 4, 4.4, 'Suite executive sangat luas dan nyaman', TRUE),
(4, 6, 5, 5, 4, 4, 4, 4, 4.2, 'Kamar deluxe sesuai ekspektasi', TRUE),
(5, 7, 20, 4, 5, 5, 5, 4, 4.6, 'Family room perfect untuk liburan keluarga', TRUE),
(6, 8, 6, 3, 4, 4, 4, 3, 3.6, 'Kamar cukup nyaman tapi AC agak berisik', TRUE),
(7, 9, 7, 5, 5, 4, 5, 4, 4.6, 'Pelayanan sangat ramah dan helpful', TRUE),
(8, 10, 8, 4, 4, 5, 4, 4, 4.2, 'Location strategis, dekat dengan mall', TRUE);

-- 11. EMPLOYEES DATA
INSERT INTO employees (employee_code, full_name, position, department, phone, email, hire_date, salary) VALUES 
('EMP001', 'Budi Santoso', 'General Manager', 'management', '+62 811 1234 567', 'budi.manager@hotel.com', '2020-01-15', 25000000),
('EMP002', 'Sari Indah', 'Front Office Manager', 'front_office', '+62 811 2345 678', 'sari.fo@hotel.com', '2020-03-20', 12000000),
('EMP003', 'Ahmad Rizki', 'Receptionist', 'front_office', '+62 811 3456 789', 'ahmad.reception@hotel.com', '2021-05-10', 6000000),
('EMP004', 'Dewi Lestari', 'Housekeeping Supervisor', 'housekeeping', '+62 811 4567 890', 'dewi.hk@hotel.com', '2020-08-15', 8000000),
('EMP005', 'Rudi Hermawan', 'Room Attendant', 'housekeeping', '+62 811 5678 901', 'rudi.attendant@hotel.com', '2021-02-28', 4500000),
('EMP006', 'Maya Sari', 'Chef de Cuisine', 'fbs', '+62 811 6789 012', 'maya.chef@hotel.com', '2019-11-10', 15000000),
('EMP007', 'Joko Prasetyo', 'Sous Chef', 'fbs', '+62 811 7890 123', 'joko.chef@hotel.com', '2020-07-22', 9000000),
('EMP008', 'Linda Wati', 'Waitress', 'fbs', '+62 811 8901 234', 'linda.waitress@hotel.com', '2021-04-15', 5000000),
('EMP009', 'Hendra Gunawan', 'Security Head', 'security', '+62 811 9012 345', 'hendra.security@hotel.com', '2020-06-30', 7000000),
('EMP010', 'Rina Melati', 'Accountant', 'management', '+62 811 0123 456', 'rina.accounting@hotel.com', '2021-01-10', 10000000);

-- 12. SERVICES DATA
INSERT INTO services (service_name, description, price, category) VALUES 
('Breakfast Buffet', 'Breakfast buffet international', 150000, 'food'),
('Lunch Set Menu', 'Set menu lunch 3 course', 250000, 'food'),
('Dinner Buffet', 'Dinner buffet dengan live cooking', 350000, 'food'),
('Room Service - Breakfast', 'Breakfast in room', 200000, 'food'),
('Laundry - Regular', 'Laundry regular service (3kg)', 75000, 'laundry'),
('Laundry - Express', 'Laundry express 4 jam (3kg)', 120000, 'laundry'),
('Dry Cleaning - Suit', 'Dry cleaning untuk jas', 100000, 'laundry'),
('Airport Transfer - Car', 'Airport transfer dengan car', 300000, 'transport'),
('Airport Transfer - Van', 'Airport transfer dengan van', 450000, 'transport'),
('City Tour - Half Day', 'City tour setengah hari', 500000, 'transport'),
('Spa - Traditional Massage', 'Traditional massage 60 menit', 350000, 'spa'),
('Spa - Aromatherapy', 'Aromatherapy massage 90 menit', 500000, 'spa'),
('Spa - Couple Package', 'Couple spa package', 800000, 'spa');

-- 13. SERVICE_ORDERS DATA
INSERT INTO service_orders (booking_id, service_id, quantity, total_price, status) VALUES 
(1, 1, 2, 300000, 'completed'),
(1, 5, 1, 75000, 'completed'),
(2, 1, 2, 300000, 'completed'),
(2, 11, 1, 350000, 'completed'),
(3, 4, 3, 600000, 'completed'),
(4, 1, 2, 300000, 'completed'),
(5, 1, 4, 600000, 'completed'),
(6, 8, 1, 300000, 'completed'),
(7, 1, 2, 300000, 'completed'),
(11, 1, 2, 300000, 'processing'),
(11, 12, 1, 500000, 'pending'),
(12, 1, 2, 300000, 'pending'),
(13, 1, 4, 600000, 'pending');

-- 14. SETTINGS DATA
INSERT INTO settings (hotel_name, address, phone, email, website, tax_rate, currency) VALUES 
('Grand Luxury Hotel & Resort', 'Jl. Merdeka No. 123, Jakarta Pusat 10110, Indonesia', '+62 21 1234567', 'info@grandluxuryhotel.com', 'www.grandluxuryhotel.com', 10.00, 'IDR');

-- 15. MAINTENANCE DATA
INSERT INTO maintenance (room_id, issue_type, description, reported_by, status, priority) VALUES 
(6, 'AC Repair', 'AC tidak dingin, perlu service', 2, 'completed', 'high'),
(3, 'Plumbing', 'Kran air di kamar mandi bocor', 3, 'in_progress', 'medium'),
(15, 'TV Issue', 'TV tidak bisa nyala', 4, 'reported', 'low'),
(24, 'Furniture', 'Kursi di kamar rusak', 5, 'reported', 'medium');