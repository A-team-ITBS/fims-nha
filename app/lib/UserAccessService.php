<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * UserAccessService
 * Backs the custom UAC "User Access" page (app/pages/user-access.php):
 * creates users and maintains user-level (role) permissions directly,
 * without going through the PHPMaker-generated Add/Edit pages.
 */
class UserAccessService
{
    public function createUser($data)
    {
        try {
            if (!App\AllowAdd("users")) {
                throw new \InvalidArgumentException("You do not have permission to add users");
            }

            $username = trim($data['username'] ?? '');
            $email = trim($data['email'] ?? '');
            $password = (string)($data['password'] ?? '');
            $employeeId = trim($data['employee_id'] ?? '') ?: null;
            $firstName = trim($data['first_name'] ?? '') ?: null;
            $middleName = trim($data['middle_name'] ?? '') ?: null;
            $lastName = trim($data['last_name'] ?? '') ?: null;
            $suffix = trim($data['suffix'] ?? '') ?: null;
            $mobileNumber = trim($data['mobile_number'] ?? '') ?: null;
            $departmentId = empty($data['department_id']) ? null : $data['department_id'];
            $positionId = empty($data['position_id']) ? null : $data['position_id'];
            $isActive = array_key_exists('is_active', $data) ? App\ConvertToBool($data['is_active']) : true;

            if ($username === '' || $email === '' || $password === '') {
                throw new \InvalidArgumentException("Username, email and password are required");
            }
            if (strlen($password) < 6) {
                throw new \InvalidArgumentException("Password must be at least 6 characters");
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException("Invalid email address");
            }

            $exists = App\ExecuteScalar(
                "SELECT COUNT(*) FROM users WHERE username = " . App\QuotedValue($username, App\DataType::STRING, "DB") .
                " OR email = " . App\QuotedValue($email, App\DataType::STRING, "DB"),
                "DB"
            );
            if ($exists > 0) {
                throw new \InvalidArgumentException("Username or email already exists");
            }

            if ($departmentId !== null) {
                $departmentExists = App\ExecuteScalar(
                    "SELECT COUNT(*) FROM departments WHERE department_id = " . App\QuotedValue($departmentId, App\DataType::NUMBER, "DB"),
                    "DB"
                );
                if (!$departmentExists) {
                    throw new \InvalidArgumentException("Selected department was not found");
                }
            }

            if ($positionId !== null) {
                $positionExists = App\ExecuteScalar(
                    "SELECT COUNT(*) FROM positions WHERE position_id = " . App\QuotedValue($positionId, App\DataType::NUMBER, "DB"),
                    "DB"
                );
                if (!$positionExists) {
                    throw new \InvalidArgumentException("Selected position was not found");
                }
            }

            $passwordHash = App\EncryptPassword($password);

            $nullableString = function ($value) {
                return $value !== null ? App\QuotedValue($value, App\DataType::STRING, "DB") : "NULL";
            };
            $nullableNumber = function ($value) {
                return $value !== null ? App\QuotedValue($value, App\DataType::NUMBER, "DB") : "NULL";
            };

            App\Execute("BEGIN", "DB");
            try {
                $sql = "INSERT INTO users (
                        username, email, password_hash, employee_id,
                        first_name, middle_name, last_name, suffix, mobile_number,
                        is_active, department_id, position_id, date_created
                    ) VALUES (" .
                    App\QuotedValue($username, App\DataType::STRING, "DB") . ", " .
                    App\QuotedValue($email, App\DataType::STRING, "DB") . ", " .
                    App\QuotedValue($passwordHash, App\DataType::STRING, "DB") . ", " .
                    $nullableString($employeeId) . ", " .
                    $nullableString($firstName) . ", " .
                    $nullableString($middleName) . ", " .
                    $nullableString($lastName) . ", " .
                    $nullableString($suffix) . ", " .
                    $nullableString($mobileNumber) . ", " .
                    App\QuotedValue($isActive, App\DataType::BOOLEAN, "DB") . ", " .
                    $nullableNumber($departmentId) . ", " .
                    $nullableNumber($positionId) .
                    ", CURRENT_TIMESTAMP)";
                App\Execute($sql, "DB");
                App\Execute("COMMIT", "DB");
            } catch (\Exception $e) {
                App\Execute("ROLLBACK", "DB");
                throw $e;
            }

            return json_encode(['success' => true, 'message' => 'User created successfully']);
        } catch (\InvalidArgumentException $e) {
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            App\LogError("Error in UserAccessService::createUser: " . $e->getMessage());
            return json_encode(['success' => false, 'message' => 'Internal server error']);
        }
    }

