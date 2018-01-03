<?php

namespace Bolt\Requirement;

use Bolt\Bootstrap;
use Bolt\Common\Ini;
use Bolt\Configuration\PathResolver;
use Bolt\Version;
use Collator;
use Composer\CaBundle\CaBundle;
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
final class BoltRequirements extends RequirementCollection
{
    const LEGACY_REQUIRED_PHP_VERSION = '5.5.9';
    const REQUIRED_PHP_VERSION = '7.0.8';

    /** @var string */
    private $boltVersion;
    /** @var string */
    private $rootPath;
    /** @var PathResolver|null */
    private $resolver;

    /**
     * Constructor.
     *
     * @param string            $checkPath
     * @param string|null       $boltVersion
     * @param PathResolver|null $resolver
     */
    public function __construct($checkPath = __DIR__, $boltVersion = null, PathResolver $resolver = null)
    {
        if ($boltVersion === null) {
            if (!class_exists(Version::class, true)) {
                throw new \BadMethodCallException(sprintf(
                    '%s requires either %s to be loadable, or a SemVer string passed as the second constructor parameter, e.g. "1.2.3"',
                    __CLASS__,
                    Version::class
                ));
            }
            $this->boltVersion = Version::forComposer();
        }
        $this->rootPath = $this->getRootDir($checkPath);

        // Load PathResolver from Bolt Application if it is installed
        if (class_exists(Bootstrap::class)) {
            try {
                $app = Bootstrap::run($this->rootPath);
                $app->boot();
                $resolver = $app['path_resolver'];
            } catch (\Exception $e) {
                // Ignore path checks if we can't configure a PathResolver.
            }
        }

        $this->resolver = $resolver;

        $this->setRequirements();
        $this->setRecommendations();
    }

    /**
     * Mandatory requirements.
     */
    protected function setRequirements()
    {
        $installedPhpVersion = phpversion();
        $requiredPhpVersion = $this->getPhpRequiredVersion();

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

        $this->addRequirement(
            is_dir($this->rootPath . '/vendor/composer'),
            'Vendor libraries must be installed',
            'Vendor libraries are missing. Install composer following instructions from <a href="http://getcomposer.org/">http://getcomposer.org/</a>. ' .
            'Then run "<strong>php composer.phar install</strong>" to install them.'
        );

        if ($this->resolver) {
            $cachePath = $this->resolver->resolve('cache');
            $this->addRequirement(
                is_writable($cachePath),
                sprintf('%s directory must be writable', $cachePath),
                sprintf('Change the permissions of "<strong>%s</strong>" directory so that the web server can write into it.', $cachePath)
            );

            if ($this->resolver->raw('logs') !== null) {
                $logsPath = $this->resolver->resolve('logs');
                $this->addRequirement(
                    is_writable($logsPath),
                    sprintf('%s directory must be writable', $logsPath),
                    sprintf('Change the permissions of "<strong>%s</strong>" directory so that the web server can write into it.', $logsPath)
                );
            }
        }

        if (version_compare($installedPhpVersion, '7.0.0', '<')) {
            $this->addPhpConfigRequirement(
                'date.timezone',
                true,
                false,
                'date.timezone setting must be set',
                'Set the "<strong>date.timezone</strong>" setting in php.ini<a href="#phpini">*</a> (like Europe/Paris).'
            );
        }

        if (version_compare($installedPhpVersion, $requiredPhpVersion, '>=')) {
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

        $this->addExtensionRequirement('iconv', 'iconv');
        $this->addExtensionRequirement('JSON', 'json_encode');
        $this->addExtensionRequirement('session', 'session_start');
        $this->addExtensionRequirement('ctype', 'ctype_alpha');
        $this->addExtensionRequirement('Tokenizer', 'token_get_all');
        $this->addExtensionRequirement('SimpleXML', 'simplexml_import_dom');

        if (function_exists('apc_store') && Ini::getBool('apc.enabled')) {
            $this->addRequirement(
                version_compare(phpversion('apc'), '3.1.13', '>='),
                'APC version must be at least 3.1.13',
                'Upgrade your <strong>APC</strong> extension (3.1.13+).'
            );
        }

        $this->addPhpConfigRequirement('detect_unicode', false);

        if (extension_loaded('suhosin')) {
            $this->addPhpConfigRequirement(
                'suhosin.executor.include.whitelist',
                function($cfgValue) { return false !== stripos($cfgValue, 'phar'); },
                false,
                'suhosin.executor.include.whitelist must be configured correctly in php.ini',
                'Add "<strong>phar</strong>" to <strong>suhosin.executor.include.whitelist</strong> in php.ini<a href="#phpini">*</a>.'
            );
        }

        if (extension_loaded('xdebug')) {
            $this->addPhpConfigRequirement('xdebug.show_exception_trace', false, true);

            $this->addPhpConfigRequirement('xdebug.scream', false, true);

            $this->addPhpConfigRecommendation(
                'xdebug.max_nesting_level',
                function($cfgValue) { return $cfgValue >= 500; },
                true,
                'xdebug.max_nesting_level should be at least 500 in php.ini',
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
                function($cfgValue) { return (int) $cfgValue === 0; },
                true,
                'string functions should not be overloaded',
                'Set "<strong>mbstring.func_overload</strong>" to <strong>0</strong> in php.ini<a href="#phpini">*</a> to disable function overloading by the mbstring extension.'
            );
        }
    }

    protected function addExtensionRequirement($extension, $functionToCheck)
    {
        $this->addRequirement(
            function_exists($functionToCheck),
            sprintf('%s() must be available', $functionToCheck),
            sprintf('Install and enable the <strong>%s</strong> extension.', $extension)
        );
    }

    /**
     * Optional recommendations.
     */
    protected function setRecommendations()
    {
        $this->addRecommendation(
            !empty(CaBundle::getSystemCaRootBundlePath()),
            'System TLS/SSL CA root bundle should be installed',
            'Some vendor libraries will attempt to use fallbacks, but will only be updated via running <code>composer update</code>' . PHP_EOL . PHP_EOL .
            'It is strongly recommended you, or your hosting provider, correctly sets up a system-wide TLS/SSL CA root bundle' . PHP_EOL . PHP_EOL .
            'See https://docs.bolt.cm/howto/curl-ca-certificates for more information.'
        );

        $pcreVersion = defined('PCRE_VERSION') ? (float) PCRE_VERSION : null;
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
                function($cfgValue) { return (int) $cfgValue === 0; },
                true,
                'intl.error_level should be 0 in php.ini',
                'Set "<strong>intl.error_level</strong>" to "<strong>0</strong>" in php.ini<a href="#phpini">*</a> to inhibit the messages when an error occurs in ICU functions.'
            );
        }

