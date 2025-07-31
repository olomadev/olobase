<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'no_trailing_whitespace_in_comment' => true,
        'blank_line_at_end_of_file' => true,
    ])
    ->setFinder($finder);
