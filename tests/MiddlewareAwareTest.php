<?php
namespace Slim\Tests;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

function testMiddlewareKernel(RequestInterface $req, ResponseInterface $res)
{
    return $res->write('hello from testMiddlewareKernel');
}

class Stackable
{
    use \Slim\MiddlewareAware;

    public function __invoke(RequestInterface $req, ResponseInterface $res)
    {
        return $res->write('Center');
    }

    public function alternativeSeed()
    {
        $this->seedMiddlewareStack('Slim\Tests\testMiddlewareKernel');
    }
}

class MiddlewareTest extends \PHPUnit_Framework_TestCase
{
    public function testSeedsMiddlewareStack()
    {
        $stackable = new Stackable;
        $stackable->add(function ($req, $res, $next) {
            return $res->write('Hi');
        });
        $prop = new \ReflectionProperty($stackable, 'stack');
        $prop->setAccessible(true);

        $bottom = new \ReflectionFunction($prop->getValue($stackable)->bottom());
        $callable = null;
        foreach ($bottom->getStaticVariables() as $k => $var) {
            if ($k === 'callable') {
                $callable = $var;
                break;
            }
        }

        $this->assertSame($stack, $callable);
    }

    public function testCallMiddlewareStack()
    {
        // Build middleware stack
        $stack = new Stackable;
        $stack->add(function ($req, $res, $next) {
            $res->write('In1');
            $res = $next($req, $res);
            $res->write('Out1');

            return $res;
        })->add(function ($req, $res, $next) {
            $res->write('In2');
            $res = $next($req, $res);
            $res->write('Out2');

            return $res;
        });

        // Request
        $uri = \Slim\Http\Uri::createFromString('https://example.com:443/foo/bar?abc=123');
        $headers = new \Slim\Http\Headers();
        $cookies = [];
        $serverParams = [];
        $body = new \Slim\Http\Body(fopen('php://temp', 'r+'));
        $request = new \Slim\Http\Request('GET', $uri, $headers, $cookies, $serverParams, $body);

        // Response
        $response = new \Slim\Http\Response();

        // Invoke call stack
        $res = $stack->callMiddlewareStack($request, $response);

        $this->assertEquals('In2In1CenterOut1Out2', (string)$res->getBody());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testMiddlewareBadReturnValue()
    {
        // Build middleware stack
        $stack = new Stackable;
        $stack->add(function ($req, $res, $next) {
            $res = $res->write('In1');
            $res = $next($req, $res);
            $res = $res->write('Out1');

            // NOTE: No return value here
        });

        // Request
        $uri = \Slim\Http\Uri::createFromString('https://example.com:443/foo/bar?abc=123');
        $headers = new \Slim\Http\Headers();
        $cookies = [];
        $serverParams = [];
        $body = new \Slim\Http\Body(fopen('php://temp', 'r+'));
        $request = new \Slim\Http\Request('GET', $uri, $headers, $cookies, $serverParams, $body);

        // Response
        $response = new \Slim\Http\Response();

        // Invoke call stack
        $res = $stack->callMiddlewareStack($request, $response);
    }

    public function testAlternativeSeedMiddlewareStack()
    {
        $stackable = new Stackable;
        $stackable->alternativeSeed();
        $prop = new \ReflectionProperty($stackable, 'stack');
        $prop->setAccessible(true);

        $bottom = new \ReflectionFunction($prop->getValue($stackable)->bottom());
        $callable = null;
        foreach ($bottom->getStaticVariables() as $k => $var) {
            if ($k === 'callable') {
                $callable = $var;
                break;
            }
        }

        $this->assertSame('Slim\Tests\testMiddlewareKernel', $callable);
    }


    public function testAddMiddlewareWhileStackIsRunningThrowException()
    {
        $stack = new Stackable;
        $stack->add(function($req, $resp) use($stack) {
            $stack->add(function($req, $resp){
                return $resp;
            });
            return $resp;
        });
        $this->setExpectedException('RuntimeException');
        $stack->callMiddlewareStack(
            $this->getMock('Psr\Http\Message\RequestInterface'),
            $this->getMock('Psr\Http\Message\ResponseInterface')
        );
    }

    public function testSeedTwiceThrowException()
    {
        $stack = new Stackable;
        $stack->alternativeSeed();
        $this->setExpectedException('RuntimeException');
        $stack->alternativeSeed();
    }

    public function testAddingNonCallableFails()
    {
        $stack = new Stackable;
        $this->setExpectedException('RuntimeException', 'Expected a callable to be added');
        $stack->add('string');

    }
}
