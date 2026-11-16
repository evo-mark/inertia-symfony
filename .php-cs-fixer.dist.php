<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@auto' => true,
        '@Symfony' => true,
        // Allow nested indentation in config tree builders
        'method_chaining_indentation' => false,
        // Keep explicit imports, even for classes in the same namespace
        'no_unused_imports' => false,
        // Allow combined imports: `use A, B;` and `use Ns\{A, B};`
        'single_import_per_statement' => false,
    ])
    // 💡 by default, Fixer looks for `*.php` files excluding `./vendor/` - here, you can groom this config
    ->setFinder(
        (new Finder())
            // 💡 root folder to check
            ->in(__DIR__)
            
    )
;
