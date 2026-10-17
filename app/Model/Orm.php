<?php declare(strict_types=1);

namespace App\Model;

use Nextras\Orm\Model\Model;
use App\Model\Admin\AdminsRepository;
use App\Model\User\UsersRepository;
use App\Model\Car\CarsRepository;
use App\Model\ServiceRecord\ServiceRecordsRepository;
use App\Model\CarDocument\CarDocumentsRepository;
use App\Model\Transaction\TransactionsRepository;
use App\Model\CalendarEvent\CalendarEventsRepository;
use App\Model\Page\PagesRepository;
use App\Model\MenuItem\MenuItemsRepository;
use App\Model\FuelRecord\FuelRecordsRepository;
use App\Model\Budget\BudgetsRepository;
use App\Model\Goal\GoalsRepository;
use App\Model\Goal\GoalDepositsRepository;
use App\Model\Household\HouseholdsRepository;
use App\Model\Household\HouseholdExpensesRepository;
use App\Model\Household\HouseholdExpenseSharesRepository;
use App\Model\Household\HouseholdSettlementsRepository;
use App\Model\Household\HouseholdItemsRepository;
use App\Model\Household\HouseholdBudgetsRepository;


/**
 * @property-read AdminsRepository $admins
 * @property-read UsersRepository $users
 * @property-read CarsRepository $cars
 * @property-read ServiceRecordsRepository $serviceRecords
 * @property-read CarDocumentsRepository $carDocuments
 * @property-read TransactionsRepository $transactions
 * @property-read CalendarEventsRepository $calendarEvents
 * @property-read PagesRepository $pages
 * @property-read MenuItemsRepository $menuItems
 * @property-read FuelRecordsRepository $fuelRecords
 * @property-read BudgetsRepository $budgets
 * @property-read GoalsRepository $goals
 * @property-read GoalDepositsRepository $goalDeposits
 * @property-read HouseholdsRepository $households
 * @property-read HouseholdExpensesRepository $householdExpenses
 * @property-read HouseholdExpenseSharesRepository $householdExpenseShares
 * @property-read HouseholdSettlementsRepository $householdSettlements
 * @property-read HouseholdItemsRepository $householdItems
 * @property-read HouseholdBudgetsRepository $householdBudgets
 */
final class Orm extends Model
{
}
