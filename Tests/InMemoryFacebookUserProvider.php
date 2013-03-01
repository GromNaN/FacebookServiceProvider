<?php

/*
 * This file is part of the FacebookServiceProvider.
 *
 * (c) Jérôme TAMARELLE <jerome@tamarelle.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silex\Provider\Tests;

use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Core\User\User;
use FOS\FacebookBundle\Security\User\UserManagerInterface as FacebookUserProviderInterface;

class InMemoryFacebookUserProvider extends InMemoryUserProvider implements FacebookUserProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function createUserFromUid($uid)
    {
        $user = new User($uid, null);
        $this->createUser($user);

        return $user;
    }
}