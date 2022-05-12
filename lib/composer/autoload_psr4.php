<?php

// autoload_psr4.php @generated by Composer

$vendorDir = dirname(dirname(__FILE__));
$baseDir = dirname($vendorDir);

return array(
    'Jose\\Component\\Signature\\Algorithm\\' => array($vendorDir . '/web-token/jwt-signature-algorithm-ecdsa'),
    'Jose\\Component\\Signature\\' => array($vendorDir . '/web-token/jwt-signature'),
    'Jose\\Component\\Core\\' => array($vendorDir . '/web-token/jwt-core'),
    'Jose\\' => array($baseDir . '/src'),
    'FG\\' => array($vendorDir . '/fgrosse/phpasn1/lib'),
    'Base64Url\\' => array($vendorDir . '/spomky-labs/base64url/src'),
);