<?php

/**
 * Represents a single run of all checked links
 */
class BrokenExternalLinksRun extends DataObject {

	private static $db = array(
		'Status' => "Enum('Started,Finished,Failed', 'Started')"
	);

	private static $has_many = array(
		'Links' => 'BrokenExternalLinks'
	);
}
