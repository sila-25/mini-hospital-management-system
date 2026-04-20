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

<main class="main-content" style="margin-left: 280px; padding: 24px; background: #f5f7fa; min-height: 100vh;">
    <div style="max-width: 1200px; margin: 0 auto;">
        <div style="background: white; border-radius: 20px; padding: 20px 28px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
            <div>
                <h1 style="margin: 0; font-size: 24px;">User Management</h1>
                <p style="margin: 4px 0 0; color: #6c86a3;">Manage system users and roles</p>
            </div>
            <button onclick="openModal('addModal')" style="background: #0A84FF; color: white; border: none; padding: 10px 20px; border-radius: 12px; cursor: pointer;"><i class="fas fa-user-plus"></i> Add New User</button>
        </div>

        <?php if ($message): ?>
            <div style="background: #d4edda; color: #155724; padding: 14px 20px; border-radius: 12px; margin-bottom: 24px; border-left: 4px solid #28a745;"><?php echo htmlspecialchars($message); ?></div>
        <?php elseif ($error): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 14px 20px; border-radius: 12px; margin-bottom: 24px; border-left: 4px solid #dc3545;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div style="background: white; border-radius: 20px; padding: 20px; overflow-x: auto; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="text-align: left; padding: 14px; border-bottom: 1px solid #eef2f6;">Name</th>
                        <th style="text-align: left; padding: 14px; border-bottom: 1px solid #eef2f6;">Email</th>
                        <th style="text-align: left; padding: 14px; border-bottom: 1px solid #eef2f6;">Role</th>
                        <th style="text-align: left; padding: 14px; border-bottom: 1px solid #eef2f6;">Status</th>
                        <th style="text-align: left; padding: 14px; border-bottom: 1px solid #eef2f6;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td style="padding: 14px; border-bottom: 1px solid #eef2f6;"><strong><?php echo htmlspecialchars($u['full_name']); ?></strong></td>
                        <td style="padding: 14px; border-bottom: 1px solid #eef2f6;"><?php echo htmlspecialchars($u['email']); ?></td>
                        <td style="padding: 14px; border-bottom: 1px solid #eef2f6;"><span style="display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: <?php echo $u['role']=='admin'?'#dc3545':($u['role']=='doctor'?'#0A84FF':'#28a745'); ?>; color: white;"><?php echo ucfirst($u['role']); ?></span></td>
                        <td style="padding: 14px; border-bottom: 1px solid #eef2f6;"><?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?></td>
                        <td style="padding: 14px; border-bottom: 1px solid #eef2f6;">
                            <button onclick="editUser(<?php echo $u['id']; ?>, '<?php echo addslashes($u['full_name']); ?>', '<?php echo addslashes($u['email']); ?>', '<?php echo $u['role']; ?>')" style="background: none; border: none; cursor: pointer; margin-right: 12px;"><i class="fas fa-edit" style="color: #FF9500;"></i></button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this user?')">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <button type="submit" name="delete_user" style="background: none; border: none; cursor: pointer;"><i class="fas fa-trash" style="color: #dc3545;"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Add User Modal -->
<div id="addModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
    <div style="background: white; border-radius: 24px; max-width: 500px; width: 90%; padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0; font-size: 20px;">Add New User</h2>
            <button onclick="closeModal('addModal')" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #999;">&times;</button>
        </div>
        <form method="POST">
            <div style="margin-bottom: 16px;"><label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Full Name</label><input type="text" name="full_name" required style="width:100%; padding: 10px; border:1px solid #ddd; border-radius: 10px;"></div>
            <div style="margin-bottom: 16px;"><label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Email</label><input type="email" name="email" required style="width:100%; padding: 10px; border:1px solid #ddd; border-radius: 10px;"></div>
            <div style="margin-bottom: 16px;"><label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Password</label><input type="password" name="password" required style="width:100%; padding: 10px; border:1px solid #ddd; border-radius: 10px;"></div>
            <div style="margin-bottom: 20px;"><label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Role</label>
                <select name="role" style="width:100%; padding: 10px; border:1px solid #ddd; border-radius: 10px;">
                    <option value="admin">Admin</option>
                    <option value="doctor">Doctor</option>
                    <option value="staff">Staff</option>
                    <option value="receptionist">Receptionist</option>
                </select>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeModal('addModal')" style="background: #f0f2f5; border: none; padding: 10px 20px; border-radius: 12px; cursor: pointer;">Cancel</button>
                <button type="submit" name="add_user" style="background: #0A84FF; color: white; border: none; padding: 10px 20px; border-radius: 12px; cursor: pointer;">Add User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
    <div style="background: white; border-radius: 24px; max-width: 500px; width: 90%; padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0; font-size: 20px;">Edit User</h2>
            <button onclick="closeModal('editModal')" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #999;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div style="margin-bottom: 16px;"><label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Full Name</label><input type="text" name="full_name" id="edit_full_name" required style="width:100%; padding: 10px; border:1px solid #ddd; border-radius: 10px;"></div>
            <div style="margin-bottom: 16px;"><label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Email</label><input type="email" name="email" id="edit_email" required style="width:100%; padding: 10px; border:1px solid #ddd; border-radius: 10px;"></div>
            <div style="margin-bottom: 20px;"><label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">Role</label>
                <select name="role" id="edit_role" style="width:100%; padding: 10px; border:1px solid #ddd; border-radius: 10px;">
                    <option value="admin">Admin</option>
                    <option value="doctor">Doctor</option>
                    <option value="staff">Staff</option>
                    <option value="receptionist">Receptionist</option>
                </select>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeModal('editModal')" style="background: #f0f2f5; border: none; padding: 10px 20px; border-radius: 12px; cursor: pointer;">Cancel</button>
                <button type="submit" name="edit_user" style="background: #0A84FF; color: white; border: none; padding: 10px 20px; border-radius: 12px; cursor: pointer;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

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