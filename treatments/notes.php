<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../config/database.php';
$conn = getConnection();

$message = '';
$error = '';
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Handle adding/editing notes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_note'])) {
        $patient_id = (int)$_POST['patient_id'];
        $appointment_id = !empty($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : null;
        $note_title = trim($_POST['note_title']);
        $note_content = trim($_POST['note_content']);
        $note_type = $_POST['note_type'];
        if (empty($patient_id) || empty($note_title) || empty($note_content)) {
            $error = "Patient, title, and content are required.";
        } else {
            $stmt = $conn->prepare("INSERT INTO clinical_notes (patient_id, appointment_id, doctor_id, note_title, note_content, note_type, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiisss", $patient_id, $appointment_id, $user_id, $note_title, $note_content, $note_type);
            if ($stmt->execute()) {
                $message = "Note added successfully.";
            } else {
                $error = "Database error: " . $stmt->error;
            }
            $stmt->close();
        }
    } elseif (isset($_POST['edit_note'])) {
        $note_id = (int)$_POST['note_id'];
        $note_title = trim($_POST['note_title']);
        $note_content = trim($_POST['note_content']);
        $note_type = $_POST['note_type'];
        $check = $conn->prepare("SELECT doctor_id FROM clinical_notes WHERE id = ?");
        $check->bind_param("i", $note_id);
        $check->execute();
        $owner = $check->get_result()->fetch_assoc();
        $check->close();
        if ($owner && ($owner['doctor_id'] == $user_id || $user_role === 'admin')) {
            $stmt = $conn->prepare("UPDATE clinical_notes SET note_title = ?, note_content = ?, note_type = ? WHERE id = ?");
            $stmt->bind_param("sssi", $note_title, $note_content, $note_type, $note_id);
            if ($stmt->execute()) $message = "Note updated.";
            else $error = "Update failed.";
            $stmt->close();
        } else {
            $error = "You can only edit your own notes.";
        }
    } elseif (isset($_POST['delete_note'])) {
        $note_id = (int)$_POST['note_id'];
        $check = $conn->prepare("SELECT doctor_id FROM clinical_notes WHERE id = ?");
        $check->bind_param("i", $note_id);
        $check->execute();
        $owner = $check->get_result()->fetch_assoc();
        $check->close();
        if ($owner && ($owner['doctor_id'] == $user_id || $user_role === 'admin')) {
            $stmt = $conn->prepare("DELETE FROM clinical_notes WHERE id = ?");
            $stmt->bind_param("i", $note_id);
            if ($stmt->execute()) $message = "Note deleted.";
            else $error = "Delete failed.";
            $stmt->close();
        } else {
            $error = "You can only delete your own notes.";
        }
    }
}

// Pagination and filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$filter_patient = isset($_GET['patient']) ? (int)$_GET['patient'] : 0;
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';