    public function updateUser($userId, $data)
    {
        try {
            if (!App\AllowEdit("users")) {
                throw new \InvalidArgumentException("You do not have permission to edit users");
            }
            if (empty($userId) || !is_numeric($userId)) {
                throw new \InvalidArgumentException("A valid user is required");
            }

            $userExists = App\ExecuteScalar(
                "SELECT COUNT(*) FROM users WHERE user_id = " . App\QuotedValue($userId, App\DataType::NUMBER, "DB"),
                "DB"
            );
            if (!$userExists) {
                throw new \InvalidArgumentException("User not found");
            }

            $username = trim($data['username'] ?? '');
            $email = trim($data['email'] ?? '');
            $password = (string)($data['password'] ?? '');
            $employeeId = trim($data['employee_id'] ?? '') ?: null;
            $firstName = trim($data['first_name'] ?? '') ?: null;
            $middleName = trim($data['middle_name'] ?? '') ?: null;
            $lastName = trim($data['last_name'] ?? '') ?: null;
            $suffix = trim($data['suffix'] ?? '') ?: null;
            $mobileNumber = trim($data['mobile_number'] ?? '') ?: null;
            $departmentId = empty($data['department_id']) ? null : $data['department_id'];
            $positionId = empty($data['position_id']) ? null : $data['position_id'];
            $isActive = array_key_exists('is_active', $data) ? App\ConvertToBool($data['is_active']) : true;

            if ($username === '' || $email === '') {
                throw new \InvalidArgumentException("Username and email are required");
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException("Invalid email address");
            }
            if ($password !== '' && strlen($password) < 6) {
                throw new \InvalidArgumentException("Password must be at least 6 characters");
            }

            $duplicate = App\ExecuteScalar(
                "SELECT COUNT(*) FROM users WHERE (username = " . App\QuotedValue($username, App\DataType::STRING, "DB") .
                " OR email = " . App\QuotedValue($email, App\DataType::STRING, "DB") . ") AND user_id <> " .
                App\QuotedValue($userId, App\DataType::NUMBER, "DB"),
                "DB"
            );
            if ($duplicate > 0) {
                throw new \InvalidArgumentException("Username or email already in use by another user");
            }

            if ($departmentId !== null) {
                $departmentExists = App\ExecuteScalar(
                    "SELECT COUNT(*) FROM departments WHERE department_id = " . App\QuotedValue($departmentId, App\DataType::NUMBER, "DB"),
                    "DB"
                );
                if (!$departmentExists) {
                    throw new \InvalidArgumentException("Selected department was not found");
                }
            }

            if ($positionId !== null) {
                $positionExists = App\ExecuteScalar(
                    "SELECT COUNT(*) FROM positions WHERE position_id = " . App\QuotedValue($positionId, App\DataType::NUMBER, "DB"),
                    "DB"
                );
                if (!$positionExists) {
                    throw new \InvalidArgumentException("Selected position was not found");
                }
            }

            $nullableString = function ($value) {
                return $value !== null ? App\QuotedValue($value, App\DataType::STRING, "DB") : "NULL";
            };
            $nullableNumber = function ($value) {
                return $value !== null ? App\QuotedValue($value, App\DataType::NUMBER, "DB") : "NULL";
            };

            $setClauses = [
                "username = " . App\QuotedValue($username, App\DataType::STRING, "DB"),
                "email = " . App\QuotedValue($email, App\DataType::STRING, "DB"),
                "employee_id = " . $nullableString($employeeId),
                "first_name = " . $nullableString($firstName),
                "middle_name = " . $nullableString($middleName),
                "last_name = " . $nullableString($lastName),
                "suffix = " . $nullableString($suffix),
                "mobile_number = " . $nullableString($mobileNumber),
                "is_active = " . App\QuotedValue($isActive, App\DataType::BOOLEAN, "DB"),
                "department_id = " . $nullableNumber($departmentId),
                "position_id = " . $nullableNumber($positionId),
            ];
            if ($password !== '') {
                $setClauses[] = "password_hash = " . App\QuotedValue(App\EncryptPassword($password), App\DataType::STRING, "DB");
            }

            App\Execute("BEGIN", "DB");
            try {
                $sql = "UPDATE users SET " . implode(", ", $setClauses) .
                    " WHERE user_id = " . App\QuotedValue($userId, App\DataType::NUMBER, "DB");
                App\Execute($sql, "DB");
                App\Execute("COMMIT", "DB");
            } catch (\Exception $e) {
                App\Execute("ROLLBACK", "DB");
                throw $e;
            }

            return json_encode(['success' => true, 'message' => 'User updated successfully']);
        } catch (\InvalidArgumentException $e) {
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            App\LogError("Error in UserAccessService::updateUser: " . $e->getMessage());
            return json_encode(['success' => false, 'message' => 'Internal server error']);
        }
    }

