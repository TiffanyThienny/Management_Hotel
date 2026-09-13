    <!-- Footer -->
    <footer class="bg-dark text-light mt-5">
        <div class="container py-4">
            <div class="row">
                <div class="col-md-4">
                    <h5><?php echo HOTEL_NAME; ?></h5>
                    <p class="mb-2"><?php echo HOTEL_ADDRESS; ?></p>
                    <p class="mb-2"><i class="fas fa-phone me-2"></i><?php echo HOTEL_PHONE; ?></p>
                    <p class="mb-0"><i class="fas fa-envelope me-2"></i><?php echo HOTEL_EMAIL; ?></p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="../index.php" class="text-light">Home</a></li>
                        <li><a href="../user/rooms.php" class="text-light">Rooms</a></li>
                        <?php if (isLoggedIn()): ?>
                            <?php if (isAdmin() || isReceptionist()): ?>
                                <li><a href="../admin/dashboard.php" class="text-light">Admin Dashboard</a></li>
                            <?php else: ?>
                                <li><a href="../user/index.php" class="text-light">User Dashboard</a></li>
                            <?php endif; ?>
                        <?php else: ?>
                            <li><a href="../login.php" class="text-light">Login</a></li>
                            <li><a href="../register.php" class="text-light">Register</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Hotel Services</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-wifi me-2"></i>Free WiFi</li>
                        <li><i class="fas fa-swimming-pool me-2"></i>Swimming Pool</li>
                        <li><i class="fas fa-utensils me-2"></i>Restaurant</li>
                        <li><i class="fas fa-spa me-2"></i>Spa & Massage</li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="row">
                <div class="col-md-6">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo HOTEL_NAME; ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>Hotel Management System v<?php echo APP_VERSION; ?></p>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Custom JavaScript -->
    <script src="../assets/js/script.js"></script>

    <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('.datatable').DataTable({
                "language": {
                    "search": "Cari:",
                    "lengthMenu": "Tampilkan _MENU_ data",
                    "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    "paginate": {
                        "first": "Pertama",
                        "last": "Terakhir",
                        "next": "Berikutnya",
                        "previous": "Sebelumnya"
                    }
                },
                "responsive": true
            });

            // Initialize datepicker
            flatpickr(".datepicker", {
                dateFormat: "Y-m-d",
                minDate: "today"
            });

            // Initialize datetime picker
            flatpickr(".datetimepicker", {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
            });

            // Confirm delete actions
            $('.confirm-delete').on('click', function(e) {
                e.preventDefault();
                var form = $(this).closest('form');
                Swal.fire({
                    title: 'Apakah Anda yakin?',
                    text: "Data yang dihapus tidak dapat dikembalikan!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Ya, hapus!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });

            // Auto-dismiss alerts
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);

            // Room availability check
            $('.check-availability').on('click', function() {
                var roomType = $(this).data('room-type');
                var checkIn = $('#check_in').val();
                var checkOut = $('#check_out').val();
                
                if (!checkIn || !checkOut) {
                    Swal.fire('Error', 'Silakan pilih tanggal check-in dan check-out', 'error');
                    return;
                }

                // Redirect to availability check
                window.location.href = '../user/rooms.php?type=' + roomType + '&check_in=' + checkIn + '&check_out=' + checkOut;
            });
        });

        // Format currency for display
        function formatCurrency(amount) {
            return 'Rp ' + amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        // Calculate total amount
        function calculateTotal() {
            var checkIn = new Date($('#check_in').val());
            var checkOut = new Date($('#check_out').val());
            var pricePerNight = parseFloat($('#price_per_night').val()) || 0;
            
            if (checkIn && checkOut && checkOut > checkIn) {
                var nights = Math.ceil((checkOut - checkIn) / (1000 * 60 * 60 * 24));
                var total = nights * pricePerNight;
                $('#total_nights').text(nights);
                $('#total_amount').text(formatCurrency(total));
                $('#final_amount').val(total);
            }
        }

        // Update statistics in real-time (for admin dashboard)
        function updateStatistics() {
            $.ajax({
                url: '../admin/ajax/statistics.php',
                method: 'GET',
                success: function(data) {
                    $('#total-rooms').text(data.total_rooms);
                    $('#available-rooms').text(data.available_rooms);
                    $('#total-bookings').text(data.total_bookings);
                    $('#pending-bookings').text(data.pending_bookings);
                }
            });
        }

        // Update statistics every 30 seconds
        setInterval(updateStatistics, 30000);
    </script>
</body>
</html>