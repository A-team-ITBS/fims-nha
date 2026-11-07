<?php

namespace PHPMaker2024\FIMS;

/**
 * Session handler
 */
class SessionHandler
{

    public function __invoke()
    {
        if (ob_get_length()) {
            ob_end_clean();
        }
        $csrf = Container("app.csrf");
        $token = $csrf->generateToken();
        WriteJson([
            $csrf->getTokenNameKey() => $csrf->getTokenName(),
            $csrf->getTokenValueKey() => $csrf->getTokenValue(),
            "JWT" => GetJwtToken()
        ]);
        return true;
    }
}
