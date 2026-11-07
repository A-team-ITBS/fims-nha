<?php
/**
 * UAC - User Access Management
 * Lists system users and lets an administrator review / assign
 * system + role (user level) access and inspect the resulting permissions.
 */
namespace PHPMaker2024\FIMS;

// ── Key metrics ──────────────────────────────────────────────
$totalUsers = ExecuteScalar("SELECT COUNT(*) FROM users", "DB");
$activeUsers = ExecuteScalar("SELECT COUNT(*) FROM users WHERE is_active = TRUE", "DB");
$totalUserLevels = ExecuteScalar("SELECT COUNT(*) FROM user_levels", "DB");
$unassignedUsers = ExecuteScalar("
    SELECT COUNT(*) FROM users u
    WHERE NOT EXISTS (SELECT 1 FROM user_level_assignments ula WHERE ula.user_id = u.user_id)
", "DB");

// ── User list (with department / primary office / access summary) ──
$users = ExecuteRowsAssociative("
    SELECT
        u.user_id,
        u.username,
        u.email,
        u.employee_id,
        u.first_name,
        u.middle_name,
        u.last_name,
        u.suffix,
        u.mobile_number,
        u.is_active,
        u.last_login,
        u.department_id,
        u.position_id,
        d.department_name,
        d.department_acronym,
        o.office_name,
        STRING_AGG(DISTINCT s.system_name || ': ' || ul.name, ', ' ORDER BY s.system_name || ': ' || ul.name) AS access_summary
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.department_id
    LEFT JOIN user_offices uo ON uo.user_id = u.user_id AND uo.is_primary = TRUE
    LEFT JOIN offices o ON uo.office_id = o.office_id
    LEFT JOIN user_level_assignments ula ON ula.user_id = u.user_id
    LEFT JOIN user_levels ul ON ula.user_level_id = ul.user_level_id
    LEFT JOIN systems s ON ul.system_id = s.system_id
    GROUP BY u.user_id, d.department_id, o.office_id
    ORDER BY u.username
", "DB");

$usersById = [];
foreach ($users as $user) {
    $usersById[$user['user_id']] = $user;
}

// ── Per-user assignment detail (for the access panel) ──
$assignmentRows = ExecuteRowsAssociative("
    SELECT
        ula.assignment_id,
        ula.user_id,
        ula.system_id,
        COALESCE(s.system_name, 'N/A') AS system_name,
        ula.user_level_id,
        ul.name AS level_name
    FROM user_level_assignments ula
    JOIN user_levels ul ON ula.user_level_id = ul.user_level_id
    LEFT JOIN systems s ON ula.system_id = s.system_id
    ORDER BY ula.user_id
", "DB");

$assignmentsByUser = [];
foreach ($assignmentRows as $row) {
    $assignmentsByUser[$row['user_id']][] = $row;
}

// ── User levels (roles/modules) for the assignment dropdown ──
$userLevels = ExecuteRowsAssociative("
    SELECT ul.user_level_id, ul.name, ul.system_id, COALESCE(s.system_name, 'N/A') AS system_name
    FROM user_levels ul
    LEFT JOIN systems s ON ul.system_id = s.system_id
    ORDER BY s.system_name, ul.name
", "DB");

// ── Permission matrix per user level ──
// Bitmask per PHPMaker's Allow enum (src/Allow.php):
// ADD=1, DELETE=2, EDIT=4, LIST=8, ADMIN=16, VIEW=32, SEARCH=64, IMPORT=128, LOOKUP=256, PUSH=512, EXPORT=1024
const PERM_BITS = [
    'list' => 8,
    'view' => 32,
    'add' => 1,
    'edit' => 4,
    'delete' => 2,
    'search' => 64,
    'export' => 1024,
    'lookup' => 256,
];

$permissionRows = ExecuteRowsAssociative("
    SELECT user_level_id, table_name, permission
    FROM user_level_permissions
    ORDER BY user_level_id, table_name
", "DB");

// PHPMaker prefixes the stored table_name with a project ID (e.g. "{FIMS}users") when it
// seeds rows itself (see models/UserLevelsAdd.php), but some tooling may also write the
// plain name. Normalize so lookups work regardless of which form is on a given row.
function normalizeTableKey($tableName)
{
    return preg_replace('/^\{[^}]*\}/', '', $tableName);
}

$permissionsByLevel = [];
foreach ($permissionRows as $row) {
    $permission = (int)$row['permission'];
    $decoded = ['table_name' => normalizeTableKey($row['table_name'])];
    foreach (PERM_BITS as $key => $bit) {
        $decoded[$key] = ($permission & $bit) !== 0;
    }
    $permissionsByLevel[$row['user_level_id']][] = $decoded;
}

// ── Full module registry, sourced from PHPMaker's own table/page list ──
// ($USER_LEVEL_TABLES is loaded globally by src/config.php via src/userlevelsettings.php)
$registryTables = $GLOBALS["USER_LEVEL_TABLES"] ?? [];
$allModules = [];
foreach ($registryTables as $table) {
    $allModules[] = [
        'table_name' => $table[0],          // plain name, e.g. "users", "UserAccess.php"
        'full_key' => $table[4] . $table[0], // PHPMaker's native stored key, e.g. "{FIMS}users"
        'caption' => $table[2],
    ];
}
usort($allModules, function ($a, $b) {
    return strcasecmp($a['caption'], $b['caption']);
});

// ── Departments / Positions (for the Add User form) ──
$departments = ExecuteRowsAssociative("SELECT department_id, department_name FROM departments WHERE is_active = TRUE ORDER BY department_name", "DB");
$positions = ExecuteRowsAssociative("SELECT position_id, position_title FROM positions WHERE is_active = TRUE ORDER BY position_title", "DB");

// ── Systems (for the Create User Level form) ──
$systems = ExecuteRowsAssociative("SELECT system_id, system_name FROM systems WHERE is_active = TRUE ORDER BY system_name", "DB");

$assignmentsByUserJson = json_encode($assignmentsByUser);
$permissionsByLevelJson = json_encode($permissionsByLevel);
$allModulesJson = json_encode($allModules);
$usersByIdJson = json_encode($usersById);

// ── Permission checks ──
$canAddUser = AllowAdd("users");
$canEditUser = AllowEdit("users");
$canAddUserLevel = AllowAdd("user_levels");
$canManageAssignments = AllowAdd("user_level_assignments") && AllowDelete("user_level_assignments");
$canManagePermissions = AllowAdd("user_level_permissions") || AllowEdit("user_level_permissions");

// ── URLs ──
$manageLevelsUrl = GetUrl("UserLevelsList");
$manageSystemsUrl = GetUrl("SystemsList");
$deleteAssignmentBaseUrl = GetUrl("UserLevelAssignmentsDelete");
?>

<!-- Main content -->
<div class="container-fluid">
    <!-- Info boxes -->
    <div class="row">
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
                <span class="info-box-icon bg-info elevation-1"><i class="fas fa-users"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Users</span>
                    <span class="info-box-number"><?php echo $totalUsers; ?></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-user-check"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Active Users</span>
                    <span class="info-box-number"><?php echo $activeUsers; ?></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
                <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-user-shield"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">User Levels (Roles)</span>
                    <span class="info-box-number"><?php echo $totalUserLevels; ?></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box mb-3">
                <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-user-slash"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Unassigned Access</span>
                    <span class="info-box-number"><?php echo $unassignedUsers; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- User list -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-users mr-1"></i> Users</h3>
            <div class="card-tools">
                <?php if ($canAddUser): ?>
                <button type="button" class="btn btn-primary btn-sm" id="openAddUserModalBtn">
                    <i class="fas fa-user-plus mr-1"></i> Add User
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div class="input-group mb-3" style="max-width: 350px;">
                <input type="text" id="userSearchInput" class="form-control" placeholder="Search users...">
                <div class="input-group-append">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                </div>
            </div>
            <div class="table-responsive">
                <table id="usersTable" class="table table-hover table-bordered text-nowrap">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Office</th>
                            <th>System Access</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo HtmlEncode($user['username']); ?></td>
                            <td><?php echo HtmlEncode(trim($user['first_name'] . ' ' . $user['last_name'])); ?></td>
                            <td><?php echo HtmlEncode($user['department_acronym'] ?: $user['department_name'] ?: '—'); ?></td>
                            <td><?php echo HtmlEncode($user['office_name'] ?: '—'); ?></td>
                            <td>
                                <?php if ($user['access_summary']): ?>
                                    <?php foreach (explode(', ', $user['access_summary']) as $access): ?>
                                        <span class="badge badge-info mb-1"><?php echo HtmlEncode($access); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="badge badge-secondary">No Access</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td><?php echo $user['last_login'] ? HtmlEncode($user['last_login']) : 'Never'; ?></td>
                            <td>
                                <?php if ($canEditUser): ?>
                                <button type="button" class="btn btn-default btn-sm edit-user-btn" data-user-id="<?php echo $user['user_id']; ?>" title="Edit User">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php endif; ?>
                                <?php if ($canManageAssignments): ?>
                                <button type="button" class="btn btn-default btn-sm manage-access-btn"
                                        data-user-id="<?php echo $user['user_id']; ?>"
                                        data-username="<?php echo HtmlEncode($user['username']); ?>"
                                        title="Manage Access">
                                    <i class="fas fa-user-lock"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($canManageAssignments): ?>
    <div class="row" id="accessPanel">
        <!-- Role Access Assignment -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-user-lock mr-1"></i> Role Access</h3>
                    <?php if ($canAddUserLevel): ?>
                    <div class="card-tools">
                        <button type="button" class="btn btn-default btn-sm" id="openCreateUserLevelModalBtn">
                            <i class="fas fa-plus mr-1"></i> Create User Level
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <p>Selected user: <strong id="selectedUserLabel">— none —</strong></p>

                    <h6>Current Assignments</h6>
                    <ul class="list-group mb-3" id="currentAssignmentsList">
                        <li class="list-group-item text-muted">Select a user to view assignments.</li>
                    </ul>

                    <h6>Assign New Access</h6>
                    <input type="hidden" id="assignUserId">
                    <input type="hidden" id="assignSystemId">
                    <div class="form-group">
                        <label>User Level (Role)</label>
                        <select class="form-control" id="assignLevelSelect">
                            <option value="">Select role...</option>
                            <?php foreach ($userLevels as $level): ?>
                            <option value="<?php echo $level['user_level_id']; ?>" data-system-id="<?php echo $level['system_id']; ?>">
                                <?php echo HtmlEncode($level['name'] . ' (' . $level['system_name'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="btn btn-primary" id="assignAccessBtn" disabled>
                        <i class="fas fa-plus mr-1"></i> Assign Access
                    </button>
                </div>
            </div>
        </div>

        <!-- Permissions Management -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Permissions Management</h3>
                    <?php if ($canManagePermissions): ?>
                    <div class="card-tools">
                        <button type="button" class="btn btn-default btn-sm" id="syncPermissionModulesBtn" title="Pull in any PHPMaker tables/pages not yet listed here">
                            <i class="fas fa-sync-alt mr-1"></i> Sync Permission Modules
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        <i class="fas fa-lightbulb mr-1"></i>
                        Tip: tick the box beside a page name to grant all its permissions at once, or a column header to toggle that permission for every page.
                    </p>
                    <div class="row mb-2 align-items-end">
                        <div class="col-md-5">
                            <strong>Table/Page</strong>
                            <input type="text" id="permissionSearchInput" class="form-control mt-1" placeholder="Search tables...">
                        </div>
                        <?php if ($canManagePermissions): ?>
                        <div class="col-md-7 text-right">
                            <button type="button" class="btn btn-outline-success btn-sm" id="checkAllPermissionsBtn" disabled>
                                <i class="fas fa-check-square mr-1"></i> Check All
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="uncheckAllPermissionsBtn" disabled>
                                <i class="far fa-square mr-1"></i> Uncheck All
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped text-nowrap" id="permissionMatrixTable">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="permSelectAll"></th>
                                    <th>&nbsp;</th>
                                    <th class="text-center"><input type="checkbox" class="perm-col-toggle" data-perm="list"><br>List</th>
                                    <th class="text-center"><input type="checkbox" class="perm-col-toggle" data-perm="view"><br>View</th>
                                    <th class="text-center"><input type="checkbox" class="perm-col-toggle" data-perm="add"><br>Add</th>
                                    <th class="text-center"><input type="checkbox" class="perm-col-toggle" data-perm="edit"><br>Edit</th>
                                    <th class="text-center"><input type="checkbox" class="perm-col-toggle" data-perm="delete"><br>Delete</th>
                                    <th class="text-center"><input type="checkbox" class="perm-col-toggle" data-perm="search"><br>Search</th>
                                    <th class="text-center"><input type="checkbox" class="perm-col-toggle" data-perm="export"><br>Export</th>
                                    <th class="text-center"><input type="checkbox" class="perm-col-toggle" data-perm="lookup"><br>Lookup</th>
                                </tr>
                            </thead>
                            <tbody id="permissionMatrixBody">
                                <tr><td colspan="10" class="text-muted">Select a user level to view / edit its permissions.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php if ($canManagePermissions): ?>
                <div class="card-footer text-right">
                    <button type="button" class="btn btn-primary" id="savePermissionsBtn" disabled>
                        <i class="fas fa-save mr-1"></i> Save Permissions
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

   
</div>

<?php if ($canAddUser): ?>
<!-- Add User modal (self-contained, no Bootstrap JS dependency) -->
<div id="addUserOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1050; overflow-y:auto;">
    <div style="max-width:750px; margin:3% auto; background:#fff; border-radius:4px; overflow:hidden;">
        <div class="card mb-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Add New User</h3>
                <button type="button" class="btn btn-tool" id="closeAddUserModalBtn"><i class="fas fa-times"></i></button>
            </div>
            <form id="addUserForm">
                <div class="card-body">
                    <div id="addUserError" class="alert alert-danger" style="display:none;"></div>

                    <h6 class="text-primary font-weight-bold">Account</h6>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Username *</label>
                            <input type="text" class="form-control" name="username" id="addUserUsername" required maxlength="50" placeholder="e.g. jdelacruz">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Email *</label>
                            <input type="email" class="form-control" name="email" id="addUserEmail" required maxlength="100" placeholder="e.g. jdelacruz@nha.gov.ph">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Employee ID</label>
                        <input type="text" class="form-control" name="employee_id" id="addUserEmployeeId" maxlength="50" placeholder="e.g. 2021-00123">
                    </div>
                    <div class="form-group">
                        <label>Password *</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="password" id="addUserPassword" required minlength="6" placeholder="Minimum 6 characters">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" id="toggleAddUserPassword" tabindex="-1">
                                    <i class="fas fa-eye" id="toggleAddUserPasswordIcon"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <hr>
                    <h6 class="text-primary font-weight-bold">Personal Information</h6>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>First Name</label>
                            <input type="text" class="form-control" name="first_name" id="addUserFirstName" maxlength="50">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Middle Name</label>
                            <input type="text" class="form-control" name="middle_name" id="addUserMiddleName" maxlength="50">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Last Name</label>
                            <input type="text" class="form-control" name="last_name" id="addUserLastName" maxlength="50">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Suffix</label>
                            <select class="form-control" name="suffix" id="addUserSuffix">
                                <option value="">(None)</option>
                                <option value="Jr.">Jr.</option>
                                <option value="Sr.">Sr.</option>
                                <option value="II">II</option>
                                <option value="III">III</option>
                                <option value="IV">IV</option>
                                <option value="V">V</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Mobile Number</label>
                            <input type="text" class="form-control" name="mobile_number" id="addUserMobileNumber" maxlength="20" placeholder="e.g. 09XX-XXX-XXXX">
                        </div>
                    </div>

                    <hr>
                    <h6 class="text-primary font-weight-bold">Department &amp; Position</h6>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Department</label>
                            <select class="form-control" name="department_id" id="addUserDepartment">
                                <option value="">(No Department)</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>"><?php echo HtmlEncode($dept['department_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">The user's primary department assignment</small>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Position</label>
                            <select class="form-control" name="position_id" id="addUserPosition">
                                <option value="">(No Position)</option>
                                <?php foreach ($positions as $position): ?>
                                <option value="<?php echo $position['position_id']; ?>"><?php echo HtmlEncode($position['position_title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">The user's position / job title</small>
                        </div>
                    </div>

                    <hr>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" name="is_active" id="addUserIsActive" checked>
                        <label class="custom-control-label" for="addUserIsActive">Active (can log in)</label>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <button type="button" class="btn btn-default" id="cancelAddUserBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitAddUserBtn">
                        <i class="fas fa-save mr-1"></i> Save User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($canEditUser): ?>
<!-- Edit User modal (self-contained, no Bootstrap JS dependency) -->
<div id="editUserOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1050; overflow-y:auto;">
    <div style="max-width:750px; margin:3% auto; background:#fff; border-radius:4px; overflow:hidden;">
        <div class="card mb-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Edit User</h3>
                <button type="button" class="btn btn-tool" id="closeEditUserModalBtn"><i class="fas fa-times"></i></button>
            </div>
            <form id="editUserForm">
                <input type="hidden" id="editUserId">
                <div class="card-body">
                    <div id="editUserError" class="alert alert-danger" style="display:none;"></div>

                    <h6 class="text-primary font-weight-bold">Account</h6>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Username *</label>
                            <input type="text" class="form-control" id="editUserUsername" required maxlength="50">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Email *</label>
                            <input type="email" class="form-control" id="editUserEmail" required maxlength="100">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Employee ID</label>
                        <input type="text" class="form-control" id="editUserEmployeeId" maxlength="50" placeholder="e.g. 2021-00123">
                    </div>
                    <div class="form-group">
                        <label>New Password (leave blank to keep current)</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="editUserPassword" minlength="6" placeholder="Minimum 6 characters">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" id="toggleEditUserPassword" tabindex="-1">
                                    <i class="fas fa-eye" id="toggleEditUserPasswordIcon"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <hr>
                    <h6 class="text-primary font-weight-bold">Personal Information</h6>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>First Name</label>
                            <input type="text" class="form-control" id="editUserFirstName" maxlength="50">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Middle Name</label>
                            <input type="text" class="form-control" id="editUserMiddleName" maxlength="50">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Last Name</label>
                            <input type="text" class="form-control" id="editUserLastName" maxlength="50">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Suffix</label>
                            <select class="form-control" id="editUserSuffix">
                                <option value="">(None)</option>
                                <option value="Jr.">Jr.</option>
                                <option value="Sr.">Sr.</option>
                                <option value="II">II</option>
                                <option value="III">III</option>
                                <option value="IV">IV</option>
                                <option value="V">V</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Mobile Number</label>
                            <input type="text" class="form-control" id="editUserMobileNumber" maxlength="20" placeholder="e.g. 09XX-XXX-XXXX">
                        </div>
                    </div>

                    <hr>
                    <h6 class="text-primary font-weight-bold">Department &amp; Position</h6>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Department</label>
                            <select class="form-control" id="editUserDepartment">
                                <option value="">(No Department)</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>"><?php echo HtmlEncode($dept['department_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">The user's primary department assignment</small>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Position</label>
                            <select class="form-control" id="editUserPosition">
                                <option value="">(No Position)</option>
                                <?php foreach ($positions as $position): ?>
                                <option value="<?php echo $position['position_id']; ?>"><?php echo HtmlEncode($position['position_title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">The user's position / job title</small>
                        </div>
                    </div>

                    <hr>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="editUserIsActive">
                        <label class="custom-control-label" for="editUserIsActive">Active (can log in)</label>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <button type="button" class="btn btn-default" id="cancelEditUserBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitEditUserBtn">
                        <i class="fas fa-save mr-1"></i> Save User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($canAddUserLevel): ?>
<!-- Create User Level modal (self-contained, no Bootstrap JS dependency) -->
<div id="createUserLevelOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1050; overflow-y:auto;">
    <div style="max-width:500px; margin:8% auto; background:#fff; border-radius:4px; overflow:hidden;">
        <div class="card mb-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Create User Level</h3>
                <button type="button" class="btn btn-tool" id="closeCreateUserLevelModalBtn"><i class="fas fa-times"></i></button>
            </div>
            <form id="createUserLevelForm">
                <div class="card-body">
                    <div id="createUserLevelError" class="alert alert-danger" style="display:none;"></div>
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" class="form-control" id="createUserLevelName" required maxlength="50" placeholder="e.g. Regional Encoder">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" id="createUserLevelDescription" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label>System</label>
                        <select class="form-control" id="createUserLevelSystem">
                            <option value="">(No System)</option>
                            <?php foreach ($systems as $system): ?>
                            <option value="<?php echo $system['system_id']; ?>"><?php echo HtmlEncode($system['system_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">The system/module this role belongs to</small>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <button type="button" class="btn btn-default" id="cancelCreateUserLevelBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitCreateUserLevelBtn">
                        <i class="fas fa-save mr-1"></i> Save User Level
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const assignmentsByUser = <?php echo $assignmentsByUserJson ?: '{}'; ?>;
    const permissionsByLevel = <?php echo $permissionsByLevelJson ?: '{}'; ?>;
    const allModules = <?php echo $allModulesJson ?: '[]'; ?>;
    const usersById = <?php echo $usersByIdJson ?: '{}'; ?>;
    const deleteAssignmentBaseUrl = <?php echo json_encode($deleteAssignmentBaseUrl); ?>;
    const canManagePermissions = <?php echo $canManagePermissions ? 'true' : 'false'; ?>;

    // ── User search / filter ──
    const searchInput = document.getElementById('userSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const term = this.value.toLowerCase();
            document.querySelectorAll('#usersTable tbody tr').forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
            });
        });
    }

    // ── Role & access assignment panel ──
    const selectedUserLabel = document.getElementById('selectedUserLabel');
    const currentAssignmentsList = document.getElementById('currentAssignmentsList');
    const assignUserId = document.getElementById('assignUserId');
    const assignSystemId = document.getElementById('assignSystemId');
    const assignLevelSelect = document.getElementById('assignLevelSelect');
    const assignAccessBtn = document.getElementById('assignAccessBtn');
    const permissionMatrixBody = document.getElementById('permissionMatrixBody');
    const savePermissionsBtn = document.getElementById('savePermissionsBtn');

    const PERM_KEYS = ['list', 'view', 'add', 'edit', 'delete', 'search', 'export', 'lookup'];
    const permSelectAll = document.getElementById('permSelectAll');
    const permissionSearchInput = document.getElementById('permissionSearchInput');
    const checkAllPermissionsBtn = document.getElementById('checkAllPermissionsBtn');
    const uncheckAllPermissionsBtn = document.getElementById('uncheckAllPermissionsBtn');
    const syncPermissionModulesBtn = document.getElementById('syncPermissionModulesBtn');

    function rowCheckboxes(tr) {
        return PERM_KEYS.map(function (key) { return tr.querySelector('.perm-' + key); });
    }

    function syncRowCheckbox(tr) {
        const boxes = rowCheckboxes(tr);
        const rowToggle = tr.querySelector('.perm-row-toggle');
        if (rowToggle) rowToggle.checked = boxes.every(function (b) { return b.checked; });
    }

    function syncColumnCheckboxes() {
        PERM_KEYS.forEach(function (key) {
            const colToggle = document.querySelector('.perm-col-toggle[data-perm="' + key + '"]');
            if (!colToggle) return;
            const boxes = Array.from(permissionMatrixBody.querySelectorAll('.perm-' + key));
            colToggle.checked = boxes.length > 0 && boxes.every(function (b) { return b.checked; });
        });
    }

    function syncMasterCheckbox() {
        if (!permSelectAll) return;
        const boxes = Array.from(permissionMatrixBody.querySelectorAll('input[type="checkbox"].perm-cell'));
        permSelectAll.checked = boxes.length > 0 && boxes.every(function (b) { return b.checked; });
    }

    function syncAllToggles() {
        document.querySelectorAll('#permissionMatrixBody tr[data-table]').forEach(syncRowCheckbox);
        syncColumnCheckboxes();
        syncMasterCheckbox();
    }

    function renderPermissionMatrix(levelId) {
        if (!permissionMatrixBody) return;
        if (savePermissionsBtn) savePermissionsBtn.disabled = !levelId;
        if (checkAllPermissionsBtn) checkAllPermissionsBtn.disabled = !levelId;
        if (uncheckAllPermissionsBtn) uncheckAllPermissionsBtn.disabled = !levelId;

        if (!levelId || allModules.length === 0) {
            permissionMatrixBody.innerHTML = '<tr><td colspan="10" class="text-muted">Select a user level to view / edit its permissions.</td></tr>';
            return;
        }

        const existing = {};
        (permissionsByLevel[levelId] || []).forEach(function (r) { existing[r.table_name] = r; });

        const checkbox = function (perm, checked) {
            return '<input type="checkbox" class="perm-cell perm-' + perm + '" ' + (checked ? 'checked' : '') +
                (canManagePermissions ? '' : ' disabled') + '>';
        };

        permissionMatrixBody.innerHTML = allModules.map(function (module) {
            const r = existing[module.table_name] || {};
            const cells = PERM_KEYS.map(function (key) {
                return '<td class="text-center">' + checkbox(key, !!r[key]) + '</td>';
            }).join('');
            return '<tr data-table="' + module.table_name + '" data-full-key="' + module.full_key + '">' +
                '<td><input type="checkbox" class="perm-row-toggle"' + (canManagePermissions ? '' : ' disabled') + '></td>' +
                '<td>' + module.caption + '</td>' + cells + '</tr>';
        }).join('');

        syncAllToggles();
    }

    if (permissionMatrixBody) {
        permissionMatrixBody.addEventListener('change', function (e) {
            if (e.target.classList.contains('perm-cell')) {
                syncRowCheckbox(e.target.closest('tr'));
                syncColumnCheckboxes();
                syncMasterCheckbox();
            } else if (e.target.classList.contains('perm-row-toggle')) {
                const tr = e.target.closest('tr');
                rowCheckboxes(tr).forEach(function (b) { b.checked = e.target.checked; });
                syncColumnCheckboxes();
                syncMasterCheckbox();
            }
        });
    }

    document.querySelectorAll('.perm-col-toggle').forEach(function (colToggle) {
        colToggle.addEventListener('change', function () {
            const key = this.dataset.perm;
            document.querySelectorAll('#permissionMatrixBody .perm-' + key).forEach(function (b) {
                b.checked = colToggle.checked;
            });
            document.querySelectorAll('#permissionMatrixBody tr[data-table]').forEach(syncRowCheckbox);
            syncMasterCheckbox();
        });
    });

    if (permSelectAll) {
        permSelectAll.addEventListener('change', function () {
            document.querySelectorAll('#permissionMatrixBody input[type="checkbox"]').forEach(function (b) {
                b.checked = permSelectAll.checked;
            });
            syncColumnCheckboxes();
        });
    }

    if (permissionSearchInput) {
        permissionSearchInput.addEventListener('input', function () {
            const term = this.value.toLowerCase();
            document.querySelectorAll('#permissionMatrixBody tr[data-table]').forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
            });
        });
    }

    function setAllPermissionCheckboxes(checked) {
        document.querySelectorAll('#permissionMatrixBody input[type="checkbox"]').forEach(function (b) {
            b.checked = checked;
        });
        syncAllToggles();
    }

    if (checkAllPermissionsBtn) {
        checkAllPermissionsBtn.addEventListener('click', function () { setAllPermissionCheckboxes(true); });
    }
    if (uncheckAllPermissionsBtn) {
        uncheckAllPermissionsBtn.addEventListener('click', function () { setAllPermissionCheckboxes(false); });
    }

    if (syncPermissionModulesBtn) {
        syncPermissionModulesBtn.addEventListener('click', function () {
            syncPermissionModulesBtn.disabled = true;
            fetch('/api/user-access/sync-modules', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
                }
            })
                .then(function (r) { return r.json(); })
                .then(function (result) {
                    if (result.success) {
                        ew.showToast(result.message || 'Sync complete');
                        if (result.inserted > 0) location.reload();
                    } else {
                        ew.alert(result.message || 'Error syncing permission modules');
                    }
                })
                .catch(function () {
                    ew.alert('Error connecting to the server. Please try again.');
                })
                .finally(function () {
                    syncPermissionModulesBtn.disabled = false;
                });
        });
    }

    if (savePermissionsBtn) {
        savePermissionsBtn.addEventListener('click', function () {
            const levelId = assignLevelSelect ? assignLevelSelect.value : '';
            if (!levelId) {
                ew.alert('Select a user level first.');
                return;
            }
            const permissions = Array.from(permissionMatrixBody.querySelectorAll('tr[data-table]')).map(function (tr) {
                const entry = { table_name: tr.dataset.table, full_key: tr.dataset.fullKey };
                PERM_KEYS.forEach(function (key) { entry[key] = tr.querySelector('.perm-' + key).checked; });
                return entry;
            });

            savePermissionsBtn.disabled = true;
            fetch('/api/user-access/permissions', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
                },
                body: JSON.stringify({ user_level_id: levelId, permissions: permissions })
            })
                .then(function (r) { return r.json(); })
                .then(function (result) {
                    if (result.success) {
                        permissionsByLevel[levelId] = permissions;
                        ew.showToast('Permissions saved successfully');
                    } else {
                        ew.alert(result.message || 'Error saving permissions');
                    }
                })
                .catch(function () {
                    ew.alert('Error connecting to the server. Please try again.');
                })
                .finally(function () {
                    savePermissionsBtn.disabled = false;
                });
        });
    }

    function selectUserForAccess(userId, username) {
        if (selectedUserLabel) selectedUserLabel.textContent = username + ' (ID: ' + userId + ')';
        if (assignUserId) assignUserId.value = userId;

        const assignments = assignmentsByUser[userId] || [];
        if (currentAssignmentsList) {
            currentAssignmentsList.innerHTML = assignments.length
                ? assignments.map(function (a) {
                    return '<li class="list-group-item d-flex justify-content-between align-items-center">' +
                        '<span><strong>' + a.system_name + '</strong>: ' + a.level_name + '</span>' +
                        '<a href="' + deleteAssignmentBaseUrl + '/' + a.assignment_id + '" class="btn btn-outline-danger btn-sm" title="Remove">' +
                        '<i class="fas fa-trash"></i></a></li>';
                }).join('')
                : '<li class="list-group-item text-muted">No access assigned yet.</li>';
        }

        if (assignLevelSelect) assignLevelSelect.value = '';
        if (assignSystemId) assignSystemId.value = '';
        if (assignAccessBtn) assignAccessBtn.disabled = true;
        renderPermissionMatrix('');

        const panel = document.getElementById('accessPanel');
        if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    document.querySelectorAll('.manage-access-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            selectUserForAccess(this.dataset.userId, this.dataset.username);
        });
    });

    if (assignLevelSelect) {
        assignLevelSelect.addEventListener('change', function () {
            const selectedOption = this.options[this.selectedIndex];
            assignAccessBtn.disabled = !this.value;
            if (assignSystemId) assignSystemId.value = this.value ? selectedOption.dataset.systemId : '';
            renderPermissionMatrix(this.value);
        });
    }

    if (assignAccessBtn) {
        assignAccessBtn.addEventListener('click', function () {
            const userId = assignUserId ? assignUserId.value : '';
            const userLevelId = assignLevelSelect ? assignLevelSelect.value : '';
            if (!userId) {
                ew.alert('Select a user first.');
                return;
            }
            if (!userLevelId) {
                ew.alert('Select a user level first.');
                return;
            }

            assignAccessBtn.disabled = true;
            fetch('/api/user-access/assignments', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
                },
                body: JSON.stringify({
                    user_id: userId,
                    user_level_id: userLevelId,
                    system_id: assignSystemId ? assignSystemId.value : null
                })
            })
                .then(function (r) { return r.json(); })
                .then(function (result) {
                    if (result.success) {
                        ew.showToast('Access assigned successfully');
                        location.reload();
                    } else {
                        ew.alert(result.message || 'Error assigning access');
                        assignAccessBtn.disabled = false;
                    }
                })
                .catch(function () {
                    ew.alert('Error connecting to the server. Please try again.');
                    assignAccessBtn.disabled = false;
                });
        });
    }

    // ── Add User modal ──
    const addUserOverlay = document.getElementById('addUserOverlay');
    const addUserForm = document.getElementById('addUserForm');
    const addUserError = document.getElementById('addUserError');
    const openAddUserModalBtn = document.getElementById('openAddUserModalBtn');

    function openAddUserModal() {
        if (!addUserOverlay) return;
        addUserForm.reset();
        addUserError.style.display = 'none';
        addUserOverlay.style.display = 'block';
    }

    function closeAddUserModal() {
        if (addUserOverlay) addUserOverlay.style.display = 'none';
    }

    if (openAddUserModalBtn) openAddUserModalBtn.addEventListener('click', openAddUserModal);
    const closeAddUserModalBtn = document.getElementById('closeAddUserModalBtn');
    const cancelAddUserBtn = document.getElementById('cancelAddUserBtn');
    if (closeAddUserModalBtn) closeAddUserModalBtn.addEventListener('click', closeAddUserModal);
    if (cancelAddUserBtn) cancelAddUserBtn.addEventListener('click', closeAddUserModal);

    const toggleAddUserPassword = document.getElementById('toggleAddUserPassword');
    if (toggleAddUserPassword) {
        toggleAddUserPassword.addEventListener('click', function () {
            const field = document.getElementById('addUserPassword');
            const icon = document.getElementById('toggleAddUserPasswordIcon');
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });
    }

    if (addUserForm) {
        addUserForm.addEventListener('submit', function (e) {
            e.preventDefault();
            addUserError.style.display = 'none';

            const payload = {
                username: document.getElementById('addUserUsername').value.trim(),
                email: document.getElementById('addUserEmail').value.trim(),
                employee_id: document.getElementById('addUserEmployeeId').value.trim() || null,
                password: document.getElementById('addUserPassword').value,
                first_name: document.getElementById('addUserFirstName').value.trim(),
                middle_name: document.getElementById('addUserMiddleName').value.trim() || null,
                last_name: document.getElementById('addUserLastName').value.trim(),
                suffix: document.getElementById('addUserSuffix').value || null,
                mobile_number: document.getElementById('addUserMobileNumber').value.trim() || null,
                department_id: document.getElementById('addUserDepartment').value || null,
                position_id: document.getElementById('addUserPosition').value || null,
                is_active: document.getElementById('addUserIsActive').checked
            };

            const submitBtn = document.getElementById('submitAddUserBtn');
            submitBtn.disabled = true;

            fetch('/api/user-access/users', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
                },
                body: JSON.stringify(payload)
            })
                .then(function (r) { return r.json(); })
                .then(function (result) {
                    if (result.success) {
                        ew.showToast('User created successfully');
                        closeAddUserModal();
                        location.reload();
                    } else {
                        addUserError.textContent = result.message || 'Error creating user';
                        addUserError.style.display = 'block';
                    }
                })
                .catch(function () {
                    addUserError.textContent = 'Error connecting to the server. Please try again.';
                    addUserError.style.display = 'block';
                })
                .finally(function () {
                    submitBtn.disabled = false;
                });
        });
    }

    // ── Create User Level modal ──
    const createUserLevelOverlay = document.getElementById('createUserLevelOverlay');
    const createUserLevelForm = document.getElementById('createUserLevelForm');
    const createUserLevelError = document.getElementById('createUserLevelError');
    const openCreateUserLevelModalBtn = document.getElementById('openCreateUserLevelModalBtn');

    function openCreateUserLevelModal() {
        if (!createUserLevelOverlay) return;
        createUserLevelForm.reset();
        createUserLevelError.style.display = 'none';
        createUserLevelOverlay.style.display = 'block';
    }

    function closeCreateUserLevelModal() {
        if (createUserLevelOverlay) createUserLevelOverlay.style.display = 'none';
    }

    if (openCreateUserLevelModalBtn) openCreateUserLevelModalBtn.addEventListener('click', openCreateUserLevelModal);
    const closeCreateUserLevelModalBtn = document.getElementById('closeCreateUserLevelModalBtn');
    const cancelCreateUserLevelBtn = document.getElementById('cancelCreateUserLevelBtn');
    if (closeCreateUserLevelModalBtn) closeCreateUserLevelModalBtn.addEventListener('click', closeCreateUserLevelModal);
    if (cancelCreateUserLevelBtn) cancelCreateUserLevelBtn.addEventListener('click', closeCreateUserLevelModal);

    if (createUserLevelForm) {
        createUserLevelForm.addEventListener('submit', function (e) {
            e.preventDefault();
            createUserLevelError.style.display = 'none';

            const payload = {
                name: document.getElementById('createUserLevelName').value.trim(),
                description: document.getElementById('createUserLevelDescription').value.trim() || null,
                system_id: document.getElementById('createUserLevelSystem').value || null
            };

            const submitBtn = document.getElementById('submitCreateUserLevelBtn');
            submitBtn.disabled = true;

            fetch('/api/user-access/user-levels', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
                },
                body: JSON.stringify(payload)
            })
                .then(function (r) { return r.json(); })
                .then(function (result) {
                    if (result.success) {
                        ew.showToast('User level created successfully');
                        closeCreateUserLevelModal();
                        location.reload();
                    } else {
                        createUserLevelError.textContent = result.message || 'Error creating user level';
                        createUserLevelError.style.display = 'block';
                    }
                })
                .catch(function () {
                    createUserLevelError.textContent = 'Error connecting to the server. Please try again.';
                    createUserLevelError.style.display = 'block';
                })
                .finally(function () {
                    submitBtn.disabled = false;
                });
        });
    }

    // ── Edit User modal ──
    const editUserOverlay = document.getElementById('editUserOverlay');
    const editUserForm = document.getElementById('editUserForm');
    const editUserError = document.getElementById('editUserError');

    function openEditUserModal(userId) {
        if (!editUserOverlay) return;
        const user = usersById[userId];
        if (!user) return;

        editUserForm.reset();
        editUserError.style.display = 'none';
        document.getElementById('editUserId').value = userId;
        document.getElementById('editUserUsername').value = user.username || '';
        document.getElementById('editUserEmail').value = user.email || '';
        document.getElementById('editUserEmployeeId').value = user.employee_id || '';
        document.getElementById('editUserFirstName').value = user.first_name || '';
        document.getElementById('editUserMiddleName').value = user.middle_name || '';
        document.getElementById('editUserLastName').value = user.last_name || '';
        document.getElementById('editUserSuffix').value = user.suffix || '';
        document.getElementById('editUserMobileNumber').value = user.mobile_number || '';
        document.getElementById('editUserDepartment').value = user.department_id || '';
        document.getElementById('editUserPosition').value = user.position_id || '';
        document.getElementById('editUserIsActive').checked = !!user.is_active;

        editUserOverlay.style.display = 'block';
    }

    function closeEditUserModal() {
        if (editUserOverlay) editUserOverlay.style.display = 'none';
    }

    document.querySelectorAll('.edit-user-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openEditUserModal(this.dataset.userId);
        });
    });

    const closeEditUserModalBtn = document.getElementById('closeEditUserModalBtn');
    const cancelEditUserBtn = document.getElementById('cancelEditUserBtn');
    if (closeEditUserModalBtn) closeEditUserModalBtn.addEventListener('click', closeEditUserModal);
    if (cancelEditUserBtn) cancelEditUserBtn.addEventListener('click', closeEditUserModal);

    const toggleEditUserPassword = document.getElementById('toggleEditUserPassword');
    if (toggleEditUserPassword) {
        toggleEditUserPassword.addEventListener('click', function () {
            const field = document.getElementById('editUserPassword');
            const icon = document.getElementById('toggleEditUserPasswordIcon');
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });
    }

    if (editUserForm) {
        editUserForm.addEventListener('submit', function (e) {
            e.preventDefault();
            editUserError.style.display = 'none';

            const userId = document.getElementById('editUserId').value;
            const payload = {
                username: document.getElementById('editUserUsername').value.trim(),
                email: document.getElementById('editUserEmail').value.trim(),
                employee_id: document.getElementById('editUserEmployeeId').value.trim() || null,
                first_name: document.getElementById('editUserFirstName').value.trim(),
                middle_name: document.getElementById('editUserMiddleName').value.trim() || null,
                last_name: document.getElementById('editUserLastName').value.trim(),
                suffix: document.getElementById('editUserSuffix').value || null,
                mobile_number: document.getElementById('editUserMobileNumber').value.trim() || null,
                department_id: document.getElementById('editUserDepartment').value || null,
                position_id: document.getElementById('editUserPosition').value || null,
                is_active: document.getElementById('editUserIsActive').checked
            };
            const password = document.getElementById('editUserPassword').value;
            if (password) {
                payload.password = password;
            }

            const submitBtn = document.getElementById('submitEditUserBtn');
            submitBtn.disabled = true;

            fetch('/api/user-access/users/' + userId, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
                },
                body: JSON.stringify(payload)
            })
                .then(function (r) { return r.json(); })
                .then(function (result) {
                    if (result.success) {
                        ew.showToast('User updated successfully');
                        closeEditUserModal();
                        location.reload();
                    } else {
                        editUserError.textContent = result.message || 'Error updating user';
                        editUserError.style.display = 'block';
                    }
                })
                .catch(function () {
                    editUserError.textContent = 'Error connecting to the server. Please try again.';
                    editUserError.style.display = 'block';
                })
                .finally(function () {
                    submitBtn.disabled = false;
                });
        });
    }
});
</script>
