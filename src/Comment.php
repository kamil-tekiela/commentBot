<?php

class Comment {
	/**
	 * @var int
	 */
	public $id;

	/**
	 * @var string
	 */
	public $body;

	/**
	 * @var string
	 */
	public $bodyWithoutCode;

	/**
	 * @var \DateTime
	 */
	public $creation_date;

	/**
	 * @var string
	 */
	public $link;

	private const RULES = [
		// gratitude
		'(?:(?:big\s+|many\s+)?th?ank(?:s|\s*you|\s*u)?(?:\s+a lot|\s+(?:very|so) much|\s+a mil+ion|\s+)?(?:\s*for (?:your|the)?(?:\s+help)?)?|th?anx|thx|cheers)[!\.,:()\s]*(?:\w+[!\.,:()\s]*)?',
		// it worked like a charm
		'(?:this\s+|that\s+|it\s+)?(?:solution\s+)?work(?:ed|s)?\s*(?:now|perfectly|great|for me|like a charm)?[!\.:()\s]*',
		// you are welcome
		'(?:(?:you(?:\'?re?| are)\s+)?welcome)+[!\.:()\s]*',
		// this was very helpful
		'(?:(?:I\s+)?(?:hope\s+)?(?:your\s+|(?:this\s+|that\s+|it\s+)(?:was\s+|is\s+)?)?(?:very\s+)?help(?:ful|ed|s)|useful(?:\s+a lot|\s+(?:very|so) much)?)+[!\.:()\s]*',
		// updated/fixed
		'(?:I\s+)?(?:done|updated|edited|fixed)+\s*(?:my|the|a)?\s*(?:answer|question|it|that|this)?[!\.:()\s]*',
		// excitement
		'(?:wonderful|brilliant|Excellent|Marvelous|awesome|(?:You )?saved my\s+\w+)+[!\.:()\s]*',
		// life saver
		'(?:You(?:\'re|\s*are) )?a life saver[!\.:()d=\s]*',
		// please accept
		'(?:please(?: \w+)* )?accept(?:ed|ing)?\b(?: the answer)?',
		// please upvote
		'(?:please(?: \w+) )?(?:give an? )?upvot(?:ed?|ing)(?: the answer)?',
	];

	public function __construct(\stdClass $json) {
		$this->id = $json->comment_id;
		$this->creation_date = date_create_from_format('U', $json->creation_date);
		$this->link = $json->link;
		$this->body = htmlspecialchars_decode($json->body, ENT_QUOTES | ENT_HTML401);

		$this->bodyWithoutCode = preg_replace('#\s*(?:<pre>)?<code>.*?<\/code>(?:<\/pre>)?\s*#s', '', $this->body);
	}

	public function noiseToSize(array $reasons) {
		$size = array_reduce($reasons, function ($carry, $item) {return $carry + strlen($item); });
		$totalLenght = strlen($this->body);
		return $size / $totalLenght;
	}

	public function executeRules(): array {
		$reasons = [];
		foreach (self::RULES as $regex) {
			$reasons = array_merge($reasons, $this->executeRule($regex));
		}
		if ($reasons) {
			$reasons = array_merge($reasons, $this->userMentioned());
		}
		return $reasons;
	}

	private function executeRule(string $regex) {
		if (preg_match_all('#'.$regex.'#iu', $this->bodyWithoutCode, $matches, PREG_SET_ORDER)) {
			return array_column($matches, 0);
		}
		return [];
	}

	private function userMentioned(): array {
		if (preg_match_all('#((?<!\S)@[[:alnum:]][-\'[:word:]]{2,})[[:punct:]]*(?!\S)|(\buser\d+\b)#iu', $this->bodyWithoutCode, $matches, PREG_SET_ORDER)) {
			return array_column($matches, 0);
		}
		return [];
	}
}
