<?php
require_once 'api_client.php';
require_once 'db.php';
require_once 'auth.php';

$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? '';

if (!in_array($type, ['investor', 'franchisor']) || !$id) {
    require_once 'header.php';
    echo "<div class='container-xxl mt-4'>Invalid request.</div>";
    require_once 'footer.php';
    exit;
}

$isInvestor = ($type === 'investor');

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $userId = $_SESSION['user_id'];
    
    if ($action === 'log_call') {
        $notes = trim($_POST['notes']);
        $activity_type = $_POST['activity_type'] ?? 'Call';
        $follow_up_date = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null;
        
        $priority = !empty($_POST['priority']) ? $_POST['priority'] : null;
        $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
        $sub_category_id = !empty($_POST['sub_category_id']) ? $_POST['sub_category_id'] : null;
        $sub_sub_category_id = !empty($_POST['sub_sub_category_id']) ? $_POST['sub_sub_category_id'] : null;
        $location = !empty($_POST['location']) ? $_POST['location'] : null;
        $investment_range = !empty($_POST['investment_range']) ? $_POST['investment_range'] : null;
        
        $cat_name = !empty($_POST['cat_name']) ? $_POST['cat_name'] : null;
        $sub_cat_name = !empty($_POST['sub_cat_name']) ? $_POST['sub_cat_name'] : null;
        $sub_sub_cat_name = !empty($_POST['sub_sub_cat_name']) ? $_POST['sub_sub_cat_name'] : null;
        
        $stmt = $pdoCrm->prepare("INSERT INTO activity_logs (entity_type, entity_id, user_id, activity_type, notes, follow_up_date, priority, category_id, sub_category_id, sub_sub_category_id, location, investment_range, cat_name, sub_cat_name, sub_sub_cat_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$type, $id, $userId, $activity_type, $notes, $follow_up_date, $priority, $category_id, $sub_category_id, $sub_sub_category_id, $location, $investment_range, $cat_name, $sub_cat_name, $sub_sub_cat_name]);
        
    } elseif ($action === 'schedule_meeting') {
        $target_id = $_POST['target_id'] ?? '';
        $meeting_datetime = $_POST['meeting_datetime'] ?? '';
        $meeting_type = $_POST['meeting_type'] ?? 'Online';
        $notes = $_POST['notes'] ?? '';
        
        $fran_id = $isInvestor ? $target_id : $id;
        $inv_id = $isInvestor ? $id : $target_id;
        
        // Validate target_id
        $targetName = 'Unknown';
        if ($isInvestor) {
            // Target is Franchisor (Local CRM Table)
            $stmt = $pdoCrm->prepare("SELECT brand_name FROM crm_franchisors WHERE id = ? OR franchisor_id = ? LIMIT 1");
            $stmt->execute([$target_id, $target_id]);
            $fData = $stmt->fetch();
            $exists = (bool)$fData;
            if ($exists) $targetName = $fData['brand_name'];
        } else {
            // Target is Investor (Lead Management - Remote)
            $vRes = validateIdFromApi('investor', $target_id);
            $exists = $vRes['exists'] ?? false;
            if ($exists) $targetName = ($vRes['data']['first_name'] ?? '') . ' ' . ($vRes['data']['last_name'] ?? '');
        }
        
        if (!$exists) {
            header("Location: view.php?type=$type&id=$id&msg=invalid_id");
            exit;
        }
        
        $stmt = $pdoCrm->prepare("INSERT INTO meetings (franchisor_id, investor_id, salesman_id, meeting_datetime, meeting_type, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$fran_id, $inv_id, $userId, $meeting_datetime, $meeting_type, $notes]);
        
        // Also log the activity
        $log_notes = "Meeting scheduled ($meeting_type) with " . ($isInvestor ? "Franchisor" : "Investor") . ": $targetName (ID: $target_id) on $meeting_datetime. Notes: $notes";
        try {
            // Log on current profile
            $stmtLog = $pdoCrm->prepare("INSERT INTO activity_logs (entity_type, entity_id, user_id, activity_type, notes) VALUES (?, ?, ?, ?, ?)");
            $stmtLog->execute([$type, $id, $userId, 'Meeting Scheduled', $log_notes]);
            
            // Log on target profile too
            $targetType = $isInvestor ? 'franchisor' : 'investor';
            $stmtLog->execute([$targetType, $target_id, $userId, 'Meeting Scheduled', $log_notes]);
        } catch (\PDOException $e) {
            // log error
        }
        
    } elseif ($action === 'update_status' && $isInvestor) {
        $status = $_POST['status'];
        $stmt = $pdoCrm->prepare("INSERT INTO investor_crm_status (investor_id, status, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status=?, updated_by=?, updated_at=CURRENT_TIMESTAMP");
        $stmt->execute([$id, $status, $userId, $status, $userId]);
        
        try {
            $stmtLog = $pdoCrm->prepare("INSERT INTO activity_logs (entity_type, entity_id, user_id, activity_type, notes) VALUES (?, ?, ?, ?, ?)");
            $stmtLog->execute([$type, $id, $userId, 'Status Update', "Status changed to: $status"]);
        } catch (\PDOException $e) {
            // log error
        }
        
    } elseif ($action === 'shortlist') {
        $target_id = $_POST['target_id'];
        $fran_id = $isInvestor ? $target_id : $id;
        $inv_id = $isInvestor ? $id : $target_id;
        
        // Validate target_id
        $targetName = 'Unknown';
        if ($isInvestor) {
            // Target is Franchisor (Local CRM Table)
            $stmt = $pdoCrm->prepare("SELECT brand_name FROM crm_franchisors WHERE id = ? OR franchisor_id = ? LIMIT 1");
            $stmt->execute([$target_id, $target_id]);
            $fData = $stmt->fetch();
            $exists = (bool)$fData;
            if ($exists) $targetName = $fData['brand_name'];
        } else {
            // Target is Investor (Lead Management - Remote)
            $vRes = validateIdFromApi('investor', $target_id);
            $exists = $vRes['exists'] ?? false;
            if ($exists) $targetName = ($vRes['data']['first_name'] ?? '') . ' ' . ($vRes['data']['last_name'] ?? '');
        }
        
        if (!$exists) {
            header("Location: view.php?type=$type&id=$id&msg=invalid_id");
            exit;
        }
        
        $stmt = $pdoCrm->prepare("INSERT INTO shortlists (franchisor_id, investor_id, salesman_id) VALUES (?, ?, ?)");
        $stmt->execute([$fran_id, $inv_id, $userId]);
        
        try {
            $log_txt = "Shortlisted " . ($isInvestor ? "Franchisor" : "Investor") . ": $targetName (ID: $target_id)";
            
            // Log on current profile
            $stmtLog = $pdoCrm->prepare("INSERT INTO activity_logs (entity_type, entity_id, user_id, activity_type, notes) VALUES (?, ?, ?, ?, ?)");
            $stmtLog->execute([$type, $id, $userId, 'Shortlisted', $log_txt]);
            
            // Log on target profile too
            $targetType = $isInvestor ? 'franchisor' : 'investor';
            $stmtLog->execute([$targetType, $target_id, $userId, 'Shortlisted', $log_txt]);
        } catch (\PDOException $e) {
            // log error
        }
        
    } elseif ($action === 'save_preferences') {
        if ($isInvestor) {
            $stmt = $pdoCrm->prepare("
                INSERT INTO investor_preferences (
                    investor_id, industry_type, ind_main_cat, ind_cat, looking_for, investment_range, 
                    need_loan, invest_timeframe, country, looking_business_state, looking_business_city, 
                    own_property, property_type, floor_area, occupation, education, business_experience
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    industry_type=VALUES(industry_type), ind_main_cat=VALUES(ind_main_cat), ind_cat=VALUES(ind_cat),
                    looking_for=VALUES(looking_for), investment_range=VALUES(investment_range), need_loan=VALUES(need_loan),
                    invest_timeframe=VALUES(invest_timeframe), country=VALUES(country), looking_business_state=VALUES(looking_business_state),
                    looking_business_city=VALUES(looking_business_city), own_property=VALUES(own_property), property_type=VALUES(property_type),
                    floor_area=VALUES(floor_area), occupation=VALUES(occupation), education=VALUES(education), business_experience=VALUES(business_experience)
            ");
            $stmt->execute([
                $id,
                $_POST['industry_type'] ?? '',
                $_POST['ind_main_cat'] ?? '',
                $_POST['ind_cat'] ?? '',
                $_POST['looking_for'] ?? '',
                $_POST['investment_range'] ?? '',
                $_POST['need_loan'] ?? 0,
                $_POST['invest_timeframe'] ?? '',
                $_POST['country'] ?? '',
                $_POST['looking_business_state'] ?? '',
                $_POST['looking_business_city'] ?? '',
                $_POST['own_property'] ?? 0,
                $_POST['property_type'] ?? '',
                $_POST['floor_area'] ?? 0,
                $_POST['occupation'] ?? '',
                $_POST['education'] ?? '',
                $_POST['business_experience'] ?? 0
            ]);
        } else {
            $stmt = $pdoCrm->prepare("
                INSERT INTO franchisor_requirements (
                    franchisor_id, industry, looking_franchise, unit_inv_min, unit_inv_max, is_finance_aid,
                    expansion_urgency, country, state, preferred_cities, expansion_location, property_type,
                    prop_area_min, prop_area_max, target_investor_profile, preferred_education, required_experience
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    industry=VALUES(industry), looking_franchise=VALUES(looking_franchise), unit_inv_min=VALUES(unit_inv_min),
                    unit_inv_max=VALUES(unit_inv_max), is_finance_aid=VALUES(is_finance_aid), expansion_urgency=VALUES(expansion_urgency),
                    country=VALUES(country), state=VALUES(state), preferred_cities=VALUES(preferred_cities), expansion_location=VALUES(expansion_location),
                    property_type=VALUES(property_type), prop_area_min=VALUES(prop_area_min), prop_area_max=VALUES(prop_area_max),
                    target_investor_profile=VALUES(target_investor_profile), preferred_education=VALUES(preferred_education), required_experience=VALUES(required_experience)
            ");
            $stmt->execute([
                $id,
                $_POST['industry'] ?? '',
                $_POST['looking_franchise'] ?? '',
                $_POST['unit_inv_min'] ?: 0,
                $_POST['unit_inv_max'] ?: 0,
                $_POST['is_finance_aid'] ?? 0,
                $_POST['expansion_urgency'] ?? '',
                $_POST['country'] ?? '',
                $_POST['state'] ?? '',
                $_POST['preferred_cities'] ?? '',
                $_POST['expansion_location'] ?? '',
                $_POST['property_type'] ?? '',
                $_POST['prop_area_min'] ?: 0,
                $_POST['prop_area_max'] ?: 0,
                $_POST['target_investor_profile'] ?? '',
                $_POST['preferred_education'] ?? '',
                $_POST['required_experience'] ?: 0
            ]);
        }
        
        try {
            $stmtLog = $pdoCrm->prepare("INSERT INTO activity_logs (entity_type, entity_id, user_id, activity_type, notes) VALUES (?, ?, ?, ?, ?)");
            $stmtLog->execute([$type, $id, $userId, 'Preferences Updated', "Matchmaking preferences were updated."]);
        } catch (\PDOException $e) {
            // log error
        }
    }
    
    header("Location: view.php?type=$type&id=$id&msg=success");
    exit;
}

require_once 'header.php';
$catMapping = require_once 'categories.php';

if ($isInvestor) {
    $apiRes = fetchSingleFromApi('investor', $id);
    $record = ($apiRes['status'] === 'success' && !empty($apiRes['data'])) ? $apiRes['data'] : null;
} else {
    // Local Franchisor - Support both internal ID and manual franchisor_id
    $stmt = $pdoCrm->prepare("SELECT * FROM crm_franchisors WHERE id = ? OR franchisor_id = ? LIMIT 1");
    $stmt->execute([$id, $id]);
    $record = $stmt->fetch();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'salesman';
$ufAndA = ($role === 'salesman') ? " AND a.user_id = $userId" : "";
$ufAndC = ($role === 'salesman') ? " AND c.user_id = $userId" : "";
$ufAndS = ($role === 'salesman') ? " AND salesman_id = $userId" : "";

// Fetch timeline
try {
    $stmt = $pdoCrm->prepare("
        SELECT 'activity' as log_source, a.activity_type as title, a.notes, a.created_at, u.username, a.follow_up_date,
               a.priority, a.location, a.investment_range, a.cat_name, a.sub_cat_name, a.sub_sub_cat_name
        FROM activity_logs a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.entity_type = ? AND a.entity_id = ? $ufAndA
        UNION ALL
        SELECT 'comment' as log_source, 'Note' as title, c.comment as notes, c.created_at, u.username, NULL as follow_up_date,
               NULL as priority, NULL as location, NULL as investment_range, NULL as cat_name, NULL as sub_cat_name, NULL as sub_sub_cat_name
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.entity_type = ? AND c.entity_id = ? $ufAndC
        ORDER BY created_at DESC
    ");
    $stmt->execute([$type, $id, $type, $id]);
    $timeline = $stmt->fetchAll();
} catch (\PDOException $e) {
    $timeline = [];
}

// Fetch status if investor
$current_status = 'Pending';
if ($isInvestor) {
    $st = $pdoCrm->prepare("SELECT status FROM investor_crm_status WHERE investor_id = ?");
    $st->execute([$id]);
    $current_status = $st->fetchColumn() ?: 'Pending';
}

// Fetch shortlists
if ($isInvestor) {
    $shortlistStmt = $pdoCrm->prepare("SELECT * FROM shortlists WHERE investor_id = ? $ufAndS");
    $shortlistStmt->execute([$id]);
} else {
    // For Franchisors, check both manual franchisor_id and internal id in shortlists table
    $shortlistStmt = $pdoCrm->prepare("SELECT * FROM shortlists WHERE (franchisor_id = ? OR franchisor_id = ?) $ufAndS");
    $shortlistStmt->execute([$record['id'] ?? $id, $record['franchisor_id'] ?? $id]);
}
$shortlists = $shortlistStmt->fetchAll();

// Fetch Preferences
$pref = [];
if ($isInvestor) {
    $pStmt = $pdoCrm->prepare("SELECT * FROM investor_preferences WHERE investor_id = ?");
    $pStmt->execute([$id]);
    $pref = $pStmt->fetch() ?: [];
} else {
    $pStmt = $pdoCrm->prepare("SELECT * FROM franchisor_requirements WHERE franchisor_id = ?");
    $pStmt->execute([$id]);
    $pref = $pStmt->fetch() ?: [];
}

// Calculate Dashboard Stats
$statCalls = 0;
$statMeetings = 0;
$statFollowUps = 0;

$timelineStats = array_fill(0, 7, 0);
$labels = [];
for($i=6; $i>=0; $i--) {
    $labels[] = date('M d', strtotime("-$i days"));
}

foreach($timeline as $item) {
    if ($item['title'] === 'Call') {
        $statCalls++;
    }
    if ($item['title'] === 'Meeting Scheduled') {
        $statMeetings++;
    }
    if (!empty($item['follow_up_date']) && strtotime($item['follow_up_date']) >= strtotime('today')) {
        $statFollowUps++;
    }
    
    // For chart
    $day = date('M d', strtotime($item['created_at']));
    $idx = array_search($day, $labels);
    if ($idx !== false) {
        $timelineStats[$idx]++;
    }
}
$statShortlists = count($shortlists);
?>

<div class="d-flex align-items-center mb-4">
    <h4 class="fw-bold py-3 m-0"><span class="text-muted fw-light">CRM /</span> <?= $isInvestor ? 'Investor' : 'Franchisor' ?> Profile</h4>
    <?php if(!$isInvestor): ?>
        <a href="edit_franchisor.php?id=<?= $id ?>" class="btn btn-warning btn-sm ms-3">
            <i class="bx bx-edit-alt me-1"></i> Edit Franchisor
        </a>
    <?php endif; ?>
</div>

<!-- Top Statistics Cards -->
<div class="row mb-4">
    <div class="col-sm-6 col-lg-3 mb-4 mb-lg-0">
        <div class="card card-border-shadow-primary h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2 pb-1">
                    <div class="avatar me-2">
                        <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-phone-call"></i></span>
                    </div>
                    <h4 class="ms-1 mb-0"><?= $statCalls ?></h4>
                </div>
                <p class="mb-1 fw-medium">Total Calls</p>
                <small class="text-muted">Calls logged on profile</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3 mb-4 mb-lg-0">
        <div class="card card-border-shadow-warning h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2 pb-1">
                    <div class="avatar me-2">
                        <span class="avatar-initial rounded bg-label-warning"><i class="bx bx-calendar-event"></i></span>
                    </div>
                    <h4 class="ms-1 mb-0"><?= $statMeetings ?></h4>
                </div>
                <p class="mb-1 fw-medium">Aligned Meetings</p>
                <small class="text-muted">Meetings scheduled</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3 mb-4 mb-sm-0">
        <div class="card card-border-shadow-danger h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2 pb-1">
                    <div class="avatar me-2">
                        <span class="avatar-initial rounded bg-label-danger"><i class="bx bx-link-alt"></i></span>
                    </div>
                    <h4 class="ms-1 mb-0"><?= $statShortlists ?></h4>
                </div>
                <p class="mb-1 fw-medium"><?= $isInvestor ? 'Franchisors Attached' : 'Investors Attached' ?></p>
                <small class="text-muted">Total matches/shortlists</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-border-shadow-info h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2 pb-1">
                    <div class="avatar me-2">
                        <span class="avatar-initial rounded bg-label-info"><i class="bx bx-time-five"></i></span>
                    </div>
                    <h4 class="ms-1 mb-0"><?= $statFollowUps ?></h4>
                </div>
                <p class="mb-1 fw-medium">Pending Follow-ups</p>
                <small class="text-muted">Future follow-up dates</small>
            </div>
        </div>
    </div>
</div>

<!-- Graph Row -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Activity Graph (Last 7 Days)</h5>
            </div>
            <div class="card-body">
                <div id="activityChart" style="min-height: 250px;"></div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-7">
        <div class="nav-align-top mb-4">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-api-data">Master Data</button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-match-pref">Match Preferences</button>
                </li>
            </ul>
            <div class="tab-content">
                <!-- Tab: Master Data -->
                <div class="tab-pane fade show active" id="navs-api-data" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0 text-capitalize"><?= htmlspecialchars($type) ?> Profile</h5>
                        <?php if ($isInvestor): ?>
                            <span class="badge bg-label-primary"><?= htmlspecialchars($current_status) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!$record): ?>
                        <div class="alert alert-danger">Record not found in external API.</div>
                    <?php else: ?>
                    
                    <?php if ($isInvestor): ?>
                        <div class="d-flex align-items-center mb-4">
                            <div class="avatar avatar-xl me-3">
                                <span class="avatar-initial rounded-circle bg-label-primary"><i class="bx bx-user fs-2"></i></span>
                            </div>
                            <div>
                                <h5 class="mb-0 text-primary"><?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?></h5>
                                <small class="text-muted"><i class="bx bx-map"></i> <?= htmlspecialchars($record['city'] ?: 'N/A') ?>, <?= htmlspecialchars($record['state'] ?: 'N/A') ?></small>
                            </div>
                        </div>
                        
                        <div class="row mb-3 g-3">
                            <div class="col-sm-6">
                                <div class="bg-lighter rounded p-3 h-100">
                                    <small class="text-muted text-uppercase d-block mb-1">Lead ID</small>
                                    <span class="fw-medium"><?= htmlspecialchars($record['id']) ?></span>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="bg-lighter rounded p-3 h-100">
                                    <small class="text-muted text-uppercase d-block mb-1">Email</small>
                                    <span class="fw-medium text-break"><?= htmlspecialchars($record['email'] ?: 'N/A') ?></span>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="bg-lighter rounded p-3 h-100 border-start border-3 border-success">
                                    <small class="text-muted text-uppercase d-block mb-1">Budget Range</small>
                                    <span class="fw-medium text-success"><?= number_format($record['min_investment']) ?> - <?= number_format($record['max_investment']) ?></span>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="bg-lighter rounded p-3 h-100">
                                    <small class="text-muted text-uppercase d-block mb-1">Contact No.</small>
                                    <span class="fw-medium"><?= htmlspecialchars($record['mobile']) ?></span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="bg-lighter rounded p-3">
                                    <small class="text-muted text-uppercase d-block mb-1">Interested Category</small>
                                    <span class="fw-medium"><?= htmlspecialchars($record['category_interested'] ?: 'Not Specified') ?></span>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>
                        
                        <div class="d-flex align-items-center mb-4">
                            <div class="avatar avatar-xl me-3">
                                <span class="avatar-initial rounded bg-label-success"><i class="bx bx-store-alt fs-2"></i></span>
                            </div>
                            <div>
                                <h5 class="mb-0 text-success"><?= htmlspecialchars($record['brand_name']) ?></h5>
                                <small class="text-muted fw-bold">ID: <?= htmlspecialchars($record['franchisor_id']) ?></small>
                                <small class="text-muted d-block"><i class="bx bx-map"></i> <?= htmlspecialchars($record['city']) ?>, <?= htmlspecialchars($record['state']) ?></small>
                            </div>
                        </div>

                        <div class="row mb-3 g-3">
                            <div class="col-12">
                                <div class="bg-lighter rounded p-3">
                                    <small class="text-muted text-uppercase d-block mb-1">Local CRM Record Details</small>
                                    <div class="mb-2"><strong>Company:</strong> <?= htmlspecialchars($record['company_name']) ?></div>
                                    <div class="mb-2"><strong>CEO:</strong> <?= htmlspecialchars($record['ceo_name']) ?></div>
                                    <div class="mb-2"><strong>Contact:</strong> <?= htmlspecialchars($record['email']) ?> / <?= htmlspecialchars($record['mobile']) ?></div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="bg-lighter rounded p-3 h-100 border-start border-3 border-primary">
                                    <small class="text-muted text-uppercase d-block mb-1">Investment Required</small>
                                    <span class="fw-bold text-primary"><?= number_format($record['unit_inv_min']) ?> - <?= number_format($record['unit_inv_max']) ?></span>
                                </div>
                            </div>
                        </div>

                        <?php if($record['profile_link']): ?>
                        <div class="mt-4 text-center">
                            <a href="<?= htmlspecialchars($record['profile_link']) ?>" target="_blank" class="btn btn-primary w-100">
                                <i class="bx bx-link-external me-1"></i> View Website / Profile
                            </a>
                        </div>
                        <?php endif; ?>
                        
                    <?php endif; ?>
                <?php endif; ?>
                </div>

                <!-- Tab: Match Preferences -->
                <div class="tab-pane fade" id="navs-match-pref" role="tabpanel">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_preferences">
                        <div class="p-3">
                            <h6 class="mb-3">Industry & Investment Details</h6>
                            <div class="row g-3 mb-4">
                                <?php if ($isInvestor): ?>
                                    <div class="col-md-6">
                                        <label class="form-label">Interested Industry</label>
                                        <input type="text" name="industry_type" class="form-control" value="<?= htmlspecialchars($pref['industry_type'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Investment Range</label>
                                        <select name="investment_range" class="form-select">
                                            <option value="">Select Range</option>
                                            <option value="0-5L" <?= ($pref['investment_range']??'') == '0-5L' ? 'selected' : '' ?>>0 - 5 Lakhs</option>
                                            <option value="5-10L" <?= ($pref['investment_range']??'') == '5-10L' ? 'selected' : '' ?>>5 - 10 Lakhs</option>
                                            <option value="10-50L" <?= ($pref['investment_range']??'') == '10-50L' ? 'selected' : '' ?>>10 - 50 Lakhs</option>
                                            <option value="50L+" <?= ($pref['investment_range']??'') == '50L+' ? 'selected' : '' ?>>50 Lakhs +</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Need Finance / Loan?</label>
                                        <select name="need_loan" class="form-select">
                                            <option value="0" <?= empty($pref['need_loan']) ? 'selected' : '' ?>>No</option>
                                            <option value="1" <?= !empty($pref['need_loan']) ? 'selected' : '' ?>>Yes</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Investment Timeframe</label>
                                        <select name="invest_timeframe" class="form-select">
                                            <option value="">Select</option>
                                            <option value="Immediate" <?= ($pref['invest_timeframe']??'') == 'Immediate' ? 'selected' : '' ?>>Immediate</option>
                                            <option value="1-3 Months" <?= ($pref['invest_timeframe']??'') == '1-3 Months' ? 'selected' : '' ?>>1-3 Months</option>
                                            <option value="3-6 Months" <?= ($pref['invest_timeframe']??'') == '3-6 Months' ? 'selected' : '' ?>>3-6 Months</option>
                                        </select>
                                    </div>
                                <?php else: ?>
                                    <div class="col-md-6">
                                        <label class="form-label">Industry</label>
                                        <input type="text" name="industry" class="form-control" value="<?= htmlspecialchars($pref['industry'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Min Investment</label>
                                        <input type="number" name="unit_inv_min" class="form-control" value="<?= htmlspecialchars($pref['unit_inv_min'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Max Investment</label>
                                        <input type="number" name="unit_inv_max" class="form-control" value="<?= htmlspecialchars($pref['unit_inv_max'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Finance Support Available?</label>
                                        <select name="is_finance_aid" class="form-select">
                                            <option value="0" <?= empty($pref['is_finance_aid']) ? 'selected' : '' ?>>No</option>
                                            <option value="1" <?= !empty($pref['is_finance_aid']) ? 'selected' : '' ?>>Yes</option>
                                        </select>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <h6 class="mb-3 border-top pt-3">Location & Property</h6>
                            <div class="row g-3 mb-4">
                                <?php if ($isInvestor): ?>
                                    <div class="col-md-6">
                                        <label class="form-label">Preferred States</label>
                                        <input type="text" name="looking_business_state" class="form-control" value="<?= htmlspecialchars($pref['looking_business_state'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Preferred Cities</label>
                                        <input type="text" name="looking_business_city" class="form-control" value="<?= htmlspecialchars($pref['looking_business_city'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Own Property?</label>
                                        <select name="own_property" class="form-select">
                                            <option value="0" <?= empty($pref['own_property']) ? 'selected' : '' ?>>No</option>
                                            <option value="1" <?= !empty($pref['own_property']) ? 'selected' : '' ?>>Yes</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Property Type</label>
                                        <input type="text" name="property_type" class="form-control" value="<?= htmlspecialchars($pref['property_type'] ?? '') ?>">
                                    </div>
                                <?php else: ?>
                                    <div class="col-md-6">
                                        <label class="form-label">Expansion States</label>
                                        <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($pref['state'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Preferred Cities</label>
                                        <input type="text" name="preferred_cities" class="form-control" value="<?= htmlspecialchars($pref['preferred_cities'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Required Property Type</label>
                                        <input type="text" name="property_type" class="form-control" value="<?= htmlspecialchars($pref['property_type'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Min Area (sq ft)</label>
                                        <input type="number" name="prop_area_min" class="form-control" value="<?= htmlspecialchars($pref['prop_area_min'] ?? '') ?>">
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mt-4 border-top pt-3 text-end">
                                <button type="submit" class="btn btn-primary"><i class="bx bx-save me-1"></i> Save Match Preferences</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Actions & Timeline -->
    <div class="col-md-5">
        
        <?php if(isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
            <div class="alert alert-success alert-dismissible" role="alert">
                Action saved successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif(isset($_GET['msg']) && $_GET['msg'] === 'invalid_id'): ?>
            <div class="alert alert-danger alert-dismissible" role="alert">
                Error: The provided ID does not exist in the master database.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="nav-align-top mb-4">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-log-call">Log</button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-meeting">Meeting</button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-shortlist">Shortlist</button>
                </li>
                <?php if($isInvestor): ?>
                <li class="nav-item">
                    <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-status">Status</button>
                </li>
                <?php endif; ?>
            </ul>
            <div class="tab-content">
                <!-- Log Call -->
                <div class="tab-pane fade show active" id="navs-log-call" role="tabpanel">
                    <div class="text-center py-4">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#detailedCallModal">
                            <i class="bx bx-phone-call me-1"></i> Log Detailed Call / Activity
                        </button>
                    </div>
                </div>
                <!-- Meeting -->
                <div class="tab-pane fade" id="navs-meeting" role="tabpanel">
                    <form method="POST">
                        <input type="hidden" name="action" value="schedule_meeting">
                        <div class="mb-3">
                            <label class="form-label">Target <?= $isInvestor ? 'Franchisor' : 'Investor' ?> ID</label>
                            <input type="text" name="target_id" class="form-control" required placeholder="e.g. <?= $isInvestor ? 'FRAN123' : 'INV456' ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date & Time</label>
                            <input type="datetime-local" name="meeting_datetime" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Meeting Type</label>
                            <select name="meeting_type" class="form-select">
                                <option value="Online">Online / Zoom</option>
                                <option value="In-Person">In-Person</option>
                                <option value="Phone">Phone</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Meeting Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Schedule</button>
                    </form>
                </div>
                <!-- Shortlist -->
                <div class="tab-pane fade" id="navs-shortlist" role="tabpanel">
                    <form method="POST">
                        <input type="hidden" name="action" value="shortlist">
                        <div class="mb-3">
                            <label class="form-label">Shortlist <?= $isInvestor ? 'Franchisor' : 'Investor' ?> ID</label>
                            <input type="text" name="target_id" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Add to Shortlist</button>
                    </form>
                    <?php if(!empty($shortlists)): ?>
                        <hr>
                        <h6>Current Shortlists</h6>
                        <ul class="list-group list-group-flush">
                            <?php foreach($shortlists as $sl): ?>
                                <li class="list-group-item px-0">
                                    Target ID: <?= htmlspecialchars($isInvestor ? $sl['franchisor_id'] : $sl['investor_id']) ?>
                                    <span class="badge bg-secondary float-end"><?= htmlspecialchars($sl['status']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <!-- Status -->
                <?php if($isInvestor): ?>
                <div class="tab-pane fade" id="navs-status" role="tabpanel">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_status">
                        <div class="mb-3">
                            <label class="form-label">Update Interest Status</label>
                            <select name="status" class="form-select">
                                <option value="Interested" <?= $current_status=='Interested'?'selected':'' ?>>Interested</option>
                                <option value="Not Interested" <?= $current_status=='Not Interested'?'selected':'' ?>>Not Interested</option>
                                <option value="Callback Required" <?= $current_status=='Callback Required'?'selected':'' ?>>Callback Required</option>
                                <option value="Meeting Scheduled" <?= $current_status=='Meeting Scheduled'?'selected':'' ?>>Meeting Scheduled</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Update Status</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-4">
            <h5 class="card-header">Activity Timeline</h5>
            <div class="card-body">
                <div class="timeline-list">
                    <?php if (empty($timeline)): ?>
                        <p class="text-muted">No activity yet.</p>
                    <?php else: ?>
                        <?php foreach($timeline as $item): ?>
                            <div class="mb-3 border-start border-2 border-primary ps-3 pb-2">
                                <div class="d-flex justify-content-between">
                                    <strong class="text-dark">
                                        <?= htmlspecialchars($item['title']) ?> 
                                        <?php if($item['priority']): ?>
                                            <span class="badge bg-label-<?= $item['priority']=='High'?'danger':($item['priority']=='Medium'?'warning':'info') ?> ms-2"><?= htmlspecialchars($item['priority']) ?></span>
                                        <?php endif; ?>
                                        <small class="text-muted fw-normal d-block">by <?= htmlspecialchars($item['username']) ?></small>
                                    </strong>
                                    <small class="text-muted text-end"><?= htmlspecialchars(date('M d, H:i', strtotime($item['created_at']))) ?></small>
                                </div>
                                
                                <?php if($item['cat_name'] || $item['location'] || $item['investment_range']): ?>
                                    <div class="my-2 p-2 bg-lighter rounded" style="font-size: 0.85rem;">
                                        <?php if($item['cat_name']): ?>
                                            <div><strong>Category:</strong> <?= htmlspecialchars($item['cat_name']) ?> <?= $item['sub_cat_name'] ? ' &rsaquo; '.htmlspecialchars($item['sub_cat_name']) : '' ?></div>
                                        <?php endif; ?>
                                        <?php if($item['location']): ?>
                                            <div><strong>Location:</strong> <?= htmlspecialchars($item['location']) ?></div>
                                        <?php endif; ?>
                                        <?php if($item['investment_range']): ?>
                                            <div><strong>Investment:</strong> <?= htmlspecialchars($item['investment_range']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="mt-1">
                                    <?= nl2br(htmlspecialchars($item['notes'])) ?>
                                </div>
                                <?php if(!empty($item['follow_up_date'])): ?>
                                    <div class="mt-2">
                                        <span class="badge bg-label-warning"><i class="bx bx-calendar me-1"></i> Follow-up: <?= htmlspecialchars($item['follow_up_date']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Call Log Modal -->
<div class="modal fade" id="detailedCallModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form method="POST">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel4">Log Detailed Call / Activity</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
          <input type="hidden" name="action" value="log_call">
          
          <div class="row g-2 mb-3">
            <div class="col-md-6">
                <label class="form-label">Activity Type</label>
                <select name="activity_type" class="form-select">
                    <option value="Call">Call</option>
                    <option value="Email">Email</option>
                    <option value="Follow-up">Follow-up</option>
                    <option value="Note">General Note</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Priority</label>
                <select name="priority" class="form-select">
                    <option value="">Select Priority...</option>
                    <option value="High">High</option>
                    <option value="Medium">Medium</option>
                    <option value="Low">Low</option>
                </select>
            </div>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-md-4">
                <label class="form-label">Category Interested</label>
                <select name="category_id" id="main_cat" class="form-select">
                    <option value="">Select Main Category...</option>
                    <?php foreach($catMapping['SeoCategoryArr'] as $cid => $cname): ?>
                        <option value="<?= $cid ?>"><?= ucwords(str_replace('-', ' ', $cname)) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="cat_name" id="cat_name_hidden">
            </div>
            <div class="col-md-4">
                <label class="form-label">Sub Category</label>
                <select name="sub_category_id" id="sub_cat" class="form-select" disabled>
                    <option value="">Select Sub Category...</option>
                </select>
                <input type="hidden" name="sub_cat_name" id="sub_cat_name_hidden">
            </div>
            <div class="col-md-4">
                <label class="form-label">Sub Sub Category</label>
                <select name="sub_sub_category_id" id="sub_sub_cat" class="form-select" disabled>
                    <option value="">Select Sub Sub Category...</option>
                </select>
                <input type="hidden" name="sub_sub_cat_name" id="sub_sub_cat_name_hidden">
            </div>
          </div>

          <div class="row g-2 mb-3">
              <div class="col-md-6">
                  <label class="form-label">Location (City/State)</label>
                  <input type="text" name="location" class="form-control" placeholder="e.g. Delhi NCR" maxlength="100">
              </div>
              <div class="col-md-6">
                  <label class="form-label">Investment Range</label>
                  <select name="investment_range" class="form-select">
                      <option value="">Select Range...</option>
                      <option value="1L - 5L">1L - 5L</option>
                      <option value="5L - 10L">5L - 10L</option>
                      <option value="10L - 20L">10L - 20L</option>
                      <option value="20L - 50L">20L - 50L</option>
                      <option value="50L - 1Cr">50L - 1Cr</option>
                      <option value="1Cr+">1Cr+</option>
                  </select>
              </div>
          </div>

          <div class="mb-3">
              <label class="form-label">Comments / Notes</label>
              <textarea name="notes" class="form-control" rows="3" required placeholder="Detailed discussion points..." maxlength="1000"></textarea>
          </div>
          
          <div class="mb-3">
              <label class="form-label">Follow-up On</label>
              <input type="date" name="follow_up_date" class="form-control" min="<?= date('Y-m-d') ?>">
          </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" class="btn btn-primary">Save Activity Log</button>
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
    // Populate Main Categories initially
    fetch(CAT_API_URL + '?parent_id=0')
        .then(response => response.json())
        .then(data => {
            const mainCat = document.getElementById('main_cat');
            if (Array.isArray(data)) {
                data.forEach(cat => {
                    let opt = document.createElement('option');
                    opt.value = cat.catid;
                    opt.textContent = cat.catname;
                    mainCat.appendChild(opt);
                });
            }
        })
        .catch(err => console.error("Error loading main categories:", err));

    // Handle Main Category Change
    document.getElementById('main_cat').addEventListener('change', function() {
        const subCat = document.getElementById('sub_cat');
        const subSubCat = document.getElementById('sub_sub_cat');
        
        // Update hidden name
        document.getElementById('cat_name_hidden').value = this.options[this.selectedIndex].text;
        
        subCat.innerHTML = '<option value="">Select Sub Category...</option>';
        subSubCat.innerHTML = '<option value="">Select Sub Sub Category...</option>';
        subCat.disabled = true;
        subSubCat.disabled = true;

        if (this.value) {
            fetch(CAT_API_URL + '?parent_id=' + this.value)
                .then(response => response.json())
                .then(data => {
                    if (Array.isArray(data) && data.length > 0) {
                        subCat.disabled = false;
                        data.forEach(cat => {
                            let opt = document.createElement('option');
                            opt.value = cat.catid;
                            opt.textContent = cat.catname;
                            subCat.appendChild(opt);
                        });
                    }
                })
                .catch(err => console.error("Fetch error for sub categories:", err));
        }
    });

    // Handle Sub Category Change
    document.getElementById('sub_cat').addEventListener('change', function() {
        const subSubCat = document.getElementById('sub_sub_cat');
        
        // Update hidden name
        document.getElementById('sub_cat_name_hidden').value = this.options[this.selectedIndex].text;
        
        subSubCat.innerHTML = '<option value="">Select Sub Sub Category...</option>';
        subSubCat.disabled = true;

        if (this.value) {
            fetch(CAT_API_URL + '?parent_id=' + this.value)
                .then(response => response.json())
                .then(data => {
                    if (Array.isArray(data) && data.length > 0) {
                        subSubCat.disabled = false;
                        data.forEach(cat => {
                            let opt = document.createElement('option');
                            opt.value = cat.catid;
                            opt.textContent = cat.catname;
                            subSubCat.appendChild(opt);
                        });
                    }
                })
                .catch(err => console.error("Fetch error for sub-sub categories:", err));
        }
    });

    // Handle Sub Sub Category Change
    document.getElementById('sub_sub_cat').addEventListener('change', function() {
        // Update hidden name
        document.getElementById('sub_sub_cat_name_hidden').value = this.options[this.selectedIndex].text;
    });

    // Real-time ID Validation
    const targetIdInputs = document.querySelectorAll('input[name="target_id"]');
    targetIdInputs.forEach(input => {
        const feedback = document.createElement('div');
        feedback.className = 'form-text mt-1';
        input.parentNode.appendChild(feedback);

        input.addEventListener('blur', function() {
            const val = this.value.trim();
            if (!val) {
                feedback.innerHTML = '';
                return;
            }

            feedback.innerHTML = '<i class="bx bx-loader-alt bx-spin me-1"></i> Checking ID...';
            const targetType = '<?= $isInvestor ? "franchisor" : "investor" ?>';
            const endpoint = (targetType === 'investor') ? '/api_investors.php' : '/api.php';
            
            fetch(API_BASE_URL + endpoint + '?action=validate&type=' + targetType + '&id=' + val)
                .then(response => response.json())
                .then(data => {
                    if (data.exists) {
                        const name = data.data.brand_name || data.data.first_name || data.data.service_company_name || 'Valid Record';
                        feedback.innerHTML = '<span class="text-success"><i class="bx bx-check-circle me-1"></i> Valid: ' + name + '</span>';
                        input.classList.remove('is-invalid');
                        input.classList.add('is-valid');
                    } else {
                        feedback.innerHTML = '<span class="text-danger"><i class="bx bx-x-circle me-1"></i> Invalid: This ID was not found in ' + targetType + ' source.</span>';
                        input.classList.remove('is-valid');
                        input.classList.add('is-invalid');
                    }
                })
                .catch(err => {
                    feedback.innerHTML = '<span class="text-warning">Validation unavailable.</span>';
                });
        });
    });

    const chartData = <?= json_encode($timelineStats) ?>;
    const chartLabels = <?= json_encode($labels) ?>;
    
    if (typeof ApexCharts !== 'undefined') {
        const options = {
            series: [{
                name: 'Activities',
                data: chartData
            }],
            chart: {
                height: 250,
                type: 'area',
                parentHeightOffset: 0,
                toolbar: { show: false }
            },
            dataLabels: { enabled: false },
            stroke: {
                curve: 'smooth',
                width: 2
            },
            colors: ['#696cff'], // Sneat Primary Color
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.4,
                    opacityTo: 0.05,
                    stops: [0, 90, 100]
                }
            },
            xaxis: {
                categories: chartLabels,
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            grid: {
                strokeDashArray: 3,
                padding: { top: 0, bottom: -8, left: -10, right: -10 }
            }
        };

        const chart = new ApexCharts(document.querySelector("#activityChart"), options);
        chart.render();
    }
});
</script>
