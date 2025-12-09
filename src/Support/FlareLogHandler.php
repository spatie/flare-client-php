<?php

namespace Spatie\FlareClient\Support;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Spatie\FlareClient\Logger;

class FlareLogHandler extends AbstractProcessingHandler
{
    public function __construct(
        protected Logger $logger,
        int|string|Level $level = Level::Debug,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $this->logger->log(
            timestampUnixNano: $record->datetime,
            body: $record->message,
            severityText: $record->level->getName(),
            severityNumber: SeverityMapper::fromSyslog($record->level->getName()),
            attributes: [
                'log.channel' => $record->channel,
                'log.context' => $record->context,
            ]
        );
    }
}
