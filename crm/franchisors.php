<?php
require_once 'header.php';
require_once 'db.php';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$fid = $_GET['fid'] ?? '';
$state = $_GET['state'] ?? '';
$city = $_GET['city'] ?? '';

$where = "1=1";
$params = [];
if ($search) {
    $where .= " AND (company_name LIKE :s1 OR brand_name LIKE :s2 OR ceo_name LIKE :s3)";
    $params[':s1'] = $params[':s2'] = $params[':s3'] = "%$search%";
}
if ($fid) {
    $where .= " AND franchisor_id LIKE :fid";
    $params[':fid'] = "%$fid%";
}
if ($state) {
    $where .= " AND state LIKE :state";
    $params[':state'] = "%$state%";
}
if ($city) {
    $where .= " AND city LIKE :city";
    $params[':city'] = "%$city%";
}

// Get Total
$stmt = $pdoCrm->prepare("SELECT COUNT(*) FROM crm_franchisors WHERE $where");
$stmt->execute($params);
$total = $stmt->fetchColumn();
$totalPages = ceil($total / $limit);

// Get Data
$stmt = $pdoCrm->prepare("SELECT * FROM crm_franchisors WHERE $where ORDER BY id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$franchisors = $stmt->fetchAll();
?>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Filter Franchisors (Local)</h5>
                <div class="d-flex gap-2">
                    <a href="add_franchisor.php" class="btn btn-sm btn-primary"><i class="bx bx-plus me-1"></i> Add Franchisor</a>
                    <a class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" href="#filterCollapse" role="button">Toggle Filters</a>
                </div>
            </div>
            <div class="collapse show" id="filterCollapse">
                <div class="card-body border-top">
                    <form method="GET">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Franchisor ID</label>
                                <input type="text" name="fid" class="form-control" value="<?= htmlspecialchars($fid) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Search Keyword</label>
                                <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">State</label>
                                <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($state) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">City</label>
                                <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($city) ?>">
                            </div>
                            <div class="col-md-12 text-end">
                                <a href="franchisors.php" class="btn btn-outline-secondary me-2">Reset</a>
                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Local Franchisors <span class="badge bg-label-primary ms-2"><?= $total ?> total</span></h5>
    </div>
    <div class="table-responsive text-nowrap">
        <table class="table table-hover table-striped">
            <thead class="table-light">
                <tr>
                    <th>Sr. No.</th>
                    <th>Franchisor ID</th>
                    <th>Company / Brand</th>
                    <th>CEO / Contact</th>
                    <th>Location</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($franchisors)): ?>
                    <tr><td colspan="6" class="text-center py-4">No local franchisors found.</td></tr>
                <?php else: ?>
                    <?php foreach($franchisors as $idx => $fran): ?>
                    <tr>
                        <td><strong><?= $offset + $idx + 1 ?></strong></td>
                        <td><span class="text-primary fw-medium"><?= htmlspecialchars($fran['franchisor_id']) ?></span></td>
                        <td>
                            <div class="d-flex flex-column">
                                <span class="fw-bold text-dark"><?= htmlspecialchars($fran['company_name']) ?></span>
                                <small class="text-muted"><?= htmlspecialchars($fran['brand_name']) ?></small>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-column small">
                                <span><?= htmlspecialchars($fran['ceo_name']) ?></span>
                                <span class="text-muted"><?= htmlspecialchars($fran['mobile']) ?></span>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($fran['city']) ?>, <?= htmlspecialchars($fran['state']) ?></td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                                <a href="view.php?type=franchisor&id=<?= $fran['id'] ?>" class="btn btn-sm btn-outline-primary">Profile</a>
                                <a href="edit_franchisor.php?id=<?= $fran['id'] ?>" class="btn btn-sm btn-outline-warning"><i class="bx bx-edit-alt"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <!-- Pagination -->
    <?php if($totalPages > 1): ?>
    <div class="card-footer d-flex justify-content-center border-top">
        <nav>
            <ul class="pagination mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= max(1, $page-1) ?>&search=<?= urlencode($search) ?>"><i class="bx bx-chevron-left"></i></a>
                </li>
                <?php 
                $range = 2;
                $show_dots = false;
                for($i=1; $i<=$totalPages; $i++): 
                    if($i <= 2 || $i > $totalPages - 2 || ($i >= $page - $range && $i <= $page + $range)):
                        $show_dots = true;
                ?>
                    <li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a></li>
                <?php elseif($show_dots): $show_dots = false; ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif; endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= min($totalPages, $page+1) ?>&search=<?= urlencode($search) ?>"><i class="bx bx-chevron-right"></i></a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>
