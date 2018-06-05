<?php

namespace wittenejdek\ssologin;

use Nette\Security\AuthenticationException;
use Nette\Security\IAuthenticator;
use Nette\Security\IAuthorizator;
use Nette\Security\IIdentity;
use Nette\Security\IUserStorage;
use Nette\Security\User;
use wittenejdek\ssologin\Exception\GeneralException;

final class SSOUser extends User
{

    /** @var IUserStorage Session storage for current user */
    private $storage;

    /** @var IAuthenticator */
    private $authenticator;

    /** @var IAuthorizator */
    private $authorizator;

    /** @var string */
    public $token;

    public function __construct(IUserStorage $storage,
                                IAuthenticator $authenticator = NULL,
                                IAuthorizator $authorizator = NULL)
    {
        parent::__construct($storage, $authenticator, $authorizator);
        $this->storage = $storage;
        $this->authenticator = $authenticator;
        $this->authorizator = $authorizator;

        // Check token
        try {
            $this->authenticator->checkToken();
        } catch (GeneralException $e) {
            $this->logout(true);
        }
    }

    /**
     * Logs out the user from the current session.
     * @return void
     */
    public function logout($clearIdentity = false)
    {
        if ($this->isLoggedIn()) {
            $this->onLoggedOut($this);
            $this->storage->setAuthenticated(false);
        }
        $this->storage->setIdentity(null);
        $this->authenticator->destroyAccessToken();
    }

    /**
     * @return void
     * @throws AuthenticationException if authentication was not successful
     */
    public function login($id = null, $password = null)
    {
        $this->logout(true);
        if ($id instanceof IIdentity) {
            $this->storage->setIdentity($id);
            $this->storage->setAuthenticated(true);
            $this->onLoggedIn($this);
        }
    }

}
