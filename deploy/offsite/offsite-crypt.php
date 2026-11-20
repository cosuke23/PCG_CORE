<?php
/**
 * Encrypt / decrypt one file for the off-site backup copy (deploy/offsite).
 * AES-256-GCM in 1 MiB chunks: each chunk has its own nonce (random file nonce
 * + chunk number) and tag, and the last chunk is marked, so a changed, reordered
 * or cut-short file fails to decrypt. The key is 32 random bytes (base64) in a
 * file that stays on this PC (never on Hermes, never in git).
 *
 *   php offsite-crypt.php keygen  <keyfile>
 *   php offsite-crypt.php encrypt <keyfile> <in> <out>
 *   php offsite-crypt.php decrypt <keyfile> <in> <out>
 */
const MAGIC = "PCGOFF1\n";
const CHUNK = 1048576;

function fail(string $m): never { fwrite(STDERR, "offsite-crypt: $m\n"); exit(1); }

function loadKey(string $file): string
{
    $k = base64_decode(trim((string) @file_get_contents($file)), true);
    if ($k === false || strlen($k) !== 32) fail("no valid key in $file");
    return $k;
}

function nonce(string $base, int $i): string
{
    return substr($base, 0, 4) . pack('J', unpack('J', substr($base, 4, 8))[1] ^ $i);
}

[$cmd, $keyFile, $in, $out] = array_pad(array_slice($argv, 1), 4, null);

if ($cmd === 'keygen') {
    if (!$keyFile) fail('keygen <keyfile>');
    if (file_exists($keyFile)) fail("$keyFile exists; refusing to overwrite a key");
    if (file_put_contents($keyFile, base64_encode(random_bytes(32)) . "\n") === false) fail("cannot write $keyFile");
    echo "key written to $keyFile\n";
    exit(0);
}
if (!in_array($cmd, ['encrypt', 'decrypt'], true) || !$keyFile || !$in || !$out) {
    fail('usage: keygen <keyfile> | encrypt|decrypt <keyfile> <in> <out>');
}
$key = loadKey($keyFile);
$src = @fopen($in, 'rb') ?: fail("cannot read $in");
$tmp = $out . '.part';
$dst = @fopen($tmp, 'wb') ?: fail("cannot write $tmp");

if ($cmd === 'encrypt') {
    $base = random_bytes(12);
    fwrite($dst, MAGIC . $base);
    $i = 0;
    $buf = fread($src, CHUNK);
    do {
        $next = fread($src, CHUNK);
        $last = $next === '' || $next === false;
        $ct = openssl_encrypt((string) $buf, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, nonce($base, $i), $tag, $last ? 'last' : 'more');
        if ($ct === false) fail('encryption failed');
        fwrite($dst, pack('N', strlen($ct)) . $tag . $ct);
        $buf = $next;
        $i++;
    } while (!$last);
} else {
    if (fread($src, strlen(MAGIC)) !== MAGIC) fail("$in is not an off-site backup file");
    $base = fread($src, 12);
    $i = 0;
    $sawLast = false;
    while (!$sawLast) {
        $len = fread($src, 4);
        if (strlen((string) $len) !== 4) fail("$in is cut short");
        $n = unpack('N', $len)[1];
        $tag = fread($src, 16);
        $ct = $n > 0 ? fread($src, $n) : '';
        if (strlen((string) $ct) !== $n) fail("$in is cut short");
        $sawLast = fread($src, 1) === '' && feof($src);
        if (!$sawLast) fseek($src, -1, SEEK_CUR);
        $pt = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, nonce($base, $i), $tag, $sawLast ? 'last' : 'more');
        if ($pt === false) fail("$in does not decrypt (wrong key, or the file was changed)");
        fwrite($dst, $pt);
        $i++;
    }
}
fclose($src);
fclose($dst);
if (!rename($tmp, $out)) fail("cannot write $out");
exit(0);
