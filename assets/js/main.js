// Main JavaScript untuk Portal Sekolah

$(document).ready(function() {
    // Pastikan semua modal yang tersembunyi benar-benar tidak terlihat dan tidak bisa diklik
    $('.modal:not(.show)').css({
        'display': 'none',
        'pointer-events': 'none',
        'visibility': 'hidden',
        'opacity': '0'
    });
    
    // Hapus backdrop yang tersisa
    $('.modal-backdrop:not(.show)').remove();
    
    // Pastikan body tidak memiliki class modal-open jika tidak ada modal yang terbuka
    if ($('.modal.show').length === 0) {
        $('body').removeClass('modal-open');
        $('.modal-backdrop').remove();
    }
    
    // Sidebar Toggle
    $('#sidebarCollapse').on('click', function() {
        $('#sidebar').toggleClass('active');
        $('#content').toggleClass('active');
    });
    
    // Auto-hide sidebar on mobile after click (exclude dropdown toggles)
    if ($(window).width() <= 768) {
        $('.sidebar a').on('click', function(e) {
            // Don't hide sidebar if clicking dropdown toggle
            if (!$(this).hasClass('dropdown-toggle')) {
                $('#sidebar').removeClass('active');
                $('#content').removeClass('active');
            }
        });
    }
    
    // Custom dropdown toggle without animation
    $(document).on('click', '.sidebar .dropdown-toggle', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $toggle = $(this);
        const targetId = $toggle.data('target');
        
        if (!targetId) {
            return false;
        }
        
        const $submenu = $('#' + targetId);
        
        if (!$submenu.length) {
            return false;
        }
        
        // Toggle submenu
        if ($submenu.hasClass('show')) {
            $submenu.removeClass('show');
            $toggle.removeClass('active');
        } else {
            $submenu.addClass('show');
            $toggle.addClass('active');
        }
        
        return false;
    });
    
    
    // Initialize all DataTables with default settings
    if ($.fn.DataTable) {
        $('.dataTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
            },
            responsive: true,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Semua"]],
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
            order: [[0, 'asc']]
        });
    }
    
    // Pastikan saat modal dibuka, semua elemen bisa diklik
    $(document).on('shown.bs.modal', '.modal', function() {
        // Hapus inline style yang mungkin mengganggu
        $(this).css({
            'display': '',
            'pointer-events': '',
            'visibility': '',
            'opacity': ''
        });
        // Pastikan semua elemen di dalam modal bisa diklik
        $(this).find('.modal-dialog, .modal-content, input, select, textarea, button, .btn, label, .form-label, .form-control, .form-select').css({
            'pointer-events': 'auto',
            'z-index': '1072'
        });
    });
    
    // Reset modal form when closed dan pastikan backdrop dihapus
    $('.modal').on('hidden.bs.modal', function () {
        $(this).find('form')[0].reset();
        $(this).find('.is-invalid').removeClass('is-invalid');
        $(this).find('.invalid-feedback').remove();
        // Hapus inline style yang mungkin mengganggu
        $(this).css({
            'display': '',
            'pointer-events': '',
            'visibility': '',
            'opacity': ''
        });
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open');
    });
    
    // Pastikan saat modal ditutup, semua backdrop dihapus
    $(document).on('hidden.bs.modal', '.modal', function() {
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open');
        // Hapus inline style yang mungkin mengganggu
        $(this).css({
            'display': '',
            'pointer-events': '',
            'visibility': '',
            'opacity': ''
        });
    });
});

// SweetAlert2 Helper Functions
function showSuccess(message) {
    Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: message,
        timer: 2000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}

function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: message,
        confirmButtonText: 'OK'
    });
}

function showConfirm(message, callback) {
    Swal.fire({
        title: 'Apakah Anda yakin?',
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#6366f1',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, lanjutkan!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed && callback) {
            callback();
        }
    });
}

// Form validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return false;
    }
    return true;
}

// Confirm delete with SweetAlert (for inline forms)
function confirmDelete(type) {
    return Swal.fire({
        title: 'Apakah Anda yakin?',
        text: 'Data ' + type + ' akan dihapus secara permanen!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        return result.isConfirmed;
    });
}

// Initialize DataTable with custom options
function initDataTable(tableId, options = {}) {
    const defaultOptions = {
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
        },
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Semua"]],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
        order: [[0, 'asc']]
    };
    
    return $(tableId).DataTable($.extend(true, {}, defaultOptions, options));
}
