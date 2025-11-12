<?php
$page_title = 'Kelola Soal';
require_once '../../config/session.php';
requireRole(['guru']);
require_once '../../includes/header.php';

$conn = getConnection();
$guru_id = $_SESSION['user_id'];
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $mata_pelajaran_id = $_POST['mata_pelajaran_id'];
            $judul = $_POST['judul'];
            $deskripsi = $_POST['deskripsi'] ?? '';
            $jenis = $_POST['jenis'];
            $waktu_pengerjaan = $_POST['waktu_pengerjaan'] ?? 60;
            $tanggal_mulai = $_POST['tanggal_mulai'] ?? null;
            $tanggal_selesai = $_POST['tanggal_selesai'] ?? null;
            $status = $_POST['status'] ?? 'draft';
            
            $stmt = $conn->prepare("INSERT INTO soal (mata_pelajaran_id, guru_id, judul, deskripsi, jenis, waktu_pengerjaan, tanggal_mulai, tanggal_selesai, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssisss", $mata_pelajaran_id, $guru_id, $judul, $deskripsi, $jenis, $waktu_pengerjaan, $tanggal_mulai, $tanggal_selesai, $status);
            
            if ($stmt->execute()) {
                $soal_id = $stmt->insert_id;
                $stmt->close();
                
                // Handle item soal
                if (isset($_POST['pertanyaan']) && is_array($_POST['pertanyaan'])) {
                    foreach ($_POST['pertanyaan'] as $index => $pertanyaan) {
                        if (!empty(trim($pertanyaan))) {
                            $jenis_jawaban = $_POST['jenis_jawaban'][$index] ?? 'pilihan_ganda';
                            $poin = $_POST['poin'][$index] ?? 1;
                            
                            $stmt = $conn->prepare("INSERT INTO item_soal (soal_id, pertanyaan, jenis_jawaban, poin, urutan) VALUES (?, ?, ?, ?, ?)");
                            $urutan = $index + 1;
                            $stmt->bind_param("issii", $soal_id, $pertanyaan, $jenis_jawaban, $poin, $urutan);
                            $stmt->execute();
                            $item_soal_id = $stmt->insert_id;
                            $stmt->close();
                            
                            // Handle pilihan jawaban untuk pilihan ganda
                            if ($jenis_jawaban == 'pilihan_ganda' && isset($_POST['pilihan'][$index]) && is_array($_POST['pilihan'][$index])) {
                                foreach ($_POST['pilihan'][$index] as $pilihan_index => $pilihan_text) {
                                    if (!empty(trim($pilihan_text))) {
                                        $is_benar = isset($_POST['is_benar'][$index][$pilihan_index]) ? 1 : 0;
                                        $urutan_pilihan = $pilihan_index + 1;
                                        
                                        $stmt = $conn->prepare("INSERT INTO pilihan_jawaban (item_soal_id, pilihan, is_benar, urutan) VALUES (?, ?, ?, ?)");
                                        $stmt->bind_param("isii", $item_soal_id, $pilihan_text, $is_benar, $urutan_pilihan);
                                        $stmt->execute();
                                        $stmt->close();
                                    }
                                }
                            }
                        }
                    }
                }
                
                $message = 'success:Soal berhasil ditambahkan!';
            } else {
                $message = 'error:Gagal menambahkan soal!';
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'];
            $stmt = $conn->prepare("DELETE FROM soal WHERE id = ? AND guru_id = ?");
            $stmt->bind_param("ii", $id, $guru_id);
            
            if ($stmt->execute()) {
                $message = 'success:Soal berhasil dihapus!';
            } else {
                $message = 'error:Gagal menghapus soal!';
            }
            $stmt->close();
        } elseif ($_POST['action'] == 'update_status') {
            $id = $_POST['id'];
            $status = $_POST['status'];
            $stmt = $conn->prepare("UPDATE soal SET status = ? WHERE id = ? AND guru_id = ?");
            $stmt->bind_param("sii", $status, $id, $guru_id);
            
            if ($stmt->execute()) {
                $message = 'success:Status soal berhasil diupdate!';
            } else {
                $message = 'error:Gagal mengupdate status!';
            }
            $stmt->close();
        }
    }
}

