<?php declare(strict_types=1);

namespace App\Core;

use Nette;
use Nette\Application\Routers\RouteList;


final class RouterFactory
{
	use Nette\StaticClass;

	public static function createRouter(): RouteList
	{
		$router = new RouteList;

		// Admin module
		$admin = $router->withModule('Admin');
		$admin->addRoute('admin/<presenter>/<action>[/<id>]', 'Dashboard:default');

		// App module (Personal HQ — authenticated user area)
		$app = $router->withModule('App');
		$app->addRoute('app/<presenter>/<action>[/<id>]', 'Dashboard:default');

		// API for the desktop widget
		$router->withModule('Api')->addRoute('api/widget', 'Widget:default');

		// Frontend (public)
		$frontend = $router->withModule('Frontend');
		$frontend->addRoute('login/google', 'Auth:google');
		$frontend->addRoute('login', 'Auth:login');
		$frontend->addRoute('register', 'Auth:register');
		$frontend->addRoute('logout', 'Auth:logout');
		$frontend->addRoute('robots.txt', 'Seo:robots');
		$frontend->addRoute('sitemap.xml', 'Seo:sitemap');
		$frontend->addRoute('podminky', 'Legal:terms');
		$frontend->addRoute('ochrana-osobnich-udaju', 'Legal:privacy');
		$frontend->addRoute('cookies', 'Legal:cookies');
		// Any single-segment URL like /o-nas → Page:detail(slug=o-nas)
		$frontend->addRoute('<slug [a-z0-9-]+>', 'Page:detail');
		$frontend->addRoute('<presenter>/<action>[/<id>]', 'Home:default');

		return $router;
	}
}
