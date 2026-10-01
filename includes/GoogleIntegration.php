<?php

declare(strict_types=1);

namespace App;

use Exception;
use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Google_Service_Calendar_EventDateTime;

/**
 * DetalStudio - Google Integration
 * Інтеграція з Google Drive та Calendar
 */

class GoogleIntegration
{

    private static $client = null;
    private static $driveService = null;
    private static $calendarService = null;

    /**
     * Initialize Google Client
     */
    private static function init()
    {
        $credentialsPath = __DIR__ . '/../config/credentials.json';
        $tokenPath = __DIR__ . '/../config/token.json';

        // 1. Initialize client if not already done
        if (self::$client === null) {
            // OAuth 2.0 Strategy
            if (file_exists($credentialsPath) && file_exists($tokenPath)) {
                try {
                    self::$client = new \Google\Client();
                    self::$client->setApplicationName('DetalStudio');
                    self::$client->setAuthConfig($credentialsPath);
                    self::$client->addScope([
                        \Google\Service\Drive::DRIVE,
                        \Google\Service\Calendar::CALENDAR
                    ]);
                    self::$client->setAccessType('offline');
                    self::$client->setPrompt('consent');

                    $accessToken = json_decode(file_get_contents($tokenPath), true);
                    self::$client->setAccessToken($accessToken);
                } catch (\Throwable $e) {
                    error_log("Failed to initialize Google client (OAuth): " . $e->getMessage());
                    self::$client = null;
                }
            }

            // Legacy Service Account Strategy (Fallback)
            if (self::$client === null && !empty(GOOGLE_SERVICE_ACCOUNT_JSON)) {
                try {
                    self::$client = new \Google\Client();
                    self::$client->setApplicationName('DetalStudio');

                    $authConfig = json_decode(GOOGLE_SERVICE_ACCOUNT_JSON, true);
                    if ($authConfig) {
                        self::$client->setAuthConfig($authConfig);
                        self::$client->addScope([
                            \Google\Service\Drive::DRIVE,
                            \Google\Service\Calendar::CALENDAR
                        ]);
                    }
                } catch (\Throwable $e) {
                    error_log("Failed to initialize Google client (Service Account): " . $e->getMessage());
                    self::$client = null;
                }
            }
        }

        if (self::$client === null) {
            error_log("Google Integration: No valid credentials found (OAuth or Service Account).");
            return false;
        }

        // 2. Check and refresh token if using OAuth strategy
        if (file_exists($tokenPath)) {
            try {
                if (self::$client->isAccessTokenExpired()) {
                    if (self::$client->getRefreshToken()) {
                        $newToken = self::$client->fetchAccessTokenWithRefreshToken(self::$client->getRefreshToken());
                        if (!isset($newToken['error'])) {
                            file_put_contents($tokenPath, json_encode(self::$client->getAccessToken()));
                        } else {
                            error_log("Google Integration: Failed to refresh token: " . json_encode($newToken));
                            return false;
                        }
                    } else {
                        error_log("Google Integration: Token expired and no refresh token available.");
                        return false;
                    }
                }
            } catch (\Throwable $e) {
                error_log("Google Integration: Error during token refresh check: " . $e->getMessage());
                return false;
            }
        }

        // 3. Initialize services if missing
        if (self::$driveService === null) {
            self::$driveService = new \Google\Service\Drive(self::$client);
        }
        if (self::$calendarService === null) {
            self::$calendarService = new \Google\Service\Calendar(self::$client);
        }

        return true;
    }

