<?php

use Bolt\Requirement\BoltRequirements;
use Symfony\Requirements\Requirement;

/**
 * Command line requirements checker.
 *
 * Heavily based on symfony/requirements.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Checker
{
    /**
     * @param string $autoloader
     */
    public static function check($autoloader)
    {
        $lineSize = 70;
        $boltRequirements = new BoltRequirements(dirname(dirname(realpath($autoloader))), '3.4');
        $iniPath = $boltRequirements->getPhpIniPath();

        static::echoTitle('Bolt Requirements Checker');

        echo '> PHP is using the following php.ini file:' . PHP_EOL;
        if ($iniPath) {
            static::echoStyle('green', '  ' . $iniPath);
        } else {
            static::echoStyle('yellow', '  WARNING: No configuration file (php.ini) used by PHP!');
        }

        echo PHP_EOL . PHP_EOL;

        echo '> Checking Bolt requirements:' . PHP_EOL . '  ';

        $messages = [];
        foreach ($boltRequirements->getRequirements() as $req) {
            if ($helpText = static::getErrorMessage($req, $lineSize)) {
                static::echoStyle('red', 'E');
                $messages['error'][] = $helpText;
            } else {
                static::echoStyle('green', '.');
            }
        }

        $checkPassed = empty($messages['error']);

        foreach ($boltRequirements->getRecommendations() as $req) {
            if ($helpText = static::getErrorMessage($req, $lineSize)) {
                static::echoStyle('yellow', 'W');
                $messages['warning'][] = $helpText;
            } else {
                static::echoStyle('green', '.');
            }
        }

        if ($checkPassed) {
            static::echoBlock('success', 'OK', 'Your system is ready to run Bolt');
        } else {
            static::echoBlock('error', 'ERROR', 'Your system is not ready to run Bolt');
            static::echoTitle('Fix the following mandatory requirements', 'red');
            foreach ($messages['error'] as $helpText) {
                echo ' * ' . $helpText . PHP_EOL;
            }
        }

        if (!empty($messages['warning'])) {
            static::echoTitle('Optional recommendations to improve your setup', 'yellow');

            foreach ($messages['warning'] as $helpText) {
                echo ' * ' . $helpText . PHP_EOL;
            }
        }

        static::echoFinish($autoloader);

        exit($checkPassed ? 0 : 1);
    }

    /**
     * @param string $autoloader
     */
    private static function echoFinish($autoloader)
    {
        $base = dirname(dirname(realpath($autoloader))) . DIRECTORY_SEPARATOR;
        $indexFile = realpath(dirname(__DIR__) . '/public/check.php');
        $indexFile = str_replace($base, '', $indexFile);

        echo PHP_EOL;
        static::echoStyle('title', 'Note');
        echo '  The command console could use a different php.ini file' . PHP_EOL;
        static::echoStyle('title', '~~~~');
        echo '  than the one used with your web server. To be on the' . PHP_EOL;
        echo '      safe side, please check the requirements from your web' . PHP_EOL;
        echo '      server using the built-in web server, e.g.:';
        echo PHP_EOL . PHP_EOL . '      ';
        static::echoStyle('yellow', 'php -S localhost:8000 ' . $indexFile);
        echo PHP_EOL . PHP_EOL;
    }

    /**
     * @param Requirement $requirement
     * @param int         $lineSize
     *
     * @return null|string
     */
    private static function getErrorMessage(Requirement $requirement, $lineSize)
    {
        if ($requirement->isFulfilled()) {
            return null;
        }

        $errorMessage = wordwrap($requirement->getTestMessage(), $lineSize - 3, PHP_EOL . '   ') . PHP_EOL;
        $errorMessage .= '   > ' . wordwrap($requirement->getHelpText(), $lineSize - 5, PHP_EOL . '   > ') . PHP_EOL;

        return $errorMessage;
    }

    /**
     * @param string      $title
     * @param string|null $style
     */
    private static function echoTitle($title, $style = null)
    {
        $style = $style ?: 'title';

        echo PHP_EOL;
        static::echoStyle($style, $title . PHP_EOL);
        static::echoStyle($style, str_repeat('~', strlen($title)) . PHP_EOL);
        echo PHP_EOL;
    }

    /**
     * @param string $style
     * @param string $message
     */
    private static function echoStyle($style, $message)
    {
        // ANSI color codes
        $styles = [
            'reset'   => "\033[0m",
            'red'     => "\033[31m",
            'green'   => "\033[32m",
            'yellow'  => "\033[33m",
            'error'   => "\033[37;41m",
            'success' => "\033[37;42m",
            'title'   => "\033[34m",
        ];
        $supports = static::hasColorSupport();

        echo($supports ? $styles[$style] : '') . $message . ($supports ? $styles['reset'] : '');
    }

    /**
     * @param string $style
     * @param string $title
     * @param string $message
     */
    private static function echoBlock($style, $title, $message)
    {
        $message = ' ' . trim($message) . ' ';
        $width = strlen($message);

        echo PHP_EOL . PHP_EOL;

        static::echoStyle($style, str_repeat(' ', $width));
        echo PHP_EOL;
        static::echoStyle($style, str_pad(' [' . $title . ']', $width, ' ', STR_PAD_RIGHT));
        echo PHP_EOL;
        static::echoStyle($style, $message);
        echo PHP_EOL;
        static::echoStyle($style, str_repeat(' ', $width));
        echo PHP_EOL;
    }

    /**
     * @return bool
     */
    private static function hasColorSupport()
    {
        static $support;

        if (null === $support) {
            if (DIRECTORY_SEPARATOR == '\\') {
                $support = false !== getenv('ANSICON') || 'ON' === getenv('ConEmuANSI');
            } else {
                $support = function_exists('posix_isatty') && @posix_isatty(STDOUT);
            }
        }

        return $support;
    }
}
