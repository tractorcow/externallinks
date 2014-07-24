<?php

if(!class_exists('AbstractQueuedJob')) return;

/**
 * An check external links job
 *
 */
class CheckExternalLinksJob extends AbstractQueuedJob {

	public static $regenerate_time = 43200;

	/**
	 * Sitemap job is going to run for a while...
	 */
	public function getJobType() {
		return QueuedJob::QUEUED;
	}

	/**
	 * @return string
	 */
	public function getTitle() {
		return 'Checking external links';
	}

	/**
	 * Return a signature for this queued job
	 *
	 * For the generate sitemap job, we only ever want one instance running, so just use the class name
	 *
	 * @return String
	 */
	public function getSignature() {
		return md5(get_class($this));
	}

	/**
	 * Note that this is duplicated for backwards compatibility purposes...
	 */
	public function setup() {
		parent::setup();
		increase_time_limit_to();

		$this->addMessage('Starting run');

		Versioned::reading_stage('Stage');
		$this->pagesToProcess = Page::get()->filter("ShowInSearch", 1)->column('ID');
		$this->currentStep = 0;
		$this->totalSteps = count($this->pagesToProcess);
	}

	/**
	 * On any restart, make sure to check that our temporary file is being created still.
	 */
	public function prepareForRestart() {
		parent::prepareForRestart();
	}

	public function jobFinished() {
		$this->isComplete = $this->currentStep >= $this->totalSteps;
		return parent::jobFinished();
	}

	/**
	 * Gets the current BrokenExternalLinksRun
	 *
	 * @return BrokenExternalLinksRun
	 */
	public function getRun() {
		if(empty($this->runID)) {
			$run = new BrokenExternalLinksRun();
			$run->write();
			$this->runID = $run->ID;
			return $run;
		} else {
			return BrokenExternalLinksRun::get()->byID($this->runID);
		}
	}

	/**
	 * Get the next page to process, incrementing the view
	 *
	 * @return Page
	 */
	public function getNextPage() {
		$id = $this->pagesToProcess[$this->currentStep++];
		Versioned::reading_stage('Stage');
		return Page::get()->byID($id);
	}

	public function process() {
		if($this->jobFinished()) return;

		// Check next page to run
		$run = $this->getRun();
		$page = $this->getNextPage();
		if(!$page) return;

		// Run on this page
		$this->addMessage("Processing page {$page->Title}");
		$task = new CheckExternalLinks();
		$task->checkPage($run, $page);

		// Complete this job if no more links to check
		if($this->jobFinished()) {
			$this->completeJob($run);
		}
	}

	protected function completeJob($run) {
		$this->addMessage("Finished checking {$this->totalSteps} pages");
		$run->Status = 'Finished';
		$run->write();

		// @todo - probably don't want this job to run indefinitely
		$time = date('Y-m-d H:i:s', time() + self::$regenerate_time);
		$this->queueExecution($time);
	}

	/**
	 * Queues this task for subsequent execution
	 */
	public function queueExecution($time = null) {
		$nextgeneration = new CheckExternalLinksJob();
		singleton('QueuedJobService')->queueJob(
			$nextgeneration,
			$time
		);
	}
}