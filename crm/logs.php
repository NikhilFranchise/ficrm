<?php
require_once 'header.php';
require_once 'db.php';
require_once 'api_client.php';

$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'salesman';

$ufWhere = ($role === 'salesman') ? " AND a.user_id = $userId" : "";

$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search_id = $_GET['search_id'] ?? '';

$filterWhere = "";
$params = [];
if ($date_from !== '') {
    $filterWhere .= " AND DATE(a.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}
if ($date_to !== '') {
    $filterWhere .= " AND DATE(a.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}
if ($search_id !== '') {
    $filterWhere .= " AND a.entity_id = :search_id";
    $params[':search_id'] = $search_id;
}

// Fetch Franchisor Logs - Join with local crm_franchisors
$queryFran = "
    SELECT a.*, u.username, f.brand_name, f.company_name, f.franchisor_id as manual_id
    FROM activity_logs a 
    JOIN users u ON a.user_id = u.id 
    LEFT JOIN crm_franchisors f ON (a.entity_id = f.id OR a.entity_id = f.franchisor_id)
    WHERE a.entity_type = 'franchisor' $ufWhere $filterWhere
    ORDER BY a.created_at DESC
";
$stmtFran = $pdoCrm->prepare($queryFran);
$stmtFran->execute($params);
$franLogs = $stmtFran->fetchAll();

// Fetch Investor Logs
$queryInv = "
    SELECT a.*, u.username
    FROM activity_logs a 
    JOIN users u ON a.user_id = u.id 
    WHERE a.entity_type = 'investor' $ufWhere $filterWhere
    ORDER BY a.created_at DESC
";
$stmtInv = $pdoCrm->prepare($queryInv);
$stmtInv->execute($params);
$invLogsRaw = $stmtInv->fetchAll();

// Get real IDs for investors from Lead Management API
$invIds = array_unique(array_column($invLogsRaw, 'entity_id'));
$invApiRes = fetchBulkFromApi('investor', $invIds);
$invMap = $invApiRes['data'] ?? [];

$invLogs = [];
foreach($invLogsRaw as $log) {
    $invData = $invMap[$log['entity_id']] ?? [];
    $log['display_name'] = ($invData['first_name'] ?? '') . ' ' . ($invData['last_name'] ?? '');
    if (trim($log['display_name']) === '') $log['display_name'] = 'Lead #' . $log['entity_id'];
    $invLogs[] = $log;
}
?>

<div class="card mb-4">
    <h5 class="card-header">Filter Logs</h5>
    <div class="card-body border-top">
        <form method="GET">
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label">Search ID</label>
                    <input type="text" name="search_id" class="form-control" placeholder="e.g. 484889" value="<?= htmlspecialchars($search_id) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter Logs</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="nav-align-top mb-4">
    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item">
            <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-fran">
                <i class="bx bx-store-alt me-1"></i> Franchisor Logs
                <span class="badge rounded-pill badge-center h-px-20 w-px-20 bg-label-primary ms-1"><?= count($franLogs) ?></span>
            </button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-inv">
                <i class="bx bx-user me-1"></i> Investor Logs
                <span class="badge rounded-pill badge-center h-px-20 w-px-20 bg-label-success ms-1"><?= count($invLogs) ?></span>
            </button>
        </li>
    </ul>
    
    <div class="tab-content">
        <!-- Franchisor Tab -->
        <div class="tab-pane fade show active" id="navs-fran" role="tabpanel">
            <div class="table-responsive text-nowrap">
                <table class="table table-hover table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Date & Time</th>
                            <th>Franchisor / Brand</th>
                            <th>Activity Type</th>
                            <th>Notes</th>
                            <th>Salesman</th>
                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        <?php if(empty($franLogs)): ?>
                            <tr><td colspan="5" class="text-center py-4">No franchisor logs found.</td></tr>
                        <?php else: ?>
                            <?php foreach($franLogs as $log): ?>
                            <tr>
                                <td>
                                    <strong><?= date('M d, Y', strtotime($log['created_at'])) ?></strong><br>
                                    <small class="text-muted"><?= date('h:i A', strtotime($log['created_at'])) ?></small>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <a href="view.php?type=franchisor&id=<?= htmlspecialchars($log['entity_id']) ?>" target="_blank" class="fw-bold text-primary">
                                            <?= htmlspecialchars($log['brand_name'] ?: $log['manual_id'] ?: $log['entity_id']) ?>
                                        </a>
                                        <small class="text-muted"><?= htmlspecialchars($log['company_name']) ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-label-info"><?= htmlspecialchars($log['activity_type']) ?></span>
                                    <?php if($log['priority']): ?>
                                        <span class="badge bg-label-<?= $log['priority']=='High'?'danger':($log['priority']=='Medium'?'warning':'secondary') ?> ms-1"><?= htmlspecialchars($log['priority']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-wrap" style="max-width: 400px;">
                                    <?= nl2br(htmlspecialchars($log['notes'])) ?>
                                    <?php if($log['follow_up_date']): ?>
                                        <div class="mt-1"><small class="text-warning fw-bold"><i class="bx bx-calendar"></i> Follow up: <?= htmlspecialchars($log['follow_up_date']) ?></small></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($log['username']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Investor Tab -->
        <div class="tab-pane fade" id="navs-inv" role="tabpanel">
            <div class="table-responsive text-nowrap">
                <table class="table table-hover table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Date & Time</th>
                            <th>Investor / Lead</th>
                            <th>Activity Type</th>
                            <th>Notes</th>
                            <th>Salesman</th>
                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        <?php if(empty($invLogs)): ?>
                            <tr><td colspan="5" class="text-center py-4">No investor logs found.</td></tr>
                        <?php else: ?>
                            <?php foreach($invLogs as $log): ?>
                            <tr>
                                <td>
                                    <strong><?= date('M d, Y', strtotime($log['created_at'])) ?></strong><br>
                                    <small class="text-muted"><?= date('h:i A', strtotime($log['created_at'])) ?></small>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <a href="view.php?type=investor&id=<?= htmlspecialchars($log['entity_id']) ?>" target="_blank" class="fw-bold text-success">
                                            <?= htmlspecialchars($log['display_name']) ?>
                                        </a>
                                        <small class="text-muted">ID: <?= htmlspecialchars($log['entity_id']) ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-label-info"><?= htmlspecialchars($log['activity_type']) ?></span>
                                    <?php if($log['priority']): ?>
                                        <span class="badge bg-label-<?= $log['priority']=='High'?'danger':($log['priority']=='Medium'?'warning':'secondary') ?> ms-1"><?= htmlspecialchars($log['priority']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-wrap" style="max-width: 400px;">
                                    <?= nl2br(htmlspecialchars($log['notes'])) ?>
                                    <?php if($log['follow_up_date']): ?>
                                        <div class="mt-1"><small class="text-warning fw-bold"><i class="bx bx-calendar"></i> Follow up: <?= htmlspecialchars($log['follow_up_date']) ?></small></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($log['username']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
