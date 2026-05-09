<?php
require_once 'auth.php';
// Admin and Manager can view, but only Admin can manage
if (!in_array($_SESSION['role'], ['admin', 'manager'])) {
    require_once 'header.php';
    echo "<div class='container-xxl mt-4'><div class='alert alert-danger'>Access Denied.</div></div>";
    require_once 'footer.php';
    exit;
}

require_once 'db.php';

// Handle user creation/update (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_SESSION['role'] !== 'admin') {
        header("Location: users.php?msg=denied");
        exit;
    }
    
    if ($_POST['action'] === 'add_user') {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $role = $_POST['role'];
        if ($username && $password && in_array($role, ['admin', 'manager', 'salesman'])) {
            $stmt = $pdoCrm->prepare("INSERT IGNORE INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->execute([$username, $password, $role]);
        }
    } elseif ($_POST['action'] === 'delete_user') {
        $id = (int)$_POST['user_id'];
        if ($id !== $_SESSION['user_id']) { // prevent self deletion
            $stmt = $pdoCrm->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
        }
    }
    header("Location: users.php?msg=success");
    exit;
}

require_once 'header.php';

$stmt = $pdoCrm->query("SELECT id, username, role, created_at FROM users ORDER BY created_at DESC");
$usersList = $stmt->fetchAll();
?>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <h5 class="card-header">Add New User</h5>
            <div class="card-body">
                <?php if(isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
                    <div class="alert alert-success">Action successful.</div>
                <?php elseif(isset($_GET['msg']) && $_GET['msg'] === 'denied'): ?>
                    <div class="alert alert-danger">Permission Denied.</div>
                <?php endif; ?>
                
                <?php if($_SESSION['role'] === 'admin'): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="add_user">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="salesman">Salesman</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </form>
                <?php else: ?>
                    <p class="text-muted">You do not have permission to add new users.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <h5 class="card-header">User Management</h5>
            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Created At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        <?php foreach($usersList as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                            <td><span class="badge bg-label-<?= $u['role']=='admin'?'danger':($u['role']=='manager'?'warning':'info') ?>"><?= ucfirst($u['role']) ?></span></td>
                            <td><?= $u['created_at'] ?></td>
                            <td>
                                <?php if($_SESSION['role'] === 'admin' && $u['id'] !== $_SESSION['user_id']): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure?');">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button class="btn btn-sm btn-danger"><i class="bx bx-trash"></i></button>
                                </form>
                                <?php elseif($u['id'] === $_SESSION['user_id']): ?>
                                    <span class="text-muted">You</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
