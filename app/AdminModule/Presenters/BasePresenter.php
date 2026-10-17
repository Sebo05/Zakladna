<?php declare(strict_types=1);

namespace App\Presentation\Admin;

use Nette\Application\UI\Presenter;
use Nextras\Dbal\Connection;
use App\Model\Orm;


abstract class BasePresenter extends Presenter
{
	/** @inject */
	public Orm $orm;

	// raw SQL for admin aggregates (counts/sums across all users)
	/** @inject */
	public Connection $db;


	public function checkRequirements(mixed $element): void
	{
		parent::checkRequirements($element);

		// Logged in as a regular user — admin and user sessions are separate.
		// Log them out so they can sign in as an admin.
		if ($this->getUser()->isLoggedIn() && !$this->getUser()->isInRole('admin')) {
			$this->getUser()->logout(true);
			$this->flashMessage('Pro přístup do administrace se přihlas jako administrátor.', 'error');
			$this->redirect(':Admin:Sign:in');
		}

		if (!$this->getUser()->isLoggedIn()) {
			$this->redirect(':Admin:Sign:in', ['backlink' => $this->storeRequest()]);
		}
	}
}
