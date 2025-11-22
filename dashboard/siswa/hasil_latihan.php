<?php
$page_title = 'Hasil Latihan';
require_once '../../config/session.php';
requireRole(['siswa']);
require_once '../../includes/header.php';

$conn = getConnection();
$siswa_id = $_SESSION['user_id'];
$sekolah_id = $_SESSION['sekolah_id'];

// Get all hasil ujian yang sudah selesai
$hasil_list = $conn->query("SELECT hu.*, s.judul, s.deskripsi, s.jenis, mp.nama_pelajaran 
    FROM hasil_ujian hu 
    JOIN soal s ON hu.soal_id = s.id 
    JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
    WHERE hu.siswa_id = $siswa_id 
    AND hu.status = 'selesai'
    AND mp.sekolah_id = $sekolah_id
    ORDER BY hu.waktu_selesai DESC")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<div class="page-header">
    <h2>Hasil Latihan</h2>
    <p>Lihat semua hasil latihan dan quiz yang telah Anda kerjakan</p>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-file-earmark-check"></i> Daftar Hasil Latihan</h5>
            </div>
            <div class="card-body">
                <?php if (count($hasil_list) > 0): ?>
                    <div class="table-responsive">
                        <table id="hasilLatihanTable" class="table table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Judul Soal</th>
                                    <th>Mata Pelajaran</th>
                                    <th>Jenis</th>
                                    <th>Nilai</th>
                                    <th>Poin</th>
                                    <th>Waktu Selesai</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hasil_list as $hasil): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($hasil['judul']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($hasil['nama_pelajaran']); ?></td>
                                        <td>
                                            <?php 
                                            $jenis_labels = [
                                                'quiz' => 'Quiz',
                                                'pilihan_ganda' => 'Pilihan Ganda',
                                                'isian' => 'Isian'
                                            ];
                                            echo $jenis_labels[$hasil['jenis']] ?? $hasil['jenis'];
                                            ?>
                                        </td>
                                        <td>
                                            <strong class="text-<?php echo $hasil['nilai'] >= 70 ? 'success' : ($hasil['nilai'] >= 50 ? 'warning' : 'danger'); ?>">
                                                <?php echo number_format($hasil['nilai'], 2); ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <?php echo number_format($hasil['poin_diperoleh'], 0); ?> / <?php echo number_format($hasil['total_poin'], 0); ?>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y H:i', strtotime($hasil['waktu_selesai'])); ?>
                                        </td>
                                        <td>
                                            <a href="hasil.php?soal_id=<?php echo $hasil['soal_id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-eye"></i> Lihat Detail
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Belum ada hasil latihan yang tersedia. Silakan kerjakan soal terlebih dahulu.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#hasilLatihanTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
        },
        responsive: true,
        order: [[5, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Semua"]],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>

