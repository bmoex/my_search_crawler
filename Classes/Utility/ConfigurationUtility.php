<?php

namespace Serfhos\MySearchCrawler\Utility;

use Serfhos\MySearchCrawler\Exception\InvalidConfigurationException;

/**
 * Utility: Extension Configuration
 *
 * @package Serfhos\MySearchCrawler\Utility
 */
class ConfigurationUtility
{
    public const EXTENSION = 'my_search_crawler';

    /**
     * @var array
     */
    public static $configuration;

    /**
     * Retrieve hosts in configuration
     *
     * @return array
     * @throws InvalidConfigurationException
     */
    public static function hosts(): array
    {
        $configuration = static::all();
        if (isset($configuration['elastic_search_hosts']) && is_array($configuration['elastic_search_hosts'])) {
            return $configuration['elastic_search_hosts'];
        }
        throw new InvalidConfigurationException('No hosts found in configuration', 1535981719668);
    }

    /**
     * @return string Defaults on extension name
     */
    public static function index(): string
    {
        $configuration = static::all();
        if (isset($configuration['elastic_search_index']) && is_string($configuration['elastic_search_index'])) {
            return $configuration['elastic_search_index'];
        }
        return self::EXTENSION;
    }

    /**
     * @return boolean
     */
    public static function verify(): bool
    {
        $configuration = static::all();
        if (isset($configuration['command_crawler_verify_ssl']) && is_string($configuration['command_crawler_verify_ssl'])) {
            return filter_var($configuration['command_crawler_verify_ssl'], FILTER_VALIDATE_BOOLEAN);
        }
        return true;
    }

    /**
     * Retrieve all configuration for extension
     *
     * @return array
     * @throws InvalidConfigurationException
     */
    public static function all(): array
    {
        if (static::$configuration === null) {
            $data = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][static::EXTENSION];
            // Re-retrieve data when referenced
            if (is_string($data)) {
                static::$configuration = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$data];
            } elseif (is_array($data) || $data instanceof \ArrayAccess) {
                static::$configuration = $data;
            }

            if (empty(static::$configuration)) {
                throw new InvalidConfigurationException('No extension configuration found', 1535981656946);
            }
        }

        return static::$configuration;
    }
}
