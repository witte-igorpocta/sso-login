<?php

namespace wittenejdek\ssologin;

use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Nette\Http\Session;
use Nette\Security\AuthenticationException;
use Nette\Security\IAuthenticator;
use Nette\Security\Identity;
use Nette\Security\IIdentity;
use Nette\Security\IUserStorage;
use Nette\Security\Permission;
use Nette\SmartObject;
use wittenejdek\ssologin\Exception\GeneralException;

final class Authenticator extends Permission implements IAuthenticator
{

    use SmartObject;

    /** @var AccessToken */
    public $accessToken;

    /** @var Session */
    protected $session;

    /** @var Configuration */
    protected $configuration;

    /** @var IUserStorage */
    protected $userStorage;

    /** @var GenericProvider */
    protected $provider;
    
    /** @var ResourceOwnerInterface */
    protected $resourceOwner;

    /** @var Callable */
    public $afterTokenRefresh;

    /** @var Callable */
    public $afterAuthorize;

    public function __construct(Session $session,
                                Configuration $configuration,
                                IUserStorage $userStorage)
    {

        $this->session = $session->getSection('ssologin');
        $this->configuration = $configuration;
        $this->userStorage = $userStorage;

        if (isset($this->session->access_token)) {
            $this->accessToken = $this->session->access_token;
        }

        // Create provider
        $this->provider = new GenericProvider([
            'clientId' => $this->configuration->applicationName,
            'clientSecret' => $this->configuration->applicationSecret,
            'redirectUri' => $this->configuration->url["redirect"],
            'urlAuthorize' => $this->configuration->url["authorize"],
            'urlAccessToken' => $this->configuration->url["accessToken"],
            'urlResourceOwnerDetails' => $this->configuration->url["resourceOwnerDetails"],
        ]);

        // Set callbacks
        $this->setAfterTokenRefresh([$this, 'afterTokenRefresh']);
        $this->setAfterAuthorize([$this, 'afterAuthorize']);

    }

    /**
     * @return bool True when token has been refreshed
     */
    public function checkToken()
    {
        // Only for authenticated users
        if ($this->userStorage->isAuthenticated()) {

            // Token expired or will expire in next 15 seconds
            if ($this->accessToken && ($this->accessToken->hasExpired() || $this->accessToken->getExpires() <= (time() + 15))) {
                try {

                    // Get new refresh token
                    $accessToken = $this->provider->getAccessToken(new \League\OAuth2\Client\Grant\RefreshToken(), [
                        'refresh_token' => $this->accessToken->getRefreshToken()
                    ]);

                    if ($accessToken) {

                        $this->saveAccessToken($accessToken);
                        bdump("Platnost soucasneho tokenu do: " . date("Y-m-d H:i:s", substr($accessToken->getExpires(), 0, 10)));

                        // Check identity from resource
                        $this->getIdentity($accessToken);

                        // Callback after token refresh
                        call_user_func_array($this->afterTokenRefresh, [$this, $accessToken]);

                        return true;
                    }
                } catch (\Exception $e) {
                    bdump("checkToken - Exception - " . $e->getMessage());
                    throw new GeneralException($e->getMessage());
                }
            } elseif (!$this->accessToken) {
                $this->authorize();
            }else {
                return true;
            }
        } else {
            return false;
        }
    }

    public function getIdentity(AccessToken $accessToken): ?ResourceOwnerInterface
    {
        // Using the access token for get data from source
        $this->resourceOwner = $this->provider->getResourceOwner($accessToken);

        $ssoData = $this->resourceOwner->toArray();
        
        // Force logout
        if(in_array('logout', $ssoData)
            || array_key_exists('logout', $ssoData)
            || (array_key_exists('error', $ssoData) && $ssoData["error"] === "access_denied")) {
            bdump("getIdentity - SSODATA - force logout");
            $this->destroyAccessToken();
            return null;
        }
        
        return $this->resourceOwner ? $this->resourceOwner : null;
    }

    public function saveAccessToken(AccessToken $accessToken)
    {
        // Save access token
        $this->session->access_token = $this->accessToken = $accessToken;
    }

    public function authorize()
    {
        // Fetch the authorization URL from the provider; this returns the
        // urlAuthorize option and generates and applies any necessary parameters
        // (e.g. state).
        $authorizationUrl = $this->provider->getAuthorizationUrl(['scope' => implode(" ", $this->configuration->permissions)]);

        // Get the state generated for you and store it to the session.
        $this->session->offsetSet('oauth2state', $this->provider->getState());

        // Redirect the user to the authorization URL.
        header('Location: ' . $authorizationUrl);
    }

    public function finishAuthorization(string $code)
    {
        try {

            // Try to get an access token using the authorization code grant.
            /** @var AccessToken $accessToken */
            $accessToken = $this->provider->getAccessToken('authorization_code', [
                'code' => $code,
            ]);

            $this->saveAccessToken($accessToken);

            // Check identity from resource
            $this->getIdentity($accessToken);

            // Callback after token refresh
            return call_user_func_array($this->afterAuthorize, [$this, $accessToken]);

        } catch (\Exception $e) {
            $this->destroyAccessToken();
        }
    }

    public function destroyAccessToken(): void
    {
        $this->session->access_token = $this->accessToken = null;
        $this->resourceOwner = null;
    }

    public function getSession(): Session
    {
        return $this->session;
    }

    public function getProvider(): GenericProvider
    {
        return $this->provider;
    }

    /**
     * @return ResourceOwnerInterface
     */
    public function getResourceOwner(): ResourceOwnerInterface
    {
        return $this->resourceOwner;
    }

    /**
     * @param callable $handler <IIdentity>function(Authenticator, AccessToken)
     */
    public function setAfterTokenRefresh($handler)
    {
        $this->afterTokenRefresh = $handler;
    }

    /**
     * @param callable $handler <IIdentity>function(Authenticator, AccessToken)
     */
    public function setAfterAuthorize($handler)
    {
        $this->afterAuthorize = $handler;
    }

    public function afterTokenRefresh(Authenticator $sender, AccessToken $accessToken)
    {
        // Get resource owner data
        $ssoData = $this->resourceOwner->toArray();
        
        $identity = new Identity($ssoData["username"], $ssoData["roles"], $ssoData);
        $this->userStorage
            ->setIdentity($identity)
            ->setAuthenticated(true);
        return $identity;
    }

    public function afterAuthorize(Authenticator $sender, AccessToken $accessToken)
    {
        // Get resource owner data
        $ssoData = $this->resourceOwner->toArray();
        
        $identity = new Identity($ssoData["username"], $ssoData["roles"], $ssoData);

        $this->userStorage
            ->setIdentity($identity)
            ->setAuthenticated(true);
        return $identity;
    }

    /** @deprecated  */
    function authenticate(array $credentials)
    {
        // TODO: Implement authenticate() method.
    }

}
