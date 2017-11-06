<?php
namespace Atompulse\Labs\PricePrediction;

use Phpml\Dataset\CsvDataset;

/**
 * Class PriceDataset
 * @package Atompulse\Labs\ML\PricePrediction\Data
 *
 * @author Petru Cojocar <petru.cojocar@gmail.com>
 */
class PriceDataset extends CsvDataset
{
    /**
     *
     */
    public function __construct(string $filepath, int $features)
    {
        parent::__construct($filepath, $features, true);
    }
}
