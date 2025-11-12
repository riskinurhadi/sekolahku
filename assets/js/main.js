// Main JavaScript untuk Portal Sekolah

$(document).ready(function() {
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
});

// SweetAlert2 Helper Functions
function showSuccess(message) {
    Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: message,
        timer: 2000,
        showConfirmButton: false
    });
}

function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: message
    });
}

function showConfirm(message, callback) {
    Swal.fire({
        title: 'Apakah Anda yakin?',
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
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

