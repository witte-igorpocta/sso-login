## sso-login
Toto rožšíření slouží pro potřeby společnosti WITTE Nejdek, spol. s r.o., WITTE Access Technology s.r.o. a WITTE Paint Application s.r.o.
<br>

#### Instalace přes composer.json
Neexistuje varianta pro přímou instalaci z packagist.org !!!

```javascript
"repositories": [
    {
      "type": "path",
      "url": "./../_packages/wittenejdek/sso-login/"
    }
  ],

 "require": {
    "wittenejdek/sso-login": "dev-master"
  },
```
<br>
<br>

#### Základní nastavení

##### Zaregistrujeme knihovnu
```
extensions:
    ssoLogin: wittenejdek\ssologin\SSOLoginExtension
```

##### Nastavení rozšíření
<br>
Vyplníme základní údaje pro identifikaci aplikace a URL adresy pro autorizační službu.  Redirect URL adresa musí být totožná s URL nastavenou u autorizační služby. V neposlední řadě zaregistrujeme vlastní Authenticator a třídu uživatele.

```
# WITTE SSO LOGIN
ssoLogin:

    applicationName: "CLIENT ID"
    applicationSecret: "CLIENT SECRET"

    uri:
        redirect: "http://cesta k aplikaci/sso/callback"
        authorize: "http://orion2.wnc.local/sso/oauth2/authorize"
        accessToken: "http://orion2.wnc.local/sso/oauth2/access-token"
        resourceOwnerDetails: "http://orion2.wnc.local/sso/resource"
        redirectAfterLogin: ":Homepage:"

services:
	authenticator: 
		class: wittenejdek\ssologin\Authenticator
	user:
		class: wittenejdek\ssologin\SSOUser
```
<br>

##### Nastavíme vlastní callbacky
Authenticator nabízí dva callbacky, afterTokenRefresh (po obnově tokenu) a afterAuthorize (po autorizaci uživatele).
Implementace callbacků je možná pomocí handlerů setAfterAuthorize a setAfterTokenRefresh.

```
services:
	customSSOCallback: App\Model\Security\CustomSSOCallbacks

	authenticator:
		class: wittenejdek\ssologin\Authenticator
		setup:
			- setAfterAuthorize([@customSSOCallback, 'afterAuthorize'])
			- setAfterTokenRefresh([@customSSOCallback, 'afterTokenRefresh'])
```

V těchto callbacích můžeme jednoduše nastavit identitu uživatele.

```php
public function afterAfterAuthorize(Authenticator $sender, AccessToken $accessToken) 
{
	// Get resource owner data
	$ssoData = $sender->getResourceOwner()->toArray();

	if (array_key_exists("personnel_id", $ssoData)) {
	
		/** @var Employee $employee */
		if ($employee = $this->em->getRepository(Employee::class)->find((int)$ssoData["personnel_id"])) {

			// Create identity
			$identity = new Identity($employee, $ssoData["roles"], []);

			// Update current identity
			$this->userStorage->setIdentity($identity)->setAuthenticated(true);
			return $identity;
		}
	}
}
```

<br>

##### Zaregistrujeme SSO presenter do routeru
```php
public static function createRouter(): Nette\Application\IRouter
{
	$router = new RouteList;
	$router[] = SSORoute::create(); // SSO Rozšíření
	return $router;
}
```
<br>
Odkaz na přihlášení je pod https://cestakaplikaci/sso. 

