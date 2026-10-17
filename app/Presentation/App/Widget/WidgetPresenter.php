<?php declare(strict_types=1);

namespace App\Presentation\App\Widget;

use Nette\Application\BadRequestException;
use App\Model\Widget\WidgetTokenService;
use App\Presentation\App\BasePresenter;


/**
 * Browser sign-in for the desktop widget (OAuth-style loopback, RFC 8252): the widget listens on
 * 127.0.0.1:<port> and opens this page; after "Povolit" the token goes back only to that loopback address.
 */
final class WidgetPresenter extends BasePresenter
{
	/** @inject */
	public WidgetTokenService $tokens;

	private int $port;
	private string $state;


	public function actionDefault(int $port = 0, string $state = ''): void
	{
		if ($port < 1024 || $port > 65535 || !preg_match('~^[A-Za-z0-9]{16,64}$~D', $state)) {
			throw new BadRequestException('Neplatný odkaz z widgetu.');
		}
		$this->port = $port;
		$this->state = $state;
	}


	#[\Nette\Application\Attributes\Requires(methods: 'POST', sameOrigin: true)]
	public function handleAllow(): void
	{
		$token = $this->tokens->issue($this->getCurrentUser()->id);
		$this->redirectUrl('http://127.0.0.1:' . $this->port . '/?' . http_build_query(['state' => $this->state, 'token' => $token]));
	}
}
