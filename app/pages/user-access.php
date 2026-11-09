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
    SELECT ul.user_level_id, ul.name, ul.description, ul.system_id, COALESCE(s.system_name, 'N/A') AS system_name
    FROM user_levels ul
    LEFT JOIN systems s ON ul.system_id = s.system_id
    ORDER BY s.system_name, ul.name
", "DB");

$userLevelsById = [];
foreach ($userLevels as $level) {
    $userLevelsById[$level['user_level_id']] = $level;
}
$userLevelsByIdJson = json_encode($userLevelsById);

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

// ── Caption / visibility overrides; see _assets/sql/00 - uac/02 - alter_table/04 - permission_module_captions.sql ──
// Hiding a module only removes it from this list - the underlying
// uac.user_level_permissions rows are left untouched, and it can be restored.
$captionOverrides = [];
$hiddenModules = [];
try {
    $overrideRows = ExecuteRowsAssociative("SELECT table_name, caption, is_hidden FROM permission_module_captions", "DB");
    foreach ($overrideRows as $row) {
        $captionOverrides[$row['table_name']] = $row['caption'];
        if (ConvertToBool($row['is_hidden'])) {
            $hiddenModules[$row['table_name']] = true;
        }
    }
} catch (\Exception $e) {
    // Table not created yet (migration not run) - fall back to registry captions only.
    $captionOverrides = [];
    $hiddenModules = [];
}

// ── Full module registry, sourced from PHPMaker's own table/page list ──
// ($USER_LEVEL_TABLES is loaded globally by src/config.php via src/userlevelsettings.php)
$registryTables = $GLOBALS["USER_LEVEL_TABLES"] ?? [];
$allModules = [];
foreach ($registryTables as $table) {
    $allModules[] = [
        'table_name' => $table[0],          // plain name, e.g. "users", "UserAccess.php"
        'full_key' => $table[4] . $table[0], // PHPMaker's native stored key, e.g. "{FIMS}users"
        'caption' => $captionOverrides[$table[0]] ?? $table[2],
        'is_hidden' => isset($hiddenModules[$table[0]]),
    ];
}
usort($allModules, function ($a, $b) {
    return strcasecmp($a['caption'], $b['caption']);
});

// ── Departments / Positions (for the Add User form) ──
$departments = ExecuteRowsAssociative("SELECT department_id, department_name FROM departments WHERE is_active = TRUE ORDER BY department_name", "DB");
$positions = ExecuteRowsAssociative("SELECT position_id, position_title FROM positions WHERE is_active = TRUE ORDER BY position_title", "DB");
$rcCodes = ExecuteRowsAssociative("SELECT rc_id, rc_code, rc_name FROM fims.responsibility_code WHERE status = 'Active' ORDER BY rc_code", "DB");

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
$canEditUserLevel = AllowEdit("user_levels");
$canManageAssignments = AllowAdd("user_level_assignments") && AllowDelete("user_level_assignments");
$canManagePermissions = AllowAdd("user_level_permissions") || AllowEdit("user_level_permissions");

// ── URLs ──
$manageLevelsUrl = GetUrl("UserLevelsList");
$manageSystemsUrl = GetUrl("SystemsList");
$deleteAssignmentBaseUrl = GetUrl("UserLevelAssignmentsDelete");
?>

