<?php
$page_title = 'Tambah Soal Ujian';
require_once '../../config/session.php';
require_once '../../config/database.php';
requireRole(['guru']);

$conn = getConnection();
$guru_id = $_SESSION['user_id'];
$message = '';

// Get tipe ujian from URL parameter
$tipe_ujian = $_GET['tipe'] ?? 'uts';
if (!in_array($tipe_ujian, ['uts', 'uas'])) {
    $tipe_ujian = 'uts';
}

$tipe_label = strtoupper($tipe_ujian);

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
        foreach ($_POST['pertanyaan'] as $index => $pertanyaan) {
            if (!empty(trim($pertanyaan))) {
                $pertanyaan_count++;
                
                // Validasi untuk pilihan ganda: harus ada minimal 2 pilihan dan jawaban benar harus dipilih
                $jenis_jawaban = $_POST['jenis_jawaban'][$index] ?? 'pilihan_ganda';
                if ($jenis_jawaban == 'pilihan_ganda') {
                    $pilihan_count = 0;
                    $has_benar = false;
                    
                    if (isset($_POST['pilihan'][$index]) && is_array($_POST['pilihan'][$index])) {
                        foreach ($_POST['pilihan'][$index] as $pilihan_index => $pilihan_text) {
                            if (!empty(trim($pilihan_text))) {
                                $pilihan_count++;
                            }
                        }
                        // Check if jawaban benar sudah dipilih
                        if (isset($_POST['is_benar'][$index]) && $_POST['is_benar'][$index] !== '') {
                            $has_benar = true;
                        }
                    }
                    
                    if ($pilihan_count < 2) {
                        $errors[] = 'Pertanyaan ' . ($pertanyaan_count) . ': Minimal harus ada 2 pilihan jawaban yang diisi';
                    }
                    
                    if (!$has_benar) {
                        $errors[] = 'Pertanyaan ' . ($pertanyaan_count) . ': Harus dipilih jawaban yang benar (centang salah satu pilihan)';
                    }
                }
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
        
        // Untuk UTS/UAS, tanggal_mulai dan tanggal_selesai diatur oleh akademik melalui jadwal
        $tanggal_mulai = null;
        $tanggal_selesai = null;
        
        // Status selalu draft untuk UTS/UAS, akan diaktifkan setelah dijadwalkan oleh akademik
        $status = 'draft';
        
        // Check if tipe_ujian column exists
        $check_column = $conn->query("SHOW COLUMNS FROM soal LIKE 'tipe_ujian'");
        if ($check_column && $check_column->num_rows > 0) {
            $stmt = $conn->prepare("INSERT INTO soal (mata_pelajaran_id, guru_id, judul, deskripsi, jenis, tipe_ujian, waktu_pengerjaan, tanggal_mulai, tanggal_selesai, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissssisss", $mata_pelajaran_id, $guru_id, $judul, $deskripsi, $jenis, $tipe_ujian, $waktu_pengerjaan, $tanggal_mulai, $tanggal_selesai, $status);
        } else {
            // Fallback jika kolom belum ada
            $stmt = $conn->prepare("INSERT INTO soal (mata_pelajaran_id, guru_id, judul, deskripsi, jenis, waktu_pengerjaan, tanggal_mulai, tanggal_selesai, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssisss", $mata_pelajaran_id, $guru_id, $judul, $deskripsi, $jenis, $waktu_pengerjaan, $tanggal_mulai, $tanggal_selesai, $status);
        }
    
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
                    $jawaban_benar_index = isset($_POST['is_benar'][$index]) ? intval($_POST['is_benar'][$index]) : -1;
                    $urutan_pilihan_counter = 1;
                    foreach ($_POST['pilihan'][$index] as $pilihan_index => $pilihan_text) {
                        if (!empty(trim($pilihan_text))) {
                            // Cek apakah pilihan ini adalah jawaban yang benar
                            $is_benar = ($pilihan_index == $jawaban_benar_index) ? 1 : 0;
                            
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
        header('Location: soal_ujian.php?tipe=' . $tipe_ujian . '&success=1');
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
            <h2>Tambah Soal <?php echo $tipe_label; ?></h2>
            <p>Buat soal <?php echo $tipe_label; ?> untuk mata pelajaran Anda</p>
        </div>
        <a href="soal_ujian.php?tipe=<?php echo $tipe_ujian; ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Kembali ke Daftar Soal <?php echo $tipe_label; ?>
        </a>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Form Tambah Soal <?php echo $tipe_label; ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" id="addSoalForm" onsubmit="return validateForm()">
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
                        <div class="col-md-6">
                            <label class="form-label">Waktu Pengerjaan (menit)</label>
                            <input type="number" class="form-control" name="waktu_pengerjaan" value="90" min="1">
                            <small class="text-muted">Waktu pengerjaan untuk ujian ini</small>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> <strong>Catatan:</strong> Setelah soal dibuat, akademik akan mengatur jadwal ujian untuk kelas-kelas yang akan mengikuti ujian ini.
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
                                            <input type="radio" name="is_benar[0]" value="0">
                                        </div>
                                        <input type="text" class="form-control" name="pilihan[0][]" placeholder="Pilihan A">
                                    </div>
                                </div>
                                <div class="pilihan-item mb-2">
                                    <div class="input-group">
                                        <div class="input-group-text">
                                            <input type="radio" name="is_benar[0]" value="1">
                                        </div>
                                        <input type="text" class="form-control" name="pilihan[0][]" placeholder="Pilihan B">
                                    </div>
                                </div>
                                <div class="pilihan-item mb-2">
                                    <div class="input-group">
                                        <div class="input-group-text">
                                            <input type="radio" name="is_benar[0]" value="2">
                                        </div>
                                        <input type="text" class="form-control" name="pilihan[0][]" placeholder="Pilihan C">
                                    </div>
                                </div>
                                <div class="pilihan-item mb-2">
                                    <div class="input-group">
                                        <div class="input-group-text">
                                            <input type="radio" name="is_benar[0]" value="3">
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
                        <a href="soal_ujian.php?tipe=<?php echo $tipe_ujian; ?>" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Batal
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Simpan Soal <?php echo $tipe_label; ?>
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
    const form = document.getElementById('addSoalForm');
    const errors = [];
    
    const mataPelajaran = document.getElementById('mata_pelajaran_id');
    if (!mataPelajaran.value) {
        errors.push('Mata pelajaran harus dipilih');
        mataPelajaran.classList.add('is-invalid');
    } else {
        mataPelajaran.classList.remove('is-invalid');
    }
    
    const judul = document.querySelector('input[name="judul"]');
    if (!judul.value.trim()) {
        errors.push('Judul soal harus diisi');
        judul.classList.add('is-invalid');
    } else {
        judul.classList.remove('is-invalid');
    }
    
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
                    let pilihanCount = 0;
                    
                    pilihanInputs.forEach(input => {
                        if (input.value.trim()) {
                            hasPilihan = true;
                            pilihanCount++;
                        }
                    });
                    
                    if (!hasPilihan || pilihanCount < 2) {
                        errors.push('Pertanyaan ' + (index + 1) + ': Minimal harus ada 2 pilihan jawaban yang diisi');
                        item.classList.add('border-danger');
                    } else {
                        item.classList.remove('border-danger');
                    }
                    
                    // Check if jawaban benar sudah dipilih
                    const benarRadios = pilihanContainer.querySelectorAll('input[type="radio"]');
                    let hasBenar = false;
                    let checkedRadioIndex = -1;
                    
                    benarRadios.forEach((radio, radioIndex) => {
                        if (radio.checked) {
                            hasBenar = true;
                            checkedRadioIndex = radioIndex;
                            // Verify that the checked radio has a filled pilihan
                            const correspondingInput = pilihanInputs[radioIndex];
                            if (!correspondingInput || !correspondingInput.value.trim()) {
                                hasBenar = false; // Radio checked but pilihan is empty
                            }
                        }
                    });
                    
                    if (!hasBenar) {
                        errors.push('Pertanyaan ' + (index + 1) + ': Harus dipilih jawaban yang benar (centang salah satu pilihan)');
                        item.classList.add('border-danger');
                        // Highlight the radio buttons area
                        const radioGroup = pilihanContainer.querySelectorAll('.input-group-text');
                        radioGroup.forEach(group => {
                            group.style.border = '2px solid #dc3545';
                        });
                    } else {
                        item.classList.remove('border-danger');
                        // Remove highlight
                        const radioGroup = pilihanContainer.querySelectorAll('.input-group-text');
                        radioGroup.forEach(group => {
                            group.style.border = '';
                        });
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
                        <input type="radio" name="is_benar[${nextIndex}]" value="0">
                    </div>
                    <input type="text" class="form-control" name="pilihan[${nextIndex}][]" placeholder="Pilihan A">
                </div>
            </div>
            <div class="pilihan-item mb-2">
                <div class="input-group">
                    <div class="input-group-text">
                        <input type="radio" name="is_benar[${nextIndex}]" value="1">
                    </div>
                    <input type="text" class="form-control" name="pilihan[${nextIndex}][]" placeholder="Pilihan B">
                </div>
            </div>
            <div class="pilihan-item mb-2">
                <div class="input-group">
                    <div class="input-group-text">
                        <input type="radio" name="is_benar[${nextIndex}]" value="2">
                    </div>
                    <input type="text" class="form-control" name="pilihan[${nextIndex}][]" placeholder="Pilihan C">
                </div>
            </div>
            <div class="pilihan-item mb-2">
                <div class="input-group">
                    <div class="input-group-text">
                        <input type="radio" name="is_benar[${nextIndex}]" value="3">
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

document.querySelectorAll('.jenis-jawaban').forEach(select => {
    select.addEventListener('change', function() {
        togglePilihan(this);
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>

