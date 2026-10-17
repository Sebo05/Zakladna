<?php declare(strict_types=1);

namespace App\Presentation\Error\Error4xx;

use Nette;
use Nette\Application\Attributes\Requires;


/**
 * Handles 4xx HTTP error responses.
 */
#[Requires(methods: '*', forward: true)]
final class Error4xxPresenter extends Nette\Application\UI\Presenter
{
	public function renderDefault(Nette\Application\BadRequestException $exception): void
	{
		// renders the appropriate error template based on the HTTP status code
		$code = $exception->getCode();
		$dir = __DIR__;
		$file = is_file($f = "$dir/$code.latte") ? $f : "$dir/4xx.latte";
		$this->template->httpCode = $code;
		$this->template->setFile($file);
	}
}
