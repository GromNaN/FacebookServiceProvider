<?php

namespace Silex\Provider\Tests;

use Silex\Application;
use Silex\WebTestCase;
use Silex\Provider\SecurityServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\FacebookServiceProvider;

class FacebookServiceProviderTest extends WebTestCase
{
    const FACEBOOK_UUID = '100005332200749';

    public function createApplication()
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REQUEST_URI'] = '/secured';

        $app = new Application();
        $app->register(new SessionServiceProvider());
        $app->register(new SecurityServiceProvider());
        $app->register(new FacebookServiceProvider());
        $app['session.test'] = true;
        unset($app['exception_handler']);

        $app['facebook'] = $this->getMockBuilder('Facebook')->disableOriginalConstructor()->getMock();

        $app->get('/secured', function () use ($app) {
            return $app['security']->getToken()->getUsername();
        });

        return $app;
    }

    public function testRedirectToFacebook()
    {
        $this->app['security.firewalls'] = array(
            'myfw' => array(
                'pattern' => '^/',
                'facebook' => array(
                ),
                'users' => array(),
            ),
        );

        $client = $this->createClient();

        $this->app['facebook']
            ->expects($this->once())
            ->method('getLoginUrl')
            ->will($this->returnValue('http://facebook.com'))
        ;

        $client->request('GET', '/secured');

        $this->assertTrue($client->getResponse()->isRedirect('http://facebook.com'));
    }

    public function testAuthenticateUser()
    {
        $this->app['security.firewalls'] = array(
            'public' => array(
                'pattern' => '^/',
                'facebook' => array(
                    'check_path' => '/facebook_connect',
                    'login_path' => '/login',
                ),
                'users' => array(
                    self::FACEBOOK_UUID => array('ROLE_USER', null),
                ),
            ),
        );

        $this->app['facebook']
            ->expects($this->once())
            ->method('getUser')
            ->will($this->returnValue(self::FACEBOOK_UUID))
        ;

        $this->app['facebook']
            ->expects($this->never())
            ->method('getLoginUrl')
        ;

        $client = $this->createClient();

        $client->request('GET', '/secured');
        $client->request('GET', '/facebook_connect');

        $this->assertTrue($client->getResponse()->isRedirect('http://localhost/secured'));
        $client->followRedirect();
        $this->assertEquals(self::FACEBOOK_UUID, $client->getResponse()->getContent());
    }

    public function testCreateUserIfNotExists()
    {
        $this->app['security.firewalls'] = array(
            'public' => array(
                'pattern' => '^/',
                'facebook' => array(
                    'check_path' => '/facebook_connect',
                    'login_path' => '/login',
                ),
                'users' => \Pimple::share(function () {
                    return new InMemoryFacebookUserProvider();
                }),
            ),
        );

        $this->app['facebook']
            ->expects($this->once())
            ->method('getUser')
            ->will($this->returnValue(self::FACEBOOK_UUID))
        ;
        $this->app['facebook']
            ->expects($this->never())
            ->method('getLoginUrl')
        ;

        $client = $this->createClient();

        $client->request('GET', '/secured');
        $client->request('GET', '/facebook_connect');

        $this->assertTrue($client->getResponse()->isRedirect('http://localhost/secured'));
        $client->followRedirect();
        $this->assertEquals(self::FACEBOOK_UUID, $client->getResponse()->getContent());
    }

    public function testNoEntryPoint()
    {
        $this->app['security.firewalls'] = array(
            'public' => array(
                'pattern' => '^/',
                'facebook' => array(
                    'entry_point' => false,
                ),
                'users' => array(),
            ),
        );

        $this->app['facebook']
            ->expects($this->never())
            ->method('getUser')
        ;
        $this->app['facebook']
            ->expects($this->never())
            ->method('getLoginUrl')
        ;

        $client = $this->createClient();

        $client->request('GET', '/secured');
        $this->assertTrue($client->getResponse()->isRedirect('http://localhost/login'));
    }

    public function testFacebookService()
    {
        $app = new Application();
        $app->register(new SessionServiceProvider());
        $app->register(new FacebookServiceProvider());
        $app['facebook.config'] = array('appId' => null, 'secret' => null);

        $this->assertInstanceOf('BaseFacebook', $app['facebook']);
    }
}
