<?php
require __DIR__ . "/../vendor/autoload.php";

// load all scripts in the api directory
$scripts = glob(__DIR__ . "/api/*.php");
foreach ($scripts as $script) {
    require $script;
}

// build the OpenAPI documentation and return it as YAML
$result = (new \OpenApi\Builder())
    ->addSource(__DIR__ . "/api")
    ->build();
header("Content-Type: application/x-yaml");
echo $result->toYaml();
