<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config)->setUsingCache(true)
                   ->setRiskyAllowed(true)
                   ->setRules(
                       [
                           '@PSR2'                                  => true,
                           'psr_autoloading'                        => true,
                           'linebreak_after_opening_tag'            => true,
                           'multiline_whitespace_before_semicolons' => true,
                           'no_php4_constructor'                    => true,
                           'no_useless_else'                        => true,
                           'ordered_imports'                        => true,
                           'php_unit_construct'                     => true,
                           'phpdoc_order'                           => true,
                           'pow_to_exponentiation'                  => true,
                           'random_api_migration'                   => true,
                           'align_multiline_comment'                => true,
                           'phpdoc_types_order'                     => true,
                           'no_null_property_initialization'        => true,
                           'no_unneeded_final_method'               => true,
                           'no_unneeded_curly_braces'               => true,
                           'no_superfluous_elseif'                  => true,
                           'trailing_comma_in_multiline'            => true,
                           'no_unused_imports'                      => true,
                           'include'                                => true,
                           'array_syntax'                           => [
                               'syntax' => 'short',
                           ],
                       ]
                   )
                   ->setFinder(
                       (Finder::create())->in(
                           [
                               __DIR__ . '/src/main',
                               __DIR__ . '/src/test',
                           ]
                       )
                                         ->files()
                                         ->name('*.php')
                   );
