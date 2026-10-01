<?php

/**
 * Test Google Calendar Connection
 * Upload this file to tools/test_google_calendar_connection.php and open in browser
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/GoogleIntegration.php';

use App\GoogleIntegration;

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Google Calendar Integration Test</h1>";

// 1. Check Configuration
echo "<h2>1. Configuration Check</h2>";
$isConfigured = GoogleIntegration::isConfigured();
if ($isConfigured) {
    echo "<p style='color:green'>✅ Google Integration is configured (token or service account found).</p>";
} else {
    echo "<p style='color:red'>❌ Google Integration is NOT configured.</p>";
    echo "<p>Please ensure <code>config/token.json</code> exists or <code>GOOGLE_SERVICE_ACCOUNT_JSON</code> is set.</p>";
    exit;
}

// 2. Test Read Access
echo "<h2>2. Read Access Test (Get Events for next 7 days)</h2>";
try {
    $start = date('Y-m-d');
    $end = date('Y-m-d', strtotime('+7 days'));
    $events = GoogleIntegration::getCalendarEvents($start, $end);

    if (is_array($events)) {
        echo "<p style='color:green'>✅ Successfully fetched events.</p>";
        echo "<p>Found " . count($events) . " events.</p>";
        if (count($events) > 0) {
            echo "<ul>";
            foreach (array_slice($events, 0, 5) as $event) {
                echo "<li>" . htmlspecialchars($event['title']) . " (" . $event['start'] . ")</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p style='color:red'>❌ Failed to fetch events (returned non-array).</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Exception during read: " . $e->getMessage() . "</p>";
}

// 3. Test Write Access (Create Dummy Event)
echo "<h2>3. Write Access Test (Create -> Update -> Delete)</h2>";
$dummyEventId = null;

try {
    echo "<p>Attempting to create a test event...</p>";

    // Construct data directly for processQueueEvent to bypass queue and execute immediately
    $createData = [
        'type' => 'create_task_event',
        'task_id' => 999999, // Dummy ID
        'title' => 'TEST EVENT DETALSTUDIO ' . date('H:i:s'),
        'deadline' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        'description' => 'This is a test event to verify API connectivity. You can delete this.'
    ];

    // We need to capture the return value of executeCreateTaskCalendarEvent.
    // processQueueEvent returns the result of the executed method.
    $dummyEventId = GoogleIntegration::processQueueEvent($createData);

    if ($dummyEventId) {
        echo "<p style='color:green'>✅ Event created successfully! ID: $dummyEventId</p>";

        // 4. Update Event
        echo "<p>Attempting to update the event...</p>";
        $updateData = [
            'type' => 'update_event',
            'event_id' => $dummyEventId,
            'title' => 'TEST EVENT UPDATED ' . date('H:i:s'),
            'deadline' => date('Y-m-d H:i:s', strtotime('+2 hours')),
            'description' => 'Updated description.'
        ];

        $updated = GoogleIntegration::processQueueEvent($updateData);
        if ($updated) {
            echo "<p style='color:green'>✅ Event updated successfully.</p>";
        } else {
            echo "<p style='color:red'>❌ Failed to update event.</p>";
        }

        // 5. Delete Event
        echo "<p>Attempting to delete the event...</p>";
        $deleteData = [
            'type' => 'delete_event',
            'event_id' => $dummyEventId
        ];

        $deleted = GoogleIntegration::processQueueEvent($deleteData);

        if ($deleted) {
            echo "<p style='color:green'>✅ Event deleted successfully.</p>";
        } else {
            echo "<p style='color:red'>❌ Failed to delete event.</p>";
        }
    } else {
        echo "<p style='color:red'>❌ Failed to create event. Check error log.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Exception during write test: " . $e->getMessage() . "</p>";
}

echo "<hr><p>Test Complete.</p>";