<link rel="stylesheet" href="/app/fims-css/fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/app/fims-css/fims.css') ?>" />

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
                        <div class="input-group">
                            <select class="form-control" id="assignLevelSelect">
                                <option value="">Select role...</option>
                                <?php foreach ($userLevels as $level): ?>
                                <option value="<?php echo $level['user_level_id']; ?>" data-system-id="<?php echo $level['system_id']; ?>">
                                    <?php echo HtmlEncode($level['name'] . ' (' . $level['system_name'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($canEditUserLevel): ?>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" id="editUserLevelBtn" disabled title="Edit this user level">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
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
                            <div class="custom-control custom-checkbox d-inline-block mr-2 align-middle">
                                <input type="checkbox" class="custom-control-input" id="showHiddenModulesCheck">
                                <label class="custom-control-label" for="showHiddenModulesCheck">Show hidden modules</label>
                            </div>
                            <button type="button" class="btn btn-outline-success btn-sm" id="checkAllPermissionsBtn" disabled>
                                <i class="fas fa-check-square mr-1"></i> Check All
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="uncheckAllPermissionsBtn" disabled>
                                <i class="far fa-square mr-1"></i> Uncheck All
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" id="deleteSelectedModulesBtn" disabled>
                                <i class="fas fa-trash mr-1"></i> Delete Selected (<span id="deleteSelectedCount">0</span>)
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped text-nowrap" id="permissionMatrixTable">
                            <thead>
                                <tr>
                                    <?php if ($canManagePermissions): ?>
                                    <th><input type="checkbox" id="selectAllForDeleteCheck" title="Select all for deletion"></th>
                                    <?php endif; ?>
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
                                <tr><td colspan="11" class="text-muted">Loading modules...</td></tr>
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
<div id="addUserOverlay">
    <div class="add-user-modal-shell">
        <div class="card mb-0 add-user-modal-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Add New User</h3>
                <button type="button" class="btn btn-tool" id="closeAddUserModalBtn"><i class="fas fa-times"></i></button>
            </div>
            <form id="addUserForm" class="add-user-modal-form">
                <div class="card-body">
                    <div class="add-user-stepper">
                        <div class="add-user-stepper-track">
                            <div class="add-user-stepper-seg is-active" data-step-index="0"></div>
                            <div class="add-user-stepper-seg" data-step-index="1"></div>
                            <div class="add-user-stepper-seg" data-step-index="2"></div>
                        </div>
                        <div class="add-user-stepper-labels">
                            <span class="is-active" data-step-index="0">Account</span>
                            <span data-step-index="1">Personal Info</span>
                            <span data-step-index="2">Assignment</span>
                        </div>
                    </div>

                    <div id="addUserError" class="alert alert-danger" style="display:none;"></div>

                    <div class="add-user-step" data-step="0">
                        <div class="step-heading">Account</div>
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
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Employee ID</label>
                                <input type="text" class="form-control" name="employee_id" id="addUserEmployeeId" maxlength="50" placeholder="e.g. 2021-00123">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Password *</label>
                                <div class="icon-input">
                                    <input type="password" class="form-control has-suffix" name="password" id="addUserPassword" required minlength="6" placeholder="Minimum 6 characters">
                                    <button type="button" class="icon-input-suffix-btn" id="toggleAddUserPassword" tabindex="-1">
                                        <i class="fas fa-eye" id="toggleAddUserPasswordIcon"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="add-user-step" data-step="1" style="display:none;">
                        <div class="step-heading">Personal Information</div>
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
                    </div>

                    <div class="add-user-step" data-step="2" style="display:none;">
                        <div class="step-heading">RC Code &amp; Position</div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>RC Code</label>
                                <div class="searchable-select" data-searchable-select data-placeholder="(No RC Code)">
                                    <input type="text" class="form-control" id="addUserRcCodeInput" placeholder="(No RC Code)" autocomplete="off">
                                    <i class="fas fa-chevron-down searchable-select-caret"></i>
                                    <input type="hidden" name="rc_id" id="addUserRcCode">
                                    <div class="searchable-select-menu" style="display:none;">
                                        <div class="searchable-select-option is-empty" data-value="" data-label="">No results found</div>
                                        <div class="searchable-select-option" data-value="" data-label="(No RC Code)">(No RC Code)</div>
                                        <?php foreach ($rcCodes as $rc): ?>
                                        <div class="searchable-select-option" data-value="<?php echo HtmlEncode($rc['rc_id']); ?>" data-label="<?php echo HtmlEncode($rc['rc_code']); ?> — <?php echo HtmlEncode($rc['rc_name']); ?>"><?php echo HtmlEncode($rc['rc_code']); ?> — <?php echo HtmlEncode($rc['rc_name']); ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <small class="form-text text-muted">The user's responsibility center code</small>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Position</label>
                                <div class="searchable-select" data-searchable-select data-placeholder="(No Position)">
                                    <input type="text" class="form-control" id="addUserPositionInput" placeholder="(No Position)" autocomplete="off">
                                    <i class="fas fa-chevron-down searchable-select-caret"></i>
                                    <input type="hidden" name="position_id" id="addUserPosition">
                                    <div class="searchable-select-menu" style="display:none;">
                                        <div class="searchable-select-option is-empty" data-value="" data-label="">No results found</div>
                                        <div class="searchable-select-option" data-value="" data-label="(No Position)">(No Position)</div>
                                        <?php foreach ($positions as $position): ?>
                                        <div class="searchable-select-option" data-value="<?php echo $position['position_id']; ?>" data-label="<?php echo HtmlEncode($position['position_title']); ?>"><?php echo HtmlEncode($position['position_title']); ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <small class="form-text text-muted">The user's position / job title</small>
                            </div>
                        </div>

                        <div class="custom-control custom-switch mt-4">
                            <input type="checkbox" class="custom-control-input" name="is_active" id="addUserIsActive" checked>
                            <label class="custom-control-label" for="addUserIsActive">Active (can log in)</label>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end align-items-center">
                    <button type="button" class="btn btn-link text-muted" id="cancelAddUserBtn">Cancel</button>
                    <button type="button" class="btn btn-light" id="addUserBackBtn" style="display:none;">Back</button>
                    <button type="button" class="btn btn-primary" id="addUserNextBtn">Next</button>
                    <button type="submit" class="btn btn-primary" id="submitAddUserBtn" style="display:none;">Save User</button>
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

<?php if ($canEditUserLevel): ?>
<!-- Edit User Level modal (self-contained, no Bootstrap JS dependency) -->
<div id="editUserLevelOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1050; overflow-y:auto;">
    <div style="max-width:500px; margin:8% auto; background:#fff; border-radius:4px; overflow:hidden;">
        <div class="card mb-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Edit User Level</h3>
                <button type="button" class="btn btn-tool" id="closeEditUserLevelModalBtn"><i class="fas fa-times"></i></button>
            </div>
            <form id="editUserLevelForm">
                <input type="hidden" id="editUserLevelId">
                <div class="card-body">
                    <div id="editUserLevelError" class="alert alert-danger" style="display:none;"></div>
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" class="form-control" id="editUserLevelName" required maxlength="50">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" id="editUserLevelDescription" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label>System</label>
                        <select class="form-control" id="editUserLevelSystem">
                            <option value="">(No System)</option>
                            <?php foreach ($systems as $system): ?>
                            <option value="<?php echo $system['system_id']; ?>"><?php echo HtmlEncode($system['system_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">The system/module this role belongs to</small>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <button type="button" class="btn btn-default" id="cancelEditUserLevelBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitEditUserLevelBtn">
                        <i class="fas fa-save mr-1"></i> Save User Level
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($canManagePermissions): ?>
<!-- Rename Module modal (self-contained, no Bootstrap JS dependency) -->
<div id="renameModuleOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1050; overflow-y:auto;">
    <div style="max-width:450px; margin:10% auto; background:#fff; border-radius:4px; overflow:hidden;">
        <div class="card mb-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Rename Module</h3>
                <button type="button" class="btn btn-tool" id="closeRenameModuleModalBtn"><i class="fas fa-times"></i></button>
            </div>
            <form id="renameModuleForm">
                <input type="hidden" id="renameModuleTableName">
                <div class="card-body">
                    <div id="renameModuleError" class="alert alert-danger" style="display:none;"></div>
                    <div class="form-group">
                        <label>Display Caption *</label>
                        <input type="text" class="form-control" id="renameModuleCaption" required maxlength="150">
                        <small class="form-text text-muted">Only changes how this row is labeled here — not the underlying page/table.</small>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <button type="button" class="btn btn-default" id="cancelRenameModuleBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitRenameModuleBtn">
                        <i class="fas fa-save mr-1"></i> Save Caption
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Confirm Removal modal (self-contained, no Bootstrap JS dependency) -->
<div id="removeModuleOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1050; overflow-y:auto;">
    <div style="max-width:450px; margin:10% auto; background:#fff; border-radius:4px; overflow:hidden;">
        <div class="card mb-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0"><i class="fas fa-exclamation-triangle text-warning mr-1"></i> Remove <span id="removeModuleCount">Module</span></h3>
                <button type="button" class="btn btn-tool" id="closeRemoveModuleModalBtn"><i class="fas fa-times"></i></button>
            </div>
            <div class="card-body">
                <p>You are about to remove the following module(s) from the Permissions Management list:</p>
                <ul class="list-group mb-3" id="removeModuleList" style="max-height:220px; overflow-y:auto;"></ul>
                <p class="text-muted small mb-0">
                    <i class="fas fa-info-circle mr-1"></i>
                    This only hides the row(s) here — existing permission settings are kept and nothing is deleted.
                    You can bring them back anytime with "Show hidden modules".
                </p>
            </div>
            <div class="card-footer text-right">
                <button type="button" class="btn btn-default" id="cancelRemoveModuleBtn">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmRemoveModuleBtn">
                    <i class="fas fa-trash mr-1"></i> Remove
                </button>
            </div>
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
    const userLevelsById = <?php echo $userLevelsByIdJson ?: '{}'; ?>;
    const deleteAssignmentBaseUrl = <?php echo json_encode($deleteAssignmentBaseUrl); ?>;
    const canManagePermissions = <?php echo $canManagePermissions ? 'true' : 'false'; ?>;

    // ── Searchable select (combobox) ──
    function initSearchableSelect(wrap) {
        const input = wrap.querySelector('input[type="text"]');
        const hidden = wrap.querySelector('input[type="hidden"]');
        const menu = wrap.querySelector('.searchable-select-menu');
        const noResults = menu.querySelector('.searchable-select-option.is-empty');
        const options = Array.from(menu.querySelectorAll('.searchable-select-option:not(.is-empty)'));
        const placeholder = wrap.getAttribute('data-placeholder') || '';

        function labelFor(value) {
            const opt = options.find(function (o) { return o.dataset.value === value; });
            return opt ? opt.dataset.label : placeholder;
        }

        function filterOptions(query) {
            const q = query.trim().toLowerCase();
            let anyVisible = false;
            options.forEach(function (o) {
                const match = q === '' || o.dataset.label.toLowerCase().indexOf(q) !== -1;
                o.style.display = match ? '' : 'none';
                o.classList.toggle('is-selected', o.dataset.value === hidden.value);
                if (match) anyVisible = true;
            });
            noResults.style.display = anyVisible ? 'none' : '';
        }

        function openMenu() {
            menu.style.display = 'block';
            filterOptions(input.value === labelFor(hidden.value) ? '' : input.value);
            input.select();
            wrap.scrollIntoView({ block: 'nearest' });
        }

        function closeMenu() {
            menu.style.display = 'none';
            input.value = labelFor(hidden.value);
        }

        input.addEventListener('focus', openMenu);
        input.addEventListener('input', function () { filterOptions(input.value); menu.style.display = 'block'; });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') input.blur();
        });
        input.addEventListener('blur', function () {
            setTimeout(closeMenu, 150);
        });

        options.forEach(function (o) {
            o.addEventListener('mousedown', function (e) {
                e.preventDefault();
                hidden.value = o.dataset.value;
                hidden.dispatchEvent(new Event('change', { bubbles: true }));
                input.value = o.dataset.label || placeholder;
                menu.style.display = 'none';
            });
        });

        wrap.resetSearchableSelect = function () {
            hidden.value = '';
            input.value = placeholder;
        };

        input.value = labelFor(hidden.value);
    }

    document.querySelectorAll('[data-searchable-select]').forEach(initSearchableSelect);

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

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
    const editUserLevelBtn = document.getElementById('editUserLevelBtn');
    const permissionMatrixBody = document.getElementById('permissionMatrixBody');
    const savePermissionsBtn = document.getElementById('savePermissionsBtn');

    const PERM_KEYS = ['list', 'view', 'add', 'edit', 'delete', 'search', 'export', 'lookup'];
    const permSelectAll = document.getElementById('permSelectAll');
    const permissionSearchInput = document.getElementById('permissionSearchInput');
    const checkAllPermissionsBtn = document.getElementById('checkAllPermissionsBtn');
    const uncheckAllPermissionsBtn = document.getElementById('uncheckAllPermissionsBtn');
    const syncPermissionModulesBtn = document.getElementById('syncPermissionModulesBtn');
    const showHiddenModulesCheck = document.getElementById('showHiddenModulesCheck');

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

        if (allModules.length === 0) {
            permissionMatrixBody.innerHTML = '<tr><td colspan="11" class="text-muted">No modules to show.</td></tr>';
            return;
        }

        // The module list itself (rename / remove / restore) is always manageable -
        // only the per-level permission checkboxes need an actual level selected.
        const existing = {};
        if (levelId) {
            (permissionsByLevel[levelId] || []).forEach(function (r) { existing[r.table_name] = r; });
        }
        const canEditCells = canManagePermissions && !!levelId;

        const checkbox = function (perm, checked) {
            return '<input type="checkbox" class="perm-cell perm-' + perm + '" ' + (checked ? 'checked' : '') +
                (canEditCells ? '' : ' disabled') + '>';
        };

        const showHidden = showHiddenModulesCheck && showHiddenModulesCheck.checked;
        const visibleModules = allModules.filter(function (m) { return showHidden || !m.is_hidden; });

        if (visibleModules.length === 0) {
            permissionMatrixBody.innerHTML = '<tr><td colspan="11" class="text-muted">No modules to show.</td></tr>';
            return;
        }

        permissionMatrixBody.innerHTML = visibleModules.map(function (module) {
            const r = existing[module.table_name] || {};
            const cells = PERM_KEYS.map(function (key) {
                return '<td class="text-center">' + checkbox(key, !!r[key]) + '</td>';
            }).join('');
            let actions = '';
            let deleteSelectCell = '';
            if (canManagePermissions) {
                actions += ' <button type="button" class="btn btn-link btn-sm p-0 ml-1 edit-module-caption-btn" data-table="' +
                    escapeHtml(module.table_name) + '" data-caption="' + escapeHtml(module.caption) + '" title="Rename">' +
                    '<i class="fas fa-pencil-alt"></i></button>';
                if (module.is_hidden) {
                    actions += ' <button type="button" class="btn btn-link btn-sm p-0 ml-1 text-success restore-module-btn" data-table="' +
                        escapeHtml(module.table_name) + '" title="Restore to the list">' +
                        '<i class="fas fa-undo"></i></button>';
                }
                deleteSelectCell = '<td><input type="checkbox" class="module-delete-select" data-table="' +
                    escapeHtml(module.table_name) + '" data-caption="' + escapeHtml(module.caption) + '"></td>';
            }
            const rowClass = module.is_hidden ? ' class="text-muted"' : '';
            return '<tr' + rowClass + ' data-table="' + escapeHtml(module.table_name) + '" data-full-key="' + escapeHtml(module.full_key) + '">' +
                deleteSelectCell +
                '<td><input type="checkbox" class="perm-row-toggle"' + (canEditCells ? '' : ' disabled') + '></td>' +
                '<td>' + escapeHtml(module.caption) + (module.is_hidden ? ' <span class="badge badge-secondary">Hidden</span>' : '') + actions + '</td>' + cells + '</tr>';
        }).join('');

        updateDeleteSelectedState();

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
            } else if (e.target.classList.contains('module-delete-select')) {
                updateDeleteSelectedState();
            }
        });

        permissionMatrixBody.addEventListener('click', function (e) {
            const editBtn = e.target.closest('.edit-module-caption-btn');
            if (editBtn) {
                openRenameModuleModal(editBtn.dataset.table, editBtn.dataset.caption);
                return;
            }
            const restoreBtn = e.target.closest('.restore-module-btn');
            if (restoreBtn) {
                const module = allModules.find(function (m) { return m.table_name === restoreBtn.dataset.table; });
                setModuleVisibility(restoreBtn.dataset.table, module ? module.caption : restoreBtn.dataset.table, false);
            }
        });
    }

    // ── Bulk delete selection ──
    const selectAllForDeleteCheck = document.getElementById('selectAllForDeleteCheck');
    const deleteSelectedModulesBtn = document.getElementById('deleteSelectedModulesBtn');
    const deleteSelectedCount = document.getElementById('deleteSelectedCount');

    function getSelectedModulesForDelete() {
        return Array.from(permissionMatrixBody.querySelectorAll('.module-delete-select:checked')).map(function (cb) {
            return { table_name: cb.dataset.table, caption: cb.dataset.caption };
        });
    }

    function updateDeleteSelectedState() {
        const boxes = Array.from(permissionMatrixBody.querySelectorAll('.module-delete-select'));
        const checkedCount = boxes.filter(function (b) { return b.checked; }).length;
        if (deleteSelectedCount) deleteSelectedCount.textContent = checkedCount;
        if (deleteSelectedModulesBtn) deleteSelectedModulesBtn.disabled = checkedCount === 0;
        if (selectAllForDeleteCheck) {
            selectAllForDeleteCheck.checked = boxes.length > 0 && checkedCount === boxes.length;
        }
    }

    if (selectAllForDeleteCheck) {
        selectAllForDeleteCheck.addEventListener('change', function () {
            permissionMatrixBody.querySelectorAll('.module-delete-select').forEach(function (cb) {
                cb.checked = selectAllForDeleteCheck.checked;
            });
            updateDeleteSelectedState();
        });
    }

    if (deleteSelectedModulesBtn) {
        deleteSelectedModulesBtn.addEventListener('click', function () {
            const selected = getSelectedModulesForDelete();
            if (selected.length === 0) return;
            openRemoveModuleModal(selected);
        });
    }

    if (showHiddenModulesCheck) {
        showHiddenModulesCheck.addEventListener('change', function () {
            renderPermissionMatrix(assignLevelSelect ? assignLevelSelect.value : '');
        });
    }

    function setModuleVisibility(tableName, caption, isHidden) {
        return fetch('/api/user-access/module-visibility', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
            },
            body: JSON.stringify({ table_name: tableName, caption: caption, is_hidden: isHidden })
        })
            .then(function (r) { return r.json(); })
            .then(function (result) {
                if (result.success) {
                    ew.showToast(isHidden ? 'Module removed from the list' : 'Module restored');
                    location.reload();
                } else {
                    ew.alert(result.message || 'Error updating module visibility');
                }
            })
            .catch(function () {
                ew.alert('Error connecting to the server. Please try again.');
            });
    }

    // ── Confirm Removal modal ──
    const removeModuleOverlay = document.getElementById('removeModuleOverlay');
    let pendingRemoveModules = [];

    function openRemoveModuleModal(modules) {
        if (!removeModuleOverlay) return;
        pendingRemoveModules = modules;

        document.getElementById('removeModuleCount').textContent =
            modules.length === 1 ? 'Module' : modules.length + ' Modules';

        document.getElementById('removeModuleList').innerHTML = modules.map(function (m) {
            return '<li class="list-group-item py-1">' +
                '<strong>' + escapeHtml(m.caption) + '</strong>' +
                '<div class="text-muted small">' + escapeHtml(m.table_name) + '</div></li>';
        }).join('');

        removeModuleOverlay.style.display = 'block';
    }

    function closeRemoveModuleModal() {
        pendingRemoveModules = [];
        if (removeModuleOverlay) removeModuleOverlay.style.display = 'none';
    }

    const closeRemoveModuleModalBtn = document.getElementById('closeRemoveModuleModalBtn');
    const cancelRemoveModuleBtn = document.getElementById('cancelRemoveModuleBtn');
    const confirmRemoveModuleBtn = document.getElementById('confirmRemoveModuleBtn');
    if (closeRemoveModuleModalBtn) closeRemoveModuleModalBtn.addEventListener('click', closeRemoveModuleModal);
    if (cancelRemoveModuleBtn) cancelRemoveModuleBtn.addEventListener('click', closeRemoveModuleModal);
    if (confirmRemoveModuleBtn) {
        confirmRemoveModuleBtn.addEventListener('click', function () {
            if (pendingRemoveModules.length === 0) return;
            confirmRemoveModuleBtn.disabled = true;
            removeModules(pendingRemoveModules)
                .finally(function () { confirmRemoveModuleBtn.disabled = false; });
        });
    }

    function removeModules(modules) {
        return fetch('/api/user-access/modules/remove', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
            },
            body: JSON.stringify({ modules: modules })
        })
            .then(function (r) { return r.json(); })
            .then(function (result) {
                if (result.success) {
                    ew.showToast(result.message || 'Module(s) removed');
                    location.reload();
                } else {
                    ew.alert(result.message || 'Error removing module(s)');
                }
            })
            .catch(function () {
                ew.alert('Error connecting to the server. Please try again.');
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
        if (editUserLevelBtn) editUserLevelBtn.disabled = true;
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
            if (editUserLevelBtn) editUserLevelBtn.disabled = !this.value;
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

    // ── Add User modal: step navigation ──
    const addUserSteps = Array.from(addUserForm.querySelectorAll('.add-user-step'));
    const addUserStepperSegs = Array.from(addUserForm.querySelectorAll('.add-user-stepper-seg'));
    const addUserStepperLabels = Array.from(addUserForm.querySelectorAll('.add-user-stepper-labels span'));
    const addUserBackBtn = document.getElementById('addUserBackBtn');
    const addUserNextBtn = document.getElementById('addUserNextBtn');
    const submitAddUserBtn = document.getElementById('submitAddUserBtn');
    let addUserStepIndex = 0;

    function validateAddUserStep(stepEl) {
        const fields = Array.from(stepEl.querySelectorAll('input, select, textarea'));
        for (const field of fields) {
            if (!field.checkValidity()) {
                field.reportValidity();
                return false;
            }
        }
        return true;
    }

    function showAddUserStep(index) {
        addUserStepIndex = index;
        addUserSteps.forEach(function (stepEl, i) {
            stepEl.style.display = i === index ? '' : 'none';
            stepEl.classList.toggle('is-visible', i === index);
        });
        addUserStepperSegs.forEach(function (seg, i) {
            seg.classList.toggle('is-active', i === index);
            seg.classList.toggle('is-done', i < index);
        });
        addUserStepperLabels.forEach(function (label, i) {
            label.classList.toggle('is-active', i === index);
            label.classList.toggle('is-done', i < index);
        });
        if (addUserBackBtn) addUserBackBtn.style.display = index === 0 ? 'none' : '';
        const isLastStep = index === addUserSteps.length - 1;
        if (addUserNextBtn) addUserNextBtn.style.display = isLastStep ? 'none' : '';
        if (submitAddUserBtn) submitAddUserBtn.style.display = isLastStep ? '' : 'none';
    }

    if (addUserNextBtn) {
        addUserNextBtn.addEventListener('click', function () {
            if (!validateAddUserStep(addUserSteps[addUserStepIndex])) return;
            showAddUserStep(addUserStepIndex + 1);
        });
    }
    if (addUserBackBtn) {
        addUserBackBtn.addEventListener('click', function () {
            showAddUserStep(addUserStepIndex - 1);
        });
    }
    addUserForm.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT' && addUserStepIndex < addUserSteps.length - 1) {
            e.preventDefault();
            if (addUserNextBtn) addUserNextBtn.click();
        }
    });

    function openAddUserModal() {
        if (!addUserOverlay) return;
        addUserForm.reset();
        addUserForm.querySelectorAll('[data-searchable-select]').forEach(function (wrap) {
            if (wrap.resetSearchableSelect) wrap.resetSearchableSelect();
        });
        addUserError.style.display = 'none';
        showAddUserStep(0);
        addUserOverlay.style.display = 'flex';
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
                rc_id: document.getElementById('addUserRcCode').value || null,
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

    // ── Edit User Level modal ──
    const editUserLevelOverlay = document.getElementById('editUserLevelOverlay');
    const editUserLevelForm = document.getElementById('editUserLevelForm');
    const editUserLevelError = document.getElementById('editUserLevelError');

    function openEditUserLevelModal(levelId) {
        if (!editUserLevelOverlay) return;
        const level = userLevelsById[levelId];
        if (!level) return;

        editUserLevelForm.reset();
        editUserLevelError.style.display = 'none';
        document.getElementById('editUserLevelId').value = levelId;
        document.getElementById('editUserLevelName').value = level.name || '';
        document.getElementById('editUserLevelDescription').value = level.description || '';
        document.getElementById('editUserLevelSystem').value = level.system_id || '';

        editUserLevelOverlay.style.display = 'block';
    }

    function closeEditUserLevelModal() {
        if (editUserLevelOverlay) editUserLevelOverlay.style.display = 'none';
    }

    if (editUserLevelBtn) {
        editUserLevelBtn.addEventListener('click', function () {
            if (assignLevelSelect && assignLevelSelect.value) {
                openEditUserLevelModal(assignLevelSelect.value);
            }
        });
    }

    const closeEditUserLevelModalBtn = document.getElementById('closeEditUserLevelModalBtn');
    const cancelEditUserLevelBtn = document.getElementById('cancelEditUserLevelBtn');
    if (closeEditUserLevelModalBtn) closeEditUserLevelModalBtn.addEventListener('click', closeEditUserLevelModal);
    if (cancelEditUserLevelBtn) cancelEditUserLevelBtn.addEventListener('click', closeEditUserLevelModal);

    if (editUserLevelForm) {
        editUserLevelForm.addEventListener('submit', function (e) {
            e.preventDefault();
            editUserLevelError.style.display = 'none';

            const levelId = document.getElementById('editUserLevelId').value;
            const payload = {
                name: document.getElementById('editUserLevelName').value.trim(),
                description: document.getElementById('editUserLevelDescription').value.trim() || null,
                system_id: document.getElementById('editUserLevelSystem').value || null
            };

            const submitBtn = document.getElementById('submitEditUserLevelBtn');
            submitBtn.disabled = true;

            fetch('/api/user-access/user-levels/' + levelId, {
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
                        ew.showToast('User level updated successfully');
                        closeEditUserLevelModal();
                        location.reload();
                    } else {
                        editUserLevelError.textContent = result.message || 'Error updating user level';
                        editUserLevelError.style.display = 'block';
                    }
                })
                .catch(function () {
                    editUserLevelError.textContent = 'Error connecting to the server. Please try again.';
                    editUserLevelError.style.display = 'block';
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

    // ── Rename Module modal ──
    const renameModuleOverlay = document.getElementById('renameModuleOverlay');
    const renameModuleForm = document.getElementById('renameModuleForm');
    const renameModuleError = document.getElementById('renameModuleError');

    function openRenameModuleModal(tableName, currentCaption) {
        if (!renameModuleOverlay) return;
        renameModuleForm.reset();
        renameModuleError.style.display = 'none';
        document.getElementById('renameModuleTableName').value = tableName;
        document.getElementById('renameModuleCaption').value = currentCaption || '';
        renameModuleOverlay.style.display = 'block';
    }

    function closeRenameModuleModal() {
        if (renameModuleOverlay) renameModuleOverlay.style.display = 'none';
    }

    const closeRenameModuleModalBtn = document.getElementById('closeRenameModuleModalBtn');
    const cancelRenameModuleBtn = document.getElementById('cancelRenameModuleBtn');
    if (closeRenameModuleModalBtn) closeRenameModuleModalBtn.addEventListener('click', closeRenameModuleModal);
    if (cancelRenameModuleBtn) cancelRenameModuleBtn.addEventListener('click', closeRenameModuleModal);

    if (renameModuleForm) {
        renameModuleForm.addEventListener('submit', function (e) {
            e.preventDefault();
            renameModuleError.style.display = 'none';

            const payload = {
                table_name: document.getElementById('renameModuleTableName').value,
                caption: document.getElementById('renameModuleCaption').value.trim()
            };

            const submitBtn = document.getElementById('submitRenameModuleBtn');
            submitBtn.disabled = true;

            fetch('/api/user-access/module-caption', {
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
                        ew.showToast('Module renamed successfully');
                        closeRenameModuleModal();
                        location.reload();
                    } else {
                        renameModuleError.textContent = result.message || 'Error renaming module';
                        renameModuleError.style.display = 'block';
                    }
                })
                .catch(function () {
                    renameModuleError.textContent = 'Error connecting to the server. Please try again.';
                    renameModuleError.style.display = 'block';
                })
                .finally(function () {
                    submitBtn.disabled = false;
                });
        });
    }

    // Show the full module list (with rename/remove actions) immediately, even
    // before a user level is selected - only the per-level checkboxes need a level.
    renderPermissionMatrix('');
});
</script>
