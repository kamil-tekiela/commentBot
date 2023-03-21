<?php

use Dharman\StackAPI;

class CommentAPI {
	/**
	 * Stack API class for using the official Stack Exchange API
	 *
	 * @var StackAPI
	 */
	private $stackAPI = null;

	/**
	 * Timestamp
	 *
	 * @var int
	 */
	private $lastRequest;

	/**
	 * End time
	 *
	 * @var int
	 */
	private $endTime;

	/**
	 * Time of last auto-flagging
	 *
	 * @var \DateTime
	 */
	private $lastFlagTime = null;

	/**
	 * Token for Dharman user. Secret!
	 *
	 * @var string
	 */
	private $userToken = '';

	/**
	 * My app key. Not secret
	 */
	private $app_key = '';

	public $running_count = 0;

	public function __construct(StackAPI $stackAPI, string $delay, DotEnv $dotEnv) {
		$this->stackAPI = $stackAPI;
		$this->lastRequest = strtotime($delay);
		$this->endTime = $this->lastRequest + 86400;
		$this->userToken = $dotEnv->get('key');
		$this->app_key = $dotEnv->get('app_key');
		if (!$this->userToken) {
			throw new \Exception('Please login first and provide valid user token!');
		}
	}

	public function fetch() {
		$apiEndpoint = 'comments';
		$url = "https://api.stackexchange.com/2.2/" . $apiEndpoint;
		// if (DEBUG) {
		// 	$url .= '/60673041';
		// }
		$args = [
			'key' => $this->app_key,
			'site' => 'stackoverflow',
			'order' => 'asc',
			'sort' => 'creation',
			'filter' => ')bG2qRHtCqMZR'
		];
		if ($this->lastRequest) {
			$args['fromdate'] = $this->lastRequest + 1;
		}

		$contents = $this->stackAPI->request('GET', $url, $args);

		// Apply heuristics
		foreach ($contents->items as $commentJSON) {
			$comment = new Comment($commentJSON);

			$reasons = $comment->executeRules();

			if ($reasons && ($ratio = $comment->noiseToSize($reasons)) > 0.33) {
				$line = $comment->creation_date->format('Y-m-d H:i:s').' - '.$comment->link.PHP_EOL;
				$line .= $comment->body.PHP_EOL;
				$line .= round($ratio, 2)."\t";
				$line .= 'Reasons: '.implode(', ', $reasons).PHP_EOL.PHP_EOL;
				if ($ratio >= 0.75) {
					file_put_contents('comments.txt', $line, FILE_APPEND);
					if (!DEBUG) {
						try {
							$this->flagPost($comment->id);
						} catch (\Exception $e) {
							file_put_contents(BASE_DIR.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'errors'.DIRECTORY_SEPARATOR.date('Y_m_d_H_i_s').'.log', $e->getMessage());
						}
					} else {
						var_dump($comment);
					}
				} else {
					file_put_contents('comments_log.txt', $line, FILE_APPEND);
				}
			}

			// set last request
			$this->lastRequest = $commentJSON->creation_date;
		}
	}

	public function getTimeLeft(): int {
		return $this->endTime - $this->lastRequest;
	}

	private function flagPost(int $question_id) {
		// throttle
		if ($this->lastFlagTime && $this->lastFlagTime >= ($now = date_create('5 seconds ago'))) {
			sleep($now->diff($this->lastFlagTime)->s + 1); // sleep at least a second
		}

		$url = 'https://api.stackexchange.com/2.2/comments/'.$question_id.'/flags/options';

		$args = [
			'key' => $this->app_key,
			'site' => 'stackoverflow',
			'access_token' => $this->userToken, // Dharman
		];

		$options = $this->stackAPI->request('GET', $url, $args);

		$option_id = null;
		foreach ($options->items as $option) {
			if ($option->title == 'It\'s no longer needed.') {
				$option_id = $option->option_id;
				break;
			}
		}

		if (!$option_id) {
			return;
		}

		$url = 'https://api.stackexchange.com/2.2/comments/'.$question_id.'/flags/add';

		$args += [
			'option_id' => $option_id,
			'preview' => true
		];

		$this->stackAPI->request('POST', $url, $args);

		$this->running_count++;
		$this->lastFlagTime = new DateTime();
	}
}
