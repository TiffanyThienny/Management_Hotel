// Custom JavaScript for Hotel Management System

$(document).ready(function() {
    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Initialize popovers
    $('[data-bs-toggle="popover"]').popover();
    
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
    
    // Confirm delete actions
    $('.confirm-delete').on('click', function(e) {
        if (!confirm('Apakah Anda yakin ingin menghapus data ini?')) {
            e.preventDefault();
        }
    });
    
    // Form validation
    $('form.needs-validation').on('submit', function(e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        $(this).addClass('was-validated');
    });
    
    // Password strength indicator
    $('#password').on('input', function() {
        const password = $(this).val();
        const strength = calculatePasswordStrength(password);
        updatePasswordStrengthIndicator(strength);
    });
    
    // Date range validation
    $('.date-range').on('change', function() {
        const checkIn = new Date($('#check_in').val());
        const checkOut = new Date($('#check_out').val());
        
        if (checkOut <= checkIn) {
            alert('Tanggal check-out harus setelah tanggal check-in');
            $('#check_out').val('');
        }
    });
    
    // Room capacity validation
    $('#adults, #children').on('change', function() {
        const adults = parseInt($('#adults').val()) || 0;
        const children = parseInt($('#children').val()) || 0;
        const totalGuests = adults + children;
        const capacity = parseInt($('#room_capacity').val()) || 0;
        
        $('#total_guests').val(totalGuests);
        
        if (capacity > 0 && totalGuests > capacity) {
            $('#capacity_warning').show();
        } else {
            $('#capacity_warning').hide();
        }
    });
    
    // Dynamic price calculation
    function calculateTotalPrice() {
        const checkIn = new Date($('#check_in').val());
        const checkOut = new Date($('#check_out').val());
        const pricePerNight = parseFloat($('#price_per_night').val()) || 0;
        
        if (checkIn && checkOut && checkOut > checkIn) {
            const nights = Math.ceil((checkOut - checkIn) / (1000 * 60 * 60 * 24));
            const total = nights * pricePerNight;
            
            $('#total_nights').text(nights);
            $('#total_amount').text(formatCurrency(total));
            $('#final_amount').val(total);
        }
    }
    
    $('input[name="check_in"], input[name="check_out"]').on('change', calculateTotalPrice);
    $('#price_per_night').on('change', calculateTotalPrice);
    
    // Payment method toggle
    $('select[name="payment_method"]').on('change', function() {
        const method = $(this).val();
        
        // Hide all additional fields
        $('.bank-fields, .card-fields, .ewallet-fields').hide();
        
        // Show relevant fields
        if (method === 'transfer') {
            $('.bank-fields').show();
        } else if (method === 'credit_card' || method === 'debit_card') {
            $('.card-fields').show();
        } else if (method === 'ovo' || method === 'gopay') {
            $('.ewallet-fields').show();
        }
    });
    
    // Image preview for file uploads
    $('input[type="file"]').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#image-preview').attr('src', e.target.result).show();
            }
            reader.readAsDataURL(file);
        }
    });
    
    // Auto-refresh statistics (for admin dashboard)
    if ($('#dashboard-stats').length) {
        setInterval(refreshDashboardStats, 30000); // Refresh every 30 seconds
    }
    
    // Search functionality
    $('#search-input').on('keyup', function() {
        const value = $(this).val().toLowerCase();
        $('.searchable-item').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });
});

// Utility Functions
function formatCurrency(amount) {
    return 'Rp ' + amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function calculatePasswordStrength(password) {
    let strength = 0;
    
    if (password.length >= 8) strength += 25;
    if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 25;
    if (password.match(/\d/)) strength += 25;
    if (password.match(/[^a-zA-Z\d]/)) strength += 25;
    
    return strength;
}

function updatePasswordStrengthIndicator(strength) {
    const indicator = $('#password-strength');
    const text = $('#password-strength-text');
    
    indicator.css('width', strength + '%');
    
    if (strength < 50) {
        indicator.removeClass('bg-warning bg-success').addClass('bg-danger');
        text.text('Weak').removeClass('text-warning text-success').addClass('text-danger');
    } else if (strength < 75) {
        indicator.removeClass('bg-danger bg-success').addClass('bg-warning');
        text.text('Medium').removeClass('text-danger text-success').addClass('text-warning');
    } else {
        indicator.removeClass('bg-danger bg-warning').addClass('bg-success');
        text.text('Strong').removeClass('text-danger text-warning').addClass('text-success');
    }
}

function refreshDashboardStats() {
    $.ajax({
        url: 'admin/ajax/statistics.php',
        method: 'GET',
        success: function(data) {
            $('#total-rooms').text(data.total_rooms);
            $('#available-rooms').text(data.available_rooms);
            $('#total-bookings').text(data.total_bookings);
            $('#pending-bookings').text(data.pending_bookings);
        },
        error: function() {
            console.log('Error refreshing statistics');
        }
    });
}

// Export data to Excel
function exportToExcel(tableId, filename) {
    const table = document.getElementById(tableId);
    const html = table.outerHTML;
    const url = 'data:application/vnd.ms-excel;charset=utf-8,' + encodeURIComponent(html);
    const link = document.createElement('a');
    link.download = filename + '.xls';
    link.href = url;
    link.click();
}

// Print specific element
function printElement(elementId) {
    const printContent = document.getElementById(elementId).innerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = printContent;
    window.print();
    document.body.innerHTML = originalContent;
    location.reload();
}

// Date utilities
function getToday() {
    return new Date().toISOString().split('T')[0];
}

function addDays(date, days) {
    const result = new Date(date);
    result.setDate(result.getDate() + days);
    return result.toISOString().split('T')[0];
}

// Notification system
function showNotification(message, type = 'info') {
    const alertClass = {
        'success': 'alert-success',
        'error': 'alert-danger',
        'warning': 'alert-warning',
        'info': 'alert-info'
    }[type] || 'alert-info';
    
    const notification = $(
        '<div class="alert ' + alertClass + ' alert-dismissible fade show" role="alert">' +
        message +
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
        '</div>'
    );
    
    $('#notifications').append(notification);
    
    setTimeout(function() {
        notification.alert('close');
    }, 5000);
}

// Loading state for buttons
function setButtonLoading(button, isLoading) {
    const $button = $(button);
    
    if (isLoading) {
        $button.prop('disabled', true);
        $button.data('original-text', $button.html());
        $button.html('<span class="loading-spinner me-2"></span>Loading...');
    } else {
        $button.prop('disabled', false);
        $button.html($button.data('original-text'));
    }
}

// Form auto-save
function initAutoSave(formId, saveUrl) {
    const $form = $(formId);
    let timeout;
    
    $form.on('input', function() {
        clearTimeout(timeout);
        timeout = setTimeout(function() {
            const formData = $form.serialize();
            
            $.ajax({
                url: saveUrl,
                method: 'POST',
                data: formData,
                success: function() {
                    showNotification('Changes saved automatically', 'success');
                },
                error: function() {
                    showNotification('Failed to save changes', 'error');
                }
            });
        }, 2000);
    });
}

// Responsive table helper
function makeTableResponsive(tableId) {
    const $table = $(tableId);
    const $wrapper = $('<div class="table-responsive"></div>');
    
    $table.wrap($wrapper);
}

// Chart initialization
function initRevenueChart(canvasId, data) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    return new Chart(ctx, {
        type: 'line',
        data: data,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Revenue Chart'
                }
            }
        }
    });
}