<?php

namespace MauticPlugin\MauticFactorialBundle\Tests\Integration;

use MauticPlugin\MauticFactorialBundle\Integration\ConnectwiseIntegration;

trait DataGeneratorTrait
{
    /**
     * @var int
     */
    protected $page = 1;

    /**
     * @var int
     */
    protected $id = 0;

    /**
     * @var array
     */
    protected $generatedRecords = [];

    /**
     * @return array
     */
    protected function generateData($maxPages)
    {
        $pageSize = ($this->page === $maxPages) ? ConnectwiseIntegration::PAGESIZE / 2 : ConnectwiseIntegration::PAGESIZE;
        $fakeData = [];
        $counter  = 0;
        while ($counter < $pageSize) {
            $data                     = [
                'id' => $this->id,
            ];
            $fakeData[]               = $data;
            $this->generatedRecords[] = $data;

            ++$counter;
            ++$this->id;
        }
        ++$this->page;

        return $fakeData;
    }

    protected function reset()
    {
        $this->id               = 0;
        $this->page             = 1;
        $this->generatedRecords = [];
    }
}
