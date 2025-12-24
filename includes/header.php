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
    <link rel="stylesheet" href="<?php echo getBasePath(); ?>assets/css/style.css">
</head>
<body class="<?php echo isset($body_class) ? $body_class : ''; ?>">
    <div class="wrapper">
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="<?php echo getBasePath(); ?>dashboard/<?php echo $user_role; ?>/index.php">
                    <img src="<?php echo getBasePath(); ?>assets/img/sekolahku.png" alt="Sekolahku" class="logo-image">
                </a>
            </div>

            <ul class="components">
                <?php if ($user_role == 'siswa'): ?>
                    <!-- Menu Siswa -->
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                        <a href="<?php echo getBasePath(); ?>dashboard/siswa/index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                            <i class="bi bi-house-door-fill"></i>
                            <span>Beranda</span>
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'jadwal.php' ? 'active' : ''; ?>">
                        <a href="<?php echo getBasePath(); ?>dashboard/siswa/jadwal.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'jadwal.php' ? 'active' : ''; ?>">
                            <i class="bi bi-calendar-week-fill"></i>
                            <span>Jadwal Pelajaran</span>
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'materi.php' || basename($_SERVER['PHP_SELF']) == 'detail_materi.php' || basename($_SERVER['PHP_SELF']) == 'kerjakan_latihan.php' ? 'active' : ''; ?>">
                        <a href="<?php echo getBasePath(); ?>dashboard/siswa/materi.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'materi.php' || basename($_SERVER['PHP_SELF']) == 'detail_materi.php' || basename($_SERVER['PHP_SELF']) == 'kerjakan_latihan.php' ? 'active' : ''; ?>">
                            <i class="bi bi-journal-text"></i>
                            <span>Materi</span>
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'informasi_akademik.php' ? 'active' : ''; ?>">
                        <a href="<?php echo getBasePath(); ?>dashboard/siswa/informasi_akademik.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'informasi_akademik.php' ? 'active' : ''; ?>">
                            <i class="bi bi-megaphone-fill"></i>
                            <span>Informasi Akademik</span>
                        </a>
                    </li>
                    <li>
                        <a href="#ujianSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                            <i class="bi bi-journal-text"></i>
                            <span>Ujian</span>
                        </a>
                        <ul class="collapse list-unstyled" id="ujianSubmenu">
                            <li><a href="<?php echo getBasePath(); ?>dashboard/siswa/soal_saya.php">Latihan</a></li>
                            <li><a href="<?php echo getBasePath(); ?>dashboard/siswa/uts.php">UTS</a></li>
                            <li><a href="<?php echo getBasePath(); ?>dashboard/siswa/uas.php">UAS</a></li>
                        </ul>
                    </li>
                    <li>
                        <a href="#hasilSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                            <i class="bi bi-clipboard-check-fill"></i>
                            <span>Hasil</span>
                        </a>
                        <ul class="collapse list-unstyled" id="hasilSubmenu">
                            <li><a href="<?php echo getBasePath(); ?>dashboard/siswa/presensi.php">Rekap Kehadiran</a></li>
                            <li><a href="<?php echo getBasePath(); ?>dashboard/siswa/hasil_latihan.php">Hasil Latihan</a></li>
                        </ul>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'profil.php' ? 'active' : ''; ?>">
                        <a href="<?php echo getBasePath(); ?>dashboard/siswa/profil.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profil.php' ? 'active' : ''; ?>">
                            <i class="bi bi-person-circle"></i>
                            <span>Profil</span>
                        </a>
                    </li>
                <?php elseif ($user_role == 'guru'): ?>
                    <!-- Menu Guru -->
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                        <a href="<?php echo getBasePath(); ?>dashboard/guru/index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                            <i class="bi bi-house-door-fill"></i>
                            <span>Beranda</span>
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'materi.php' || basename($_SERVER['PHP_SELF']) == 'tambah_materi.php' || basename($_SERVER['PHP_SELF']) == 'edit_materi.php' || basename($_SERVER['PHP_SELF']) == 'tambah_latihan.php' || basename($_SERVER['PHP_SELF']) == 'submisi_latihan.php' ? 'active' : ''; ?>">
                        <a href="<?php echo getBasePath(); ?>dashboard/guru/materi.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'materi.php' || basename($_SERVER['PHP_SELF']) == 'tambah_materi.php' || basename($_SERVER['PHP_SELF']) == 'edit_materi.php' || basename($_SERVER['PHP_SELF']) == 'tambah_latihan.php' || basename($_SERVER['PHP_SELF']) == 'submisi_latihan.php' ? 'active' : ''; ?>">
                            <i class="bi bi-journal-text"></i>
                            <span>Materi</span>
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'soal.php' || basename($_SERVER['PHP_SELF']) == 'tambah_soal.php' ? 'active' : ''; ?>">
                        <a href="<?php echo getBasePath(); ?>dashboard/guru/soal.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'soal.php' || basename($_SERVER['PHP_SELF']) == 'tambah_soal.php' ? 'active' : ''; ?>">
                            <i class="bi bi-file-earmark-text"></i>
                            <span>Soal</span>
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'mata_pelajaran.php' ? 'active' : ''; ?>">
                        <a href="<?php echo getBasePath(); ?>dashboard/guru/mata_pelajaran.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'mata_pelajaran.php' ? 'active' : ''; ?>">
                            <i class="bi bi-book"></i>
                            <span>Mata Pelajaran</span>
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'siswa.php' ? 'active' : ''; ?>">
                        <a href="<?php echo getBasePath(); ?>dashboard/guru/siswa.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'siswa.php' ? 'active' : ''; ?>">
                            <i class="bi bi-people"></i>
                            <span>Siswa</span>
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'informasi_akademik.php' || basename($_SERVER['PHP_SELF']) == 'tambah_informasi.php' ? 'active' : ''; ?>">
                        <a href="<?php echo getBasePath(); ?>dashboard/guru/informasi_akademik.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'informasi_akademik.php' || basename($_SERVER['PHP_SELF']) == 'tambah_informasi.php' ? 'active' : ''; ?>">
                            <i class="bi bi-megaphone-fill"></i>
                            <span>Informasi Akademik</span>
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'profil.php' ? 'active' : ''; ?>">
                        <a href="<?php echo getBasePath(); ?>dashboard/guru/profil.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profil.php' ? 'active' : ''; ?>">
                            <i class="bi bi-person-circle"></i>
                            <span>Profil</span>
                        </a>
                    </li>
                <?php elseif ($user_role == 'akademik'): ?>
                    <!-- Menu Akademik -->
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                        <a href="<?php echo getBasePath(); ?>dashboard/akademik/index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                            <i class="bi bi-house-door-fill"></i>
                            <span>Beranda</span>
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'jadwal.php' || basename($_SERVER['PHP_SELF']) == 'tambah_jadwal.php' || basename($_SERVER['PHP_SELF']) == 'edit_jadwal.php' ? 'active' : ''; ?>">
                        <a href="<?php echo getBasePath(); ?>dashboard/akademik/jadwal.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'jadwal.php' || basename($_SERVER['PHP_SELF']) == 'tambah_jadwal.php' || basename($_SERVER['PHP_SELF']) == 'edit_jadwal.php' ? 'active' : ''; ?>">
                            <i class="bi bi-calendar-week-fill"></i>
                            <span>Jadwal</span>
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'mata_pelajaran.php' || basename($_SERVER['PHP_SELF']) == 'tambah_mata_pelajaran.php' || basename($_SERVER['PHP_SELF']) == 'edit_mata_pelajaran.php' ? 'active' : ''; ?>">
                        <a href="<?php echo getBasePath(); ?>dashboard/akademik/mata_pelajaran.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'mata_pelajaran.php' || basename($_SERVER['PHP_SELF']) == 'tambah_mata_pelajaran.php' || basename($_SERVER['PHP_SELF']) == 'edit_mata_pelajaran.php' ? 'active' : ''; ?>">
                            <i class="bi bi-book"></i>
                            <span>Mata Pelajaran</span>
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'jadwal_ujian.php' ? 'active' : ''; ?>">
                        <a href="<?php echo getBasePath(); ?>dashboard/akademik/jadwal_ujian.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'jadwal_ujian.php' ? 'active' : ''; ?>">
                            <i class="bi bi-calendar-check-fill"></i>
                            <span>Jadwal Ujian</span>
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'slider.php' ? 'active' : ''; ?>">
                        <a href="<?php echo getBasePath(); ?>dashboard/akademik/slider.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'slider.php' ? 'active' : ''; ?>">
                            <i class="bi bi-images"></i>
                            <span>Slider</span>
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'profil.php' ? 'active' : ''; ?>">
                        <a href="<?php echo getBasePath(); ?>dashboard/akademik/profil.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profil.php' ? 'active' : ''; ?>">
                            <i class="bi bi-person-circle"></i>
                            <span>Profil</span>
                        </a>
                    </li>
                <?php elseif ($user_role == 'kepala_sekolah'): ?>
                    <!-- Menu Kepala Sekolah -->
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                        <a href="<?php echo getBasePath(); ?>dashboard/kepala_sekolah/index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                            <i class="bi bi-house-door-fill"></i>
                            <span>Beranda</span>
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'guru.php' || basename($_SERVER['PHP_SELF']) == 'tambah_guru.php' ? 'active' : ''; ?>">
                        <a href="<?php echo getBasePath(); ?>dashboard/kepala_sekolah/guru.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'guru.php' || basename($_SERVER['PHP_SELF']) == 'tambah_guru.php' ? 'active' : ''; ?>">
                            <i class="bi bi-person-badge"></i>
                            <span>Guru</span>
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'siswa.php' || basename($_SERVER['PHP_SELF']) == 'tambah_siswa.php' ? 'active' : ''; ?>">
                        <a href="<?php echo getBasePath(); ?>dashboard/kepala_sekolah/siswa.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'siswa.php' || basename($_SERVER['PHP_SELF']) == 'tambah_siswa.php' ? 'active' : ''; ?>">
                            <i class="bi bi-people"></i>
                            <span>Siswa</span>
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'kelas.php' || basename($_SERVER['PHP_SELF']) == 'tambah_kelas.php' || basename($_SERVER['PHP_SELF']) == 'edit_kelas.php' ? 'active' : ''; ?>">
                        <a href="<?php echo getBasePath(); ?>dashboard/kepala_sekolah/kelas.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'kelas.php' || basename($_SERVER['PHP_SELF']) == 'tambah_kelas.php' || basename($_SERVER['PHP_SELF']) == 'edit_kelas.php' ? 'active' : ''; ?>">
                            <i class="bi bi-building"></i>
                            <span>Kelas</span>
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'informasi_akademik.php' || basename($_SERVER['PHP_SELF']) == 'tambah_informasi.php' || basename($_SERVER['PHP_SELF']) == 'detail_informasi.php' ? 'active' : ''; ?>">
                        <a href="<?php echo getBasePath(); ?>dashboard/kepala_sekolah/informasi_akademik.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'informasi_akademik.php' || basename($_SERVER['PHP_SELF']) == 'tambah_informasi.php' || basename($_SERVER['PHP_SELF']) == 'detail_informasi.php' ? 'active' : ''; ?>">
                            <i class="bi bi-megaphone-fill"></i>
                            <span>Informasi Akademik</span>
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'profil.php' ? 'active' : ''; ?>">
                        <a href="<?php echo getBasePath(); ?>dashboard/kepala_sekolah/profil.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profil.php' ? 'active' : ''; ?>">
                            <i class="bi bi-person-circle"></i>
                            <span>Profil</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

        <!-- Main Content -->
        <div class="content" id="content">
            <!-- Top Navbar -->
            <header class="dashboard-top-header">
                <div class="header-left">
                    <!-- Sidebar toggle removed as per request -->
                </div>

                <div class="user-profile-section">
                    <div class="header-icons">
                        <a href="<?php echo getBasePath(); ?>dashboard/<?php echo $user_role; ?>/informasi_akademik.php" class="icon-btn">
                            <i class="bi bi-bell"></i>
                            <?php if ($unread_count > 0): ?>
                                <span class="badge"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </div>

                    <div class="dropdown">
                        <button class="user-profile-info dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php 
                            $foto_profil_path = getBasePath() . 'uploads/profil/' . ($user['foto_profil'] ?? '');
                            if (!empty($user['foto_profil']) && file_exists(dirname(__DIR__).'/uploads/profil/' . $user['foto_profil'])):
                            ?>
                                <img src="<?php echo $foto_profil_path; ?>" alt="Avatar" class="user-avatar">
                            <?php else: ?>
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($user['nama_lengkap'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div class="user-details d-none d-md-flex">
                                <span class="user-name"><?php echo htmlspecialchars($user['nama_lengkap']); ?></span>
                                <span class="user-role"><?php echo ucfirst($user_role); ?></span>
                            </div>
                            <i class="bi bi-chevron-down dropdown-arrow"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end user-profile-dropdown" aria-labelledby="userDropdown">
                            <li>
                                <a class="dropdown-item" href="<?php echo getBasePath(); ?>dashboard/<?php echo $user_role; ?>/profil.php">
                                    <i class="bi bi-person"></i> Profil Saya
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?php echo getBasePath(); ?>logout.php">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="container-fluid">