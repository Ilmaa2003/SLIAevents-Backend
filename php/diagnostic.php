<?php
/**
 * Diagnostic Tool for SLIA Payment Bridge
 * Upload this to slia.lk and visit it in your browser.
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>SLIA Bridge Diagnostic</h1>";

echo "<h2>1. Server Info</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";

echo "<h2>2. Required Extensions</h2>";
$extensions = ['curl', 'json', 'hash', 'openssl'];
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ Extension <strong>$ext</strong> is LOADED.<br>";
    } else {
        echo "❌ Extension <strong>$ext</strong> is MISSING!<br>";
    }
}

echo "<h2>3. Function Checks</h2>";
$functions = ['curl_init', 'hash_hmac', 'json_encode', 'hash_equals'];
foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "✅ Function <strong>$func</strong> is available.<br>";
    } else {
        echo "❌ Function <strong>$func</strong> is NOT found!<br>";
    }
}

echo "<h2>4. Connectivity Test (Self)</h2>";
$url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
echo "Trying to reach: $url<br>";

echo "<h2>5. Outbound Connectivity (Paycorp)</h2>";
$ch = curl_init('https://sampath.paycorp.lk/rest/service/proxy');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if (curl_errno($ch)) {
    echo "❌ Outbound Test FAILED: " . curl_error($ch) . "<br>";
} else {
    echo "✅ Outbound Test SUCCESS (HTTP Code: $code)<br>";
}
curl_close($ch);

echo "<br><hr><p>End of Diagnostics</p>";
