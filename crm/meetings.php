<?php
require_once 'header.php';
require_once 'db.php';

// Fetch Filters
$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'salesman';

$salesman_id = $_GET['salesman_id'] ?? '';
if ($role === 'salesman') {
    $salesman_id = $userId; // Force filter to only their own meetings
}

$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where = "1=1";
$params = [];

if ($salesman_id !== '') {
    $where .= " AND m.salesman_id = :salesman_id";
    $params[':salesman_id'] = $salesman_id;
}
if ($date_from !== '') {
    $where .= " AND DATE(m.meeting_datetime) >= :date_from";
    $params[':date_from'] = $date_from;
}
if ($date_to !== '') {
    $where .= " AND DATE(m.meeting_datetime) <= :date_to";
    $params[':date_to'] = $date_to;
}

// Fetch all meetings
$query = "
    SELECT m.*, u.username as salesman_name,
           (SELECT COUNT(*) FROM activity_logs a WHERE a.activity_type = 'Meeting Scheduled' AND a.created_at = m.created_at) as has_log
    FROM meetings m
    LEFT JOIN users u ON m.salesman_id = u.id
    WHERE $where
    ORDER BY m.meeting_datetime DESC
";
$stmt = $pdoCrm->prepare($query);
$stmt->execute($params);
$meetings = $stmt->fetchAll();

// Dashboard Stats
$totalMeetings = count($meetings);
$upcomingMeetings = 0;
$pastMeetings = 0;
$onlineMeetings = 0;

foreach ($meetings as $m) {
    if (strtotime($m['meeting_datetime']) > time()) {
        $upcomingMeetings++;
    } else {
        $pastMeetings++;
    }
    if ($m['meeting_type'] === 'Online') {
        $onlineMeetings++;
    }
}

// Fetch all salesmen for filter
$salesmen = $pdoCrm->query("SELECT id, username FROM users")->fetchAll();
?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-sm-6 col-lg-3 mb-4 mb-lg-0">
        <div class="card card-border-shadow-primary h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2 pb-1">
                    <div class="avatar me-2">
                        <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-calendar"></i></span>
                    </div>
                    <h4 class="ms-1 mb-0"><?= $totalMeetings ?></h4>
                </div>
                <p class="mb-1 fw-medium">Total Meetings</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3 mb-4 mb-lg-0">
        <div class="card card-border-shadow-warning h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2 pb-1">
                    <div class="avatar me-2">
                        <span class="avatar-initial rounded bg-label-warning"><i class="bx bx-time-five"></i></span>
                    </div>
                    <h4 class="ms-1 mb-0"><?= $upcomingMeetings ?></h4>
                </div>
                <p class="mb-1 fw-medium">Upcoming</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3 mb-4 mb-sm-0">
        <div class="card card-border-shadow-success h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2 pb-1">
                    <div class="avatar me-2">
                        <span class="avatar-initial rounded bg-label-success"><i class="bx bx-check-circle"></i></span>
                    </div>
                    <h4 class="ms-1 mb-0"><?= $pastMeetings ?></h4>
                </div>
                <p class="mb-1 fw-medium">Past Completed</p>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-border-shadow-info h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2 pb-1">
                    <div class="avatar me-2">
                        <span class="avatar-initial rounded bg-label-info"><i class="bx bx-laptop"></i></span>
                    </div>
                    <h4 class="ms-1 mb-0"><?= $onlineMeetings ?></h4>
                </div>
                <p class="mb-1 fw-medium">Online Meetings</p>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <h5 class="card-header">Meetings Filter</h5>
    <div class="card-body">
        <form method="GET">
            <div class="row g-2">
                <?php if($role !== 'salesman'): ?>
                <div class="col-md-3">
                    <label class="form-label">Salesman</label>
                    <select name="salesman_id" class="form-select">
                        <option value="">All Salesmen</option>
                        <?php foreach($salesmen as $sm): ?>
                            <option value="<?= $sm['id'] ?>" <?= $salesman_id == $sm['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sm['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter Meetings</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <h5 class="card-header">All Meetings Log</h5>
    <div class="table-responsive text-nowrap">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Type</th>
                    <th>Franchisor ID</th>
                    <th>Investor ID</th>
                    <th>Salesman</th>
                    <th>Meeting Notes</th>
                </tr>
            </thead>
            <tbody class="table-border-bottom-0">
                <?php if(empty($meetings)): ?>
                    <tr><td colspan="6" class="text-center">No meetings found.</td></tr>
                <?php else: ?>
                    <?php foreach($meetings as $m): ?>
                        <?php 
                            $isPast = strtotime($m['meeting_datetime']) < time(); 
                        ?>
                        <tr>
                            <td>
                                <strong><?= date('M d, Y', strtotime($m['meeting_datetime'])) ?></strong><br>
                                <small class="text-muted"><?= date('h:i A', strtotime($m['meeting_datetime'])) ?></small>
                                <?php if($isPast): ?>
                                    <span class="badge bg-label-success ms-2">Done</span>
                                <?php else: ?>
                                    <span class="badge bg-label-warning ms-2">Upcoming</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-label-info"><?= htmlspecialchars($m['meeting_type']) ?></span></td>
                            <td><a href="view.php?type=franchisor&id=<?= htmlspecialchars($m['franchisor_id']) ?>" target="_blank" class="fw-bold text-primary"><?= htmlspecialchars($m['franchisor_id']) ?></a></td>
                            <td><a href="view.php?type=investor&id=<?= htmlspecialchars($m['investor_id']) ?>" target="_blank" class="fw-bold text-success"><?= htmlspecialchars($m['investor_id']) ?></a></td>
                            <td><?= htmlspecialchars($m['salesman_name']) ?></td>
                            <td class="text-wrap" style="max-width: 300px;">
                                <?php 
                                $notes = htmlspecialchars($m['notes']);
                                if(empty($notes)): ?>
                                    <span class="text-muted fst-italic">No notes attached.</span>
                                <?php elseif(strlen($notes) > 100): 
                                    echo nl2br(substr($notes, 0, 100)) . '...';
                                ?>
                                    <a href="javascript:void(0);" class="text-primary d-block mt-1 small" 
                                       onclick='showFullNote(<?= json_encode($m['notes']) ?>)'>View Full</a>
                                <?php else: ?>
                                    <?= nl2br($notes) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Full Note View Modal -->
<div class="modal fade" id="fullNoteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Meeting Note</h5>
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
</script>
