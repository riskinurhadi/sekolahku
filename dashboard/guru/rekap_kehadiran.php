<?php
$page_title = 'Rekap Kehadiran';
require_once '../../config/session.php';
require_once '../../config/database.php';
requireRole(['guru']);

$conn = getConnection();
$guru_id = $_SESSION['user_id'];
$sekolah_id = $_SESSION['sekolah_id'];

// Get filter parameters
$filter_periode = isset($_GET['periode']) ? $_GET['periode'] : 'bulan'; // minggu, bulan, semester
$filter_mata_pelajaran = isset($_GET['mata_pelajaran_id']) ? intval($_GET['mata_pelajaran_id']) : 0;
$filter_tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

// Calculate date range based on periode
$date_start = date('Y-m-d');
$date_end = date('Y-m-d');
$periode_label = '';

if ($filter_periode == 'minggu') {
    // Get start and end of current week
    $date_start = date('Y-m-d', strtotime('monday this week', strtotime($filter_tanggal)));
    $date_end = date('Y-m-d', strtotime('sunday this week', strtotime($filter_tanggal)));
    $periode_label = date('d/m/Y', strtotime($date_start)) . ' - ' . date('d/m/Y', strtotime($date_end));
} elseif ($filter_periode == 'bulan') {
    // Get start and end of current month
    $date_start = date('Y-m-01', strtotime($filter_tanggal));
    $date_end = date('Y-m-t', strtotime($filter_tanggal));
    $periode_label = date('F Y', strtotime($filter_tanggal));
} elseif ($filter_periode == 'semester') {
    // Semester 1: July-December, Semester 2: January-June
    $month = date('n', strtotime($filter_tanggal));
    $year = date('Y', strtotime($filter_tanggal));
    
    if ($month >= 7 && $month <= 12) {
        // Semester 1
        $date_start = $year . '-07-01';
        $date_end = $year . '-12-31';
        $periode_label = 'Semester 1 ' . $year;
    } else {
        // Semester 2
        $date_start = $year . '-01-01';
        $date_end = $year . '-06-30';
        $periode_label = 'Semester 2 ' . $year;
    }
}

// Get all mata pelajaran for filter
$stmt = $conn->prepare("SELECT id, nama_pelajaran FROM mata_pelajaran WHERE guru_id = ? ORDER BY nama_pelajaran ASC");
$stmt->bind_param("i", $guru_id);
$stmt->execute();
$mata_pelajaran_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Build query untuk get semua sesi pelajaran dalam periode
$query_sesi = "SELECT sp.*, mp.nama_pelajaran, mp.kode_pelajaran, mp.id as mata_pelajaran_id
    FROM sesi_pelajaran sp
    JOIN mata_pelajaran mp ON sp.mata_pelajaran_id = mp.id
    WHERE sp.guru_id = ? 
    AND DATE(sp.waktu_mulai) BETWEEN ? AND ?
    AND (sp.status = 'selesai' OR sp.status = 'aktif')";

$params = [$guru_id, $date_start, $date_end];
$types = "iss";

if ($filter_mata_pelajaran > 0) {
    $query_sesi .= " AND sp.mata_pelajaran_id = ?";
    $params[] = $filter_mata_pelajaran;
    $types .= "i";
}

$query_sesi .= " ORDER BY sp.waktu_mulai ASC";

$stmt = $conn->prepare($query_sesi);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$sesi_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get all students who have presensi or should have presensi
$siswa_rekap = [];

// Get students from presensi records in the period
$query_siswa = "SELECT DISTINCT u.id, u.nama_lengkap, u.username, k.nama_kelas, k.tingkat
    FROM presensi p
    JOIN users u ON p.siswa_id = u.id
    LEFT JOIN kelas k ON u.kelas_id = k.id
    JOIN sesi_pelajaran sp ON p.sesi_pelajaran_id = sp.id
    WHERE sp.guru_id = ? 
    AND DATE(sp.waktu_mulai) BETWEEN ? AND ?";
$params_siswa = [$guru_id, $date_start, $date_end];
$types_siswa = "iss";

if ($filter_mata_pelajaran > 0) {
    $query_siswa .= " AND sp.mata_pelajaran_id = ?";
    $params_siswa[] = $filter_mata_pelajaran;
    $types_siswa .= "i";
}

$query_siswa .= " ORDER BY k.tingkat ASC, k.nama_kelas ASC, u.nama_lengkap ASC";

