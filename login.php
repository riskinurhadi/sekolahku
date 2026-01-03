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
            background: #ffffff !important;
            min-height: 100vh !important;
            width: 100% !important;
        }
        
        body.login-page {
            margin: 0 !important;
            padding: 0 !important;
            overflow-x: hidden;
            background: #ffffff !important;
            min-height: 100vh !important;
            width: 100% !important;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            position: relative;
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
            max-width: 900px !important;
            display: flex !important;
            flex-direction: row !important;
            overflow: hidden !important;
            min-height: 550px !important;
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
        
        /* Logo Kemenag - Solid background without glassmorphism */
        .logo-kemenag-shape {
            width: 100px !important;
            height: 100px !important;
            background: rgba(255, 255, 255, 0.25) !important;
            clip-path: polygon(50% 0%, 100% 38%, 82% 100%, 18% 100%, 0% 38%) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2) !important;
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
        
        /* Logo Sekolahku - Solid background without glassmorphism */
        .logo-waykanan-shape {
            width: 100px !important;
            height: 100px !important;
            background: rgba(255, 255, 255, 0.25) !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2) !important;
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
        
        .login-brand-title {
            font-size: 48px !important;
            font-weight: 700 !important;
            color: #ffffff !important;
            margin-bottom: 30px !important;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3), 0 1px 3px rgba(0, 0, 0, 0.2) !important;
            letter-spacing: 1px !important;
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
        
        /* Responsive Styles */
        @media (max-width: 991.98px) {
            /* Tablet and below */
            body.login-page .login-card {
                flex-direction: column !important;
                max-width: 600px !important;
                min-height: auto !important;
            }
            
            .login-left-panel {
                padding: 40px 30px !important;
                min-height: 250px !important;
            }
            
            .login-right-panel {
                padding: 40px 30px !important;
            }
            
            .login-brand-title {
                font-size: 36px !important;
                margin-bottom: 20px !important;
            }
            
            .login-system-description {
                font-size: 14px !important;
            }
            
            .login-form-title {
                font-size: 28px !important;
            }
            
            .login-form-subtitle {
                font-size: 14px !important;
                margin-bottom: 30px !important;
            }
        }
        
        @media (max-width: 575.98px) {
            /* Mobile */
            body.login-page .login-container {
                padding: 15px !important;
            }
            
            body.login-page .login-card {
                border-radius: 16px !important;
                max-width: 100% !important;
            }
            
            .login-left-panel {
                padding: 30px 20px !important;
                min-height: 200px !important;
            }
            
            .login-right-panel {
                padding: 30px 20px !important;
            }
            
            .login-brand-title {
                font-size: 28px !important;
                margin-bottom: 16px !important;
                letter-spacing: 0.5px !important;
            }
            
            .login-system-description {
                font-size: 13px !important;
                line-height: 1.6 !important;
            }
            
            .login-form-wrapper {
                text-align: center !important;
            }
            
            .login-form-title {
                font-size: 24px !important;
                margin-bottom: 6px !important;
                text-align: center !important;
            }
            
            .login-form-subtitle {
                font-size: 13px !important;
                margin-bottom: 25px !important;
                text-align: center !important;
            }
            
            .login-form .input-group {
                border-radius: 10px !important;
            }
            
            .login-form .input-group-text {
                padding: 10px 14px !important;
                font-size: 16px !important;
            }
            
            .login-form .form-control {
                padding: 10px 14px !important;
                font-size: 14px !important;
            }
            
            .btn-login {
                padding: 12px 18px !important;
                font-size: 15px !important;
                border-radius: 10px !important;
            }
            
            .alert-danger {
                padding: 10px 14px !important;
                font-size: 13px !important;
                margin-bottom: 20px !important;
            }
            
            .login-footer {
                margin-top: 25px !important;
                padding-top: 15px !important;
                font-size: 12px !important;
            }
        }
        
        @media (max-width: 375px) {
            /* Small mobile */
            .login-left-panel {
                padding: 25px 15px !important;
                min-height: 180px !important;
            }
            
            .login-right-panel {
                padding: 25px 15px !important;
            }
            
            .login-brand-title {
                font-size: 24px !important;
            }
            
            .login-system-description {
                font-size: 12px !important;
            }
            
            .login-form-title {
                font-size: 22px !important;
            }
        }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <!-- Left Panel: System Info -->
            <div class="login-left-panel">
                <div class="login-logo-section">
                    <h1 class="login-brand-title">sekolahpintar</h1>
                    
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
                        <small>© 2025 Sekolahku - Portal Pembelajaran Online <br> support by <a href="https://instagram.com/ellfauzan_" target="_blank">@ellfauzan_</a></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
