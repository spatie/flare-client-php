<?php

use Spatie\FlareClient\Context\ConsoleContextProvider;
use Spatie\FlareClient\FlareMiddleware\AddGitInformation;
use Spatie\FlareClient\Report;

function reportGitRemote(string $remote): ?string
{
    $originalDirectory = getcwd();
    $directory = sys_get_temp_dir().'/flare-git-'.uniqid();
    mkdir($directory);
    exec('git init -q '.escapeshellarg($directory));
    exec('git -C '.escapeshellarg($directory).' remote add origin '.escapeshellarg($remote));

    try {
        chdir($directory);

        $report = (new Report())->useContext(new ConsoleContextProvider());

        (new AddGitInformation())->handle($report, fn (Report $report) => $report);

        return $report->allContext()['git']['remote'] ?? null;
    } finally {
        chdir($originalDirectory);
        exec('rm -rf '.escapeshellarg($directory));
    }
}

it('strips embedded credentials from the git remote url', function () {
    expect(reportGitRemote('https://oauth2:secret-token@github.com/spatie/flare-client-php.git'))
        ->toBe('https://github.com/spatie/flare-client-php.git');
});

it('keeps git remote urls without credentials unchanged', function (string $remote) {
    expect(reportGitRemote($remote))->toBe($remote);
})->with([
    'https://github.com/spatie/flare-client-php.git',
    'git@github.com:spatie/flare-client-php.git',
]);
