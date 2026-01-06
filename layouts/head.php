<?php
  require_once __DIR__ . '/../includes/base.php';
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MaarifaZone LMS</title>
    <link rel="stylesheet" href="<?= $base ?>/assets/css/4download-style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Open Sans', 'Segoe UI', Arial, sans-serif; background: #f5f6fa; }
        .sticky-header { position: sticky; top: 0; z-index: 1000; background: #23272b; box-shadow: 0 2px 12px #0002; }
        .navbar { background: #252212ff !important; box-shadow: 0 2px 12px #0002; }
        .navbar .navbar-brand { display: flex; align-items: center; gap: 12px; color: #fff; font-weight: 700; font-size: 1.3rem; letter-spacing: 1px; }
        .navbar .logo-img { width: 44px; height: 44px; border-radius: 12px; }
        .navbar .logo-text { display: flex; flex-direction: column; }
        .navbar .logo-text h1 { font-size: 1.2rem; margin: 0; color: #fff; font-weight: 700; letter-spacing: 1px; }
        .navbar .logo-text p { font-size: 0.9rem; color: #bbb; margin: 0; }
        .navbar-nav .nav-link { color: #fff !important; font-weight: 600; margin-right: 1.2rem; letter-spacing: 0.5px; border-radius: 6px; padding: 0.5rem 1rem; }
        .navbar-nav .nav-link.active, .navbar-nav .nav-link:focus, .navbar-nav .nav-link:hover { color: #857f76ff !important; background: #181a1b; }
        .navbar-toggler { border: none; }
        .navbar-toggler:focus { box-shadow: none; }
        .offcanvas.offcanvas-start { background: #23272b; color: #fff; width: 260px; }
        .offcanvas .navbar-nav .nav-link { color: #fff !important; font-size: 1.1rem; margin: 0.5rem 0; }
        .offcanvas .navbar-nav .nav-link.active, .offcanvas .navbar-nav .nav-link:focus, .offcanvas .navbar-nav .nav-link:hover { color: #FFA726 !important; background: #181a1b; }
        .hero-section { background: linear-gradient(120deg, #23272b 80%, #37332eff 120%); color: #fff; padding: 3.5rem 0 2.5rem 0; text-align: center; margin-bottom: 2.5rem; box-shadow: 0 4px 32px #0002; }
        .hero-section h2 { font-size: 2.3rem; font-weight: 700; margin-bottom: 1rem; letter-spacing: 1px; }
        .hero-section p { font-size: 1.15rem; color: #fff; opacity: 0.92; margin-bottom: 2rem; }
        .hero-section .btn { font-size: 1.1rem; padding: 0.7rem 2.2rem; border-radius: 30px; background: #FFA726; color: #fff; font-weight: 700; border: none; box-shadow: 0 2px 12px #FFA72644; transition: background 0.18s; }
        .hero-section .btn:hover { background: #FFB13E; color: #23272b; }
        .content-wrap { display: flex; gap: 2rem; margin-top: 2rem; }
        .main-col { flex: 1 1 0; }
        .sidebar { width: 320px; max-width: 100%; }
        .section-title { font-size: 1.3rem; font-weight: 700; color: #23272b; margin-bottom: 1.2rem; letter-spacing: 0.5px; }
        .section-divider { border: none; border-top: 2px solid #eee; margin: 2.5rem 0 2rem 0; }
        .course-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 2rem; }
        .course-card { position: relative; display: flex; flex-direction: column; align-items: center; background: #fff; border-radius: 18px; box-shadow: 0 4px 24px rgba(0,0,0,0.07); padding: 1.2rem 1.2rem 1.5rem 1.2rem; margin-bottom: 0; transition: box-shadow 0.25s cubic-bezier(.4,2,.6,1), transform 0.18s cubic-bezier(.4,2,.6,1); cursor: pointer; overflow: hidden; }
        .course-card:hover { box-shadow: 0 12px 36px 0 #FFA72633, 0 2px 12px #0002; transform: translateY(-6px) scale(1.03); }
        .course-img { width: 72px; height: 72px; border-radius: 16px; overflow: hidden; background: #f0f0f0; margin-bottom: 1rem; display: flex; align-items: center; justify-content: center; transition: box-shadow 0.18s, transform 0.18s; }
        .course-card:hover .course-img { box-shadow: 0 0 0 4px #FFA72622; transform: scale(1.08); }
        .course-img img { width: 100%; height: 100%; object-fit: cover; border-radius: 16px; transition: transform 0.18s; }
        .course-title { font-size: 1.08rem; font-weight: 700; color: #23272b; margin-bottom: 0.5rem; text-align: center; min-height: 2.2em; display: flex; align-items: center; justify-content: center; }
        .course-meta { font-size: 0.85rem; color: #888; margin-bottom: 0.7rem; text-align: center; }
        .course-btn, .course-action-btn { font-size: 0.95rem; padding: 0.35rem 1.1rem; border-radius: 20px; background: #FFA726; color: #fff; border: none; font-weight: 600; transition: background 0.2s; text-align: center; display: inline-block; margin-top: 0.2rem; }
        .course-btn:hover, .course-action-btn:hover { background: #FFB13E; color: #23272b; }
        .course-action-btn { position: absolute; top: 1.1rem; right: 1.1rem; width: 36px; height: 36px; border-radius: 50%; padding: 0; display: flex; align-items: center; justify-content: center; background: #f5f6fa; color: #FFA726; box-shadow: 0 2px 8px #FFA72622; font-size: 1.2rem; border: none; transition: background 0.18s, color 0.18s; }
        .course-action-btn:hover { background: #FFA726; color: #fff; }
        .sidebar .card { background: #fff; border-radius: 16px; box-shadow: 0 2px 12px #0001; margin-bottom: 2rem; padding: 1.2rem; }
        .sidebar .card-header { font-weight: 700; color: #fff; font-size: 1.1rem; margin-bottom: 1rem; border-bottom: 1px solid #eee; padding-bottom: 0.5rem; background: none; }
        .sidebar .category-list { list-style: none; padding: 0; margin: 0; }
        .sidebar .category-list li { margin-bottom: 0.5rem; }
        .sidebar .category-list a { color: rgba(21, 15, 7, 1); font-weight: 600; text-decoration: none; transition: color 0.18s; }
        .sidebar .category-list a:hover { color: #FFB13E; }
        .sidebar .recent-list { list-style: none; padding: 0; margin: 0; }
        .sidebar .recent-list li { margin-bottom: 0.7rem; }
        .sidebar .recent-list a { color: #fff; font-weight: 600; text-decoration: none; }
        .sidebar .recent-list a:hover { color: #FFA726; }
        .sidebar .social-icons { display: flex; gap: 0.7rem; margin-top: 0.5rem; }
        .sidebar .social-icons a { color: #23272b; font-size: 1.3rem; transition: color 0.18s; }
        .sidebar .social-icons a:hover { color: #FFA726; }
        .sidebar .ad-block { background: #f0f0f0; border-radius: 12px; height: 120px; display: flex; align-items: center; justify-content: center; color: #bbb; font-size: 1.1rem; margin-top: 1.5rem; }
        .footer { background: #23272b; color: #fff; padding: 2rem 0 1rem 0; margin-top: 3rem; }
        .footer .container { max-width: 1200px; }
        .footer .copyright { text-align: center; color: #bbb; font-size: 0.98rem; margin-top: 1.2rem; }
        .sidebar-inline-section {
            display: flex;
            gap: 2rem;
            margin: 2.5rem 0 2.5rem 0;
            justify-content: center;
        }
        .sidebar-inline-section .card {
            min-width: 220px;
            max-width: 320px;
            flex: 1 1 220px;
        }
        @media (max-width: 1200px) {
            .course-grid { grid-template-columns: repeat(4, 1fr); }
        }
        @media (max-width: 991px) {
            .content-wrap { flex-direction: column; }
            .sidebar { width: 100%; max-width: 100%; margin-top: 2rem; }
            .course-grid { grid-template-columns: repeat(3, 1fr); gap: 1.2rem; }
            .sidebar-inline-section { flex-direction: column; gap: 1.2rem; }
            .sidebar-inline-section .card { max-width: 100%; }
        }
        @media (max-width: 600px) {
            .navbar .logo-text h1 { font-size: 1rem; }
            .navbar .logo-img { width: 32px; height: 32px; }
            .course-card { flex-direction: column; align-items: center; }
            .course-grid { grid-template-columns: repeat(2, 1fr); gap: 1rem; }
            .course-img { width: 48px; height: 48px; margin-bottom: 0.7rem; }
            .course-title { font-size: 0.98rem; }
            .course-btn { font-size: 0.85rem; padding: 0.25rem 0.7rem; }
        }
        .course-price {
            font-size: 1.05rem;
            font-weight: 700;
            color: #FFA726;
            margin-bottom: 0.5rem;
            text-align: center;
        }
        @media (max-width: 600px) {
            .course-price { font-size: 0.98rem; }
        }
        .mega-dropdown .dropdown-menu.mega-menu {
            left: 0 !important;
            right: 0 !important;
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
            border-radius: 16px;
            box-shadow: 0 8px 32px #0002;
            background: #fff;
            color: #23272b;
            border: none;
            padding: 1.5rem 2rem;
        }
        .mega-dropdown .dropdown-menu.mega-menu ul {
            padding-left: 0;
        }
        .mega-dropdown .dropdown-menu.mega-menu a.dropdown-item {
            color: #23272b;
            font-weight: 600;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            transition: background 0.15s, color 0.15s;
        }
        .mega-dropdown .dropdown-menu.mega-menu a.dropdown-item:hover {
            background: #FFA72622;
            color: #FFA726;
        }
        @media (max-width: 991px) {
            .mega-dropdown .dropdown-menu.mega-menu {
                max-width: 100%;
                padding: 1rem 0.5rem;
            }
        }
        .os-badge {
            position: absolute;
            top: 0.7rem;
            left: 0.7rem;
            background: #00b4d8;
            color: #fff;
            border-radius: 0.5em;
            padding: 0.2em 0.7em;
            font-size: 1.1em;
            font-weight: 700;
            display: flex;
            align-items: center;
            z-index: 2;
        }
        .rating-badge {
            position: absolute;
            top: 60px;
            left: 50%;
            transform: translateX(-50%);
            background: #4caf50;
            color: #fff;
            border-radius: 50%;
            font-size: 1.05em;
            font-weight: 700;
            padding: 0.3em 0.7em;
            box-shadow: 0 2px 8px #4caf5022;
            z-index: 3;
        }
        .course-category {
            display: inline-block;
            background: #e3f2fd;
            color: #00b4d8;
            font-size: 0.92rem;
            font-weight: 600;
            border-radius: 1em;
            padding: 0.2em 0.9em;
            margin-bottom: 0.7rem;
            margin-top: 0.1rem;
            text-align: center;
            letter-spacing: 0.01em;
        }
        .course-meta-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.2rem;
            font-size: 0.98rem;
            color: #888;
            margin-top: 0.5rem;
        }
        .meta-icon {
            margin-right: 0.3em;
            color: #00b4d8;
            font-size: 1.1em;
        }
        .mobile-custom-ui {
            min-height: 100vh;
            background: linear-gradient(135deg, #00c896 0%, #00b4d8 100%);
        }
        .mobile-custom-ui .top-nav-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.2rem;
            background: linear-gradient(90deg, #00c896 0%, #00b4d8 100%);
            box-shadow: 0 2px 8px #0001;
        }
        .mobile-custom-ui .top-nav-bar .logo {
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }
        .mobile-custom-ui .top-nav-bar .logo i {
            font-size: 1.7rem;
            color: #fff;
        }
        .mobile-custom-ui .top-nav-bar .logo span {
            font-weight: 700;
            font-size: 1.25rem;
            color: #fff;
            letter-spacing: 1px;
        }
        .mobile-custom-ui .top-nav-bar .close-btn {
            background: none;
            border: none;
            color: #fff;
            font-size: 1.7rem;
        }
        .mobile-custom-ui .main-content {
            display: flex;
            flex-direction: row;
            min-height: 80vh;
        }
        .mobile-custom-ui .left-menu {
            width: 44vw;
            max-width: 220px;
            background: #f8f9fa;
            border-top-right-radius: 1.2rem;
            border-bottom-right-radius: 1.2rem;
            box-shadow: 2px 0 12px #00b4d822;
            padding: 1.2rem 0.5rem 1.2rem 1.2rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .mobile-custom-ui .left-menu ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .mobile-custom-ui .left-menu ul li {
            margin-bottom: 0.5rem;
        }
        .mobile-custom-ui .left-menu ul li a {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            color: #00b4d8;
            font-weight: 700;
            font-size: 1.08rem;
            text-decoration: none;
        }
        .mobile-custom-ui .left-menu ul li a .plus-icon {
            margin-left: auto;
        }
        .mobile-custom-ui .right-card-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.2rem 0.5rem;
        }
        .mobile-custom-ui .right-card-section .card {
            background: #fff;
            border-radius: 1.2rem;
            box-shadow: 0 4px 24px #00b4d822;
            padding: 1.2rem 1.2rem 1.5rem 1.2rem;
            min-width: 0;
            max-width: 320px;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }
        .mobile-custom-ui .right-card-section .card .course-img {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            overflow: hidden;
            background: #f0f0f0;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            border: 4px solid #e3f2fd;
        }
        .mobile-custom-ui .right-card-section .card .course-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        .mobile-custom-ui .right-card-section .card .course-title {
            font-size: 1.08rem;
            font-weight: 700;
            color: #23272b;
            margin-bottom: 0.3rem;
            text-align: center;
            min-height: 2.2em;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }
        .mobile-custom-ui .right-card-section .card .course-meta-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.2rem;
            font-size: 0.98rem;
            color: #888;
            margin-top: 0.5rem;
        }
        .mobile-custom-ui .right-card-section .card .course-meta-row span {
            display: flex;
            align-items: center;
        }
        .mobile-custom-ui .right-card-section .card .course-meta-row .meta-icon {
            margin-right: 0.3em;
            color: #00b4d8;
            font-size: 1.1em;
        }
    </style>
</head>