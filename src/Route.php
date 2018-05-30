<?php

namespace wittenejdek\ssologin;

class Route
{
    public static function create()
    {
        $routeList = new \Nette\Application\Routers\RouteList('WitteLogin');
        $routeList[] = new \Nette\Application\Routers\Route('sso/<action>[/<id>]', 'SingleSignOn:default');
        return $routeList;
    }
}