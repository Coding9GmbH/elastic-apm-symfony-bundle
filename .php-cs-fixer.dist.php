<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->exclude('vendor')
    ->exclude('node_modules')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'array_syntax' => ['syntax' => 'short'],
        'protected_to_private' => false,
        'native_constant_invocation' => false,
        'native_function_invocation' => false,
        'combine_consecutive_unsets' => true,
        'concat_space' => ['spacing' => 'one'],
        'multiline_whitespace_before_semicolons' => false,
        'single_quote' => true,
        'blank_line_after_opening_tag' => true,
        'blank_line_before_statement' => true,
        'declare_strict_types' => false,
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
        'ordered_imports' => true,
        'phpdoc_align' => false,
        'phpdoc_summary' => false,
        'phpdoc_to_comment' => false,
        'phpdoc_types_order' => ['null_adjustment' => 'always_last'],
        'strict_comparison' => true,
        'strict_param' => true,
        'array_indentation' => true,
        'compact_nullable_typehint' => true,
        'modernize_types_casting' => true,
        'no_null_property_initialization' => true,
        'no_superfluous_elseif' => true,
        'no_useless_else' => true,
        'ordered_class_elements' => true,
        'return_assignment' => true,
        'void_return' => false,
        'yoda_style' => false,
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder)
    ->setCacheFile('.php-cs-fixer.cache')
;