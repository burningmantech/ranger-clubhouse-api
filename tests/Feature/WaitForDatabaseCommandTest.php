<?php

namespace Tests\Feature;

use App\Console\Commands\WaitForDatabase;
use Tests\TestCase;

class WaitForDatabaseCommandTest extends TestCase
{
    /**
     * The signature must place a space before the colon that separates the
     * `--time` option's default value from its description. Without that
     * space, Laravel's signature parser folds the entire description into
     * the default value (e.g. "60: wait X seconds until database is ready"),
     * which breaks the `$i < $time` loop in handle() after only a handful
     * of iterations instead of the intended 60.
     *
     * @return void
     */
    public function test_time_option_default_is_parsed_as_sixty_not_concatenated_with_description(): void
    {
        $command = new WaitForDatabase();

        $option = $command->getDefinition()->getOption('time');

        $this->assertSame('60', $option->getDefault());
        $this->assertSame(60, (int) $option->getDefault());
        $this->assertSame('wait X seconds until database is ready', $option->getDescription());
    }
}
