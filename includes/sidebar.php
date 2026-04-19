<?php
// Dynamic path handling for correct linking from any subfolder
function get_relative_prefix() {
    $script_name = $_SERVER['SCRIPT_NAME'];
    $path = ltrim($script_name, '/');
    $segments = explode('/', $path);
    $depth = count($segments) - 1;
    return str_repeat('../', $depth);
}
$prefix = get_relative_prefix();
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir_name = basename(dirname($_SERVER['PHP_SELF']));

$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['user_role'] ?? '';
$user_avatar = strtoupper(substr($user_name, 0, 1));
?>
<aside class="sidebar">
    <!-- Logo Section -->
    <div class="sidebar-logo">
        <div class="logo-icon">
            <i class="fas fa-heartbeat"></i>
        </div>
        <div class="logo-text">
            <span class="clinic-name">VeeCare</span>
            <span class="clinic-sub">Medical Centre</span>
        </div>
    </div>

    <!-- User Profile Section -->
    <div class="sidebar-user">
        <div class="user-avatar"><?php echo $user_avatar; ?></div>
        <div class="user-details">
            <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
            <div class="user-role"><?php echo ucfirst(htmlspecialchars($user_role)); ?></div>
        </div>
        <a href="<?php echo $prefix; ?>logout.php" class="logout-icon"><i class="fas fa-sign-out-alt"></i></a>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <ul class="nav-menu">
            <!-- Dashboard -->
            <li class="nav-item">
                <a href="<?php echo $prefix; ?>dashboard.php" class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-divider"></li>

            <!-- Patients -->
            <li class="nav-category">Patients</li>
            <li class="nav-item">
                <a href="<?php echo $prefix; ?>patients/view_patients.php" class="nav-link">
                    <i class="fas fa-users"></i> <span>All Patients</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo $prefix; ?>patients/add_patient.php" class="nav-link">
                    <i class="fas fa-user-plus"></i> <span>Add Patient</span>
                </a>
            </li>
            <li class="nav-divider"></li>

            <!-- Appointments -->
            <li class="nav-category">Appointments</li>
            <li class="nav-item">
                <a href="<?php echo $prefix; ?>appointments/calendar.php" class="nav-link">
                    <i class="fas fa-calendar-alt"></i> <span>Calendar</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo $prefix; ?>appointments/add_appointment.php" class="nav-link">
                    <i class="fas fa-calendar-plus"></i> <span>Schedule</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo $prefix; ?>appointments/view_appointments.php" class="nav-link">
                    <i class="fas fa-list"></i> <span>All Appointments</span>
                </a>
            </li>
            <li class="nav-divider"></li>

            <!-- Clinical -->
            <li class="nav-category">Clinical</li>
            <li class="nav-item">
                <a href="<?php echo $prefix; ?>prescriptions/view_prescriptions.php" class="nav-link">
                    <i class="fas fa-prescription-bottle"></i> <span>Prescriptions</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo $prefix; ?>treatments/diagnosis.php" class="nav-link">
                    <i class="fas fa-notes-medical"></i> <span>Diagnosis</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo $prefix; ?>treatments/procedures.php" class="nav-link">
                    <i class="fas fa-syringe"></i> <span>Procedures</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo $prefix; ?>treatments/notes.php" class="nav-link">
                    <i class="fas fa-file-alt"></i> <span>Clinical Notes</span>
                </a>
            </li>
            <li class="nav-divider"></li>

            <!-- Billing -->
            <li class="nav-category">Billing</li>
            <li class="nav-item">
                <a href="<?php echo $prefix; ?>billing/invoices.php" class="nav-link">
                    <i class="fas fa-file-invoice-dollar"></i> <span>Invoices</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo $prefix; ?>billing/payments.php" class="nav-link">
                    <i class="fas fa-credit-card"></i> <span>Payments</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo $prefix; ?>billing/receipts.php" class="nav-link">
                    <i class="fas fa-receipt"></i> <span>Receipts</span>
                </a>
            </li>
            <li class="nav-divider"></li>

            <!-- Reports -->
            <li class="nav-category">Reports</li>
            <li class="nav-item">
                <a href="<?php echo $prefix; ?>reports/daily_reports.php" class="nav-link">
                    <i class="fas fa-chart-line"></i> <span>Daily Report</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo $prefix; ?>reports/patient_reports.php" class="nav-link">
                    <i class="fas fa-users"></i> <span>Patient Report</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo $prefix; ?>reports/financial_reports.php" class="nav-link">
                    <i class="fas fa-chart-pie"></i> <span>Financial Report</span>
                </a>
            </li>
            <li class="nav-divider"></li>

            <!-- Settings -->
            <li class="nav-category">Settings</li>
            <li class="nav-item">
                <a href="<?php echo $prefix; ?>settings/clinic_settings.php" class="nav-link">
                    <i class="fas fa-building"></i> <span>Clinic Settings</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo $prefix; ?>settings/profile.php" class="nav-link">
                    <i class="fas fa-user-circle"></i> <span>Profile</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo $prefix; ?>settings/users.php" class="nav-link">
                    <i class="fas fa-users-cog"></i> <span>Users</span>
                </a>
            </li>
            <li class="nav-divider"></li>

            <!-- Logout -->
            <li class="nav-item">
                <a href="<?php echo $prefix; ?>logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="system-status">
            <i class="fas fa-shield-alt"></i>
            <span>Secure Connection</span>
        </div>
        <div class="version">
            <i class="fas fa-code-branch"></i>
            <span>v3.0.0</span>
        </div>
    </div>