    public function savePermissions($userLevelId, $permissions)
    {
        try {
            if (!App\AllowAdd("user_level_permissions") && !App\AllowEdit("user_level_permissions")) {
                throw new \InvalidArgumentException("You do not have permission to manage permissions");
            }
            if (empty($userLevelId) || !is_numeric($userLevelId)) {
                throw new \InvalidArgumentException("A valid user level is required");
            }
            if (!is_array($permissions) || empty($permissions)) {
                throw new \InvalidArgumentException("No permissions supplied");
            }

            $levelExists = App\ExecuteScalar(
                "SELECT COUNT(*) FROM user_levels WHERE user_level_id = " . App\QuotedValue($userLevelId, App\DataType::NUMBER, "DB"),
                "DB"
            );
            if (!$levelExists) {
                throw new \InvalidArgumentException("User level not found");
            }

            App\Execute("BEGIN", "DB");
            try {
                foreach ($permissions as $perm) {
                    $tableName = trim($perm['table_name'] ?? '');
                    $fullKey = trim($perm['full_key'] ?? '') ?: $tableName;
                    if ($tableName === '') {
                        continue;
                    }
                    // Bitmask per PHPMaker's Allow enum (src/Allow.php):
                    // ADD=1, DELETE=2, EDIT=4, LIST=8, VIEW=32, SEARCH=64, LOOKUP=256, EXPORT=1024
                    $bitmask = (!empty($perm['list']) ? 8 : 0)
                        | (!empty($perm['view']) ? 32 : 0)
                        | (!empty($perm['add']) ? 1 : 0)
                        | (!empty($perm['edit']) ? 4 : 0)
                        | (!empty($perm['delete']) ? 2 : 0)
                        | (!empty($perm['search']) ? 64 : 0)
                        | (!empty($perm['export']) ? 1024 : 0)
                        | (!empty($perm['lookup']) ? 256 : 0);

                    // PHPMaker may have stored this row under the plain name or its own
                    // "{ProjectID}name" key (see models/UserLevelsAdd.php) - find whichever
                    // already exists so we UPDATE it instead of inserting a duplicate.
                    $existingKey = App\ExecuteScalar(
                        "SELECT table_name FROM user_level_permissions WHERE user_level_id = " .
                        App\QuotedValue($userLevelId, App\DataType::NUMBER, "DB") . " AND table_name IN (" .
                        App\QuotedValue($tableName, App\DataType::STRING, "DB") . ", " .
                        App\QuotedValue($fullKey, App\DataType::STRING, "DB") . ") LIMIT 1",
                        "DB"
                    );
                    $keyToWrite = $existingKey ?: $fullKey;

                    $sql = "INSERT INTO user_level_permissions (user_level_id, table_name, permission) VALUES (" .
                        App\QuotedValue($userLevelId, App\DataType::NUMBER, "DB") . ", " .
                        App\QuotedValue($keyToWrite, App\DataType::STRING, "DB") . ", " .
                        App\QuotedValue($bitmask, App\DataType::NUMBER, "DB") . ")" .
                        " ON CONFLICT (user_level_id, table_name) DO UPDATE SET permission = EXCLUDED.permission";
                    App\Execute($sql, "DB");
                }
                App\Execute("COMMIT", "DB");
            } catch (\Exception $e) {
                App\Execute("ROLLBACK", "DB");
                throw $e;
            }

            return json_encode(['success' => true, 'message' => 'Permissions saved successfully']);
        } catch (\InvalidArgumentException $e) {
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            App\LogError("Error in UserAccessService::savePermissions: " . $e->getMessage());
            return json_encode(['success' => false, 'message' => 'Internal server error']);
        }
    }