$stmt = $conn->prepare($query_siswa);
$stmt->bind_param($types_siswa, ...$params_siswa);
$stmt->execute();
$siswa_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Build rekap data
foreach ($siswa_data as $siswa) {
    $siswa_id = $siswa['id'];
    
    // Get presensi stats for this student
    $query_presensi = "SELECT 
        COUNT(CASE WHEN p.status = 'hadir' THEN 1 END) as total_hadir,
        COUNT(CASE WHEN p.status = 'terlambat' THEN 1 END) as total_terlambat,
        COUNT(CASE WHEN p.status = 'tidak_hadir' THEN 1 END) as total_tidak_hadir,
        COUNT(*) as total_presensi
    FROM presensi p
    JOIN sesi_pelajaran sp ON p.sesi_pelajaran_id = sp.id
    WHERE p.siswa_id = ? 
    AND sp.guru_id = ? 
    AND DATE(sp.waktu_mulai) BETWEEN ? AND ?";
    
    $params_presensi = [$siswa_id, $guru_id, $date_start, $date_end];
    $types_presensi = "iiss";
    
    if ($filter_mata_pelajaran > 0) {
        $query_presensi .= " AND sp.mata_pelajaran_id = ?";
        $params_presensi[] = $filter_mata_pelajaran;
        $types_presensi .= "i";
    }
    
    $stmt = $conn->prepare($query_presensi);
    $stmt->bind_param($types_presensi, ...$params_presensi);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $total_sesi = count($sesi_list);
    $persentase_hadir = $total_sesi > 0 ? round(($stats['total_hadir'] / $total_sesi) * 100, 1) : 0;
    
    $siswa_rekap[] = [
        'id' => $siswa_id,
        'nama_lengkap' => $siswa['nama_lengkap'],
        'username' => $siswa['username'],
        'nama_kelas' => $siswa['nama_kelas'],
        'tingkat' => $siswa['tingkat'],
        'total_hadir' => $stats['total_hadir'] ?? 0,
        'total_terlambat' => $stats['total_terlambat'] ?? 0,
        'total_tidak_hadir' => $stats['total_tidak_hadir'] ?? 0,
        'total_presensi' => $stats['total_presensi'] ?? 0,
        'total_sesi' => $total_sesi,
        'persentase_hadir' => $persentase_hadir
    ];
}

// Get mata pelajaran name for display
$mata_pelajaran_name = 'Semua Mata Pelajaran';
if ($filter_mata_pelajaran > 0) {
    foreach ($mata_pelajaran_list as $mp) {
        if ($mp['id'] == $filter_mata_pelajaran) {
            $mata_pelajaran_name = $mp['nama_pelajaran'];
            break;
        }
    }
}

require_once '../../includes/header.php';
?>

<style>
.page-header {
    margin-bottom: 32px;
}

.page-header h2 {
    font-size: 32px;
    font-weight: 700;
    color: #1e3a8a;
    margin-bottom: 8px;
}

.page-header p {
    font-size: 16px;
    color: #64748b;
    margin-bottom: 0;
}

.filter-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    margin-bottom: 24px;
}

.rekap-table {
    background: #ffffff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.rekap-table thead {
    background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
    color: #ffffff;
}

.rekap-table thead th {
    padding: 16px;
    font-weight: 600;
    border: none;
    text-align: center;
}

.rekap-table tbody td {
    padding: 16px;
    vertical-align: middle;
    border-bottom: 1px solid #e5e7eb;
}

.rekap-table tbody tr:hover {
    background-color: #f8fafc;
}

.badge-presensi {
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.875rem;
}

.badge-hadir {
    background-color: #d1fae5;
    color: #10b981;
}

.badge-terlambat {
    background-color: #fed7aa;
    color: #f97316;
}

.badge-tidak-hadir {
    background-color: #fecdd3;
    color: #f43f5e;
}

.summary-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    text-align: center;
}

.summary-card .summary-value {
    font-size: 32px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 8px;
}

.summary-card .summary-label {
    font-size: 14px;
    color: #64748b;
    font-weight: 600;
}

.btn-download {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border: none;
    color: #ffffff;
    font-weight: 600;
    padding: 12px 24px;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.btn-download:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    color: #ffffff;
}
</style>

<div class="page-header">
    <h2><i class="bi bi-clipboard-data"></i> Rekap Kehadiran</h2>
    <p>Lihat ringkasan kehadiran siswa dalam periode tertentu</p>
</div>

