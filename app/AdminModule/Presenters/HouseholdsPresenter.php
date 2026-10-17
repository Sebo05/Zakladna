<?php declare(strict_types=1);

namespace App\Presentation\Admin\Households;

use Nette\Application\BadRequestException;
use App\Presentation\Admin\BasePresenter;


/** Read-only view of shared households. GDPR: counts only, never amounts, balances or item/expense/goal titles. */
final class HouseholdsPresenter extends BasePresenter
{
	private const Counts = '
		(SELECT COUNT(*) FROM users WHERE household_id = h.id) AS members,
		(SELECT COUNT(*) FROM household_expenses WHERE household_id = h.id) AS expenses,
		(SELECT COUNT(*) FROM household_settlements WHERE household_id = h.id) AS settlements,
		(SELECT COUNT(*) FROM household_items WHERE household_id = h.id AND kind = \'task\') AS tasks,
		(SELECT COUNT(*) FROM household_items WHERE household_id = h.id AND kind = \'shopping\') AS shopping,
		(SELECT COUNT(*) FROM household_budgets WHERE household_id = h.id) AS budgets,
		(SELECT COUNT(*) FROM goals WHERE household_id = h.id) AS goals,
		GREATEST(COALESCE((SELECT MAX(date) FROM household_expenses WHERE household_id = h.id), (SELECT DATE(MAX(created_at)) FROM household_items WHERE household_id = h.id)), COALESCE((SELECT DATE(MAX(created_at)) FROM household_items WHERE household_id = h.id), (SELECT MAX(date) FROM household_expenses WHERE household_id = h.id))) AS last_activity';


	public function renderDefault(): void
	{
		$this->template->households = $this->db->query('
			SELECT h.id, h.name, h.created_at, o.name AS owner_name,
				(SELECT GROUP_CONCAT(name ORDER BY created_at SEPARATOR ", ") FROM users WHERE household_id = h.id) AS member_names,
				' . self::Counts . '
			FROM households h JOIN users o ON o.id = h.owner_id
			ORDER BY h.created_at DESC
		')->fetchAll();
	}


	public function renderDetail(int $id): void
	{
		$household = $this->db->query('SELECT h.id, h.name, h.created_at, h.owner_id, ' . self::Counts . ' FROM households h WHERE h.id = %i', $id)->fetch();
		if ($household === null) {
			throw new BadRequestException('Domácnost nenalezena.');
		}
		$this->template->household = $household;
		$this->template->members = $this->db->query('
			SELECT u.id, u.name, u.email,
				(SELECT COUNT(*) FROM household_expenses WHERE household_id = %i AND paid_by_id = u.id) AS expenses,
				(SELECT COUNT(*) FROM household_items WHERE household_id = %i AND created_by_id = u.id) AS items
			FROM users u WHERE u.household_id = %i ORDER BY u.created_at
		', $id, $id, $id)->fetchAll();
	}
}