        $accelerator =
            (extension_loaded('eaccelerator') && Ini::getBool('eaccelerator.enable'))
            ||
            (extension_loaded('apc') && Ini::getBool('apc.enabled'))
            ||
            (extension_loaded('Zend Optimizer+') && Ini::getBool('zend_optimizerplus.enable'))
            ||
            (extension_loaded('Zend OPcache') && Ini::getBool('opcache.enable'))
            ||
            (extension_loaded('xcache') && Ini::getBool('xcache.cacher'))
            ||
            (extension_loaded('wincache') && Ini::getBool('wincache.ocenabled'))
        ;

        $this->addRecommendation(
            $accelerator,
            'A PHP accelerator should be installed for optimum performance',
            'Install and/or enable a <strong>PHP accelerator</strong> (highly recommended).'
        );

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->addRecommendation(
                Ini::getBytes('realpath_cache_size') >= 5 * 1024 * 1024,
                'realpath_cache_size should be at least 5M in php.ini',
                'Setting "<strong>realpath_cache_size</strong>" to e.g. "<strong>5242880</strong>" or "<strong>5M</strong>" in php.ini<a href="#phpini">*</a> may improve performance on Windows significantly in some cases.'
            );
        }

        $this->addPhpConfigRecommendation('short_open_tag', false);

        $this->addPhpConfigRecommendation('magic_quotes_gpc', false, true);

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
     * Finds the PHP required version from Bolt version.
     *
     * @return string
     */
    protected function getPhpRequiredVersion()
    {
        if (!file_exists($path = $this->rootPath . '/composer.lock')) {
            return self::LEGACY_REQUIRED_PHP_VERSION;
        }

        $composerLock = json_decode(file_get_contents($path), true);
        foreach ($composerLock['packages'] as $package) {
            if ($package['name'] === 'bolt/bolt') {
                return (int) $package['version'][1] > 3 ? self::REQUIRED_PHP_VERSION : self::LEGACY_REQUIRED_PHP_VERSION;
            }
        }

        return self::LEGACY_REQUIRED_PHP_VERSION;
    }

    /**
     * @param string $checkPath
     *
     * @return string
     */
    private function getRootDir($checkPath)
    {
        $dir = $checkPath;
        while (!file_exists($dir . '/composer.json')) {
            if ($dir === dirname($dir)) {
                break;
            }
            $dir = dirname($dir);
        }

        return $dir;
    }
}
