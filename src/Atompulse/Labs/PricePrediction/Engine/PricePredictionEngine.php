<?php
namespace Atompulse\Labs\PricePrediction\Engine;

use Atompulse\Labs\PricePrediction\PriceDataset;
use Phpml\Math\Statistic\Mean;
use Phpml\Math\Statistic\StandardDeviation;
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
     * @var array
     */
    protected $originalState = null;

    /**
     * @var float
     */
    protected $categoricalBoundary = 0.45; // feature values are categorical if they converge to 1
    /**
     * @var float|
     */
    protected $limitedVariableBoundary = 0.15; // feature values are continuous if they converge to 0

    /**
     * @param array|null $transformers
     * @param float|null $categoricalBoundary
     * @param float|null $limitedVariableBoundary
     */
    public function __construct(array $transformers = null, float $categoricalBoundary = null, float $limitedVariableBoundary = null) {
        // initialize stored transformers
        if ($transformers) {
            $this->transformers = $transformers;
            $this->originalState = $transformers;
        }
        if ($categoricalBoundary) {
            $this->categoricalBoundary = $categoricalBoundary;
        }
        if ($limitedVariableBoundary) {
            $this->limitedVariableBoundary = $limitedVariableBoundary;
        }
        // initialize support vector regression
        $this->regression = new SVR();
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
        // that we can feed into the SVR; otherwise our little SVR will do nothing.
        // we want to know if features are categorical or variables;
        // if they are variables we want know if this variance is continuous or
        // if it is limited to a predetermined set.
        // we do this naively by analyzing the occurrences of values in columns and noticing
        // the patterns that occur
        // we use a configurable categoricalBoundary and limitedVariableBoundary to be able to fine-tune
        // this automated analysis

        foreach ($columnarDataSamples as $column => $columnSamples) {
            $pivot = array_count_values($columnSamples);
            $totalValues = count($columnSamples);
            $uniqueValues = count(array_keys($pivot));
            $q = round($totalValues / ($uniqueValues * 100), 2); // ratio
            // check feature type
            switch ($q) {
                case $q > $this->categoricalBoundary :
                    $this->transformers[$column] = [
                        'transformer' => 'Categorical',
                        'input' => $q,
                        'pivot' => $pivot
                        ];
                    break;
                case $q <= $this->categoricalBoundary && $q > $this->limitedVariableBoundary :
                    $this->transformers[$column] = [
                        'transformer' => 'ShortVariable',
                        'input' => $q,
                        'pivot' => $pivot
                        ];
                    break;
                default:
                    $this->transformers[$column] = [
                        'transformer' => 'Continuous',
                        'input' => $q,
                        'pivot' => $pivot,
                    ];
                    break;
            }

            // check feature value type
            // check if all values are numeric in this column
            if (ctype_digit(implode('', $columnSamples[0]))) {
                $this->transformers[$column]['TYPE'] = 'NUMERIC';
                $this->transformers[$column]['MEAN'] = Mean::median($columnSamples);
            } else {
                $this->transformers[$column]['TYPE'] = 'STRING';
                $this->transformers[$column]['MEAN'] = sqrt($uniqueValues);
                foreach ($pivot as $label => &$frequency) {
                    $frequency = $this->transformers[$column]['MEAN'] / sqrt($frequency);
                }
                $this->transformers[$column]['VALUES'] = $pivot;
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

        return $this->regression->predict($samples);
    }

    /**
     * @param array $samples
     * @return array
     */
    protected function transformSamples(array $samples)
    {
        foreach ($samples as &$sample) {
            foreach ($sample as $column => &$value) {
                switch ($this->transformers[$column]['transformer']) {
                    case 'Categorical' :
                        if ($this->transformers[$column]['TYPE'] === 'NUMERIC' &&
                            is_numeric($value) &&
                            isset($this->transformers[$column]['VALUES'][$value])) {
                                    $value = sqrt($this->transformers[$column]['VALUES'][$value]) / $this->transformers[$column]['MEAN'];
                            } else {
                                // TODO: feedback recursively this new value into the transformer and redo the structure
                                break;
                                    break;
                            }
                        } else {

                        }
                        break;
                    case 'ShortVariable' :
                        if (!is_numeric($value)) {
                            $value = 0;
                            continue;
                        }
                        $value = sqrt($value) / $this->transformers[$column]['MEAN'];
                        break;
                    case 'Continuous' :
                        if (!is_numeric($value)) {
                            $value = 0;
                            continue;
                        }

                        $value = sqrt($value) / $this->transformers[$column]['MEAN'];

                        break;
                }
            }
        }
//
        return $samples;
    }

    protected function correlate()
    {

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
