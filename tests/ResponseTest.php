<?php

namespace Jenky\Hermes\Test;

use Illuminate\Http\JsonResponse;
use Jenky\Hermes\Response;

class ResponseTest extends TestCase
{
    public function test_json_response()
    {
        $response = $this->httpClient()->get('json');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->header('Content-Type'));
        $this->assertJson($response->toJson());
        $this->assertEqualsIgnoringCase('Sample Slide Show', $response->get('slideshow.title'));
        $this->assertEqualsIgnoringCase('Sample Slide Show', $response->slideshow['title']);
        $response->test = true;
        $this->assertTrue($response->test);

        $response['exception'] = false;
        $this->assertFalse($response['exception']);

        $this->createTestResponse(
            new JsonResponse($response->toArray(), $response->getStatusCode(), $response->header())
        )
            ->assertHeader('Content-Type', 'application/json')
            ->assertSuccessful()
            ->assertJsonStructure([
                'test',
                'exception',
                'slideshow' => [
                    'author',
                    'date',
                    'slides',
                    'title',
                ],
            ]);

        unset($response->test);
        $this->assertFalse(isset($response->test));
        $this->assertFalse(isset($response['test']));

        unset($response['exception']);
        $this->assertFalse(isset($response->exception));
        $this->assertFalse(isset($response['exception']));
    }

    public function test_xml_response()
    {
        $response = $this->httpClient()->get('xml', [
            'response_handler' => Response::class,
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/xml', $response->header('content-type'));
    }
}
