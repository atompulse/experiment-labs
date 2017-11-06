<?php

namespace Atompulse\Labs\PricePrediction\Engine;

use Atompulse\Labs\PricePrediction\PriceDataset;
use Phpml\SupportVectorMachine\Kernel;
use Phpml\Math\Statistic\Correlation;
use Phpml\Regression\SVR;

/**
 * Class PricePredictionEngine
 * @package Atompulse\Labs\PricePrediction\Engine
 *
 * @author Petru Cojocar <petru.cojocar@gmail.com>
 */
class PricePredictionEngine
{
    /**
     * @var array
     */
    protected $transformers = null;

    /**
     * @var float
     */
    protected $categoricalBoundary = 0.1; // feature values are categorical if they converge to 1

    /**
     * @param array|null $transformers
     * @param float|null $categoricalBoundary
     */
    public function __construct(array $transformers = null, float $categoricalBoundary = null)
    {
        // initialize stored transformers
        if ($transformers) {
            $this->transformers = $transformers;
            $this->originalState = $transformers;
        }
        if ($categoricalBoundary) {
            $this->categoricalBoundary = $categoricalBoundary;
        }

        $kernel = Kernel::RBF;
        $degree = 3;
        $epsilon = 0.1;
        // A large C gives you low bias and high variance. A small C gives you higher bias and lower variance.
        $cost = 999999;
        $gamma = null;
        $coef0 = 0.0;
        $tolerance = 0.001;
        $cacheSize = 1000;
        $shrinking = true;
        // initialize support vector regression
        $this->regression = new SVR($kernel, $degree, $epsilon, $cost, $gamma, $coef0, $tolerance, $cacheSize, $shrinking);
    }

    /**
     * Train model using a dataset
     * @param PriceDataset $dataset
     * @return array
     */
    public function train(PriceDataset $dataset)
    {
        // transpose data from rows to columns
        $columnarDataSamples = $this->getVertex($dataset->getSamples());

        // analyze all values from all columns to figure out what type of data
        // we are dealing with so we can transform them to a more formal structure
        // that we can feed into the SVR;
        // we want to know if features are categorical or variables;
        // we do this naively by analyzing the occurrences of values in columns and noticing
        // the patterns that occur
        // we use a configurable categoricalBoundary to be able to fine-tune this automated analysis

        foreach ($columnarDataSamples as $column => $columnSamples) {
            // handle empty values
            foreach ($columnSamples as &$rawValue) {
                if (empty($rawValue)) {
                    $rawValue = 0;
                }
            }

            $pivot = array_count_values($columnSamples);
            $totalValues = count($columnSamples);
            $uniqueValues = count(array_keys($pivot));
            $ratio = round($totalValues / ($uniqueValues * 100), 2); // ratio
            // check feature type
            switch ($ratio) {
                case $ratio > $this->categoricalBoundary :
                    $this->transformers[$column] = [
                        'transformer' => 'Categorical',
                        'ratio' => $ratio
                    ];
                    break;
                default:
                    $this->transformers[$column] = [
                        'transformer' => 'Continuous',
                        'ratio' => $ratio
                    ];
                    break;
            }

            // check feature value type
            // check if all values are numeric in this column
            if ($columnSamples == array_filter($columnSamples, 'is_numeric')) {
                $this->transformers[$column]['TYPE'] = 'NUMERIC';
                // determine feature/price correlation
                $this->transformers[$column]['C'] = Correlation::pearson($columnSamples, $dataset->getTargets());
            } else {
                $this->transformers[$column]['TYPE'] = 'STRING';
                $values = [];
                $ord = 1;
                foreach ($pivot as $value => $frequency) {
                    $values[$value] = 1 / (1 + exp($ord));
                    $ord++;
                }
                // store categorical values
                $this->transformers[$column]['VALUES'] = $values;

                $columnSamplesTransformed = [];
                foreach ($columnSamples as $key => $value) {
                    $columnSamplesTransformed[$key] = $this->transformers[$column]['VALUES'][$value];
                }
                // determine feature/price correlation
                $this->transformers[$column]['C'] = Correlation::pearson($columnSamplesTransformed, $dataset->getTargets());
            }
        }

        $data = $this->transformSamples($dataset->getSamples());

        $this->regression->train($data, $dataset->getTargets());

        return $this->transformers;
    }

    /**
     * @param array $samples
     * @return array
     */
    public function predict(array $samples)
    {
        // if using only predict, then before this, when doing only training, the transformers should be stored
        // and then restored before attempting to predict
        if (!$this->transformers) {
            throw new \Exception("Data transformers not initialized");
        }

        $data = $this->transformSamples($samples);

        return $this->regression->predict($data);
    }

    /**
     * @param array $samples
     * @return array
     * @throws \Exception
     */
    protected function transformSamples(array $samples)
    {
        // transform all samples
        foreach ($samples as &$sample) {
            // transform 1 sample
            foreach ($sample as $column => &$value) {
                // drop empty values or features which dont correlate to price
                if (empty($value) || $this->transformers[$column]['C'] < 0) {
                    $value = 0.001;
                    continue;
                }
                switch ($this->transformers[$column]['transformer']) {
                    case 'Categorical' :
                        if ($this->transformers[$column]['TYPE'] === 'NUMERIC') {
                            $value = 1 / $this->transformers[$column]['C'] + exp(-$value) * 100;
                        } else {
                            if (isset($this->transformers[$column]['VALUES'][$value])) {
                                $value = $this->transformers[$column]['VALUES'][$value] * 10;
                            } else {
                                $this->transformers[$column]['VALUES'][$value] = 1 / (1 + exp(count($this->transformers[$column]['VALUES']) + 1));
                                $value = $this->transformers[$column]['VALUES'][$value] * 10;
                            }
                        }
                        break;
                    case 'Continuous' :
                        if ($this->transformers[$column]['TYPE'] === 'NUMERIC' && is_numeric($value)) {
                            $value = 1 / $this->transformers[$column]['C'] + exp(-$value) * 100;
                        } else {
                            throw new \Exception("Invalid Continuous value detected [$value] for column [$column]");
                        }
                        break;
                }
            }
        }
        return $samples;
    }

    /**
     * Transpose row data to columnar data
     * @param array $data
     * @return array
     */
    private function getVertex(array $data)
    {
        $vertex = [];
        foreach ($data as $row) {
            foreach ($row as $column => $value) {
                $vertex[$column][] = $value;
            }
        }

        return $vertex;
    }
}
