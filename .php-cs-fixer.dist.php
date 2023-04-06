<?php

$finder = PhpCsFixer\Finder::create()
    ->notPath('vendor')
    ->notPath('node_modules')
    ->in(__DIR__)
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
;

return (new PhpCsFixer\Config())
    ->setRules(array(
        '@Symfony' => true,
        'binary_operator_spaces' => [
           'operators' => ['=>' => 'single_space'],
        ],
        'linebreak_after_opening_tag' => true,
        'not_operator_with_successor_space' => true,
        'phpdoc_order' => false,
        'single_line_throw' => false,
        'phpdoc_no_empty_return' => false,
        'phpdoc_align' => ['align' => 'left'],
        'yoda_style' => false,
    ))
    ->setFinder($finder)
;
