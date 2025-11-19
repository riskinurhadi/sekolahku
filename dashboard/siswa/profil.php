<?php
$page_title = 'Profil Saya';
require_once '../../config/session.php';
requireRole(['siswa']);
require_once '../../includes/header.php';

$conn = getConnection();
$user_id = $_SESSION['user_id'];
$message = '';

// Check if foto_profil column exists, if not add it
$column_check = $conn->query("SHOW COLUMNS FROM users LIKE 'foto_profil'");
if ($column_check->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN foto_profil VARCHAR(255) NULL AFTER email");
}

// Create uploads/profil directory if not exists
$upload_dir = __DIR__ . '/../../uploads/profil/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] == 0) {
    $file = $_FILES['foto_profil'];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'profil_' . $user_id . '_' . time() . '.' . $extension;
        $filepath = $upload_dir . $filename;
        
        // Get old photo to delete
        $stmt = $conn->prepare("SELECT foto_profil FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $old_photo = $stmt->get_result()->fetch_assoc()['foto_profil'];
        $stmt->close();
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Delete old photo if exists
            if ($old_photo && file_exists(__DIR__ . '/../../uploads/profil/' . $old_photo)) {
                unlink(__DIR__ . '/../../uploads/profil/' . $old_photo);
            }
            
            // Update database
            $stmt = $conn->prepare("UPDATE users SET foto_profil = ? WHERE id = ?");
            $stmt->bind_param("si", $filename, $user_id);
            if ($stmt->execute()) {
                $message = 'success:Foto profil berhasil diupdate!';
            } else {
                $message = 'error:Gagal mengupdate foto profil!';
            }
            $stmt->close();
        } else {
            $message = 'error:Gagal mengupload foto!';
        }
    } else {
        $message = 'error:Format file tidak didukung atau ukuran file terlalu besar (maks 5MB)!';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nama_lengkap'])) {
    $nama_lengkap = $_POST['nama_lengkap'];
    $email = $_POST['email'] ?? '';
    
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET nama_lengkap = ?, email = ?, password = ? WHERE id = ?");
        $stmt->bind_param("sssi", $nama_lengkap, $email, $password, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET nama_lengkap = ?, email = ? WHERE id = ?");
        $stmt->bind_param("ssi", $nama_lengkap, $email, $user_id);
    }
    
    if ($stmt->execute()) {
        $_SESSION['user_nama'] = $nama_lengkap;
        if (empty($message)) {
            $message = 'success:Profil berhasil diupdate!';
        }
    } else {
        if (empty($message)) {
            $message = 'error:Gagal mengupdate profil!';
        }
    }
    $stmt->close();
}

// Get user info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

// Get photo path
$foto_profil = '';
if (!empty($user_data['foto_profil']) && file_exists(__DIR__ . '/../../uploads/profil/' . $user_data['foto_profil'])) {
    $foto_profil = getBasePath() . 'uploads/profil/' . $user_data['foto_profil'];
}
?>

<style>
.profile-photo-section {
    text-align: center;
    padding: 24px;
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid #e5e7eb;
    margin-bottom: 24px;
}

.profile-photo-wrapper {
    position: relative;
    display: inline-block;
    margin-bottom: 20px;
}

.profile-photo {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #e5e7eb;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.profile-photo-wrapper:hover .profile-photo {
    border-color: #1e3a8a;
    box-shadow: 0 6px 20px rgba(30, 58, 138, 0.2);
}

.profile-photo-placeholder {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 48px;
    font-weight: 700;
    border: 4px solid #e5e7eb;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    margin: 0 auto;
}

.photo-upload-btn {
    position: relative;
    display: inline-block;
    cursor: pointer;
    margin-top: 12px;
}

.photo-upload-btn input[type="file"] {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
}

.photo-upload-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: #1e3a8a;
    color: #ffffff;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s ease;
    cursor: pointer;
}

.photo-upload-label:hover {
    background: #1e40af;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(30, 58, 138, 0.3);
}

.profile-info-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid #e5e7eb;
    margin-bottom: 24px;
}

.profile-info-card .card-header {
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 16px;
    margin-bottom: 24px;
}

.profile-info-card .card-header h5 {
    font-size: 18px;
    font-weight: 700;
    color: #1e3a8a;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-label {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 8px;
}

.form-control {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 10px 16px;
    transition: all 0.2s ease;
}

.form-control:focus {
    border-color: #1e3a8a;
    box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
}

.btn-primary {
    background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
    border: none;
    border-radius: 8px;
    padding: 12px 24px;
    font-weight: 600;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(30, 58, 138, 0.3);
}
</style>