    public function syncPermissionModules()
    {
        try {
            if (!App\AllowAdd("user_level_permissions") && !App\AllowEdit("user_level_permissions")) {
                throw new \InvalidArgumentException("You do not have permission to manage permissions");
            }

            $registryTables = $GLOBALS["USER_LEVEL_TABLES"] ?? [];
            if (empty($registryTables)) {
                throw new \InvalidArgumentException("PHPMaker table registry is unavailable");
            }

            $userLevels = App\ExecuteRowsAssociative("SELECT user_level_id FROM user_levels", "DB");
            $existingRows = App\ExecuteRowsAssociative("SELECT user_level_id, table_name FROM user_level_permissions", "DB");

            $existingKeys = [];
            foreach ($existingRows as $row) {
                $existingKeys[$row['user_level_id'] . '|' . $row['table_name']] = true;
            }

            $inserted = 0;
            App\Execute("BEGIN", "DB");
            try {
                foreach ($userLevels as $level) {
                    $levelId = $level['user_level_id'];
                    foreach ($registryTables as $table) {
                        $plainKey = $table[0];
                        $fullKey = $table[4] . $table[0];
                        $alreadyExists = isset($existingKeys[$levelId . '|' . $plainKey])
                            || isset($existingKeys[$levelId . '|' . $fullKey]);
                        if ($alreadyExists) {
                            continue;
                        }

                        $sql = "INSERT INTO user_level_permissions (user_level_id, table_name, permission) VALUES (" .
                            App\QuotedValue($levelId, App\DataType::NUMBER, "DB") . ", " .
                            App\QuotedValue($fullKey, App\DataType::STRING, "DB") . ", 0)" .
                            " ON CONFLICT (user_level_id, table_name) DO NOTHING";
                        App\Execute($sql, "DB");
                        $inserted++;
                    }
                }
                App\Execute("COMMIT", "DB");
            } catch (\Exception $e) {
                App\Execute("ROLLBACK", "DB");
                throw $e;
            }

            $message = $inserted > 0
                ? "Sync complete. {$inserted} missing module permission row(s) added."
                : "Already in sync — no missing modules found.";
            return json_encode(['success' => true, 'message' => $message, 'inserted' => $inserted]);
        } catch (\InvalidArgumentException $e) {
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            App\LogError("Error in UserAccessService::syncPermissionModules: " . $e->getMessage());
            return json_encode(['success' => false, 'message' => 'Internal server error']);
        }
    }

    public function createUserLevel($data)
    {
        try {
            if (!App\AllowAdd("user_levels")) {
                throw new \InvalidArgumentException("You do not have permission to create user levels");
            }

            $name = trim($data['name'] ?? '');
            $description = trim($data['description'] ?? '') ?: null;
            $systemId = empty($data['system_id']) ? null : $data['system_id'];

            if ($name === '') {
                throw new \InvalidArgumentException("Name is required");
            }
            if (strlen($name) > 50) {
                throw new \InvalidArgumentException("Name must be 50 characters or fewer");
            }

            $exists = App\ExecuteScalar(
                "SELECT COUNT(*) FROM user_levels WHERE LOWER(name) = LOWER(" .
                App\QuotedValue($name, App\DataType::STRING, "DB") . ")",
                "DB"
            );
            if ($exists > 0) {
                throw new \InvalidArgumentException("A user level with this name already exists");
            }

            if ($systemId !== null) {
                $systemExists = App\ExecuteScalar(
                    "SELECT COUNT(*) FROM systems WHERE system_id = " . App\QuotedValue($systemId, App\DataType::NUMBER, "DB"),
                    "DB"
                );
                if (!$systemExists) {
                    throw new \InvalidArgumentException("Selected system was not found");
                }
            }

            // user_levels has no ID sequence (PHPMaker treats -2/-1/0 as reserved:
            // Anonymous/Administrator/Default) — pick the next free positive ID ourselves.
            $newLevelId = (int)App\ExecuteScalar(
                "SELECT GREATEST(1, COALESCE(MAX(user_level_id), 0) + 1) FROM user_levels",
                "DB"
            );

            App\Execute("BEGIN", "DB");
            try {
                $sql = "INSERT INTO user_levels (user_level_id, name, description, system_id) VALUES (" .
                    App\QuotedValue($newLevelId, App\DataType::NUMBER, "DB") . ", " .
                    App\QuotedValue($name, App\DataType::STRING, "DB") . ", " .
                    ($description !== null ? App\QuotedValue($description, App\DataType::STRING, "DB") : "NULL") . ", " .
                    ($systemId !== null ? App\QuotedValue($systemId, App\DataType::NUMBER, "DB") : "NULL") .
                    ")";
                App\Execute($sql, "DB");

                // Seed a zero-permission row for every known module, mirroring PHPMaker's
                // own UserLevelsAdd behavior (models/UserLevelsAdd.php) so the new role
                // shows up fully in the Permissions Management matrix ready to configure.
                $registryTables = $GLOBALS["USER_LEVEL_TABLES"] ?? [];
                foreach ($registryTables as $table) {
                    $fullKey = $table[4] . $table[0];
                    $sql = "INSERT INTO user_level_permissions (user_level_id, table_name, permission) VALUES (" .
                        App\QuotedValue($newLevelId, App\DataType::NUMBER, "DB") . ", " .
                        App\QuotedValue($fullKey, App\DataType::STRING, "DB") . ", 0)" .
                        " ON CONFLICT (user_level_id, table_name) DO NOTHING";
                    App\Execute($sql, "DB");
                }

                App\Execute("COMMIT", "DB");
            } catch (\Exception $e) {
                App\Execute("ROLLBACK", "DB");
                throw $e;
            }

            return json_encode(['success' => true, 'message' => 'User level created successfully', 'user_level_id' => $newLevelId]);
        } catch (\InvalidArgumentException $e) {
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            App\LogError("Error in UserAccessService::createUserLevel: " . $e->getMessage());
            return json_encode(['success' => false, 'message' => 'Internal server error']);
        }
    }

