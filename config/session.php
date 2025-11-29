<?php
// Set timezone ke Indonesia (WIB - GMT+7)
date_default_timezone_set('Asia/Jakarta');

// Konfigurasi Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fungsi untuk mendapatkan base path relatif ke root
function getBasePath() {
    if (!isset($_SERVER['SCRIPT_NAME'])) {
        return '';
    }
    
    $script_path = dirname($_SERVER['SCRIPT_NAME']);
    $path_parts = array_filter(explode('/', $script_path));
    $levels = count($path_parts);
    
    return $levels > 0 ? str_repeat('../', $levels) : '';
}

// Fungsi untuk cek apakah user sudah login
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

// Fungsi untuk redirect berdasarkan role
function redirectByRole($role) {
    switch($role) {
        case 'developer':
            header('Location: ../dashboard/developer/index.php');
            break;
        case 'kepala_sekolah':
            header('Location: ../dashboard/kepala_sekolah/index.php');
            break;
        case 'guru':
            header('Location: ../dashboard/guru/index.php');
            break;
        case 'siswa':
            header('Location: ../dashboard/siswa/index.php');
            break;
        case 'akademik':
            header('Location: ../dashboard/akademik/index.php');
            break;
        default:
            header('Location: ../login.php');
    }
    exit();
}

// Fungsi untuk cek role dan redirect jika tidak sesuai
function requireRole($allowedRoles) {
    if (!isLoggedIn()) {
        header('Location: ../login.php');
        exit();
    }
    
    if (!in_array($_SESSION['user_role'], $allowedRoles)) {
        header('Location: ../login.php');
        exit();
    }
}
?>

