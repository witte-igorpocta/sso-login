<?php

namespace wittenejdek\ssologin;

use Nette\Application\IPresenter;
use Nette\Configurator;
use Nette\DI\Compiler;
use Nette\DI\CompilerExtension;
use Nette\Reflection\Extension;
use wittenejdek\ssologin\UI\SingleSignOnPresenter;

class SSOLoginExtension extends CompilerExtension
{
    /** @var array */
    private $defaults = [
        'applicationName' => null,
        'applicationSecret' => null,
        "uri" => [
            "redirect" => null,
            "authorize" => null,
            "accessToken" => null,
            "resourceOwnerDetails" => null,
            "api" => null,
        ]
    ];

    public function loadConfiguration()
    {

        $builder = $this->getContainerBuilder();
        $this->validateConfig($this->defaults);
        
        $builder->addDefinition($this->prefix('configuration'))
            ->setFactory(Configuration::class, [
                $this->config["applicationName"],
                $this->config["applicationSecret"],
                $this->config["uri"],
                [],
            ]);

        // Presenter, Control factory, Serializer
        $builder->addDefinition($this->prefix('presenter'))
            ->setClass(SingleSignOnPresenter::class);
        
        
    }

    public function beforeCompile(): void
    {
        $builder = $this->getContainerBuilder();

        // Mapping
        $presenterFactory = $builder->getDefinition($builder->getByType('Nette\Application\IPresenterFactory'));
        $presenterFactory->addSetup('if (!? instanceof \Nette\Application\PresenterFactory) { throw new \RuntimeException(\'Cannot set WITTE SSO Login mapping\'); } else { ?->setMapping(?); }', [
            '@self',
            '@self',
            ['WitteLogin' => 'wittenejdek\ssologin\UI\*Presenter'],
        ]);
    }

    /**
     * @param Configurator $configurator
     */
    public static function register(Configurator $configurator)
    {
        $configurator->onCompile[] = function ($config, Compiler $compiler) {
            $compiler->addExtension('WITTENejdek_SSOLogin', new Extension());
        };
    }

}