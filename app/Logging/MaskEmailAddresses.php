<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

/**
 * Masks email addresses in log messages and context ("a***@example.com"),
 * so the log files hold no customers' or users' addresses (O7). Added to
 * the log channels with 'tap' in config/logging.php.
 */
class MaskEmailAddresses
{
    private const PATTERN = '/([A-Za-z0-9._%+\-])[A-Za-z0-9._%+\-]*@([A-Za-z0-9\-]+(?:\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,})/';

    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();
        if ($monolog instanceof \Monolog\Logger) {
            $monolog->pushProcessor(fn (LogRecord $record) => $record->with(
                message: self::mask($record->message),
                context: self::maskArray($record->context),
            ));
        }
    }

    public static function mask(string $text): string
    {
        return (string) preg_replace(self::PATTERN, '$1***@$2', $text);
    }

    /**
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    private static function maskArray(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($value)) {
                $values[$key] = self::mask($value);
            } elseif (is_array($value)) {
                $values[$key] = self::maskArray($value);
            }
        }

        return $values;
    }
}
