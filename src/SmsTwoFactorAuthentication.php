<?php

namespace PHPMaker2024\FIMS;

/**
 * Two Factor Authentication class (SMS Authentication only)
 */
class SmsTwoFactorAuthentication extends AbstractTwoFactorAuthentication implements TwoFactorAuthenticationInterface
{
    /**
     * Send one time password
     *
     * @param string $usr User
     */
    public static function sendOneTimePassword($usr, $account = null)
    {
        global $Language;

        // Get mobile number
        $oldAccount = self::getAccount($usr);
        $mobileNumber = $account ?? $oldAccount;
        if (EmptyValue($mobileNumber)) { // Check if empty, cannot use CheckPhone due to possible different phone number formats
            return str_replace(["%a", "%u"], [$mobileNumber, $usr], $Language->phrase("SendOTPSkipped")); // Return error message
        }

        // Create OTP and save in user profile
        $profile = Profile();
        $profile->setUserName($usr)->loadFromStorage();
        $secret = $profile->getUserSecret(); // Get user secret
        $code = Random(Config("TWO_FACTOR_AUTHENTICATION_PASS_CODE_LENGTH")); // Generate OTP
        $encryptedCode = Encrypt($code, $secret); // Encrypt OTP
        $otpAccount = $oldAccount == $mobileNumber && !Config("OTP_ONLY") ? "" : $mobileNumber; // Save mobile number if changed / OTP only
        $profile->setOneTimePassword($otpAccount, $encryptedCode);

        // Send OTP
        $smsClass = Config("SMS_CLASS");
        $rc = new \ReflectionClass($smsClass);
        if ($rc->isAbstract()) {
            throw new \Exception("Make sure you have enabled an extension for sending SMS messages.");
        }
        $sms = new $smsClass();
        $sms->load(Config("SMS_ONE_TIME_PASSWORD_TEMPLATE"), data: [
            "Code" => $code,
            "Account" => PartialHideValue($usr)
        ]);
        $sms->Recipient = FormatPhoneNumber($mobileNumber);

        // Call Otp_Sending event
        if (Otp_Sending($usr, $sms)) {
            $res = $sms->send();
            return $res ? $res : $sms->SendErrDescription; // Return success / error description
        } else {
            return $sms->SendErrDescription ?: $Language->phrase("SendOTPCancelled"); // User cancel
        }
    }

    /**
     * Get account (mobile number)
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
            return (IsSysAdmin() ? $row[SYS_ADMIN_PHONE_NUMBER] : $row[Config("USER_PHONE_FIELD_NAME")]) ?? "";
        }

        // Check if phone field name is defined
        if (EmptyValue(Config("USER_PHONE_FIELD_NAME"))) {
            return "";
        }

        // Load phone number
        return FindUserByUserName($usr)?->get(Config("USER_PHONE_FIELD_NAME")) ?? "";
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
     * Show User Phone
     *
     * @return void
     */
    public function show()
    {
        $user = CurrentUserName(); // Must be current user
        $profile = Container("user.profile");
        $profile->setUserName($user)->loadFromStorage();
        $mobileNumber = self::getAccount($user); // Get mobile number
        WriteJson(["account" => $mobileNumber, "success" => true, "verified" => $profile->hasUserSecret(true)]);
    }
}
