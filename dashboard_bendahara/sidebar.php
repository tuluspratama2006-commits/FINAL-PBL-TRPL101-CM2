<?php
require_once '../config/koneksi.php';

// Cek apakah user sudah login
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit();
}

$role = $_SESSION['role'];
$current_page = basename($_SERVER['PHP_SELF']);

// Fetch user data from database
$id_user = $_SESSION['id_user'] ?? null;
$nama_lengkap = '';
$nik = '';
$rt_number = '';
$user_data = null;

if ($id_user) {
    $query_user = "SELECT u.username, u.nik, u.rt_number, u.role, u.email,
                        w.nama_lengkap as warga_nama
                    FROM user u
                    LEFT JOIN warga w ON u.id_warga = w.id_warga
                    WHERE u.id_user = '$id_user'";
    $result_user = mysqli_query($koneksi, $query_user);

    if ($result_user && mysqli_num_rows($result_user) > 0) {
        $user_data = mysqli_fetch_assoc($result_user);
        $nama_lengkap = $user_data['nama_lengkap'] ?: $user_data['warga_nama'] ?: $user_data['username'];
        $nik = $user_data['nik'];
        $rt_number = $user_data['rt_number'];

        // Update session with latest data
        $_SESSION['nama'] = $nama_lengkap;
        $_SESSION['nik'] = $nik;
        $_SESSION['rt_number'] = $rt_number;
        $role = $user_data['role'] ?? $role; // Update role from DB if available
    }
}

// Fungsi untuk menentukan apakah menu aktif
function isActive($page, $current_page) {
    return $page == $current_page ? 'active' : '';
}

// Tentukan judul section berdasarkan halaman
$section_title = 'Dashboard';
if (strpos($current_page, 'iuran') !== false || strpos($current_page, 'bayar_iuran') !== false) {
    $section_title = 'Iuran';
} elseif (strpos($current_page, 'pengeluaran') !== false) {
    $section_title = 'Pengeluaran';
} elseif (strpos($current_page, 'laporan') !== false) {
    $section_title = 'Laporan';
} elseif (strpos($current_page, 'warga') !== false && $current_page !== 'dashboard_warga.php') {
    $section_title = 'Warga';
} elseif (strpos($current_page, 'pengaturan') !== false) {
    $section_title = 'Pengaturan';
}
?>

