<?php
$page_title = 'Import Siswa';
require_once '../../config/session.php';
require_once '../../config/database.php';
requireRole(['kepala_sekolah']);

$conn = getConnection();
$sekolah_id = $_SESSION['sekolah_id'];
$message = '';
$import_result = null;

// Handle file upload and import
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'error:Error uploading file!';
    } elseif ($file['size'] > 5242880) { // 5MB max
        $message = 'error:File size terlalu besar! Maksimal 5MB.';
    } else {
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Check file extension
        if (!in_array($file_ext, ['csv', 'xls', 'xlsx'])) {
            $message = 'error:Format file tidak didukung! Gunakan CSV, XLS, atau XLSX.';
        } else {
            // Process file
            $import_result = processImport($conn, $sekolah_id, $file);
            
            if ($import_result['success']) {
                $message = 'success:' . $import_result['message'];
            } else {
                $message = 'error:' . $import_result['message'];
            }
        }
    }
}

function processImport($conn, $sekolah_id, $file) {
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    $temp_file = $file['tmp_name'];
    $header_keys = [];
    
    // For Excel files, inform user to convert to CSV first
    if (in_array($file_ext, ['xls', 'xlsx'])) {
        return [
            'success' => false,
            'message' => 'File Excel (.xls/.xlsx) belum didukung. Silakan export file Excel ke format CSV terlebih dahulu, kemudian upload file CSV tersebut.',
            'success_count' => 0,
            'error_count' => 0,
            'errors' => []
        ];
    }
    
    // Process CSV file
    if (($handle = fopen($temp_file, 'r')) !== false) {
        $line_number = 0;
        $headers = [];
        $header_keys = [];
        
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            $line_number++;
            
            // Skip empty rows
            if (empty(array_filter($data))) {
                continue;
            }
            
            // First row as headers
            if ($line_number == 1) {
                $headers = array_map('trim', $data);
                // Convert headers to lowercase for comparison
                $headers = array_map('strtolower', $headers);
                
                // Validate headers
                $required_headers = ['username', 'password', 'nama lengkap', 'kelas'];
                
                foreach ($required_headers as $req_header) {
                    $key = array_search(strtolower($req_header), $headers);
                    if ($key === false) {
                        // Try alternative names
                        if ($req_header == 'nama lengkap') {
                            $key = array_search('nama_lengkap', $headers);
                            if ($key === false) $key = array_search('nama', $headers);
                        } elseif ($req_header == 'kelas') {
                            $key = array_search('nama_kelas', $headers);
                        }
                    }
                    
                    if ($key === false) {
                        fclose($handle);
                        return [
                            'success' => false,
                            'message' => "Header '$req_header' tidak ditemukan! Pastikan file memiliki header: Username, Password, Nama Lengkap, Kelas, Email (opsional).",
                            'success_count' => 0,
                            'error_count' => 0,
                            'errors' => []
                        ];
                    }
                    
                    $header_keys[$req_header] = $key;
                }
                
                // Email is optional
                $email_key = array_search('email', $headers);
                if ($email_key !== false) {
                    $header_keys['email'] = $email_key;
                }
                
                continue;
            }
            
            // Process data rows
            $username = isset($data[$header_keys['username']]) ? trim($data[$header_keys['username']]) : '';
            $password = isset($data[$header_keys['password']]) ? trim($data[$header_keys['password']]) : '';
            $nama_lengkap = isset($data[$header_keys['nama lengkap']]) ? trim($data[$header_keys['nama lengkap']]) : '';
            $kelas_nama = isset($data[$header_keys['kelas']]) ? trim($data[$header_keys['kelas']]) : '';
            $email = (isset($header_keys['email']) && isset($data[$header_keys['email']])) ? trim($data[$header_keys['email']]) : '';
            
            // Validate required fields
            if (empty($username) || empty($password) || empty($nama_lengkap) || empty($kelas_nama)) {
                $error_count++;
                $errors[] = "Baris $line_number: Data tidak lengkap (Username, Password, Nama Lengkap, atau Kelas kosong)";
                continue;
            }
            
            // Validate email format if provided
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_count++;
                $errors[] = "Baris $line_number: Format email tidak valid ($email)";
                continue;
            }
            
            // Get kelas_id from nama_kelas
            $kelas_stmt = $conn->prepare("SELECT id FROM kelas WHERE nama_kelas = ? AND sekolah_id = ?");
            $kelas_stmt->bind_param("si", $kelas_nama, $sekolah_id);
            $kelas_stmt->execute();
            $kelas_result = $kelas_stmt->get_result();
            
            if ($kelas_result->num_rows == 0) {
                $error_count++;
                $errors[] = "Baris $line_number: Kelas '$kelas_nama' tidak ditemukan di sistem";
                $kelas_stmt->close();
                continue;
            }
            
            $kelas_data = $kelas_result->fetch_assoc();
            $kelas_id = $kelas_data['id'];
            $kelas_stmt->close();
            
            // Check if username already exists
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $check_stmt->bind_param("s", $username);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                $error_count++;
                $errors[] = "Baris $line_number: Username '$username' sudah digunakan";
                $check_stmt->close();
                continue;
            }
            $check_stmt->close();
            
            // Insert student
            // Hash password from CSV
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $insert_stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, email, role, sekolah_id, kelas_id) VALUES (?, ?, ?, ?, 'siswa', ?, ?)");
            $insert_stmt->bind_param("ssssii", $username, $hashed_password, $nama_lengkap, $email, $sekolah_id, $kelas_id);
            
            if ($insert_stmt->execute()) {
                $success_count++;
            } else {
                $error_count++;
                $errors[] = "Baris $line_number: Gagal menambahkan siswa - " . $conn->error;
            }
            
            $insert_stmt->close();
        }
        
        fclose($handle);
    } else {
        return [
            'success' => false,
            'message' => 'Gagal membaca file!',
            'success_count' => 0,
            'error_count' => 0,
            'errors' => []
        ];
    }
    
    // Build result message
    $result_message = "Import selesai: $success_count berhasil";
    if ($error_count > 0) {
        $result_message .= ", $error_count gagal";
    }
    
    return [
        'success' => $success_count > 0,
        'message' => $result_message,
        'success_count' => $success_count,
        'error_count' => $error_count,
        'errors' => $errors
    ];
}