<!-- Filter Section -->
<div class="filter-card">
    <form method="GET" id="filterForm">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label fw-bold" style="color: #1e293b;">Periode</label>
                <select name="periode" class="form-select" id="periodeSelect" onchange="updateDateInput()">
                    <option value="minggu" <?php echo $filter_periode == 'minggu' ? 'selected' : ''; ?>>Per Minggu</option>
                    <option value="bulan" <?php echo $filter_periode == 'bulan' ? 'selected' : ''; ?>>Per Bulan</option>
                    <option value="semester" <?php echo $filter_periode == 'semester' ? 'selected' : ''; ?>>Per Semester</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold" style="color: #1e293b;">Tanggal</label>
                <input type="date" name="tanggal" class="form-control" value="<?php echo htmlspecialchars($filter_tanggal); ?>" id="tanggalInput">
                <input type="hidden" name="periode" value="<?php echo htmlspecialchars($filter_periode); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold" style="color: #1e293b;">Mata Pelajaran</label>
                <select name="mata_pelajaran_id" class="form-select">
                    <option value="0" <?php echo $filter_mata_pelajaran == 0 ? 'selected' : ''; ?>>Semua Mata Pelajaran</option>
                    <?php foreach ($mata_pelajaran_list as $mp): ?>
                        <option value="<?php echo $mp['id']; ?>" <?php echo $filter_mata_pelajaran == $mp['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($mp['nama_pelajaran']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Filter
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="summary-card">
            <div class="summary-value"><?php echo count($siswa_rekap); ?></div>
            <div class="summary-label">Total Siswa</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="summary-card">
            <div class="summary-value"><?php echo count($sesi_list); ?></div>
            <div class="summary-label">Total Sesi</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="summary-card">
            <div class="summary-value">
                <?php 
                $total_hadir = array_sum(array_column($siswa_rekap, 'total_hadir'));
                echo $total_hadir;
                ?>
            </div>
            <div class="summary-label">Total Hadir</div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="summary-card">
            <div class="summary-value">
                <?php 
                $avg_persentase = count($siswa_rekap) > 0 
                    ? round(array_sum(array_column($siswa_rekap, 'persentase_hadir')) / count($siswa_rekap), 1) 
                    : 0;
                echo $avg_persentase . '%';
                ?>
            </div>
            <div class="summary-label">Rata-rata Kehadiran</div>
        </div>
    </div>
</div>

<!-- Download Button -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="mb-0" style="color: #1e293b; font-weight: 600;">
            Periode: <?php echo htmlspecialchars($periode_label); ?> | 
            Mata Pelajaran: <?php echo htmlspecialchars($mata_pelajaran_name); ?>
        </h5>
    </div>
    <a href="download_rekap_pdf.php?periode=<?php echo urlencode($filter_periode); ?>&tanggal=<?php echo urlencode($filter_tanggal); ?>&mata_pelajaran_id=<?php echo $filter_mata_pelajaran; ?>" 
       class="btn btn-download" target="_blank">
        <i class="bi bi-download me-2"></i> Download PDF
    </a>
</div>

<!-- Rekap Table -->
<?php if (empty($siswa_rekap)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-inbox text-muted" style="font-size: 4rem; opacity: 0.3;"></i>
            <h5 class="mt-3 text-muted">Tidak ada data rekap</h5>
            <p class="text-muted">Tidak ada presensi dalam periode yang dipilih</p>
        </div>
    </div>
<?php else: ?>
    <div class="rekap-table">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th style="width: 20%;">Nama Siswa</th>
                    <th style="width: 15%;">Kelas</th>
                    <th style="width: 12%;" class="text-center">Hadir</th>
                    <th style="width: 12%;" class="text-center">Terlambat</th>
                    <th style="width: 12%;" class="text-center">Tidak Hadir</th>
                    <th style="width: 12%;" class="text-center">Total Presensi</th>
                    <th style="width: 12%;" class="text-center">Persentase</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                foreach ($siswa_rekap as $siswa): 
                    // Calculate total sesi yang seharusnya ada untuk siswa ini
                    // For now, use total_sesi from all sessions
                ?>
                    <tr>
                        <td class="text-center"><?php echo $no++; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></strong>
                            <br>
                            <small class="text-muted"><?php echo htmlspecialchars($siswa['username']); ?></small>
                        </td>
                        <td>
                            <?php if ($siswa['nama_kelas']): ?>
                                <?php echo htmlspecialchars($siswa['nama_kelas']); ?>
                                <?php if ($siswa['tingkat']): ?>
                                    <br><small class="text-muted">Kelas <?php echo $siswa['tingkat']; ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="badge-presensi badge-hadir"><?php echo $siswa['total_hadir']; ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge-presensi badge-terlambat"><?php echo $siswa['total_terlambat']; ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge-presensi badge-tidak-hadir"><?php echo $siswa['total_tidak_hadir']; ?></span>
                        </td>
                        <td class="text-center">
                            <strong><?php echo $siswa['total_presensi']; ?></strong>
                            <br>
                            <small class="text-muted">dari <?php echo $siswa['total_sesi']; ?> sesi</small>
                        </td>
                        <td class="text-center">
                            <strong style="color: <?php echo $siswa['persentase_hadir'] >= 75 ? '#10b981' : ($siswa['persentase_hadir'] >= 50 ? '#f97316' : '#f43f5e'); ?>;">
                                <?php echo $siswa['persentase_hadir']; ?>%
                            </strong>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
function updateDateInput() {
    const periode = document.getElementById('periodeSelect').value;
    const tanggalInput = document.getElementById('tanggalInput');
    const today = new Date().toISOString().split('T')[0];
    
    // Update tanggal berdasarkan periode
    tanggalInput.value = today;
    
    // Update hidden input
    const hiddenInput = document.querySelector('input[name="periode"][type="hidden"]');
    if (hiddenInput) {
        hiddenInput.value = periode;
    }
}

// Update date input type based on periode
document.getElementById('periodeSelect').addEventListener('change', function() {
    const periode = this.value;
    const tanggalInput = document.getElementById('tanggalInput');
    
    if (periode === 'semester') {
        // For semester, show month picker would be better, but we'll use date
        // Set to first day of current month for semester calculation
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>

