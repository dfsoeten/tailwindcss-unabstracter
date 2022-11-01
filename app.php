#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use DFSoeten\TailwindcssUnabstracter\Command\TailwindUnabstractCommand;
use Symfony\Component\Console\Application;

$application = new Application();

$application->addCommands([
    new TailwindUnabstractCommand()
]);

$application->run();