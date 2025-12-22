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

// Update last_activity untuk tracking user online
// Cek apakah kolom last_activity ada
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'last_activity'");
if ($check_column->num_rows == 0) {
    // Tambahkan kolom last_activity jika belum ada
    $conn->query("ALTER TABLE users ADD COLUMN last_activity DATETIME NULL AFTER updated_at");
}

// Update last_activity untuk user yang sedang login
$now = date('Y-m-d H:i:s');
$update_stmt = $conn->prepare("UPDATE users SET last_activity = ? WHERE id = ?");
$update_stmt->bind_param("si", $now, $user_id);
$update_stmt->execute();
$update_stmt->close();

// Get active users berdasarkan role (dalam 5 menit terakhir)
$active_users = [];
$total_online = 0;
$online_threshold = date('Y-m-d H:i:s', strtotime('-5 minutes'));

// Hanya tampilkan untuk role guru dan siswa
if (in_array($user_role, ['guru', 'siswa']) && $sekolah_id) {
    // Hitung total user online terlebih dahulu
    $query_count = "SELECT COUNT(*) as total 
                    FROM users 
                    WHERE role = ? 
                    AND sekolah_id = ? 
                    AND id != ? 
                    AND last_activity >= ?";
    
    $stmt_count = $conn->prepare($query_count);
    if ($stmt_count) {
        $stmt_count->bind_param("siis", $user_role, $sekolah_id, $user_id, $online_threshold);
        $stmt_count->execute();
        $result_count = $stmt_count->get_result();
        $total_online = $result_count->fetch_assoc()['total'];
        $stmt_count->close();
    }
    
    // Ambil 5 user pertama untuk ditampilkan
    if ($total_online > 0) {
        $query_active = "SELECT id, nama_lengkap, foto_profil 
                         FROM users 
                         WHERE role = ? 
                         AND sekolah_id = ? 
                         AND id != ? 
                         AND last_activity >= ? 
                         ORDER BY last_activity DESC 
                         LIMIT 5";
        
        $stmt_active = $conn->prepare($query_active);
        if ($stmt_active) {
            $stmt_active->bind_param("siis", $user_role, $sekolah_id, $user_id, $online_threshold);
            $stmt_active->execute();
            $result_active = $stmt_active->get_result();
            $active_users = $result_active->fetch_all(MYSQLI_ASSOC);
            $stmt_active->close();
        }
    }
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
    // Jika target_user_id tidak null, berarti pesan spesifik untuk user tersebut
    // Jika target_user_id null, filter berdasarkan target_role
    $query .= " AND ((ia.target_user_id IS NOT NULL AND ia.target_user_id = ?)";
    $params[] = $user_id;
    $types .= "i";
    
    $query .= " OR (ia.target_user_id IS NULL AND (ia.target_role = 'semua' OR ia.target_role = ?)))";
    $params[] = $user_role;
    $types .= "s";
    
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
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --light: #f8f9fa;
            --dark: #212529;
            --sidebar-width: 260px;
            --header-height: 70px;
        }
        
        body {
            background-color: #f3f4f6 !important;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            overflow-x: hidden;
        }
        
        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            background: #ffffff;
            box-shadow: 4px 0 24px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-header {
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .sidebar-header .logo-image {
            max-height: 40px;
            width: auto;
        }
        
        .sidebar ul.components {
            padding: 1rem;
            margin: 0;
        }
        
        .sidebar ul li {
            margin-bottom: 0.25rem;
        }
        
        .sidebar ul li a {
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            color: #64748b;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .sidebar ul li a:hover {
            color: var(--primary);
            background: rgba(67, 97, 238, 0.05);
        }
        
        .sidebar ul li a.active {
            color: #fff;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }
        
        .sidebar ul li a i {
            margin-right: 12px;
            font-size: 1.1rem;
        }
        
        /* Content */
        .content {
            margin-left: var(--sidebar-width);
            padding-top: var(--header-height);
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        /* Header */
        .dashboard-top-header {
            position: fixed;
            top: 0;
            right: 0;
            left: var(--sidebar-width);
            height: var(--header-height);
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            z-index: 999;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            box-shadow: 0 2px 15px rgba(0,0,0,0.03);
        }
        
        /* Search */
        .search-input {
            border: 1px solid #e2e8f0;
            border-radius: 50px;
            padding: 0.5rem 1rem 0.5rem 2.5rem;
            background: #f8fafc;
            width: 250px;
        }
        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        
        /* Profile */
        .user-profile-info {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            transition: background 0.2s;
        }
        .user-profile-info:hover {
            background: #f1f5f9;
        }
        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 10px;
            overflow: hidden;
        }
        .user-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: #1e293b;
            margin: 0;
        }
        .user-role {
            font-size: 0.75rem;
            color: #64748b;
            margin: 0;
        }
        
        /* Active Users */
        .sidebar-active-users {
            margin-top: auto;
            padding: 1.5rem;
            border-top: 1px solid #f1f5f9;
        }
        .active-users-title {
            font-size: 0.7rem;
            font-weight: 700;
            color: #94a3b8;
            letter-spacing: 0.5px;
            margin-bottom: 0.75rem;
            display: block;
        }
        .active-users-list {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        .active-user-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .active-user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .avatar-initials {
            width: 100%;
            height: 100%;
            background: #e2e8f0;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <img src="<?php echo getBasePath(); ?>assets/img/sekolahku.png" alt="Sekolahku" class="logo-image">
            </div>
            
            <ul class="list-unstyled components">
                <li>
                    <a href="<?php echo getBasePath(); ?>dashboard/<?php echo $_SESSION['user_role']; ?>/index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                        <i class="bi bi-house-door"></i> Beranda
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
                        <a href="<?php echo getBasePath(); ?>dashboard/kepala_sekolah/kelas.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'kelas.php' ? 'active' : ''; ?>">
                            <i class="bi bi-building"></i> Kelas
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
                            <i class="bi bi-people"></i> Siswa
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/guru/mulai_pelajaran.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'mulai_pelajaran.php' ? 'active' : ''; ?>">
                            <i class="bi bi-play-circle"></i> Jadwal Pelajaran
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/guru/history_presensi.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'history_presensi.php' ? 'active' : ''; ?>">
                            <i class="bi bi-clipboard-check"></i> Hasil Presensi
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/guru/soal.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'soal.php' ? 'active' : ''; ?>">
                            <i class="bi bi-file-earmark-text"></i> Soal
                        </a>
                    </li>
                    <li>
                        <a href="javascript:void(0);" class="dropdown-toggle <?php echo (basename($_SERVER['PHP_SELF']) == 'soal_ujian.php' || basename($_SERVER['PHP_SELF']) == 'tambah_soal_ujian.php') ? 'active' : ''; ?>" data-target="ujianGuruSubmenu">
                            <i class="bi bi-file-earmark-medical"></i> Ujian
                            <i class="bi bi-chevron-down ms-auto"></i>
                        </a>
                        <ul class="sidebar-submenu list-unstyled <?php echo (basename($_SERVER['PHP_SELF']) == 'soal_ujian.php' || basename($_SERVER['PHP_SELF']) == 'tambah_soal_ujian.php') ? 'show' : ''; ?>" id="ujianGuruSubmenu">
                            <li>
                                <a href="<?php echo getBasePath(); ?>dashboard/guru/soal_ujian.php?tipe=uts" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'soal_ujian.php' && isset($_GET['tipe']) && $_GET['tipe'] == 'uts') || (basename($_SERVER['PHP_SELF']) == 'tambah_soal_ujian.php' && isset($_GET['tipe']) && $_GET['tipe'] == 'uts') ? 'active' : ''; ?>">
                                    <i class="bi bi-file-earmark-medical"></i> UTS
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo getBasePath(); ?>dashboard/guru/soal_ujian.php?tipe=uas" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'soal_ujian.php' && isset($_GET['tipe']) && $_GET['tipe'] == 'uas') || (basename($_SERVER['PHP_SELF']) == 'tambah_soal_ujian.php' && isset($_GET['tipe']) && $_GET['tipe'] == 'uas') ? 'active' : ''; ?>">
                                    <i class="bi bi-file-earmark-medical-fill"></i> UAS
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/guru/informasi_akademik.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'informasi_akademik.php' || basename($_SERVER['PHP_SELF']) == 'detail_informasi.php' ? 'active' : ''; ?>">
                            <i class="bi bi-megaphone"></i> Informasi Akademik
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php if ($_SESSION['user_role'] == 'akademik'): ?>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/akademik/mata_pelajaran.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'mata_pelajaran.php' ? 'active' : ''; ?>">
                            <i class="bi bi-book"></i> Mata Pelajaran
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/akademik/jadwal.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'jadwal.php' ? 'active' : ''; ?>">
                            <i class="bi bi-calendar-week"></i> Jadwal Pelajaran
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/akademik/jadwal_ujian.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'jadwal_ujian.php' || basename($_SERVER['PHP_SELF']) == 'soal_ujian.php' ? 'active' : ''; ?>">
                            <i class="bi bi-calendar-event"></i> Jadwal Ujian
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/akademik/slider.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'slider.php' ? 'active' : ''; ?>">
                            <i class="bi bi-images"></i> Slider Dashboard
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php if ($_SESSION['user_role'] == 'developer'): ?>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/akademik/slider.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'slider.php' ? 'active' : ''; ?>">
                            <i class="bi bi-images"></i> Slider Dashboard
                        </a>
                    </li>
                <?php endif; ?>  
                
                <?php if ($_SESSION['user_role'] == 'siswa'): ?>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/siswa/jadwal.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'jadwal.php' ? 'active' : ''; ?>">
                            <i class="bi bi-calendar-week"></i> Jadwal Pelajaran
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/siswa/informasi_akademik.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'informasi_akademik.php' || basename($_SERVER['PHP_SELF']) == 'detail_informasi.php' ? 'active' : ''; ?>">
                            <i class="bi bi-megaphone"></i> Informasi Akademik
                        </a>
                    </li>
                    <li>
                        <a href="javascript:void(0);" class="dropdown-toggle <?php echo (basename($_SERVER['PHP_SELF']) == 'soal_saya.php' || basename($_SERVER['PHP_SELF']) == 'uts.php' || basename($_SERVER['PHP_SELF']) == 'uas.php') ? 'active' : ''; ?>" data-target="ujianSubmenu">
                            <i class="bi bi-file-earmark-text"></i> Ujian
                            <i class="bi bi-chevron-down ms-auto"></i>
                        </a>
                        <ul class="sidebar-submenu list-unstyled <?php echo (basename($_SERVER['PHP_SELF']) == 'soal_saya.php' || basename($_SERVER['PHP_SELF']) == 'uts.php' || basename($_SERVER['PHP_SELF']) == 'uas.php') ? 'show' : ''; ?>" id="ujianSubmenu">
                            <li>
                                <a href="<?php echo getBasePath(); ?>dashboard/siswa/soal_saya.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'soal_saya.php' ? 'active' : ''; ?>">
                                    <i class="bi bi-pencil-square"></i> Latihan
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo getBasePath(); ?>dashboard/siswa/uts.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'uts.php' ? 'active' : ''; ?>">
                                    <i class="bi bi-file-earmark-medical"></i> UTS
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo getBasePath(); ?>dashboard/siswa/uas.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'uas.php' ? 'active' : ''; ?>">
                                    <i class="bi bi-file-earmark-medical-fill"></i> UAS
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <a href="javascript:void(0);" class="dropdown-toggle <?php echo (basename($_SERVER['PHP_SELF']) == 'hasil_latihan.php' || basename($_SERVER['PHP_SELF']) == 'presensi.php') ? 'active' : ''; ?>" data-target="hasilSubmenu">
                            <i class="bi bi-check-circle"></i> Hasil
                            <i class="bi bi-chevron-down ms-auto"></i>
                        </a>
                        <ul class="sidebar-submenu list-unstyled <?php echo (basename($_SERVER['PHP_SELF']) == 'hasil_latihan.php' || basename($_SERVER['PHP_SELF']) == 'presensi.php') ? 'show' : ''; ?>" id="hasilSubmenu">
                            <li>
                                <a href="<?php echo getBasePath(); ?>dashboard/siswa/presensi.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'presensi.php' ? 'active' : ''; ?>">
                                    <i class="bi bi-clipboard-check"></i> Rekap Kehadiran
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo getBasePath(); ?>dashboard/siswa/hasil_latihan.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'hasil_latihan.php' ? 'active' : ''; ?>">
                                    <i class="bi bi-file-earmark-check"></i> Hasil Latihan
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <a href="<?php echo getBasePath(); ?>dashboard/siswa/profil.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profil.php' ? 'active' : ''; ?>">
                            <i class="bi bi-person-circle"></i> Profil
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            
            <!-- Active Users Section (hanya untuk guru dan siswa) -->
            <?php if (in_array($user_role, ['guru', 'siswa'])): ?>
                <div class="sidebar-active-users">
                    <div class="active-users-header">
                        <span class="active-users-title">ACTIVE <?php echo strtoupper($user_role == 'guru' ? 'TEACHERS' : 'STUDENTS'); ?></span>
                    </div>
                    <?php if ($total_online > 0 && !empty($active_users)): ?>
                        <div class="active-users-list">
                            <?php foreach ($active_users as $active_user): 
                                $foto_profil = '';
                                if (!empty($active_user['foto_profil']) && file_exists(__DIR__ . '/../uploads/profil/' . $active_user['foto_profil'])) {
                                    $foto_profil = getBasePath() . 'uploads/profil/' . $active_user['foto_profil'];
                                }
                                $initials = strtoupper(substr($active_user['nama_lengkap'], 0, 1));
                            ?>
                                <div class="active-user-avatar" title="<?php echo htmlspecialchars($active_user['nama_lengkap']); ?>">
                                    <?php if ($foto_profil): ?>
                                        <img src="<?php echo htmlspecialchars($foto_profil); ?>" alt="<?php echo htmlspecialchars($active_user['nama_lengkap']); ?>">
                                    <?php else: ?>
                                        <span class="avatar-initials"><?php echo $initials; ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if (isset($total_online) && $total_online > 5): ?>
                                <div class="active-user-avatar active-user-more" title="<?php echo ($total_online - 5); ?> more">
                                    <span class="avatar-more">+<?php echo $total_online - 5; ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="active-users-empty">
                            <span class="empty-message">Tidak ada <?php echo $user_role == 'guru' ? 'guru' : 'siswa'; ?> online</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </nav>
        
        <!-- Page Content -->
        <div id="content" class="content">
            <!-- Top Header -->
            <div class="dashboard-top-header">
                <div class="search-section">
                    <div class="search-wrapper">
                        <i class="bi bi-search search-icon"></i>
                        <input type="text" class="search-input" placeholder="Cari sesuatu...">
                    </div>
                </div>
                <div class="user-profile-section">
                    <div class="header-icons">
                        <a href="#" class="icon-btn" title="Notifikasi">
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
                    <div class="dropdown">
                        <div class="user-profile-info dropdown-toggle" id="userProfileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-avatar">
                                <?php 
                                $foto_profil = '';
                                if (!empty($user['foto_profil']) && file_exists(__DIR__ . '/../uploads/profil/' . $user['foto_profil'])) {
                                    $foto_profil = getBasePath() . 'uploads/profil/' . $user['foto_profil'];
                                }
                                if ($foto_profil): ?>
                                    <img src="<?php echo htmlspecialchars($foto_profil); ?>" alt="Foto Profil" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($user['nama_lengkap'], 0, 1)); ?>
                                <?php endif; ?>
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
                            <i class="bi bi-chevron-down dropdown-arrow"></i>
                        </div>
                        <ul class="dropdown-menu user-profile-dropdown dropdown-menu-end" aria-labelledby="userProfileDropdown">
                            <li>
                                <a class="dropdown-item" href="<?php echo getBasePath(); ?>dashboard/<?php echo $user_role; ?>/profil.php">
                                    <i class="bi bi-person-circle"></i> Profil
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="https://sekolahku.rnara.my.id/logout.php">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="container">
