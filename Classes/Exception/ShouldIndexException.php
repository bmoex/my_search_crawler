<?php

namespace Serfhos\MySearchCrawler\Exception;

use LogicException;

/**
 * Exception: Throws by lookup of shouldIndex()
 */
class ShouldIndexException extends LogicException implements ExtensionException
{
    /** @var array */
    public $context;

    /**
     * @param  string  $message
     * @param  array  $context
     * @param int $code
     */
    public static function throw(string $message, array $context, int $code): void
    {
        $exception = new static($message, $code);
        $exception->context = $context;
        throw $exception;
    }
}
