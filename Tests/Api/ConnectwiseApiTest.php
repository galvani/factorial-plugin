<?php

namespace MauticPlugin\MauticFactorialBundle\Tests\Api;

use MauticPlugin\MauticFactorialBundle\Api\ConnectwiseApi;
use MauticPlugin\MauticFactorialBundle\Integration\ConnectwiseIntegration;
use MauticPlugin\MauticFactorialBundle\Tests\Integration\DataGeneratorTrait;

class ConnectwiseApiTest extends \PHPUnit\Framework\TestCase
{
    use DataGeneratorTrait;

    /**
     * @testdox Tests that fetchAllRecords loops until all records are obtained
     *
     * @covers  \MauticPlugin\MauticFactorialBundle\Api\ConnectwiseApi::fetchAllRecords
     *
     * @throws \Mautic\PluginBundle\Exception\ApiErrorException
     */
    public function testResultPagination(): void
    {
        $integration = $this->getMockBuilder(ConnectwiseIntegration::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['makeRequest', 'getApiUrl'])
            ->getMock();

        $page = 0;
        $integration->expects($this->exactly(3))
            ->method('makeRequest')
            ->willReturnCallback(
                function ($endpoint, $parameters) use (&$page) {
                    ++$page;

                    // Page should be incremented 3 times by fetchAllRecords method
                    $this->assertEquals(['page' => $page, 'pageSize' => ConnectwiseIntegration::PAGESIZE], $parameters);

                    return $this->generateData(3);
                }
            );

        $api = new ConnectwiseApi($integration);

        $records = $api->fetchAllRecords('test');

        $this->assertEquals($this->generatedRecords, $records);
    }
}
