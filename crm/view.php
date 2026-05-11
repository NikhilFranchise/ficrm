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
$masterCats = $catMapping['SeoCategoryArr'] ?? [];

if ($isInvestor) {
    $apiRes = fetchSingleFromApi('investor', $id);
    $record = $apiRes['data'] ?? null;
    
    // Fetch local extended details
    $stmt = $pdoCrm->prepare("SELECT * FROM crm_investor_details WHERE investor_id = ?");
    $stmt->execute([$id]);
    $localDetails = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    if ($record) {
        $record = array_merge($record, $localDetails);
    }
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
                        <div class="d-flex align-items-center">
                            <h5 class="mb-0 text-capitalize me-3"><?= htmlspecialchars($type) ?> Profile</h5>
                            <?php if ($isInvestor): ?>
                                <button class="btn btn-sm btn-outline-warning btn-edit-investor" 
                                        data-id="<?= $record['id'] ?>"
                                        data-email="<?= htmlspecialchars($record['email']) ?>"
                                        data-min="<?= $record['min_investment'] ?>"
                                        data-max="<?= $record['max_investment'] ?>"
                                        data-cat="<?= htmlspecialchars($record['category_interested'] ?? '') ?>">
                                    <i class="bx bx-edit-alt"></i> Edit
                                </button>
                            <?php endif; ?>
                        </div>
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

        <?php if($isInvestor && !empty($localDetails)): ?>
        <!-- Extended Local Details Section -->
        <div class="card mt-4 shadow-none border">
            <div class="card-header d-flex justify-content-between align-items-center bg-lighter">
                <h5 class="mb-0 text-primary fw-bold"><i class="bx bx-stats me-1"></i> Financial & Property Profile</h5>
                <span class="badge bg-label-primary">Extended Data</span>
            </div>
            <div class="card-body pt-4">
                <div class="row g-4">
                    <!-- Professional & Education -->
                    <div class="col-md-6 border-end">
                        <h6 class="text-muted text-uppercase small fw-bold mb-3">Professional Background</h6>
                        <div class="d-flex flex-column gap-2">
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Education:</span>
                                <span class="fw-medium text-dark"><?= htmlspecialchars($record['qualification'] ?? 'Not Specified') ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Occupation:</span>
                                <span class="fw-medium text-dark"><?= htmlspecialchars($record['occupation'] ?? 'Not Specified') ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Monthly Income:</span>
                                <span class="fw-medium text-dark"><?= htmlspecialchars($record['income_range'] ?? 'Not Specified') ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Financial Info -->
                    <div class="col-md-6 ps-md-4">
                        <h6 class="text-muted text-uppercase small fw-bold mb-3">Financial Profile</h6>
                        <div class="d-flex flex-column gap-2">
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Available Capital:</span>
                                <span class="fw-bold text-success"><?= htmlspecialchars($record['available_capital'] ?? 'Not Specified') ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Investment Range:</span>
                                <span class="fw-medium text-dark">₹<?= number_format((float)($record['min_investment']??0)) ?> - ₹<?= number_format((float)($record['max_investment']??0)) ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Loan Interest:</span>
                                <span><?= ($record['loan_interest']??0) ? '<span class="badge bg-label-success">Yes</span>' : '<span class="badge bg-label-secondary">No</span>' ?></span>
                            </div>
                            <?php if($record['loan_interest']??0): ?>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Loan Range:</span>
                                    <span class="fw-medium text-dark"><?= htmlspecialchars($record['loan_range'] ?? '') ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-12"><hr class="my-0"></div>

                    <!-- Property Details -->
                    <div class="col-md-12">
                        <h6 class="text-muted text-uppercase small fw-bold mb-3">Property & Assets</h6>
                        <div class="row text-center">
                            <div class="col-md-4">
                                <div class="p-2 border rounded bg-light">
                                    <small class="text-muted d-block">Owns Property?</small>
                                    <span class="fw-bold h6 mb-0"><?= ($record['is_property_own']??0) ? 'Yes' : 'No' ?></span>
                                </div>
                            </div>
                            <?php if($record['is_property_own']??0): ?>
                            <div class="col-md-4">
                                <div class="p-2 border rounded bg-light">
                                    <small class="text-muted d-block">Property Type</small>
                                    <span class="fw-bold h6 mb-0"><?= htmlspecialchars($record['property_type_mortgage'] ?? 'N/A') ?></span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-2 border rounded bg-light">
                                    <small class="text-muted d-block">Area / Value</small>
                                    <span class="fw-bold h6 mb-0"><?= htmlspecialchars($record['property_size_mortgage'] ?? '') ?> / <?= htmlspecialchars($record['property_value_mortgage'] ?? '') ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Preferences -->
                    <div class="col-12"><hr class="my-0"></div>
                    <div class="col-md-12">
                        <h6 class="text-muted text-uppercase small fw-bold mb-3">Investment Preferences</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="d-flex align-items-center">
                                    <i class="bx bx-map-pin me-2 text-primary"></i>
                                    <div>
                                        <small class="text-muted d-block">Target Location</small>
                                        <span class="fw-medium"><?= htmlspecialchars($record['business_city_looking'] ?? 'Any') ?>, <?= htmlspecialchars($record['business_state_looking'] ?? 'Any') ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center">
                                    <i class="bx bx-time-five me-2 text-primary"></i>
                                    <div>
                                        <small class="text-muted d-block">Timeframe</small>
                                        <span class="fw-medium"><?= htmlspecialchars($record['investment_date'] ?? 'Flexible') ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center">
                                    <i class="bx bx-info-circle me-2 text-primary"></i>
                                    <div>
                                        <small class="text-muted d-block">Loan Purpose</small>
                                        <span class="small text-muted"><?= htmlspecialchars($record['loan_purpose'] ?? 'N/A') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
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
                                    <?php 
                                    $notes = htmlspecialchars($item['notes']);
                                    if(strlen($notes) > 150): 
                                        echo nl2br(substr($notes, 0, 150)) . '...';
                                    ?>
                                        <a href="javascript:void(0);" class="text-primary d-block mt-1" 
                                           onclick='showFullNote(<?= json_encode($item['notes']) ?>)'>View Full Note</a>
                                    <?php else: ?>
                                        <?= nl2br($notes) ?>
                                    <?php endif; ?>
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
function showFullNote(content) {
    document.getElementById('fullNoteContent').innerText = content;
    new bootstrap.Modal(document.getElementById('fullNoteModal')).show();
}

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

        var chart = new ApexCharts(document.querySelector("#activityChart"), options);
        chart.render();
    }
});
</script>

