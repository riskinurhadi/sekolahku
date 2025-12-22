<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// Get user info
$conn = getConnection();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$sekolah_id = $_SESSION['sekolah_id'] ?? null;

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sekolah_id && isset($user['sekolah_id'])) {
    $sekolah_id = $user['sekolah_id'];
}

// Get count of unread informasi akademik
$unread_count = 0;
// ... (PHP logic for notifications remains the same)
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Portal Sekolah'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <style>
        :root {
            --bs-primary-rgb: 67, 97, 238;
            --bs-secondary-rgb: 63, 55, 201;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 280px;
            z-index: 1000;
            background-color: #fff;
            box-shadow: 0 0 2rem rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        @media (max-width: 991.98px) {
            .sidebar {
                left: -280px;
            }
            .sidebar.active {
                left: 0;
            }
            .main-content {
                width: 100%;
                margin-left: 0;
            }
        }

        .sidebar-header {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            border-bottom: 1px solid #dee2e6;
        }
        .sidebar-header img {
            max-height: 40px;
        }

        .sidebar-nav {
            padding: 1rem;
        }
        .sidebar-nav .nav-link {
            display: flex;
            align-items: center;
            padding: .75rem 1rem;
            color: #495057;
            border-radius: .5rem;
            margin-bottom: .25rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .sidebar-nav .nav-link:hover {
            color: var(--bs-primary);
            background-color: rgba(var(--bs-primary-rgb), 0.08);
        }
        .sidebar-nav .nav-link.active {
            color: #fff;
            background: linear-gradient(45deg, rgb(var(--bs-primary-rgb)), rgb(var(--bs-secondary-rgb)));
            box-shadow: 0 7px 15px rgba(var(--bs-primary-rgb), 0.3);
        }
        .sidebar-nav .nav-link i {
            font-size: 1.2rem;
            margin-right: 1rem;
            width: 20px;
            text-align: center;
        }
        .sidebar-nav .nav-item .collapse .nav-link {
            padding-left: 3.5rem;
            font-size: .9em;
        }

        .main-content {
            margin-left: 280px;
            transition: all 0.3s ease;
            padding: 1.5rem;
        }

        .top-navbar {
            background-color: #fff;
            border-radius: .75rem;
            padding: .75rem 1.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }
        
        .navbar-toggler {
            border: none;
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .dropdown-menu-end {
            border-radius: .75rem;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1);
            border: 1px solid #dee2e6;
        }

    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="#"><img src="<?php echo getBasePath(); ?>assets/img/sekolahku.png" alt="Sekolahku"></a>
            </div>

            <ul class="nav flex-column sidebar-nav">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>dashboard/siswa/index.php">
                        <i class="bi bi-house-door-fill"></i>
                        <span>Beranda</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'jadwal.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>dashboard/siswa/jadwal.php">
                        <i class="bi bi-calendar-week-fill"></i>
                        <span>Jadwal Pelajaran</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'informasi_akademik.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>dashboard/siswa/informasi_akademik.php">
                        <i class="bi bi-megaphone-fill"></i>
                        <span>Informasi Akademik</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#ujianSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="ujianSubmenu">
                        <i class="bi bi-journal-text"></i>
                        <span>Ujian</span>
                    </a>
                    <div class="collapse" id="ujianSubmenu">
                        <ul class="nav flex-column">
                            <li class="nav-item"><a class="nav-link" href="<?php echo getBasePath(); ?>dashboard/siswa/soal_saya.php">Latihan</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?php echo getBasePath(); ?>dashboard/siswa/uts.php">UTS</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?php echo getBasePath(); ?>dashboard/siswa/uas.php">UAS</a></li>
                        </ul>
                    </div>
                </li>
                 <li class="nav-item">
                    <a class="nav-link" href="#hasilSubmenu" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="hasilSubmenu">
                        <i class="bi bi-clipboard-check-fill"></i>
                        <span>Hasil</span>
                    </a>
                    <div class="collapse" id="hasilSubmenu">
                        <ul class="nav flex-column">
                            <li class="nav-item"><a class="nav-link" href="<?php echo getBasePath(); ?>dashboard/siswa/presensi.php">Rekap Kehadiran</a></li>
                            <li class="nav-item"><a class="nav-link" href="<?php echo getBasePath(); ?>dashboard/siswa/hasil_latihan.php">Hasil Latihan</a></li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profil.php' ? 'active' : ''; ?>" href="<?php echo getBasePath(); ?>dashboard/siswa/profil.php">
                        <i class="bi bi-person-circle"></i>
                        <span>Profil</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <div class="main-content" id="main-content">
            <!-- Top Navbar -->
            <nav class="navbar navbar-expand-lg navbar-light top-navbar">
                <div class="container-fluid">
                    <button class="navbar-toggler" type="button" id="sidebar-toggle">
                        <span class="navbar-toggler-icon"></span>
                    </button>

                    <div class="ms-auto">
                        <ul class="navbar-nav align-items-center">
                            <li class="nav-item me-3">
                                <a class="nav-link" href="<?php echo getBasePath(); ?>dashboard/<?php echo $user_role; ?>/informasi_akademik.php">
                                    <i class="bi bi-bell-fill fs-5"></i>
                                    <?php if ($unread_count > 0): ?>
                                        <span class="badge rounded-pill bg-danger position-absolute top-0 start-100 translate-middle">
                                            <?php echo $unread_count > 9 ? '9+' : $unread_count; ?>
                                        </span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <?php 
                                    $foto_profil_path = getBasePath() . 'uploads/profil/' . ($user['foto_profil'] ?? '');
                                    if (!empty($user['foto_profil']) && file_exists(dirname(__DIR__).'/uploads/profil/' . $user['foto_profil'])):
                                    ?>
                                        <img src="<?php echo $foto_profil_path; ?>" alt="Avatar" class="avatar me-2">
                                    <?php else: ?>
                                        <div class="avatar me-2 bg-primary text-white d-flex justify-content-center align-items-center">
                                            <?php echo strtoupper(substr($user['nama_lengkap'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <span class="d-none d-md-inline"><?php echo htmlspecialchars($user['nama_lengkap']); ?></span>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <li><a class="dropdown-item" href="<?php echo getBasePath(); ?>dashboard/siswa/profil.php">Profil Saya</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="<?php echo getBasePath(); ?>logout.php">Logout</a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>

            <!-- Page Content goes here -->
            <div class="container-fluid">