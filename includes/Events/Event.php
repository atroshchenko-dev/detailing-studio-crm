<?php
namespace App\Events;

/**
 * Базовий абстрактний клас доменної події
 *
 * Всі конкретні події (напр. OrderCreated, TaskUpdated) успадковують цей клас.
 *
 * @package App\Events
 */
abstract class Event {
    /** @var mixed Додаткові дані події */
    public $payload;

    public function __construct($payload = []) {
        $this->payload = $payload;
    }
}
