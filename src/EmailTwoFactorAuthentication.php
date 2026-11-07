<?php

namespace PHPMaker2024\FIMS;

/**
 * Two Factor Authentication class (Email Authentication only)
 */
class EmailTwoFactorAuthentication extends AbstractTwoFactorAuthentication implements TwoFactorAuthenticationInterface
{
    /**
     * Send one time password
     *
     * @param string $usr User
     */
    public static function sendOneTimePassword($usr, $account = null)
    {
        global $Language;

        // Get email address
        $oldAccount = self::getAccount($usr);
        $emailAddress = $account ?? $oldAccount;
        if (EmptyValue($emailAddress) || !CheckEmail($emailAddress)) { // Check if valid email address
            return str_replace(["%a", "%u"], [$emailAddress, $usr], $Language->phrase("SendOTPSkipped")); // Return error message
        }

        // Create OTP and save in user profile
        $profile = Profile();
        $profile->setUserName($usr)->loadFromStorage();
        $secret = $profile->getUserSecret(); // Get user secret
        $code = Random(Config("TWO_FACTOR_AUTHENTICATION_PASS_CODE_LENGTH")); // Generate OTP
        $encryptedCode = Encrypt($code, $secret); // Encrypt OTP
        $otpAccount = $oldAccount == $emailAddress && !Config("OTP_ONLY") ? "" : $emailAddress; // Save email address if changed / OTP only
        $profile->setOneTimePassword($otpAccount, $encryptedCode);

        // Send OTP email
        $email = new Email();
        $email->load(Config("EMAIL_ONE_TIME_PASSWORD_TEMPLATE"), data: [
            "From" => Config("SENDER_EMAIL"), // Replace Sender
            "To" => $emailAddress, // Replace Recipient
            "Code" => $code,
            "Account" => PartialHideValue($usr)
        ]);

        // Call Otp_Sending event
        if (Otp_Sending($usr, $email)) {
            $res = $email->send();
            return $res ? $res : $email->SendErrDescription; // Return success / error description
        } else {
            return $email->SendErrDescription ?: $Language->phrase("SendOTPCancelled"); // User cancel
        }
    }

    /**
     * Get account (email address)
     *
     * @param string $usr User
     */
    public static function getAccount($usr): string
    {
        // Check if empty user
        if (EmptyValue($usr)) {
            return "";
        }

        // Load from session for system admin / register
        if (is_array(Session(SESSION_USER_PROFILE_RECORD))) {
            $row = Session(SESSION_USER_PROFILE_RECORD);
            return (IsSysAdmin() ? $row[SYS_ADMIN_EMAIL_ADDRESS] : $row[Config("USER_EMAIL_FIELD_NAME")]) ?? "";
        }

        // Check email field name not defined
        if (EmptyValue(Config("USER_EMAIL_FIELD_NAME"))) {
            return "";
        }

        // Load email address
        return FindUserByUserName($usr)?->get(Config("USER_EMAIL_FIELD_NAME")) ?? "";
    }

    /**
     * Check code
     *
     * @param string $otp One time password
     * @param string $code Code
     */
    public static function checkCode($otp, $code): bool
    {
        return $otp == $code;
    }

    /**
     * Generate secret
     */
    public static function generateSecret(): string
    {
        return Random(); // Generate a radom number for secret, used for encrypting OTP
    }

    /**
     * Show User Email
     *
     * @return void
     */
    public function show()
    {
        $user = CurrentUserName(); // Must be current user
        $profile = Container("user.profile");
        $profile->setUserName($user)->loadFromStorage();
        $emailAddress = self::getAccount($user); // Get email address
        WriteJson(["account" => $emailAddress, "success" => true, "verified" => $profile->hasUserSecret(true)]);
    }
}
