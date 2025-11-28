<?php
$page_title = 'Ujian Tengah Semester (UTS)';
require_once '../../config/session.php';
requireRole(['siswa']);
require_once '../../includes/header.php';

$conn = getConnection();
$siswa_id = $_SESSION['user_id'];
$sekolah_id = $_SESSION['sekolah_id'];

// Get siswa kelas
$stmt = $conn->prepare("SELECT kelas_id FROM users WHERE id = ?");
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$siswa_info = $stmt->get_result()->fetch_assoc();
$kelas_id = $siswa_info['kelas_id'] ?? null;
$stmt->close();

// Get jadwal UTS untuk kelas siswa
$check_table = $conn->query("SHOW TABLES LIKE 'jadwal_ujian'");
$jadwal_list = [];
if ($check_table && $check_table->num_rows > 0 && $kelas_id) {
    $check_column = $conn->query("SHOW COLUMNS FROM soal LIKE 'tipe_ujian'");
    if ($check_column && $check_column->num_rows > 0) {
        $stmt = $conn->prepare("SELECT ju.*, s.id as soal_id, s.judul as judul_soal, s.deskripsi, s.waktu_pengerjaan, mp.nama_pelajaran, u.nama_lengkap as nama_guru, pengawas.nama_lengkap as nama_pengawas,
            (SELECT COUNT(*) FROM hasil_ujian hu WHERE hu.soal_id = s.id AND hu.siswa_id = ? AND hu.status = 'selesai') as sudah_dikerjakan
            FROM jadwal_ujian ju
            JOIN soal s ON ju.soal_id = s.id
            JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id
            JOIN users u ON s.guru_id = u.id
            LEFT JOIN users pengawas ON ju.pengawas_id = pengawas.id
            WHERE ju.kelas_id = ? AND s.tipe_ujian = 'uts'
            ORDER BY ju.tanggal_ujian DESC, ju.jam_mulai ASC");
        $stmt->bind_param("ii", $siswa_id, $kelas_id);
    } else {
        // Fallback jika kolom tipe_ujian belum ada
        $stmt = $conn->prepare("SELECT ju.*, s.id as soal_id, s.judul as judul_soal, s.deskripsi, s.waktu_pengerjaan, mp.nama_pelajaran, u.nama_lengkap as nama_guru, pengawas.nama_lengkap as nama_pengawas,
            (SELECT COUNT(*) FROM hasil_ujian hu WHERE hu.soal_id = s.id AND hu.siswa_id = ? AND hu.status = 'selesai') as sudah_dikerjakan
            FROM jadwal_ujian ju
            JOIN soal s ON ju.soal_id = s.id
            JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id
            JOIN users u ON s.guru_id = u.id
            LEFT JOIN users pengawas ON ju.pengawas_id = pengawas.id
            WHERE ju.kelas_id = ?
            ORDER BY ju.tanggal_ujian DESC, ju.jam_mulai ASC");
        $stmt->bind_param("ii", $siswa_id, $kelas_id);
    }
    $stmt->execute();
    $jadwal_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$conn->close();
?>

<div class="page-header">
    <div>
        <h2>Ujian Tengah Semester (UTS)</h2>
        <p>Daftar jadwal UTS yang harus Anda ikuti</p>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <?php if (empty($kelas_id)): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> Anda belum terdaftar dalam kelas. Silakan hubungi admin untuk mendaftarkan Anda ke kelas.
                    </div>
                <?php elseif (count($jadwal_list) > 0): ?>
                    <div class="table-responsive">
                        <table id="utsTable" class="table table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Mata Pelajaran</th>
                                    <th>Judul Soal</th>
                                    <th>Tanggal</th>
                                    <th>Waktu</th>
                                    <th>Ruangan</th>
                                    <th>Pengawas</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jadwal_list as $jadwal): ?>
                                    <?php
                                    $now = date('Y-m-d H:i:s');
                                    $tanggal_waktu = $jadwal['tanggal_ujian'] . ' ' . $jadwal['jam_mulai'];
                                    $tanggal_selesai = $jadwal['tanggal_ujian'] . ' ' . $jadwal['jam_selesai'];
                                    $is_belum_mulai = strtotime($tanggal_waktu) > strtotime($now);
                                    $is_berlangsung = strtotime($tanggal_waktu) <= strtotime($now) && strtotime($tanggal_selesai) >= strtotime($now);
                                    $is_selesai = strtotime($tanggal_selesai) < strtotime($now);
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($jadwal['nama_pelajaran']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($jadwal['judul_soal']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($jadwal['tanggal_ujian'])); ?></td>
                                        <td><?php echo date('H:i', strtotime($jadwal['jam_mulai'])); ?> - <?php echo date('H:i', strtotime($jadwal['jam_selesai'])); ?></td>
                                        <td><?php echo htmlspecialchars($jadwal['ruangan'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($jadwal['nama_pengawas'] ?: '-'); ?></td>
                                        <td>
                                            <?php if ($jadwal['sudah_dikerjakan'] > 0): ?>
                                                <span class="badge bg-success">Selesai</span>
                                            <?php elseif ($is_selesai): ?>
                                                <span class="badge bg-danger">Waktu Habis</span>
                                            <?php elseif ($is_berlangsung): ?>
                                                <span class="badge bg-warning">Berlangsung</span>
                                            <?php else: ?>
                                                <span class="badge bg-info">Belum Dimulai</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($jadwal['sudah_dikerjakan'] > 0): ?>
                                                <a href="hasil.php?soal_id=<?php echo $jadwal['soal_id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-eye"></i> Lihat Hasil
                                                </a>
                                            <?php elseif ($is_berlangsung || ($is_belum_mulai && strtotime($tanggal_waktu) <= strtotime('+1 hour', strtotime($now)))): ?>
                                                <a href="kerjakan_soal.php?id=<?php echo $jadwal['soal_id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-pencil"></i> Kerjakan
                                                </a>
                                            <?php elseif ($is_belum_mulai): ?>
                                                <span class="text-muted">Belum waktunya</span>
                                            <?php else: ?>
                                                <span class="text-muted">Waktu habis</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state text-center py-5">
                        <i class="bi bi-calendar-event" style="font-size: 3rem; color: #cbd5e1; opacity: 0.6;"></i>
                        <h5 class="mt-3 mb-2" style="font-size: 1rem; font-weight: 600; color: #1e293b;">Belum ada jadwal UTS</h5>
                        <p class="text-muted mb-3" style="font-size: 0.875rem; color: #64748b;">Jadwal UTS akan muncul setelah akademik mengatur jadwal ujian</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    <?php if (count($jadwal_list) > 0): ?>
    $('#utsTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
        },
        responsive: true,
        order: [[2, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Semua"]],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
    });
    <?php endif; ?>
});
</script>

<?php require_once '../../includes/footer.php'; ?>

