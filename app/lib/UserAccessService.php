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
            $firstName = trim($data['first_name'] ?? '');
            $lastName = trim($data['last_name'] ?? '');
            $departmentId = $data['department_id'] ?? null;
            $isActive = array_key_exists('is_active', $data) ? App\ConvertToBool($data['is_active']) : true;

            if ($username === '' || $email === '' || $password === '' || $firstName === '' || $lastName === '') {
                throw new \InvalidArgumentException("Username, email, password, first name and last name are required");
            }
            if (strlen($password) < 8) {
                throw new \InvalidArgumentException("Password must be at least 8 characters");
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

            if ($departmentId !== null && $departmentId !== '') {
                $departmentExists = App\ExecuteScalar(
                    "SELECT COUNT(*) FROM departments WHERE department_id = " . App\QuotedValue($departmentId, App\DataType::NUMBER, "DB"),
                    "DB"
                );
                if (!$departmentExists) {
                    throw new \InvalidArgumentException("Selected department was not found");
                }
            } else {
                $departmentId = null;
            }

            $passwordHash = App\EncryptPassword($password);

            App\Execute("BEGIN", "DB");
            try {
                $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, is_active, department_id, date_created) VALUES (" .
                    App\QuotedValue($username, App\DataType::STRING, "DB") . ", " .
                    App\QuotedValue($email, App\DataType::STRING, "DB") . ", " .
                    App\QuotedValue($passwordHash, App\DataType::STRING, "DB") . ", " .
                    App\QuotedValue($firstName, App\DataType::STRING, "DB") . ", " .
                    App\QuotedValue($lastName, App\DataType::STRING, "DB") . ", " .
                    App\QuotedValue($isActive, App\DataType::BOOLEAN, "DB") . ", " .
                    ($departmentId !== null ? App\QuotedValue($departmentId, App\DataType::NUMBER, "DB") : "NULL") .
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
                    if ($tableName === '') {
                        continue;
                    }
                    $bitmask = (!empty($perm['view']) ? 1 : 0)
                        | (!empty($perm['add']) ? 2 : 0)
                        | (!empty($perm['edit']) ? 4 : 0)
                        | (!empty($perm['delete']) ? 8 : 0);

                    $sql = "INSERT INTO user_level_permissions (user_level_id, table_name, permission) VALUES (" .
                        App\QuotedValue($userLevelId, App\DataType::NUMBER, "DB") . ", " .
                        App\QuotedValue($tableName, App\DataType::STRING, "DB") . ", " .
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
}
