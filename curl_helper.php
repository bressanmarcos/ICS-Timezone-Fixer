<?php
// curl_helper.php

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
