<?php
$page_title = 'Tambah Soal UTS/UAS';
require_once '../../config/session.php';
require_once '../../config/database.php';
requireRole(['guru']);

$conn = getConnection();
$guru_id = $_SESSION['user_id'];
$message = '';
$tipe_ujian = $_GET['tipe'] ?? 'uts'; // uts atau uas

// Validasi tipe ujian
if (!in_array($tipe_ujian, ['uts', 'uas'])) {
    $tipe_ujian = 'uts';
}

// Get mata pelajaran for dropdown
$mata_pelajaran = $conn->query("SELECT * FROM mata_pelajaran WHERE guru_id = $guru_id ORDER BY nama_pelajaran")->fetch_all(MYSQLI_ASSOC);

// Handle form submission BEFORE header output
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validasi field required
    $errors = [];
    
    if (empty($_POST['mata_pelajaran_id'])) {
        $errors[] = 'Mata pelajaran harus dipilih';
    }
    if (empty(trim($_POST['judul']))) {
        $errors[] = 'Judul soal harus diisi';
    }
    if (empty($_POST['jenis'])) {
        $errors[] = 'Jenis soal harus dipilih';
    }
    
    // Validasi pertanyaan minimal 1
    $pertanyaan_count = 0;
    if (isset($_POST['pertanyaan']) && is_array($_POST['pertanyaan'])) {
        foreach ($_POST['pertanyaan'] as $pertanyaan) {
            if (!empty(trim($pertanyaan))) {
                $pertanyaan_count++;
            }
        }
    }
    if ($pertanyaan_count == 0) {
        $errors[] = 'Minimal harus ada 1 pertanyaan yang diisi';
    }
    
    // Jika ada error, set message dan skip insert
    if (!empty($errors)) {
        $message = 'error:' . implode(', ', $errors);
    } else {
        $mata_pelajaran_id = $_POST['mata_pelajaran_id'];
        $judul = trim($_POST['judul']);
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $jenis = $_POST['jenis'];
        $waktu_pengerjaan = !empty($_POST['waktu_pengerjaan']) ? (int)$_POST['waktu_pengerjaan'] : 60;
        $tipe_ujian_post = $_POST['tipe_ujian'] ?? 'uts';
        
        // Untuk UTS/UAS, tanggal akan diatur oleh akademik, jadi set NULL
        $tanggal_mulai = null;
        $tanggal_selesai = null;
        
        // Status selalu draft karena perlu jadwal dari akademik
        $status = 'draft';
        
        $stmt = $conn->prepare("INSERT INTO soal (mata_pelajaran_id, guru_id, judul, deskripsi, jenis, tipe_ujian, waktu_pengerjaan, tanggal_mulai, tanggal_selesai, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissssisss", $mata_pelajaran_id, $guru_id, $judul, $deskripsi, $jenis, $tipe_ujian_post, $waktu_pengerjaan, $tanggal_mulai, $tanggal_selesai, $status);
    
        if ($stmt->execute()) {
            $soal_id = $stmt->insert_id;
            $stmt->close();
            
            // Handle item soal
            if (isset($_POST['pertanyaan']) && is_array($_POST['pertanyaan'])) {
                $urutan_counter = 1;
                
                foreach ($_POST['pertanyaan'] as $index => $pertanyaan) {
                    if (empty(trim($pertanyaan))) {
                        continue;
                    }
                    
                    $jenis_jawaban = $_POST['jenis_jawaban'][$index] ?? 'pilihan_ganda';
                    $poin = $_POST['poin'][$index] ?? 1;
                    
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
                    
                    $urutan_counter++;
                }
            }
            
            $conn->close();
            header('Location: soal.php?success=1&msg=' . urlencode('Soal ' . strtoupper($tipe_ujian_post) . ' berhasil dibuat!'));
            exit();
        } else {
            $message = 'error:Gagal menambahkan soal!';
        }
    }
}