<?php if ($message): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php 
            $msg = explode(':', $message);
            if ($msg[0] == 'success') {
                echo "if (typeof Swal !== 'undefined') {";
                echo "    Swal.fire({icon: 'success', title: 'Berhasil!', text: '" . addslashes($msg[1]) . "', timer: 3000});";
                echo "} else {";
                echo "    alert('" . addslashes($msg[1]) . "');";
                echo "}";
            } else {
                echo "if (typeof Swal !== 'undefined') {";
                echo "    Swal.fire({icon: 'error', title: 'Error', text: '" . addslashes($msg[1]) . "', timer: 3000});";
                echo "} else {";
                echo "    alert('" . addslashes($msg[1]) . "');";
                echo "}";
            }
            ?>
        });
    </script>
<?php endif; ?>

<div class="page-header">
    <h2>Profil Saya</h2>
    <p>Kelola data pribadi dan foto profil Anda</p>
</div>

<div class="row">
    <!-- Photo Section -->
    <div class="col-md-4">
        <div class="profile-photo-section">
            <div class="profile-photo-wrapper">
                <?php if ($foto_profil): ?>
                    <img src="<?php echo htmlspecialchars($foto_profil); ?>" alt="Foto Profil" class="profile-photo" id="profilePhotoPreview">
                <?php else: ?>
                    <div class="profile-photo-placeholder" id="profilePhotoPreview">
                        <?php echo strtoupper(substr($user_data['nama_lengkap'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
            <form method="POST" enctype="multipart/form-data" id="photoUploadForm">
                <div class="photo-upload-btn">
                    <input type="file" name="foto_profil" id="foto_profil" accept="image/jpeg,image/jpg,image/png,image/gif" onchange="previewPhoto(this)">
                    <label for="foto_profil" class="photo-upload-label">
                        <i class="bi bi-camera"></i> <?php echo $foto_profil ? 'Ganti Foto' : 'Upload Foto'; ?>
                    </label>
                </div>
                <button type="submit" class="btn btn-primary mt-2" style="display: none;" id="savePhotoBtn">
                    <i class="bi bi-save"></i> Simpan Foto
                </button>
            </form>
            <p class="text-muted small mt-3 mb-0">Format: JPG, PNG, GIF<br>Maksimal: 5MB</p>
        </div>
    </div>
    
    <!-- Profile Info Section -->
    <div class="col-md-8">
        <div class="profile-info-card">
            <div class="card-header">
                <h5><i class="bi bi-person-circle"></i> Informasi Profil</h5>
            </div>
            <form method="POST" id="profilForm">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['username']); ?>" disabled>
                        <small class="text-muted">Username tidak dapat diubah</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_lengkap" value="<?php echo htmlspecialchars($user_data['nama_lengkap']); ?>" required>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Password Baru</label>
                        <input type="password" class="form-control" name="password" placeholder="Kosongkan jika tidak ingin mengubah">
                        <small class="text-muted">Kosongkan jika tidak ingin mengubah password</small>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Role</label>
                        <input type="text" class="form-control" value="Siswa" disabled>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Simpan Perubahan
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function previewPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('profilePhotoPreview');
            if (preview.tagName === 'IMG') {
                preview.src = e.target.result;
            } else {
                // Replace placeholder with image
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'profile-photo';
                img.id = 'profilePhotoPreview';
                img.alt = 'Foto Profil';
                preview.parentNode.replaceChild(img, preview);
            }
            document.getElementById('savePhotoBtn').style.display = 'inline-block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Handle photo form submission
document.getElementById('photoUploadForm').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('foto_profil');
    if (!fileInput.files || !fileInput.files[0]) {
        e.preventDefault();
        if (typeof Swal !== 'undefined') {
            Swal.fire({icon: 'warning', title: 'Peringatan', text: 'Pilih foto terlebih dahulu!'});
        } else {
            alert('Pilih foto terlebih dahulu!');
        }
        return false;
    }
    
    const file = fileInput.files[0];
    const maxSize = 5 * 1024 * 1024; // 5MB
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    
    if (!allowedTypes.includes(file.type)) {
        e.preventDefault();
        if (typeof Swal !== 'undefined') {
            Swal.fire({icon: 'error', title: 'Error', text: 'Format file tidak didukung! Gunakan JPG, PNG, atau GIF.'});
        } else {
            alert('Format file tidak didukung! Gunakan JPG, PNG, atau GIF.');
        }
        return false;
    }
    
    if (file.size > maxSize) {
        e.preventDefault();
        if (typeof Swal !== 'undefined') {
            Swal.fire({icon: 'error', title: 'Error', text: 'Ukuran file terlalu besar! Maksimal 5MB.'});
        } else {
            alert('Ukuran file terlalu besar! Maksimal 5MB.');
        }
        return false;
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
