<?php

namespace Silex\Provider;

use Silex\Application;
use Silex\ServiceProviderInterface;
use FOS\FacebookBundle\Facebook\FacebookSessionPersistence;
use FOS\FacebookBundle\Security\Authentication\Provider\FacebookProvider;
use FOS\FacebookBundle\Security\Authentication\Token\FacebookUserToken;
use FOS\FacebookBundle\Security\EntryPoint\FacebookAuthenticationEntryPoint;
use FOS\FacebookBundle\Security\Firewall\FacebookListener;

/**
 * @author Jérôme TAMARELLE <jerome@tamarelle.net>
 */
class FacebookServiceProvider implements ServiceProviderInterface
{
    private $fakeRoutes;

    public function register(Application $app)
    {
        // used to register routes for login_check and logout
        $this->fakeRoutes = array();

        $that = $this;

        $app['facebook.session_prefix'] = '_facebook_';
        $app['facebook.permissions'] = array();

        $app['facebook'] = $app->share(function () use ($app) {
            return new FacebookSessionPersistence(
                $app['facebook.config'],
                $app['session'],
                $app['facebook.session_prefix']
            );
        });

        $app['security.authentication_listener.factory.facebook'] = $app->protect(function ($name, $options) use ($app, $that) {

            $app['security.authentication.success_handler.facebook'] = $app['security.authentication.success_handler._proto']('facebook', $options);
            $app['security.authentication.failure_handler.facebook'] = $app['security.authentication.failure_handler._proto']('facebook', $options);

            $that->addFakeRoute(
                'match',
                $tmp = isset($options['check_path']) ? $options['check_path'] : '/login_check',
                str_replace('/', '_', ltrim($tmp, '/'))
            );

            $app['security.entry_point.'.$name.'.facebook'] = $app->share(function () use ($app, $options) {
                return new FacebookAuthenticationEntryPoint($app['facebook'], $options, $app['facebook.permissions']);
            });

            $app['security.authentication_provider.'.$name.'.facebook'] = $app->share(function () use ($app, $name, $options) {
                return new FacebookProvider(
                    'facebook',
                    $app['facebook'],
                    $app['security.user_provider.' . $name],
                    $app['security.user_checker'],
                    isset($options['createIfNotExists']) ? $options['createIfNotExists'] : false
                );
            });

            $app['security.authentication_listener.'.$name.'.facebook'] = $app->share(function () use ($app, $options) {
                return new FacebookListener(
                    $app['security'],
                    $app['security.authentication_manager'],
                    $app['security.session_strategy'],
                    $app['security.http_utils'],
                    'facebook',
                    $app['security.authentication.success_handler.facebook'],
                    $app['security.authentication.failure_handler.facebook'],
                    $options,
                    $app['logger'],
                    $app['dispatcher']
                );
            });

            return array(
                'security.authentication_provider.'.$name.'.facebook',
                'security.authentication_listener.'.$name.'.facebook',
                'security.entry_point.'.$name.'.facebook',
                'pre_auth',
            );
        });
    }

    public function boot(Application $app)
    {
        if (!isset($app['facebook.config'])) {
            throw new \RuntimeException('"facebook.config" not defined');
        }

        foreach ($this->fakeRoutes as $route) {
            list($method, $pattern, $name) = $route;

            $app->$method($pattern, function() {})->bind($name);
        }
    }

    public function addFakeRoute($method, $pattern, $name)
    {
        $this->fakeRoutes[] = array($method, $pattern, $name);
    }
}
