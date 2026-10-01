<?php
namespace App\Events;

/**
 * Диспетчер подій
 *
 * Реєструє слухачів та диспатчить події.
 * Помилки в слухачах не зупиняють виконання інших слухачів.
 *
 * @package App\Events
 */
class EventDispatcher {
    /** @var array<string, callable[]> Реєстр слухачів */
    private static $listeners = [];

    /**
     * Зареєструвати слухача на подію
     *
     * @param string   $eventName FQCN класу події
     * @param callable $callback  Колбек-функція слухача
     * @return void
     */
    public static function addListener(string $eventName, callable $callback): void {
        self::$listeners[$eventName][] = $callback;
    }

    /**
     * Диспатчити подію всім зареєстрованим слухачам
     *
     * @param object $event Об'єкт події (extends Event)
     * @return void
     */
    public static function dispatch(object $event): void {
        $eventName = get_class($event);
        if (isset(self::$listeners[$eventName])) {
            foreach (self::$listeners[$eventName] as $callback) {
                try {
                    call_user_func($callback, $event);
                } catch (\Exception $e) {
                    error_log("Event Dispatcher Error [$eventName]: " . $e->getMessage());
                }
            }
        }
    }
}
