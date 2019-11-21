<?php

namespace Serfhos\MySearchCrawler\Exception;

use LogicException;

/**
 * Exception: Invalid configuration
 */
class RequestNotFoundException extends LogicException implements ExtensionException
{
}
