<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use Monolog\Level;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Logger;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Support\SeverityMapper;

class AddLogs implements FlareMiddleware
{
    const DEFAULT_MAX_LOGS_WITH_ERRORS = 100;

    const DEFAULT_MINIMAL_LOG_LEVEL_WITH_ERRORS = Level::Info;

    public static function register(ContainerInterface $container, array $config): Closure
    {
        return fn () => new self(
            $container->get(Logger::class),
            $config,
        );
    }

    public function __construct(
        protected Logger $logger,
        protected array $config,
    ) {
    }

    public function handle(ReportFactory $report, Closure $next): ReportFactory
    {
        $minimalLevel = $this->config['minimal_level'] ?? self::DEFAULT_MAX_LOGS_WITH_ERRORS;

        if (! $minimalLevel instanceof Level) {
            $minimalLevel = self::DEFAULT_MINIMAL_LOG_LEVEL_WITH_ERRORS;
        }

        $minimalLevel = SeverityMapper::fromMonolog($minimalLevel);

        $logs = array_values(array_filter(
            $this->logger->logs(),
            fn (array $log) => array_key_exists('severityNumber', $log) && $log['severityNumber'] >= $minimalLevel,
        ));

        $maxItems = $this->config['max_items_with_errors'] ?? self::DEFAULT_MAX_LOGS_WITH_ERRORS;

        if (count($logs) > $maxItems) {
            $logs = array_slice($logs, count($logs) - $maxItems);
        }

        return $report->spanEvent(...array_map(
            fn (array $log) => new SpanEvent(
                name: "Log entry",
                timestamp: $log['observedTimeUnixNano'],
                attributes: [
                    'flare.span_event_type' => SpanEventType::Log,
                    'log.message' => $log['body'],
                    'log.level' => $log['severityText'] ?? 'unknown',
                    'log.context' => $log['attributes']['log.context'] ?? [],
                ]
            ),
            $logs,
        ));
    }
}