<?php if ($isInvestor): ?>
<!-- Edit Investor Modal -->
<div class="modal fade" id="editInvestorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary">
        <h5 class="modal-title text-white">Comprehensive Investor Edit</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="editInvestorForm">
        <div class="modal-body">
            <input type="hidden" name="id" id="edit_id">
            
            <!-- Section 1: Personal Details -->
            <div class="divider text-start mb-4">
                <div class="divider-text fw-bold text-primary"><i class="bx bx-user me-1"></i> Personal Details</div>
            </div>
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Title</label>
                    <select name="title" id="edit_title" class="form-select">
                        <option value="1">Mr.</option>
                        <option value="2">Mrs.</option>
                        <option value="3">Ms.</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">First Name</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" id="edit_last_name" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="edit_email" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Pincode</label>
                    <input type="text" name="pincode" id="edit_pincode" class="form-control">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Full Address</label>
                    <textarea name="address" id="edit_address" class="form-control" rows="2"></textarea>
                </div>
            </div>

            <!-- Section 2: Professional & Financial -->
            <div class="divider text-start my-4">
                <div class="divider-text fw-bold text-primary"><i class="bx bx-briefcase me-1"></i> Professional & Financial</div>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Education Qualification</label>
                    <select name="qualification" id="edit_qualification" class="form-select">
                        <option value="">Select Qualification</option>
                        <option value="Graduate">Graduate</option>
                        <option value="Post Graduate">Post Graduate</option>
                        <option value="Undergraduate">Undergraduate</option>
                        <option value="Professional">Professional</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Occupation</label>
                    <select name="occupation" id="edit_occupation" class="form-select">
                        <option value="">Select Occupation</option>
                        <option value="Business">Business</option>
                        <option value="Service">Service</option>
                        <option value="Professional">Professional</option>
                        <option value="Self Employed">Self Employed</option>
                        <option value="Retired">Retired</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Min Investment (₹)</label>
                    <input type="number" name="min_investment" id="edit_min" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Max Investment (₹)</label>
                    <input type="number" name="max_investment" id="edit_max" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Available Capital</label>
                    <input type="text" name="available_capital" id="edit_available_capital" class="form-control">
                </div>
            </div>

            <!-- Section 3: Loan & Property -->
            <div class="divider text-start my-4">
                <div class="divider-text fw-bold text-primary"><i class="bx bx-home me-1"></i> Loan & Property Details</div>
            </div>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Need for loan?</label>
                    <select name="loan_interest" id="edit_loan_interest" class="form-select">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Loan Range</label>
                    <input type="text" name="loan_range" id="edit_loan_range" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Current Monthly Income</label>
                    <input type="text" name="income_range" id="edit_income_range" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Own Property?</label>
                    <select name="is_property_own" id="edit_is_property_own" class="form-select">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Mortgage Property Type</label>
                    <input type="text" name="property_type_mortgage" id="edit_property_type" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Property Size (Sq.ft)</label>
                    <input type="text" name="property_size_mortgage" id="edit_property_size" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Property Value (₹)</label>
                    <input type="text" name="property_value_mortgage" id="edit_property_value" class="form-control">
                </div>
            </div>

            <!-- Section 4: Preferences -->
            <div class="divider text-start my-4">
                <div class="divider-text fw-bold text-primary"><i class="bx bx-target-lock me-1"></i> Investment Preferences</div>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Category Interested In</label>
                    <select name="category_interested" id="edit_cat" class="form-select">
                        <option value="">Select Category</option>
                        <?php foreach($masterCats as $cname): ?>
                            <option value="<?= htmlspecialchars($cname) ?>"><?= ucwords(str_replace('-', ' ', $cname)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Investment Timeframe</label>
                    <input type="text" name="investment_date" id="edit_investment_date" class="form-control" placeholder="e.g. Within 3 months">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Looking for Business in (State)</label>
                    <input type="text" name="business_state_looking" id="edit_state_looking" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Looking for Business in (City)</label>
                    <input type="text" name="business_city_looking" id="edit_city_looking" class="form-control">
                </div>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary btn-lg px-5">Update Profile</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editBtn = document.querySelector('.btn-edit-investor');
    if (!editBtn) return;
    
    const editModal = new bootstrap.Modal(document.getElementById('editInvestorModal'));
    const editForm = document.getElementById('editInvestorForm');

    editBtn.addEventListener('click', function() {
        const data = <?= json_encode($record) ?>;
        if(!data) return;
        
        document.getElementById('edit_id').value = data.id || '';
        document.getElementById('edit_title').value = data.title || '';
        document.getElementById('edit_name').value = data.name || '';
        document.getElementById('edit_last_name').value = data.last_name || '';
        document.getElementById('edit_email').value = data.email || '';
        document.getElementById('edit_pincode').value = data.pincode || '';
        document.getElementById('edit_address').value = data.address || '';
        document.getElementById('edit_qualification').value = data.qualification || '';
        document.getElementById('edit_occupation').value = data.occupation || '';
        document.getElementById('edit_min').value = data.min_investment || '';
        document.getElementById('edit_max').value = data.max_investment || '';
        document.getElementById('edit_available_capital').value = data.available_capital || '';
        document.getElementById('edit_loan_interest').value = data.loan_interest || 0;
        document.getElementById('edit_loan_range').value = data.loan_range || '';
        document.getElementById('edit_income_range').value = data.income_range || '';
        document.getElementById('edit_is_property_own').value = data.is_property_own || 0;
        document.getElementById('edit_property_type').value = data.property_type_mortgage || '';
        document.getElementById('edit_property_size').value = data.property_size_mortgage || '';
        document.getElementById('edit_property_value').value = data.property_value_mortgage || '';
        document.getElementById('edit_cat').value = data.category_interested || '';
        document.getElementById('edit_investment_date').value = data.investment_date || '';
        document.getElementById('edit_state_looking').value = data.business_state_looking || '';
        document.getElementById('edit_city_looking').value = data.business_city_looking || '';
        
        editModal.show();
    });

    editForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('updated_by', '<?= $_SESSION['username'] ?? 'Unknown' ?>');
        const apiBase = "<?= API_BASE_URL ?>";
        
        // 1. Local Backup & Local Details Update FIRST
        fetch('api_export/ajax_investor_local_update.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status !== 'success') {
                throw new Exception("Local backup failed: " + data.message);
            }
            // 2. Remote Master Update SECOND
            return fetch(apiBase + "/api_investor_update.php", {
                method: 'POST',
                body: formData
            });
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                alert("Master update error: " + data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert("Process failed. Please check your connection.");
        });
    });
});
</script>
<?php endif; ?>

<?php require_once 'footer.php'; ?>
