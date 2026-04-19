<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        if (empty($full_name) || empty($email) || empty($password)) $error = "All fields required.";
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = "Invalid email.";
        elseif (strlen($password) < 8) $error = "Password must be at least 8 characters.";
        else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $full_name, $email, $hash, $role);
            if ($stmt->execute()) $message = "User added successfully.";
            else $error = "Email already exists.";
            $stmt->close();
        }
    } elseif (isset($_POST['edit_user'])) {
        $id = (int)$_POST['user_id'];
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, role = ? WHERE id = ?");
        $stmt->bind_param("sssi", $full_name, $email, $role, $id);
        if ($stmt->execute()) $message = "User updated.";
        else $error = "Update failed.";
        $stmt->close();
    } elseif (isset($_POST['delete_user'])) {
        $id = (int)$_POST['user_id'];
        if ($id == $_SESSION['user_id']) $error = "You cannot delete your own account.";
        else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) $message = "User deleted.";
            else $error = "Delete failed.";
            $stmt->close();
        }
    }
}

$users = [];
$res = $conn->query("SELECT id, full_name, email, role, is_active FROM users ORDER BY id");
$users = $res->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <div>
            <h1>User Management</h1>
            <p>Manage system users and roles</p>
        </div>
        <button onclick="openModal('addModal')" class="btn-primary"><i class="fas fa-user-plus"></i> Add New User</button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="users-table-container">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($u['full_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td><span class="role-badge role-<?php echo $u['role']; ?>"><?php echo ucfirst($u['role']); ?></span></td>
                    <td><?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?></td>
                    <td>
                        <button onclick="editUser(<?php echo $u['id']; ?>, '<?php echo addslashes($u['full_name']); ?>', '<?php echo addslashes($u['email']); ?>', '<?php echo $u['role']; ?>')" class="btn-icon btn-edit"><i class="fas fa-edit"></i></button>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this user?')">
                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            <button type="submit" name="delete_user" class="btn-icon btn-delete"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Modals (same as before) -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add New User</h2>
            <button class="close-modal" onclick="closeModal('addModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="form-group"><label>Full Name</label><input type="text" name="full_name" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
            <div class="form-group"><label>Role</label>
                <select name="role">
                    <option value="admin">Admin</option>
                    <option value="doctor">Doctor</option>
                    <option value="staff">Staff</option>
                    <option value="receptionist">Receptionist</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" name="add_user" class="btn-primary">Add User</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit User</h2>
            <button class="close-modal" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="form-group"><label>Full Name</label><input type="text" name="full_name" id="edit_full_name" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_email" required></div>
            <div class="form-group"><label>Role</label>
                <select name="role" id="edit_role">
                    <option value="admin">Admin</option>
                    <option value="doctor">Doctor</option>
                    <option value="staff">Staff</option>
                    <option value="receptionist">Receptionist</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" name="edit_user" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<style>
.users-table-container {
    background: white;
    border-radius: 20px;
    padding: 20px;
    overflow-x: auto;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.users-table {
    width: 100%;
    border-collapse: collapse;
}
.users-table th {
    text-align: left;
    padding: 14px;
    border-bottom: 1px solid #eef2f6;
    color: #6c86a3;
    font-weight: 600;
    font-size: 12px;
}
.users-table td {
    padding: 14px;
    border-bottom: 1px solid #eef2f6;
}
.role-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.role-admin { background: #dc3545; color: white; }
.role-doctor { background: #0A84FF; color: white; }
.role-staff { background: #28a745; color: white; }
.role-receptionist { background: #FF9500; color: white; }
.btn-icon {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 16px;
    margin: 0 4px;
}
.btn-edit { color: #FF9500; }
.btn-edit:hover { color: #cc7a00; }
.btn-delete { color: #dc3545; }
.btn-delete:hover { color: #a71d2a; }
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}
.modal-content {
    background: white;
    border-radius: 24px;
    max-width: 500px;
    width: 90%;
    padding: 24px;
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.modal-header h2 {
    margin: 0;
    font-size: 20px;
}
.close-modal {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #999;
}
.form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 20px;
}
.btn-secondary {
    background: #f0f2f5;
    color: #4a627a;
    border: none;
    padding: 10px 20px;
    border-radius: 12px;
    cursor: pointer;
}
.page-header {
    background: white;
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
.page-header h1 {
    margin: 0;
    font-size: 24px;
}
.page-header p {
    margin: 4px 0 0;
    color: #6c86a3;
}
.btn-primary {
    background: #0A84FF;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 500;
}
.btn-primary:hover {
    background: #006EDB;
}
.alert {
    padding: 14px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
}
.alert-success {
    background: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}
.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border-left: 4px solid #dc3545;
}
</style>

<script>
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
function editUser(id, name, email, role) {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_full_name').value = name;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_role').value = role;
    openModal('editModal');
}
</script>

<?php include '../includes/footer.php'; ?>