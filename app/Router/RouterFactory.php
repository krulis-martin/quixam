<?php

declare(strict_types=1);

namespace App\Router;

use Nette;
use Nette\Application\Routers\RouteList;

final class RouterFactory
{
    use Nette\StaticClass;

    public static function createRouter(): RouteList
    {
        $router = new RouteList();

        // REST API routes
        $router[] = new PostRoute('rest/login', 'RestLogin:getToken');
        $router[] = new PostRoute('rest/refresh', 'RestLogin:refreshToken');

        $router[] = new GetRoute('rest/templates/test/<id>', 'RestTemplates:getTest');
        $router[] = new PostRoute('rest/templates/test/<id>/group/<groupId>', 'RestTemplates:addGroup');
        $router[] = new DeleteRoute('rest/templates/test/<id>/group/<groupId>', 'RestTemplates:deleteGroup');
        $router[] = new PostRoute('rest/templates/test/<id>/question/<groupId>', 'RestTemplates:addQuestion');
        $router[] = new DeleteRoute('rest/templates/test/<id>/question/<groupId>', 'RestTemplates:deleteQuestion');

        // routes for app presenters
        $router->addRoute('<presenter>/<action>[/<id>]', [
            'presenter' => 'Homepage',
            'action' => 'default',
            'id' => null,
        ]);
        return $router;
    }
}
