<?php
// Function Explanation
    // This function generates the HMAC-SHA256 signature for a time-limited
    // media capability URL. The signature binds the media uuid, the action and
    // the expiry timestamp to the signing secret.
    // The signature is appended to the media URL that is sent to the provider;
    // message_media.php verifies it on unauthenticated (external) fetches, so
    // the link itself is the authorization and a leaked link stops working
    // when the expiry passes.

    // Example usage:
    //      $secret = message_media_secret();
    //      $exp    = time() + 86400;
    //      $sig    = message_media_sign($uuid, 'download', $exp, $secret);
    //      $url    = 'https://example.com/app/messages/message_media.php?id='
    //              . $uuid . '&action=download&exp=' . $exp . '&sig=' . $sig;

function message_media_sign($uuid, $action, $exp, $secret = '') {
    $uuid = (string) $uuid;
    $action = (string) $action;
    $exp = (int) $exp;
    $secret = (string) $secret;

    //no secret configured, nothing to sign
    if ($secret === '') {
        return '';
    }

    return hash_hmac('sha256', $uuid.'|'.$action.'|'.$exp, $secret);
}

// Function Explanation
    // This function returns the secret used to sign the time-limited media
    // capability URLs. The global configuration value message.media.secret
    // takes precedence when set. Otherwise the per-installation default
    // setting message/media_secret is used; a unique value for it is
    // generated on install by app_defaults.php so that every installation
    // signs with its own secret.
    // An empty string is returned when no secret is available.

function message_media_secret() {
    static $secret = null;

    //resolve the secret only once per request
    if ($secret !== null) {
        return $secret;
    }

    //a global override from the configuration file takes precedence
    $secret = '';
    global $config;
    if (is_object($config)) {
        $secret = (string) $config->get('message.media.secret', '');
    }

    //fall back to the per-installation default setting
    if ($secret === '') {
        global $settings;
        if (is_object($settings)) {
            $value = $settings->get('message', 'media_secret');
            if (is_scalar($value) && $value !== '') {
                $secret = (string) $value;
            }
        }
    }

    return $secret;
}
