<?php
/*
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2024 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace ElGigi\FlysystemUsefulAdapters\Tests;

use Psr\Log\AbstractLogger;
use Stringable;

class FakeLogger extends AbstractLogger
{
    public array $logs = [];

    /**
     * @inheritDoc
     */
    public function log($level, Stringable|string $message, array $context = []): void
    {
        array_walk(
            $context,
            function ($value, $key) use (&$message) {
                is_bool($value) && $value = (int)$value;
                $message = str_replace('{' . $key . '}', (string)$value, $message);
            },
        );
        $this->logs[$level][] = $message;
    }

    public function getLogs(): array
    {
        return $this->logs;
    }
}