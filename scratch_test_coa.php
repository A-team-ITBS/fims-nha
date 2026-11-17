<?php
chdir(__DIR__);
require 'vendor/autoload.php';
require 'src/constants.php';
require 'src/config.php';
require 'src/phpfn.php';
global $CONFIG, $ConfigData;
$ConfigData = new \Dflydev\DotAccessData\Data($CONFIG);
require 'src/userfn.php';
$builder = new \DI\ContainerBuilder();
require 'app/services/ChartOfAccountsService.php';
$svc = new \PHPMaker2024\FIMS\Service\ChartOfAccountsService();
$res = $svc->fetchPostable('', false);
echo "count: " . count($res['data'] ?? []) . "\n";
echo json_encode(array_slice($res['data'] ?? [], 0, 2), JSON_PRETTY_PRINT) . "\n";
