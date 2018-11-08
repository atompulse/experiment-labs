<?php

require_once '../vendor/autoload.php';

use Phpml\Metric\ClassificationReport;

use Atompulse\Labs\PricePrediction\PriceDataset;
use Atompulse\Labs\PricePrediction\Engine\PricePredictionEngine;

$trainDataset = new PriceDataset(__DIR__ . '/data/train.csv', 13);
$testDataset = new PriceDataset(__DIR__ . '/data/test.csv', 13);

# train
$pricePredictionEngine = new PricePredictionEngine();
$pricePredictionEngine->train($trainDataset);

# test training samples
$predictions = $pricePredictionEngine->predict($trainDataset->getSamples());
$targets = $trainDataset->getTargets();
$report = new ClassificationReport($targets, $predictions);
print_r($report->getAverage());

# test training samples
$predictions = $pricePredictionEngine->predict($testDataset->getSamples());
print_r($predictions);
