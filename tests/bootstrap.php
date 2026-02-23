<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Stubs for internal NC classes referenced by OCP interfaces but not shipped
// in the nextcloud/ocp package (e.g. IRootFolder extends OC\Hooks\Emitter).
if (!interface_exists('OC\Hooks\Emitter', false)) {
    eval('namespace OC\Hooks; interface Emitter {}');
}
if (!class_exists('OC\User\NoUserException', false)) {
    eval('namespace OC\User; class NoUserException extends \Exception {}');
}

// Stub for OC class — needed by OCP\Util::addInitScript() which calls OC::$server
if (!class_exists('OC', false)) {
    eval('
        class OC {
            /** @var object */
            public static $server;
        }
        // Minimal server mock with get() that returns a no-op object
        OC::$server = new class {
            public function get(string $class): object {
                return new class {
                    public function findLanguage(string $app = null): string { return "en"; }
                };
            }
        };
    ');
}

// nextcloud/ocp package doesn't define autoload — register OCP namespace manually
spl_autoload_register(function (string $class): void {
    $prefix = 'OCP\\';
    if (str_starts_with($class, $prefix)) {
        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file = __DIR__ . '/../vendor/nextcloud/ocp/OCP/' . $relative . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});