<style>
    /* Top Bar */
    .top-bar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 60px;
        background: #072f66;
        color: white;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 20px;
        z-index: 1000;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .top-bar-left {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .hamburger-btn {
        background: none;
        border: none;
        color: white;
        font-size: 1.5rem;
        cursor: pointer;
        padding: 8px;
        border-radius: 5px;
        transition: all 0.3s ease;
        transform: scale(1);
    }

    .hamburger-btn:hover {
        background: rgba(255,255,255,0.1);
        transform: scale(1.1);
    }

    .hamburger-btn:active {
        transform: scale(0.95);
        transition: transform 0.1s ease;
    }

    .expand-btn {
        background: none;
        border: none;
        color: white;
        font-size: 1.2rem;
        cursor: pointer;
        padding: 8px;
        border-radius: 5px;
        transition: background 0.3s;
        margin-left: auto;
    }

    .expand-btn:hover {
        background: rgba(255,255,255,0.1);
    }

    .logo-container {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .logo-text {
        font-weight: 600;
        font-size: 18px;
        line-height: 1.1;
        color: white;
    }

    .top-bar-center {
        flex: 1;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 20px;
    }

    .page-title {
        font-size: 20px;
        font-weight: 600;
        color: white;
    }

    .search-container .input-group {
        width: 200px;
    }

    .search-container .btn-outline-secondary {
        background: rgba(255,255,255,0.1);
        border: 1px solid rgba(255,255,255,0.2);
        color: rgba(255,255,255,0.7);
        border-radius: 20px 0 0 20px;
    }

    .search-container .btn-outline-secondary:hover {
        background: rgba(255,255,255,0.2);
        color: white;
    }

    .search-container .form-control {
        border: 1px solid rgba(255,255,255,0.2);
        border-left: none;
        border-radius: 0 20px 20px 0;
        background: rgba(255,255,255,0.1);
        color: white;
        font-size: 14px;
        transition: all 0.3s;
    }

    .search-container .form-control::placeholder {
        color: rgba(255,255,255,0.7);
    }

    .search-container .form-control:focus {
        outline: none;
        background: rgba(255,255,255,0.15);
        border-color: rgba(255,255,255,0.3);
        color: white;
    }

    .top-bar-right {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .search-container {
        position: relative;
        width: 200px;
    }

    .notification-btn {
        background: none;
        border: none;
        color: white;
        font-size: 1.2rem;
        cursor: pointer;
        padding: 8px;
        border-radius: 50%;
        transition: background 0.3s;
        position: relative;
    }

    .notification-btn:hover {
        background: rgba(255,255,255,0.1);
    }

    .notification-badge {
        position: absolute;
        top: 5px;
        right: 5px;
        width: 8px;
        height: 8px;
        background: #dc3545;
        border-radius: 50%;
    }

    /* Notification Dropdown */
    .notification-dropdown {
        position: absolute;
        top: 50px;
        right: 0;
        width: 300px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 1001;
        display: none;
        max-height: 400px;
        overflow-y: auto;
    }

    .notification-dropdown.show {
        display: block;
    }

    .notification-header {
        padding: 15px;
        border-bottom: 1px solid #eee;
        font-weight: 600;
        color: #072f66;
    }

    .notification-item {
        padding: 12px 15px;
        border-bottom: 1px solid #f8f9fa;
        cursor: pointer;
        transition: background 0.2s;
    }

    .notification-item:hover {
        background: #f8f9fa;
    }

    .notification-item:last-child {
        border-bottom: none;
    }

    .notification-title {
        font-size: 14px;
        font-weight: 500;
        color: #072f66;
        margin-bottom: 2px;
    }

    .notification-text {
        font-size: 12px;
        color: #6c757d;
    }

    .notification-time {
        font-size: 11px;
        color: #adb5bd;
        margin-top: 2px;
    }

    .notification-empty {
        padding: 20px;
        text-align: center;
        color: #6c757d;
        font-size: 14px;
    }

    .font-weight-bold {
        font-weight: bold !important;
    }

    .profile-container {
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        padding: 5px 10px;
        border-radius: 20px;
        transition: background 0.3s;
    }

    .profile-container:hover {
        background: rgba(255,255,255,0.1);
    }

    .profile-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: white;
        color: #072f66;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 12px;
    }

    .profile-name {
        font-size: 14px;
        font-weight: 500;
    }

    /* Sidebar */
    .sidebar {
        width: 260px;
        height: 100vh;
        position: fixed;
        left: 0;
        top: 60px;
        background: #072f66;
        color: white;
        padding: 0;
        z-index: 999;
        box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        transform: translateX(-100%);
        overflow-y: auto;
        backdrop-filter: blur(10px);
        transition: transform 0.4s cubic-bezier(0.4, 0.0, 0.2, 1), width 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
        will-change: transform, width;
    }

    .sidebar.open {
        transform: translateX(0);
    }

    .sidebar.expanded {
        width: 400px;
    }



    .sidebar-content {
        padding: 20px;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    /* Sidebar Header */
    .sidebar-header {
        margin-bottom: 20px;
        text-align: center;
    }

    .app-title {
        font-size: 18px;
        font-weight: 700;
        color: white;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .app-title img {
        width: 50px;
        height: auto;
    }

    .section-title {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 16px;
        font-weight: 600;
        color: #4dabf7;
    }

    .section-title img {
        width: 24px;
        height: 24px;
    }

    /* Menu Styles */
    .menu {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .menu a {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 15px;
        border-radius: 8px;
        color: white;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s;
        font-size: 14px;
    }

    .menu a:hover {
        background: #0b3f77;
    }

    .menu a.active {
        background: #0b3f77;
        border-left: 4px solid #4dabf7;
    }

    .menu a i {
        font-size: 1.2rem;
        width: 24px;
        text-align: center;
    }

    /* User Info */
    .user-info {
        margin-top: auto;
        padding: 15px 0;
        border-top: 1px solid rgba(255,255,255,0.1);
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: white;
        color: #072f66;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 14px;
    }

    .user-details {
        flex: 1;
    }

    .user-name {
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 2px;
    }

    .user-role {
        font-size: 12px;
        color: rgba(255,255,255,0.7);
    }

    /* Logout Button */
    .logout-btn {
        margin-top: 15px;
    }

    .logout-btn a {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 10px;
        background: white;
        color: #072f66;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s;
        width: 100%;
    }

    .logout-btn a:hover {
        background: #f8f9fa;
    }

    /* Overlay untuk mobile */
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 60px;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 998;
    }

    .sidebar-overlay.active {
        display: block;
    }

    /* Responsive */
    @media (min-width: 769px) {
        .sidebar {
            top: 0;
        }

        .sidebar.open {
            transform: translateX(0);
        }

        .main-content {
            transition: margin-left 0.3s ease-in-out, opacity 0.3s ease-in-out;
        }

        .main-content.sidebar-open {
            margin-left: 260px;
        }

        .main-content.sidebar-open.expanded {
            margin-left: 400px;
        }

        .main-content.fade-in {
            opacity: 1;
        }

        .main-content.fade-out {
            opacity: 0.7;
        }

        .top-bar {
            transition: left 0.3s ease-in-out;
        }

        .top-bar.sidebar-open {
            left: 260px;
        }

        .top-bar.sidebar-open.expanded {
            left: 400px;
        }
    }

    @media (max-width: 768px) {
        .expand-btn {
            display: none;
        }

        .sidebar {
            width: 280px;
            top: 60px;
        }

        .main-content {
            margin-top: 60px;
        }
    }

    /* Scrollbar */
    .sidebar::-webkit-scrollbar {
        width: 5px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: rgba(255,255,255,0.1);
        border-radius: 10px;
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.3);
        border-radius: 10px;
    }

    .sidebar::-webkit-scrollbar-thumb:hover {
        background: rgba(255,255,255,0.4);
    }

    /* Smooth Navigation Transitions */
    .menu a {
        position: relative;
        overflow: hidden;
    }

    .menu a::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
        transition: left 0.5s;
    }

    .menu a:hover::before {
        left: 100%;
    }

    /* Active state animation */
    .menu a.active {
        transform: translateX(5px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }


</style>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const mainContent = document.querySelector('.main-content');
    const topBar = document.getElementById('topBar');

    if (sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        if (mainContent) mainContent.classList.remove('sidebar-open');
        if (topBar) topBar.classList.remove('sidebar-open');
        localStorage.setItem('sidebarOpen', 'false');
    } else {
        sidebar.classList.add('open');
        overlay.classList.add('active');
        if (mainContent) mainContent.classList.add('sidebar-open');
        if (topBar) topBar.classList.add('sidebar-open');
        localStorage.setItem('sidebarOpen', 'true');
    }
}

function toggleExpand() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    const topBar = document.getElementById('topBar');
    const expandBtn = document.querySelector('.expand-btn i');

    if (sidebar.classList.contains('expanded')) {
        sidebar.classList.remove('expanded');
        if (mainContent) mainContent.classList.remove('expanded');
        if (topBar) topBar.classList.remove('expanded');
        expandBtn.className = 'bi bi-chevron-right';
        localStorage.setItem('sidebarExpanded', 'false');
    } else {
        sidebar.classList.add('expanded');
        if (mainContent) mainContent.classList.add('expanded');
        if (topBar) topBar.classList.add('expanded');
        expandBtn.className = 'bi bi-chevron-left';
        localStorage.setItem('sidebarExpanded', 'true');
    }
}

function toggleNotification() {
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.classList.toggle('show');
}

function markAsRead(idNotification) {
    fetch('../backend/notifications/mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id_notification=' + idNotification
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove bold styling from the clicked notification
            const notificationItem = event.target.closest('.notification-item');
            if (notificationItem) {
                notificationItem.classList.remove('font-weight-bold');
            }
            // Update notification badge
            updateNotificationBadge();
        } else {
            console.error('Failed to mark notification as read:', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function updateNotificationBadge() {
    fetch('../backend/notifications/get_unread_count.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const badge = document.querySelector('.notification-badge');
            if (data.count > 0) {
                if (!badge) {
                    const notificationBtn = document.querySelector('.notification-btn');
                    const newBadge = document.createElement('span');
                    newBadge.className = 'notification-badge';
                    notificationBtn.appendChild(newBadge);
                }
            } else {
                if (badge) {
                    badge.remove();
                }
            }
        }
    })
    .catch(error => {
        console.error('Error updating badge:', error);
    });
}

// Close notification dropdown when clicking outside
document.addEventListener('click', function(event) {
    const notificationContainer = document.querySelector('.notification-container');
    const dropdown = document.getElementById('notificationDropdown');

    if (!notificationContainer.contains(event.target)) {
        dropdown.classList.remove('show');
    }
});

// Add click handlers to menu links
document.addEventListener('DOMContentLoaded', function() {
    // Load sidebar state on page load
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const mainContent = document.querySelector('.main-content');
    const topBar = document.getElementById('topBar');
    const expandBtn = document.querySelector('.expand-btn i');

    // Default to closed on desktop, open on mobile
    const isDesktop = window.innerWidth >= 769;
    const defaultOpen = isDesktop ? false : true;
    const sidebarOpen = localStorage.getItem('sidebarOpen') !== null ? localStorage.getItem('sidebarOpen') === 'true' : defaultOpen;
    const sidebarExpanded = localStorage.getItem('sidebarExpanded') === 'true'; // Default to false

    if (sidebarOpen) {
        sidebar.classList.add('open');
        overlay.classList.add('active');
        if (mainContent) mainContent.classList.add('sidebar-open');
        if (topBar) topBar.classList.add('sidebar-open');
    } else {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        if (mainContent) mainContent.classList.remove('sidebar-open');
        if (topBar) topBar.classList.remove('sidebar-open');
    }

    if (sidebarExpanded) {
        sidebar.classList.add('expanded');
        if (mainContent) mainContent.classList.add('expanded');
        if (topBar) topBar.classList.add('expanded');
        if (expandBtn) expandBtn.className = 'bi bi-chevron-left';
    } else {
        sidebar.classList.remove('expanded');
        if (mainContent) mainContent.classList.remove('expanded');
        if (topBar) topBar.classList.remove('expanded');
        if (expandBtn) expandBtn.className = 'bi bi-chevron-right';
    }

    // Add keyboard navigation support
    document.addEventListener('keydown', function(e) {
        const sidebar = document.getElementById('sidebar');
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            toggleSidebar();
        }
    });

    // Add focus management for accessibility
    const menuItems = document.querySelectorAll('.menu a');
    menuItems.forEach(function(item, index) {
        item.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                const nextItem = menuItems[index + 1] || menuItems[0];
                nextItem.focus();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                const prevItem = menuItems[index - 1] || menuItems[menuItems.length - 1];
                prevItem.focus();
            } else if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });

    // Close sidebar when clicking outside on desktop
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const hamburger = document.querySelector('.hamburger-btn');
        const isDesktop = window.innerWidth >= 769;
        if (isDesktop && !sidebar.contains(event.target) && !hamburger.contains(event.target) && sidebar.classList.contains('open')) {
            toggleSidebar();
        }
    });
});
</script>

