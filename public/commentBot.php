<?php

use Dharman\StackAPI;

define('BASE_DIR', realpath(__DIR__.'/..'));

include BASE_DIR.'/vendor/autoload.php';

$dotEnv = new DotEnv();
$dotEnv->load(BASE_DIR.'/config.ini');
define('DEBUG', (bool) $dotEnv->get('DEBUG'));

$client = new GuzzleHttp\Client();

$stackAPI = new StackAPI($client);

$fetcher = new CommentAPI($stackAPI, '1 year ago', $dotEnv);

$failedTries = 0;
while (1) {
	try {
		$fetcher->fetch();
	} catch (\Throwable $e) {
		$failedTries++;
		file_put_contents(BASE_DIR.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'errors'.DIRECTORY_SEPARATOR.date('Y_m_d_H_i_s').'.log', $e->getMessage().PHP_EOL.print_r($e->getTrace(), true));
		sleep($failedTries * 60);
		// keep trying up to n times
		if ($failedTries >= 10) {
			throw $e;
		}
		continue;
	}

	$failedTries = 0;

	if ($fetcher->running_count >= $dotEnv->get('commentsToFlag')) {
		break;
	}

	sleep(15);
}
