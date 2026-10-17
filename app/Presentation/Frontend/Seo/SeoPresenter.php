<?php declare(strict_types=1);

namespace App\Presentation\Frontend\Seo;

use Nette\Application\Responses\TextResponse;
use App\Presentation\Frontend\BasePresenter;
use App\Presentation\Frontend\Legal\LegalPresenter;


/** robots.txt + sitemap.xml. App/admin/API are disallowed; login/register stay crawlable so their noindex header is seen. */
final class SeoPresenter extends BasePresenter
{
	public function actionRobots(): void
	{
		$this->getHttpResponse()->setContentType('text/plain', 'utf-8');
		$this->sendResponse(new TextResponse(implode("\n", [
			'User-agent: *',
			'Disallow: /app/',
			'Disallow: /admin/',
			'Disallow: /api/',
			'',
			'Sitemap: ' . $this->link('//sitemap'),
			'',
		])));
	}


	public function actionSitemap(): void
	{
		$urls = [[$this->link('//Home:default'), '1.0']];
		foreach (LegalPresenter::Docs as $action => $_) {
			$urls[] = [$this->link("//Legal:$action"), '0.3'];
		}
		$legalSlugs = array_column(LegalPresenter::Docs, 'slug');
		foreach ($this->orm->pages->findBy(['visible' => true, 'slug!=' => $legalSlugs]) as $page) {
			$urls[] = [$this->link('//Page:detail', $page->slug), '0.5'];
		}

		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ($urls as [$loc, $priority]) {
			$xml .= '<url><loc>' . htmlspecialchars($loc, ENT_XML1) . "</loc><priority>$priority</priority></url>\n";
		}
		$this->getHttpResponse()->setContentType('application/xml', 'utf-8');
		$this->sendResponse(new TextResponse($xml . "</urlset>\n"));
	}
}
