<?php
namespace WP_Plupload;

/**
 * Autoloader
 */
class Autoloader
{
    /** @const string */
    const NAMESPACE_PREFIX = 'WP_Plupload\\';
    const SOURCE_DIR = 'src';

    /**
     * Register
     *
     * @param bool $throw
     * @param bool $prepend
     * @return void
     */
    public static function register($throw = true, $prepend = false)
    {
        spl_autoload_register(array(new self, 'autoload'), $throw, $prepend);
    }

    /**
     * Autoload
     *
     * @param string $class
     * @return void
     */
    public static function autoload($class)
    {
        $prefixLength = strlen(self::NAMESPACE_PREFIX);
        if (0 === strncmp(self::NAMESPACE_PREFIX, $class, $prefixLength)) {
            $file = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, $prefixLength));
            $file = realpath(__DIR__ . DIRECTORY_SEPARATOR . self::SOURCE_DIR . DIRECTORY_SEPARATOR . $file . '.php');
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }
}