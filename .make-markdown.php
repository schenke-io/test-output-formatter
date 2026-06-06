<?php

require_once __DIR__ . '/vendor/autoload.php';

use SchenkeIo\PackagingTools\Markdown\MarkdownAssembler;
use SchenkeIo\PackagingTools\Enums\SetupMessages;
use SchenkeIo\PackagingTools\Setup\Config;

$assembler = new MarkdownAssembler('resources/md');
$assembler->badges()->all();
$assembler    ->addMarkdown('header.md')->addTableOfContents();



$assembler->addMarkdown('installation.md')
    ->addMarkdown('features.md');

$assembler->skills()->all()->writeGuidelines(new \SchenkeIo\PackagingTools\Setup\ProjectContext(), 'resources/boost/guidelines/core.blade.php');

$assembler->classes()
    ->add(\SchenkeIo\TestOutputFormatter\PHPStan\ErrorFormatter::class)
    ->add(\SchenkeIo\TestOutputFormatter\PHPStan\CompactErrorFormatter::class)
    ->add(\SchenkeIo\TestOutputFormatter\PHPStan\JsonErrorFormatter::class);

$assembler->writeMarkdown('README.md');

Config::output(SetupMessages::readmeGenerated);