<?php declare(strict_types=1);

// Daily cron: DB backup + e-mail reminders + bank sync. Hosting: `php bin/cron.php` once a day (e.g. 7:00).

require __DIR__ . '/../vendor/autoload.php';

$container = (new App\Bootstrap)->bootWebApplication();

$file = $container->getByType(App\Model\Backup\BackupService::class)->run();
echo 'Backup: ' . basename($file) . ' (' . round(filesize($file) / 1024) . " kB)\n";

$sent = $container->getByType(App\Model\Notification\Notifier::class)->run();
echo "Notifications: $sent e-mail(s)\n";

$orm = $container->getByType(App\Model\Orm::class);
$sync = $container->getByType(App\Model\Bank\BankSyncService::class);
foreach ($orm->bankAccounts->findAll() as $account) {
	if (!$account->isConnected()) {
		continue;
	}
	try {
		echo "Bank #{$account->id}: " . $sync->sync($account) . " new\n";
	} catch (App\Model\Bank\BankException $e) {
		echo "Bank #{$account->id}: {$e->getMessage()}\n";
	}
	sleep(1);
}
