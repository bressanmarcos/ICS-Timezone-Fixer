<?php

// User agent to use for the cURL request.
// This is a common user agent that should be accepted by most servers.
define('CURL_USERAGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36');

/**
 * Fetch data from a URL with optional limits.
 *
 * @param string   $url        The URL to download.
 * @param int|null $maxBytes   If set, stop reading after this many bytes.
 * @param int|null $rangeBytes If set, request only the first N bytes via HTTP range.
 * @param int      $connectTO  Seconds to wait for a connection.
 * @param int      $timeout    Maximum overall transfer time in seconds.
 *
 * @return string              The downloaded content.
 * @throws Exception           On initialisation or fetch failure.
 */
function fetchFromUrl(
    string $url,
    ?int $maxBytes = null,
    ?int $rangeBytes = null,
    int $connectTO = 2,
    int $timeout = 3
): string {
    $ch = curl_init($url);
    if ($ch === false) {
        throw new Exception('Failed to initialise cURL.');
    }

    curl_setopt_array($ch, [
        CURLOPT_FAILONERROR    => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => $connectTO,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_USERAGENT      => CURL_USERAGENT,
    ]);

    if ($rangeBytes !== null) {
        curl_setopt($ch, CURLOPT_RANGE, '0-' . ($rangeBytes - 1));
    }

    $content = '';
    $downloaded = 0;

    // Stream data into $content while enforcing $maxBytes
    $write = function ($ch, $data) use (&$content, &$downloaded, $maxBytes) {
        $len = strlen($data);
        $downloaded += $len;
        if ($maxBytes !== null && $downloaded > $maxBytes) {
            // Tell cURL to abort the transfer.
            return -1;
        }
        $content .= $data;
        return $len;
    };
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, $write);

    $result = curl_exec($ch);
    if ($result === false) {
        $error = curl_error($ch);
        $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        throw new Exception("Unable to fetch URL. HTTP $code, cURL error: $error");
    }

    curl_close($ch);
    return $content;
}
?>
