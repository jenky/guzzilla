<?php

namespace Jenky\Hermes\Test;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Support\Facades\Event;
use Jenky\Hermes\Contracts\Hermes;
use Jenky\Hermes\Contracts\HttpResponseHandler;
use Jenky\Hermes\Events\RequestHandled;
use Jenky\Hermes\JsonResponse;
use Jenky\Hermes\Response;
use Psr\Http\Message\RequestInterface;
use SimpleXMLElement;

class FeatureTest extends TestCase
{
    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app[Hermes::class]->extend('json', function ($app, array $config) {
            return new Client($this->makeClientOptions(
                array_merge($config, [
                    'options' => [
                        'response_handler' => JsonResponse::class,
                    ],
                    'interceptors' => [
                        ResponseHandler::class,
                    ],
                ]))
            );
        });
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application   $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('hermes.channels.jsonplaceholder', [
            'driver' => 'json',
            'options' => [
                'base_uri' => 'https://jsonplaceholder.typicode.com',
                'http_errors' => false,
            ]
        ]);
    }

    public function test_client_is_instance_of_guzzle()
    {
        $this->assertInstanceOf(Client::class, guzzle());
    }

    public function test_request_event_fired()
    {
        Event::fake();

        $this->httpClient()->get('https://example.com');

        Event::assertDispatched(RequestHandled::class);
    }

    public function test_tap()
    {
        $response = $this->httpClient([
            'tap' => [
                AddHeaderToRequest::class.':X-Foo,bar',
            ],
        ])->get('headers');

        $this->assertEquals('bar', $response->get('headers.X-Foo'));
    }

    public function test_response_handler()
    {
        $response = $this->httpClient()->get('xml', [
            'response_handler' => XmlResponse::class,
        ]);

        $this->assertInstanceOf(SimpleXMLElement::class, $response->toXml());

        $this->expectException(\InvalidArgumentException::class);

        $this->httpClient()->get('html', [
            'response_handler' => InvalidResponseHandler::class,
        ]);
    }

    // public function test_driver()
    // {
    //     $response = guzzle('jsonplaceholder')->get('users');
    // }
}

class AddHeaderToRequest
{
    public function __invoke(HandlerStack $handler, $header, $value)
    {
        $handler->push(Middleware::mapRequest(function (RequestInterface $request) use ($header, $value) {
            return $request->withHeader($header, $value);
        }));
    }
}

class InvalidResponseHandler
{

}

class XmlResponse extends Response implements HttpResponseHandler
{
    public function toXml()
    {
        return new SimpleXMLElement((string) $this->getBody());
    }
}