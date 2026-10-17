<?php declare(strict_types=1);

namespace App\Presentation\Frontend\Legal;

use App\Presentation\Frontend\BasePresenter;


/**
 * Terms, privacy policy (GDPR) and cookie policy.
 * Text comes from the admin-editable page with the doc's slug; without it the built-in _<doc>.latte is used.
 * Page content may use placeholders {provozovatel}, {email}, {mesto} — filled from config `operator`.
 */
final class LegalPresenter extends BasePresenter
{
	public const Docs = [
		'terms' => ['slug' => 'podminky', 'title' => 'Podmínky používání', 'lead' => 'Pravidla pro používání aplikace Základna. Krátce: je zdarma, data jsou tvoje a chovej se slušně.'],
		'privacy' => ['slug' => 'ochrana-osobnich-udaju', 'title' => 'Ochrana osobních údajů', 'lead' => 'Jaké údaje Základna zpracovává, proč, jak dlouho a jaká máš práva podle GDPR. Bez reklam, bez analytiky, nic neprodáváme.'],
		'cookies' => ['slug' => 'cookies', 'title' => 'Cookies', 'lead' => 'Základna používá jen cookies nutné k tomu, aby fungovalo přihlášení a zabezpečení. Žádná analytika, žádné reklamy, žádné sledování.'],
	];


	public function renderTerms(): void
	{
		$this->showDoc('terms');
	}


	public function renderPrivacy(): void
	{
		$this->showDoc('privacy');
	}


	public function renderCookies(): void
	{
		$this->showDoc('cookies');
	}


	/** Placeholders an admin can use in the page text. */
	public static function placeholders(array $operator): array
	{
		// %7B…%7D: TinyMCE may URL-encode the braces inside href="mailto:{email}"
		return ['{provozovatel}' => $operator['name'], '{email}' => $operator['email'], '%7Bemail%7D' => $operator['email'], '{mesto}' => $operator['city']];
	}


	private function showDoc(string $doc): void
	{
		$page = $this->orm->pages->findBySlug(self::Docs[$doc]['slug']);
		$this->template->doc = $doc;
		$this->template->meta = self::Docs[$doc];
		// admin text is trusted HTML (TinyMCE); placeholder values are escaped
		$this->template->custom = $page?->visible && trim((string) $page->content) !== ''
			? strtr($page->content, array_map('htmlspecialchars', self::placeholders($this->operator)))
			: null;
		$this->setView('doc');
	}
}
