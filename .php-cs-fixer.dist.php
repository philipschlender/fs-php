<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = new Finder();
$finder->in(__DIR__);

$rules = [
    '@PSR12' => true,
    '@Symfony' => true,
];

$config = new Config();
$config->setFinder($finder)
    ->setRules($rules);

return $config;
