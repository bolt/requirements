<?php

namespace Bolt\Requirement;

use Collator;
use DateTimeZone;
use PDO;
use ReflectionExtension;
use Symfony\Requirements\RequirementCollection;

/**
 * This class specifies all requirements and optional recommendations that
 * are necessary to run Bolt.
 *
 * @author Tobias Schultze <http://tobion.de>
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class BoltRequirements extends RequirementCollection
{
    const LEGACY_REQUIRED_PHP_VERSION = '5.3.3';
    const REQUIRED_PHP_VERSION = '5.5.9';

    /** @var string */
    protected $checkPath;
    /** @var null */
    private $boltVersion;

    /**
     * Constructor.
     *
     * @param string      $checkPath
     * @param string|null $boltVersion
     */
    public function __construct($checkPath = __DIR__, $boltVersion = null)
    {
        $this->checkPath = $checkPath;
        if ($boltVersion === null) {
            if (!class_exists('Bolt\Version', true)) {
                throw new \BadMethodCallException(sprintf(
                    '%s requires either \Bolt\Version to be loadable, or a SemVer version string passed as the second ' .
                    'contructor parameter, e.g. "1.2.3"',
                    __CLASS__
                ));
            }
            $this->boltVersion = \Bolt\Version::forComposer();
        }

        $this->setRequirements();
        $this->setRecommendations();
    }

    /**
     * Mandatory requirements
     */
    protected function setRequirements()
    {
        $installedPhpVersion = phpversion();
        $requiredPhpVersion = $this->getPhpRequiredVersion();

        $this->addRecommendation(
            $requiredPhpVersion,
            'Vendors should be installed in order to check all requirements.',
            'Run the <code>composer install</code> command.',
            'Run the "composer install" command.'
        );

        if (false !== $requiredPhpVersion) {
            $this->addRequirement(
                version_compare($installedPhpVersion, $requiredPhpVersion, '>='),
                sprintf('PHP version must be at least %s (%s installed)', $requiredPhpVersion, $installedPhpVersion),
                sprintf(
                    'You are running PHP version "<strong>%s</strong>", but Bolt needs at least PHP "<strong>%s</strong>" to run.
                Before using Bolt, upgrade your PHP installation, preferably to the latest version.',
                    $installedPhpVersion,
                    $requiredPhpVersion
                ),
                sprintf('Install PHP %s or newer (installed version is %s)', $requiredPhpVersion, $installedPhpVersion)
            );
        }

        $this->addRequirement(
            is_dir($this->checkPath . '/vendor/composer'),
            'Vendor libraries must be installed',
            'Vendor libraries are missing. Install composer following instructions from <a href="http://getcomposer.org/">http://getcomposer.org/</a>. ' .
            'Then run "<strong>php composer.phar install</strong>" to install them.'
        );

        $cacheDir = version_compare($this->boltVersion, '3.99999', '>') ? 'var/cache/' : 'app/cache/';

        $this->addRequirement(
            is_writable($this->checkPath . DIRECTORY_SEPARATOR . $cacheDir),
            sprintf('%s directory must be writable', $cacheDir),
            sprintf('Change the permissions of "<strong>%s</strong>" directory so that the web server can write into it.', $cacheDir)
        );
/*
        $this->addRequirement(
            is_writable($this->checkPath . '/var/logs'),
            'app/logs/ or var/logs/ directory must be writable',
            'Change the permissions of either "<strong>app/logs/</strong>" or  "<strong>var/logs/</strong>" directory so that the web server can write into it.'
        );
*/
        if (version_compare($installedPhpVersion, '7.0.0', '<')) {
            $this->addPhpConfigRequirement(
                'date.timezone',
                true,
                false,
                'date.timezone setting must be set',
                'Set the "<strong>date.timezone</strong>" setting in php.ini<a href="#phpini">*</a> (like Europe/Paris).'
            );
        }

        if (false !== $requiredPhpVersion && version_compare($installedPhpVersion, $requiredPhpVersion, '>=')) {
            $timezones = [];
            foreach (DateTimeZone::listAbbreviations() as $abbreviations) {
                foreach ($abbreviations as $abbreviation) {
                    $timezones[$abbreviation['timezone_id']] = true;
                }
            }

            $this->addRequirement(
                isset($timezones[@date_default_timezone_get()]),
                sprintf(
                    'Configured default timezone "%s" must be supported by your installation of PHP',
                    @date_default_timezone_get()
                ),
                'Your default timezone is not supported by PHP. Check for typos in your <strong>php.ini</strong> file and have a look at the list of deprecated timezones at <a href="http://php.net/manual/en/timezones.others.php">http://php.net/manual/en/timezones.others.php</a>.'
            );
        }

        $this->addRequirement(
            function_exists('iconv'),
            'iconv() must be available',
            'Install and enable the <strong>iconv</strong> extension.'
        );

        $this->addRequirement(
            function_exists('json_encode'),
            'json_encode() must be available',
            'Install and enable the <strong>JSON</strong> extension.'
        );

        $this->addRequirement(
            function_exists('session_start'),
            'session_start() must be available',
            'Install and enable the <strong>session</strong> extension.'
        );

        $this->addRequirement(
            function_exists('ctype_alpha'),
            'ctype_alpha() must be available',
            'Install and enable the <strong>ctype</strong> extension.'
        );

        $this->addRequirement(
            function_exists('token_get_all'),
            'token_get_all() must be available',
            'Install and enable the <strong>Tokenizer</strong> extension.'
        );

        $this->addRequirement(
            function_exists('simplexml_import_dom'),
            'simplexml_import_dom() must be available',
            'Install and enable the <strong>SimpleXML</strong> extension.'
        );

        if (function_exists('apc_store') && ini_get('apc.enabled')) {
            if (version_compare($installedPhpVersion, '5.4.0', '>=')) {
                $this->addRequirement(
                    version_compare(phpversion('apc'), '3.1.13', '>='),
                    'APC version must be at least 3.1.13 when using PHP 5.4',
                    'Upgrade your <strong>APC</strong> extension (3.1.13+).'
                );
            } else {
                $this->addRequirement(
                    version_compare(phpversion('apc'), '3.0.17', '>='),
                    'APC version must be at least 3.0.17',
                    'Upgrade your <strong>APC</strong> extension (3.0.17+).'
                );
            }
        }

        $this->addPhpConfigRequirement('detect_unicode', false);

        if (extension_loaded('suhosin')) {
            $this->addPhpConfigRequirement(
                'suhosin.executor.include.whitelist',
                create_function('$cfgValue', 'return false !== stripos($cfgValue, "phar");'),
                false,
                'suhosin.executor.include.whitelist must be configured correctly in php.ini',
                'Add "<strong>phar</strong>" to <strong>suhosin.executor.include.whitelist</strong> in php.ini<a href="#phpini">*</a>.'
            );
        }

        if (extension_loaded('xdebug')) {
            $this->addPhpConfigRequirement(
                'xdebug.show_exception_trace',
                false,
                true
            );

            $this->addPhpConfigRequirement(
                'xdebug.scream',
                false,
                true
            );

            $this->addPhpConfigRecommendation(
                'xdebug.max_nesting_level',
                create_function('$cfgValue', 'return $cfgValue > 500;'),
                true,
                'xdebug.max_nesting_level should be above 500 in php.ini',
                'Set "<strong>xdebug.max_nesting_level</strong>" to e.g. "<strong>500</strong>" in php.ini<a href="#phpini">*</a> to stop Xdebug\'s infinite recursion protection erroneously throwing a fatal error in your project.'
            );
        }

        $pcreVersion = defined('PCRE_VERSION') ? (float) PCRE_VERSION : null;

        $this->addRequirement(
            null !== $pcreVersion,
            'PCRE extension must be available',
            'Install the <strong>PCRE</strong> extension (version 8.0+).'
        );

        if (extension_loaded('mbstring')) {
            $this->addPhpConfigRequirement(
                'mbstring.func_overload',
                create_function('$cfgValue', 'return (int) $cfgValue === 0;'),
                true,
                'string functions should not be overloaded',
                'Set "<strong>mbstring.func_overload</strong>" to <strong>0</strong> in php.ini<a href="#phpini">*</a> to disable function overloading by the mbstring extension.'
            );
        }
    }

    /**
     * Optional recommendations
     */
    protected function setRecommendations()
    {
        $pcreVersion = defined('PCRE_VERSION') ? (float) PCRE_VERSION : null;

        if (file_exists($this->checkPath . '/vendor/composer')) {
            require_once $this->checkPath . '/vendor/autoload.php';
        }

        if (null !== $pcreVersion) {
            $this->addRecommendation(
                $pcreVersion >= 8.0,
                sprintf('PCRE extension should be at least version 8.0 (%s installed)', $pcreVersion),
                '<strong>PCRE 8.0+</strong> is preconfigured in PHP since 5.3.2 but you are using an outdated version of it. Bolt probably works anyway but it is recommended to upgrade your PCRE extension.'
            );
        }

        $this->addRecommendation(
            class_exists('DomDocument'),
            'PHP-DOM and PHP-XML modules should be installed',
            'Install and enable the <strong>PHP-DOM</strong> and the <strong>PHP-XML</strong> modules.'
        );

        $this->addRecommendation(
            function_exists('mb_strlen'),
            'mb_strlen() should be available',
            'Install and enable the <strong>mbstring</strong> extension.'
        );

        $this->addRecommendation(
            function_exists('iconv'),
            'iconv() should be available',
            'Install and enable the <strong>iconv</strong> extension.'
        );

        $this->addRecommendation(
            function_exists('utf8_decode'),
            'utf8_decode() should be available',
            'Install and enable the <strong>XML</strong> extension.'
        );

        $this->addRecommendation(
            function_exists('filter_var'),
            'filter_var() should be available',
            'Install and enable the <strong>filter</strong> extension.'
        );

        if (!defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->addRecommendation(
                function_exists('posix_isatty'),
                'posix_isatty() should be available',
                'Install and enable the <strong>php_posix</strong> extension (used to colorize the CLI output).'
            );
        }

        $this->addRecommendation(
            extension_loaded('intl'),
            'intl extension should be available',
            'Install and enable the <strong>intl</strong> extension (used for validators).'
        );

        if (extension_loaded('intl')) {
            // in some WAMP server installations, new Collator() returns null
            $this->addRecommendation(
                null !== new Collator('fr_FR'),
                'intl extension should be correctly configured',
                'The intl extension does not behave properly. This problem is typical on PHP 5.3.x x64 WIN builds.'
            );

            // check for compatible ICU versions (only done when you have the intl extension)
            if (defined('INTL_ICU_VERSION')) {
                $version = INTL_ICU_VERSION;
            } else {
                $reflector = new ReflectionExtension('intl');

                ob_start();
                $reflector->info();
                $output = strip_tags(ob_get_clean());

                preg_match('/^ICU version +(?:=> )?(.*)$/m', $output, $matches);
                $version = $matches[1];
            }

            $this->addRecommendation(
                version_compare($version, '4.0', '>='),
                'intl ICU version should be at least 4+',
                'Upgrade your <strong>intl</strong> extension with a newer ICU version (4+).'
            );

            $this->addPhpConfigRecommendation(
                'intl.error_level',
                create_function('$cfgValue', 'return (int) $cfgValue === 0;'),
                true,
                'intl.error_level should be 0 in php.ini',
                'Set "<strong>intl.error_level</strong>" to "<strong>0</strong>" in php.ini<a href="#phpini">*</a> to inhibit the messages when an error occurs in ICU functions.'
            );
        }

        $accelerator =
            (extension_loaded('eaccelerator') && ini_get('eaccelerator.enable'))
            ||
            (extension_loaded('apc') && ini_get('apc.enabled'))
            ||
            (extension_loaded('Zend Optimizer+') && ini_get('zend_optimizerplus.enable'))
            ||
            (extension_loaded('Zend OPcache') && ini_get('opcache.enable'))
            ||
            (extension_loaded('xcache') && ini_get('xcache.cacher'))
            ||
            (extension_loaded('wincache') && ini_get('wincache.ocenabled'))
        ;

        $this->addRecommendation(
            $accelerator,
            'a PHP accelerator should be installed',
            'Install and/or enable a <strong>PHP accelerator</strong> (highly recommended).'
        );

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->addRecommendation(
                $this->getRealpathCacheSize() >= 5 * 1024 * 1024,
                'realpath_cache_size should be at least 5M in php.ini',
                'Setting "<strong>realpath_cache_size</strong>" to e.g. "<strong>5242880</strong>" or "<strong>5M</strong>" in php.ini<a href="#phpini">*</a> may improve performance on Windows significantly in some cases.'
            );
        }

        $this->addPhpConfigRecommendation('short_open_tag', false);

        $this->addPhpConfigRecommendation('magic_quotes_gpc', false, true);

        $this->addPhpConfigRecommendation('register_globals', false, true);

        $this->addPhpConfigRecommendation('session.auto_start', false);

        $this->addRecommendation(
            class_exists('PDO'),
            'PDO should be installed',
            'Install <strong>PDO</strong> (mandatory for Doctrine).'
        );

        if (class_exists('PDO')) {
            $drivers = PDO::getAvailableDrivers();
            $this->addRecommendation(
                count($drivers) > 0,
                sprintf('PDO should have some drivers installed (currently available: %s)', count($drivers) ? implode(', ', $drivers) : 'none'),
                'Install <strong>PDO drivers</strong> (mandatory for Doctrine).'
            );
        }
    }

    /**
     * Loads realpath_cache_size from php.ini and converts it to int.
     *
     * (e.g. 16k is converted to 16384 int)
     *
     * @return int
     */
    protected function getRealpathCacheSize()
    {
        $size = ini_get('realpath_cache_size');
        $size = trim($size);
        $unit = '';
        if (!ctype_digit($size)) {
            $unit = strtolower(substr($size, -1, 1));
            $size = (int) substr($size, 0, -1);
        }
        switch ($unit) {
            case 'g':
                return $size * 1024 * 1024 * 1024;
            case 'm':
                return $size * 1024 * 1024;
            case 'k':
                return $size * 1024;
            default:
                return (int) $size;
        }
    }

    /**
     * Defines PHP required version from Bolt version.
     *
     * @return string|false The PHP required version or false if it could not be guessed
     */
    protected function getPhpRequiredVersion()
    {
        if (!file_exists($path = $this->checkPath . '/composer.lock')) {
            return false;
        }

        $composerLock = json_decode(file_get_contents($path), true);
        foreach ($composerLock['packages'] as $package) {
            $name = $package['name'];
            if ('bolt/bolt' !== $name && 'symfony/debug' !== $name) {
                continue;
            }

            return (int) $package['version'][1] > 2 ? self::REQUIRED_PHP_VERSION : self::LEGACY_REQUIRED_PHP_VERSION;
        }

        return false;
    }
}
