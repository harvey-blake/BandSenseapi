<?php

use Binance\Tests\BaseTestCase;
use Aeris\GuzzleHttpMock\Expect;

class SimpleEarnLockedProductPositionTest extends BaseTestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function testSimpleEarnLockedProductPosition()
    {
        $this->httpMock
            ->shouldReceiveRequest()
            ->withUrl(new Expect\Equals('/sapi/v1/simple-earn/locked/position'))
            ->withMethod('GET')
            ->withQueryParams(new Expect\ArrayEquals([
                'current' => '1',
                'size' => '100',
                'recvWindow' => '5000'
            ]), ['timestamp', 'signature'])
            ->andRespondWithJson($this->data, $statusCode = 200);

        $response = $this->spotClient->simpleEarnLockedProductPosition([
            'current' => 1,
            'size' => 100,
            'recvWindow' => 5000
        ]);

        $this->assertEquals($response, $this->data);
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }
}
