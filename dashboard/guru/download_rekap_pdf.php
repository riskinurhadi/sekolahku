<?php
require_once '../../config/session.php';
require_once '../../config/database.php';
requireRole(['guru']);

require_once '../../fpdf/fpdf.php';

$conn = getConnection();
$guru_id = $_SESSION['user_id'];
$sekolah_id = $_SESSION['sekolah_id'];

// Get filter parameters
$filter_periode = isset($_GET['periode']) ? $_GET['periode'] : 'bulan';
$filter_mata_pelajaran = isset($_GET['mata_pelajaran_id']) ? intval($_GET['mata_pelajaran_id']) : 0;
$filter_tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

// Calculate date range based on periode (same logic as rekap_kehadiran.php)
$date_start = date('Y-m-d');
$date_end = date('Y-m-d');
$periode_label = '';

if ($filter_periode == 'minggu') {
    $date_start = date('Y-m-d', strtotime('monday this week', strtotime($filter_tanggal)));
    $date_end = date('Y-m-d', strtotime('sunday this week', strtotime($filter_tanggal)));
    $periode_label = date('d/m/Y', strtotime($date_start)) . ' - ' . date('d/m/Y', strtotime($date_end));
} elseif ($filter_periode == 'bulan') {
    $date_start = date('Y-m-01', strtotime($filter_tanggal));
    $date_end = date('Y-m-t', strtotime($filter_tanggal));
    $periode_label = date('F Y', strtotime($filter_tanggal));
} elseif ($filter_periode == 'semester') {
    $month = date('n', strtotime($filter_tanggal));
    $year = date('Y', strtotime($filter_tanggal));
    
    if ($month >= 7 && $month <= 12) {
        $date_start = $year . '-07-01';
        $date_end = $year . '-12-31';
        $periode_label = 'Semester 1 ' . $year;
    } else {
        $date_start = $year . '-01-01';
        $date_end = $year . '-06-30';
        $periode_label = 'Semester 2 ' . $year;
    }
}

// Get guru info
$stmt = $conn->prepare("SELECT nama_lengkap FROM users WHERE id = ?");
$stmt->bind_param("i", $guru_id);
$stmt->execute();
$guru = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get mata pelajaran info
$mata_pelajaran_name = 'Semua Mata Pelajaran';
if ($filter_mata_pelajaran > 0) {
    $stmt = $conn->prepare("SELECT nama_pelajaran FROM mata_pelajaran WHERE id = ? AND guru_id = ?");
    $stmt->bind_param("ii", $filter_mata_pelajaran, $guru_id);
    $stmt->execute();
    $mp_result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($mp_result) {
        $mata_pelajaran_name = $mp_result['nama_pelajaran'];
    }
}

// Get sesi list
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

// Get siswa rekap (same logic as rekap_kehadiran.php)
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

