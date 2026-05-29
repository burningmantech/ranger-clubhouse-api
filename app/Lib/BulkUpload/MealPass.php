<?php

namespace App\Lib\BulkUpload;

/**
 * Combinations of pre/event/post meal periods that the bulk uploader
 * accepts. The case value is the action id that arrives from the UI.
 *
 * Replaces the parallel BulkUploader::MEAL_TYPES + MEAL_MATRIX arrays so
 * the legal combinations are defined exactly once.
 */
enum MealPass: string
{
    case All           = 'all_eat_pass';
    case Event         = 'event_eat_pass';
    case PreEvent      = 'pre_event_eat_pass';
    case PostEvent     = 'post_event_eat_pass';
    case PreEventEvent = 'pre_event_event_eat_pass';
    case EventPost     = 'event_post_event_eat_pass';
    case PrePost       = 'pre_post_eat_pass';

    /**
     * @return array{pre: bool, event: bool, post: bool}
     */
    public function periods(): array
    {
        return match ($this) {
            self::All           => ['pre' => true,  'event' => true,  'post' => true],
            self::Event         => ['pre' => false, 'event' => true,  'post' => false],
            self::PreEvent      => ['pre' => true,  'event' => false, 'post' => false],
            self::PostEvent     => ['pre' => false, 'event' => false, 'post' => true],
            self::PreEventEvent => ['pre' => true,  'event' => true,  'post' => false],
            self::EventPost     => ['pre' => false, 'event' => true,  'post' => true],
            self::PrePost       => ['pre' => true,  'event' => false, 'post' => true],
        };
    }

    public static function tryFromAction(string $action): ?self
    {
        return self::tryFrom(str_replace('alloc_', '', $action));
    }
}
