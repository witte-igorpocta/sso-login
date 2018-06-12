<?php

namespace wittenejdek\ssologin\UI;

use League\OAuth2\Client\Provider\AbstractProvider;
use Nette\Application\UI\Presenter;
use Nette\Security\User;
use wittenejdek\ssologin\Authenticator;
use wittenejdek\ssologin\Configuration;

class SingleSignOnPresenter extends Presenter
{
    /** @var Authenticator */
    public $authenticator;

    /** @var Configuration */
    public $configuration;

    /** @persistent */
    public $backlink = '';

    public function __construct(Authenticator $authenticator, Configuration $configuration)
    {
        $this->authenticator = $authenticator;
        $this->configuration = $configuration;
    }

    public function actionDefault()
    {
        $this->authenticator->setState($this->backlink);
        $this->authenticator->authorize();
        $this->terminate();
    }

    public function actionCallback()
    {

        if (array_key_exists('error', $this->getParameters())) {

        	$this->getTemplate()->setFile(__DIR__ .  '/template/error.latte' );
        	$this->getTemplate()->error = $this->getParameter('error', 'ERROR');
			$this->getTemplate()->message = $this->getParameter('message', 'Something is wrong :(');
			$this->getTemplate()->hint = $this->getParameter('hint', 'Try it later');
			return;

        } elseif ($this->getParameter('code', false)) {
            $this->authenticator->finishAuthorization($this->getParameter('code'));

        } elseif (empty($this->getParameter('state', null)
            || (
                $this->authenticator->getSession()->offsetExists('oauth2state')
                && $this->getParameter('state', null) !== $this->authenticator->getSession()->offsetSet('oauth2state', $this->authenticator->getProvider()->getState())))) {

            if ($this->authenticator->getSession()->offsetExists('oauth2state')) {
                $this->authenticator->getSession()->offsetUnset('oauth2state');
            }

            exit('Invalid state');

        }

        if($this->getParameter('state', false)) {
            $this->backlink = $this->getParameter('state');
            $this->restoreRequest($this->backlink);
        }

        $this->redirect($this->configuration->url["redirectAfterLogin"]);
    }

}
