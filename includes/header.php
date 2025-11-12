<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// Get user info
$conn = getConnection();
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
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
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo getBasePath(); ?>assets/css/style.css">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <h4>Admin <?php echo isset($user['sekolah_id']) && $user['sekolah_id'] ? 'Sekolah' : 'Portal'; ?></h4>
            </div>
            
            <ul class="list-unstyled components">
                <li>
                    <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                
                <?php if ($_SESSION['user_role'] == 'developer'): ?>
                    <li>
                        <a href="sekolah.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'sekolah.php' ? 'active' : ''; ?>">
                            <i class="bi bi-building"></i> Sekolah
                        </a>
                    </li>
                    <li>
                        <a href="kepala_sekolah.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'kepala_sekolah.php' ? 'active' : ''; ?>">
                            <i class="bi bi-person-badge"></i> Kepala Sekolah
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php if ($_SESSION['user_role'] == 'kepala_sekolah'): ?>
                    <li>
                        <a href="guru.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'guru.php' ? 'active' : ''; ?>">
                            <i class="bi bi-person-workspace"></i> Guru & Staf
                        </a>
                    </li>
                    <li>
                        <a href="siswa.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'siswa.php' ? 'active' : ''; ?>">
                            <i class="bi bi-people"></i> Siswa
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php if ($_SESSION['user_role'] == 'guru'): ?>
                    <li>
                        <a href="siswa.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'siswa.php' ? 'active' : ''; ?>">
                            <i class="bi bi-people"></i> Siswa
                        </a>
                    </li>
                    <li>
                        <a href="mata_pelajaran.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'mata_pelajaran.php' ? 'active' : ''; ?>">
                            <i class="bi bi-book"></i> Mata Pelajaran
                        </a>
                    </li>
                    <li>
                        <a href="soal.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'soal.php' ? 'active' : ''; ?>">
                            <i class="bi bi-file-earmark-text"></i> Soal & Quiz
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php if ($_SESSION['user_role'] == 'siswa'): ?>
                    <li>
                        <a href="soal_saya.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'soal_saya.php' ? 'active' : ''; ?>">
                            <i class="bi bi-file-earmark-text"></i> Soal Saya
                        </a>
                    </li>
                    <li>
                        <a href="hasil.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'hasil.php' ? 'active' : ''; ?>">
                            <i class="bi bi-clipboard-check"></i> Hasil Ujian
                        </a>
                    </li>
                    <li>
                        <a href="profil.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profil.php' ? 'active' : ''; ?>">
                            <i class="bi bi-person-circle"></i> Profil Saya
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        
        <!-- Page Content -->
        <div id="content" class="content">
            <!-- Top Navbar -->
            <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-light">
                        <i class="bi bi-list"></i>
                    </button>
                    
                    <div class="ms-auto d-flex align-items-center">
                        <div class="me-3">
                            <span class="text-muted">Selamat Datang, <strong><?php echo htmlspecialchars($user['nama_lengkap']); ?>!</strong></span>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($user['nama_lengkap']); ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="profil.php"><i class="bi bi-person"></i> Profil</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>
            
            <!-- Main Content -->
            <div class="container-fluid">

