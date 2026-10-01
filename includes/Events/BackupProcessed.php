<?php
declare(strict_types=1);

namespace App\Events;

/**
 * Подія: бекап виконано (успішно або з помилкою)
 *
 * Слухач: TelegramListener — сповіщає в TOPIC_GENERAL.
 *
 * @package App\Events
 */
class BackupProcessed extends Event {
    /** @var bool Чи успішний бекап */
    public bool $success;
    /** @var string Повідомлення про результат */
    public string $message;
    /** @var string|null Ім'я файлу бекапу */
    public ?string $fileName;

    /**
     * @param bool        $success  Результат
     * @param string      $message  Повідомлення
     * @param string|null $fileName Ім'я файлу (якщо успішно)
     */
    public function __construct(bool $success, string $message, ?string $fileName = null) {
        $this->success = $success;
        $this->message = $message;
        $this->fileName = $fileName;
    }
}
