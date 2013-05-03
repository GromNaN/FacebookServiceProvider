# FacebookServiceProvider #

The FacebookServiceProvider adds Facebook Connect and API to your
applications.

It integrates [FOSFacebookBundle](https://github.com/FriendsOfSymfony/FOSFacebookBundle) into Silex.

## Parameters ##

* __facebook.config:__ Configuration of your Facebook application.
  (appId, secret, fileUpload, )
* __facebook.permissions:__ List of permissions required to connect.
* __facebook.session_prefix:__ Prefix for Facebook data.

## Services ##

* __facebook:__ Facebook SDK. [Read online documentation](https://developers.facebook.com/docs/reference/php/)

## Installation ##

Using composer :

    composer require grom/facebook-service-provider

## Registering ##

First, you must register the [SecurityServiceProvider](http://silex.sensiolabs.org/doc/providers/security.html#registering).

```php
use Silex\Provider\FacebookServiceProvider;

$app->register(new FacebookServiceProvider(), array(
    'facebook.config' => array(
        'appId'      => 'YOUR_APP_ID',
        'secret'     => 'YOUR_APP_SECRET',
        'fileUpload' => false, // optional
    ),
    'facebook.permissions' => array('email'),
));
```

## Authentication ##

To authenticate users with Facebook Connect, first you need to
register the [SecurityServiceProvider](http://silex.sensiolabs.org/doc/providers/security.html).

To enable Facebook authentication, just add a "facebook" option to your firewall configuration.

```php
$app['security.firewalls'] = array(
    'private' => array(
        'pattern' => '^/',
        'facebook' => array(
            'check_path' => '/login_check',
            'login_path' => '/login',
        ),
        // Users are identified by their Facebook UID
        'users' => array(
            // This is Mark Zuckerberg
            '4' => array('ROLE_USER', null),
        ),
    ),
);
```

If you don't set a `login_path`, the user is redirected to Facebook.

Developers of embedded Facebook Applications may define `app_url`,
`server_url` and `display` options.

## Defining a custom User Provider and automatic user creation##

The UserProvider used to find Facebook user is similar to the
[username/password UserProvider](http://silex.sensiolabs.org/doc/providers/security.html#defining-a-custom-user-provider). The differences are that users are identified by their Facebook UID
instead of their username.

If the Facebook UID is not found in your database, the user provider
can create the user automatically. You simply have to implements the
method `FOS\FacebookBundle\Security\User\UserProviderInterface::createUserFromUid`

```php
use FOS\FacebookBundle\Security\User\UserManagerInterface as FacebookUserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Doctrine\DBAL\Connection;

class FacebookUserProvider implements FacebookUserProviderInterface
{
    private $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function loadUserByUsername($uid)
    {
        $stmt = $this->conn->executeQuery('SELECT * FROM users WHERE username = ?', array($uid));

        if (!$user = $stmt->fetch()) {
            throw new UsernameNotFoundException(sprintf('Facebook UID "%s" does not exist.', $uid));
        }

        return new User($user['username'], null, explode(',', $user['roles']), true, true, true, true);
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    public function createUserFromUid($uid)
    {
        $this->conn->insert('users', array(
            'username' => $uid,
            'roles'    => 'ROLE_USER',
        ));

        return $this->loadUserByUsername($uid);
    }

    public function supportsClass($class)
    {
        return $class === 'Symfony\Component\Security\Core\User\User';
    }
}
```

Now use your custom user provider.

```php
$app['security.firewalls'] = array(
    'default' => array(
        'facebook' => array(
        ),
        'users' => $app->share(function () use ($app) {
            return new FacebookUserProvider($app['db']);
        }),
    ),
);
```

## Facebook Graph API ##

Once a user is authenticated with Facebook, you can make Facebook
Graph API requests.

```php
$app->get('/', function () use ($app) {
    $user = $app['facebook']->api('/me');

    return 'Welcome ' . $user['name'];
});
```

Look at the [Facebook Graph Explorer](https://developers.facebook.com/tools/explorer/).
