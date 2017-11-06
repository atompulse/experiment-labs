<?php
namespace Atompulse\Labs\PricePrediction\Data;

use Phpml\Math\Statistic\Correlation;
use Phpml\Preprocessing\Preprocessor;

/**
 * Class PriceDataTransformer
 * @package Atompulse\Labs\PricePrediction\Data
 *
 * @author Petru Cojocar <petru.cojocar@gmail.com>
 */
class PriceDataTransformer implements Preprocessor
{
    protected $translationStack = [];

    /**
     * @var array
     */
    protected $samples = [];

    public function fit(array &$samples)
    {
        foreach ($samples as &$sample) {
            $this->fitSample($sample);
        }
    }

    public function transform(array &$samples)
    {
        die('aici');
        $this->samples = $samples;
        $vertex = [];

        foreach ($this->samples as $sample) {
            foreach ($sample as $column => $value) {
                $vertex[$column][] = $value;
            }
        }
        print_r($samples);
        print_r($vertex);

        print_r(Correlation::pearson($vertex[8], $vertex[9]));

        die;
    }

    protected function fitSample(array &$sample)
    {
        foreach ($sample as $key => &$value) {
            if (!is_numeric($value)) {
                $this->fitColumnValue($key, $value);
            } else {
                $value = floatval($value);
            }
        }
    }

    protected function fitColumnValue($column, &$value)
    {
        if (isset($this->translationStack[$column][$value])) {
            $value = $this->translationStack[$column][$value];
        } else {
            if (!isset($this->translationStack[$column])) {
                $this->translationStack[$column] = [];
            }
            $this->translationStack[$column][$value] = count($this->translationStack[$column]) + 1;
            $value = $this->translationStack[$column][$value];
        }
    }

}
