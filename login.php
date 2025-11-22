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
    <title>Login - Sekolahku</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        /* Login page styles - Matching dashboard color scheme with glassmorphism */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1e3a8a 100%) !important;
            background-attachment: fixed !important;
            background-size: cover !important;
            background-position: center center !important;
            background-repeat: no-repeat !important;
            min-height: 100vh !important;
            width: 100% !important;
        }
        
        body.login-page {
            margin: 0 !important;
            padding: 0 !important;
            overflow-x: hidden;
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1e3a8a 100%) !important;
            background-attachment: fixed !important;
            background-size: cover !important;
            background-position: center center !important;
            background-repeat: no-repeat !important;
            min-height: 100vh !important;
            width: 100% !important;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            position: relative;
        }
        
        body.login-page::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1e3a8a 100%);
            background-attachment: fixed;
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
            z-index: -1;
            pointer-events: none;
        }
        
        body.login-page .login-container {
            min-height: 100vh !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            background: transparent !important;
            padding: 20px !important;
            width: 100% !important;
            box-sizing: border-box !important;
            margin: 0 !important;
            position: relative;
            z-index: 1;
        }
        
        body.login-page .login-card {
            background: #ffffff !important;
            border-radius: 24px !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12), 
                        0 4px 20px rgba(0, 0, 0, 0.08) !important;
            border: 1px solid #e5e7eb !important;
            width: 100% !important;
            max-width: 1100px !important;
            display: flex !important;
            flex-direction: row !important;
            overflow: hidden !important;
            min-height: 650px !important;
            margin: 0 auto !important;
            position: relative !important;
        }
        
        /* Left Panel - Blue solid background */
        .login-left-panel {
            flex: 1 !important;
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1e3a8a 100%) !important;
            padding: 60px 50px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            position: relative !important;
            overflow: hidden !important;
        }
        
        .login-left-panel::before {
            content: '' !important;
            position: absolute !important;
            top: -50% !important;
            right: -50% !important;
            width: 200% !important;
            height: 200% !important;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%) !important;
            pointer-events: none !important;
        }
        
        .login-right-panel {
            flex: 1 !important;
            background: #ffffff !important;
            padding: 60px 50px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            position: relative !important;
        }
        
        .login-logo-section {
            position: relative !important;
            z-index: 1 !important;
            width: 100% !important;
            text-align: center !important;
        }
        
        .login-form-wrapper {
            width: 100%;
            max-width: 400px;
        }
        
        /* Logo Kemenag - Soft purple to match dashboard */
        .logo-kemenag-shape {
            width: 100px !important;
            height: 100px !important;
            background: rgba(255, 255, 255, 0.2) !important;
            backdrop-filter: blur(10px) !important;
            -webkit-backdrop-filter: blur(10px) !important;
            clip-path: polygon(50% 0%, 100% 38%, 82% 100%, 18% 100%, 0% 38%) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.3) !important;
            border: 1px solid rgba(255, 255, 255, 0.3) !important;
        }
        
        .logo-kemenag-inner {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            gap: 2px !important;
        }
        
        .logo-kemenag-inner .bi-book-fill {
            color: #fbbf24 !important;
            font-size: 24px !important;
        }
        
        .logo-kemenag-inner .bi-star-fill {
            color: #fbbf24 !important;
            font-size: 16px !important;
        }
        
        /* Logo Sekolahku - Soft purple with glass effect */
        .logo-waykanan-shape {
            width: 100px !important;
            height: 100px !important;
            background: rgba(255, 255, 255, 0.2) !important;
            backdrop-filter: blur(10px) !important;
            -webkit-backdrop-filter: blur(10px) !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.3) !important;
            border: 1px solid rgba(255, 255, 255, 0.3) !important;
        }
        
        .logo-waykanan-inner .bi-mortarboard-fill {
            color: #ffffff !important;
            font-size: 40px !important;
        }
        
        .logo-kemenag-text,
        .logo-waykanan-text {
            color: rgba(255, 255, 255, 0.95) !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            text-align: center !important;
            margin-top: 8px !important;
            letter-spacing: 1px !important;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2) !important;
        }
        
        .login-system-title {
            font-size: 42px !important;
            font-weight: 700 !important;
            color: #ffffff !important;
            margin-bottom: 12px !important;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3), 0 1px 3px rgba(0, 0, 0, 0.2) !important;
            letter-spacing: -0.5px !important;
        }
        
        .login-system-subtitle {
            font-size: 32px !important;
            font-weight: 600 !important;
            color: #ffffff !important;
            margin-bottom: 40px !important;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3), 0 1px 3px rgba(0, 0, 0, 0.2) !important;
            letter-spacing: -0.3px !important;
        }
        
        .login-system-description {
            color: #ffffff !important;
            font-size: 16px !important;
            line-height: 1.7 !important;
            text-shadow: 0 1px 4px rgba(0, 0, 0, 0.25) !important;
            max-width: 450px !important;
            margin: 0 auto !important;
            opacity: 0.95 !important;
        }
        
        .login-form-title {
            color: #1e3a8a !important;
            font-weight: 700 !important;
            font-size: 32px !important;
            margin-bottom: 8px !important;
        }
        
        .login-form-subtitle {
            color: #64748b !important;
            font-size: 15px !important;
            margin-bottom: 35px !important;
        }
        
        /* Input with clean white background */
        .login-form .input-group {
            background: #ffffff !important;
            border-radius: 12px !important;
            border: 1px solid #e5e7eb !important;
            overflow: hidden !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
            transition: all 0.2s ease !important;
        }
        
        .login-form .input-group:hover {
            border-color: #cbd5e1 !important;
        }
        
        .login-form .input-group-text {
            background: #f8fafc !important;
            border: none !important;
            border-right: 1px solid #e5e7eb !important;
            color: #64748b !important;
            padding: 12px 16px !important;
        }
        
        .login-form .form-control {
            background: #ffffff !important;
            border: none !important;
            padding: 12px 16px !important;
            color: #1e293b !important;
            font-size: 15px !important;
        }
        
        .login-form .form-control::placeholder {
            color: #94a3b8 !important;
        }
        
        /* Input focus - Blue to match dashboard */
        .login-form .input-group:focus-within {
            border-color: #1e3a8a !important;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1) !important;
        }
        
        .login-form .input-group:focus-within .input-group-text {
            background: #f1f5f9 !important;
            color: #1e3a8a !important;
            border-right-color: #1e3a8a !important;
        }
        
        .login-form .form-control:focus {
            outline: none !important;
            box-shadow: none !important;
        }
        
        /* Button - Blue gradient matching dashboard */
        .btn-login {
            width: 100% !important;
            padding: 14px 20px !important;
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%) !important;
            border: none !important;
            border-radius: 12px !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            font-size: 16px !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.3) !important;
        }
        
        .btn-login:hover {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%) !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 20px rgba(30, 58, 138, 0.4) !important;
        }
        
        .login-footer {
            margin-top: 35px !important;
            padding-top: 20px !important;
            border-top: 1px solid #e5e7eb !important;
            text-align: center !important;
            color: #64748b !important;
            font-size: 13px !important;
        }
        
        .alert-danger {
            background: #fef2f2 !important;
            border: 1px solid #fecaca !important;
            color: #991b1b !important;
            border-radius: 12px !important;
            padding: 12px 16px !important;
            margin-bottom: 24px !important;
        }
        
        .alert-danger i {
            margin-right: 8px;
        }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <!-- Left Panel: System Info -->
            <div class="login-left-panel">
                <div class="login-logo-section">
                    <img src="assets/img/sekolahku-login.png" alt="Sekolahku" style="max-width: 250px; height: auto; margin-bottom: 30px;">
                    
                    <p class="login-system-description">
                        Portal pembelajaran online untuk mengelola kegiatan belajar mengajar dengan mudah dan efisien.
                    </p>
                    
                </div>
            </div>
            
            <!-- Right Panel: Login Form -->
            <div class="login-right-panel">
                <div class="login-form-wrapper">
                    <h2 class="login-form-title">Login</h2>
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
                        <small>© 2025 Sekolahku - Portal Pembelajaran Online</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
