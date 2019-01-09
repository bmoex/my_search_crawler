<?php

namespace Serfhos\MySearchCrawler\Exception;

/**
 * Exception: Throws by lookup of shouldIndex()
 *
 * @package Serfhos\MySearchCrawler\Exception
 */
class ShouldIndexException extends \LogicException implements ExtensionException
{
    /**
     * @var array
     */
    protected $context;

    /**
     * @param string $message
     * @param array $context
     * @param integer $code
     */
    public static function throw(string $message, array $context, int $code)
    {
        $exception = new static($message, $code);
        $exception->context = $context;
        throw $exception;
    }
}