<!-- Top Bar (Selalu tampil) -->
<div class="top-bar" id="topBar">
    <div class="top-bar-left">
        <button class="hamburger-btn" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>
        <div class="logo-container">
            <img src="../img/akurad1.png" alt="Logo" width="40" height="auto">
            <div class="logo-text"><?php echo $section_title; ?></div>
        </div>
    </div>
    <div class="top-bar-right">
        <div class="search-container">
            <div class="input-group">
                <button class="btn btn-outline-secondary" type="button">
                    <i class="bi bi-search"></i>
                </button>
                <input type="text" class="form-control" placeholder="Cari...">
            </div>
        </div>
        <div class="notification-container" style="position: relative;">
            <button class="notification-btn" onclick="toggleNotification()">
                <i class="bi bi-bell"></i>
                <?php
                // Count unread notifications with error handling
                $unread_count = 0;
                if ($id_user) {
                    $query_count = "SELECT COUNT(*) as count FROM notifications WHERE id_user = '$id_user' AND is_read = 0";
                    $result_count = mysqli_query($koneksi, $query_count);
                    if ($result_count && mysqli_num_rows($result_count) > 0) {
                        $count_data = mysqli_fetch_assoc($result_count);
                        $unread_count = $count_data['count'];
                    }
                }
                if ($unread_count > 0) {
                    echo '<span class="notification-badge"></span>';
                }
                ?>
            </button>
            <div class="notification-dropdown" id="notificationDropdown">
                <div class="notification-header">
                    Notifikasi
                </div>
                <?php
                // Fetch notifications with error handling
                if ($id_user) {
                    $query_notifications = "SELECT * FROM notifications WHERE id_user = '$id_user' ORDER BY created_at DESC LIMIT 10";
                    $result_notifications = mysqli_query($koneksi, $query_notifications);
                    if ($result_notifications && mysqli_num_rows($result_notifications) > 0) {
                        while ($notification = mysqli_fetch_assoc($result_notifications)) {
                            $is_read_class = $notification['is_read'] ? '' : 'font-weight-bold';
                            echo '<div class="notification-item ' . $is_read_class . '" onclick="markAsRead(' . $notification['id_notification'] . ')">';
                            echo '<div class="notification-title">' . htmlspecialchars($notification['title']) . '</div>';
                            echo '<div class="notification-text">' . htmlspecialchars($notification['message']) . '</div>';
                            echo '<div class="notification-time">' . date('d/m/Y H:i', strtotime($notification['created_at'])) . '</div>';
                            echo '</div>';
                        }
                    } else {
                        echo '<div class="notification-empty">Tidak ada notifikasi baru</div>';
                    }
                } else {
                    echo '<div class="notification-empty">Tidak ada notifikasi baru</div>';
                }
                ?>
            </div>
        </div>
        <div class="profile-container">
            <div class="profile-avatar">
                <?php
                if ($role === 'RT') echo 'RT';
                elseif ($role === 'Bendahara') echo 'B';
                elseif ($role === 'warga') echo substr($_SESSION['nama'] ?? 'W', 0, 1);
                else echo 'U';
                ?>
            </div>
            <div class="profile-name">
                <?php echo $_SESSION['nama'] ?? 'User'; ?>
            </div>
        </div>
    </div>
