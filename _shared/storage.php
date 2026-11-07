<?php

/**
 * Centralized S3 / MinIO storage setup for NHA FIMS.
 *
 * Include this from each module's app/global.php.
 * Requires:
 *   - _shared/load-env.php already included (provides env())
 *   - vendor/autoload.php already included (provides AWS SDK)
 */

if (defined('FIMS_STORAGE_LOADED')) {
    return;
}
define('FIMS_STORAGE_LOADED', true);

use Aws\S3\S3Client;

$s3client = new S3Client([
    'version'                 => 'latest',
    'region'                  => env('DO_SPACES_REGION', 'sgp1'),
    'endpoint'                => env('DO_SPACES_ENDPOINT', 'https://sgp1.digitaloceanspaces.com'),
    'use_path_style_endpoint' => true,
    'credentials' => [
        'key'    => env('DO_SPACES_KEY', ''),
        'secret' => env('DO_SPACES_SECRET', ''),
    ],
    'http' => ['verify' => false],
]);

$s3client->registerStreamWrapper();

if (!function_exists('GetSignedUrl')) {
    /**
     * Generate a pre-signed URL for a private S3 / MinIO object.
     *
     * @param string $objectKey  S3 object key (e.g. "NHA-FIMS/docs/report.pdf")
     * @param string $expiration Relative expiry accepted by strtotime() (default: +48 hours)
     * @return string            The pre-signed URL
     */
    function GetSignedUrl(string $objectKey, string $expiration = '+48 hours'): string
    {
        global $s3client;
        $cmd     = $s3client->getCommand('GetObject', [
            'Bucket' => env('DO_SPACES_BUCKET', ''),
            'Key'    => $objectKey,
        ]);
        $request = $s3client->createPresignedRequest($cmd, $expiration);
        return (string) $request->getUri();
    }
}
