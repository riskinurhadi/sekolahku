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
    
    // Auto-hide sidebar on mobile after click
    if ($(window).width() <= 768) {
        $('.sidebar a').on('click', function() {
            $('#sidebar').removeClass('active');
            $('#content').removeClass('active');
        });
    }
    
    // Fix dropdown collapse size issue - prevent shrinking
    function lockDropdownSize() {
        $('#hasilSubmenu').find('a').each(function() {
            const $link = $(this);
            $link.css({
                'font-size': '13px',
                'padding': '10px 20px',
                'padding-left': '48px',
                'transform': 'none',
                'scale': '1',
                'zoom': '1',
                'line-height': '1.5'
            });
            
            // Also lock icon size
            $link.find('i').css({
                'font-size': '16px',
                'width': '18px'
            });
        });
    }
    
    // Lock size on all collapse events
    $('#hasilSubmenu').on('show.bs.collapse shown.bs.collapse hide.bs.collapse hidden.bs.collapse', function() {
        lockDropdownSize();
    });
    
    // Also monitor during collapsing state
    $('#hasilSubmenu').on('show.bs.collapse', function() {
        const checkInterval = setInterval(function() {
            if ($('#hasilSubmenu').hasClass('collapsing')) {
                lockDropdownSize();
            } else {
                clearInterval(checkInterval);
                lockDropdownSize();
            }
        }, 10);
    });
    
    // Also fix for any collapse elements in sidebar
    $('.sidebar .collapse').on('shown.bs.collapse', function() {
        $(this).find('a').each(function() {
            $(this).css({
                'font-size': '13px',
                'padding': '10px 20px',
                'padding-left': '48px',
                'transform': 'none',
                'scale': '1',
                'zoom': '1'
            });
        });
    });
    
    // Initial lock for already shown dropdowns
    if ($('#hasilSubmenu').hasClass('show')) {
        lockDropdownSize();
    }
    
    // Use MutationObserver to prevent size changes - more aggressive
    const collapseElement = document.getElementById('hasilSubmenu');
    if (collapseElement) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                    const links = collapseElement.querySelectorAll('a');
                    links.forEach(function(link) {
                        // Force reset all size-related properties
                        link.style.setProperty('font-size', '13px', 'important');
                        link.style.setProperty('padding', '10px 20px', 'important');
                        link.style.setProperty('padding-left', '48px', 'important');
                        link.style.setProperty('transform', 'none', 'important');
                        link.style.setProperty('scale', '1', 'important');
                        link.style.setProperty('zoom', '1', 'important');
                        link.style.setProperty('line-height', '1.5', 'important');
                        
                        // Also lock icon sizes
                        const icons = link.querySelectorAll('i');
                        icons.forEach(function(icon) {
                            icon.style.setProperty('font-size', '16px', 'important');
                            icon.style.setProperty('width', '18px', 'important');
                        });
                    });
                }
            });
        });
        
        observer.observe(collapseElement, {
            attributes: true,
            attributeFilter: ['style', 'class'],
            subtree: true,
            childList: true
        });
        
        // Also observe child elements more aggressively
        const links = collapseElement.querySelectorAll('a');
        links.forEach(function(link) {
            observer.observe(link, {
                attributes: true,
                attributeFilter: ['style', 'class']
            });
            
            // Also observe icons
            const icons = link.querySelectorAll('i');
            icons.forEach(function(icon) {
                observer.observe(icon, {
                    attributes: true,
                    attributeFilter: ['style']
                });
            });
        });
        
        // Additional interval check as backup
        setInterval(function() {
            if (collapseElement.classList.contains('show') || collapseElement.classList.contains('collapsing')) {
                lockDropdownSize();
            }
        }, 50);
    }
    
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
