<?php
require_once 'header.php';
require_once 'db.php';
require_once 'api_client.php';

$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'salesman';

$fid = $_GET['fid'] ?? '';
$salesman_id = $_GET['salesman_id'] ?? '';
if ($role === 'salesman') {
    $salesman_id = $userId;
}
$status = $_GET['status'] ?? '';

$where = "1=1";
$params = [];

if ($fid !== '') {
    $where .= " AND (s.franchisor_id = :fid OR f.franchisor_id = :fid2 OR f.brand_name LIKE :fid3)";
    $params[':fid'] = $fid;
    $params[':fid2'] = $fid;
    $params[':fid3'] = "%$fid%";
}
if ($salesman_id !== '') {
    $where .= " AND s.salesman_id = :salesman_id";
    $params[':salesman_id'] = $salesman_id;
}
if ($status !== '') {
    $where .= " AND s.status = :status";
    $params[':status'] = $status;
}

$query = "
    SELECT s.*, 
           u.username as salesman_name,
           c.status as crm_status,
           f.brand_name, f.company_name
    FROM shortlists s
    LEFT JOIN users u ON s.salesman_id = u.id
    LEFT JOIN investor_crm_status c ON s.investor_id = c.investor_id
    LEFT JOIN crm_franchisors f ON s.franchisor_id = f.franchisor_id OR s.franchisor_id = f.id
    WHERE $where
    ORDER BY s.created_at DESC
";

$stmt = $pdoCrm->prepare($query);
$stmt->execute($params);
$shortlistsRaw = $stmt->fetchAll();

// Get details for investors from API (Lead Management)
$investorIds = array_unique(array_column($shortlistsRaw, 'investor_id'));
$invApiRes = fetchBulkFromApi('investor', $investorIds);
$invMap = $invApiRes['data'] ?? [];

$shortlists = [];
foreach($shortlistsRaw as $sl) {
    $invData = $invMap[$sl['investor_id']] ?? [];
    $sl['inv_name'] = ($invData['first_name'] ?? 'N/A') . ' ' . ($invData['last_name'] ?? '');
    $sl['inv_city'] = $invData['city'] ?? 'N/A';
    $sl['inv_state'] = $invData['state'] ?? 'N/A';
    $sl['investment_range'] = ($invData['min_investment'] ?? '0') . ' - ' . ($invData['max_investment'] ?? '0');
    $shortlists[] = $sl;
}

$salesmen = $pdoCrm->query("SELECT id, username FROM users WHERE role IN ('salesman', 'manager', 'admin')")->fetchAll();
?>

<div class="card">
    <h5 class="card-header">Matched / Shortlisted Investors</h5>
    <div class="card-body">
        <form method="GET" class="mb-4">
            <div class="row g-2">
                <div class="col-md-3">
                    <input type="text" name="fid" class="form-control" placeholder="Franchisor ID or Brand" value="<?= htmlspecialchars($fid) ?>">
                </div>
                <?php if($role !== 'salesman'): ?>
                <div class="col-md-3">
                    <select name="salesman_id" class="form-select">
                        <option value="">All Salesmen</option>
                        <?php foreach($salesmen as $sm): ?>
                            <option value="<?= $sm['id'] ?>" <?= $salesman_id == $sm['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sm['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">All Align Statuses</option>
                        <option value="Shortlisted" <?= $status == 'Shortlisted' ? 'selected' : '' ?>>Shortlisted</option>
                        <option value="Meeting Scheduled" <?= $status == 'Meeting Scheduled' ? 'selected' : '' ?>>Meeting Scheduled</option>
                        <option value="Closed" <?= $status == 'Closed' ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Filter Matches</button>
                </div>
            </div>
        </form>

        <div class="table-responsive text-nowrap">
            <table class="table table-hover table-bordered">
                <thead>
                    <tr>
                        <th>Franchisor / Brand</th>
                        <th>Investor / Lead</th>
                        <th>Investor Location</th>
                        <th>Investment Range</th>
                        <th>CRM Status</th>
                        <th>Align Status</th>
                        <th>Salesman</th>
                        <th>Matched On</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                    <?php if(empty($shortlists)): ?>
                        <tr><td colspan="9" class="text-center">No matched investors found.</td></tr>
                    <?php else: ?>
                        <?php foreach($shortlists as $sl): ?>
                        <tr>
                            <td>
                                <div class="d-flex flex-column">
                                    <a href="view.php?type=franchisor&id=<?= htmlspecialchars($sl['franchisor_id']) ?>" target="_blank" class="fw-bold text-primary"><?= htmlspecialchars($sl['brand_name'] ?: $sl['franchisor_id']) ?></a>
                                    <small class="text-muted"><?= htmlspecialchars($sl['company_name']) ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex flex-column">
                                    <a href="view.php?type=investor&id=<?= htmlspecialchars($sl['investor_id']) ?>" target="_blank" class="fw-bold text-info"><?= htmlspecialchars($sl['inv_name']) ?></a>
                                    <small class="text-muted">ID: <?= htmlspecialchars($sl['investor_id']) ?></small>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($sl['inv_city']) ?>, <?= htmlspecialchars($sl['inv_state']) ?></td>
                            <td><?= htmlspecialchars($sl['investment_range']) ?></td>
                            <td>
                                <?php if($sl['crm_status']): ?>
                                    <span class="badge bg-label-info"><?= htmlspecialchars($sl['crm_status']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-label-success"><?= htmlspecialchars($sl['status']) ?></span></td>
                            <td><?= htmlspecialchars($sl['salesman_name']) ?></td>
                            <td><?= htmlspecialchars(date('M d, Y', strtotime($sl['created_at']))) ?></td>
                            <td class="text-center">
                                <a href="view.php?type=investor&id=<?= $sl['investor_id'] ?>" class="btn btn-sm btn-primary">Profile</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
