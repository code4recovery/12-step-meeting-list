<?php
$finder = (new PhpCsFixer\Finder())
	->ignoreDotFiles(true)
	->exclude(['dev-tools/phpstan', 'tests/Fixtures']) # paths to exclude
	->in(__DIR__)
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
    ])
    ->setFinder($finder)
;