$where = "1=1";
$params = [];
$types = "";
if ($filter_patient) {
    $where .= " AND cn.patient_id = ?";
    $params[] = $filter_patient;
    $types .= "i";
}
if ($filter_type) {
    $where .= " AND cn.note_type = ?";
    $params[] = $filter_type;
    $types .= "s";
}
if ($user_role !== 'admin') {
    $where .= " AND cn.doctor_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

// Count total
$countSql = "SELECT COUNT(*) as total FROM clinical_notes cn WHERE $where";
$stmt = $conn->prepare($countSql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
$totalPages = ceil($total / $limit);

// Fetch notes
$sql = "SELECT cn.*, p.full_name as patient_name, p.patient_id as patient_code, u.full_name as doctor_name 
        FROM clinical_notes cn
        JOIN patients p ON cn.patient_id = p.id
        LEFT JOIN users u ON cn.doctor_id = u.id
        WHERE $where
        ORDER BY cn.created_at DESC
        LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get patients for dropdown
$patients = [];
$res = $conn->query("SELECT id, full_name, patient_id FROM patients ORDER BY full_name");
while ($row = $res->fetch_assoc()) $patients[] = $row;

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<!-- Main Content - Properly positioned -->
<div style="margin-left: 280px; padding: 24px; background: #f5f7fa; min-height: 100vh;">
    <div style="max-width: 1200px; margin: 0 auto;">
        <!-- Header -->
        <div style="background: white; border-radius: 20px; padding: 20px 28px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
            <div>
                <h1 style="margin: 0; font-size: 24px;">Clinical Notes</h1>
                <p style="margin: 4px 0 0; color: #6c86a3;">Record and manage patient clinical notes</p>
            </div>
            <button onclick="openModal('addNoteModal')" style="background: #0A84FF; color: white; border: none; padding: 10px 20px; border-radius: 12px; cursor: pointer;"><i class="fas fa-plus"></i> Add Note</button>
        </div>

        <!-- Filter Bar -->
        <div style="background: white; border-radius: 20px; padding: 20px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
            <form method="GET" style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end;">
                <div style="display: flex; flex-direction: column; gap: 6px;">
                    <label style="font-size: 12px; font-weight: 600;">Patient</label>
                    <select name="patient" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 10px;">
                        <option value="0">All Patients</option>
                        <?php foreach ($patients as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo ($filter_patient == $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; flex-direction: column; gap: 6px;">
                    <label style="font-size: 12px; font-weight: 600;">Note Type</label>
                    <select name="type" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 10px;">
                        <option value="">All</option>
                        <option value="progress" <?php echo ($filter_type == 'progress') ? 'selected' : ''; ?>>Progress Note</option>
                        <option value="clinical" <?php echo ($filter_type == 'clinical') ? 'selected' : ''; ?>>Clinical Note</option>
                        <option value="discharge" <?php echo ($filter_type == 'discharge') ? 'selected' : ''; ?>>Discharge Summary</option>
                    </select>
                </div>
                <div>
                    <button type="submit" style="background: #0A84FF; color: white; border: none; padding: 8px 20px; border-radius: 10px; cursor: pointer;">Filter</button>
                </div>
                <div>
                    <a href="notes.php" style="background: #f0f2f5; padding: 8px 20px; border-radius: 10px; text-decoration: none; color: #666;">Clear</a>
                </div>
            </form>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div style="background: #d4edda; color: #155724; padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; border-left: 4px solid #28a745;"><?php echo htmlspecialchars($message); ?></div>
        <?php elseif ($error): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; border-left: 4px solid #dc3545;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Notes Grid -->
        <?php if (empty($notes)): ?>
            <div style="background: white; border-radius: 20px; padding: 60px; text-align: center; color: #999;">
                <i class="fas fa-notes-medical" style="font-size: 48px; margin-bottom: 16px;"></i>
                <p>No clinical notes found.</p>
                <button onclick="openModal('addNoteModal')" style="background: #0A84FF; color: white; border: none; padding: 10px 20px; border-radius: 12px; margin-top: 16px; cursor: pointer;">Add your first note</button>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;">
                <?php foreach ($notes as $note): ?>
                    <div style="background: white; border-radius: 20px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border-left: 4px solid #0A84FF;">
                        <div style="font-size: 16px; font-weight: 700; margin-bottom: 8px;"><?php echo htmlspecialchars($note['note_title']); ?></div>
                        <div style="font-size: 12px; color: #6c86a3; margin-bottom: 12px; display: flex; gap: 12px; flex-wrap: wrap;">
                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($note['patient_name']); ?></span>
                            <span><i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y g:i A', strtotime($note['created_at'])); ?></span>
                            <span><i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars($note['doctor_name']); ?></span>
                            <span style="background: <?php echo $note['note_type']=='progress'?'#0A84FF':($note['note_type']=='clinical'?'#34C759':'#FF9500'); ?>; color: white; padding: 2px 8px; border-radius: 20px; font-size: 10px;"><?php echo ucfirst($note['note_type']); ?></span>
                        </div>
                        <div style="font-size: 13px; color: #2c3e50; margin-bottom: 12px;"><?php echo nl2br(htmlspecialchars(substr($note['note_content'], 0, 150))); ?><?php echo strlen($note['note_content']) > 150 ? '...' : ''; ?></div>
                        <div>
                            <button onclick="editNote(<?php echo $note['id']; ?>, '<?php echo addslashes($note['note_title']); ?>', '<?php echo addslashes($note['note_content']); ?>', '<?php echo $note['note_type']; ?>')" style="padding: 4px 10px; font-size: 12px; background: none; border: 1px solid #FF9500; border-radius: 8px; cursor: pointer; margin-right: 8px; color: #FF9500;"><i class="fas fa-edit"></i> Edit</button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this note?')">
                                <input type="hidden" name="note_id" value="<?php echo $note['id']; ?>">
                                <button type="submit" name="delete_note" style="padding: 4px 10px; font-size: 12px; background: none; border: 1px solid #FF3B30; border-radius: 8px; cursor: pointer; color: #FF3B30;"><i class="fas fa-trash"></i> Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($totalPages > 1): ?>
                <div style="margin-top: 30px; display: flex; justify-content: center; gap: 8px;">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&patient=<?php echo $filter_patient; ?>&type=<?php echo urlencode($filter_type); ?>" style="padding: 8px 14px; background: white; border-radius: 10px; text-decoration: none; color: #0A84FF; <?php echo ($i == $page) ? 'background: #0A84FF; color: white;' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Note Modal -->
<div id="addNoteModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
    <div style="background: white; border-radius: 24px; max-width: 600px; width: 90%; padding: 24px; max-height: 85vh; overflow-y: auto;">
        <h2 style="margin-bottom: 20px;">Add Clinical Note</h2>
        <form method="POST">
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Patient</label>
                <select name="patient_id" required style="width:100%; padding: 10px; border:1px solid #ddd; border-radius: 12px;">
                    <option value="">Select Patient</option>
                    <?php foreach ($patients as $p): ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['full_name']); ?> (<?php echo $p['patient_id']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Appointment (Optional)</label>
                <select name="appointment_id" style="width:100%; padding: 10px; border:1px solid #ddd; border-radius: 12px;">
                    <option value="">Select Appointment (optional)</option>
                </select>
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Note Type</label>
                <select name="note_type" required style="width:100%; padding: 10px; border:1px solid #ddd; border-radius: 12px;">
                    <option value="progress">Progress Note</option>
                    <option value="clinical">Clinical Note</option>
                    <option value="discharge">Discharge Summary</option>
                </select>
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Title</label>
                <input type="text" name="note_title" required style="width:100%; padding: 10px; border:1px solid #ddd; border-radius: 12px;">
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Content</label>
                <textarea name="note_content" rows="6" required style="width:100%; padding: 10px; border:1px solid #ddd; border-radius: 12px;"></textarea>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeModal('addNoteModal')" style="background: #f0f2f5; border: none; padding: 10px 20px; border-radius: 12px;">Cancel</button>
                <button type="submit" name="add_note" style="background: #0A84FF; color: white; border: none; padding: 10px 20px; border-radius: 12px;">Save Note</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Note Modal -->
<div id="editNoteModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
    <div style="background: white; border-radius: 24px; max-width: 600px; width: 90%; padding: 24px;">
        <h2 style="margin-bottom: 20px;">Edit Clinical Note</h2>
        <form method="POST">
            <input type="hidden" name="note_id" id="edit_note_id">
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Note Type</label>
                <select name="note_type" id="edit_note_type" required style="width:100%; padding: 10px; border:1px solid #ddd; border-radius: 12px;">
                    <option value="progress">Progress Note</option>
                    <option value="clinical">Clinical Note</option>
                    <option value="discharge">Discharge Summary</option>
                </select>
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Title</label>
                <input type="text" name="note_title" id="edit_note_title" required style="width:100%; padding: 10px; border:1px solid #ddd; border-radius: 12px;">
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Content</label>
                <textarea name="note_content" id="edit_note_content" rows="6" required style="width:100%; padding: 10px; border:1px solid #ddd; border-radius: 12px;"></textarea>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeModal('editNoteModal')" style="background: #f0f2f5; border: none; padding: 10px 20px; border-radius: 12px;">Cancel</button>
                <button type="submit" name="edit_note" style="background: #0A84FF; color: white; border: none; padding: 10px 20px; border-radius: 12px;">Update Note</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).style.display = 'flex';
}
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}
function editNote(id, title, content, type) {
    document.getElementById('edit_note_id').value = id;
    document.getElementById('edit_note_title').value = title;
    document.getElementById('edit_note_content').value = content;
    document.getElementById('edit_note_type').value = type;
    openModal('editNoteModal');
}
</script>

<?php include '../includes/footer.php'; ?>