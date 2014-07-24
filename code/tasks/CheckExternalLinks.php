<?php

/**
 * Runs external link checker
 */
class CheckExternalLinks extends BuildTask {
	protected $title = 'Checking broken External links in the SiteTree';

	protected $description = 'A task that records external broken links in the SiteTree';

	protected $enabled = true;

	public function run($request) {
		// If queuedjobs is installed, run as a queued service instead
		if(class_exists('QueuedJobService')) {
			Debug::message('Adding service to queuedjobs');
			singleton('CheckExternalLinksJob')->queueExecution();
			return;
		}

		// Generate a new run
		Debug::message("Starting external link run");
		$run = BrokenExternalLinksRun::create();
		$run->write();

		// Get all pages
		try {
			$pages = SiteTree::get();
			foreach ($pages as $page) {
				$this->checkPage($run, $page);
			}
		} catch(Exception $ex) {
			$run->Status = 'Failed';
			$run->write();
			throw $ex;
		}

		$run->Status = 'Finished';
		$run->write();

		Debug::message("Finished");
	}

	/**
	 * Checks links in a single page
	 *
	 * @param BrokenExternalLinksRun $run
	 * @param Page $page
	 */
	public function checkPage($run, $page) {
		$htmlValue = Injector::inst()->create('HTMLValue', $page->Content);
		$links = $htmlValue->getElementsByTagName('a');
		if(empty($links)) return;

		// Populate link tracking for internal links & links to asset files.
		foreach($links as $link) {
			$this->checkPageLink($run, $page, $link);
		}
	}

	/**
	 * Checks a single link on a page
	 *
	 * @param BrokenExternalLinksRun $run
	 * @param Page $page
	 * @param string $link
	 */
	public function checkPageLink($run, $page, $link) {
		// Exclude internal urls
		$href = Director::makeRelative($link->getAttribute('href'));
		if(!preg_match('/^http/', $href)) return;

		// CURL request this page
		$handle = curl_init($href);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);
		curl_exec($handle);
		$httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
		curl_close($handle);

		// Check if valid code
		if($httpCode >= 200 && $httpCode <= 302) return;

		// Generate new report
		$brokenLink = new BrokenExternalLinks();
		$brokenLink->PageID = $page->ID;
		$brokenLink->RunID = $run->ID;
		$brokenLink->Link = $href;
		$brokenLink->HTTPCode = $httpCode;
		$brokenLink->write();

		// TODO set the broken link class
		/*
		$class = $link->getAttribute('class');
		$class = ($class) ? $class . 'ss-broken' : 'ss-broken';
		$link->setAttribute('class', ($class ? "$class ss-broken" : 'ss-broken'));
		*/

		// use raw sql query to set broken link as calling the dataobject write
		// method will reset the links if no broken internal links are found
		$query = "UPDATE \"SiteTree\" SET \"HasBrokenLink\" = 1 ";
		$query .= "WHERE \"ID\" = " . (int)$page->ID;
		$result = DB::query($query);
		if (!$result) {
			// error updating hasBrokenLink
		}
	}
}
