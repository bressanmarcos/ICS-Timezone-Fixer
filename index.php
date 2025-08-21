<?php
require_once __DIR__ . '/curl_helper.php';

define('MAX_FILE_SIZE', 819200); // 800 kB
define('MISSING_TIMEZONES_FILE', __DIR__ . '/missing_timezones');

// Main execution
try {
    $icsUrl = getIcsUrl();
    validateUrl($icsUrl);
    validateFileContent($icsUrl);
    $icsContent = fetchIcsContent($icsUrl, MAX_FILE_SIZE);
    $missingTimezones = readMissingTimezones(MISSING_TIMEZONES_FILE);
    $modifiedIcsContent = insertMissingTimezones($icsContent, $missingTimezones);
    outputIcsContent($modifiedIcsContent);
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

// Function to get the ICS URL from the query parameter
function getIcsUrl(): string {
    if (!isset($_GET['ics_url']) || empty($_GET['ics_url'])) {
        outputInstructions();
        exit;
    }
    return $_GET['ics_url'];
}

// Function to display usage instructions
function outputInstructions() {
    echo "<h1>ICS Timezone Fixer</h1>";
    echo "<p>This tool modifies a provided .ics calendar file to include missing timezones, ensuring accurate event times in Google Calendar and other apps.</p>";
    echo "<h2>How to Use:</h2>";
    echo "<ol>";
    echo "<li>Provide an .ics file URL as a query parameter named <code>ics_url</code>.</li>";
    echo "<li>Example usage:</li>";
    echo "<pre>https://ics-changer.great-site.net/?ics_url=https://original-calendar-url.ics</pre>";
    echo "<li>Just use the new URL as a replacement for the original one!</li>";
    echo "</ol>";
    echo "<h2>Note:</h2>";
    echo "<p>The hosted version is provided as-is, without guarantees. If you require reliable access, consider setting up your own server using this code.</p>";
}

// Function to validate the provided URL and enforce HTTPS
function validateUrl($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new Exception('Invalid URL.');
    }

    // Enforce HTTPS
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (strtolower($scheme) !== 'https') {
        throw new Exception('Only HTTPS URLs are allowed.');
    }
}

/**
 * Validate that the remote file appears to be a valid `.ics` by reading its first KB.
 *
 * @throws Exception when the file cannot be read or does not contain BEGIN:VCALENDAR
 */
function validateFileContent(string $url): void {
    // Read first 100 bytes only
    $partial = fetchFromUrl($url, rangeBytes: 128);
    if (strpos($partial, 'BEGIN:VCALENDAR') === false) {
        throw new Exception('The file does not appear to be a valid .ics (BEGIN:VCALENDAR not found).');
    }
}

/**
 * Download the full .ics file, stopping at $maxFileSize.
 */
function fetchIcsContent(string $url, int $maxFileSize): string {
    return fetchFromUrl($url, maxBytes: $maxFileSize);
}

// Function to read the missing timezones from the side file
function readMissingTimezones($filename) {
    if (!file_exists($filename)) {
        throw new Exception('Missing timezones file not found.');
    }

    $content = file_get_contents($filename);
    if ($content === false) {
        throw new Exception('Unable to read the missing timezones file.');
    }

    return $content;
}

// Function to insert missing timezones into the ICS content
function insertMissingTimezones($icsContent, $missingTimezones) {
    $pos = strpos($icsContent, 'BEGIN:VEVENT');
    if ($pos === false) {
        throw new Exception('No events found in calendar.');
    }

    $modifiedIcsContent = substr($icsContent, 0, $pos) . $missingTimezones . "\n" . substr($icsContent, $pos);

    return $modifiedIcsContent;
}

// Function to output the modified ICS content with appropriate headers
function outputIcsContent($modifiedIcsContent) {
    // Now that everything is validated and modified, set the content type headers
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="modified_calendar.ics"');

    echo $modifiedIcsContent;
}
?>
