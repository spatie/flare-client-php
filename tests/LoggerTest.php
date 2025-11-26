<?php

it('can log a statement', function () {
    $logger = setupFlare()->logger;

    $logger->log(
        body: "Hi there"
    );

    dd($logger);
});