// Get all soal
$stmt = $conn->prepare("SELECT s.*, mp.nama_pelajaran 
    FROM soal s 
    JOIN mata_pelajaran mp ON s.mata_pelajaran_id = mp.id 
    WHERE s.guru_id = ? 
    ORDER BY s.created_at DESC");
$stmt->bind_param("i", $guru_id);
$stmt->execute();
$soal_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get mata pelajaran for dropdown
$mata_pelajaran = $conn->query("SELECT * FROM mata_pelajaran WHERE guru_id = $guru_id ORDER BY nama_pelajaran")->fetch_all(MYSQLI_ASSOC);

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

<div class="row mb-4">
    <div class="col-12">
        <h2 class="mb-0">Kelola Soal & Quiz</h2>
        <p class="text-muted">Buat dan kelola soal, quiz, dan ujian</p>
    </div>
</div>

<!-- Add Soal Form -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Tambah Soal Baru</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="addSoalForm">
                    <input type="hidden" name="action" value="add">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                            <select class="form-select" name="mata_pelajaran_id" id="mata_pelajaran_id" required>
                                <option value="">Pilih Mata Pelajaran</option>
                                <?php foreach ($mata_pelajaran as $mp): ?>
                                    <option value="<?php echo $mp['id']; ?>"><?php echo htmlspecialchars($mp['nama_pelajaran']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jenis Soal <span class="text-danger">*</span></label>
                            <select class="form-select" name="jenis" id="jenis_soal" required>
                                <option value="quiz">Quiz</option>
                                <option value="pilihan_ganda">Pilihan Ganda</option>
                                <option value="isian">Isian</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Judul Soal <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="judul" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="form-control" name="deskripsi" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Waktu Pengerjaan (menit)</label>
                            <input type="number" class="form-control" name="waktu_pengerjaan" value="60" min="1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tanggal Mulai</label>
                            <input type="datetime-local" class="form-control" name="tanggal_mulai">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tanggal Selesai</label>
                            <input type="datetime-local" class="form-control" name="tanggal_selesai">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="draft">Draft</option>
                                <option value="aktif">Aktif</option>
                                <option value="selesai">Selesai</option>
                            </select>
                        </div>
                    </div>
                    
                    <hr>
                    <h5>Pertanyaan</h5>
                    <div id="pertanyaan-container">
                        <div class="pertanyaan-item mb-4 p-3 border rounded">
                            <div class="row mb-2">
                                <div class="col-md-8">
                                    <label class="form-label">Pertanyaan 1 <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="pertanyaan[]" rows="2" required></textarea>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Jenis Jawaban</label>
                                    <select class="form-select jenis-jawaban" name="jenis_jawaban[]">
                                        <option value="pilihan_ganda">Pilihan Ganda</option>
                                        <option value="isian">Isian</option>
                                        <option value="essay">Essay</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Poin</label>
                                    <input type="number" class="form-control" name="poin[]" value="1" min="1">
                                </div>
                            </div>
                            <div class="pilihan-container">
                                <label class="form-label">Pilihan Jawaban</label>
                                <div class="pilihan-item mb-2">
                                    <div class="input-group">
                                        <div class="input-group-text">
                                            <input type="radio" name="is_benar[0][0]" value="1">
                                        </div>
                                        <input type="text" class="form-control" name="pilihan[0][]" placeholder="Pilihan A">
                                    </div>
                                </div>
                                <div class="pilihan-item mb-2">
                                    <div class="input-group">
                                        <div class="input-group-text">
                                            <input type="radio" name="is_benar[0][1]" value="1">
                                        </div>
                                        <input type="text" class="form-control" name="pilihan[0][]" placeholder="Pilihan B">
                                    </div>
                                </div>
                                <div class="pilihan-item mb-2">
                                    <div class="input-group">
                                        <div class="input-group-text">
                                            <input type="radio" name="is_benar[0][2]" value="1">
                                        </div>
                                        <input type="text" class="form-control" name="pilihan[0][]" placeholder="Pilihan C">
                                    </div>
                                </div>
                                <div class="pilihan-item mb-2">
                                    <div class="input-group">
                                        <div class="input-group-text">
                                            <input type="radio" name="is_benar[0][3]" value="1">
                                        </div>
                                        <input type="text" class="form-control" name="pilihan[0][]" placeholder="Pilihan D">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="tambahPertanyaan()">
                        <i class="bi bi-plus"></i> Tambah Pertanyaan
                    </button>
                    
                    <hr>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Simpan Soal
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Soal List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list"></i> Daftar Soal</h5>
            </div>
            <div class="card-body">
                <?php if (count($soal_list) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Judul</th>
                                    <th>Mata Pelajaran</th>
                                    <th>Jenis</th>
                                    <th>Status</th>
                                    <th>Waktu</th>
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
                                            <form method="POST" style="display: inline;" class="status-form">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="id" value="<?php echo $soal['id']; ?>">
                                                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                                    <option value="draft" <?php echo $soal['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                                    <option value="aktif" <?php echo $soal['status'] == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                                                    <option value="selesai" <?php echo $soal['status'] == 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td><?php echo $soal['waktu_pengerjaan']; ?> menit</td>
                                        <td>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus soal ini?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $soal['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="bi bi-trash"></i> Hapus
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-file-earmark-text"></i>
                        <h5>Belum ada soal</h5>
                        <p>Mulai dengan menambahkan soal baru di atas.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
let pertanyaanCount = 1;

function tambahPertanyaan() {
    const container = document.getElementById('pertanyaan-container');
    const newItem = document.createElement('div');
    newItem.className = 'pertanyaan-item mb-4 p-3 border rounded';
    newItem.innerHTML = `
        <div class="row mb-2">
            <div class="col-md-8">
                <label class="form-label">Pertanyaan ${pertanyaanCount + 1} <span class="text-danger">*</span></label>
                <textarea class="form-control" name="pertanyaan[]" rows="2" required></textarea>
            </div>
            <div class="col-md-2">
                <label class="form-label">Jenis Jawaban</label>
                <select class="form-select jenis-jawaban" name="jenis_jawaban[]" onchange="togglePilihan(this)">
                    <option value="pilihan_ganda">Pilihan Ganda</option>
                    <option value="isian">Isian</option>
                    <option value="essay">Essay</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Poin</label>
                <input type="number" class="form-control" name="poin[]" value="1" min="1">
            </div>
        </div>
        <div class="pilihan-container">
            <label class="form-label">Pilihan Jawaban</label>
            <div class="pilihan-item mb-2">
                <div class="input-group">
                    <div class="input-group-text">
                        <input type="radio" name="is_benar[${pertanyaanCount}][0]" value="1">
                    </div>
                    <input type="text" class="form-control" name="pilihan[${pertanyaanCount}][]" placeholder="Pilihan A">
                </div>
            </div>
            <div class="pilihan-item mb-2">
                <div class="input-group">
                    <div class="input-group-text">
                        <input type="radio" name="is_benar[${pertanyaanCount}][1]" value="1">
                    </div>
                    <input type="text" class="form-control" name="pilihan[${pertanyaanCount}][]" placeholder="Pilihan B">
                </div>
            </div>
            <div class="pilihan-item mb-2">
                <div class="input-group">
                    <div class="input-group-text">
                        <input type="radio" name="is_benar[${pertanyaanCount}][2]" value="1">
                    </div>
                    <input type="text" class="form-control" name="pilihan[${pertanyaanCount}][]" placeholder="Pilihan C">
                </div>
            </div>
            <div class="pilihan-item mb-2">
                <div class="input-group">
                    <div class="input-group-text">
                        <input type="radio" name="is_benar[${pertanyaanCount}][3]" value="1">
                    </div>
                    <input type="text" class="form-control" name="pilihan[${pertanyaanCount}][]" placeholder="Pilihan D">
                </div>
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-danger mt-2" onclick="this.closest('.pertanyaan-item').remove()">
            <i class="bi bi-trash"></i> Hapus Pertanyaan
        </button>
    `;
    container.appendChild(newItem);
    pertanyaanCount++;
}

function togglePilihan(select) {
    const container = select.closest('.pertanyaan-item').querySelector('.pilihan-container');
    if (select.value === 'pilihan_ganda') {
        container.style.display = 'block';
    } else {
        container.style.display = 'none';
    }
}

// Initialize toggle for existing items
document.querySelectorAll('.jenis-jawaban').forEach(select => {
    select.addEventListener('change', function() {
        togglePilihan(this);
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>

