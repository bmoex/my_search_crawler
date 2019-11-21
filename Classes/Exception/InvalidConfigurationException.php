<?php

namespace Serfhos\MySearchCrawler\Exception;

use UnexpectedValueException;

/**
 * Exception: Invalid configuration
 */
class InvalidConfigurationException extends UnexpectedValueException implements ExtensionException
{
}
