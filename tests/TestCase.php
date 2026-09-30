<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Database\Migrations\Migrator;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $app->extend('migrator', function ($original, $app) {
            $migrator = new class($app['migration.repository'], $app['db'], $app['files'], $app['events']) extends Migrator
            {
                public function getMigrationFiles($paths)
                {
                    $files = parent::getMigrationFiles($paths);
                    // Preserve historical IDs, but order the mistyped 12026 year chronologically for fresh test databases.
                    uksort($files, fn ($a, $b) => preg_replace('/^12026_/', '2026_', $a) <=> preg_replace('/^12026_/', '2026_', $b));
                    return $files;
                }
            };
            foreach ($original->paths() as $path) $migrator->path($path);
            return $migrator;
        });
        return $app;
    }
}
