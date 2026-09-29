<?php
/**
 * ============================================
 * INDSAC LICENSE KEY GENERATOR
 * ============================================
 * Run this ONCE on the INDSAC server to generate
 * the RSA key pair used for license signing.
 * 
 * Usage: php generate_keys.php
 * 
 * Output:
 *   - private_key.pem (KEEP SECRET - stays on server)
 *   - public_key.pem  (distribute with Eyesense app)
 */

$KEY_BITS = 4096;  // Strong RSA key size
$OUTPUT_DIR = __DIR__;

echo "============================================\n";
echo "  INDSAC License Key Generator\n";
echo "============================================\n\n";

// Generate RSA key pair
// Auto-detect openssl.cnf for Windows PHP installations
$opensslCnf = (defined('DB_CFG_OPENSSL_CONF') && DB_CFG_OPENSSL_CONF) ? DB_CFG_OPENSSL_CONF : getenv('OPENSSL_CONF');
if (!$opensslCnf || !file_exists($opensslCnf)) {
    $phpDir = dirname(PHP_BINARY);
    $candidates = [
        $phpDir . '/extras/ssl/openssl.cnf',
        $phpDir . '/openssl.cnf',
    ];
    foreach ($candidates as $path) {
        if (file_exists($path)) {
            $opensslCnf = $path;
            break;
        }
    }
}

$config = [
    "private_key_bits" => $KEY_BITS,
    "private_key_type" => OPENSSL_KEYTYPE_RSA,
    "digest_alg" => "sha256",
];

if ($opensslCnf) {
    $config['config'] = $opensslCnf;
    echo "Using OpenSSL config: $opensslCnf\n\n";
}

$keyPair = openssl_pkey_new($config);
if (!$keyPair) {
    die("❌ Failed to generate key pair: " . openssl_error_string() . "\n");
}

// Extract private key (pass config so OpenSSL finds openssl.cnf on Windows)
openssl_pkey_export($keyPair, $privateKeyPEM, null, $config);

// Extract public key
$pubKeyDetails = openssl_pkey_get_details($keyPair);
$publicKeyPEM = $pubKeyDetails['key'];

// Save private key
$privatePath = $OUTPUT_DIR . '/private_key.pem';
file_put_contents($privatePath, $privateKeyPEM);
echo "✅ Private key saved: $privatePath\n";
echo "   ⚠️  KEEP THIS SECRET. DO NOT distribute.\n\n";

// Save public key
$publicPath = $OUTPUT_DIR . '/public_key.pem';
file_put_contents($publicPath, $publicKeyPEM);
echo "✅ Public key saved: $publicPath\n";
echo "   📤 Copy this file to Eyesense app configs/ directory.\n\n";

// Set secure permissions on private key
if (PHP_OS_FAMILY !== 'Windows') {
    chmod($privatePath, 0600);
    echo "🔒 Set file permissions: private_key.pem (0600)\n";
}

// Auto-copy public key to configs/ for client use
$configsDir = dirname($OUTPUT_DIR) . '/configs';
if (!is_dir($configsDir)) {
    mkdir($configsDir, 0755, true);
}
$configsPublicPath = $configsDir . '/public_key.pem';
copy($publicPath, $configsPublicPath);
echo "📋 Auto-copied public key to: $configsPublicPath\n";

echo "\n============================================\n";
echo "  Key generation complete ($KEY_BITS-bit RSA)\n";
echo "============================================\n";
echo "\nPublic key automatically deployed to configs/public_key.pem\n\n";
