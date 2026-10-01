<?php

declare(strict_types=1);

namespace App\Traits;

use Exception;

/**
 * Telegram Bot API — низькорівневий HTTP-транспорт
 *
 * Забезпечує HTTP-комунікацію з Telegram Bot API через cURL.
 * Управляє токеном та chat ID з БД (Setting) або config.
 *
 * @package App\Traits
 */
trait TelegramApi
{
    protected static $apiUrl = 'https://api.telegram.org/bot';

    /**
     * Отримати токен бота (пріоритет: БД → config)
     *
     * @return string Токен бота або порожній рядок
     */
    public static function getToken()
    {
        $token = \App\Models\Setting::get('telegram_bot_token');
        if (!empty($token)) return $token;
        return defined('TELEGRAM_BOT_TOKEN') ? TELEGRAM_BOT_TOKEN : '';
    }

    /**
     * Отримати ID групового чату (пріоритет: БД → config)
     *
     * @return string Chat ID або порожній рядок
     */
    public static function getGroupChatId()
    {
        $chatId = \App\Models\Setting::get('telegram_group_chat_id');
        if (!empty($chatId)) return $chatId;
        return defined('TELEGRAM_GROUP_CHAT_ID') ? TELEGRAM_GROUP_CHAT_ID : '';
    }

    protected static function logDebug($message)
    {
        // Debug logging disabled for production
    }

    /**
     * Виконати запит до Telegram Bot API
     *
     * @param string $method Метод API (sendMessage, editMessageText тощо)
     * @param array  $params Параметри запиту
     * @return array Відповідь API
     */
    public static function request($method, $params = [])
    {
        $url = self::$apiUrl . self::getToken() . '/' . $method;
        return self::makeRequest($url, $params);
    }

    /**
     * Видалити повідомлення з чату
     *
     * @param int|string $chatId    ID чату
     * @param int        $messageId ID повідомлення
     * @return array Відповідь API
     */
    public static function deleteMessage($chatId, $messageId)
    {
        return self::request('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ]);
    }

    /**
     * Завантажити файл з Telegram серверів
     *
     * @param string $fileId Telegram file_id
     * @return array{content: string, extension: string}|false Вміст та розширення або false
     */
    public static function downloadFile($fileId)
    {
        if (strpos($fileId, 'mock_') === 0) {
            return ['content' => 'fake_image_content', 'extension' => 'jpg'];
        }

        $url = self::$apiUrl . self::getToken() . '/getFile';
        $response = self::makeRequest($url, ['file_id' => $fileId]);

        if (isset($response['ok']) && $response['ok'] && isset($response['result']['file_path'])) {
            $filePath = $response['result']['file_path'];
            $downloadUrl = 'https://api.telegram.org/file/bot' . self::getToken() . '/' . $filePath;

            $content = file_get_contents($downloadUrl);
            if ($content !== false) {
                return [
                    'content' => $content,
                    'extension' => pathinfo($filePath, PATHINFO_EXTENSION)
                ];
            }
        }
        return false;
    }

    /**
     * Виконати HTTP POST-запит через cURL
     *
     * @param string $url  URL ендпоінту
     * @param array  $data Дані для відправки (JSON)
     * @return array Декодована відповідь
     */
    protected static function makeRequest($url, $data)
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 60);
            $response = curl_exec($ch);
            if ($response === false) {
                $curlError = curl_error($ch);
                error_log("Telegram CURL Error: " . $curlError);
                curl_close($ch);
                
                // Retry once on connection timeout (DNS may resolve to different IP)
                if (strpos($curlError, 'timed out') !== false || strpos($curlError, 'Connection') !== false) {
                    $ch2 = curl_init();
                    curl_setopt($ch2, CURLOPT_URL, $url);
                    curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch2, CURLOPT_POST, 1);
                    curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($data));
                    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 3);
                    curl_setopt($ch2, CURLOPT_TIMEOUT, 8);
                    curl_setopt($ch2, CURLOPT_DNS_CACHE_TIMEOUT, 0);
                    curl_setopt($ch2, CURLOPT_FRESH_CONNECT, true);
                    $response = curl_exec($ch2);
                    if ($response !== false) {
                        curl_close($ch2);
                        return json_decode($response, true) ?: ['ok' => false];
                    }
                    error_log("Telegram CURL Retry Error: " . curl_error($ch2));
                    curl_close($ch2);
                }
                
                return ['ok' => false];
            }
            curl_close($ch);
            return json_decode($response, true) ?: ['ok' => false];
        } catch (Exception $e) {
            error_log("Telegram API error: " . $e->getMessage());
            return ['ok' => false];
        }
    }
}
