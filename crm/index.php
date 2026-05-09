<?php
require_once 'header.php';
require_once 'db.php';

// RBAC
$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'salesman';

$ufSalesman = ($role === 'salesman') ? " WHERE salesman_id = $userId" : "";
$ufWhere = ($role === 'salesman') ? " WHERE a.user_id = $userId" : "";
$ufAnd = ($role === 'salesman') ? " AND user_id = $userId" : "";
$ufSalesmanAnd = ($role === 'salesman') ? " AND salesman_id = $userId" : "";
$ufWhereAnd = ($role === 'salesman') ? " AND a.user_id = $userId" : "";

// --- STATS COMPILATION --- //
// Match Preferences
$totInvPref = $pdoCrm->query("SELECT COUNT(*) FROM investor_preferences")->fetchColumn();
$totFranReq = $pdoCrm->query("SELECT COUNT(*) FROM franchisor_requirements")->fetchColumn();

// 1. Matches (Shortlists)
$totMatches = $pdoCrm->query("SELECT COUNT(*) FROM shortlists" . ($role==='salesman'?" WHERE salesman_id=$userId":""))->fetchColumn();
$monthMatches = $pdoCrm->query("SELECT COUNT(*) FROM shortlists WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) $ufSalesmanAnd")->fetchColumn();
$todayMatches = $pdoCrm->query("SELECT COUNT(*) FROM shortlists WHERE DATE(created_at) = CURRENT_DATE() $ufSalesmanAnd")->fetchColumn();

// 2. Meetings
$totMeetings = $pdoCrm->query("SELECT COUNT(*) FROM meetings" . ($role==='salesman'?" WHERE salesman_id=$userId":""))->fetchColumn();
$monthMeetings = $pdoCrm->query("SELECT COUNT(*) FROM meetings WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) $ufSalesmanAnd")->fetchColumn();
$todayMeetings = $pdoCrm->query("SELECT COUNT(*) FROM meetings WHERE DATE(created_at) = CURRENT_DATE() $ufSalesmanAnd")->fetchColumn();

// 3. Calls & Logs
$totCalls = $pdoCrm->query("SELECT COUNT(*) FROM activity_logs WHERE activity_type='Call' $ufAnd")->fetchColumn();
$monthCalls = $pdoCrm->query("SELECT COUNT(*) FROM activity_logs WHERE activity_type='Call' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) $ufAnd")->fetchColumn();
$todayCalls = $pdoCrm->query("SELECT COUNT(*) FROM activity_logs WHERE activity_type='Call' AND DATE(created_at) = CURRENT_DATE() $ufAnd")->fetchColumn();

// Recent Activities - Join with crm_franchisors for brand names
$recentActivities = $pdoCrm->query("
    SELECT a.activity_type as title, a.notes, a.created_at, u.username, a.entity_type, a.entity_id,
           f.brand_name
    FROM activity_logs a 
    JOIN users u ON a.user_id = u.id 
    LEFT JOIN crm_franchisors f ON (a.entity_type = 'franchisor' AND (a.entity_id = f.id OR a.entity_id = f.franchisor_id))
    $ufWhere
    ORDER BY a.created_at DESC LIMIT 6
")->fetchAll();

// --- CHART DATA (Last 7 Days) --- //
$dates = [];
$labels = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $dates[] = $d;
    $labels[] = date('M d', strtotime("-$i days"));
}

$chartCalls = [];
$chartMeetings = [];
$chartMatches = [];

foreach($dates as $d) {
    $chartCalls[] = (int)$pdoCrm->query("SELECT COUNT(*) FROM activity_logs WHERE activity_type='Call' AND DATE(created_at) = '$d' $ufAnd")->fetchColumn();
    $chartMeetings[] = (int)$pdoCrm->query("SELECT COUNT(*) FROM meetings WHERE DATE(created_at) = '$d' $ufSalesmanAnd")->fetchColumn();
    $chartMatches[] = (int)$pdoCrm->query("SELECT COUNT(*) FROM shortlists WHERE DATE(created_at) = '$d' $ufSalesmanAnd")->fetchColumn();
}

// Meeting Types Bar Chart
$mTypesQuery = $pdoCrm->query("SELECT meeting_type, COUNT(*) as c FROM meetings " . ($role==='salesman'?" WHERE salesman_id=$userId":"") . " GROUP BY meeting_type")->fetchAll();
$mTypes = [];
$mCounts = [];
foreach($mTypesQuery as $mt) {
    $mTypes[] = $mt['meeting_type'];
    $mCounts[] = (int)$mt['c'];
}

