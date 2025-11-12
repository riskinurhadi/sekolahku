<?php
$page_title = 'Nilai Hasil Ujian';
require_once '../../config/session.php';
requireRole(['guru']);
require_once '../../includes/header.php';

$conn = getConnection();
$guru_id = $_SESSION['user_id'];
$soal_id = $_GET['soal_id'] ?? 0;
$message = '';

// Verify soal belongs to this guru
$stmt = $conn->prepare("SELECT s.*, mp.nama_pelajaran FROM soal s 
    JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
    WHERE s.id = ? AND s.guru_id = ?");
$stmt->bind_param("ii", $soal_id, $guru_id);
$stmt->execute();
$soal = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$soal) {
    header('Location: soal.php');
    exit();
}

// Handle update nilai
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_nilai') {
    $jawaban_id = $_POST['jawaban_id'];
    $poin_baru = (int)$_POST['poin'];
    
    // Get item soal untuk validasi poin maksimal
    $stmt = $conn->prepare("SELECT poin FROM item_soal WHERE id = (SELECT item_soal_id FROM jawaban_siswa WHERE id = ?)");
    $stmt->bind_param("i", $jawaban_id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($item && $poin_baru >= 0 && $poin_baru <= $item['poin']) {
        // Update poin di jawaban_siswa
        $stmt = $conn->prepare("UPDATE jawaban_siswa SET poin_diperoleh = ? WHERE id = ?");
        $stmt->bind_param("ii", $poin_baru, $jawaban_id);
        $stmt->execute();
        $stmt->close();
        
        // Recalculate total poin dan nilai untuk semua hasil ujian dengan soal_id ini
        $stmt = $conn->prepare("SELECT DISTINCT siswa_id FROM jawaban_siswa WHERE soal_id = ?");
        $stmt->bind_param("i", $soal_id);
        $stmt->execute();
        $siswa_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        foreach ($siswa_list as $siswa) {
            $siswa_id = $siswa['siswa_id'];
            
            // Calculate total poin and poin diperoleh
            $stmt = $conn->prepare("SELECT 
                SUM(is.poin) as total_poin,
                COALESCE(SUM(js.poin_diperoleh), 0) as poin_diperoleh
                FROM item_soal is
                LEFT JOIN jawaban_siswa js ON is.id = js.item_soal_id AND js.siswa_id = ?
                WHERE is.soal_id = ?");
            $stmt->bind_param("ii", $siswa_id, $soal_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            $total_poin = $result['total_poin'] ?? 0;
            $poin_diperoleh = $result['poin_diperoleh'] ?? 0;
            $nilai = $total_poin > 0 ? ($poin_diperoleh / $total_poin) * 100 : 0;
            
            // Update hasil_ujian
            $stmt = $conn->prepare("UPDATE hasil_ujian SET total_poin = ?, poin_diperoleh = ?, nilai = ? WHERE soal_id = ? AND siswa_id = ?");
            $stmt->bind_param("iiddi", $total_poin, $poin_diperoleh, $nilai, $soal_id, $siswa_id);
            $stmt->execute();
            $stmt->close();
        }
        
        $message = 'success:Nilai berhasil diupdate!';
    } else {
        $message = 'error:Poin tidak valid!';
    }
}

// Get all hasil ujian untuk soal ini
$stmt = $conn->prepare("SELECT hu.*, u.nama_lengkap, u.username 
    FROM hasil_ujian hu 
    JOIN users u ON hu.siswa_id = u.id 
    WHERE hu.soal_id = ? 
    ORDER BY hu.waktu_selesai DESC, u.nama_lengkap ASC");
$stmt->bind_param("i", $soal_id);
$stmt->execute();
$hasil_ujian_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<?php if ($message): ?>
    <script>
        <?php 
        $msg = explode(':', $message);
        if ($msg[0] == 'success') {
            echo "showSuccess('" . addslashes($msg[1]) . "');";
        } else {
            echo "showError('" . addslashes($msg[1]) . "');";
        }
        ?>
    </script>
<?php endif; ?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2>Nilai Hasil Ujian</h2>
            <p><?php echo htmlspecialchars($soal['judul']); ?> - <?php echo htmlspecialchars($soal['nama_pelajaran']); ?></p>
        </div>
        <a href="soal.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Kembali ke Daftar Soal
        </a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list-check"></i> Daftar Hasil Ujian Siswa</h5>
            </div>
            <div class="card-body">
                <?php if (count($hasil_ujian_list) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="hasilUjianTable">
                            <thead>
                                <tr>
                                    <th>Nama Siswa</th>
                                    <th>Username</th>
                                    <th>Nilai</th>
                                    <th>Poin</th>
                                    <th>Status</th>
                                    <th>Waktu Selesai</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hasil_ujian_list as $hasil): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($hasil['nama_lengkap']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($hasil['username']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $hasil['nilai'] >= 70 ? 'success' : ($hasil['nilai'] >= 50 ? 'warning' : 'danger'); ?>">
                                                <?php echo number_format($hasil['nilai'], 2); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $hasil['poin_diperoleh']; ?> / <?php echo $hasil['total_poin']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $hasil['status'] == 'selesai' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($hasil['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $hasil['waktu_selesai'] ? date('d/m/Y H:i', strtotime($hasil['waktu_selesai'])) : '-'; ?></td>
                                        <td>
                                            <a href="detail_nilai.php?soal_id=<?php echo $soal_id; ?>&siswa_id=<?php echo $hasil['siswa_id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-eye"></i> Detail & Nilai
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state text-center py-5">
                        <i class="bi bi-clipboard-check" style="font-size: 4rem; color: #ccc;"></i>
                        <h5 class="mt-3">Belum ada hasil ujian</h5>
                        <p class="text-muted">Siswa belum mengerjakan soal ini</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    <?php if (count($hasil_ujian_list) > 0): ?>
    initDataTable('#hasilUjianTable', {
        order: [[5, 'desc']], // Sort by waktu selesai descending
        columnDefs: [
            { orderable: false, targets: [6] } // Disable sorting on action column
        ]
    });
    <?php endif; ?>
});
</script>

<?php require_once '../../includes/footer.php'; ?>

