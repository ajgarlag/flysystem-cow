<?php

$header = <<<EOF
AJGL Flysystem COW adapter

Copyright (C) Antonio J. García Lagar <aj@garcialagar.es>

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
EOF;

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/src'])
    ->in([__DIR__ . '/tests'])
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS' => true,
        '@PER-CS:risky' => true,
        '@PHP81Migration' => true,
        'header_comment' => ['header' => $header],
    ])
    ->setFinder($finder)
;
