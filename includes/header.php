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
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo getBasePath(); ?>assets/css/style.css">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <h4><i class="bi bi-mortarboard-fill"></i> <?php echo isset($user['sekolah_id']) && $user['sekolah_id'] ? 'Sekolah' : 'Portal'; ?> Academy</h4>
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
                        <a href="mulai_pelajaran.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'mulai_pelajaran.php' ? 'active' : ''; ?>">
                            <i class="bi bi-play-circle"></i> Mulai Pelajaran
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
                        <a href="presensi.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'presensi.php' ? 'active' : ''; ?>">
                            <i class="bi bi-clipboard-check"></i> Presensi
                        </a>
                    </li>
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
            <!-- Top Header -->
            <div class="dashboard-top-header">
                <div class="logo-section">
                    <div class="logo-icon">IA</div>
                    <h4 class="logo-text">Portal Sekolah Academy</h4>
                </div>
                <div class="search-section">
                    <div class="search-wrapper">
                        <i class="bi bi-search search-icon"></i>
                        <input type="text" class="search-input" placeholder="What do you want to find?">
                    </div>
                </div>
                <div class="user-profile-section">
                    <div class="header-icons">
                        <a href="#" class="icon-btn" title="Notifications">
                            <i class="bi bi-bell"></i>
                            <span class="badge">3</span>
                        </a>
                        <a href="#" class="icon-btn" title="Messages">
                            <i class="bi bi-chat-dots"></i>
                            <span class="badge">2</span>
                        </a>
                    </div>
                    <div class="user-profile-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user['nama_lengkap'], 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <p class="user-name"><?php echo htmlspecialchars($user['nama_lengkap']); ?></p>
                            <p class="user-role"><?php 
                                $role_labels = [
                                    'developer' => 'Developer',
                                    'kepala_sekolah' => 'Kepala Sekolah',
                                    'guru' => 'Guru',
                                    'siswa' => 'Siswa'
                                ];
                                echo $role_labels[$_SESSION['user_role']] ?? ucfirst($_SESSION['user_role']);
                            ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="container-fluid">