    /**
     * Process queued Google Calendar event
     * Called by NotificationQueue worker
     */
    public static function processQueueEvent($data)
    {
        if (!isset($data['type'])) {
            error_log("Google Integration Queue Error: Missing type");
            return false;
        }

        try {
            $result = false;
            switch ($data['type']) {
                case 'create_order_event':
                    $result = self::executeCreateOrderCalendarEvent(
                        $data['order_id'],
                        $data['title'],
                        $data['deadline'],
                        $data['description'],
                        $data['field'] ?? 'google_event_id'
                    );
                    break;
                case 'create_task_event':
                    $result = self::executeCreateTaskCalendarEvent(
                        $data['task_id'],
                        $data['title'],
                        $data['deadline'],
                        $data['description']
                    );
                    break;
                case 'update_order_event':
                    $result = self::executeUpdateOrderCalendarEvent(
                        $data['event_id'],
                        $data['title'],
                        $data['deadline'],
                        $data['description']
                    );
                    break;
                case 'update_task_event':
                    $result = self::executeUpdateTaskCalendarEvent(
                        $data['event_id'],
                        $data['title'],
                        $data['deadline'],
                        $data['description']
                    );
                    break;
                case 'update_event': // Backward compatibility
                    $result = self::executeUpdateOrderCalendarEvent(
                        $data['event_id'],
                        $data['title'],
                        $data['deadline'],
                        $data['description']
                    );
                    break;
                case 'delete_event':
                    $result = self::executeDeleteCalendarEvent($data['event_id']);
                    break;
                case 'create_order_folder':
                    $result = self::executeCreateOrderFolder(
                        $data['order_id'],
                        $data['client_name'],
                        $data['car_info']
                    );
                    break;
                default:
                    error_log("Google Integration Queue Warning: Unknown type " . $data['type']);
                    return false;
            }

            return $result;
        } catch (Exception $e) {
            error_log("Google Integration Queue Error: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Create folder for order (Immediate)
     */
    public static function createOrderFolder($orderId, $clientName, $carInfo)
    {
        return self::executeCreateOrderFolder($orderId, $clientName, $carInfo);
    }

    /**
     * Execute Create folder for order (Sync/Worker)
     */
    public static function executeCreateOrderFolder($orderId, $clientName, $carInfo)
    {
        if (!self::init()) {
            return false;
        }

        try {
            // Main folder name format: YYYY-MM-DD - Client - Car
            $folderName = date('Y-m-d') . " - $clientName - $carInfo";

            // Folder metadata
            $folder = new Google_Service_Drive_DriveFile();
            $folder->setName($folderName);
            $folder->setMimeType('application/vnd.google-apps.folder');

            // Set parent folder if configured
            if (!empty(GOOGLE_DRIVE_FOLDER_ID)) {
                $folder->setParents([GOOGLE_DRIVE_FOLDER_ID]);
            }

            // Create folder
            $createdFolder = self::$driveService->files->create($folder);
            $folderId = $createdFolder->getId();

            // Create subfolders
            $subfolders = ['Фото До', 'Фото Після', 'Документи', 'Відео'];
            foreach ($subfolders as $subfolderName) {
                $subfolder = new Google_Service_Drive_DriveFile();
                $subfolder->setName($subfolderName);
                $subfolder->setMimeType('application/vnd.google-apps.folder');
                $subfolder->setParents([$folderId]);
                self::$driveService->files->create($subfolder);
            }

            // Update order with folder ID
            $stmt = db()->prepare("UPDATE orders SET google_folder_id = ? WHERE id = ?");
            $stmt->execute([$folderId, $orderId]);

            return $folderId;
        } catch (Exception $e) {
            error_log("Failed to create order folder: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get folder contents
     */
    public static function getFolderContents($folderId)
    {
        if (!self::init()) {
            return [];
        }

        try {
            $query = "'$folderId' in parents and trashed = false";
            $results = self::$driveService->files->listFiles(['q' => $query]);

            $files = [];
            foreach ($results->getFiles() as $file) {
                $files[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'mimeType' => $file->getMimeType(),
                    'webViewLink' => $file->getWebViewLink(),
                    'thumbnailLink' => $file->getThumbnailLink()
                ];
            }

            return $files;
        } catch (Exception $e) {
            error_log("Failed to get folder contents: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Upload photo to order-specific subfolder (Фото До / Фото Після)
     */
    public static function uploadOrderPhoto($orderId, $filePath, $fileName, $photoType = 'before')
    {
        if (!self::init()) {
            return false;
        }

        try {
            // 1. Get order folder ID
            $stmt = db()->prepare("SELECT google_folder_id, order_number FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();

            if (!$order) {
                error_log("Google Integration: Order $orderId not found for photo upload.");
                return false;
            }

            // 2. If no folder, create it first (Sync for now to ensure we have ID)
            $folderId = $order['google_folder_id'];
            if (!$folderId) {
                // We need client name and car info for naming
                $stmtInfo = db()->prepare("
                    SELECT c.name as client_name, CONCAT(car.make, ' ', car.model) as car_info
                    FROM orders o
                    LEFT JOIN clients c ON o.client_id = c.id
                    LEFT JOIN cars car ON o.car_id = car.id
                    WHERE o.id = ?
                ");
                $stmtInfo->execute([$orderId]);
                $info = $stmtInfo->fetch();

                $folderId = self::executeCreateOrderFolder(
                    $orderId,
                    $info['client_name'] ?? 'Client',
                    $info['car_info'] ?? 'Car'
                );
            }

            if (!$folderId) {
                error_log("Google Integration: Failed to get or create folder for order $orderId");
                return false;
            }

            // 3. Find/Create subfolder based on photoType
            $subfolderName = match ($photoType) {
                'after' => 'Фото Після',
                'process' => 'В процесі', // Add process if needed, or fallback
                default => 'Фото До'
            };

            // Check if subfolder exists
            $query = "'$folderId' in parents and name = '$subfolderName' and mimeType = 'application/vnd.google-apps.folder' and trashed = false";
            $results = self::$driveService->files->listFiles(['q' => $query]);
            $subfolderId = null;

            foreach ($results->getFiles() as $file) {
                $subfolderId = $file->getId();
                break;
            }

            if (!$subfolderId) {
                // Create missing subfolder
                $subfolder = new \Google\Service\Drive\DriveFile();
                $subfolder->setName($subfolderName);
                $subfolder->setMimeType('application/vnd.google-apps.folder');
                $subfolder->setParents([$folderId]);
                $createdSub = self::$driveService->files->create($subfolder);
                $subfolderId = $createdSub->getId();
            }

            // 4. Upload file
            return self::uploadFile($filePath, $fileName, $subfolderId);
        } catch (Exception $e) {
            error_log("Failed to upload order photo: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generic Upload File
     */
    public static function uploadFile($filePath, $fileName, $folderId = null)
    {
        if (!self::init()) {
            return false;
        }

        try {
            $file = new \Google\Service\Drive\DriveFile();
            $file->setName($fileName);

            if ($folderId) {
                $file->setParents([$folderId]);
            } elseif (defined('GOOGLE_DRIVE_FOLDER_ID') && !empty(GOOGLE_DRIVE_FOLDER_ID)) {
                $file->setParents([GOOGLE_DRIVE_FOLDER_ID]);
            }

            $content = file_get_contents($filePath);
            $uploadedFile = self::$driveService->files->create($file, [
                'data' => $content,
                'mimeType' => mime_content_type($filePath),
                'uploadType' => 'multipart'
            ]);

            return $uploadedFile->getId();
        } catch (Exception $e) {
            error_log("Failed to upload file: " . $e->getMessage());
            if (strpos($e->getMessage(), 'storageQuotaExceeded') !== false) {
                error_log("HINT: Service Accounts have 0 quota. You MUST share a folder with the Service Account email and set GOOGLE_DRIVE_FOLDER_ID in config.php.");
            }
            return false;
        }
    }

    /**
     * List files in specific folder (or all)
     */
    public static function listFiles($folderId = null)
    {
        if (!self::init()) {
            return [];
        }

        try {
            $q = "trashed = false";
            if ($folderId) {
                $q .= " and '$folderId' in parents";
            } else if (defined('GOOGLE_DRIVE_FOLDER_ID') && !empty(GOOGLE_DRIVE_FOLDER_ID)) {
                // Default to main backup folder if not specified
                $backupFolderId = GOOGLE_DRIVE_FOLDER_ID;
                $q .= " and '$backupFolderId' in parents";
            }

            $optParams = [
                'q' => $q,
                'orderBy' => 'createdTime desc'
            ];

            $results = self::$driveService->files->listFiles($optParams);

            $files = [];
            foreach ($results->getFiles() as $file) {
                $files[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'createdTime' => $file->getCreatedTime()
                ];
            }

            return $files;
        } catch (Exception $e) {
            error_log("Failed to list files: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete file by ID
     */
    public static function deleteFile($fileId)
    {
        if (!self::init()) {
            return false;
        }

        try {
            self::$driveService->files->delete($fileId);
            return true;
        } catch (Exception $e) {
            error_log("Failed to delete file: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create calendar event for order deadline
     */
    /**
     * Create calendar event for order deadline (Immediate)
     */
    public static function createOrderCalendarEvent($orderId, $title, $deadline, $description = '', $field = 'google_event_id')
    {
        return self::executeCreateOrderCalendarEvent($orderId, $title, $deadline, $description, $field);
    }

    /**
     * Execute Create calendar event for order deadline (Private, Sync)
     */
    private static function executeCreateOrderCalendarEvent($orderId, $title, $deadline, $description = '', $field = 'google_event_id')
    {
        if (!self::init()) {
            return false;
        }

        try {
            $event = new Google_Service_Calendar_Event([
                'summary' => $title,
                'description' => $description,
                'start' => [
                    'dateTime' => date('c', strtotime($deadline)),
                    'timeZone' => 'Europe/Kiev'
                ],
                'end' => [
                    'dateTime' => date('c', strtotime($deadline . ' +1 hour')),
                    'timeZone' => 'Europe/Kiev'
                ],
                'reminders' => [
                    'useDefault' => false,
                    'overrides' => [
                        ['method' => 'popup', 'minutes' => 60],
                        ['method' => 'popup', 'minutes' => 1440] // 24 hours
                    ]
                ]
            ]);

            $calendarId = GOOGLE_CALENDAR_ID ?: 'primary';

            $createdEvent = self::$calendarService->events->insert($calendarId, $event);

            $eventId = $createdEvent->getId();

            // Update order with event ID
            $allowedFields = ['google_event_id', 'google_arrival_event_id'];
            if (!in_array($field, $allowedFields, true)) {
                $field = 'google_event_id';
            }
            $stmt = db()->prepare("UPDATE orders SET $field = ? WHERE id = ?");
            $stmt->execute([$eventId, $orderId]);

            return $eventId;
        } catch (Exception $e) {
            error_log("Failed to create calendar event: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create calendar event for task deadline
     */
    /**
     * Create calendar event for task deadline (Immediate)
     */
    public static function createTaskCalendarEvent($taskId, $title, $deadline, $description = '')
    {
        return self::executeCreateTaskCalendarEvent($taskId, $title, $deadline, $description);
    }

    /**
     * Execute Create calendar event for task deadline (Private, Sync)
     */
    private static function executeCreateTaskCalendarEvent($taskId, $title, $deadline, $description = '')
    {
        if (!self::init()) {
            return false;
        }

        try {
            $workStart = \App\Models\Setting::get('work_day_start', '09:00');
            $workEnd = \App\Models\Setting::get('work_day_end', '18:00');
            $dateOnly = date('Y-m-d', strtotime($deadline));

            $event = new Google_Service_Calendar_Event([
                'summary' => $title,
                'description' => $description,
                'start' => [
                    'dateTime' => date('c', strtotime($dateOnly . ' ' . $workStart)),
                    'timeZone' => 'Europe/Kiev'
                ],
                'end' => [
                    'dateTime' => date('c', strtotime($dateOnly . ' ' . $workEnd)),
                    'timeZone' => 'Europe/Kiev'
                ],
                'reminders' => [
                    'useDefault' => false,
                    'overrides' => [
                        ['method' => 'popup', 'minutes' => 30],
                        ['method' => 'popup', 'minutes' => 120] // 2 hours
                    ]
                ]
            ]);

            $calendarId = GOOGLE_CALENDAR_ID ?: 'primary';
            $createdEvent = self::$calendarService->events->insert($calendarId, $event);

            $eventId = $createdEvent->getId();

            // Update task with event ID
            $stmt = db()->prepare("UPDATE tasks SET google_event_id = ? WHERE id = ?");
            $stmt->execute([$eventId, $taskId]);

            return $eventId;
        } catch (Exception $e) {
            error_log("Failed to create calendar event: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update calendar event for order (Immediate)
     */
    public static function updateOrderCalendarEvent($eventId, $title, $deadline, $description = '')
    {
        return self::executeUpdateOrderCalendarEvent($eventId, $title, $deadline, $description);
    }

    /**
     * Execute Update calendar event for order (Private, Sync)
     */
    private static function executeUpdateOrderCalendarEvent($eventId, $title, $deadline, $description = '')
    {
        if (!self::init()) {
            return false;
        }

        try {
            $calendarId = GOOGLE_CALENDAR_ID ?: 'primary';
            $event = self::$calendarService->events->get($calendarId, $eventId);

            $event->setSummary($title);
            $event->setDescription($description);

            $start = new Google_Service_Calendar_EventDateTime();
            $start->setDateTime(date('c', strtotime($deadline)));
            $start->setTimeZone('Europe/Kiev');
            $event->setStart($start);

            $end = new Google_Service_Calendar_EventDateTime();
            $end->setDateTime(date('c', strtotime($deadline . ' +1 hour')));
            $end->setTimeZone('Europe/Kiev');
            $event->setEnd($end);

            self::$calendarService->events->update($calendarId, $eventId, $event);

            return true;
        } catch (Exception $e) {
            error_log("Failed to update order calendar event: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update calendar event for task (Immediate)
     */
    public static function updateTaskCalendarEvent($eventId, $title, $deadline, $description = '')
    {
        return self::executeUpdateTaskCalendarEvent($eventId, $title, $deadline, $description);
    }

    /**
     * Execute Update calendar event for task (Private, Sync)
     */
    private static function executeUpdateTaskCalendarEvent($eventId, $title, $deadline, $description = '')
    {
        if (!self::init()) {
            return false;
        }

        try {
            $calendarId = GOOGLE_CALENDAR_ID ?: 'primary';
            $event = self::$calendarService->events->get($calendarId, $eventId);

            $dateOnly = date('Y-m-d', strtotime($deadline));
            $event->setSummary($title);
            $event->setDescription($description);

            // Fetch work hours
            $workStart = \App\Models\Setting::get('work_day_start', '09:00');
            $workEnd = \App\Models\Setting::get('work_day_end', '18:00');

            $start = new Google_Service_Calendar_EventDateTime();
            $start->setDateTime(date('c', strtotime($dateOnly . ' ' . $workStart)));
            $start->setTimeZone('Europe/Kiev');
            $event->setStart($start);

            $end = new Google_Service_Calendar_EventDateTime();
            $end->setDateTime(date('c', strtotime($dateOnly . ' ' . $workEnd)));
            $end->setTimeZone('Europe/Kiev');
            $event->setEnd($end);

            self::$calendarService->events->update($calendarId, $eventId, $event);

            return true;
        } catch (Exception $e) {
            error_log("Failed to update task calendar event: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete calendar event
     */
    /**
     * Delete calendar event (Immediate)
     */
    public static function deleteCalendarEvent($eventId)
    {
        return self::executeDeleteCalendarEvent($eventId);
    }

    /**
     * Execute Delete calendar event (Private, Sync)
     */
    private static function executeDeleteCalendarEvent($eventId)
    {
        if (!self::init()) {
            return false;
        }

        try {
            $calendarId = GOOGLE_CALENDAR_ID ?: 'primary';
            self::$calendarService->events->delete($calendarId, $eventId);
            return true;
        } catch (Exception $e) {
            error_log("Failed to delete calendar event: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get calendar events for period
     */
    public static function getCalendarEvents($startDate, $endDate)
    {
        if (!self::init()) {
            return [];
        }

        try {
            $calendarId = GOOGLE_CALENDAR_ID ?: 'primary';

            $optParams = [
                'timeMin' => date('c', strtotime($startDate)),
                'timeMax' => date('c', strtotime($endDate)),
                'singleEvents' => true,
                'orderBy' => 'startTime'
            ];

            $results = self::$calendarService->events->listEvents($calendarId, $optParams);

            $events = [];
            foreach ($results->getItems() as $event) {
                $events[] = [
                    'id' => $event->getId(),
                    'title' => $event->getSummary(),
                    'description' => $event->getDescription(),
                    'start' => $event->getStart()->getDateTime(),
                    'end' => $event->getEnd()->getDateTime()
                ];
            }

            return $events;
        } catch (Exception $e) {
            error_log("Failed to get calendar events: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if Google integration is configured
     */
    public static function isConfigured()
    {
        $tokenPath = __DIR__ . '/../config/token.json';
        return file_exists($tokenPath) || !empty(GOOGLE_SERVICE_ACCOUNT_JSON);
    }

    /**
     * Get Google Drive file URL
     */
    public static function getFileUrl($fileId)
    {
        return "https://drive.google.com/file/d/$fileId/view";
    }

    /**
     * Get Google Drive folder URL
     */
    public static function getFolderUrl($folderId)
    {
        return "https://drive.google.com/drive/folders/$folderId";
    }
}
