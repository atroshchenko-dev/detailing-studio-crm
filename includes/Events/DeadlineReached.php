<?php
declare(strict_types=1);

namespace App\Events;

/**
 * Подія: дедлайн задачі або замовлення наближається/прострочений
 *
 * Диспатчиться cron-скриптом check_deadlines.php.
 * Слухач: TelegramListener — сповіщає в TOPIC_ALERTS.
 *
 * @package App\Events
 */
class DeadlineReached extends Event {
    /** @var array Дані задачі або замовлення */
    public array $entity;
    /** @var string Тип сутності: 'task' або 'order' */
    public string $type;
    /** @var string Рівень терміновості (текст) */
    public string $urgency;

    /**
     * @param array  $entity  Задача або замовлення
     * @param string $type    'task' | 'order'
     * @param string $urgency Опис терміновості
     */
    public function __construct(array $entity, string $type, string $urgency = '') {
        $this->entity = $entity;
        $this->type = $type;
        $this->urgency = $urgency;
    }
}
