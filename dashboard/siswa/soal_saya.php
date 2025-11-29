<?php
$page_title = 'Soal Saya';
require_once '../../config/session.php';
requireRole(['siswa']);
require_once '../../includes/header.php';

$conn = getConnection();
$siswa_id = $_SESSION['user_id'];
$sekolah_id = $_SESSION['sekolah_id'];

// Check if tipe_ujian column exists
$check_column = $conn->query("SHOW COLUMNS FROM soal LIKE 'tipe_ujian'");
if ($check_column && $check_column->num_rows > 0) {
    // Get only soal latihan (not UTS/UAS)
    $soal_list = $conn->query("SELECT s.*, mp.nama_pelajaran, 
        (SELECT COUNT(*) FROM hasil_ujian hu WHERE hu.soal_id = s.id AND hu.siswa_id = $siswa_id) as sudah_dikerjakan,
        (SELECT nilai FROM hasil_ujian hu WHERE hu.soal_id = s.id AND hu.siswa_id = $siswa_id LIMIT 1) as nilai
        FROM soal s 
        JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
        WHERE mp.sekolah_id = $sekolah_id 
        AND (s.tipe_ujian = 'latihan' OR s.tipe_ujian IS NULL)
        ORDER BY s.created_at DESC")->fetch_all(MYSQLI_ASSOC);
} else {
    // Fallback: get all soal (tipe_ujian column doesn't exist yet)
    $soal_list = $conn->query("SELECT s.*, mp.nama_pelajaran, 
        (SELECT COUNT(*) FROM hasil_ujian hu WHERE hu.soal_id = s.id AND hu.siswa_id = $siswa_id) as sudah_dikerjakan,
        (SELECT nilai FROM hasil_ujian hu WHERE hu.soal_id = s.id AND hu.siswa_id = $siswa_id LIMIT 1) as nilai
        FROM soal s 
        JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
        WHERE mp.sekolah_id = $sekolah_id 
        ORDER BY s.created_at DESC")->fetch_all(MYSQLI_ASSOC);
}

$conn->close();

// Show success message if redirected from submit
$show_success_alert = isset($_GET['success']) && $_GET['success'] == 1;
?>

<div class="page-header">
    <h2>Soal Saya</h2>
    <p>Daftar semua soal yang tersedia untuk Anda</p>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="soalSayaTable" class="table table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Judul</th>
                                <th>Mata Pelajaran</th>
                                <th>Jenis</th>
                                <th>Status Soal</th>
                                <th>Waktu</th>
                                <th>Status Pengerjaan</th>
                                <th>Nilai</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($soal_list as $soal): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($soal['judul']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($soal['nama_pelajaran']); ?></td>
                                    <td>
                                        <?php 
                                        $jenis_labels = [
                                            'quiz' => 'Quiz',
                                            'pilihan_ganda' => 'Pilihan Ganda',
                                            'isian' => 'Isian'
                                        ];
                                        echo $jenis_labels[$soal['jenis']] ?? $soal['jenis'];
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_badges = [
                                            'draft' => 'bg-secondary',
                                            'aktif' => 'bg-success',
                                            'selesai' => 'bg-warning'
                                        ];
                                        $badge_class = $status_badges[$soal['status']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($soal['status']); ?></span>
                                    </td>
                                    <td><?php echo $soal['waktu_pengerjaan']; ?> menit</td>
                                    <td>
                                        <?php if ($soal['sudah_dikerjakan'] > 0): ?>
                                            <span class="badge bg-success">Selesai</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Belum Dikerjakan</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($soal['nilai'] !== null): ?>
                                            <strong><?php echo number_format($soal['nilai'], 2); ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($soal['status'] == 'aktif'): ?>
                                            <?php if ($soal['sudah_dikerjakan'] > 0): ?>
                                                <a href="hasil.php?soal_id=<?php echo $soal['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-eye"></i> Lihat Hasil
                                                </a>
                                            <?php else: ?>
                                                <a href="kerjakan_soal.php?id=<?php echo $soal['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-pencil"></i> Kerjakan
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Tidak tersedia</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#soalSayaTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
        },
        responsive: true,
        order: [[0, 'asc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Semua"]],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
    });
});
</script>

<?php if ($show_success_alert): ?>
<script>
// Pastikan script dijalankan setelah semua library ter-load
window.addEventListener('load', function() {
    setTimeout(function() {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Selamat!',
                text: 'Selamat, anda telah menyelesaikan soal',
                confirmButtonText: 'Oke',
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'index.php';
                }
            });
        } else {
            // Fallback jika SweetAlert2 belum ter-load
            alert('Selamat, anda telah menyelesaikan soal');
            window.location.href = 'index.php';
        }
    }, 300);
});
</script>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
