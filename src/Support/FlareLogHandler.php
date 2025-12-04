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
        // TODO: what if the logger is called before Flare has been resolved?
        // Is that technically possible?
        // If so, make Flare not a parameter but make it possible to be set and do this when booting Flare
        // Keep a map of early logs so that we can keep track of them

        $this->logger->log(
            timestampUnixNano: $record->datetime,
            body: $record->message,
            severityText: $record->level->getName(),
            severityNumber: SeverityMapper::fromSyslog($record->level->getName()),
            attributes: [
                'log.channel' => $record->channel,
                ...$record->context,
                ...$record->extra,
            ] // TODO: find a better format here
        );
    }
}
