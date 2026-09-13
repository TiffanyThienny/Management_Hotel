# Website Management Hotel

[![PHP Version](https://img.shields.io/badge/php-%5E7.4%20%7C%20%5E8.0-blue.svg)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/mysql-%252300f.svg?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com/)

Website Management Hotel adalah aplikasi berbasis web yang dirancang untuk mengelola operasional hotel secara efisien. Sistem ini mencakup manajemen data tamu, pemesanan kamar (booking), serta pengelolaan hak akses untuk pengguna biasa (`user`) dan administrator (`admin`).

---

## 📁 Struktur Direktori

Berikut adalah penjelasan singkat mengenai struktur folder dan file utama dalam proyek ini:

* **`admin/`** : Berisi file dan halaman khusus untuk modul manajemen administrator.
* **`assets/`** : Menyimpan file aset statis seperti CSS, JavaScript, gambar, dan font.
* **`config/`** : Berisi konfigurasi inti sistem, termasuk koneksi ke database.
* **`includes/`** : Menyimpan potongan kode reusable (seperti header, footer, atau sidebar) untuk efisiensi penulisan kode.
* **`user/`** : Berisi halaman khusus untuk modul pelanggan atau pengguna biasa.
* **`database.sql`** : File skrip SQL untuk mengimpor struktur tabel dan data awal ke MySQL.
* **`index.php`** : Halaman utama/landing page website.
* **`install.php`** : Skrip untuk membantu proses instalasi atau inisialisasi awal sistem.
* **`login.php` / `logout.php` / `register.php`** : Modul otentikasi pengguna (Masuk, Keluar, dan Daftar Akun).

---

## 🚀 Prasyarat & Instalasi

Pastikan Anda sudah menginstal web server lokal seperti **XAMPP**, **Laragon**, atau sejenisnya yang mendukung PHP dan MySQL.

### Langkah-langkah Instalasi:

1. **Clone atau Unduh Repositori**
   Unduh proyek ini dan letakkan di dalam folder root server Anda (misal: `htdocs` jika menggunakan XAMPP).
   ```bash
   git clone [https://github.com/Alfis-Fathoni/Website_Management_Hotel.git](https://github.com/Alfis-Fathoni/Website_Management_Hotel.git)
