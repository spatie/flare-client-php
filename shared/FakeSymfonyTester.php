<?php

namespace Spatie\FlareClient\Tests\Shared;

use Closure;
use Spatie\FlareClient\Api;
use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Memory\Memory;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Senders\Exceptions\BadResponseCode;
use Spatie\FlareClient\Support\Container;
use Spatie\FlareClient\Support\Ids;
use Spatie\FlareClient\Support\SymfonyTester;
use Spatie\FlareClient\Time\Time;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

class FakeSymfonyTester extends SymfonyTester
{
    public BufferedOutput $bufferedOutput;

    /** @var array<string, bool> */
    public array $entityEnabled = [];

    public bool $stackFrameArgumentsOn = false;

    /** @var array<int, array{0:string, 1:string}> */
    public array $extraEnvironmentRows = [];

    public ?Closure $preCheckCallback = null;

    /** @param array<string, bool> $options */
    public static function create(array $options = [], ?FlareConfig $config = null): self
    {
        $container = Container::instance();

        $output = new BufferedOutput();

        $instance = new self(
            api: $container->get(Api::class),
            ids: $container->get(Ids::class),
            time: $container->get(Time::class),
            memory: $container->get(Memory::class),
            resource: $container->get(Resource::class),
            reportFactory: $container->get(ReportFactory::class),
            config: $config ?? new FlareConfig(apiToken: 'fake-api-key'),
            input: self::buildInput($options),
            output: $output,
        );

        $instance->bufferedOutput = $output;

        return $instance;
    }

    /** @param array<string, bool> $options */
    public static function buildInput(array $options = []): ArrayInput
    {
        $params = [];

        foreach ($options as $name => $value) {
            $params['--'.$name] = $value;
        }

        $input = new ArrayInput($params);
        $input->bind(new InputDefinition([
            new InputOption('errors', null, InputOption::VALUE_NONE),
            new InputOption('logs', null, InputOption::VALUE_NONE),
            new InputOption('traces', null, InputOption::VALUE_NONE),
        ]));

        return $input;
    }

    public function output(): string
    {
        return $this->bufferedOutput->fetch();
    }

    public function sendErrorPayload(): void
    {
        parent::sendErrorPayload();
    }

    public function sendTracePayload(): void
    {
        parent::sendTracePayload();
    }

    public function sendLogPayload(): void
    {
        parent::sendLogPayload();
    }

    public function environmentInfoRows(): array
    {
        return $this->environmentInfo();
    }

    public function describeBadResponseFor(BadResponseCode $exception): string
    {
        return $this->describeBadResponse($exception);
    }

    public function shouldWarnAboutStackFrameArgumentsIniSettingFor(bool $stackFrameArgumentsEnabled): bool
    {
        return $this->shouldWarnAboutStackFrameArgumentsIniSetting($stackFrameArgumentsEnabled);
    }

    public function writeLinePublic(string $message, string $style = self::STYLE_PLAIN): void
    {
        $this->writeLine($message, $style);
    }

    protected function isEntityEnabled(FlareEntityType $type): bool
    {
        return $this->entityEnabled[$type->value] ?? true;
    }

    protected function stackFrameArgumentsEnabled(): bool
    {
        return $this->stackFrameArgumentsOn;
    }

    protected function environmentInfo(): array
    {
        return [
            ...parent::environmentInfo(),
            ...$this->extraEnvironmentRows,
        ];
    }

    protected function preCheckEntity(FlareEntityType $type): bool
    {
        if (! parent::preCheckEntity($type)) {
            return false;
        }

        if ($this->preCheckCallback === null) {
            return true;
        }

        return ($this->preCheckCallback)($type, $this);
    }
}
