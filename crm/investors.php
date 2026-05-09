<?php
require_once 'header.php';
require_once 'api_client.php';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$search = $_GET['search'] ?? '';
$inv_min = $_GET['inv_min'] ?? '';
$inv_max = $_GET['inv_max'] ?? '';

$extra = [
    'inv_min' => $inv_min,
    'inv_max' => $inv_max
];

$apiRes = fetchFromApi('investor', $page, $search, $extra);
$investors = $apiRes['status'] === 'success' ? $apiRes['data'] : [];
$total = $apiRes['status'] === 'success' ? $apiRes['total'] : 0;
$limit = 10;
$totalPages = ceil($total / $limit);
?>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Filter Paid Investors (Leads)</h5>
                <?php if(isset($apiRes['debug_sql'])): ?>
                    <div class="mt-2 small text-muted">
                        <strong>Debug SQL:</strong> <?= htmlspecialchars($apiRes['debug_sql']) ?><br>
                        <strong>Debug GET (API):</strong> <?= json_encode($apiRes['debug_get'] ?? []) ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-body border-top mt-3">
                <form method="GET">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Search Keyword</label>
                            <input type="text" name="search" class="form-control" placeholder="Search by name, email, city..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Min Investment</label>
                            <input type="number" name="inv_min" class="form-control" placeholder="Min" value="<?= htmlspecialchars($inv_min) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Max Investment</label>
                            <input type="number" name="inv_max" class="form-control" placeholder="Max" value="<?= htmlspecialchars($inv_max) ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Paid Investors Directory <span class="badge bg-label-primary ms-2"><?= $total ?> total</span></h5>
    </div>
    <div class="table-responsive text-nowrap">
        <table class="table table-hover table-striped">
            <thead class="table-light">
                <tr>
                    <th>Sr. No.</th>
                    <th>Lead ID</th>
                    <th>Name & Contact</th>
                    <th>Location</th>
                    <th>Investment Pref</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if($apiRes['status'] === 'error'): ?>
                    <tr><td colspan="6" class="text-center py-4 text-danger"><?= htmlspecialchars($apiRes['message']) ?></td></tr>
                <?php elseif(empty($investors)): ?>
                    <tr><td colspan="6" class="text-center py-4">No leads found.</td></tr>
                <?php else: ?>
                    <?php foreach($investors as $idx => $inv): ?>
                    <tr>
                        <td><strong><?= ($page - 1) * $limit + $idx + 1 ?></strong></td>
                        <td><span class="text-primary fw-medium"><?= htmlspecialchars($inv['id']) ?></span></td>
                        <td>
                            <div class="d-flex flex-column">
                                <span class="fw-bold text-dark"><?= htmlspecialchars($inv['first_name'] . ' ' . $inv['last_name']) ?></span>
                                <small class="text-muted"><?= htmlspecialchars($inv['email']) ?> / <?= htmlspecialchars($inv['mobile']) ?></small>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($inv['city']) ?>, <?= htmlspecialchars($inv['state']) ?></td>
                        <td>
                            <div class="small">
                                <i class="bx bx-rupee"></i> <?= number_format($inv['min_investment']) ?> - <?= number_format($inv['max_investment']) ?>
                            </div>
                        </td>
                        <td class="text-center">
                            <a href="view.php?type=investor&id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-primary">Profile</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if($totalPages > 1): ?>
    <div class="card-footer d-flex justify-content-center border-top">
        <nav>
            <ul class="pagination mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= max(1, $page-1) ?>&search=<?= urlencode($search) ?>&inv_min=<?= $inv_min ?>&inv_max=<?= $inv_max ?>"><i class="bx bx-chevron-left"></i></a>
                </li>
                <?php 
                $range = 2;
                $show_dots = false;
                for($i=1; $i<=$totalPages; $i++): 
                    if($i <= 2 || $i > $totalPages - 2 || ($i >= $page - $range && $i <= $page + $range)):
                        $show_dots = true;
                ?>
                    <li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&inv_min=<?= $inv_min ?>&inv_max=<?= $inv_max ?>"><?= $i ?></a></li>
                <?php elseif($show_dots): $show_dots = false; ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif; endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= min($totalPages, $page+1) ?>&search=<?= urlencode($search) ?>&inv_min=<?= $inv_min ?>&inv_max=<?= $inv_max ?>"><i class="bx bx-chevron-right"></i></a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>
