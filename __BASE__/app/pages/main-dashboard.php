<?php
/**
 * Main Dashboard For ITBS_STANDARD_APP
 */
namespace PHPMaker2024\ITBS_STANDARD_APP;

// Key Metrics
$totalUsers = ExecuteScalar("SELECT COUNT(*) FROM users", "DB");
$totalUsersLastMonth = ExecuteScalar("SELECT COUNT(*) FROM users WHERE date_created <= CURRENT_DATE - INTERVAL '1 month'", "DB");
$totalUsersChange = ($totalUsersLastMonth > 0) ? round((($totalUsers - $totalUsersLastMonth) / $totalUsersLastMonth) * 100, 2) : 100;

$activeUsers = ExecuteScalar("SELECT COUNT(*) FROM users WHERE is_active = TRUE", "DB");
$activeUsersPercent = ($totalUsers > 0) ? round(($activeUsers / $totalUsers) * 100, 2) : 0;

$totalSystems = ExecuteScalar("SELECT COUNT(*) FROM systems", "DB");

$totalUserLevels = ExecuteScalar("SELECT COUNT(*) FROM user_levels", "DB");

// User Growth Chart Data
$userGrowthData = ExecuteRowsAssociative("
    WITH RECURSIVE date_range AS (
        SELECT DATE_TRUNC('month', CURRENT_DATE - INTERVAL '5 months') AS month
        UNION ALL
        SELECT month + INTERVAL '1 month'
        FROM date_range
        WHERE month < DATE_TRUNC('month', CURRENT_DATE)
    )
    SELECT 
        TO_CHAR(dr.month, 'Mon') as month,
        COALESCE(COUNT(u.user_id), 0) as total_users,
        COALESCE(SUM(CASE WHEN u.is_active = TRUE THEN 1 ELSE 0 END), 0) as active_users
    FROM date_range dr
    LEFT JOIN users u ON DATE_TRUNC('month', u.date_created) <= dr.month
    GROUP BY dr.month
    ORDER BY dr.month
", "DB");

$chartLabels = array_column($userGrowthData, 'month');
$totalData = array_column($userGrowthData, 'total_users');
$activeData = array_column($userGrowthData, 'active_users');

$userGrowthChartData = [
    'labels' => $chartLabels,
    'datasets' => [
        [
            'label' => 'Total Users',
            'data' => $totalData,
            'borderColor' => 'rgba(60,141,188,0.8)',
            'backgroundColor' => 'rgba(60,141,188,0.1)',
            'fill' => true
        ],
        [
            'label' => 'Active Users',
            'data' => $activeData,
            'borderColor' => 'rgba(0, 166, 90, 0.8)',
            'backgroundColor' => 'rgba(0, 166, 90, 0.1)',
            'fill' => true
        ]
    ]
];

// Audit Log Timeline
$auditLogTimeline = ExecuteRowsAssociative("
    SELECT 
        action_date,
        script,
        \"user\",
        action,
        \"table\",
        action_type,
        details,
        action_count,
        aggregated_id
    FROM aggregated_audit_logs
    ORDER BY aggregated_id DESC
    LIMIT 20
", "DB");

// Function to get appropriate icon for action type
function getActionIcon($actionType) {
    switch ($actionType) {
        case 'Add':
            return 'fa-plus';
        case 'Update':
            return 'fa-edit';
        case 'Delete':
            return 'fa-trash';
        case 'Login':
            return 'fa-sign-in-alt';
        case 'Logout':
            return 'fa-sign-out-alt';
        default:
            return 'fa-info-circle';
    }
}

// Function to get appropriate color for action type
function getActionColor($actionType) {
    switch ($actionType) {
        case 'Add':
            return 'success';
        case 'Update':
            return 'info';
        case 'Delete':
            return 'danger';
        case 'Login':
            return 'primary';
        case 'Logout':
            return 'secondary';
        default:
            return 'warning';
    }
}

// User Level Distribution
$userLevelDistribution = ExecuteRowsAssociative("
    SELECT ul.name, COUNT(*) as count
    FROM user_level_assignments ula
    JOIN user_levels ul ON ula.user_level_id = ul.user_level_id
    GROUP BY ul.user_level_id, ul.name
", "DB");

$levelLabels = array_column($userLevelDistribution, 'name');
$levelCounts = array_column($userLevelDistribution, 'count');

$userLevelChartData = [
    'labels' => $levelLabels,
    'datasets' => [
        [
            'data' => $levelCounts,
            'backgroundColor' => ['#f56954', '#00a65a', '#f39c12', '#00c0ef', '#3c8dbc', '#d2d6de']
        ]
    ]
];

// Top System Usage
$topSystems = ExecuteRowsAssociative("
    SELECT s.system_name, COUNT(*) as user_count
    FROM user_level_assignments ula
    JOIN user_levels ul ON ula.user_level_id = ul.user_level_id
    JOIN systems s ON ul.system_id = s.system_id
    GROUP BY s.system_id, s.system_name
    ORDER BY user_count DESC
    LIMIT 5
", "DB");

// Recent User Registrations
$recentUsers = ExecuteRowsAssociative("
    SELECT u.username, u.email, u.date_created, ul.name as user_level
    FROM users u
    LEFT JOIN user_level_assignments ula ON u.user_id = ula.user_id
    LEFT JOIN user_levels ul ON ula.user_level_id = ul.user_level_id
    ORDER BY u.date_created DESC
    LIMIT 5
", "DB");

// System Health
$systemHealth = ExecuteRowsAssociative("
    SELECT s.system_name, 
           CASE 
               WHEN COUNT(ula.user_id) > 0 THEN 'Active'
               ELSE 'Inactive'
           END as status,
           COUNT(DISTINCT ula.user_id) as active_users
    FROM systems s
    LEFT JOIN user_levels ul ON s.system_id = ul.system_id
    LEFT JOIN user_level_assignments ula ON ul.user_level_id = ula.user_level_id
    GROUP BY s.system_id, s.system_name
", "DB");

// Prepare data for JavaScript charts
$userGrowthChartDataJson = json_encode($userGrowthChartData);
$userLevelChartDataJson = json_encode($userLevelChartData);

// URLs for quick actions
$createUserUrl = GetUrl("UsersAdd");
$manageUserLevelsUrl = GetUrl("UserLevelsList");
$manageSystemsUrl = GetUrl("SystemsList");
$viewAuditLogsUrl = GetUrl("AuditLogsList");

$auditLog = Container("audit_logs");

// $conn = Conn();

// $data = $conn->fetchAssociative("SELECT * FROM audit_logs WHERE action = ?", ["login"]);

// print_r($data);

// $data = $conn->insert("audit_logs", [
//     "action" => "test",
//     '"user"' => "test",
//     "new_value" => "test log",
//     "date_time" => date("Y-m-d H:i:s")
// ]);


// $id = $conn->lastInsertId();
// print_r($id);
// print_r($data);

?>


<!-- Main content -->
<div class="container-fluid">
    <!-- Info boxes -->
    <div class="row">
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-info elevation-1"><i class="fas fa-users"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Users</span>
                    <span class="info-box-number">
                        <?php echo $totalUsers; ?>
                        <small><?php echo $totalUsersChange; ?>% from last month</small>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-user-check"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Active Users</span>
                    <span class="info-box-number">
                        <?php echo $activeUsers; ?>
                        <small><?php echo $activeUsersPercent; ?>% of total</small>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
                <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-server"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Systems</span>
                    <span class="info-box-number"><?php echo $totalSystems; ?></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
                <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-user-shield"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">User Levels</span>
                    <span class="info-box-number"><?php echo $totalUserLevels; ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- User Growth Chart -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">User Growth Over Time</h3>
                </div>
                <div class="card-body">
                    <div class="chart">
                        <canvas id="userGrowthChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Audit Log Timeline -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Audit Log Timeline</h3>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php 
                        $currentDate = null;
                        foreach ($auditLogTimeline as $log): 
                            if ($currentDate !== $log['action_date']) {
                                $currentDate = $log['action_date'];
                        ?>
                            <div class="time-label">
                                <span class="bg-red"><?php echo $log['action_date']; ?></span>
                            </div>
                        <?php 
                            }
                        ?>
                        <div>
                            <i class="fas <?php echo getActionIcon($log['action_type']); ?> bg-<?php echo getActionColor($log['action_type']); ?>"></i>
                            <div class="timeline-item">
                                <span class="time"><i class="fas fa-clock"></i> <?php echo $log['action_count']; ?> action<?php echo $log['action_count'] > 1 ? 's' : ''; ?></span>
                                <h3 class="timeline-header"><?php echo $log['script']; ?> by <?php echo $log['user']; ?></h3>
                                <div class="timeline-body">
                                    <strong><?php echo $log['action_type']; ?></strong> on <?php echo $log['table']; ?><br>
                                    <?php echo nl2br($log['details']); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- User Level Distribution -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">User Level Distribution</h3>
                </div>
                <div class="card-body">
                    <canvas id="userLevelChart"></canvas>
                </div>
            </div>

            <!-- Top System Usage -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Top System Usage</h3>
                </div>
                <div class="card-body p-0">
                    <ul class="nav flex-column">
                        <?php foreach ($topSystems as $system): ?>
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <?php echo $system['system_name']; ?>
                                <span class="float-right badge bg-primary"><?php echo $system['user_count']; ?> users</span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Recent User Registrations -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent User Registrations</h3>
                </div>
                <div class="card-body p-0">
                    <ul class="nav flex-column">
                        <?php foreach ($recentUsers as $user): ?>
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <?php echo $user['username']; ?> (<?php echo $user['email']; ?>)
                                <span class="float-right text-muted text-sm"><?php echo $user['date_created']; ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Quick Actions</h3>
                </div>
                <div class="card-body  d-grid">
                    <a href="<?php echo $createUserUrl; ?>" class="btn btn-primary btn-block mb-3">
                        <i class="fas fa-user-plus mr-2"></i> Create New User
                    </a>
                    <a href="<?php echo $manageUserLevelsUrl; ?>" class="btn btn-info btn-block mb-3">
                        <i class="fas fa-users-cog mr-2"></i> Manage User Levels
                    </a>
                    <a href="<?php echo $manageSystemsUrl; ?>" class="btn btn-warning btn-block mb-3">
                        <i class="fas fa-cogs mr-2"></i> Manage Systems
                    </a>
                    <a href="<?php echo $viewAuditLogsUrl; ?>" class="btn btn-secondary btn-block">
                        <i class="fas fa-history mr-2"></i> View Audit Logs
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- System Health -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">System Health</h3>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover text-nowrap">
                        <thead>
                            <tr>
                                <th>System Name</th>
                                <th>Status</th>
                                <th>Active Users</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($systemHealth as $system): ?>
                            <tr>
                                <td><?php echo $system['system_name']; ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $system['status'] === 'Active' ? 'success' : 'danger'; ?>">
                                        <?php echo $system['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo $system['active_users']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // User Growth Chart
    var growthCtx = document.getElementById('userGrowthChart').getContext('2d');
    new Chart(growthCtx, {
        type: 'line',
        data: <?php echo $userGrowthChartDataJson; ?>,
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });

    // User Level Distribution Chart
    var levelCtx = document.getElementById('userLevelChart').getContext('2d');
    new Chart(levelCtx, {
        type: 'pie',
        data: <?php echo $userLevelChartDataJson; ?>,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