// Activity Pie Chart
$actFran = (int)$pdoCrm->query("SELECT COUNT(*) FROM activity_logs WHERE entity_type='franchisor' $ufAnd")->fetchColumn();
$actInv = (int)$pdoCrm->query("SELECT COUNT(*) FROM activity_logs WHERE entity_type='investor' $ufAnd")->fetchColumn();
?>

<!-- Welcome Section -->
<div class="row">
    <div class="col-lg-12 mb-4">
        <div class="card bg-primary text-white">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="text-white mb-2">Welcome to FranMatch CRM, <?= htmlspecialchars(ucfirst($_SESSION['username'])) ?>! 🚀</h4>
                    <p class="mb-0 text-white-50">Here is your daily pipeline snapshot and matchmaking overview.</p>
                </div>
                <div>
                    <span class="badge bg-white text-primary fs-6"><?= date('l, F jS Y') ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Key Performance Indicators (KPIs) -->
<div class="row mb-4">
    <!-- Calls -->
    <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
        <div class="card card-border-shadow-primary h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 text-muted">Calls Logged</h6>
                    <span class="badge bg-label-primary rounded-pill p-2"><i class="bx bx-phone-call fs-4"></i></span>
                </div>
                <div class="mt-3">
                    <h3 class="mb-1"><?= $totCalls ?></h3>
                    <div class="d-flex justify-content-between text-muted small mt-2 border-top pt-2">
                        <span>Today: <strong class="text-primary"><?= $todayCalls ?></strong></span>
                        <span>This Month: <strong class="text-primary"><?= $monthCalls ?></strong></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Meetings -->
    <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
        <div class="card card-border-shadow-success h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 text-muted">Meetings Scheduled</h6>
                    <span class="badge bg-label-success rounded-pill p-2"><i class="bx bx-calendar-event fs-4"></i></span>
                </div>
                <div class="mt-3">
                    <h3 class="mb-1"><?= $totMeetings ?></h3>
                    <div class="d-flex justify-content-between text-muted small mt-2 border-top pt-2">
                        <span>Today: <strong class="text-success"><?= $todayMeetings ?></strong></span>
                        <span>This Month: <strong class="text-success"><?= $monthMeetings ?></strong></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Matchmaking Pairs -->
    <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
        <div class="card card-border-shadow-info h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 text-muted">Matches (Shortlists)</h6>
                    <span class="badge bg-label-info rounded-pill p-2"><i class="bx bx-link-alt fs-4"></i></span>
                </div>
                <div class="mt-3">
                    <h3 class="mb-1"><?= $totMatches ?></h3>
                    <div class="d-flex justify-content-between text-muted small mt-2 border-top pt-2">
                        <span>Today: <strong class="text-info"><?= $todayMatches ?></strong></span>
                        <span>This Month: <strong class="text-info"><?= $monthMatches ?></strong></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pref Profiles -->
    <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
        <div class="card card-border-shadow-warning h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 text-muted">Match Preferences</h6>
                    <span class="badge bg-label-warning rounded-pill p-2"><i class="bx bx-target-lock fs-4"></i></span>
                </div>
                <div class="mt-3">
                    <h3 class="mb-1"><?= $totInvPref + $totFranReq ?></h3>
                    <div class="d-flex justify-content-between text-muted small mt-2 border-top pt-2">
                        <span>Investors: <strong class="text-warning"><?= $totInvPref ?></strong></span>
                        <span>Franchisors: <strong class="text-warning"><?= $totFranReq ?></strong></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Charts Area -->
<div class="row">
    <!-- Activity Timeline Chart -->
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Pipeline Velocity (Last 7 Days)</h5>
            </div>
            <div class="card-body">
                <div id="velocityChart" style="min-height: 300px;"></div>
            </div>
        </div>
    </div>

    <!-- Meeting Breakdown Chart -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Meetings by Type</h5>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <div id="meetingTypeChart" style="width: 100%;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Row: Logs & Activity Breakdown -->