</aside>

<style>
/* Google Fonts Import */
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap');

/* Sidebar Container */
.sidebar {
    width: 280px;
    background: linear-gradient(165deg, #0a0f1c 0%, #0f1622 50%, #131a2a 100%);
    color: #ffffff;
    position: fixed;
    left: 0;
    top: 0;
    height: 100vh;
    overflow-y: auto;
    z-index: 100;
    display: flex;
    flex-direction: column;
    box-shadow: 4px 0 25px rgba(0,0,0,0.15);
    border-right: 1px solid rgba(10,132,255,0.15);
}

/* Custom Scrollbar */
.sidebar::-webkit-scrollbar {
    width: 4px;
}
.sidebar::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.05);
}
.sidebar::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #0A84FF, #34C759);
    border-radius: 4px;
}

/* Logo Section */
.sidebar-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 32px 24px;
    border-bottom: 1px solid rgba(10,132,255,0.15);
    margin-bottom: 8px;
}
.logo-icon i {
    font-size: 32px;
    background: linear-gradient(135deg, #0A84FF, #34C759);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    filter: drop-shadow(0 2px 4px rgba(10,132,255,0.3));
}
.logo-text {
    display: flex;
    flex-direction: column;
}
.clinic-name {
    font-size: 22px;
    font-weight: 800;
    font-family: 'Space Grotesk', sans-serif;
    background: linear-gradient(135deg, #ffffff, #0A84FF);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    letter-spacing: 0.5px;
}
.clinic-sub {
    font-size: 10px;
    color: rgba(255,255,255,0.5);
    letter-spacing: 1px;
    margin-top: 2px;
}

/* User Profile Section */
.sidebar-user {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    background: linear-gradient(135deg, rgba(10,132,255,0.12), rgba(52,199,89,0.06));
    margin: 16px;
    border-radius: 60px;
    border: 1px solid rgba(10,132,255,0.25);
    backdrop-filter: blur(10px);
}
.user-avatar {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, #0A84FF, #34C759);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 18px;
    color: white;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(10,132,255,0.3);
}
.user-details {
    flex: 1;
}
.user-name {
    font-size: 15px;
    font-weight: 700;
    font-family: 'Space Grotesk', sans-serif;
    line-height: 1.2;
    color: #ffffff;
}
.user-role {
    font-size: 11px;
    color: #0A84FF;
    font-weight: 600;
    letter-spacing: 0.5px;
}
.logout-icon {
    color: rgba(255,255,255,0.6);
    font-size: 18px;
    transition: 0.2s;
    flex-shrink: 0;
}
.logout-icon:hover {
    color: #FF3B30;
    transform: scale(1.1);
}

/* Navigation */
.sidebar-nav {
    flex: 1;
    padding: 8px 12px;
}
.nav-menu {
    list-style: none;
}
.nav-item {
    margin-bottom: 4px;
}
.nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    color: rgba(255,255,255,0.7);
    text-decoration: none;
    border-radius: 12px;
    transition: all 0.3s ease;
    font-size: 14px;
    font-weight: 500;
    font-family: 'Plus Jakarta Sans', sans-serif;
    position: relative;
    overflow: hidden;
}
.nav-link::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    width: 0;
    height: 100%;
    background: linear-gradient(90deg, rgba(10,132,255,0.2), transparent);
    transition: width 0.3s ease;
}
.nav-link:hover::before {
    width: 100%;
}
.nav-link i {
    width: 22px;
    font-size: 16px;
    color: rgba(255,255,255,0.6);
    transition: all 0.2s;
    position: relative;
    z-index: 1;
}
.nav-link span {
    position: relative;
    z-index: 1;
}
.nav-link:hover {
    color: #0A84FF;
    transform: translateX(4px);
}
.nav-link:hover i {
    color: #0A84FF;
}
.nav-link.active {
    background: linear-gradient(135deg, rgba(10,132,255,0.2), rgba(52,199,89,0.08));
    color: #0A84FF;
    border-left: 3px solid #0A84FF;
}
.nav-link.active i {
    color: #0A84FF;
}
.nav-divider {
    height: 1px;
    background: linear-gradient(90deg, rgba(10,132,255,0.3), transparent);
    margin: 12px 0;
}
.nav-category {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: rgba(255,255,255,0.4);
    padding: 10px 12px;
    margin-top: 8px;
    font-weight: 700;
}

/* Sidebar Footer */
.sidebar-footer {
    padding: 16px 20px;
    border-top: 1px solid rgba(10,132,255,0.1);
    font-size: 11px;
    display: flex;
    justify-content: space-between;
    color: rgba(255,255,255,0.5);
    background: rgba(0,0,0,0.2);
}
.system-status i, .version i {
    margin-right: 6px;
}
.system-status i {
    color: #34C759;
}

/* Responsive */
@media (max-width: 1024px) {
    .sidebar {
        width: 80px;
    }
    .logo-text, .user-details, .nav-link span, .nav-category, .sidebar-footer span {
        display: none;
    }
    .sidebar-user {
        justify-content: center;
        padding: 12px;
    }
    .nav-link {
        justify-content: center;
        padding: 12px;
    }
    .sidebar-logo {
        justify-content: center;
    }
}
</style>