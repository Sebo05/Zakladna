<?php declare(strict_types=1);

namespace App\Presentation\Admin\Dashboard;

use Nette\Utils\Json;
use App\Presentation\Admin\BasePresenter;


/** Operator overview: how the whole app is used. GDPR: counts only, never money values or record titles. */
final class DashboardPresenter extends BasePresenter
{
	public function renderDefault(): void
	{
		$q = fn(string $sql) => $this->db->query($sql)->fetchField();

		$users = (int) $q('SELECT COUNT(*) FROM users');
		$households = (int) $q('SELECT COUNT(*) FROM households');
		$inHousehold = (int) $q('SELECT COUNT(*) FROM users WHERE household_id IS NOT NULL');

		$this->template->kpi = [
			'users' => $users,
			'newWeek' => (int) $q('SELECT COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL 7 DAY'),
			'newMonth' => (int) $q('SELECT COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL 30 DAY'),
			'households' => $households,
			'avgMembers' => $households ? $inHousehold / $households : 0,
			'cars' => (int) $q('SELECT COUNT(*) FROM cars'),
			'transactions' => (int) $q('SELECT COUNT(*) FROM transactions'),
			'monthTransactions' => (int) $q('SELECT COUNT(*) FROM transactions WHERE date > LAST_DAY(CURDATE() - INTERVAL 1 MONTH)'),
			'goals' => (int) $q('SELECT COUNT(*) FROM goals'),
			'householdGoals' => (int) $q('SELECT COUNT(*) FROM goals WHERE household_id IS NOT NULL'),
			'fuel' => (int) $q('SELECT COUNT(*) FROM fuel_records'),
			'events' => (int) $q('SELECT COUNT(*) FROM calendar_events'),
			'customised' => (int) $q('SELECT COUNT(*) FROM users WHERE dashboard_layout IS NOT NULL'),
		];

		// Feature adoption: share of users with at least one record in each module
		$adoption = [
			['Garáž', 'car', 'SELECT COUNT(DISTINCT user_id) FROM cars'],
			['Finance', 'wallet', 'SELECT COUNT(DISTINCT user_id) FROM transactions'],
			['Tankování', 'fuel', 'SELECT COUNT(DISTINCT c.user_id) FROM fuel_records f JOIN cars c ON c.id = f.car_id'],
			['Kalendář', 'calendar', 'SELECT COUNT(DISTINCT user_id) FROM calendar_events'],
			['Rozpočty', 'piggy-bank', 'SELECT COUNT(DISTINCT user_id) FROM budgets'],
			['Cíle', 'target', 'SELECT COUNT(DISTINCT user_id) FROM goal_deposits'],
			['Domácnost', 'house-plus', 'SELECT COUNT(*) FROM users WHERE household_id IS NOT NULL'],
			['Vlastní přehled', 'sliders-horizontal', 'SELECT COUNT(*) FROM users WHERE dashboard_layout IS NOT NULL'],
		];
		$rows = [];
		foreach ($adoption as [$label, $icon, $sql]) {
			$n = (int) $q($sql);
			$rows[] = ['label' => $label, 'icon' => $icon, 'users' => $n, 'pct' => $users ? $n / $users * 100 : 0];
		}
		usort($rows, fn($a, $b) => $b['users'] <=> $a['users']);
		$this->template->adoption = $rows;

		// Registrations per day, last 30 days (zero-filled)
		$perDay = $this->db->query('
			SELECT CAST(DATE(created_at) AS CHAR) AS d, COUNT(*) AS n FROM users
			WHERE created_at >= CURDATE() - INTERVAL 29 DAY GROUP BY DATE(created_at)
		')->fetchPairs('d', 'n');
		$labels = $data = [];
		for ($i = 29; $i >= 0; $i--) {
			$day = new \DateTimeImmutable("-$i days");
			$labels[] = $day->format('j. n.');
			$data[] = (int) ($perDay[$day->format('Y-m-d')] ?? 0);
		}
		$this->template->registrationsChart = Json::encode([
			'labels' => $labels,
			'datasets' => [['label' => 'Registrace', 'data' => $data, 'backgroundColor' => '#0a84ff', 'borderRadius' => 4, 'maxBarThickness' => 14]],
		], htmlSafe: true);

		// Activity per week (last 8 weeks) by record date
		$weeks = [];
		for ($i = 7; $i >= 0; $i--) {
			$weeks[] = (new \DateTimeImmutable("monday this week -$i weeks"))->format('Y-m-d');
		}
		$series = [
			['Transakce', '#0a84ff', 'transactions', 'date'],
			['Tankování', '#ff9f0a', 'fuel_records', 'date'],
			['Události', '#bf5af2', 'calendar_events', 'date'],
			['Společné výdaje', '#30d158', 'household_expenses', 'date'],
		];
		$datasets = [];
		foreach ($series as [$label, $color, $table, $col]) {
			$counts = $this->db->query("
				SELECT CAST(DATE_SUB(DATE($col), INTERVAL WEEKDAY($col) DAY) AS CHAR) AS w, COUNT(*) AS n FROM $table
				WHERE $col >= %s GROUP BY w
			", $weeks[0])->fetchPairs('w', 'n');
			$datasets[] = [
				'label' => $label,
				'data' => array_map(fn($w) => (int) ($counts[$w] ?? 0), $weeks),
				'backgroundColor' => $color,
				'borderRadius' => 4,
				'maxBarThickness' => 28,
			];
		}
		$this->template->activityChart = Json::encode([
			'labels' => array_map(fn($w) => (new \DateTimeImmutable($w))->format('j. n.'), $weeks),
			'datasets' => $datasets,
		], htmlSafe: true);
		$this->template->chartOptions = Json::encode(['responsive' => true, 'maintainAspectRatio' => false, 'plugins' => ['legend' => ['display' => false]], 'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]]]);
		$this->template->stackedOptions = Json::encode(['responsive' => true, 'maintainAspectRatio' => false, 'plugins' => ['legend' => ['position' => 'bottom', 'labels' => ['usePointStyle' => true, 'boxWidth' => 8]]], 'scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true, 'beginAtZero' => true, 'ticks' => ['precision' => 0]]]]);

		$this->template->recentUsers = $this->orm->users->findAll()->orderBy('createdAt', 'DESC')->limitBy(6);

		// Data health
		$this->template->inactiveUsers = (int) $q('
			SELECT COUNT(*) FROM users u
			WHERE u.household_id IS NULL
				AND NOT EXISTS (SELECT 1 FROM cars WHERE user_id = u.id)
				AND NOT EXISTS (SELECT 1 FROM transactions WHERE user_id = u.id)
				AND NOT EXISTS (SELECT 1 FROM calendar_events WHERE user_id = u.id)
				AND NOT EXISTS (SELECT 1 FROM goals WHERE user_id = u.id)
				AND NOT EXISTS (SELECT 1 FROM budgets WHERE user_id = u.id)
		');
		$this->template->soloHouseholds = (int) $q('
			SELECT COUNT(*) FROM households h WHERE (SELECT COUNT(*) FROM users WHERE household_id = h.id) <= 1
		');
		$this->template->expiredDocs = (int) $q('SELECT COUNT(*) FROM car_documents WHERE expires_at < CURDATE()');
		$this->template->pagesCount = (int) $q('SELECT COUNT(*) FROM pages');
		$this->template->menuCount = (int) $q('SELECT COUNT(*) FROM menu_items');
		$this->template->adminName = $this->getUser()->getIdentity()?->getData()['name'] ?? '';
	}
}
