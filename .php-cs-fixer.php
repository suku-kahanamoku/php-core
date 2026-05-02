<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/api')
    ->name('*.php');

return (new Config())
    ->setRules([
        '@PSR12'                       => true,
        'array_syntax'                 => ['syntax' => 'short'],
        'no_unused_imports'            => true,
        'ordered_imports'              => ['sort_algorithm' => 'alpha'],
        'single_quote'                 => true,
        'trailing_comma_in_multiline'  => ['elements' => ['arrays', 'arguments', 'parameters']],
        'binary_operator_spaces'       => ['default' => 'align_single_space_minimal'],
        'no_extra_blank_lines'         => true,
    ])
    ->setLineEnding("\n")
    ->setFinder($finder);
