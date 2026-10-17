<?php declare(strict_types=1);

namespace App\Presentation\Admin\Users;

use Nette\Application\BadRequestException;
use Nette\Application\UI\Form;
use Nette\Security\Passwords;
use Nextras\Dbal\Connection;
use App\Presentation\Admin\BasePresenter;
use App\Model\Admin\Admin;
use App\Model\User\User;


final class UsersPresenter extends BasePresenter
{
	/** @inject */
	public Passwords $passwords;

	private ?User $editedUser = null;
	private ?Admin $editedAdmin = null;


	public function renderDefault(): void
	{
		// ponytail: one query with correlated counts, fine up to a few thousand users
		$this->template->users = $this->db->query("
			SELECT u.id, u.name, u.email, u.created_at, u.dashboard_layout IS NOT NULL AS customised,
				h.id AS household_id, h.name AS household_name,
				(SELECT COUNT(*) FROM cars WHERE user_id = u.id) AS cars,
				(SELECT COUNT(*) FROM transactions WHERE user_id = u.id) AS transactions,
				(SELECT COUNT(*) FROM goals WHERE user_id = u.id) AS goals,
				(SELECT COUNT(*) FROM calendar_events WHERE user_id = u.id) AS events
			FROM users u LEFT JOIN households h ON h.id = u.household_id
			ORDER BY u.created_at DESC
		")->fetchAll();
		$this->template->admins = $this->orm->admins->findAll()->orderBy("createdAt", "DESC");
	}


	public function actionUserDetail(int $id): void
	{
		$user = $this->orm->users->getById($id);
		if ($user === null) {
			throw new BadRequestException("Uživatel nenalezen.");
		}
		// GDPR: counts and dates only — no amounts, titles, categories or notes are loaded
		$this->template->detailUser = $user;
		$this->template->activity = $this->db->query("
			SELECT %s AS label, %s AS icon, COUNT(*) AS n, MAX(date) AS last FROM transactions WHERE user_id = %i
			UNION ALL SELECT %s, %s, COUNT(*), MAX(created_at) FROM cars WHERE user_id = %i
			UNION ALL SELECT %s, %s, COUNT(*), MAX(f.date) FROM fuel_records f JOIN cars c ON c.id = f.car_id WHERE c.user_id = %i
			UNION ALL SELECT %s, %s, COUNT(*), MAX(s.date) FROM service_records s JOIN cars c ON c.id = s.car_id WHERE c.user_id = %i
			UNION ALL SELECT %s, %s, COUNT(*), MAX(date) FROM calendar_events WHERE user_id = %i
			UNION ALL SELECT %s, %s, COUNT(*), NULL FROM budgets WHERE user_id = %i
			UNION ALL SELECT %s, %s, COUNT(*), MAX(created_at) FROM goals WHERE user_id = %i
			UNION ALL SELECT %s, %s, COUNT(*), MAX(date) FROM goal_deposits WHERE user_id = %i
			UNION ALL SELECT %s, %s, COUNT(*), MAX(date) FROM household_expenses WHERE paid_by_id = %i
		",
			"Transakce", "wallet", $id, "Auta", "car", $id, "Tankování", "fuel", $id, "Servisy", "wrench", $id,
			"Události", "calendar", $id, "Rozpočty", "piggy-bank", $id, "Cíle", "target", $id,
			"Vklady do cílů", "trending-up", $id, "Společné výdaje", "receipt", $id,
		)->fetchAll();
		$hh = $user->household;
		$this->template->household = $hh ? ["id" => $hh->id, "name" => $hh->name, "owner" => $hh->owner->id === $user->id] : null;
	}


	#[\Nette\Application\Attributes\Requires(sameOrigin: true)]
	public function actionUserDelete(int $id): void
	{
		$user = $this->orm->users->getById($id);
		if ($user === null) {
			throw new BadRequestException("Uživatel nenalezen.");
		}
		$photos = $this->db->query("SELECT photo FROM cars WHERE user_id = %i AND photo IS NOT NULL", $id)->fetchPairs(null, "photo");
		$name = $user->name;

		// Raw SQL in one transaction: several FKs to users are RESTRICT, ORM cascade does not cover them.
		$this->db->transactional(function (Connection $db) use ($id): void {
			// Household: behave like "leave" — hand ownership over, or drop the household if last member
			$hh = $db->query("SELECT household_id FROM users WHERE id = %i", $id)->fetchField();
			if ($hh) {
				$heir = $db->query("SELECT id FROM users WHERE household_id = %i AND id != %i ORDER BY created_at LIMIT 1", $hh, $id)->fetchField();
				if ($heir) {
					$db->query("UPDATE households SET owner_id = %i WHERE id = %i AND owner_id = %i", $heir, $hh, $id);
				} else {
					$db->query("DELETE FROM households WHERE id = %i", $hh); // cascades expenses, items, settlements, budgets, goals
				}
			}
			// Any other household still owned by this user (data drift) — transfer or drop
			foreach ($db->query("SELECT id FROM households WHERE owner_id = %i", $id)->fetchAll() as $row) {
				$heir = $db->query("SELECT id FROM users WHERE household_id = %i AND id != %i LIMIT 1", $row->id, $id)->fetchField();
				$heir
					? $db->query("UPDATE households SET owner_id = %i WHERE id = %i", $heir, $row->id)
					: $db->query("DELETE FROM households WHERE id = %i", $row->id);
			}
			// Shared records left in households of other members
			$db->query("DELETE FROM household_expense_shares WHERE user_id = %i", $id);
			$db->query("DELETE FROM household_expenses WHERE paid_by_id = %i", $id);
			$db->query("DELETE FROM household_settlements WHERE from_user_id = %i OR to_user_id = %i", $id, $id);
			$db->query("UPDATE household_items i JOIN households h ON h.id = i.household_id SET i.created_by_id = h.owner_id WHERE i.created_by_id = %i", $id);
			$db->query("DELETE FROM goal_deposits WHERE user_id = %i", $id);
			// Personal data
			$db->query("DELETE FROM transactions WHERE user_id = %i", $id);
			$db->query("UPDATE transactions SET car_id = NULL WHERE car_id IN (SELECT id FROM cars WHERE user_id = %i)", $id);
			$db->query("DELETE FROM car_documents WHERE car_id IN (SELECT id FROM cars WHERE user_id = %i)", $id);
			$db->query("DELETE FROM service_records WHERE car_id IN (SELECT id FROM cars WHERE user_id = %i)", $id);
			$db->query("DELETE FROM cars WHERE user_id = %i", $id); // fuel_records cascade
			$db->query("DELETE FROM calendar_events WHERE user_id = %i", $id);
			$db->query("DELETE FROM users WHERE id = %i", $id); // budgets, goals cascade
		});

		foreach ($photos as $photo) {
			if (is_file($p = __DIR__ . "/../../../www/upload/cars/" . basename($photo))) {
				@unlink($p);
			}
		}

		$this->flashMessage("Uživatel $name a všechna jeho data byla smazána.", "success");
		$this->redirect("default");
	}

	public function actionUserChangePassword(int $id): void
	{
		$user = $this->orm->users->getById($id);
		if ($user === null) {
			throw new BadRequestException('Uživatel nenalezen.');
		}
		$this->editedUser = $user;
	}


	public function renderUserChangePassword(int $id): void
	{
		$this->template->detailUser = $this->editedUser;
	}


	protected function createComponentUserPasswordForm(): Form
	{
		return $this->createPasswordForm(function (string $newPassword): void {
			$this->editedUser->password = $this->passwords->hash($newPassword);
			$this->orm->users->persistAndFlush($this->editedUser);
			$this->flashMessage("Heslo uživatele {$this->editedUser->name} bylo změněno.", 'success');
			$this->redirect('userDetail', ['id' => $this->editedUser->id]);
		});
	}


	public function actionAdminDetail(int $id): void
	{
		$admin = $this->orm->admins->getById($id);
		if ($admin === null) {
			throw new BadRequestException('Administrátor nenalezen.');
		}
		$this->template->admin = $admin;
	}


	#[\Nette\Application\Attributes\Requires(sameOrigin: true)]
	public function actionAdminDelete(int $id): void
	{
		$admin = $this->orm->admins->getById($id);
		if ($admin === null) {
			throw new BadRequestException('Administrátor nenalezen.');
		}

		if ($admin->id === $this->getUser()->getId()) {
			$this->flashMessage('Sebe sama nemůžeš smazat.', 'error');
			$this->redirect('default');
		}

		if ($this->orm->admins->findAll()->countStored() <= 1) {
			$this->flashMessage('Posledního administrátora nelze smazat.', 'error');
			$this->redirect('default');
		}

		$this->orm->admins->removeAndFlush($admin);
		$this->flashMessage('Administrátor smazán.', 'success');
		$this->redirect('default');
	}


	public function actionAdminChangePassword(int $id): void
	{
		$admin = $this->orm->admins->getById($id);
		if ($admin === null) {
			throw new BadRequestException('Administrátor nenalezen.');
		}
		$this->editedAdmin = $admin;
	}


	public function renderAdminChangePassword(int $id): void
	{
		$this->template->admin = $this->editedAdmin;
	}


	protected function createComponentAdminPasswordForm(): Form
	{
		return $this->createPasswordForm(function (string $newPassword): void {
			$this->editedAdmin->password = $this->passwords->hash($newPassword);
			$this->orm->admins->persistAndFlush($this->editedAdmin);
			$this->flashMessage("Heslo administrátora {$this->editedAdmin->name} bylo změněno.", 'success');
			$this->redirect('adminDetail', ['id' => $this->editedAdmin->id]);
		});
	}


	private function createPasswordForm(callable $onSave): Form
	{
		$form = new Form;
		$form->addPassword('password', 'Nové heslo:')
			->setRequired('Zadejte nové heslo.')
			->addRule(Form::MIN_LENGTH, 'Heslo musí mít alespoň %d znaků.', 6);
		$form->addPassword('passwordConfirm', 'Nové heslo znovu:')
			->setRequired('Potvrďte heslo.')
			->addRule(Form::EQUAL, 'Hesla se neshodují.', $form['password']);
		$form->addSubmit('send', 'Změnit heslo');

		$form->onSuccess[] = function (Form $form, \stdClass $data) use ($onSave): void {
			$onSave($data->password);
		};
		return $form;
	}
}
