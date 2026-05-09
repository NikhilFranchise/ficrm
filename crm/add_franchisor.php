<?php
require_once 'header.php';
require_once 'db.php';
$categories = require_once 'categories.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdoCrm->prepare("
            INSERT INTO crm_franchisors (
                franchisor_id, company_name, brand_name, ceo_name, mobile, email, city, state, 
                ind_main_cat, ind_cat, ind_sub_cat, unit_inv_min, unit_inv_max, profile_link
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
            $_POST['profile_link']
        ]);
        $msg = '<div class="alert alert-success">Franchisor added successfully!</div>';
    } catch (Exception $e) {
        $msg = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}
?>

<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="fw-bold py-3 mb-4"><span class="text-muted fw-light">CRM /</span> Add New Franchisor</h4>

    <?= $msg ?>

    <div class="card mb-4">
        <h5 class="card-header">Franchisor Details</h5>
        <div class="card-body">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Franchisor ID (Manual)</label>
                        <input type="text" name="franchisor_id" class="form-control" placeholder="e.g. FRAN1234" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="company_name" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Brand Name</label>
                        <input type="text" name="brand_name" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">CEO Name</label>
                        <input type="text" name="ceo_name" class="form-control" maxlength="100">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Mobile</label>
                        <input type="text" name="mobile" class="form-control" maxlength="10" pattern="[0-9]{10}" placeholder="10 digit number">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" maxlength="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">City</label>
                        <input type="text" name="city" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">State</label>
                        <input type="text" name="state" class="form-control">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Main Category</label>
                        <select name="ind_main_cat" id="main_cat" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php foreach($categories['SeoCategoryArr'] as $id => $name): ?>
                                <option value="<?= $id ?>"><?= ucwords(str_replace('-', ' ', $name)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Sub Category</label>
                        <select name="ind_cat" id="sub_cat" class="form-select" disabled>
                            <option value="">Select Sub Category</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Sub Sub Category</label>
                        <select name="ind_sub_cat" id="sub_sub_cat" class="form-select" disabled>
                            <option value="">Select Sub Sub Category</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Min Investment</label>
                        <input type="number" name="unit_inv_min" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Max Investment</label>
                        <input type="number" name="unit_inv_max" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Profile Link (External)</label>
                        <input type="url" name="profile_link" class="form-control" placeholder="https://...">
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary me-2">Create Franchisor</button>
                    <a href="franchisors.php" class="btn btn-outline-secondary">Back to List</a>
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
    // Handle Main Category Change
    document.getElementById('main_cat').addEventListener('change', function() {
        loadCategories(this.value, 'sub_cat');
        document.getElementById('sub_sub_cat').innerHTML = '<option value="">Select Sub Sub Category...</option>';
    });

    // Handle Sub Category Change
    document.getElementById('sub_cat').addEventListener('change', function() {
        loadCategories(this.value, 'sub_sub_cat');
    });

    function loadCategories(parentId, targetId) {
        const target = document.getElementById(targetId);
        target.innerHTML = '<option value="">Loading...</option>';
        target.disabled = false;

        if (!parentId) {
            target.innerHTML = '<option value="">Select...</option>';
            return;
        }

        fetch(CAT_API_URL + '?parent_id=' + parentId)
            .then(response => response.json())
            .then(data => {
                target.innerHTML = '<option value="">Select...</option>';
                if (Array.isArray(data) && data.length > 0) {
                    data.forEach(cat => {
                        let opt = document.createElement('option');
                        opt.value = cat.catid;
                        opt.textContent = cat.catname;
                        target.appendChild(opt);
                    });
                } else {
                    target.innerHTML = '<option value="">No options found</option>';
                }
            })
            .catch(err => {
                console.error('API Error:', err);
                target.innerHTML = '<option value="">Error loading data</option>';
            });
    }
});
</script>
