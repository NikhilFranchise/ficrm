<?php
require_once 'header.php';
require_once 'db.php';
require_once 'api_client.php';

$search_type = $_GET['search_type'] ?? 'investor';
$budget = $_GET['budget'] ?? '';
$state = $_GET['state'] ?? '';
$city = $_GET['city'] ?? '';
$industry = $_GET['industry'] ?? '';
$property_type = $_GET['property_type'] ?? '';

$results = [];
$searched = false;

if (isset($_GET['search_type'])) {
    $searched = true;
    if ($search_type === 'investor') {
        // Search for Investors (Leads)
        $where = "1=1";
        $params = [];
        
        if ($budget !== '') {
            $where .= " AND p.investment_range = :budget";
            $params[':budget'] = $budget;
        }
        if ($state !== '') {
            $where .= " AND p.looking_business_state LIKE :state";
            $params[':state'] = "%$state%";
        }
        if ($city !== '') {
            $where .= " AND p.looking_business_city LIKE :city";
            $params[':city'] = "%$city%";
        }
        if ($industry !== '') {
            $where .= " AND p.industry_type LIKE :industry";
            $params[':industry'] = "%$industry%";
        }
        
        $where .= " AND p.investor_id IS NOT NULL";
        
        $sql = "SELECT p.* FROM investor_preferences p WHERE $where ORDER BY p.updated_at DESC";
        try {
            $stmt = $pdoCrm->prepare($sql);
            $stmt->execute($params);
            $resultsRaw = $stmt->fetchAll();
            
            // Get data from Lead API
            $investorIds = array_unique(array_column($resultsRaw, 'investor_id'));
            $apiRes = fetchBulkFromApi('investor', $investorIds);
            $invMap = $apiRes['data'] ?? [];
            
            $results = [];
            foreach($resultsRaw as $row) {
                if (isset($invMap[$row['investor_id']])) {
                    $invData = $invMap[$row['investor_id']];
                    $row['investor_name'] = $invData['first_name'] . ' ' . $invData['last_name'];
                    $row['mobile'] = $invData['mobile'] ?? 'N/A';
                    $row['email'] = $invData['email'] ?? 'N/A';
                    $results[] = $row;
                }
            }
        } catch (Exception $e) {
            die("Error searching investors: " . $e->getMessage());
        }
        
    } else {
        // Search for Franchisors (Local CRM Table)
        $where = "1=1";
        $params = [];
        
        if ($budget !== '') {
            if (is_numeric($budget)) {
                $where .= " AND p.unit_inv_max >= :budget1 AND p.unit_inv_min <= :budget2";
                $params[':budget1'] = $budget;
                $params[':budget2'] = $budget;
            }
        }
        if ($state !== '') {
            $where .= " AND p.state LIKE :state";
            $params[':state'] = "%$state%";
        }
        if ($city !== '') {
            $where .= " AND p.preferred_cities LIKE :city";
            $params[':city'] = "%$city%";
        }
        if ($industry !== '') {
            $where .= " AND p.industry LIKE :industry";
            $params[':industry'] = "%$industry%";
        }
        
        $sql = "SELECT p.* FROM franchisor_requirements p WHERE $where ORDER BY p.updated_at DESC";
        try {
            $stmt = $pdoCrm->prepare($sql);
            $stmt->execute($params);
            $resultsRaw = $stmt->fetchAll();
            
            // Join with local franchisors table
            $results = [];
            foreach($resultsRaw as $row) {
                // Search by both internal ID and manual franchisor_id
                $st = $pdoCrm->prepare("SELECT id, brand_name, company_name, city, state FROM crm_franchisors WHERE id = ? OR franchisor_id = ? LIMIT 1");
                $st->execute([$row['franchisor_id'], $row['franchisor_id']]);
                $franData = $st->fetch();
                if ($franData) {
                    $row['local_id'] = $franData['id'];
                    $row['company_name'] = $franData['company_name'];
                    $row['brand_name'] = $franData['brand_name'];
                    $row['f_city'] = $franData['city'];
                    $row['f_state'] = $franData['state'];
                    $results[] = $row;
                }
            }
        } catch (Exception $e) {
            die("Error searching franchisors: " . $e->getMessage());
        }
    }
}
?>

