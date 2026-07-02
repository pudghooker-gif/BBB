<?php

namespace VanguardLTE\Logging;

use Monolog\Formatter\JsonFormatter;

class B2BJsonFormatter
{
    public function __invoke($logger)
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter(new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true));
        }
    }
}
