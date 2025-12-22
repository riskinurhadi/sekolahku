<?php
// Pastikan sesi sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ambil info user untuk sidebar
$user_nama = $_SESSION['nama_lengkap'] ?? 'User';
$user_role = $_SESSION['role'] ?? '';
$sekolah_nama = $_SESSION['sekolah_nama'] ?? 'Sekolahku';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Dashboard'; ?> - <?php echo $sekolah_nama; ?></title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        :root {
            --sidebar-width: 260px;
            --topbar-height: 70px;
            --navy-dark: #0f172a;
            --navy-light: #1e293b;
            --accent-blue: #3b82f6;
            --text-muted: #94a3b8;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            overflow-x: hidden;
        }

        /* Sidebar Styling */
        #sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background-color: var(--navy-dark);
            color: white;
            transition: all 0.3s;
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .sidebar-logo {
            width: 35px;
            height: 35px;
            background: var(--accent-blue);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.2rem;
        }

        .nav-list {
            padding: 1.5rem 0.8rem;
            list-style: none;
            flex-grow: 1;
            overflow-y: auto;
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link-custom {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 15px;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .nav-link-custom i {
            font-size: 1.2rem;
        }

        .nav-link-custom:hover {
            background: rgba(255,255,255,0.05);
            color: white;
        }

        .nav-link-custom.active {
            background: var(--accent-blue);
            color: white;
        }

        /* Topbar Styling */
        #main-wrapper {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: all 0.3s;
        }

        #topbar {
            height: var(--topbar-height);
            background: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            position: sticky;
            top: 0;
            z-index: 999;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .search-box {
            background: #f1f5f9;
            border-radius: 10px;
            padding: 8px 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            width: 300px;
        }

        .search-box input {
            border: none;
            background: transparent;
            outline: none;
            font-size: 0.9rem;
            width: 100%;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 12px;
            transition: background 0.2s;
        }

        .user-profile:hover {
            background: #f8fafc;
        }

        .profile-img {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--navy-dark);
            font-weight: 700;
        }

        .content-area {
            padding: 2rem;
        }

        @media (max-width: 992px) {
            #sidebar { margin-left: calc(-1 * var(--sidebar-width)); }
            #main-wrapper { margin-left: 0; }
            #sidebar.active { margin-left: 0; }
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <nav id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">S</div>
            <div class="overflow-hidden">
                <h6 class="mb-0 fw-bold">Sekolahku</h6>
                <small class="text-muted d-block" style="font-size: 0.7rem;">Sistem Akademik</small>
            </div>
        </div>

        <ul class="nav-list">
            <li class="nav-item">
                <a href="../<?php echo $user_role; ?>/index.php" class="nav-link-custom <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <i class="bi bi-grid-1x2"></i> <span>Dashboard</span>
                </a>
            </li>

            <?php if ($user_role == 'siswa'): ?>
            <li class="nav-item">
                <a href="soal_saya.php" class="nav-link-custom <?php echo basename($_SERVER['PHP_SELF']) == 'soal_saya.php' ? 'active' : ''; ?>">
                    <i class="bi bi-journal-text"></i> <span>Tugas & Soal</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="jadwal.php" class="nav-link-custom <?php echo basename($_SERVER['PHP_SELF']) == 'jadwal.php' ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-week"></i> <span>Jadwal</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="presensi.php" class="nav-link-custom <?php echo basename($_SERVER['PHP_SELF']) == 'presensi.php' ? 'active' : ''; ?>">
                    <i class="bi bi-person-check"></i> <span>Presensi</span>
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-item mt-4">
                <small class="text-muted px-3 text-uppercase fw-bold" style="font-size: 0.65rem;">Lainnya</small>
            </li>
            <li class="nav-item">
                <a href="profil.php" class="nav-link-custom <?php echo basename($_SERVER['PHP_SELF']) == 'profil.php' ? 'active' : ''; ?>">
                    <i class="bi bi-person-circle"></i> <span>Profil</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="../../logout.php" class="nav-link-custom text-danger">
                    <i class="bi bi-box-arrow-left"></i> <span>Keluar</span>
                </a>
            </li>
        </ul>

        <div class="p-3 mt-auto">
            <div class="bg-white bg-opacity-10 rounded-3 p-3 text-center">
                <p class="small mb-0 text-white-50">Butuh bantuan?</p>
                <a href="#" class="btn btn-sm btn-outline-light border-0 w-100">Hubungi Admin</a>
            </div>
        </div>
    </nav>

    <!-- Main Content Wrapper -->
    <main id="main-wrapper">
        <!-- Topbar -->
        <header id="topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn d-lg-none" id="sidebarToggle">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <div class="search-box d-none d-md-flex">
                    <i class="bi bi-search text-muted"></i>
                    <input type="text" placeholder="Cari materi atau tugas...">
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <div class="position-relative me-2">
                    <i class="bi bi-bell fs-5 text-muted"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.5rem; padding: 0.3em 0.5em;">
                        3
                    </span>
                </div>
                
                <div class="user-profile dropdown">
                    <div class="text-end d-none d-sm-block">
                        <p class="mb-0 fw-bold" style="font-size: 0.85rem;"><?php echo htmlspecialchars($user_nama); ?></p>
                        <small class="text-muted text-capitalize" style="font-size: 0.75rem;"><?php echo $user_role; ?></small>
                    </div>
                    <div class="profile-img" data-bs-toggle="dropdown">
                        <?php echo strtoupper(substr($user_nama, 0, 1)); ?>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-3 p-2" style="border-radius: 12px; width: 200px;">
                        <li><a class="dropdown-item rounded-2 py-2" href="profil.php"><i class="bi bi-person me-2"></i> Profil Saya</a></li>
                        <li><a class="dropdown-item rounded-2 py-2" href="#"><i class="bi bi-gear me-2"></i> Pengaturan</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item rounded-2 py-2 text-danger" href="../../logout.php"><i class="bi bi-box-arrow-left me-2"></i> Keluar</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Dynamic Content Start -->
        <div class="content-area">