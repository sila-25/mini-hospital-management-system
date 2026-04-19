<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../config/database.php';
$conn = getConnection();

$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDayOfMonth);
$startWeekday = date('N', $firstDayOfMonth);

$startDate = "$year-$month-01";
$endDate = "$year-$month-$daysInMonth";
$appointments = [];
$sql = "SELECT a.*, p.full_name as patient_name, p.patient_id, u.full_name as doctor_name 
        FROM appointments a 
        JOIN patients p ON a.patient_id = p.id 
        LEFT JOIN users u ON a.doctor_id = u.id 
        WHERE a.appointment_date BETWEEN ? AND ? 
        ORDER BY a.appointment_date, a.appointment_time";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $appointments[$row['appointment_date']][] = $row;
}
$stmt->close();

$calendarDays = [];
$startPadding = $startWeekday - 1;
for ($i = 0; $i < $startPadding; $i++) { $calendarDays[] = null; }
for ($d = 1; $d <= $daysInMonth; $d++) {
    $calendarDays[] = $d;
}
$remaining = 42 - count($calendarDays);
for ($i = 0; $i < $remaining; $i++) { $calendarDays[] = null; }
$weeks = array_chunk($calendarDays, 7);

$statusColors = [
    'scheduled' => '#0A84FF',
    'completed' => '#34C759',
    'cancelled' => '#FF3B30',
    'no_show' => '#FF9500'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar | VeeCare</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #eef2f8; }
        
        /* Sidebar (same as before) */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #0f0f1a 0%, #1a1a2e 100%);
            color: #fff;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
        }
        .sidebar-header { padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h2 { font-size: 20px; display: flex; align-items: center; gap: 10px; }
        .sidebar-header h2 i { color: #0A84FF; }
        .sidebar-header p { font-size: 11px; color: rgba(255,255,255,0.5); margin-top: 5px; }
        .sidebar-nav { padding: 20px; }
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s;
            font-size: 14px;
        }
        .nav-link:hover, .nav-link.active { background: rgba(10,132,255,0.15); color: #0A84FF; }
        .nav-link i { width: 20px; }
        .nav-divider { height: 1px; background: rgba(255,255,255,0.08); margin: 12px 0; }
        .nav-category { font-size: 10px; text-transform: uppercase; color: rgba(255,255,255,0.4); padding: 8px 12px; margin-top: 8px; }
        
        .main-content { margin-left: 260px; padding: 24px; }
        
        /* Header */
        .page-header {
            background: #fff;
            border-radius: 20px;
            padding: 20px 28px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .page-header h1 { font-size: 24px; font-weight: 700; color: #1a1a2e; }
        .page-header p { font-size: 13px; color: #5a6e8a; margin-top: 4px; }
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #f0f2f5;
            color: #4a627a;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-back:hover { background: #e4e8ef; transform: translateX(-2px); }
        
        /* Calendar Navigation */
        .calendar-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            background: #fff;
            border-radius: 20px;
            padding: 12px 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .nav-buttons a {
            background: #f0f2f5;
            padding: 8px 18px;
            border-radius: 30px;
            text-decoration: none;
            color: #0A84FF;
            font-weight: 600;
            font-size: 14px;
            margin: 0 5px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .nav-buttons a:hover { background: #e4e8ef; transform: translateY(-1px); }
        .calendar-nav h2 { font-size: 1.7rem; font-weight: 700; color: #1a1a2e; }
        .new-appt-btn {
            background: linear-gradient(135deg, #0A84FF, #006EDB);
            color: white !important;
            box-shadow: 0 2px 8px rgba(10,132,255,0.2);
        }
        .new-appt-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 14px rgba(10,132,255,0.3); }
        
        /* Calendar Grid - Highly Visible Date Boxes */
        .calendar {
            background: #ffffff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
        }
        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
        }
        .calendar-weekday {
            padding: 16px 8px;
            text-align: center;
            font-weight: 800;
            font-size: 13px;
            color: #334155;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background: #ffffff;
        }
        .calendar-day {
            min-height: 130px;
            background: #ffffff;
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 10px;
            transition: all 0.2s ease;
            position: relative;
        }
        .calendar-day:nth-child(7n) { border-right: none; }
        .calendar-day.empty {
            background: #fefefe;
            border-bottom: 1px solid #e2e8f0;
        }
        .calendar-day:not(.empty):hover {
            background: #f0f9ff;
            transform: scale(1.01);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            z-index: 2;
            border-radius: 12px;
        }
        .day-number {
            font-weight: 800;
            font-size: 16px;
            color: #1e293b;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 18px;
            margin-bottom: 12px;
            transition: all 0.2s;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
        }
        .calendar-day.today .day-number {
            background: #0A84FF;
            color: white;
            border: none;
            box-shadow: 0 4px 10px rgba(10,132,255,0.4);
        }
        .calendar-day:not(.empty):not(.today) .day-number:hover {
            background: #e2e8f0;
            transform: scale(1.05);
        }
        
        /* Appointment Cards */
        .appointment-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .appointment-card {
            background: #ffffff;
            border-left: 3px solid #0A84FF;
            border-radius: 8px;
            padding: 6px 8px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .appointment-card:hover {
            background: #eff6ff;
            transform: translateX(3px);
        }
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .appointment-time {
            font-weight: 700;
            font-size: 10px;
            color: #0A84FF;
            background: #eef2ff;
            padding: 2px 6px;
            border-radius: 20px;
            white-space: nowrap;
        }
        .appointment-patient {
            font-weight: 500;
            color: #1e293b;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex: 1;
        }
        .more-link {
            font-size: 10px;
            color: #0A84FF;
            text-decoration: none;
            display: inline-block;
            margin-top: 6px;
            font-weight: 600;
        }
        .more-link:hover { text-decoration: underline; }
        
        .empty-day-placeholder {
            text-align: center;
            color: #94a3b8;
            font-size: 20px;
            margin-top: 20px;
        }
        
        @media (max-width: 1024px) {
            .calendar-day { min-height: 110px; padding: 8px; }
            .appointment-card { padding: 4px 6px; gap: 5px; }
            .appointment-time { font-size: 9px; }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); position: fixed; z-index: 1000; }
            .main-content { margin-left: 0; }
            .calendar-day { min-height: 90px; padding: 6px; }
            .appointment-card { font-size: 9px; }
            .calendar-nav h2 { font-size: 1.2rem; }
        }
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-header"><h2><i class="fas fa-heartbeat"></i> VeeCare</h2><p>Medical Centre</p></div>
    <nav class="sidebar-nav">
        <div class="nav-item"><a href="../dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a></div>
        <div class="nav-divider"></div>
        <div class="nav-category">Appointments</div>
        <div class="nav-item"><a href="calendar.php" class="nav-link active"><i class="fas fa-calendar-alt"></i> Calendar</a></div>
        <div class="nav-item"><a href="add_appointment.php" class="nav-link"><i class="fas fa-plus-circle"></i> New Appointment</a></div>
        <div class="nav-item"><a href="view_appointments.php" class="nav-link"><i class="fas fa-list"></i> All Appointments</a></div>
        <div class="nav-divider"></div>
        <div class="nav-item"><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </nav>
</aside>

<main class="main-content">
    <div class="page-header">
        <div><h1>Appointment Calendar</h1><p>Visual overview of scheduled appointments</p></div>
        <a href="../dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <div class="calendar-nav">
        <div class="nav-buttons">
            <a href="?month=<?php echo $month-1; ?>&year=<?php echo $year; ?>"><i class="fas fa-chevron-left"></i> Prev</a>
            <a href="?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?>">Today</a>
            <a href="?month=<?php echo $month+1; ?>&year=<?php echo $year; ?>">Next <i class="fas fa-chevron-right"></i></a>
        </div>
        <h2><?php echo date('F Y', mktime(0,0,0,$month,1,$year)); ?></h2>
        <a href="add_appointment.php" class="btn-back new-appt-btn"><i class="fas fa-plus"></i> New Appointment</a>
    </div>

    <div class="calendar">
        <div class="calendar-weekdays">
            <div class="calendar-weekday">Mon</div><div class="calendar-weekday">Tue</div><div class="calendar-weekday">Wed</div>
            <div class="calendar-weekday">Thu</div><div class="calendar-weekday">Fri</div><div class="calendar-weekday">Sat</div>
            <div class="calendar-weekday">Sun</div>
        </div>
        <div class="calendar-days">
            <?php 
            $today = date('Y-m-d');
            foreach ($weeks as $week): ?>
                <?php foreach ($week as $day): ?>
                    <?php $isCurrentMonth = ($day !== null); ?>
                    <?php $dateKey = $isCurrentMonth ? sprintf("%04d-%02d-%02d", $year, $month, $day) : ''; ?>
                    <?php $isToday = ($dateKey == $today); ?>
                    <div class="calendar-day <?php echo $isCurrentMonth ? '' : 'empty'; ?> <?php echo $isToday ? 'today' : ''; ?>">
                        <?php if ($isCurrentMonth): ?>
                            <div class="day-number"><?php echo $day; ?></div>
                            <div class="appointment-list">
                                <?php if (isset($appointments[$dateKey])): ?>
                                    <?php $displayCount = 0; ?>
                                    <?php foreach ($appointments[$dateKey] as $apt): ?>
                                        <?php if ($displayCount < 3): ?>
                                            <div class="appointment-card" title="<?php echo htmlspecialchars($apt['patient_name']) . ' with Dr. ' . htmlspecialchars($apt['doctor_name']); ?>">
                                                <span class="status-dot" style="background: <?php echo $statusColors[$apt['status']] ?? '#888'; ?>;"></span>
                                                <span class="appointment-time"><?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></span>
                                                <span class="appointment-patient"><?php echo htmlspecialchars($apt['patient_name']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php $displayCount++; ?>
                                    <?php endforeach; ?>
                                    <?php if (count($appointments[$dateKey]) > 3): ?>
                                        <a href="view_appointments.php?filter_date=<?php echo $dateKey; ?>" class="more-link">+<?php echo count($appointments[$dateKey])-3; ?> more</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="empty-day-placeholder">—</div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>
</main>
</body>
</html>