$siswa_rekap = [];
foreach ($siswa_data as $siswa) {
    $siswa_id = $siswa['id'];
    
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

$conn->close();

// Create PDF
class PDF extends FPDF
{
    function Header()
    {
        // Logo or Title
        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor(30, 58, 138);
        $this->Cell(0, 10, iconv('UTF-8', 'ISO-8859-1', 'Rekap Kehadiran Siswa'), 0, 1, 'C');
        $this->Ln(5);
    }

    function Footer()
    {
        // Position at 1.5 cm from bottom
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 10, iconv('UTF-8', 'ISO-8859-1', 'Halaman ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function TableHeader()
    {
        // Header colors
        $this->SetFillColor(30, 58, 138);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.3);
        $this->SetFont('Arial', 'B', 9);
        
        // Header columns
        $this->Cell(10, 8, 'No', 1, 0, 'C', true);
        $this->Cell(55, 8, iconv('UTF-8', 'ISO-8859-1', 'Nama Siswa'), 1, 0, 'L', true);
        $this->Cell(25, 8, iconv('UTF-8', 'ISO-8859-1', 'Kelas'), 1, 0, 'C', true);
        $this->Cell(20, 8, iconv('UTF-8', 'ISO-8859-1', 'Hadir'), 1, 0, 'C', true);
        $this->Cell(20, 8, iconv('UTF-8', 'ISO-8859-1', 'Terlambat'), 1, 0, 'C', true);
        $this->Cell(20, 8, iconv('UTF-8', 'ISO-8859-1', 'Tidak Hadir'), 1, 0, 'C', true);
        $this->Cell(20, 8, iconv('UTF-8', 'ISO-8859-1', 'Total'), 1, 0, 'C', true);
        $this->Cell(20, 8, iconv('UTF-8', 'ISO-8859-1', 'Persentase'), 1, 1, 'C', true);
        
        $this->SetTextColor(0, 0, 0);
    }

    function TableRow($no, $siswa)
    {
        $this->SetFont('Arial', '', 9);
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.3);
        
        // No
        $this->Cell(10, 7, $no, 1, 0, 'C');
        
        // Nama Siswa
        $this->Cell(55, 7, iconv('UTF-8', 'ISO-8859-1', $siswa['nama_lengkap']), 1, 0, 'L');
        
        // Kelas
        $kelas = $siswa['nama_kelas'] ? iconv('UTF-8', 'ISO-8859-1', $siswa['nama_kelas']) : '-';
        $this->Cell(25, 7, $kelas, 1, 0, 'C');
        
        // Hadir
        $this->SetFillColor(209, 250, 229);
        $this->Cell(20, 7, $siswa['total_hadir'], 1, 0, 'C', true);
        $this->SetFillColor(255, 255, 255);
        
        // Terlambat
        $this->SetFillColor(254, 215, 170);
        $this->Cell(20, 7, $siswa['total_terlambat'], 1, 0, 'C', true);
        $this->SetFillColor(255, 255, 255);
        
        // Tidak Hadir
        $this->SetFillColor(254, 205, 211);
        $this->Cell(20, 7, $siswa['total_tidak_hadir'], 1, 0, 'C', true);
        $this->SetFillColor(255, 255, 255);
        
        // Total
        $this->Cell(20, 7, $siswa['total_presensi'], 1, 0, 'C');
        
        // Persentase
        $color = [255, 255, 255];
        if ($siswa['persentase_hadir'] >= 75) {
            $color = [209, 250, 229];
        } elseif ($siswa['persentase_hadir'] >= 50) {
            $color = [254, 215, 170];
        } else {
            $color = [254, 205, 211];
        }
        $this->SetFillColor($color[0], $color[1], $color[2]);
        $this->Cell(20, 7, $siswa['persentase_hadir'] . '%', 1, 1, 'C', true);
        $this->SetFillColor(255, 255, 255);
    }
}

$pdf = new PDF('L'); // Landscape orientation
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

// Info Section
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(30, 58, 138);
$pdf->Cell(0, 7, iconv('UTF-8', 'ISO-8859-1', 'Guru: ' . $guru['nama_lengkap']), 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 6, iconv('UTF-8', 'ISO-8859-1', 'Periode: ' . $periode_label), 0, 1, 'L');
$pdf->Cell(0, 6, iconv('UTF-8', 'ISO-8859-1', 'Mata Pelajaran: ' . $mata_pelajaran_name), 0, 1, 'L');
$pdf->Cell(0, 6, iconv('UTF-8', 'ISO-8859-1', 'Tanggal Cetak: ' . date('d/m/Y H:i:s')), 0, 1, 'L');
$pdf->Ln(5);

// Summary
$total_siswa = count($siswa_rekap);
$total_sesi = count($sesi_list);
$total_hadir = array_sum(array_column($siswa_rekap, 'total_hadir'));
$avg_persentase = count($siswa_rekap) > 0 
    ? round(array_sum(array_column($siswa_rekap, 'persentase_hadir')) / count($siswa_rekap), 1) 
    : 0;

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 7, iconv('UTF-8', 'ISO-8859-1', 'Ringkasan: Total Siswa: ' . $total_siswa . ' | Total Sesi: ' . $total_sesi . ' | Total Hadir: ' . $total_hadir . ' | Rata-rata Kehadiran: ' . $avg_persentase . '%'), 0, 1, 'L');
$pdf->Ln(5);

// Table
$pdf->TableHeader();
$no = 1;
foreach ($siswa_rekap as $siswa) {
    $pdf->TableRow($no++, $siswa);
    
    // Check if we need a new page
    if ($pdf->GetY() > 180) {
        $pdf->AddPage('L');
        $pdf->TableHeader();
    }
}

// Output PDF
$filename = 'Rekap_Kehadiran_' . date('Y-m-d') . '_' . str_replace(' ', '_', $periode_label) . '.pdf';
$pdf->Output('D', $filename); // 'D' = download, 'I' = inline
?>

