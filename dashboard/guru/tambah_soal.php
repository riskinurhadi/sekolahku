<?php
$page_title = 'Tambah Soal Baru';
require_once '../../config/session.php';
requireRole(['guru']);
require_once '../../includes/header.php';

$conn = getConnection();
$guru_id = $_SESSION['user_id'];
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
            $urutan_counter = 1; // Counter untuk urutan yang sebenarnya
            
            foreach ($_POST['pertanyaan'] as $index => $pertanyaan) {
                // Skip jika pertanyaan kosong
                if (empty(trim($pertanyaan))) {
                    continue;
                }
                
                $jenis_jawaban = $_POST['jenis_jawaban'][$index] ?? 'pilihan_ganda';
                $poin = $_POST['poin'][$index] ?? 1;
                
                // Insert item soal dengan urutan yang benar
                $stmt = $conn->prepare("INSERT INTO item_soal (soal_id, pertanyaan, jenis_jawaban, poin, urutan) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issii", $soal_id, $pertanyaan, $jenis_jawaban, $poin, $urutan_counter);
                $stmt->execute();
                $item_soal_id = $stmt->insert_id;
                $stmt->close();
                
                // Handle pilihan jawaban untuk pilihan ganda
                if ($jenis_jawaban == 'pilihan_ganda' && isset($_POST['pilihan'][$index]) && is_array($_POST['pilihan'][$index])) {
                    $urutan_pilihan_counter = 1;
                    foreach ($_POST['pilihan'][$index] as $pilihan_index => $pilihan_text) {
                        if (!empty(trim($pilihan_text))) {
                            $is_benar = isset($_POST['is_benar'][$index][$pilihan_index]) ? 1 : 0;
                            
                            $stmt = $conn->prepare("INSERT INTO pilihan_jawaban (item_soal_id, pilihan, is_benar, urutan) VALUES (?, ?, ?, ?)");
                            $stmt->bind_param("isii", $item_soal_id, $pilihan_text, $is_benar, $urutan_pilihan_counter);
                            $stmt->execute();
                            $stmt->close();
                            
                            $urutan_pilihan_counter++;
                        }
                    }
                }
                
                $urutan_counter++; // Increment untuk pertanyaan berikutnya
            }
        }
        
        $conn->close();
        echo "<script>showSuccess('Soal berhasil ditambahkan!'); setTimeout(function(){ window.location.href = 'soal.php'; }, 1500);</script>";
    } else {
        $message = 'error:Gagal menambahkan soal!';
    }
}

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

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2>Tambah Soal Baru</h2>
            <p>Buat soal, quiz, atau ujian baru</p>
        </div>
        <a href="soal.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Kembali ke Daftar Soal
        </a>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Form Tambah Soal</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="addSoalForm">
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
                    <button type="button" class="btn btn-secondary mb-3" onclick="tambahPertanyaan()">
                        <i class="bi bi-plus"></i> Tambah Pertanyaan
                    </button>
                    
                    <hr>
                    <div class="d-flex justify-content-between">
                        <a href="soal.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Batal
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Simpan Soal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function tambahPertanyaan() {
    const container = document.getElementById('pertanyaan-container');
    const existingItems = container.querySelectorAll('.pertanyaan-item');
    const nextIndex = existingItems.length; // Index berikutnya berdasarkan jumlah item yang ada
    
    const newItem = document.createElement('div');
    newItem.className = 'pertanyaan-item mb-4 p-3 border rounded';
    newItem.innerHTML = `
        <div class="row mb-2">
            <div class="col-md-8">
                <label class="form-label">Pertanyaan ${nextIndex + 1} <span class="text-danger">*</span></label>
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
                        <input type="radio" name="is_benar[${nextIndex}][0]" value="1">
                    </div>
                    <input type="text" class="form-control" name="pilihan[${nextIndex}][]" placeholder="Pilihan A">
                </div>
            </div>
            <div class="pilihan-item mb-2">
                <div class="input-group">
                    <div class="input-group-text">
                        <input type="radio" name="is_benar[${nextIndex}][1]" value="1">
                    </div>
                    <input type="text" class="form-control" name="pilihan[${nextIndex}][]" placeholder="Pilihan B">
                </div>
            </div>
            <div class="pilihan-item mb-2">
                <div class="input-group">
                    <div class="input-group-text">
                        <input type="radio" name="is_benar[${nextIndex}][2]" value="1">
                    </div>
                    <input type="text" class="form-control" name="pilihan[${nextIndex}][]" placeholder="Pilihan C">
                </div>
            </div>
            <div class="pilihan-item mb-2">
                <div class="input-group">
                    <div class="input-group-text">
                        <input type="radio" name="is_benar[${nextIndex}][3]" value="1">
                    </div>
                    <input type="text" class="form-control" name="pilihan[${nextIndex}][]" placeholder="Pilihan D">
                </div>
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-danger mt-2" onclick="this.closest('.pertanyaan-item').remove(); updatePertanyaanNumbers();">
            <i class="bi bi-trash"></i> Hapus Pertanyaan
        </button>
    `;
    container.appendChild(newItem);
    
    // Re-initialize toggle for new item
    const newSelect = newItem.querySelector('.jenis-jawaban');
    newSelect.addEventListener('change', function() {
        togglePilihan(this);
    });
}

function updatePertanyaanNumbers() {
    const container = document.getElementById('pertanyaan-container');
    const items = container.querySelectorAll('.pertanyaan-item');
    items.forEach((item, index) => {
        const label = item.querySelector('label');
        if (label) {
            label.textContent = `Pertanyaan ${index + 1} `;
            const span = document.createElement('span');
            span.className = 'text-danger';
            span.textContent = '*';
            label.appendChild(span);
        }
    });
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

