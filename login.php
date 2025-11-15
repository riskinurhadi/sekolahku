<?php
require_once 'config/database.php';
require_once 'config/session.php';

// Jika sudah login, redirect ke dashboard sesuai role
if (isLoggedIn()) {
    redirectByRole($_SESSION['user_role']);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_nama'] = $user['nama_lengkap'];
                $_SESSION['sekolah_id'] = $user['sekolah_id'];
                
                redirectByRole($user['role']);
            } else {
                $error = 'Username atau password salah!';
            }
        } else {
            $error = 'Username atau password salah!';
        }
        
        $stmt->close();
        $conn->close();
    } else {
        $error = 'Silakan isi username dan password!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - MTsN 1 Way Kanan</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        /* Login page styles - Matching dashboard color scheme */
        body.login-page {
            margin: 0 !important;
            padding: 0 !important;
            overflow-x: hidden;
            background: #f5f7fa !important;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        body.login-page .login-container {
            min-height: 100vh !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            background: #f5f7fa !important;
            padding: 20px !important;
            width: 100% !important;
            box-sizing: border-box !important;
            margin: 0 !important;
        }
        
        body.login-page .login-card {
            background: #ffffff !important;
            border-radius: 16px !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1) !important;
            width: 100% !important;
            max-width: 1100px !important;
            display: flex !important;
            flex-direction: row !important;
            overflow: hidden !important;
            min-height: 650px !important;
            margin: 0 auto !important;
        }
        
        /* Left Panel - Purple/Indigo gradient matching dashboard */
        .login-left-panel {
            flex: 1 !important;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 50%, #4338ca 100%) !important;
            padding: 60px 50px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        
        .login-right-panel {
            flex: 1 !important;
            background: #ffffff !important;
            padding: 60px 50px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        
        .login-logo-wrapper {
            display: flex !important;
            align-items: flex-start !important;
            justify-content: center !important;
            gap: 30px !important;
            margin-bottom: 50px !important;
        }
        
        /* Logo Kemenag - Purple to match dashboard */
        .logo-kemenag-shape {
            width: 100px !important;
            height: 100px !important;
            background: #6366f1 !important;
            clip-path: polygon(50% 0%, 100% 38%, 82% 100%, 18% 100%, 0% 38%) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3) !important;
        }
        
        .logo-kemenag-inner .bi-book-fill {
            color: #fbbf24 !important;
        }
        
        .logo-kemenag-inner .bi-star-fill {
            color: #fbbf24 !important;
        }
        
        /* Logo Way Kanan - Keep blue but adjust */
        .logo-waykanan-shape {
            width: 100px !important;
            height: 100px !important;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%) !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3) !important;
        }
        
        .login-system-title {
            font-size: 36px !important;
            font-weight: 700 !important;
            color: #ffffff !important;
            margin-bottom: 8px !important;
        }
        
        .login-system-subtitle {
            font-size: 28px !important;
            font-weight: 600 !important;
            color: #ffffff !important;
            margin-bottom: 35px !important;
        }
        
        .login-form-title {
            color: #1e293b !important;
            font-weight: 700 !important;
            font-size: 32px !important;
            margin-bottom: 8px !important;
        }
        
        .login-form-subtitle {
            color: #64748b !important;
            font-size: 15px !important;
            margin-bottom: 35px !important;
        }
        
        /* Input focus - Purple to match dashboard */
        .login-form .input-group:focus-within .input-group-text {
            border-color: #6366f1 !important;
            background: #eef2ff !important;
            color: #6366f1 !important;
        }
        
        .login-form .form-control:focus {
            border-color: #6366f1 !important;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1) !important;
            outline: none !important;
        }
        
        /* Button - Purple gradient matching dashboard */
        .btn-login {
            width: 100% !important;
            padding: 14px 20px !important;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%) !important;
            border: none !important;
            border-radius: 10px !important;
            color: #ffffff !important;
            font-weight: 600 !important;
            font-size: 16px !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
        }
        
        .btn-login:hover {
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%) !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.3) !important;
        }
        
        .login-footer {
            margin-top: 35px !important;
            padding-top: 20px !important;
            border-top: 1px solid #e9ecef !important;
            text-align: center !important;
            color: #64748b !important;
            font-size: 13px !important;
        }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <!-- Left Panel: System Info & Logo -->
            <div class="login-left-panel">
                <div class="login-logo-section">
                    <div class="login-logo-wrapper">
                        <!-- Logo Kementerian Agama -->
                        <div class="logo-kemenag">
                            <div class="logo-kemenag-shape">
                                <div class="logo-kemenag-inner">
                                    <i class="bi bi-book-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                </div>
                            </div>
                            <div class="logo-kemenag-text">IKHLAS BERAMAL</div>
                        </div>
                        
                        <!-- Logo Way Kanan -->
                        <div class="logo-waykanan">
                            <div class="logo-waykanan-shape">
                                <div class="logo-waykanan-inner">
                                    <i class="bi bi-shield-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                </div>
                            </div>
                            <div class="logo-waykanan-text">WAY KANAN</div>
                        </div>
                    </div>
                    
                    <h1 class="login-system-title">Sistem Informasi</h1>
                    <h2 class="login-system-subtitle">MTsN 1 Way Kanan</h2>
                    <p class="login-system-description">
                        Akses ke dasbor admin untuk mengelola konten, berita, dan informasi penting lainnya dengan mudah.
                    </p>
                </div>
            </div>
            
            <!-- Right Panel: Login Form -->
            <div class="login-right-panel">
                <div class="login-form-wrapper">
                    <h2 class="login-form-title">Login Admin</h2>
                    <p class="login-form-subtitle">Selamat datang, silakan masuk.</p>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" class="login-form">
                        <div class="form-group">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-person"></i>
                                </span>
                                <input type="text" class="form-control" id="username" name="username" placeholder="Username" required autofocus>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-login">
                            Login
                        </button>
                    </form>
                    
                    <div class="login-footer">
                        <small>© 2025 MTs Negeri 1 Way Kanan</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
