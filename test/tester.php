<?php

require_once '../vendor/autoload.php';

use Phpml\CrossValidation\StratifiedRandomSplit;
use Phpml\Metric\Accuracy;
use Phpml\Regression\SVR;
use Phpml\SupportVectorMachine\Kernel;
use Phpml\Pipeline;


use Atompulse\Labs\PricePrediction\PriceDataset;
use Atompulse\Labs\PricePrediction\Data\PriceDataTransformer;
use Atompulse\Labs\PricePrediction\Engine\PricePredictionEngine;

//$samples = [[5], [10], [15], [20], [25], [30], [35], [40], [45], [50]];
//$targets = [0.5,  1,    1.5,  2,    2.5,  3,    3.5,  4,    4.5,  5];
//
//$samples = [];
//$targets = [];
//$seed = 0;
//
//for ($i = 1; $i <= 100; $i++) {
////    $samples[] = [$seed + ($i * 5), $seed + ($i * 10), $seed + ($i * 15), $seed + ($i * 20)];
////    $targets[] = $i * 0.5;
//    $sample = [];
//    for ($j = 1; $j <= 15; $j++) {
//        $sample[] = $seed + ($i * $j);
//    }
//    $samples[] = $sample;
//    $targets[] = $i * 0.5;
//}
//print_r($samples);
//print_r($targets);
//
//
//$regression = new SVR(
//        Kernel::LINEAR, 1, 0.001, 1,
//        null, 0, 0.001,
//        5000, true);
//$regression->train($samples, $targets);
//
//print $regression->predict($samples[50]);
//
//die;
use Phpml\Math\Statistic\Correlation;

$trainDataset = new PriceDataset(__DIR__ . '/train.csv', 13);
$testDataset = new PriceDataset(__DIR__ . '/test.csv', 13);

//$dataset = new \Phpml\Dataset\ArrayDataset($samples, $targets);

//$transformers = [
//    new PriceDataTransformer()
//];


//$regression = new SVR(
//        Kernel::LINEAR, 1, 0.001, 1,
//        0.01, 5, 0.001,
//        5000, true);
//$pipeline = new Pipeline($transformers, $regression);
//$pipeline->train($trainDataset->getSamples(), $trainDataset->getTargets());
//$pipeline->predict();

$pricePredictionEngine = new PricePredictionEngine();
$pricePredictionEngine->train($trainDataset);
$predictions = $pricePredictionEngine->predict($trainDataset->getSamples());
print_r($predictions);
die;



$trainingSamples = $trainDataset->getSamples();
$trainingTargets = $trainDataset->getTargets();

foreach ($trainingTargets as &$target) {
    $target = [$target];
}

//print_r($trainingSamples);
//print_r($trainingTargets);
//die;

////print_r($testDataset->getSamples());die;
//$testSamples = $testDataset->getSamples();
//print_r($testSamples);
//$transformer = new PriceDataTransformer();
//$transformer->transform($testSamples);
//print_r($testSamples);

$transformer = new PriceDataTransformer();
$transformer->transform($trainingSamples);
$transformer->transform($trainingTargets);
//print_r($trainingSamples);
//print_r($trainingTargets);
die;

$predictions = $regression->predict(array_slice($trainingSamples, 0, 10));
$trainingTargets = array_slice($trainingTargets, 0, 10);
foreach ($predictions as $k => $prediction) {
    print "{$trainingTargets[$k][0]} => $prediction\n";
}