</div>

<!-- Overlay untuk mobile -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-content">
        <!-- Sidebar Header -->
        <div class="sidebar-header">
            <div class="app-title">
                <img src="../img/akurad1.png" alt="Logo"> AKURAD.APP
            </div>
        </div>

        <!-- Menu -->
        <nav class="menu">
            <?php if ($role === 'RT'): ?>
                <a href="rt.php" class="<?php echo isActive('rt.php', $current_page); ?>" onclick="toggleSidebar()">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="iuranrt.php" class="<?php echo isActive('iuranrt.php', $current_page); ?>" onclick="toggleSidebar()">
                    <i class="bi bi-cash-stack"></i> Iuran
                </a>
                <a href="pengeluaranrt.php" class="<?php echo isActive('pengeluaranrt.php', $current_page); ?>" onclick="toggleSidebar()">
                    <i class="bi bi-credit-card"></i> Pengeluaran
                </a>
                <a href="laporanbulanan.php" class="<?php echo isActive('laporanbulanan.php', $current_page); ?>" onclick="toggleSidebar()">
                    <i class="bi bi-file-earmark-bar-graph"></i> Laporan
                </a>
                <a href="warga.php" class="<?php echo isActive('warga.php', $current_page); ?>" onclick="toggleSidebar()">
                    <i class="bi bi-people"></i> Warga
                </a>
                <a href="pengaturanrt.php" class="<?php echo isActive('pengaturanrt.php', $current_page); ?>" onclick="toggleSidebar()">
                    <i class="bi bi-gear"></i> Pengaturan
                </a>
            <?php elseif ($role === 'Bendahara'): ?>
                <a href="bendahara.php" class="<?php echo isActive('bendahara.php', $current_page); ?>" onclick="toggleSidebar()">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="iuranbendahara.php" class="<?php echo isActive('iuranbendahara.php', $current_page); ?>" onclick="toggleSidebar()">
                    <i class="bi bi-cash-stack"></i> Iuran
                </a>
                <a href="pengeluaranbendahara.php" class="<?php echo isActive('pengeluaranbendahara.php', $current_page); ?>" onclick="toggleSidebar()">
                    <i class="bi bi-credit-card"></i> Pengeluaran
                </a>
                <a href="laporanbulanan.php" class="<?php echo isActive('laporanbulanan.php', $current_page); ?>" onclick="toggleSidebar()">
                    <i class="bi bi-file-earmark-bar-graph"></i> Laporan
                </a>
                <a href="warga.php" class="<?php echo isActive('warga.php', $current_page); ?>" onclick="toggleSidebar()">
                    <i class="bi bi-people"></i> Warga
                </a>
                <a href="pengaturanrt.php" class="<?php echo isActive('pengaturanrt.php', $current_page); ?>" onclick="toggleSidebar()">
                    <i class="bi bi-gear"></i> Pengaturan
                </a>
            <?php elseif ($role === 'warga'): ?>
                <a href="warga.php" class="<?php echo isActive('warga.php', $current_page); ?>" onclick="toggleSidebar()">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="bayariuranwarga.php" class="<?php echo isActive('bayariuranwarga.php', $current_page); ?>" onclick="toggleSidebar()">
                    <i class="bi bi-credit-card"></i> Bayar Iuran
                </a>
                <a href="iuranwarga.php" class="<?php echo isActive('iuranwarga.php', $current_page); ?>" onclick="toggleSidebar()">
                    <i class="bi bi-cash-stack"></i> Iuran
                </a>
                <a href="pengeluaranwarga.php" class="<?php echo isActive('pengeluaranwarga.php', $current_page); ?>" onclick="toggleSidebar()">
                    <i class="bi bi-credit-card"></i> Pengeluaran
                </a>
                <a href="laporanbulanan.php" class="<?php echo isActive('laporanbulanan.php', $current_page); ?>" onclick="toggleSidebar()">
                    <i class="bi bi-file-earmark-bar-graph"></i> Laporan
                </a>
                <a href="pengaturanwarga.php" class="<?php echo isActive('pengaturanwarga.php', $current_page); ?>" onclick="toggleSidebar()">
                    <i class="bi bi-gear"></i> Pengaturan
                </a>
            <?php endif; ?>
            </nav>

        <!-- User Info -->
        <div class="user-info">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div class="user-avatar">
                    <?php
                    if ($role === 'RT') echo 'RT';
                    elseif ($role === 'Bendahara') echo 'B';
                    elseif ($role === 'warga') echo substr($_SESSION['nama'] ?? 'W', 0, 1);
                    ?>
                </div>
                <div class="user-details">
                    <div class="user-name">
                        <?php echo $_SESSION['nama'] ?? 'Pengguna'; ?>
                    </div>
                    <div class="user-role">
                        <?php
                        if ($role === 'RT') echo 'Ketua RT';
                        elseif ($role === 'Bendahara') echo 'Bendahara';
                        elseif ($role === 'warga') echo 'Warga';
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Logout Button -->
            <div class="logout-btn">
                <a href="logout.php">
                    <i class="bi bi-box-arrow-left"></i>Logout
                </a>
            </div>
        </div>
    </div>
</aside>
