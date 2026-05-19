<?php

namespace Spatie\FlareClient\Support;

use Spatie\FlareClient\Api;
use Spatie\FlareClient\EntryPoint\EntryPoint;
use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Memory\Memory;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Time\Time;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SymfonyTester extends Tester
{
    protected SymfonyStyle $io;

    public function __construct(
        Api $api,
        Ids $ids,
        Time $time,
        Memory $memory,
        Resource $resource,
        ReportFactory $reportFactory,
        FlareConfig $config,
        protected InputInterface $input,
        protected OutputInterface $output,
    ) {
        parent::__construct(
            api: $api,
            ids: $ids,
            time: $time,
            memory: $memory,
            resource: $resource,
            reportFactory: $reportFactory,
            config: $config
        );

        $this->io = new SymfonyStyle($input, $output);
    }

    /** @return array<int, FlareEntityType> */
    protected function testEntityTypes(): array
    {
        $errors = $this->input->hasOption('errors') && (bool) $this->input->getOption('errors');
        $logs = $this->input->hasOption('logs') && (bool) $this->input->getOption('logs');
        $traces = $this->input->hasOption('traces') && (bool) $this->input->getOption('traces');

        $testAll = ! $errors && ! $logs && ! $traces;

        $types = [];

        if ($testAll || $errors) {
            $types[] = FlareEntityType::Errors;
        }

        if ($testAll || $traces) {
            $types[] = FlareEntityType::Traces;
        }

        if ($testAll || $logs) {
            $types[] = FlareEntityType::Logs;
        }

        return $types;
    }

    protected function writeLine(string $message, string $style = self::STYLE_PLAIN): void
    {
        if ($style === self::STYLE_PLAIN) {
            $this->io->writeln($message);

            return;
        }

        $tag = match ($style) {
            self::STYLE_WARNING => 'comment',
            self::STYLE_ERROR => 'error',
            default => 'info',
        };

        $this->io->writeln("<{$tag}>{$message}</{$tag}>");
    }

    protected function writeNewline(): void
    {
        $this->io->newLine();
    }

    protected function buildEntryPoint(): EntryPoint
    {
        $entryPoint = new EntryPoint(
            type: EntryPointType::Cli,
            value: 'flare:test',
        );

        $entryPoint->setHandler(
            handlerIdentifier: 'flare:test',
            handlerName: static::class,
            handlerType: 'php_command',
        );

        return $entryPoint;
    }
}