$conn->close();
require_once '../../includes/header.php';
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
            <h2>Tambah Soal <?php echo strtoupper($tipe_ujian); ?></h2>
            <p>Buat soal <?php echo strtoupper($tipe_ujian); ?> untuk mata pelajaran Anda</p>
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
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Form Tambah Soal <?php echo strtoupper($tipe_ujian); ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" id="addUjianForm" onsubmit="return validateForm()">
                    <input type="hidden" name="tipe_ujian" value="<?php echo $tipe_ujian; ?>">
                    
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
                            <input type="text" class="form-control" name="judul" placeholder="Contoh: Soal UTS Matematika Kelas X" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="form-control" name="deskripsi" rows="2" placeholder="Deskripsi soal (opsional)"></textarea>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Waktu Pengerjaan (menit) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="waktu_pengerjaan" value="90" min="1" required>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> <strong>Catatan:</strong> Setelah soal dibuat, jadwal ujian akan diatur oleh bagian akademik. Status soal akan otomatis menjadi "Draft" sampai jadwal ditentukan.
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
// Validasi form sebelum submit
function validateForm() {
    const form = document.getElementById('addUjianForm');
    const errors = [];
    
    // Validasi mata pelajaran
    const mataPelajaran = document.getElementById('mata_pelajaran_id');
    if (!mataPelajaran.value) {
        errors.push('Mata pelajaran harus dipilih');
        mataPelajaran.classList.add('is-invalid');
    } else {
        mataPelajaran.classList.remove('is-invalid');
    }
    
    // Validasi judul
    const judul = document.querySelector('input[name="judul"]');
    if (!judul.value.trim()) {
        errors.push('Judul soal harus diisi');
        judul.classList.add('is-invalid');
    } else {
        judul.classList.remove('is-invalid');
    }
    
    // Validasi pertanyaan minimal 1
    const pertanyaanInputs = document.querySelectorAll('textarea[name="pertanyaan[]"]');
    let pertanyaanCount = 0;
    pertanyaanInputs.forEach(input => {
        if (input.value.trim()) {
            pertanyaanCount++;
            input.classList.remove('is-invalid');
        }
    });
    
    if (pertanyaanCount === 0) {
        errors.push('Minimal harus ada 1 pertanyaan yang diisi');
        pertanyaanInputs.forEach(input => {
            input.classList.add('is-invalid');
        });
    }
    
    // Validasi pilihan jawaban untuk pilihan ganda
    const pertanyaanItems = document.querySelectorAll('.pertanyaan-item');
    pertanyaanItems.forEach((item, index) => {
        const pertanyaanText = item.querySelector('textarea[name="pertanyaan[]"]');
        if (pertanyaanText && pertanyaanText.value.trim()) {
            const jenisJawaban = item.querySelector('select[name="jenis_jawaban[]"]');
            if (jenisJawaban && jenisJawaban.value === 'pilihan_ganda') {
                const pilihanContainer = item.querySelector('.pilihan-container');
                if (pilihanContainer) {
                    const pilihanInputs = pilihanContainer.querySelectorAll('input[type="text"][name*="pilihan"]');
                    let hasPilihan = false;
                    
                    pilihanInputs.forEach(input => {
                        if (input.value.trim()) {
                            hasPilihan = true;
                        }
                    });
                    
                    if (!hasPilihan) {
                        errors.push('Pertanyaan ' + (index + 1) + ': Pilihan jawaban harus diisi untuk pilihan ganda');
                    }
                    
                    const benarRadios = pilihanContainer.querySelectorAll('input[type="radio"]');
                    let hasBenar = false;
                    benarRadios.forEach(radio => {
                        if (radio.checked) {
                            hasBenar = true;
                        }
                    });
                    
                    if (!hasBenar) {
                        errors.push('Pertanyaan ' + (index + 1) + ': Harus dipilih jawaban yang benar');
                    }
                }
            }
        }
    });
    
    if (errors.length > 0) {
        showError(errors.join('<br>'));
        return false;
    }
    
    return true;
}

function tambahPertanyaan() {
    const container = document.getElementById('pertanyaan-container');
    const existingItems = container.querySelectorAll('.pertanyaan-item');
    const nextIndex = existingItems.length;
    
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
            label.innerHTML = `Pertanyaan ${index + 1} <span class="text-danger">*</span>`;
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

