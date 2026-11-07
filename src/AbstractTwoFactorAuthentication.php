<?php

namespace PHPMaker2024\FIMS;

/**
 * Abstract Two Factor Authentication class
 */
abstract class AbstractTwoFactorAuthentication implements TwoFactorAuthenticationInterface
{
    /**
     * Check code
     *
     * @param string $secret Secret
     * @param string $code Code
     */
    abstract public static function checkCode($secret, $code): bool;

    /**
     * Generate secret
     */
    abstract public static function generateSecret(): string;

    /**
     * Show (API action)
     *
     * @return void
     */
    abstract public function show();

    /**
     * Generate backup codes
     */
    public static function generateBackupCodes(): array
    {
        $length = Config("TWO_FACTOR_AUTHENTICATION_BACKUP_CODE_LENGTH");
        $count = Config("TWO_FACTOR_AUTHENTICATION_BACKUP_CODE_COUNT");
        $ar = [];
        for ($i = 0; $i < $count; $i++) {
            $ar[] = Random($length);
        }
        return $ar;
    }

    /**
     * Get backup codes (API action)
     *
     * @return void
     */
    public function getBackupCodes()
    {
        $user = CurrentUserName(); // Must be current user
        $profile = Container("user.profile");
        $profile->setUserName($user)->loadFromStorage();
        $codes = $profile->getBackupCodes();
        WriteJson(["codes" => $codes, "success" => is_array($codes)]);
    }

    /**
     * Get new backup codes (API action)
     *
     * @return void
     */
    public function getNewBackupCodes()
    {
        $user = CurrentUserName(); // Must be current user
        $profile = Container("user.profile");
        $profile->setUserName($user)->loadFromStorage();
        $codes = $profile->getNewBackupCodes();
        WriteJson(["codes" => $codes, "success" => is_array($codes)]);
    }

    /**
     * Verify (API action)
     *
     * @param string $code
     * @return void
     */
    public function verify($code)
    {
        $user = CurrentUserName(); // Must be current user
        $profile = Container("user.profile");
        $profile->setUserName($user)->loadFromStorage();
        if ($code === null) { // Verify if user has secret only
            if ($profile->hasUserSecret(true)) {
                WriteJson(["success" => true]);
                return;
            }
        } else { // Verify user code
            if ($profile->hasUserSecret()) { // Verified, just check code
                WriteJson(["success" => $profile->verify2FACode($code)]);
                return;
            }
        }
        WriteJson(["success" => false]);
    }

    /**
     * Reset (API action)
     *
     * @param string $user
     * @return void
     */
    public function reset($user)
    {
        $user = IsSysAdmin() ? $user : (Config("FORCE_TWO_FACTOR_AUTHENTICATION") ? null : CurrentUserName());
        if ($user) {
            $profile = Container("user.profile");
            $profile->setUserName($user)->loadFromStorage();
            if ($profile->hasUserSecret()) {
                $profile->resetUserSecret();
                WriteJson(["success" => true]);
                return;
            }
        }
        WriteJson(["success" => false]);
    }
}