<div class="card mb-4">
    <h5 class="card-header bg-primary text-white"><i class="bx bx-target-lock me-2"></i> Advanced Matchmaking Engine</h5>
    <div class="card-body mt-3">
        <form method="GET">
            <div class="row g-3">
                <div class="col-md-12 mb-2">
                    <label class="form-label d-block fw-bold text-primary">I am looking for:</label>
                    <div class="form-check form-check-inline mt-2">
                        <input class="form-check-input" type="radio" name="search_type" id="find_investor" value="investor" <?= $search_type === 'investor' ? 'checked' : '' ?>>
                        <label class="form-check-label h6 mb-0" for="find_investor">Investors (Buyers)</label>
                    </div>
                    <div class="form-check form-check-inline mt-2 ms-4">
                        <input class="form-check-input" type="radio" name="search_type" id="find_franchisor" value="franchisor" <?= $search_type === 'franchisor' ? 'checked' : '' ?>>
                        <label class="form-check-label h6 mb-0" for="find_franchisor">Franchisors (Local Brands)</label>
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Preferred State</label>
                    <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($state) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Preferred City</label>
                    <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($city) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Industry Match</label>
                    <input type="text" name="industry" class="form-control" value="<?= htmlspecialchars($industry) ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Find Matches</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if($searched): ?>
<div class="card">
    <h5 class="card-header">Search Results <span class="badge bg-label-primary ms-2"><?= count($results) ?> matches</span></h5>
    <div class="table-responsive text-nowrap">
        <table class="table table-hover table-striped">
            <thead class="table-light">
                <tr>
                    <th>Match Profile ID</th>
                    <th>Target Details</th>
                    <th>Matched Preferences</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($results)): ?>
                    <tr><td colspan="4" class="text-center py-5">No exact matches found.</td></tr>
                <?php else: ?>
                    <?php foreach($results as $row): ?>
                        <tr>
                            <td>
                                <?php if($search_type === 'investor'): ?>
                                    <span class="text-success fw-bold"><i class="bx bx-user me-1"></i> <?= htmlspecialchars($row['investor_id']) ?></span>
                                <?php else: ?>
                                    <span class="text-info fw-bold"><i class="bx bx-store-alt me-1"></i> <?= htmlspecialchars($row['franchisor_id']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($search_type === 'investor'): ?>
                                    <span class="d-block fw-bold"><?= htmlspecialchars($row['investor_name']) ?></span>
                                    <small class="text-muted"><?= htmlspecialchars($row['email']) ?></small>
                                <?php else: ?>
                                    <span class="d-block fw-bold"><?= htmlspecialchars($row['brand_name']) ?></span>
                                    <small class="text-muted"><?= htmlspecialchars($row['f_city']) ?>, <?= htmlspecialchars($row['f_state']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="bg-lighter p-2 rounded small">
                                    <?php if($search_type === 'investor'): ?>
                                        <span><strong>Loc:</strong> <?= htmlspecialchars($row['looking_business_city'] ?: 'Any') ?></span> | 
                                        <span><strong>Budget:</strong> <?= htmlspecialchars($row['investment_range']) ?></span>
                                    <?php else: ?>
                                        <span><strong>Exp:</strong> <?= htmlspecialchars($row['preferred_cities'] ?: 'Any') ?></span> | 
                                        <span><strong>Req:</strong> <?= number_format($row['unit_inv_min']) ?> - <?= number_format($row['unit_inv_max']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <a href="view.php?type=<?= $search_type ?>&id=<?= $search_type === 'investor' ? $row['investor_id'] : $row['local_id'] ?>" class="btn btn-sm btn-primary">Profile</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once 'footer.php'; ?>
