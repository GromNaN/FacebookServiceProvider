<?php

namespace Silex\Provider;

use Silex\Application;
use Silex\ServiceProviderInterface;
use FOS\FacebookBundle\Facebook\FacebookSessionPersistence;
use FOS\FacebookBundle\Security\Authentication\Provider\FacebookProvider;
use FOS\FacebookBundle\Security\Authentication\Token\FacebookUserToken;
use FOS\FacebookBundle\Security\EntryPoint\FacebookAuthenticationEntryPoint;
use FOS\FacebookBundle\Security\Firewall\FacebookListener;
use FOS\FacebookBundle\Security\User\UserManagerInterface;

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

        $app['security.authentication_listener.factory.facebook'] = $app->protect(function($name, $options) use ($app) {
            $entryPoint = isset($options['entry_point']) ? $options['entry_point'] : true;
            $options['redirect_to_facebook_login'] = !isset($options['login_path']);

            if ($entryPoint && !isset($app['security.entry_point.'.$name.'.facebook'])) {
                $app['security.entry_point.'.$name.'.facebook'] = $app['security.entry_point.facebook._proto']($name, $options);
            }

            if (!isset($app['security.authentication_listener.'.$name.'.facebook'])) {
                $app['security.authentication_listener.'.$name.'.facebook'] = $app['security.authentication_listener.facebook._proto']($name, $options);
            }

            if (!isset($app['security.authentication_provider.'.$name.'.facebook'])) {
                $app['security.authentication_provider.'.$name.'.facebook'] = $app['security.authentication_provider.facebook._proto']($name);
            }

            return array(
                'security.authentication_provider.'.$name.'.facebook',
                'security.authentication_listener.'.$name.'.facebook',
                $entryPoint ? 'security.entry_point.'.$name.'.facebook' : null,
                'http' // listener position
            );
        });

        $app['security.entry_point.facebook._proto'] = $app->protect(function ($name, array $options) use ($app) {
            return $app->share(function () use ($app, $options) {
                return new FacebookAuthenticationEntryPoint($app['facebook'], $options, $app['facebook.permissions']);
            });
        });

        $app['security.authentication_listener.facebook._proto'] = $app->protect(function ($name, $options) use ($app, $that) {
            return $app->share(function () use ($app, $name, $options, $that) {
                $that->addFakeRoute(
                    'get',
                    $tmp = isset($options['check_path']) ? $options['check_path'] : '/login_check',
                    str_replace('/', '_', ltrim($tmp, '/'))
                );

                $class = isset($options['listener_class']) ? $options['listener_class'] : 'FOS\\FacebookBundle\\Security\\Firewall\\FacebookListener';

                if (!isset($app['security.authentication.success_handler.'.$name])) {
                    $app['security.authentication.success_handler.'.$name] = $app['security.authentication.success_handler._proto']($name, $options);
                }

                if (!isset($app['security.authentication.failure_handler.'.$name])) {
                    $app['security.authentication.failure_handler.'.$name] = $app['security.authentication.failure_handler._proto']($name, $options);
                }

                return new $class(
                    $app['security'],
                    $app['security.authentication_manager'],
                    $app['security.session_strategy'],
                    $app['security.http_utils'],
                    $name,
                    $app['security.authentication.success_handler.'.$name],
                    $app['security.authentication.failure_handler.'.$name],
                    $options,
                    $app['logger'],
                    $app['dispatcher']
                );
            });
        });

        $app['security.authentication_provider.facebook._proto'] = $app->protect(function ($name) use ($app) {
            return $app->share(function () use ($app, $name) {
                return new FacebookProvider(
                    $name,
                    $app['facebook'],
                    $app['security.user_provider.'.$name],
                    $app['security.user_checker'],
                    $app['security.user_provider.'.$name] instanceof UserManagerInterface
                );
            });
        });
    }

    public function boot(Application $app)
    {
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
