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
        $router->add(new PostRoute('rest/login', 'RestLogin:getToken'));
        $router->add(new PostRoute('rest/refresh', 'RestLogin:refreshToken'));

        $router->add(new GetRoute('rest/templates/test/<id>', 'RestTemplates:getTest'));
        $router->add(new PostRoute('rest/templates/test/<id>/grading', 'RestTemplates:setGrading'));
        $router->add(new PostRoute('rest/templates/test/<id>/group/<groupId>', 'RestTemplates:addGroup'));
        $router->add(new DeleteRoute('rest/templates/test/<id>/group/<groupId>', 'RestTemplates:deleteGroup'));
        $router->add(new PostRoute(
            'rest/templates/test/<id>/group/<groupId>/question/<questionId>',
            'RestTemplates:addQuestion'
        ));
        $router->add(new DeleteRoute(
            'rest/templates/test/<id>/group/<groupId>/question/<questionId>',
            'RestTemplates:deleteQuestion'
        ));

        $router->add(new GetRoute('rest/terms', 'RestTerms:default'));
        $router->add(new PostRoute('rest/terms/<testId>', 'RestTerms:addTerm'));
        $router->add(new DeleteRoute('rest/term/<id>', 'RestTerms:removeTerm'));
        $router->add(new PostRoute('rest/term/<id>/users', 'RestTerms:registerUsers'));

        $router->add(new GetRoute('rest/term/<id>/answers', 'RestTerms:answers'));
        $router->add(new PostRoute('rest/grade-answer/<id>', 'RestTerms:gradeAnswer'));

        // routes for app presenters
        $router->addRoute('<presenter>/<action>[/<id>]', [
            'presenter' => 'Homepage',
            'action' => 'default',
            'id' => null,
        ]);
        return $router;
    }
}
