<?php
require_once 'header.php';
require_once 'db.php';
$categories = require_once 'categories.php';

$id = $_GET['id'] ?? '';
if (!$id) {
    echo "<div class='alert alert-danger'>Invalid Franchisor ID</div>";
    exit;
}

// Fetch existing data
$stmt = $pdoCrm->prepare("SELECT * FROM crm_franchisors WHERE id = ?");
$stmt->execute([$id]);
$franchisor = $stmt->fetch();

if (!$franchisor) {
    echo "<div class='alert alert-danger'>Franchisor not found</div>";
    exit;
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdoCrm->prepare("
            UPDATE crm_franchisors SET 
                franchisor_id = ?, company_name = ?, brand_name = ?, ceo_name = ?, 
                mobile = ?, email = ?, city = ?, state = ?, 
                ind_main_cat = ?, ind_cat = ?, ind_sub_cat = ?, 
                unit_inv_min = ?, unit_inv_max = ?, profile_link = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['franchisor_id'],
            $_POST['company_name'],
            $_POST['brand_name'],
            $_POST['ceo_name'],
            $_POST['mobile'],
            $_POST['email'],
            $_POST['city'],
            $_POST['state'],
            $_POST['ind_main_cat'],
            $_POST['ind_cat'],
            $_POST['ind_sub_cat'],
            $_POST['unit_inv_min'] ?: 0,
            $_POST['unit_inv_max'] ?: 0,
            $_POST['profile_link'],
            $id
        ]);
        $msg = '<div class="alert alert-success">Franchisor updated successfully!</div>';
        
        // Refresh data
        $stmt = $pdoCrm->prepare("SELECT * FROM crm_franchisors WHERE id = ?");
        $stmt->execute([$id]);
        $franchisor = $stmt->fetch();
    } catch (Exception $e) {
        $msg = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}
?>

<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">CRM /</span> Edit Franchisor</h4>

    <?= $msg ?>

    <div class="card mb-4">
        <h5 class="card-header">Update Franchisor Details: <?= htmlspecialchars($franchisor['brand_name']) ?></h5>
        <div class="card-body">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Franchisor ID (Manual)</label>
                        <input type="text" name="franchisor_id" class="form-control" value="<?= htmlspecialchars($franchisor['franchisor_id']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($franchisor['company_name']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Brand Name</label>
                        <input type="text" name="brand_name" class="form-control" value="<?= htmlspecialchars($franchisor['brand_name']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">CEO Name</label>
                        <input type="text" name="ceo_name" class="form-control" maxlength="100" value="<?= htmlspecialchars($franchisor['ceo_name']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Mobile</label>
                        <input type="text" name="mobile" class="form-control" maxlength="10" pattern="[0-9]{10}" value="<?= htmlspecialchars($franchisor['mobile']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" maxlength="100" value="<?= htmlspecialchars($franchisor['email']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">City</label>
                        <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($franchisor['city']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">State</label>
                        <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($franchisor['state']) ?>">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Main Category</label>
                        <select name="ind_main_cat" id="main_cat" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php foreach($categories['SeoCategoryArr'] as $cat_id => $name): ?>
                                <option value="<?= $cat_id ?>" <?= $franchisor['ind_main_cat'] == $cat_id ? 'selected' : '' ?>><?= ucwords(str_replace('-', ' ', $name)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Sub Category</label>
                        <select name="ind_cat" id="sub_cat" class="form-select">
                            <option value="<?= htmlspecialchars($franchisor['ind_cat']) ?>"><?= htmlspecialchars($franchisor['ind_cat'] ?: 'Select Sub Category') ?></option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Sub Sub Category</label>
                        <select name="ind_sub_cat" id="sub_sub_cat" class="form-select">
                            <option value="<?= htmlspecialchars($franchisor['ind_sub_cat']) ?>"><?= htmlspecialchars($franchisor['ind_sub_cat'] ?: 'Select Sub Sub Category') ?></option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Min Investment</label>
                        <input type="number" name="unit_inv_min" class="form-control" value="<?= htmlspecialchars($franchisor['unit_inv_min']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Max Investment</label>
                        <input type="number" name="unit_inv_max" class="form-control" value="<?= htmlspecialchars($franchisor['unit_inv_max']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Profile Link (External)</label>
                        <input type="url" name="profile_link" class="form-control" value="<?= htmlspecialchars($franchisor['profile_link']) ?>">
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary me-2">Update Franchisor</button>
                    <a href="view.php?type=franchisor&id=<?= $id ?>" class="btn btn-outline-secondary">View Profile</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>

<script>
// Use relative path for local categories API
const CAT_API_URL = 'api_export/ajax_categories.php';

document.addEventListener("DOMContentLoaded", function() {
    
    // Initial load of sub-categories if main cat is selected
    if (document.getElementById('main_cat').value) {
        loadCategories(document.getElementById('main_cat').value, 'sub_cat', '<?= $franchisor['ind_cat'] ?>');
    }
    if ('<?= $franchisor['ind_cat'] ?>') {
        loadCategories('<?= $franchisor['ind_cat'] ?>', 'sub_sub_cat', '<?= $franchisor['ind_sub_cat'] ?>');
    }

    // Handle Main Category Change
    document.getElementById('main_cat').addEventListener('change', function() {
        loadCategories(this.value, 'sub_cat');
        document.getElementById('sub_sub_cat').innerHTML = '<option value="">Select Sub Sub Category...</option>';
    });

    // Handle Sub Category Change
    document.getElementById('sub_cat').addEventListener('change', function() {
        loadCategories(this.value, 'sub_sub_cat');
    });

    function loadCategories(parentId, targetId, selectedId = '') {
        const target = document.getElementById(targetId);
        if (!parentId) {
            target.innerHTML = '<option value="">Select...</option>';
            return;
        }

        fetch(CAT_API_URL + '?parent_id=' + parentId)
            .then(response => response.json())
            .then(data => {
                target.innerHTML = '<option value="">Select...</option>';
                if (Array.isArray(data)) {
                    data.forEach(cat => {
                        let opt = document.createElement('option');
                        opt.value = cat.catid;
                        opt.textContent = cat.catname;
                        if (cat.catid == selectedId) opt.selected = true;
                        target.appendChild(opt);
                    });
                }
            });
    }
});
</script>