<div class="row">
    <!-- Lead Activity Distribution -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Activity Distribution</h5>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <div id="activityDistChart" style="width: 100%;"></div>
            </div>
        </div>
    </div>

    <!-- Recent Global Activities List -->
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title m-0">Recent CRM Activities</h5>
                <a href="logs.php" class="btn btn-sm btn-outline-primary">View All Logs</a>
            </div>
            <div class="card-body">
                <ul class="p-0 m-0">
                    <?php if(empty($recentActivities)): ?>
                        <p class="text-muted">No activities found.</p>
                    <?php else: ?>
                        <?php foreach($recentActivities as $act): ?>
                        <li class="d-flex mb-3 pb-2 border-bottom">
                            <div class="avatar flex-shrink-0 me-3">
                                <?php if($act['entity_type'] === 'investor'): ?>
                                    <span class="avatar-initial rounded bg-label-success"><i class="bx bx-user"></i></span>
                                <?php else: ?>
                                    <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-store-alt"></i></span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                <div class="me-2">
                                    <small class="text-muted d-block mb-1 text-uppercase fw-bold">
                                        <?= htmlspecialchars($act['entity_type']) ?> &middot; <?= htmlspecialchars($act['title']) ?>
                                    </small>
                                    <h6 class="mb-0 text-truncate" style="max-width: 350px;">
                                        <a href="view.php?type=<?= $act['entity_type'] ?>&id=<?= $act['entity_id'] ?>" class="text-dark">
                                            <?= htmlspecialchars($act['brand_name'] ?: ($act['entity_type'] == 'investor' ? 'Lead #'.$act['entity_id'] : $act['entity_id'])) ?>:
                                        </a>
                                        <span class="fw-normal text-muted"><?= htmlspecialchars($act['notes']) ?></span>
                                    </h6>
                                </div>
                                <div class="text-end">
                                    <small class="d-block text-muted"><?= date('M d, H:i', strtotime($act['created_at'])) ?></small>
                                    <small class="fw-bold text-primary">By <?= htmlspecialchars($act['username']) ?></small>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>

<!-- Initializing ApexCharts -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // 1. Pipeline Velocity Line Chart
    const velocityOptions = {
        series: [
            { name: 'Calls Logged', data: <?= json_encode($chartCalls) ?> },
            { name: 'Meetings Aligned', data: <?= json_encode($chartMeetings) ?> },
            { name: 'Matches Found', data: <?= json_encode($chartMatches) ?> }
        ],
        chart: {
            height: 300,
            type: 'area',
            toolbar: { show: false }
        },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3 },
        colors: ['#696cff', '#71dd37', '#03c3ec'],
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
            categories: <?= $labels ? json_encode($labels) : '[]' ?>,
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        legend: {
            position: 'top',
            horizontalAlign: 'left'
        }
    };
    new ApexCharts(document.querySelector("#velocityChart"), velocityOptions).render();

    // 2. Meeting Types Donut Chart
    const mTypes = <?= json_encode($mTypes) ?>;
    const mCounts = <?= json_encode($mCounts) ?>;
    
    if (mCounts.length > 0) {
        const meetingOptions = {
            series: mCounts,
            labels: mTypes,
            chart: {
                type: 'donut',
                height: 250
            },
            colors: ['#696cff', '#03c3ec', '#ffab00', '#ff3e1d'],
            plotOptions: {
                pie: {
                    donut: {
                        size: '70%',
                        labels: {
                            show: true,
                            name: { show: true },
                            value: { show: true },
                            total: {
                                show: true,
                                label: 'Meetings',
                                color: '#566a7f',
                                formatter: function (w) {
                                    return <?= $totMeetings ?>
                                }
                            }
                        }
                    }
                }
            },
            dataLabels: { enabled: false },
            legend: { position: 'bottom' }
        };
        new ApexCharts(document.querySelector("#meetingTypeChart"), meetingOptions).render();
    } else {
        document.querySelector("#meetingTypeChart").innerHTML = '<p class="text-center text-muted">No meetings yet.</p>';
    }

    // 3. Activity Distribution Pie Chart
    const actFran = <?= $actFran ?>;
    const actInv = <?= $actInv ?>;
    
    if (actFran > 0 || actInv > 0) {
        const distOptions = {
            series: [actFran, actInv],
            labels: ['Franchisor Logs', 'Investor Logs'],
            chart: {
                type: 'pie',
                height: 250
            },
            colors: ['#696cff', '#71dd37'],
            dataLabels: {
                enabled: true,
                formatter: function (val) {
                    return Math.round(val) + "%"
                }
            },
            legend: { position: 'bottom' }
        };
        new ApexCharts(document.querySelector("#activityDistChart"), distOptions).render();
    } else {
        document.querySelector("#activityDistChart").innerHTML = '<p class="text-center text-muted">No activity yet.</p>';
    }
});
</script>
