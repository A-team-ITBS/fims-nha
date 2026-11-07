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
        u.first_name,
        u.last_name,
        u.is_active,
        u.last_login,
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

// ── Permission matrix per user level (bitmask: 1=View, 2=Add, 4=Edit, 8=Delete) ──
$permissionRows = ExecuteRowsAssociative("
    SELECT user_level_id, table_name, permission
    FROM user_level_permissions
    ORDER BY user_level_id, table_name
", "DB");

$permissionsByLevel = [];
foreach ($permissionRows as $row) {
    $permission = (int)$row['permission'];
    $permissionsByLevel[$row['user_level_id']][] = [
        'table_name' => $row['table_name'],
        'view' => ($permission & 1) !== 0,
        'add' => ($permission & 2) !== 0,
        'edit' => ($permission & 4) !== 0,
        'delete' => ($permission & 8) !== 0,
    ];
}

// ── Full module registry (every table_name ever assigned a permission) ──
$allModules = ExecuteRowsAssociative("SELECT DISTINCT table_name FROM user_level_permissions ORDER BY table_name", "DB");

// ── Departments (for the Add User form) ──
$departments = ExecuteRowsAssociative("SELECT department_id, department_name FROM departments WHERE is_active = TRUE ORDER BY department_name", "DB");

$assignmentsByUserJson = json_encode($assignmentsByUser);
$permissionsByLevelJson = json_encode($permissionsByLevel);
$allModulesJson = json_encode(array_column($allModules, 'table_name'));

// ── Permission checks ──
$canAddUser = AllowAdd("users");
$canEditUser = AllowEdit("users");
$canManageAssignments = AllowAdd("user_level_assignments") && AllowDelete("user_level_assignments");
$canManagePermissions = AllowAdd("user_level_permissions") || AllowEdit("user_level_permissions");

// ── URLs ──
$editUserBaseUrl = GetUrl("UsersEdit");
$manageLevelsUrl = GetUrl("UserLevelsList");
$manageSystemsUrl = GetUrl("SystemsList");
$addAssignmentBaseUrl = GetUrl("UserLevelAssignmentsAdd");
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
                                <a href="<?php echo $editUserBaseUrl . '/' . $user['user_id']; ?>" class="btn btn-default btn-sm" title="Edit User">
                                    <i class="fas fa-edit"></i>
                                </a>
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
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-user-lock mr-1"></i> Role Access</h3>
                </div>
                <div class="card-body">
                    <p>Selected user: <strong id="selectedUserLabel">— none —</strong></p>

                    <h6>Current Assignments</h6>
                    <ul class="list-group mb-3" id="currentAssignmentsList">
                        <li class="list-group-item text-muted">Select a user to view assignments.</li>
                    </ul>

                    <h6>Assign New Access</h6>
                    <form id="assignAccessForm" method="get" action="<?php echo $addAssignmentBaseUrl; ?>">
                        <input type="hidden" name="user_id" id="assignUserId">
                        <input type="hidden" name="system_id" id="assignSystemId">
                        <div class="form-group">
                            <label>User Level (Role)</label>
                            <select class="form-control" id="assignLevelSelect" name="user_level_id">
                                <option value="">Select role...</option>
                                <?php foreach ($userLevels as $level): ?>
                                <option value="<?php echo $level['user_level_id']; ?>" data-system-id="<?php echo $level['system_id']; ?>">
                                    <?php echo HtmlEncode($level['name'] . ' (' . $level['system_name'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" id="assignAccessBtn" disabled>
                            <i class="fas fa-plus mr-1"></i> Assign Access
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Permission matrix -->
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-table mr-1"></i> Permission Matrix</h3>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-sm table-striped text-nowrap">
                        <thead>
                            <tr>
                                <th>Table / Module</th>
                                <th class="text-center">View</th>
                                <th class="text-center">Add</th>
                                <th class="text-center">Edit</th>
                                <th class="text-center">Delete</th>
                            </tr>
                        </thead>
                        <tbody id="permissionMatrixBody">
                            <tr><td colspan="5" class="text-muted">Select a user level to view / edit its permissions.</td></tr>
                        </tbody>
                    </table>
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

    <!-- Quick links -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Quick Links</h3>
        </div>
        <div class="card-body d-flex flex-wrap" style="gap: .5rem;">
            <a href="<?php echo $manageLevelsUrl; ?>" class="btn btn-info">
                <i class="fas fa-users-cog mr-2"></i> Manage User Levels
            </a>
            <a href="<?php echo $manageSystemsUrl; ?>" class="btn btn-warning">
                <i class="fas fa-cogs mr-2"></i> Manage Systems
            </a>
        </div>
    </div>
</div>

<?php if ($canAddUser): ?>
<!-- Add User modal (self-contained, no Bootstrap JS dependency) -->
<div id="addUserOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1050;">
    <div style="max-width:500px; margin:5% auto; background:#fff; border-radius:4px; overflow:hidden;">
        <div class="card mb-0">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-plus mr-1"></i> Add User</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" id="closeAddUserModalBtn"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <form id="addUserForm">
                <div class="card-body">
                    <div id="addUserError" class="alert alert-danger" style="display:none;"></div>
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" class="form-control" name="username" id="addUserUsername" required maxlength="50">
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" class="form-control" name="email" id="addUserEmail" required maxlength="100">
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>First Name *</label>
                            <input type="text" class="form-control" name="first_name" id="addUserFirstName" required maxlength="50">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Last Name *</label>
                            <input type="text" class="form-control" name="last_name" id="addUserLastName" required maxlength="50">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <select class="form-control" name="department_id" id="addUserDepartment">
                            <option value="">— none —</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>"><?php echo HtmlEncode($dept['department_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Password *</label>
                            <input type="password" class="form-control" name="password" id="addUserPassword" required minlength="8">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Confirm Password *</label>
                            <input type="password" class="form-control" id="addUserPasswordConfirm" required minlength="8">
                        </div>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" id="addUserIsActive" checked value="1">
                        <label class="form-check-label" for="addUserIsActive">Active</label>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <button type="button" class="btn btn-default" id="cancelAddUserBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitAddUserBtn">
                        <i class="fas fa-save mr-1"></i> Create User
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

    function renderPermissionMatrix(levelId) {
        if (!permissionMatrixBody) return;
        if (savePermissionsBtn) savePermissionsBtn.disabled = !levelId;

        if (!levelId || allModules.length === 0) {
            permissionMatrixBody.innerHTML = '<tr><td colspan="5" class="text-muted">Select a user level to view / edit its permissions.</td></tr>';
            return;
        }

        const existing = {};
        (permissionsByLevel[levelId] || []).forEach(function (r) { existing[r.table_name] = r; });

        const checkbox = function (module, perm, checked) {
            return '<input type="checkbox" class="perm-' + perm + '" ' + (checked ? 'checked' : '') +
                (canManagePermissions ? '' : ' disabled') + '>';
        };

        permissionMatrixBody.innerHTML = allModules.map(function (module) {
            const r = existing[module] || { view: false, add: false, edit: false, delete: false };
            return '<tr data-table="' + module + '"><td>' + module + '</td>' +
                '<td class="text-center">' + checkbox(module, 'view', r.view) + '</td>' +
                '<td class="text-center">' + checkbox(module, 'add', r.add) + '</td>' +
                '<td class="text-center">' + checkbox(module, 'edit', r.edit) + '</td>' +
                '<td class="text-center">' + checkbox(module, 'delete', r.delete) + '</td></tr>';
        }).join('');
    }

    if (savePermissionsBtn) {
        savePermissionsBtn.addEventListener('click', function () {
            const levelId = assignLevelSelect ? assignLevelSelect.value : '';
            if (!levelId) {
                ew.alert('Select a user level first.');
                return;
            }
            const permissions = Array.from(permissionMatrixBody.querySelectorAll('tr[data-table]')).map(function (tr) {
                return {
                    table_name: tr.dataset.table,
                    view: tr.querySelector('.perm-view').checked,
                    add: tr.querySelector('.perm-add').checked,
                    edit: tr.querySelector('.perm-edit').checked,
                    delete: tr.querySelector('.perm-delete').checked
                };
            });

            savePermissionsBtn.disabled = true;
            fetch('/user-access/permissions', {
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

    if (addUserForm) {
        addUserForm.addEventListener('submit', function (e) {
            e.preventDefault();
            addUserError.style.display = 'none';

            const password = document.getElementById('addUserPassword').value;
            const passwordConfirm = document.getElementById('addUserPasswordConfirm').value;
            if (password !== passwordConfirm) {
                addUserError.textContent = 'Passwords do not match.';
                addUserError.style.display = 'block';
                return;
            }

            const payload = {
                username: document.getElementById('addUserUsername').value.trim(),
                email: document.getElementById('addUserEmail').value.trim(),
                first_name: document.getElementById('addUserFirstName').value.trim(),
                last_name: document.getElementById('addUserLastName').value.trim(),
                department_id: document.getElementById('addUserDepartment').value || null,
                password: password,
                is_active: document.getElementById('addUserIsActive').checked
            };

            const submitBtn = document.getElementById('submitAddUserBtn');
            submitBtn.disabled = true;

            fetch('/user-access/users', {
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
});
</script>
