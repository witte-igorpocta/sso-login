<?php

namespace wittenejdek\ssologin;

use Nette\SmartObject;

class Configuration
{

    use SmartObject;

    /** @var string */
    public $applicationName;

    /** @var string */
    public $applicationSecret;

    /** @var array */
    public $permissions = [];

    /** @var array */
    public $url = [
        "redirect" => null,
        "authorize" => null,
        "accessToken" => null,
        "resourceOwnerDetails" => null,
        'api' => '',
        'redirectAfterLogin' => 'Homepage:'
    ];

    public function __construct(string $applicationName, 
                                string $applicationSecret, 
                                array $url = [], 
                                array $permissions = [])
    {
        $this->applicationName = $applicationName;
        $this->applicationSecret = $applicationSecret;
        $this->url = $url;
        $this->permissions = $permissions;
    }

}