// Get all classes for reference
$kelas = $conn->query("SELECT * FROM kelas WHERE sekolah_id = $sekolah_id ORDER BY tingkat ASC, nama_kelas ASC")->fetch_all(MYSQLI_ASSOC);

$conn->close();
require_once '../../includes/header.php';
?>

<?php if ($message): ?>
    <script>
        $(document).ready(function() {
            <?php 
            $msg = explode(':', $message, 2);
            if ($msg[0] == 'success') {
                echo "Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: '" . addslashes($msg[1]) . "',
                    confirmButtonText: 'OK'
                }).then(function() {
                    window.location.href = 'siswa.php';
                });";
            } else {
                echo "Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: '" . addslashes($msg[1]) . "',
                    confirmButtonText: 'OK'
                });";
            }
            ?>
        });
    </script>
<?php endif; ?>

<div class="page-header">
    <h2><i class="bi bi-upload"></i> Import Siswa dari CSV/Excel</h2>
    <p>Upload file CSV untuk mengimpor data siswa secara massal</p>
</div>

<div class="row">
    <div class="col-12 col-md-8">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-4">Format File</h5>
                
                <div class="alert alert-info">
                    <h6 class="alert-heading"><i class="bi bi-info-circle"></i> Format Header File CSV:</h6>
                    <p class="mb-0">File harus memiliki header dengan format berikut (urutan kolom bebas):</p>
                    <ul class="mb-0 mt-2">
                        <li><strong>A. Username</strong> (wajib)</li>
                        <li><strong>B. Password</strong> (wajib)</li>
                        <li><strong>C. Nama Lengkap</strong> (wajib)</li>
                        <li><strong>D. Kelas</strong> (wajib) - Gunakan nama kelas yang sudah ada di sistem</li>
                        <li><strong>E. Email</strong> (opsional)</li>
                    </ul>
                </div>
                
                <form method="POST" enctype="multipart/form-data" id="importForm">
                    <div class="mb-3">
                        <label for="file" class="form-label">Pilih File CSV/Excel <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="file" name="file" accept=".csv,.xls,.xlsx" required>
                        <small class="form-text text-muted">Format yang didukung: CSV, XLS, XLSX (Maksimal 5MB). <strong>Catatan:</strong> Untuk file Excel, silakan export ke CSV terlebih dahulu.</small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <strong><i class="bi bi-exclamation-triangle"></i> Catatan:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Password harus diisi di file CSV/Excel untuk setiap siswa</li>
                            <li>Pastikan nama kelas sudah ada di sistem sebelum melakukan import</li>
                            <li>Username harus unik (tidak boleh duplikat)</li>
                        </ul>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <a href="siswa.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Import Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-4">Daftar Kelas</h5>
                <p class="text-muted small mb-3">Gunakan nama kelas berikut saat membuat file CSV:</p>
                <div class="list-group">
                    <?php if (empty($kelas)): ?>
                        <div class="alert alert-warning">
                            <small>Tidak ada kelas. Silakan tambah kelas terlebih dahulu.</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($kelas as $k): ?>
                            <div class="list-group-item list-group-item-action">
                                <strong><?php echo htmlspecialchars($k['nama_kelas']); ?></strong>
                                <small class="text-muted d-block">Tingkat <?php echo $k['tingkat']; ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-body">
                <h5 class="card-title mb-3">Contoh File CSV</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Password</th>
                                <th>Nama Lengkap</th>
                                <th>Kelas</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>siswa01</td>
                                <td>password123</td>
                                <td>Budi Santoso</td>
                                <td>10 D</td>
                                <td>budi@email.com</td>
                            </tr>
                            <tr>
                                <td>siswa02</td>
                                <td>password456</td>
                                <td>Siti Nurhaliza</td>
                                <td>10 D</td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($import_result && !empty($import_result['errors'])): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Detail Error (<?php echo count($import_result['errors']); ?> error)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th width="50">#</th>
                                <th>Pesan Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($import_result['errors'] as $index => $error): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td class="text-danger"><?php echo htmlspecialchars($error); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
$(document).ready(function() {
    $('#importForm').on('submit', function(e) {
        var fileInput = $('#file')[0];
        
        if (fileInput.files.length === 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Silakan pilih file terlebih dahulu!',
                confirmButtonText: 'OK'
            });
            return false;
        }
        
        var file = fileInput.files[0];
        var fileSize = file.size / 1024 / 1024; // Size in MB
        
        if (fileSize > 5) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Ukuran file terlalu besar! Maksimal 5MB.',
                confirmButtonText: 'OK'
            });
            return false;
        }
        
        Swal.fire({
            title: 'Import Data?',
            text: 'Apakah Anda yakin ingin mengimport data dari file ini?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, Import!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading
                Swal.fire({
                    title: 'Memproses...',
                    text: 'Sedang memproses file, mohon tunggu...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Submit form
                this.submit();
            }
        });
        
        return false;
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>

