<?php
require_once '../../config/session.php';
require_once '../../config/database.php';

// Pastikan hanya siswa yang bisa akses
if ($_SESSION['role'] !== 'siswa') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$nama_siswa = $_SESSION['nama'];

// Ambil data statistik sederhana (Contoh: Jumlah presensi, Jadwal hari ini)
// Anda bisa menyesuaikan query ini dengan struktur database asli Anda
$stmt_presensi = $db->prepare("SELECT COUNT(*) as total FROM presensi WHERE user_id = ? AND status = 'Hadir'");
$stmt_presensi->execute([$user_id]);
$total_hadir = $stmt_presensi->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt_jadwal = $db->prepare("SELECT COUNT(*) as total FROM jadwal j JOIN kelas k ON j.kelas_id = k.id JOIN siswa s ON s.kelas_id = k.id WHERE s.user_id = ?");
$stmt_jadwal->execute([$user_id]);
$total_jadwal = $stmt_jadwal->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa | Sekolahku</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --secondary-color: #f8fafc;
            --sidebar-width: 260px;
            --text-dark: #1e293b;
            --text-muted: #64748b;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f1f5f9;
            color: var(--text-dark);
            overflow-x: hidden;
        }

        /* Sidebar Styling */
        #sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: #ffffff;
            border-right: 1px solid #e2e8f0;
            transition: all 0.3s;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            display: flex;
            align-items: center;
        }

        .sidebar-header img {
            height: 40px;
            margin-right: 10px;
        }

        .nav-link {
            padding: 0.8rem 1.5rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            font-weight: 500;
            transition: all 0.2s;
            border-radius: 0 50px 50px 0;
            margin-right: 1rem;
        }

        .nav-link:hover {
            color: var(--primary-color);
            background: #f5f3ff;
        }

        .nav-link.active {
            color: #ffffff;
            background: var(--primary-color);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .nav-link i {
            width: 20px;
            margin-right: 12px;
            font-size: 1.1rem;
        }

        /* Main Content */
        #content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            padding: 2rem;
            transition: all 0.3s;
        }

        /* Top Navbar */
        .top-navbar {
            background: transparent;
            padding-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .search-bar {
            background: #ffffff;
            border-radius: 12px;
            padding: 0.5rem 1rem;
            display: flex;
            align-items: center;
            width: 350px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }

        .search-bar input {
            border: none;
            outline: none;
            width: 100%;
            margin-left: 10px;
            font-size: 0.9rem;
        }

        /* Welcome Card */
        .welcome-card {
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            border-radius: 24px;
            padding: 2.5rem;
            color: white;
            position: relative;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .welcome-card h1 {
            font-weight: 700;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .welcome-card p {
            opacity: 0.9;
            max-width: 500px;
        }

        .welcome-img {
            position: absolute;
            right: 2rem;
            bottom: 0;
            height: 180px;
            opacity: 0.8;
        }

        /* Stat Cards */
        .stat-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 1.5rem;
            border: none;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .icon-box {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }

        /* Colors for Icons */
        .bg-purple { background: #f5f3ff; color: #7c3aed; }
        .bg-blue { background: #eff6ff; color: #2563eb; }
        .bg-orange { background: #fff7ed; color: #ea580c; }
        .bg-green { background: #f0fdf4; color: #16a34a; }

        /* Custom Table */
        .card-table {
            background: #ffffff;
            border-radius: 20px;
            padding: 1.5rem;
            border: none;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .table thead th {
            background: #f8fafc;
            border: none;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            padding: 1rem;
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }

        /* Mobile Responsive */
        @media (max-width: 992px) {
            #sidebar {
                left: -var(--sidebar-width);
            }
            #content {
                margin-left: 0;
            }
            #sidebar.active {
                left: 0;
            }
            .search-bar {
                width: 200px;
            }
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <nav id="sidebar">
        <div class="sidebar-header">
            <img src="../../assets/img/sekolahku.png" alt="Logo" onerror="this.src='https://ui-avatars.com/api/?name=SK&background=6366f1&color=fff'">
            <span class="fw-bold fs-5" style="color: var(--primary-color)">Sekolahku</span>
        </div>

        <div class="nav flex-column mt-2">
            <a href="index.php" class="nav-link active">
                <i class="fa-solid fa-house"></i> Dashboard
            </a>
            <a href="informasi_akademik.php" class="nav-link">
                <i class="fa-solid fa-bullhorn"></i> Informasi
            </a>
            <a href="jadwal.php" class="nav-link">
                <i class="fa-solid fa-calendar-days"></i> Jadwal Pelajaran
            </a>
            <a href="presensi.php" class="nav-link">
                <i class="fa-solid fa-user-check"></i> Presensi
            </a>
            <a href="soal_saya.php" class="nav-link">
                <i class="fa-solid fa-book-open"></i> Materi & Tugas
            </a>
            <a href="hasil.php" class="nav-link">
                <i class="fa-solid fa-chart-line"></i> Nilai Saya
            </a>
            <a href="profil.php" class="nav-link">
                <i class="fa-solid fa-user-gear"></i> Pengaturan Profil
            </a>
            
            <div class="mt-auto p-3">
                <a href="../../logout.php" class="nav-link text-danger">
                    <i class="fa-solid fa-right-from-bracket"></i> Keluar
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div id="content">
        <!-- Top Navbar -->
        <header class="top-navbar">
            <div class="d-flex align-items-center">
                <button class="btn btn-light d-lg-none me-3" id="sidebarToggle">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="search-bar">
                    <i class="fa-solid fa-magnifying-glass text-muted"></i>
                    <input type="text" placeholder="Cari materi atau tugas...">
                </div>
            </div>
            
            <div class="d-flex align-items-center">
                <div class="dropdown me-3">
                    <button class="btn btn-light position-relative rounded-pill" type="button">
                        <i class="fa-solid fa-bell text-muted"></i>
                        <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle"></span>
                    </button>
                </div>
                <div class="d-flex align-items-center">
                    <div class="text-end me-3 d-none d-sm-block">
                        <div class="fw-bold small"><?php echo htmlspecialchars($nama_siswa); ?></div>
                        <div class="text-muted" style="font-size: 0.75rem;">Siswa Aktif</div>
                    </div>
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($nama_siswa); ?>&background=6366f1&color=fff" class="rounded-circle" width="45" height="45">
                </div>
            </div>
        </header>

        <!-- Welcome Banner -->
        <div class="welcome-card">
            <div class="z-1 position-relative">
                <h1>Halo, <?php echo explode(' ', $nama_siswa)[0]; ?>! 👋</h1>
                <p>Selamat datang kembali di dashboard Sekolahku. Cek jadwal pelajaranmu hari ini dan pastikan semua tugas sudah dikerjakan tepat waktu.</p>
                <a href="jadwal.php" class="btn btn-light text-primary fw-bold px-4 py-2 mt-2 rounded-pill shadow-sm">
                    Lihat Jadwal Hari Ini
                </a>
            </div>
            <!-- Gunakan emoji atau SVG sebagai pengganti gambar ilustrasi jika tidak ada -->
            <div class="welcome-img d-none d-md-block">
                <i class="fa-solid fa-user-graduate" style="font-size: 150px; color: rgba(255,255,255,0.2);"></i>
            </div>
        </div>

        <!-- Statistics Row -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon-box bg-purple">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <div class="text-muted small fw-medium">Total Kehadiran</div>
                    <div class="fs-4 fw-bold"><?php echo $total_hadir; ?> Hari</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon-box bg-blue">
                        <i class="fa-solid fa-book"></i>
                    </div>
                    <div class="text-muted small fw-medium">Mata Pelajaran</div>
                    <div class="fs-4 fw-bold"><?php echo $total_jadwal; ?> Mapel</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon-box bg-orange">
                        <i class="fa-solid fa-clipboard-list"></i>
                    </div>
                    <div class="text-muted small fw-medium">Tugas Pending</div>
                    <div class="fs-4 fw-bold">3 Tugas</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon-box bg-green">
                        <i class="fa-solid fa-star"></i>
                    </div>
                    <div class="text-muted small fw-medium">Rata-rata Nilai</div>
                    <div class="fs-4 fw-bold">85.5</div>
                </div>
            </div>
        </div>

        <!-- Content Row -->
        <div class="row g-4">
            <!-- Left Column: Recent Information -->
            <div class="col-lg-8">
                <div class="card-table">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0">Informasi Terbaru</h5>
                        <a href="informasi_akademik.php" class="text-primary text-decoration-none small fw-bold">Lihat Semua</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Judul Informasi</th>
                                    <th>Kategori</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Ambil 5 informasi terbaru
                                $stmt_info = $db->query("SELECT * FROM informasi_akademik ORDER BY created_at DESC LIMIT 5");
                                while ($info = $stmt_info->fetch(PDO::FETCH_ASSOC)) :
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-truncate" style="max-width: 250px;">
                                            <?php echo htmlspecialchars($info['judul']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-primary px-3 py-2 rounded-pill">
                                            <?php echo htmlspecialchars($info['kategori'] ?? 'Akademik'); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small">
                                        <?php echo date('d M Y', strtotime($info['created_at'])); ?>
                                    </td>
                                    <td>
                                        <a href="detail_informasi.php?id=<?php echo $info['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">Detail</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Column: Quick Calendar / Upcoming -->
            <div class="col-lg-4">
                <div class="card-table mb-4" style="background: var(--primary-color); color: white;">
                    <h5 class="fw-bold mb-3">Presensi Cepat</h5>
                    <p class="small opacity-75">Gunakan kode dari gurumu untuk melakukan presensi kehadiran hari ini.</p>
                    <a href="presensi.php" class="btn btn-light w-100 fw-bold py-2 rounded-pill">Masuk ke Menu Presensi</a>
                </div>

                <div class="card-table">
                    <h5 class="fw-bold mb-4">Agenda Hari Ini</h5>
                    <div class="d-flex mb-3">
                        <div class="bg-light text-center rounded p-2 me-3" style="min-width: 60px;">
                            <div class="small text-muted">SEN</div>
                            <div class="fw-bold">22</div>
                        </div>
                        <div>
                            <div class="fw-bold">Matematika</div>
                            <div class="small text-muted">08:00 - 09:30 • Ruang 10</div>
                        </div>
                    </div>
                    <div class="d-flex mb-3">
                        <div class="bg-light text-center rounded p-2 me-3" style="min-width: 60px;">
                            <div class="small text-muted">SEN</div>
                            <div class="fw-bold">22</div>
                        </div>
                        <div>
                            <div class="fw-bold">Bahasa Inggris</div>
                            <div class="small text-muted">10:00 - 11:30 • Ruang Lab</div>
                        </div>
                    </div>
                    <hr>
                    <button class="btn btn-outline-secondary btn-sm w-100 rounded-pill">Lihat Kalender Akademik</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle Sidebar for Mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>