    public function assignUserLevel($data)
    {
        try {
            if (!App\AllowAdd("user_level_assignments")) {
                throw new \InvalidArgumentException("You do not have permission to assign access");
            }

            $userId = $data['user_id'] ?? null;
            $userLevelId = $data['user_level_id'] ?? null;
            $systemId = empty($data['system_id']) ? null : $data['system_id'];

            if (empty($userId) || !is_numeric($userId)) {
                throw new \InvalidArgumentException("A valid user is required");
            }
            if ($userLevelId === null || $userLevelId === '' || !is_numeric($userLevelId)) {
                throw new \InvalidArgumentException("A valid user level is required");
            }

            $userExists = App\ExecuteScalar(
                "SELECT COUNT(*) FROM users WHERE user_id = " . App\QuotedValue($userId, App\DataType::NUMBER, "DB"),
                "DB"
            );
            if (!$userExists) {
                throw new \InvalidArgumentException("User not found");
            }

            $levelExists = App\ExecuteScalar(
                "SELECT COUNT(*) FROM user_levels WHERE user_level_id = " . App\QuotedValue($userLevelId, App\DataType::NUMBER, "DB"),
                "DB"
            );
            if (!$levelExists) {
                throw new \InvalidArgumentException("User level not found");
            }

            $alreadyAssigned = App\ExecuteScalar(
                "SELECT COUNT(*) FROM user_level_assignments WHERE user_id = " . App\QuotedValue($userId, App\DataType::NUMBER, "DB") .
                " AND user_level_id = " . App\QuotedValue($userLevelId, App\DataType::NUMBER, "DB"),
                "DB"
            );
            if ($alreadyAssigned > 0) {
                throw new \InvalidArgumentException("This user already has that role assigned");
            }

            $assignedBy = App\CurrentUserID() ?: null;

            $sql = "INSERT INTO user_level_assignments (system_id, user_id, user_level_id, assigned_by, created_at) VALUES (" .
                ($systemId !== null ? App\QuotedValue($systemId, App\DataType::NUMBER, "DB") : "NULL") . ", " .
                App\QuotedValue($userId, App\DataType::NUMBER, "DB") . ", " .
                App\QuotedValue($userLevelId, App\DataType::NUMBER, "DB") . ", " .
                ($assignedBy !== null ? App\QuotedValue($assignedBy, App\DataType::NUMBER, "DB") : "NULL") .
                ", CURRENT_TIMESTAMP)";
            App\Execute($sql, "DB");

            return json_encode(['success' => true, 'message' => 'Access assigned successfully']);
        } catch (\InvalidArgumentException $e) {
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            App\LogError("Error in UserAccessService::assignUserLevel: " . $e->getMessage());
            return json_encode(['success' => false, 'message' => 'Internal server error']);
        }
    }
}
