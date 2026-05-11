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

// Pagination Config
$limit = 10;

// Franchisor Pagination
$f_page = isset($_GET['f_page']) ? (int)$_GET['f_page'] : 1;
if ($f_page < 1) $f_page = 1;
$f_offset = ($f_page - 1) * $limit;

$countFranQuery = "SELECT COUNT(*) FROM activity_logs a WHERE a.entity_type = 'franchisor' $ufWhere $filterWhere";
$stmtCountFran = $pdoCrm->prepare($countFranQuery);
$stmtCountFran->execute($params);
$totalFran = (int)$stmtCountFran->fetchColumn();
$totalFranPages = ceil($totalFran / $limit);

// Fetch Franchisor Logs
$queryFran = "
    SELECT a.*, u.username, f.brand_name, f.company_name, f.franchisor_id as manual_id
    FROM activity_logs a 
    JOIN users u ON a.user_id = u.id 
    LEFT JOIN crm_franchisors f ON (a.entity_id = f.id OR a.entity_id = f.franchisor_id)
    WHERE a.entity_type = 'franchisor' $ufWhere $filterWhere
    ORDER BY a.created_at DESC
    LIMIT $limit OFFSET $f_offset
";
$stmtFran = $pdoCrm->prepare($queryFran);
$stmtFran->execute($params);
$franLogs = $stmtFran->fetchAll();

// Investor Pagination
$i_page = isset($_GET['i_page']) ? (int)$_GET['i_page'] : 1;
if ($i_page < 1) $i_page = 1;
$i_offset = ($i_page - 1) * $limit;

$countInvQuery = "SELECT COUNT(*) FROM activity_logs a WHERE a.entity_type = 'investor' $ufWhere $filterWhere";
$stmtCountInv = $pdoCrm->prepare($countInvQuery);
$stmtCountInv->execute($params);
$totalInv = (int)$stmtCountInv->fetchColumn();
$totalInvPages = ceil($totalInv / $limit);

// Fetch Investor Logs
$queryInv = "
    SELECT a.*, u.username
    FROM activity_logs a 
    JOIN users u ON a.user_id = u.id 
    WHERE a.entity_type = 'investor' $ufWhere $filterWhere
    ORDER BY a.created_at DESC
    LIMIT $limit OFFSET $i_offset
";
$stmtInv = $pdoCrm->prepare($queryInv);
$stmtInv->execute($params);
$invLogsRaw = $stmtInv->fetchAll();

// Get real IDs for investors from Lead Management API
$invIds = array_unique(array_column($invLogsRaw, 'entity_id'));
$invApiRes = !empty($invIds) ? fetchBulkFromApi('investor', $invIds) : ['data' => []];
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
                <span class="badge rounded-pill badge-center h-px-20 w-px-20 bg-label-primary ms-1"><?= $totalFran ?></span>
            </button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-inv">
                <i class="bx bx-user me-1"></i> Investor Logs
                <span class="badge rounded-pill badge-center h-px-20 w-px-20 bg-label-success ms-1"><?= $totalInv ?></span>
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
                                <td class="text-wrap" style="max-width: 350px;">
                                    <?php 
                                    $notes = htmlspecialchars($log['notes']);
                                    if(strlen($notes) > 100): 
                                        echo nl2br(substr($notes, 0, 100)) . '...';
                                    ?>
                                        <a href="javascript:void(0);" class="text-primary d-block mt-1 small" 
                                           onclick='showFullNote(<?= json_encode($log['notes']) ?>)'>View Full</a>
                                    <?php else: ?>
                                        <?= nl2br($notes) ?>
                                    <?php endif; ?>
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

            <!-- Franchisor Pagination -->
            <?php if($totalFranPages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination pagination-sm justify-content-center">
                    <li class="page-item <?= $f_page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?f_page=<?= $f_page - 1 ?>&i_page=<?= $i_page ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&search_id=<?= $search_id ?>">Previous</a>
                    </li>
                    <?php 
                    $start = max(1, $f_page - 2);
                    $end = min($totalFranPages, $f_page + 2);
                    for($i=$start; $i<=$end; $i++): 
                    ?>
                        <li class="page-item <?= $i == $f_page ? 'active' : '' ?>">
                            <a class="page-link" href="?f_page=<?= $i ?>&i_page=<?= $i_page ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&search_id=<?= $search_id ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $f_page >= $totalFranPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?f_page=<?= $f_page + 1 ?>&i_page=<?= $i_page ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&search_id=<?= $search_id ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
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
                                <td class="text-wrap" style="max-width: 350px;">
                                    <?php 
                                    $notes = htmlspecialchars($log['notes']);
                                    if(strlen($notes) > 100): 
                                        echo nl2br(substr($notes, 0, 100)) . '...';
                                    ?>
                                        <a href="javascript:void(0);" class="text-primary d-block mt-1 small" 
                                           onclick='showFullNote(<?= json_encode($log['notes']) ?>)'>View Full</a>
                                    <?php else: ?>
                                        <?= nl2br($notes) ?>
                                    <?php endif; ?>
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

            <!-- Investor Pagination -->
            <?php if($totalInvPages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination pagination-sm justify-content-center">
                    <li class="page-item <?= $i_page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?i_page=<?= $i_page - 1 ?>&f_page=<?= $f_page ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&search_id=<?= $search_id ?>">Previous</a>
                    </li>
                    <?php 
                    $start = max(1, $i_page - 2);
                    $end = min($totalInvPages, $i_page + 2);
                    for($idx=$start; $idx<=$end; $idx++): 
                    ?>
                        <li class="page-item <?= $idx == $i_page ? 'active' : '' ?>">
                            <a class="page-link" href="?i_page=<?= $idx ?>&f_page=<?= $f_page ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&search_id=<?= $search_id ?>"><?= $idx ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $i_page >= $totalInvPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?i_page=<?= $i_page + 1 ?>&f_page=<?= $f_page ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&search_id=<?= $search_id ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Full Note View Modal -->
<div class="modal fade" id="fullNoteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Activity Note</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
          <div id="fullNoteContent" style="white-space: pre-wrap;"></div>
      </div>
    </div>
  </div>
</div>

<?php require_once 'footer.php'; ?>

<script>
function showFullNote(content) {
    document.getElementById('fullNoteContent').innerText = content;
    new bootstrap.Modal(document.getElementById('fullNoteModal')).show();
}

// Preserve active tab on pagination
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('i_page')) {
        const invTab = document.querySelector('button[data-bs-target="#navs-inv"]');
        if(invTab) invTab.click();
    }
});
</script>
