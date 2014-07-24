<?php

class BrokenExternalLinks extends DataObject {

	private static $db = array(
		'Link' => 'Varchar(2083)', // 2083 is the maximum length of a URL in Internet Explorer.
		'HTTPCode' =>'Int'
	);

	private static $has_one = array(
		'Page' => 'Page',
		'Run' => 'BrokenExternalLinksRun'
	);

	public static $summary_fields = array(
		'Page.Title' => 'Page',
		'HTTPCode' => 'HTTP Code',
		'Created' => 'Created'
	);

	public static $searchable_fields = array(
		'HTTPCode' => array('title' => 'HTTP Code')
	);

	function canEdit($member = false) {
		return false;
	}

}

class BrokenExternalLinksAdmin extends ModelAdmin {

	public static $url_segment = 'broken-external-links-admin';

	public static $managed_models = array(
		'BrokenExternalLinks'
	);

	public static $menu_title = 'Broken Ext. links';

	public function init() {
		parent::init();
	}

}
