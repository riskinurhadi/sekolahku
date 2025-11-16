<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa - Modern</title>
    
    <!-- Dependensi: Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Dependensi: Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- Dependensi: Google Font (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- 
      CSS Anda dari 'style.css' 
      ditempatkan di sini
    -->
    <style>
        /* Portal Sekolah - Modern Dashboard Styles */

        :root {
            --primary-color: #8b7fd8;
            --primary-dark: #7c6dd4;
            --purple-light: #f3f0ff;
            --blue-color: #6ba3d8;
            --blue-light: #e8f2fb;
            --orange-color: #f5a97f;
            --orange-light: #fff0e8;
            --green-color: #7fb8a0;
            --green-light: #e8f5ed;
            --sidebar-width: 280px;
            --header-height: 80px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            /* Latar belakang gradien yang bersih dan lembut */
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 50%, #e8ecf1 100%);
            background-attachment: fixed;
            color: #1e293b;
            font-size: 14px;
            line-height: 1.6;
            overflow-x: hidden;
            min-height: 100vh;
        }

        .wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* Sidebar - Efek Glassmorphism */
        .sidebar {
            width: var(--sidebar-width);
            min-width: var(--sidebar-width);
            /* Glassmorphism: Latar belakang transparan + blur */
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 1000;
            /* Shadow dan border untuk efek 3D */
            box-shadow: 4px 0 24px rgba(0, 0, 0, 0.06), 
                        2px 0 8px rgba(0, 0, 0, 0.04),
                        inset -1px 0 0 rgba(255, 255, 255, 0.9);
            border-right: 1px solid rgba(255, 255, 255, 0.4);
            overflow-y: auto;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            padding-bottom: 100px;
        }

        .sidebar-header {
            padding: 24px 20px;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(233, 236, 239, 0.6);
        }

        .sidebar-header h4 {
            color: var(--primary-color);
            margin: 0;
            font-weight: 700;
            font-size: 22px;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-header h4 i {
            font-size: 24px;
        }

        .sidebar .components {
            padding: 20px 0;
            list-style: none;
            flex: 1;
        }

        .sidebar .components li {
            margin: 2px 0;
        }

        .sidebar .components a {
            padding: 12px 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            color: #64748b;
            text-decoration: none;
            transition: all 0.2s ease;
            border-radius: 0;
            position: relative;
            font-weight: 500;
            margin: 0 12px;
            border-radius: 8px;
        }

        .sidebar .components a:hover {
            color: #1e293b;
            background: #f8f9fa;
        }

        .sidebar .components a.active {
            color: #6366f1;
            background: #eef2ff;
            font-weight: 600;
        }

        .sidebar .components a i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            font-size: 18px;
        }

        /* Sidebar Footer - Logout Button */
        .sidebar-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px;
            border-top: 1px solid #e9ecef;
            background: #ffffff;
        }

        .sidebar-logout-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            width: 100%;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: #ffffff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .sidebar-logout-btn:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3);
        }

        .sidebar-logout-btn i {
            font-size: 18px;
            width: 20px;
            text-align: center;
        }

        /* Content Area */
        .content {
            flex: 1;
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            background: transparent;
            transition: all 0.3s ease;
        }

        /* Top Header - Efek Glassmorphism */
        .dashboard-top-header {
            /* Glassmorphism: Latar belakang transparan + blur */
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            padding: 0 32px;
            height: var(--header-height);
            /* Shadow dan border untuk efek 3D */
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06), 
                        0 1px 4px rgba(0, 0, 0, 0.04),
                        inset 0 1px 0 rgba(255, 255, 255, 0.9);
            border-bottom: 1px solid rgba(255, 255, 255, 0.4);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .dashboard-top-header .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .dashboard-top-header .logo-section .logo-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-weight: 700;
            font-size: 18px;
            letter-spacing: 0.5px;
        }

        .dashboard-top-header .logo-section .logo-text {
            font-size: 22px;
            font-weight: 700;
            color: #6366f1;
            margin: 0;
            letter-spacing: -0.5px;
        }

        .dashboard-top-header .search-section {
            flex: 1;
            max-width: 600px;
            margin: 0 auto;
        }

        .dashboard-top-header .search-section .search-wrapper {
            position: relative;
            width: 100%;
        }

        .dashboard-top-header .search-section .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 18px;
            z-index: 1;
        }

        .dashboard-top-header .search-section .search-input {
            width: 100%;
            padding: 12px 16px 12px 48px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            background: #f8f9fa;
            transition: all 0.2s ease;
            color: #1e293b;
        }

        .dashboard-top-header .search-section .search-input:focus {
            outline: none;
            border-color: #6366f1;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .dashboard-top-header .search-section .search-input::placeholder {
            color: #94a3b8;
        }

        .dashboard-top-header .user-profile-section {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-shrink: 0;
        }

        .dashboard-top-header .header-icons {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .dashboard-top-header .header-icons .icon-btn {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            border: none;
            background: #f8f9fa;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            position: relative;
            font-size: 20px;
        }

        .dashboard-top-header .header-icons .icon-btn:hover {
            background: #e9ecef;
            color: #1e293b;
        }

        .dashboard-top-header .header-icons .icon-btn .badge {
            position: absolute;
            top: 6px;
            right: 6px;
            min-width: 18px;
            height: 18px;
            background: #ef4444;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #ffffff;
            font-weight: 700;
            padding: 0 5px;
            border: 2px solid #ffffff;
        }

        .dashboard-top-header .user-profile-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px 12px 6px 6px;
            border-radius: 12px;
            background: #f8f9fa;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .dashboard-top-header .user-profile-info:hover {
            background: #e9ecef;
        }

        .dashboard-top-header .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
        }

        .dashboard-top-header .user-details {
            display: flex;
            flex-direction: column;
        }

        .dashboard-top-header .user-details .user-name {
            font-weight: 700;
            color: #1e293b;
            font-size: 14px;
            margin: 0;
            line-height: 1.2;
        }

        .dashboard-top-header .user-details .user-role {
            font-size: 12px;
            color: #64748b;
            margin: 0;
            line-height: 1.2;
        }

        /* Main Content Container */
        .container-fluid {
            padding: 32px;
        }

        /* Statistics Cards - Efek Glassmorphism */
        .statistics-row {
            margin-bottom: 32px;
        }

        .stat-card {
            /* Glassmorphism: Latar belakang transparan + blur */
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(30px) saturate(200%);
            -webkit-backdrop-filter: blur(30px) saturate(200%);
            border-radius: 24px;
            padding: 24px;
            /* Shadow dan border untuk efek 3D */
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08), 
                        0 2px 16px rgba(0, 0, 0, 0.06),
                        inset 0 2px 4px rgba(255, 255, 255, 0.9),
                        inset 0 -1px 2px rgba(255, 255, 255, 0.5);
            border: 1.5px solid rgba(255, 255, 255, 0.5);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 20px;
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        
        /* Efek highlight di bagian atas card */
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, 
                transparent 0%, 
                rgba(255, 255, 255, 0.9) 20%, 
                rgba(255, 255, 255, 1) 50%,
                rgba(255, 255, 255, 0.9) 80%,
                transparent 100%);
            opacity: 0.8;
        }

        /* Efek cahaya radial di tengah card */
        .stat-card::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.3) 0%, transparent 70%);
            opacity: 0.6;
            pointer-events: none;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.12), 
                        0 8px 24px rgba(0, 0, 0, 0.08),
                        inset 0 2px 4px rgba(255, 255, 255, 0.95),
                        inset 0 -1px 2px rgba(255, 255, 255, 0.6);
            background: rgba(255, 255, 255, 0.5);
            border-color: rgba(255, 255, 255, 0.7);
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card .stat-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #fff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            flex-shrink: 0;
        }

        .stat-card.primary .stat-icon {
            background: linear-gradient(135deg, #b8a9f0 0%, #9d8ae8 100%);
            box-shadow: 0 4px 16px rgba(139, 127, 216, 0.3);
        }

        .stat-card.success .stat-icon {
            background: linear-gradient(135deg, #7fb3d8 0%, #6ba3d8 100%);
            box-shadow: 0 4px 16px rgba(107, 163, 216, 0.3);
        }

        .stat-card.info .stat-icon {
            background: linear-gradient(135deg, #f9c99a 0%, #f5a97f 100%);
            box-shadow: 0 4px 16px rgba(245, 169, 127, 0.3);
        }

        .stat-card.warning .stat-icon {
            background: linear-gradient(135deg, #8fc9a8 0%, #7fb8a0 100%);
            box-shadow: 0 4px 16px rgba(127, 184, 160, 0.3);
        }

        .stat-card .stat-content {
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .stat-card .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
            line-height: 1;
            letter-spacing: -0.5px;
        }

        .stat-card .stat-label {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
            text-transform: none;
        }

        /* Dashboard Cards - Efek Glassmorphism */
        .dashboard-card {
            /* Glassmorphism: Latar belakang transparan + blur */
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(30px) saturate(200%);
            -webkit-backdrop-filter: blur(30px) saturate(200%);
            border-radius: 24px;
            /* Shadow dan border untuk efek 3D */
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08), 
                        0 2px 16px rgba(0, 0, 0, 0.06),
                        inset 0 2px 4px rgba(255, 255, 255, 0.9),
                        inset 0 -1px 2px rgba(255, 255, 255, 0.5);
            border: 1.5px solid rgba(255, 255, 255, 0.5);
            overflow: hidden;
            transition: all 0.3s ease;
            margin-bottom: 24px;
            position: relative;
        }

        /* Efek highlight di bagian atas card */
        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, 
                transparent 0%, 
                rgba(255, 255, 255, 0.9) 20%, 
                rgba(255, 255, 255, 1) 50%,
                rgba(255, 255, 255, 0.9) 80%,
                transparent 100%);
            z-index: 1;
            opacity: 0.8;
        }

        /* Efek cahaya radial di tengah card */
        .dashboard-card::after {
            content: '';
            position: absolute;
            top: 20%;
            left: 50%;
            transform: translateX(-50%);
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.25) 0%, transparent 70%);
            opacity: 0.5;
            pointer-events: none;
            z-index: 0;
        }

        .dashboard-card:hover {
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.12), 
                        0 8px 24px rgba(0, 0, 0, 0.08),
                        inset 0 2px 4px rgba(255, 255, 255, 0.95),
                        inset 0 -1px 2px rgba(255, 255, 255, 0.6);
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.5);
            border-color: rgba(255, 255, 255, 0.7);
        }

        .dashboard-card:hover::before {
            opacity: 1;
        }

        .dashboard-card .card-header {
            /* Header card juga semi-transparan */
            background: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(15px) saturate(180%);
            -webkit-backdrop-filter: blur(15px) saturate(180%);
            border-bottom: 1px solid rgba(226, 232, 240, 0.4);
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 2;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
        }

        .dashboard-card .card-header h5,
        .dashboard-card .card-header h6 {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        .dashboard-card .card-header h5 i,
        .dashboard-card .card-header h6 i {
            font-size: 20px;
            color: #6366f1;
        }

        .dashboard-card .card-header a {
            color: #10b981;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
        }

        .dashboard-card .card-header a:hover {
            color: #059669;
            transform: translateX(4px);
        }

        .dashboard-card .card-body {
            padding: 24px;
            background: transparent;
            position: relative;
            z-index: 1;
        }

        /* Tables */
        .table {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            margin: 0;
        }

        .table thead {
            background: #f8fafc;
        }

        .table thead th {
            border: none;
            border-bottom: 2px solid #e2e8f0;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            color: #64748b;
            padding: 16px;
            letter-spacing: 0.5px;
        }

        .table tbody td {
            padding: 16px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
        }

        .table tbody tr:hover {
            background: #f8fafc;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .dashboard-card .table thead {
            background: #f8fafc;
        }

        .dashboard-card .table thead th {
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
            padding: 18px 16px;
            border-bottom: 2px solid #e2e8f0;
        }

        .dashboard-card .table tbody td {
            padding: 18px 16px;
            font-size: 14px;
            color: #1e293b;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .dashboard-card .table tbody tr:hover {
            background: #f8fafc;
        }

        /* Buttons */
        .btn {
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.12);
        }

        .btn-primary {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: #fff;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
            color: #fff;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #fff;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: #fff;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }

        .btn-info {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: #fff;
        }

        .btn-info:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #fff;
        }

        .btn-outline-primary {
            color: #4f46e5;
            border-color: #4f46e5;
        }
        .btn-outline-primary:hover {
            background: #4f46e5;
            color: #fff;
            border-color: #4f46e5;
        }

        /* Badges */
        .badge {
            padding: 6px 12px;
            font-weight: 600;
            border-radius: 6px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .bg-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
            color: #fff !important;
        }

        .bg-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
            color: #fff !important;
        }
        
        .bg-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
            color: #fff !important;
        }

        .bg-secondary {
            background: linear-gradient(135deg, #64748b 0%, #475569 100%) !important;
            color: #fff !important;
        }

        /* Forms */
        .form-control, .form-select {
            border-radius: 10px;
            border: 1.5px solid #e2e8f0;
            padding: 10px 14px;
            font-size: 14px;
            transition: all 0.2s ease;
            background: #fff;
        }

        .form-control:focus, .form-select:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            outline: none;
        }

        .form-label {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
            font-size: 14px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
            color: #6366f1;
            display: inline-block;
        }

        .empty-state h5 {
            color: #1e293b;
            margin-bottom: 12px;
            font-weight: 700;
            font-size: 18px;
        }

        .empty-state p {
            color: #64748b;
            margin-bottom: 24px;
            font-size: 14px;
        }

        /* DataTables */
        .dataTables_wrapper {
            padding: 0;
        }

        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px 12px;
            margin-left: 8px;
        }

        .dataTables_wrapper .dataTables_length select {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 6px 12px;
            margin: 0 8px;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 8px 12px;
            margin: 0 2px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #64748b !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #6366f1;
            color: #fff !important;
            border-color: #6366f1;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #6366f1 !important;
            color: #fff !important;
            border-color: #6366f1 !important;
        }

        .dataTables_wrapper .dataTables_info {
            color: #64748b;
            font-size: 13px;
        }

        /* Modal */
        .modal-content {
            border: none;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 20px 24px;
            border-radius: 16px 16px 0 0;
        }

        .modal-title {
            font-weight: 700;
            color: #1e293b;
            font-size: 18px;
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            border-top: 1px solid #e2e8f0;
            padding: 16px 24px;
        }

        /* Scrollbar */
        .sidebar::-webkit-scrollbar,
        .card-body[style*="overflow-y: auto"]::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track,
        .card-body[style*="overflow-y: auto"]::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .sidebar::-webkit-scrollbar-thumb,
        .card-body[style*="overflow-y: auto"]::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover,
        .card-body[style*="overflow-y: auto"]::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        .card-body[style*="overflow-y: auto"] {
            /* Perbaikan untuk Firefox/Edge */
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f1f5f9;
        }


        /* Responsive */
        @media (max-width: 768px) {
            /* Sidebar menjadi Bottom Navigation Bar di Mobile */
            .sidebar {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                top: auto;
                width: 100%;
                min-width: 100%;
                height: auto;
                min-height: 60px;
                max-height: 70px;
                flex-direction: row;
                padding: 0;
                padding-bottom: env(safe-area-inset-bottom);
                box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
                border-top: 1px solid #e9ecef;
                overflow-x: auto;
                overflow-y: hidden;
                z-index: 1000;
                /* Pastikan glassmorphism bottom nav bekerja */
                background: rgba(255, 255, 255, 0.9);
                backdrop-filter: blur(15px) saturate(180%);
                -webkit-backdrop-filter: blur(15px) saturate(180%);
            }
            
            /* Sembunyikan sidebar header di mobile */
            .sidebar-header {
                display: none;
            }
            
            /* Sembunyikan sidebar footer (logout) di mobile */
            .sidebar-footer {
                display: none;
            }
            
            /* Menu items horizontal di mobile */
            .sidebar .components {
                display: flex;
                flex-direction: row;
                padding: 0;
                margin: 0;
                width: 100%;
                justify-content: space-around;
                align-items: center;
                flex: 1;
                overflow-x: auto;
                overflow-y: hidden;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none; /* Firefox */
                -ms-overflow-style: none; /* IE and Edge */
            }
            
            .sidebar .components::-webkit-scrollbar {
                display: none; /* Chrome, Safari, Opera */
            }
            
            .sidebar .components li {
                margin: 0;
                flex: 1;
                min-width: 70px;
                max-width: 120px;
                display: flex;
                flex-shrink: 0;
            }
            
            /* Styling menu items untuk bottom nav */
            .sidebar .components a {
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 8px 4px;
                margin: 0;
                width: 100%;
                min-height: 60px;
                border-radius: 0;
                gap: 4px;
                font-size: 10px;
                text-align: center;
                position: relative;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            
            .sidebar .components a span {
                display: block;
                max-width: 100%;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            
            .sidebar .components a i {
                margin-right: 0;
                margin-bottom: 0;
                width: auto;
                font-size: 20px;
                display: block;
                flex-shrink: 0;
            }
            
            /* Active state untuk bottom nav */
            .sidebar .components a.active {
                color: #6366f1;
                background: transparent;
                font-weight: 600;
            }
            
            .sidebar .components a.active::before {
                content: '';
                position: absolute;
                top: 0;
                left: 50%;
                transform: translateX(-50%);
                width: 40px;
                height: 3px;
                background: #6366f1;
                border-radius: 0 0 3px 3px;
            }
            
          _ .sidebar .components a:hover {
                background: #f8f9fa;
            }
            
            /* Content area dengan padding bottom untuk bottom nav */
            .content {
                margin-left: 0;
                padding-bottom: calc(70px + env(safe-area-inset-bottom));
            }
            
            .dashboard-top-header {
                flex-direction: column;
                height: auto;
                padding: 16px;
                gap: 16px;
            }
            
            .dashboard-top-header .search-section {
                max-width: 100%;
            }
            
            .dashboard-top-header .logo-section {
                display: none; /* Sembunyikan logo di header mobile */
            }
            .dashboard-top-header .user-profile-section {
                width: 100%;
                justify-content: space-between;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .stat-card .stat-value {
                font-size: 24px;
            }
            
            .stat-card .stat-icon {
                width: 56px;
                height: 56px;
                font-size: 24px;
            }
            
            .container-fluid {
                padding: 16px;
                padding-bottom: 24px;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dashboard-card, .stat-card {
            animation: fadeIn 0.3s ease;
        }

        /* Sisa CSS Anda (Login, Lesson Card, dll.) 
           tetap ada di sini tetapi tidak relevan 
           untuk tampilan dashboard utama ini.
        */
        
        /* ... Sisa CSS dari file Anda ... */
        .lesson-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .lesson-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }

        .lesson-title {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 12px;
        }

        .lesson-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #64748b;
        }

        .detail-item i {
            width: 18px;
            text-align: center;
            color: #94a3b8;
        }

        .detail-item strong {
            color: #1e293b;
            font-weight: 600;
        }

        .lesson-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .presensi-lesson-item {
            transition: all 0.2s ease;
        }

        .presensi-lesson-item:hover {
            background: #f8f9fa;
            margin-left: -8px;
            margin-right: -8px;
            padding-left: 8px;
            padding-right: 8px;
            border-radius: 8px;
        }

        .presensi-lesson-item:last-child {
            border-bottom: none !important;
        }

        .presensi-form-inline .input-group {
            max-width: 400px;
        }

        .presensi-form-inline .form-control-sm {
            font-size: 14px;
            padding: 6px 12px;
        }

        .presensi-form-inline .btn-sm {
            padding: 6px 16px;
            font-size: 14px;
        }

        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 24px;
            background: #fff;
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
        }

        .card-header {
            background: #ffffff;
            border-bottom: 1px solid #e9ecef;
            padding: 20px 24px;
            font-weight: 600;
            font-size: 16px;
            color: #1e293b;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-header h5 {
            margin: 0;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-body {
            padding: 24px;
            background: #ffffff;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
            border-width: 0.15em;
        }

        /* Style login tidak ditampilkan di dashboard */
        body.login-page {
             display: none;
        }
    </style>
</head>
<body>

    <div class="wrapper">
        <!-- 
          Sidebar (Simulasi dari header.php Anda)
          Dibuat berdasarkan style .sidebar di CSS Anda
        -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h4><i class="bi bi-mortarboard-fill"></i> Portal Siswa</h4>
            </div>
            <ul class="components list-unstyled">
                <li class="active"><a href="#"><i class="bi bi-house-door-fill"></i> <span>Dashboard</span></a></li>
                <li><a href="#"><i class="bi bi-calendar-week-fill"></i> <span>Jadwal</span></a></li>
                <li><a href="#"><i class="bi bi-clipboard-check-fill"></i> <span>Presensi</span></a></li>
                <li><a href="#"><i class="bi bi-file-earmark-text-fill"></i> <span>Soal Saya</span></a></li>
                <li><a href="#"><i class="bi bi-trophy-fill"></i> <span>Hasil Ujian</span></a></li>
                <li><a href="#"><i class="bi bi-person-fill"></i> <span>Profil</span></a></li>
            </ul>
            <div class="sidebar-footer">
                <a href="#" class="sidebar-logout-btn">
                    <i class="bi bi-box-arrow-left"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>

        <!-- Content Area -->
        <div class="content">

            <!-- 
              Header Atas (Simulasi dari header.php Anda)
              Dibuat berdasarkan style .dashboard-top-header di CSS Anda
            -->
            <header class="dashboard-top-header">
                <div class="logo-section">
                    <div class="logo-icon">S</div>
                    <h1 class="logo-text">Dashboard</h1>
                </div>
                
                <div class="search-section">
                    <div class="search-wrapper">
                        <i class="bi bi-search search-icon"></i>
                        <input type="text" class="search-input" placeholder="Cari pelajaran, soal, atau guru...">
                    </div>
                </div>
                
                <div class="user-profile-section">
                    <div class="header-icons">
                        <a href="#" class="icon-btn">
                            <i class="bi bi-bell-fill"></i>
                            <span class="badge">3</span>
                        </a>
                        <a href="#" class="icon-btn"><i class="bi bi-gear-fill"></i></a>
                    </div>
                    <div class="user-profile-info">
                        <div class="user-avatar">SS</div>
                        <div class="user-details">
                            <p class="user-name">Siswa Siswanto</p>
                            <p class="user-role">Siswa</p>
                        </div>
                    </div>
                </div>
            </header>

            <!-- 
              Konten Utama 
              Diambil dari index.php Anda, dengan data PHP diganti statis
            -->
            <main class="container-fluid">

                <!-- 
                    Slider Section 
                    CATATAN: 
                    Inline style asli dari index.php telah dihapus dan diganti dengan 
                    class 'dashboard-card' dari style.css Anda. 
                    Style 'height: 250px; padding: 0;' ditambahkan untuk menyesuaikan 
                    tampilan slider di dalam card.
                -->
                <div class="dashboard-card mb-4" style="height: 250px; padding: 0; overflow: hidden;">
                    <div id="sliderCarousel" class="carousel slide h-100" data-bs-ride="carousel" data-bs-interval="5000">
                        <div class="carousel-indicators">
                            <button type="button" data-bs-target="#sliderCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                            <button type="button" data-bs-target="#sliderCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                        </div>
                        <div class="carousel-inner h-100">
                            <!-- Slide 1 (Contoh) -->
                            <div class="carousel-item h-100 active" data-bs-interval="5000">
                                <a href="#" target="_blank" style="display: block; height: 100%;">
                                    <img src="https://placehold.co/1200x250/6366f1/white?text=Pengumuman+Ujian+Akhir" 
                                         class="d-block w-100 h-100" 
                                         style="object-fit: cover;" 
                                         alt="Slider 1">
                                    <div class="carousel-caption d-none d-md-block" style="background: linear-gradient(to top, rgba(0,0,0,0.7), transparent); padding: 2rem; border-radius: 0 0 12px 12px;">
                                        <h5 style="font-weight: 600; margin-bottom: 0.5rem;">Ujian Akhir Semester</h5>
                                        <p style="margin-bottom: 0;">Jadwal ujian akhir semester telah terbit. Lihat di menu jadwal.</p>
                                    </div>
                                </a>
                            </div>
                            <!-- Slide 2 (Contoh) -->
                            <div class="carousel-item h-100" data-bs-interval="5000">
                                <img src="https://placehold.co/1200x250/10b981/white?text=Info+Sekolah" 
                                     class="d-block w-100 h-100" 
                                     style="object-fit: cover;" 
                                     alt="Slider 2">
                                <div class="carousel-caption d-none d-md-block" style="background: linear-gradient(to top, rgba(0,0,0,0.7), transparent); padding: 2rem; border-radius: 0 0 12px 12px;">
                                    <h5 style="font-weight: 600; margin-bottom: 0.5rem;">Kegiatan Class Meeting</h5>
                                    <p style="margin-bottom: 0;">Class meeting akan diadakan setelah ujian selesai.</p>
                                </div>
                            </div>
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#sliderCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#sliderCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row statistics-row">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card primary">
                            <div class="stat-icon">
                                <i class="bi bi-file-earmark-text"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value">8</div>
                                <div class="stat-label">Soal<br>Aktif</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card success">
                            <div class="stat-icon">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value">5</div>
                                <div class="stat-label">Soal<br>Selesai</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card info">
                            <div class="stat-icon">
                                <i class="bi bi-star"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value">85.2</div>
                                <div class="stat-label">Rata-rata Nilai</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stat-card warning">
                            <div class="stat-icon">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value">3</div>
                                <div class="stat-label">Belum Dikerjakan</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Jadwal Besok & Ringkasan Jadwal Minggu Ini -->
                <div class="row mt-4 align-items-stretch">
                    <!-- Jadwal Besok -->
                    <div class="col-lg-6 mb-4 d-flex">
                        <div class="dashboard-card w-100 d-flex flex-column" style="max-height: 400px;">
                            <div class="card-header bg-info text-white flex-shrink-0">
                                <h6 class="mb-0"><i class="bi bi-calendar-check"></i> Jadwal Besok</h6>
                            </div>
                            <div class="card-body flex-grow-1 d-flex flex-column" style="overflow: hidden;">
                                <div class="flex-grow-1" style="overflow-y: auto; min-height: 0;">
                                    
                                <!-- Contoh Jadwal 1 -->
                                    <div class="history-task-item mb-2 p-2 bg-white rounded border" style="border-color: #e2e8f0 !important; transition: all 0.2s ease;">
                                        <div class="d-flex align-items-start">
                                            <div class="history-icon-wrapper me-3 flex-shrink-0" style="width: 42px; height: 42px; border-radius: 10px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
                                                <i class="bi bi-book text-white" style="font-size: 1.1rem;"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1 fw-semibold" style="color: #1e293b; font-size: 14px; line-height: 1.3;">
                                                    Matematika Wajib
                                                </h6>
                                                <p class="mb-0 text-muted" style="font-size: 12px; line-height: 1.4; color: #64748b;">
                                                    07:30 - 09:00
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                
                                <!-- Contoh Jadwal 2 -->
                                    <div class="history-task-item mb-2 p-2 bg-white rounded border" style="border-color: #e2e8f0 !important; transition: all 0.2s ease;">
                                        <div class="d-flex align-items-start">
                                            <div class="history-icon-wrapper me-3 flex-shrink-0" style="width: 42px; height: 42px; border-radius: 10px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
                                                <i class="bi bi-journal-bookmark text-white" style="font-size: 1.1rem;"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1 fw-semibold" style="color: #1e293b; font-size: 14px; line-height: 1.3;">
                                                    Bahasa Indonesia
                                                </h6>
                                                <p class="mb-0 text-muted" style="font-size: 12px; line-height: 1.4; color: #64748b;">
                                                    09:30 - 11:00
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                
                                <!-- Contoh Jadwal 3 -->
                                    <div class="history-task-item mb-2 p-2 bg-white rounded border" style="border-color: #e2e8f0 !important; transition: all 0.2s ease;">
                                        <div class="d-flex align-items-start">
                                            <div class="history-icon-wrapper me-3 flex-shrink-0" style="width: 42px; height: 42px; border-radius: 10px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
                                                <i class="bi bi-book-half text-white" style="font-size: 1.1rem;"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1 fw-semibold" style="color: #1e293b; font-size: 14px; line-height: 1.3;">
                                                    Fisika
                                                </h6>
                                                <p class="mb-0 text-muted" style="font-size: 12px; line-height: 1.4; color: #64748b;">
                                                    11:00 - 12:30
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Presensi Minggu Ini -->
                    <div class="col-lg-6 mb-4 d-flex">
                        <div class="dashboard-card w-100 d-flex flex-column" style="max-height: 400px;">
                            <div class="card-header d-flex justify-content-between align-items-center flex-shrink-0">
                                <h6 class="mb-0"><i class="bi bi-clipboard-check"></i> Presensi Minggu Ini</h6>
                                <a href="presensi.php" class="text-decoration-none small">Lihat Detail <i class="bi bi-arrow-right"></i></a>
                            </div>
                            <div class="card-body flex-grow-1 d-flex flex-column justify-content-center">
                                <div class="text-center">
                                    <h1 class="text-primary mb-1 fw-bold" style="font-size: 3.5rem; line-height: 1; color: #6366f1 !important;">
                                        95%
                                    </h1>
                                    <p class="text-muted mb-3" style="font-size: 0.9rem;">Kehadiran</p>
                                    <div class="progress mx-auto mb-3" style="height: 10px; max-width: 280px;">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                             style="width: 95%" 
                                             aria-valuenow="95" 
                                             aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <div class="row text-center mt-2 pt-2 border-top">
                                        <div class="col-4">
                                            <div class="py-1">
                                                <h3 class="text-success mb-1 fw-bold" style="font-size: 2rem;">18</h3>
                                                <p class="text-muted mb-0" style="font-size: 0.85rem;">Hadir</p>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="py-1">
                                                <h3 class="text-warning mb-1 fw-bold" style="font-size: 2rem;">1</h3>
                                                <p class="text-muted mb-0" style="font-size: 0.85rem;">Terlambat</p>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="py-1">
                                                <h3 class="text-danger mb-1 fw-bold" style="font-size: 2rem;">1</h3>
                                                <p class="text-muted mb-0" style="font-size: 0.85rem;">Tidak Hadir</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2 pt-2 border-top">
                                        <p class="text-muted mb-0" style="font-size: 0.85rem;">Total: 20 sesi</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ringkasan Jadwal & Hasil Terbaru -->
                <div class="row mt-4 align-items-stretch">
                    <!-- Ringkasan Jadwal Minggu Ini -->
                    <div class="col-lg-4 mb-4 d-flex">
                        <div class="dashboard-card w-100 d-flex flex-column h-100">
                            <div class="card-header d-flex justify-content-between align-items-center flex-shrink-0">
                                <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Ringkasan Jadwal Minggu Ini</h5>
                                <a href="jadwal.php" class="text-decoration-none small">Lihat Detail <i class="bi bi-arrow-right"></i></a>
                            </div>
                            <div class="card-body flex-grow-1 d-flex flex-column justify-content-center">
                                <div class="row text-center">
                                    <div class="col-6 mb-3">
                                        <div class="p-2">
                                            <h4 class="text-primary mb-0" style="color: #6366f1 !important;">12</h4>
                                            <small class="text-muted">Total Jadwal</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="p-2">
                                            <h4 class="text-success mb-0">1</h4>
                                            <small class="text-muted">Berlangsung</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2">
                                            <h4 class="text-info mb-0">7</h4>
                                            <small class="text-muted">Selesai</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2">
                                            <h4 class="text-secondary mb-0">4</h4>
                                            <small class="text-muted">Terjadwal</small>
                              _         </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Hasil Terbaru -->
                    <div class="col-lg-8 mb-4 d-flex">
                        <div class="dashboard-card w-100 d-flex flex-column h-100">
                            <div class="card-header d-flex justify-content-between align-items-center flex-shrink-0">
                                <h5 class="mb-0"><i class="bi bi-trophy"></i> Hasil Terbaru</h5>
                                <a href="hasil.php" class="text-decoration-none">Lihat Semua <i class="bi bi-arrow-right"></i></a>
                            </div>
                            <div class="card-body flex-grow-1 d-flex flex-column">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Mata Pelajaran</th>
                                                <th>Judul Soal</th>
                                                <th>Nilai</th>
                                                <th>Tanggal</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Contoh Hasil 1 -->
                                            <tr>
                                                <td>
                                                    <strong>Biologi</strong>
                                                </td>
                                                <td>Ujian Akhir Semester</td>
                                                <td>
                                                    <span class="badge bg-success fs-6">
                                                        92.5
                                                    </span>
                                                </td>
                                                <td>
                                                    <small>15/11/2025</small>
                                                </td>
                                                <td>
                                                    <a href="#" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i> Detail
                                                    </a>
                                                </td>
                                            </tr>
                                            <!-- Contoh Hasil 2 -->
                                            <tr>
                                                <td>
                                                    <strong>Sejarah Indonesia</strong>
                                                </td>
                                                <td>Quiz Bab 3</td>
                                                <td>
                                                    <span class="badge bg-danger fs-6">
                                                        55.0
                                                    </span>
                                                </td>
                                                <td>
                                                    <small>14/11/2025</small>
                                                </td>
                                                <td>
                                                   <a href="#" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i> Detail
                                                    </a>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Soal -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="dashboard-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Soal Aktif</h5>
                                <a href="soal_saya.php" class="text-decoration-none">Lihat Semua <i class="bi bi-arrow-right"></i></a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Judul</th>
                                                <th>Mata Pelajaran</th>
                                                <th>Jenis</th>
                                                <th>Waktu</th>
                                                <th>Status</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Contoh Soal 1: Belum Dikerjakan -->
                                            <tr>
                                                <td><strong>Try Out Fisika</strong></td>
                                                <td>Fisika</td>
                                                <td>Pilihan Ganda</td>
                                                <td>90 menit</td>
                                                <td>
                                                    <span class="badge bg-warning">Belum Dikerjakan</span>
                                                </td>
                                                <td>
                                                    <a href="#" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-pencil"></i> Kerjakan
                                                    </a>
                                                </td>
                                            </tr>
                                            <!-- Contoh Soal 2: Selesai -->
                                            <tr>
                                                <td><strong>Latihan Harian Kimia</strong></td>
                                                <td>Kimia</td>
                                               <td>Isian</td>
                                                <td>45 menit</td>
                                                <td>
                                                    <span class="badge bg-success">Selesai</span>
                                                </td>
                                                <td>
                                                    <a href="#" class="btn btn-sm btn-info">
                                                        <i class="bi bi-eye"></i> Lihat Hasil
                                                    </a>
                                             </td>
                                         </tr>
                                        </tbody> 
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
            
        </div> <!-- end content -->
    </div> <!-- end wrapper -->

    <!-- Dependensi: Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>