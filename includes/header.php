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

// Get sekolah_id if not set
if (!$sekolah_id && isset($user['sekolah_id'])) {
    $sekolah_id = $user['sekolah_id'];
}

// Get count of unread informasi akademik
$unread_count = 0;
$table_check = $conn->query("SHOW TABLES LIKE 'informasi_akademik'");
if ($table_check && $table_check->num_rows > 0) {
    $query = "SELECT COUNT(*) as count
        FROM informasi_akademik ia
        WHERE ia.status = 'terkirim'
        AND ia.id NOT IN (SELECT informasi_id FROM informasi_akademik_baca WHERE user_id = ?)";
    
    $params = [$user_id];
    $types = "i";
    
    // Filter berdasarkan sekolah jika ada
    if ($sekolah_id) {
        $query .= " AND (ia.sekolah_id = ? OR ia.sekolah_id IS NULL)";
        $params[] = $sekolah_id;
        $types .= "i";
    }
    
    // Filter berdasarkan target role
    $query .= " AND (ia.target_role = 'semua' OR ia.target_role = ?";
    $params[] = $user_role;
    $types .= "s";
    
    // Include pesan yang ditujukan khusus untuk user ini (target_user_id tidak null)
    $query .= " OR (ia.target_user_id = ? AND ia.target_user_id IS NOT NULL))";
    $params[] = $user_id;
    $types .= "i";
    
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $unread_count = $result['count'] ?? 0;
        $stmt->close();
    }
}
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
                <h4><i class="bi bi-mortarboard-fill"></i> <?php echo isset($user['sekolah_id']) && $user['sekolah_id'] ? 'Sekolah' : 'Portal'; ?>ku</h4>
            </div>
            
            <ul class="list-unstyled components">
                <li>
                    <a href="<?php echo getBasePath(); ?>dashboard/<?php echo $_SESSION['user_role']; ?>/index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                        <i class="bi bi-house-door"></i> Home
                    </a>
                </li>
                
                <?php if ($_SESSION['user_role'] == 'developer'): ?>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/developer/sekolah.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'sekolah.php' ? 'active' : ''; ?>">
                            <i class="bi bi-building"></i> Sekolah
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/developer/kepala_sekolah.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'kepala_sekolah.php' ? 'active' : ''; ?>">
                            <i class="bi bi-person-badge"></i> Kepala Sekolah
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php if ($_SESSION['user_role'] == 'kepala_sekolah'): ?>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/kepala_sekolah/guru.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'guru.php' ? 'active' : ''; ?>">
                            <i class="bi bi-person-workspace"></i> Guru & Staf
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/kepala_sekolah/siswa.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'siswa.php' ? 'active' : ''; ?>">
                            <i class="bi bi-people"></i> Siswa
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/kepala_sekolah/informasi_akademik.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'informasi_akademik.php' || basename($_SERVER['PHP_SELF']) == 'detail_informasi.php' ? 'active' : ''; ?>">
                            <i class="bi bi-megaphone"></i> Informasi Akademik
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php if ($_SESSION['user_role'] == 'guru'): ?>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/guru/siswa.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'siswa.php' ? 'active' : ''; ?>">
                            <i class="bi bi-people"></i> Students
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/guru/mata_pelajaran.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'mata_pelajaran.php' ? 'active' : ''; ?>">
                            <i class="bi bi-book"></i> Subject
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/guru/mulai_pelajaran.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'mulai_pelajaran.php' ? 'active' : ''; ?>">
                            <i class="bi bi-play-circle"></i> Routine
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/guru/soal.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'soal.php' ? 'active' : ''; ?>">
                            <i class="bi bi-file-earmark-text"></i> Exam
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/guru/informasi_akademik.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'informasi_akademik.php' || basename($_SERVER['PHP_SELF']) == 'detail_informasi.php' ? 'active' : ''; ?>">
                            <i class="bi bi-megaphone"></i> Informasi Akademik
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php if ($_SESSION['user_role'] == 'siswa'): ?>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/siswa/presensi.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'presensi.php' ? 'active' : ''; ?>">
                            <i class="bi bi-clipboard-check"></i> Attendance
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/siswa/soal_saya.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'soal_saya.php' ? 'active' : ''; ?>">
                            <i class="bi bi-file-earmark-text"></i> Exam
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/siswa/hasil.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'hasil.php' ? 'active' : ''; ?>">
                            <i class="bi bi-clipboard-check"></i> Notice
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/siswa/informasi_akademik.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'informasi_akademik.php' || basename($_SERVER['PHP_SELF']) == 'detail_informasi.php' ? 'active' : ''; ?>">
                            <i class="bi bi-megaphone"></i> Informasi Akademik
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/siswa/profil.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profil.php' ? 'active' : ''; ?>">
                            <i class="bi bi-person-circle"></i> Account
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            
            <!-- Logout Button -->
            <div class="sidebar-footer">
                <a href="https://sekolahku.rnara.my.id/logout.php" class="sidebar-logout-btn">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </nav>
        
        <!-- Page Content -->
        <div id="content" class="content">
            <!-- Top Header -->
            <div class="dashboard-top-header">
                <div class="logo-section"> 
                    <!-- <div class="logo-icon">IA</div> -->
                    <h4 class="logo-text me-5"></h4>
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
                        <a href="<?php echo getBasePath(); ?>dashboard/<?php echo $user_role; ?>/informasi_akademik.php" class="icon-btn" title="Informasi Akademik">
                            <i class="bi bi-chat-dots"></i>
                            <?php if ($unread_count > 0): ?>
                                <span class="badge"><?php echo $unread_count > 99 ? '99+' : $unread_count; ?></span>
                            <?php endif; ?>
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

