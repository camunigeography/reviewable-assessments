<?php

# Abstract class to create an online reviewable assessment system; requires a form definition implementation
require_once ('frontControllerApplication.php');
abstract class reviewableAssessments extends frontControllerApplication
{
	# Function to assign defaults additional to the general application defaults
	public function defaults ()
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$defaults = array (
			'applicationName'			=> NULL,
			'hostname'					=> 'localhost',
			'username'					=> 'reviewableassessments',	/* Note that these are only used for the lookup of people and colleges from the people database, not the main application itself */
			'database'					=> 'reviewableassessments',
			'table'						=> 'submissions',
			'password'					=> NULL,
			'authentication'			=> true,
			'administrators'			=> true,
			'div'						=> 'reviewableassessments',
			'tabUlClass'				=> 'tabsflat',
			'description'				=> 'assessment',
			'userCallback'				=> NULL,		// NB Currently only a simple public function name supported
			'collegesCallback'			=> NULL,		// NB Currently only a simple public function name supported
			'dosListCallback'			=> NULL,		// NB Currently only a simple public function name supported
			'usersAutocomplete'			=> false,		// URL of an autocomplete JSON endpoint
			'emailDomain'				=> 'cam.ac.uk',
			'directorDescription'		=> NULL,		// E.g. 'XYZ Officer',
			'pdfStylesheets'			=> array (),
			'stage2Info'				=> false,		// To enable stage 2 info, define a string describing the information to be added, e.g. 'additional information'
			'types'						=> array ('Undergraduate', 'MPhil', 'PhD', 'Research staff', 'Academic staff', 'Other'),	// In order of appearance in the form and for the exemplars listing
			'listingAdditionalFields'	=> array (),
			'descriptionDefault'		=> false,		// Whether to create a default description when creating a new form
			'descriptionMaxLength'		=> 130,
		);
		
		# Return the defaults
		return $defaults;
	}
	
	
	# Function to assign supported actions
	public function actions ()
	{
		# Define available tasks
		$actions = array (
			'home' => array (
				'description' => false,
				'url' => '',
				'icon' => 'house',
				'tab' => 'Home',
			),
			'create' => array (
				'description' => false,
				'url' => 'new/',
				'tab' => 'New',
				'icon' => 'add',
			),
			'examples' => array (
				'url' => 'examples/',
				'tab' => 'Examplars',
				'description' => false,
				'icon' => 'page_white_stack',
				'enableIf' => $this->settings['exemplars'],
			),
			'submissions' => array (		// Available to all users
				'url' => 'submissions/',
				'description' => false,
				'tab' => ($this->user && $this->userIsReviewer ? ($this->userIsAdministrator ? 'All submissions' : 'Submissions') : NULL),		// Show only the tab if the user is logged-in and a reviewer
				'icon' => 'application_view_list',
			),
			'download' => array (
				'description' => 'Download data',
				'administrator' => true,
				'parent' => 'admin',
				'subtab' => 'Download data',
				'icon' => 'database_save',
			),
			'downloadcsv' => array (
				'description' => 'Download data',
				'administrator' => true,
				'export' => true,
			),
		);
		
		# Return the actions
		return $actions;
	}
	
	
	# Database structure definition
	public function databaseStructure ()
	{
		# Get the domain-specific fields
		$specificFields = $this->databaseStructureSpecificFields ();
		
		# Define the base SQL
		$sql = "
			CREATE TABLE IF NOT EXISTS `administrators` (
			  `username__JOIN__people__people__reserved` varchar(191) NOT NULL COMMENT 'Username',
			  `active` enum('','Yes','No') NOT NULL DEFAULT 'Yes' COMMENT 'Currently active?',
			  `privilege` enum('Administrator','Restricted administrator') NOT NULL DEFAULT 'Administrator' COMMENT 'Administrator level',
			  `state` varchar(255) DEFAULT NULL COMMENT 'State',
			  PRIMARY KEY (`username__JOIN__people__people__reserved`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='System administrators';
			
			CREATE TABLE `countries` (
			  `id` int(11) NOT NULL COMMENT 'Automatic key',
			  `value` varchar(191) NOT NULL COMMENT 'Country name',
			  `label` varchar(255) NOT NULL COMMENT 'Label',
			  PRIMARY KEY (`id`),
			  KEY `country` (`value`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Country names';
			
			CREATE TABLE IF NOT EXISTS `settings` (
			  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Automatic key (ignored)',
			  `directorUsername` VARCHAR(255) NOT NULL COMMENT 'Director username',
			  `peopleResponsible` TEXT NULL COMMENT 'People responsible, for specified groupings',
			  `additionalCompletionCc` TEXT NULL COMMENT 'Additional e-mail addresses to Cc on completion, for specified groupings',
			  `introductionHtml` text COMMENT 'Front page introduction text',
			  `feedbackHtml` TEXT NULL COMMENT 'Feedback page additional note',
			  `exemplars` TEXT NULL DEFAULT NULL COMMENT 'Exemplars (list of IDs, one per line)',
			  `approvalCoverSheetHtml` TEXT NULL DEFAULT NULL COMMENT 'Approval cover sheet template',
			  `logoImageFile` varchar(255) DEFAULT NULL COMMENT 'Logo image (.png only, max 200px), for cover letter',
			  `directorName` varchar(255) DEFAULT NULL COMMENT 'Director name',
			  `directorSignatureImageFile` varchar(255) DEFAULT NULL COMMENT 'Director signature image (.png only, max 120px), for cover letter',
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Settings';
			
			CREATE TABLE IF NOT EXISTS `submissions` (
			  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Automatic key',
			  `username` varchar(50) NOT NULL COMMENT 'CRSID',
			  `status` enum('started','submitted','reopened','deleted','archived','rejected','approved','parked') NOT NULL DEFAULT 'started' COMMENT 'Status of submission',
			  `parentId` int(11) DEFAULT NULL COMMENT 'Master record for this version (NULL indicates the master itself)',
			  `archivedVersion` int(11) DEFAULT NULL COMMENT 'Version number (NULL represents current, 1 is oldest, 2 is newer, etc.)',
			  `description` varchar(255) NOT NULL COMMENT 'Description',
			  `name` varchar(255) DEFAULT NULL COMMENT 'Your name',
			  `email` varchar(255) DEFAULT NULL COMMENT 'Your email',
			  `type` enum('" . implode ("','", $this->settings['types']) . "') DEFAULT NULL COMMENT 'Position/course',
			  `college` varchar(255) DEFAULT NULL COMMENT 'College',
			  `seniorPerson` varchar(255) DEFAULT NULL COMMENT 'Person responsible',
			  `currentReviewer` varchar(255) DEFAULT NULL COMMENT 'Current reviewer (initially same as seniorPerson, but a passup event can change this)',
			  `confirmation` TINYINT NULL DEFAULT '0' COMMENT 'Confirmation';
			  `reviewOutcome` varchar(255) DEFAULT NULL COMMENT 'Review outcome',
			  `comments` text COMMENT 'Comments from administrator',
			  `stage2InfoRequired` int(1) DEFAULT NULL COMMENT 'Stage 2 information required',
			  `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Automatic timestamp',
			  
			  {$specificFields}
			  
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Table of submissions';
		";
		
		# Return the SQL
		return $sql;
	}
	
	
	# Database structure, which the implementation should override
	public function databaseStructureSpecificFields ()
	{
		# Return the SQL
		return $sql = "
			  /* Domain-specific fields to be added here */
			  
		";
	}
	
	
	# Additional processing, run before actions is processed
	protected function mainPreActions ()
	{
		# Disable caching on home
		$this->globalActions['home']['nocache'] = true;
		
		# If using descriptionDefault, switch on name lookups
		if ($this->settings['descriptionDefault']) {
			$this->settings['useCamUniLookup'] = true;
		}
		
		# Get the list of Directors of Studies and determine if the user is a DoS
		$this->dosList = $this->getDosList ();
		$this->userIsDos = (isSet ($this->dosList[$this->user]));
		
		# Determine what submissions, if any, the user has rights to review
		$this->userHasReviewableSubmissions = $this->getReviewableSubmissions ();
		
		# Determine if the user has submissions that they can review, or it is expected that they will have in the future (e.g. because they are a DoS)
		$this->userIsReviewer = ($this->userHasReviewableSubmissions || $this->userIsAdministrator || $this->userIsDos);
		
		# Workaround for incorrect dataDirectory in FCA, as we need the child class, not this inherited parent
		$reflector = new ReflectionClass (get_class ($this));	// I.e. child class, e.g. fooAssessments.php
		$applicationRoot = dirname ($reflector->getFileName ());
		$this->dataDirectory = $applicationRoot . $this->settings['dataDirectory'];
	}
	
	
	# Function to get reviewable submissions
	private function getReviewableSubmissions ()
	{
		# Determine the conditions; if an administrator, there is no limitation
		$conditions = array ();
		if (!$this->userIsAdministrator) {
			#!# Should this be using currentReviewer now instead?
			$conditions = array ('seniorPerson' => $this->user);
		}
		
		# Get the data
		$data = $this->databaseConnection->selectPairs ($this->settings['database'], $this->settings['table'], $conditions, 'id');
		
		# Return the list
		return $data;
	}
	
	
	# Additional processing
	protected function main ()
	{
		# Define the internal fields that should not be made visible or editable by the user
		$this->internalFields = array ('id', 'username', 'status', 'parentId', 'archivedVersion', 'currentReviewer', 'reviewOutcome', 'comments', 'stage2InfoRequired', 'updatedAt');
		
		# Define private data fields, used for hiding in examples mode
		$this->privateDataFields = $this->privateDataFields ();
		
		# Define the review outcomes
		$this->reviewOutcomes = $this->reviewOutcomes ();
		
		# Define statuses; the keys here must be kept in sync with the status field's ENUM specification
		$this->statuses = $this->statuses ();
		
	}
	
	
	# Function to define the private data fields, used for hiding in examples mode; this is overrideable by the form definition implementation
	public function privateDataFields ()
	{
		return array ('name', 'email', 'college', 'seniorPerson');
	}
	
	
	# Function to define the review outcomes
	public function reviewOutcomes ()
	{
		# Define the review outcomes
		$reviewOutcomes = array (
			'rejected'						=> array (
				'setStatusTo'					=> 'rejected',
				'icon' 							=> 'cancel',
				'text' 							=> "Your {$this->settings['description']} has <strong>not been approved</strong> for the reasons given below. You will need to rethink your proposed project and submit a completely new assessment. If necessary, please visit to discuss the reasons for this.",
				'requireComments'				=> true,
				'emailSubject'					=> 'not approved',
			),
			'changesneeded'					=> array (
				'setStatusTo'					=> 'reopened',
				'icon' 							=> 'bullet_error',
				'text' 							=> 'You need to <strong>make changes</strong> to the form as per the following comments:',	// NB '<strong>make changes</strong> to the form' will get highlighted, so this string must remain
				'requireComments'				=> true,
				'createArchivalVersion'			=> true,
				'emailSubject'					=> 'changes needed',
			),
			#!# This appears even if the current director is reviewing
			'passup'						=> array (
				'setStatusTo'					=> 'submitted',
				'icon' 							=> 'bullet_go',
				'text'							=> "Your {$this->settings['description']} has been through initial review, and will now <strong>proceed to the next stage of review</strong> by the {$this->settings['directorDescription']}.",
				'emailSubject'					=> 'passed to next stage of review',
				'setCurrentReviewerToDirector'	=> true,
			),
			'stage2'						=> array (
				'setStatusTo'					=> 'reopened',
				'icon' 							=> 'bullet_go',
				'text' 							=> "You now need to <strong>add {$this->settings['stage2Info']}</strong>.",
				'directorOnly'					=> true,
				'createArchivalVersion'			=> true,
				'setStage2InfoRequired'			=> true,
				'emailSubject'					=> 'more information needed',
			),
			'changesstage2'					=> array (
				'setStatusTo'					=> 'reopened',
				'icon' 							=> 'bullet_go',
				'text' 							=> "You need to <strong>make changes</strong> to the form as per the comments below <strong>and also add {$this->settings['stage2Info']}</strong>.",	// NB '<strong>make changes</strong> to the form' will get highlighted, so these strings must remain
				'directorOnly'					=> true,
				'requireComments'				=> true,
				'setStage2InfoRequired'			=> true,
				'createArchivalVersion'			=> true,
				'emailSubject'					=> 'changes and more information needed',
			),
			'approved'						=> array (
				'setStatusTo'					=> 'approved',
				'icon'							=> 'tick',
				'text' 							=> "Your {$this->settings['description']} has been <strong>approved</strong>, and so you are now permitted to undertake the activity in line with your submission. Many thanks for your careful attention. Please print it out and take it with you.",
				'directorOnly'					=> true,
				'emailSubject'					=> 'approved',
			),
			'parked'						=> array (
				'setStatusTo'					=> 'parked',
				'icon'							=> 'control_pause',
				'text' 							=> "Set this {$this->settings['description']} as permanently <strong>parked</strong>. (This will not send a notification to the user.)",
				'directorOnly'					=> true,
				'emailSubject'					=> false,	// i.e. do not send an e-mail
			),
		);
		
		# Set undefined flags to false to avoid having to do isSet checks
		$optionalFlags = array ('requireComments', 'createArchivalVersion', 'setCurrentReviewerToDirector', 'directorOnly', 'setStage2InfoRequired');
		foreach ($reviewOutcomes as $id => $reviewOutcome) {
			foreach ($optionalFlags as $optionalFlag) {
				if (!isSet ($reviewOutcome[$optionalFlag])) {
					$reviewOutcomes[$id][$optionalFlag] = false;
				}
			}
		}
		
		# Remove stage2-related options if not enabled
		if (!$this->settings['stage2Info']) {
			unset ($reviewOutcomes['stage2']);
			unset ($reviewOutcomes['changesstage2']);
		}
		
		# Return the review outcomes
		return $reviewOutcomes;
	}
	
	
	# Function to define the statuses
	public function statuses ()
	{
		# Define statuses; the keys here must be kept in sync with the status field's ENUM specification
		return array (
			'started' => array (
				'icon' => 'page_edit',
				'editableBySubmitter' => true,
			),
			'submitted' => array (
				'icon' => 'page_find',
				'editableBySubmitter' => false,
			),
			'reopened' => array (
				'icon' => 'page_error',
				'editableBySubmitter' => true,
			),
			'deleted' => array (
				'icon' => 'page_delete',
				'editableBySubmitter' => false,
			),
			'archived' => array (
				'icon' => 'page',
				'editableBySubmitter' => false,
			),
			'rejected' => array (
				'icon' => 'page_red',
				'editableBySubmitter' => false,
			),
			'approved' => array (
				'icon' => 'page_green',
				'editableBySubmitter' => false,
			),
			'parked' => array (
				'icon' => 'control_pause',
				'editableBySubmitter' => false,
			),
		);
	}
	
	
	# Home page
	public function home ()
	{
		# Start the HTML
		$html  = '';
		
		# Show introduction HTML
		if ($this->userIsAdministrator) {
			$html .= "\n" . '<p class="actions right"><a href="' . $this->baseUrl . '/settings.html#form_introductionHtml"><img src="/images/icons/pencil.png" alt=""> Edit text</a></p>';
		}
		$html .= $this->settings['introductionHtml'];
		
		# Start new
		$html .= "\n<h2>Start a new {$this->settings['description']}</h2>";
		$html .= "\n<p><a class=\"actions\" href=\"{$this->baseUrl}/new/\"><img src=\"/images/icons/add.png\" alt=\"Add\" class=\"icon\" /> Start a <strong>new</strong> {$this->settings['description']}</a></p>";
		
		# List submitted forms
		$html .= $this->listSubmissions (array ('approved'), "Approved {$this->settings['description']} forms");
		$html .= $this->listSubmissions (array ('submitted'), 'Forms awaiting review');
		$html .= $this->listSubmissions (array ('reopened', 'started'), "Incomplete {$this->settings['description']} forms");
		$html .= $this->listSubmissions (array ('rejected'), ucfirst ($this->settings['descriptionPlural']) . ' not approved');
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to create a global submissions table
	private function unifiedSubmissionsTable ()
	{
		# Start the HTML
		$html = '';
		
		# End if not a reviewer
		if (!$this->userIsReviewer) {
			$html .= "\n<p>You do not appear to be a reviewer so have no access to this section. Please check the URL and try again.</p>";
			return $html;
		}
		
		# Add export button
		if ($this->userIsAdministrator) {
			$html .= "\n<p><a class=\"actions right\" href=\"{$this->baseUrl}/download.html\"><img src=\"/images/icons/database_save.png\" /> Download data</a></p>";
		}
		
		# Construct the HTML
		$html .= "\n<p>As a reviewer, you can see the following submissions:</p>";
		$submissionsTable = $this->listSubmissions (false, false, $reviewingMode = true);
		$html .= $submissionsTable;
		
		# Return the HTML
		return $html;
	}
	
	
	
	# Submissions (front controller)
	public function submissions ($id)
	{
		# Start the HTML
		$html = '';
		
		# Show the table of submissions if no ID supplied
		if (!$id) {
			$html .= $this->unifiedSubmissionsTable ();
			echo $html;
			return false;
		}
		
		# Ensure the ID is numeric or end
		if (!is_numeric ($id)) {
			$html .= "\n<p>The ID you supplied is not valid. Please check the URL and try again.</p>";
			echo $html;
			return false;
		}
		
		# If a version is specified, ensure it is numeric
		$version = false;
		if (isSet ($_GET['version'])) {
			
			# Ensure the version is numeric or end
			if (!is_numeric ($_GET['version']) || $_GET['version'] < 1) {
				$html .= "\n<p>The version you supplied is not valid. Please check the URL and try again.</p>";
				echo $html;
				return false;
			}
			$version = $_GET['version'];
			
			# Lookup up the actual ID of the record
			if (!$id = $this->getDatabaseIdOfVersion ($id, $version)) {
				$html .= "\n<p>The version you supplied is not valid. Please check the URL and try again.</p>";
				echo $html;
				return false;
			}
		}
		
		# Get the submission or end
		if (!$submission = $this->getSubmission ($id)) {
			$html .= "\n<p>The ID you supplied is not valid. Please check the URL and try again.</p>";
			echo $html;
			return false;
		}
		
		# Deny direct ID access to archived versions
		if ($submission['archivedVersion'] && !$version) {
			$html .= "\n<p>The ID you supplied is not valid. Please check the URL and try again.</p>";
			echo $html;
			return false;
		}
		
		# Ensure this ID is owned by this user
		if ($submission['username'] != $this->user) {
			if (!$this->userCanReviewSubmission ($id)) {
				$html .= "\n<p>You do not appear to have rights to view the specified submission. Please check the URL and try again.</p>";
				echo $html;
				return false;
			}
		}
		
		# Deny access if deleted
		if ($submission['status'] == 'deleted') {
			$html  = "\n" . '<p>The submission has been deleted.</p>';
			$html .= "\n" . "<p><a href=\"{$this->baseUrl}/\">Return to the home page.</a></p>";
			echo $html;
			return true;
		}
		
		# Determine the action, ensuring it is supported
		$action = 'show';
		if (isSet ($_GET['do'])) {
			$extraActions = array ('delete', 'clone', 'review', 'compare', 'reassign');
			if (!in_array ($_GET['do'], $extraActions)) {
				$this->page404 ();
				return false;
			}
			$action = $_GET['do'];
		}
		
		# For edit/clone/delete, check that the user has rights, or end
		$userHasEditCloneDeleteRights = $this->userHasEditCloneDeleteRights ($submission['username']);
		if (!$userHasEditCloneDeleteRights) {
			$disallowedActions = array ('edit', 'clone', 'delete');
			if (in_array ($action, $disallowedActions)) {
				$html .= "\n<p>You do not appear to have rights to {$action} the specified submission. Please check the URL and try again.</p>";
				echo $html;
				return false;
			}
		}
		
		# For reassign, check that the user has rights, or end
		if ($action == 'reassign') {
			if (!$this->userCanReassign ($submission)) {
				$html = "\n<p>You do not appear to have rights to reassign this submission.</p>";
				echo $html;
				return $html;
			}
		}
		
		# If showing, switch between show and edit depending on the status of the submission
		#!# Review whether the architecture here can be improved; see also the presence of 'edit' above
		if ($action == 'show') {
			if ($this->statuses[$submission['status']]['editableBySubmitter']) {
				if ($userHasEditCloneDeleteRights) {
					$action = 'edit';
				}
			}
		}
		
		# Create the HTML for this action
		$action = 'submission' . ucfirst ($action);		// e.g. submissionEdit, submissionReview, etc,
		$html = $this->{$action} ($submission);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to determine if a user can review a submission
	private function userCanReviewSubmission ($id)
	{
		return (in_array ($id, $this->userHasReviewableSubmissions));
	}
	
	
	# Function to view a submission
	private function submissionShow ($submission)
	{
		# Show title
		#!# Replace username with real name
		$html  = "\n<h2>" . ucfirst ($this->settings['description']) . " form by {$submission['username']} (#{$submission['id']})</h2>";
		
		# Display a flash message if set
		if ($flashValue = application::getFlashMessage ('submission' . $submission['id'], $this->baseUrl . '/')) {	// id is used to ensure the flash only appears attached to the matching submission
			$message = "\n" . "<p>{$this->tick} <strong>The submission has been " . htmlspecialchars ($flashValue) . ', as below:</strong></p>';
			$html .= "\n<div class=\"graybox flashmessage\">" . $message . '</div>';
		}
		
		# Show the form
		$html .= $this->viewSubmission ($submission);
		
		# If approved, and PDF export is requested, do export
		if (isSet ($_GET['export']) && $_GET['export'] == 'pdf') {
			$this->exportPdf ($html, $submission['status'], $submission['id'], $submission);
			return;
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to export as PDF
	private function exportPdf ($html, $status, $id, $submission)
	{
		# End if not approved
		if ($status != 'approved') {
			$this->page404 ();
			return false;
		}
		
		# Compile the stylesheets for each
		$stylesheetsHtml = '';
		foreach ($this->settings['pdfStylesheets'] as $stylesheetFilename) {
			$stylesheetsHtml .= "\n" . '<style type="text/css" media="all">@import "' . $stylesheetFilename . '";</style>';
		}
		
		# Assemble cover sheet if required
		$coverSheetHtml = '';
		if (trim ($this->settings['approvalCoverSheetHtml'])) {
			$coverSheetHtml  = str_repeat ('<p>&nbsp;</p>', 3);
			$coverSheetHtml .= $this->processTemplate ($this->settings['approvalCoverSheetHtml'], $submission);
			$coverSheetHtml .= "\n" . '<p style="page-break-after: always;">&nbsp;</p>';
		}
		
		# Add a timestamp heading
		$introductionHtml = "\n<p class=\"comment\">Printed at " . date ('g:ia, jS F Y') . " from {$_SERVER['_SITE_URL']}{$this->baseUrl}/submissions/{$id}/</p>\n<hr />";
		
		# Compile the HTML
		$pdfHtml  = $stylesheetsHtml;
		$pdfHtml .= $coverSheetHtml;
		$pdfHtml .= $introductionHtml;
		$pdfHtml .= "\n<div id=\"{$this->settings['div']}\">";
		$pdfHtml .= $html;
		$pdfHtml .= "\n</div>";
		
		# Serve the HTML and terminate all execution
		application::html2pdf ($pdfHtml, "assessment{$id}.pdf");
		exit;
	}
	
	
	# Function to templatise
	private function processTemplate ($template, $submission)
	{
		# Start an array of placeholders for replacement
		$replacements = array ();
		
		# Replace submission fields where present
		foreach ($submission as $field => $value) {
			$placeholder = '{' . $field . '}';
			if (substr_count ($template, $placeholder)) {
				$replacements[$placeholder] = $value;
			}
		}
		
		# Format special-case fields
		$replacements['{updatedAt}'] = date ('jS F, Y', strtotime ($submission['updatedAt']));
		$replacements['{seniorPerson}'] = strip_tags ($this->renderResponsiblePerson ($submission['seniorPerson'], $htmlNoEntities_ignored = array () /* modified by reference */));
		$replacements['{college}'] = $this->renderCollege ($submission['college']);
		
		# Similarly, substitute supported settings values
		$replacements['{settings.directorName}'] = $this->settings['directorName'];
		
		# Image files, and their max size
		$settings = array (
			'directorSignatureImageFile' => 120,
			'logoImageFile' => 300,
		);
		foreach ($settings as $setting => $maxSize) {
			$imageFile = $this->dataDirectory . $setting . '.png';
			if (file_exists ($imageFile)) {
				$imageFileBase64 = base64_encode (file_get_contents ($imageFile));
				$replacements["{settings.{$setting}}"] = "<img src=\"data:image/png;base64,{$imageFileBase64}\" style=\"max-width: {$maxSize}px; max-height: {$maxSize}px;\" />";
			}
		}
		
		# Substitute
		$template = strtr ($template, $replacements);
		
		# Return the template
		return $template;
	}
	
	
	# Function to edit a submission
	private function submissionEdit ($submission)
	{
		# Show title
		#!# Replace username with real name
		$html  = "\n<h2>" . ucfirst ($this->settings['description']) . " form by {$submission['username']} (#{$submission['id']})</h2>";
		
		# Display a flash message if set
		if ($flashValue = application::getFlashMessage ('submission' . $submission['id'], $this->baseUrl . '/')) {	// id is used to ensure the flash only appears attached to the matching submission
			$message = "\n" . "<p>{$this->tick} <strong>The submission has been " . htmlspecialchars ($flashValue) . ', as below:</strong></p>';
			$html .= "\n<div class=\"graybox flashmessage\">" . $message . '</div>';
		}
		
		# Show the form or view it, depending on the status
		$html .= $this->submissionProcessor ($submission);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to delete a submission
	private function submissionDelete ($submission)
	{
		# Start the HTML
		$html  = "\n<h2>Delete a {$this->settings['description']} form by {$submission['username']} (#{$submission['id']})</h2>";
		
		# If re-opening, change the database status then redirect
		if ($submission['status'] == 'submitted') {
			$html = "\n<p>This submission has been submitted so cannot now be deleted.</p>";
			return $html;
		}
		
		# Get any archive versions
		$archivedVersions = $this->getArchivedVersions ($submission['id']);
		$totalArchivedVersions = count ($archivedVersions);
		
		# Confirmation form
		$form = new form (array (
			'databaseConnection' => $this->databaseConnection,
			'formCompleteText' => false,
			'nullText' => '',
			'display' => 'paragraphs',
			'submitButtonText' => 'Delete submission permanently',
			'div' => 'graybox',
		));
		$form->heading ('p', "Do you really want to delete the submission below" . ($archivedVersions ? ' (and its ' . ($totalArchivedVersions == 1 ? 'earlier version' : "{$totalArchivedVersions} earlier versions") . ')' : '') . "? This cannot be undone.");
		$form->select (array (
			'name'				=> 'confirmation',
			'title'				=> 'Confirm deletion',
			'required'			=> true,
			'forceAssociative'	=> true,
			'values'			=> array ('Yes, delete this submission permanently'),
		));
		if (!$result = $form->process ($html)) {
			$html .= $this->viewSubmission ($submission, true);
			return $html;
		}
		
		# Set the status of the record (and any archived versions) as deleted
		$query = "UPDATE {$this->settings['database']}.{$this->settings['table']} SET status = 'deleted' WHERE id = :id OR parentId = :id;";
		if (!$this->databaseConnection->query ($query, array ('id' => $submission['id']))) {
			$html .= $this->reportError ("There was a problem setting the submission as deleted:\n\n" . print_r ($version, true), 'There was a problem deleting the submission. The Webmaster has been informed and will investigate shortly.');
			return $html;
		}
		
		# Redirect, resetting the HTML
		$html = "\n<p>{$this->tick} The submission has been deleted.</p>";
		
		# Redirect
		$redirectTo = "{$_SERVER['_SITE_URL']}{$this->baseUrl}/submissions/{$submission['id']}/";
		$html .= application::sendHeader (302, $redirectTo, true);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to review a submission
	private function submissionReview ($submission)
	{
		# Start the HTML
		$html  = "\n<h2>Review a {$this->settings['description']} form by {$submission['username']} (#{$submission['id']})</h2>";
		
		# Ensure the user has reviewing rights
		if (!$this->userCanReviewSubmission ($submission['id'])) {
			$html = "\n<p>You do not appear to have rights to review this submission.</p>";
			return $html;
		}
		
		# Ensure the submission has not been handed up to the Director
		$passedUp = ($submission['currentReviewer'] != $submission['seniorPerson']);	// Whether the submission is now in the hands of the Director
		if ($passedUp && !$this->userIsAdministrator) {
			$html = "\n<p>You have already passed this submission up to the {$this->settings['directorDescription']}, so you cannot now review the submission.</p>";
			return $html;
		}
		
		# Reviewing is only possible in the 'submitted' state, so that a versioning cannot be done twice accidentally
		if ($submission['status'] != 'submitted') {
			if ($submission['status'] == 'started' || $submission['status'] == 'reopened') {
				$html = "\n<p>The submission is <a href=\"{$this->baseUrl}/submissions/{$submission['id']}/\">open for editing</a>.</p>";
			} else {
				$html = "\n<p>The submission <a href=\"{$this->baseUrl}/submissions/{$submission['id']}/\">can be viewed</a>.</p>";
			}
			return $html;
		}
		
		# Obtain the review, or end
		if (!$reviewOutcome = $this->reviewForm ($submission)) {
			$html .= $this->viewSubmission ($submission, true);
			return $html;
		}
		
		# Create an archival version if necessary; effectively this is a cloned submission which references a parentId; if an error occurs, report this but continue
		if ($reviewOutcome['createArchivalVersion']) {
			$this->createArchivalVersion ($submission, $reviewOutcome['outcome'], $reviewOutcome['comments'], $html);
		}
		
		# Update the record
		$update = array (
			'status'				=> $reviewOutcome['setStatusTo'],
			'comments'				=> $reviewOutcome['comments'],		// May be blank
			'reviewOutcome'			=> $reviewOutcome['outcome'],
			'currentReviewer'		=> ($reviewOutcome['setCurrentReviewerToDirector'] ? $this->settings['directorUsername'] : $submission['currentReviewer']),		// Update, or retain original value
			'stage2InfoRequired'	=> ($reviewOutcome['setStage2InfoRequired'] ? 1 : $submission['stage2InfoRequired']),	// Update, or retain original value
			'updatedAt'				=> 'NOW()',	// Database library will convert from string to SQL keyword
		);
		$this->databaseConnection->update ($this->settings['database'], $this->settings['table'], $update, array ('id' => $submission['id']));
		
		# Determine the submission URL
		$submissionUrl = "{$_SERVER['_SITE_URL']}{$this->baseUrl}/submissions/{$submission['id']}/";
		
		# E-mail the review outcome if required
		$this->emailReviewOutcome ($submissionUrl, $submission, $update['currentReviewer'], $reviewOutcome['outcome'], $reviewOutcome['comments']);
		
		# Redirect to the submission URL, resetting the HTML
		$html = application::sendHeader (302, $submissionUrl, true);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to create the review outcomes form
	private function reviewForm ($submission)
	{
		# Start the HTML
		$html = '';
		
		# If the user can reassign, show a link to the reassign page
		if ($this->userCanReassign ($submission)) {
			$html .= "<p class=\"alignright\">Or <a class=\"submission\" href=\"{$this->baseUrl}/submissions/{$submission['id']}/reassign.html\"> reassign reviewer &hellip;</a></p>";
		}
		
		# Filter the review outcomes to those available for the current user
		$reviewOutcomes = array ();
		foreach ($this->reviewOutcomes as $id => $reviewOutcome) {
			if ($reviewOutcome['directorOnly'] && !$this->userIsAdministrator) {continue;}	// Omit Director-only items if not an admin
			$reviewOutcomes[$id] = $this->icon ($reviewOutcome['icon']) . ' ' . $reviewOutcome['text'];
		}
		
		# Create the form
		$form = new form (array (
			'display' => 'paragraphs',
			'div' => 'graybox ultimateform',
			'unsavedDataProtection' => true,
		));
		$form->heading ('p', 'Please review the submission below and submit the form. This will e-mail your decision, and a link to this page, to the original submitter.');
		$form->radiobuttons (array (
		    'name'		=> 'outcome',
		    'title'		=> 'Review outcome',
		    'values'	=> $reviewOutcomes,
		    'required'	=> true,
			'entities'	=> false,	// i.e. treat labels as incoming HTML
		));
		$form->textarea (array (
		    'name'		=> 'comments',
		    'title'		=> 'Comments (optional)',
		    'cols'		=> 80,
		    'rows'		=> 6,
		    'required'	=> false,
		));
		if ($unfinalisedData = $form->getUnfinalisedData ()) {
			if ($unfinalisedData['outcome']) {
				if ($this->reviewOutcomes[$unfinalisedData['outcome']]['requireComments']) {
					if (!strlen ($unfinalisedData['comments'])) {
						$form->registerProblem ('commentsneeded', 'You need to add comments.', 'comments');
					}
				}
			}
		}
		
		# Process the form
		if ($result = $form->process ($html)) {
			
			# Merge in the review outcomes attributes to the outcome ID and the comments
			$result += $this->reviewOutcomes[$result['outcome']];
		}
		
		# Show the HTML
		echo $html;
		
		# Return the result
		return $result;
	}
	
	
	# Function to create an archived submission, which clones the original to a new entry with a higher ID, so that the original ID is maintained
	private function createArchivalVersion ($version, $reviewOutcome, $reviewComments, &$html)
	{
		# Set the parentId and remove the current ID
		$version['parentId'] = $version['id'];
		unset ($version['id']);
		
		# Set the status, comments and review outcome
		$version['status'] = 'archived';
		$version['comments'] = $reviewComments;
		$version['reviewOutcome'] = $reviewOutcome;
		
		# Get the highest version number
		$maxArchivedVersion = $this->getMostRecentArchivedVersion ($version['parentId'], 'archivedVersion');
		$version['archivedVersion'] = $maxArchivedVersion + 1;
		
		# Set the last updated time
		$version['updatedAt'] = 'NOW()';	// Database library will convert to native function
		
		# Insert the new archival entry
		if (!$this->databaseConnection->insert ($this->settings['database'], $this->settings['table'], $version)) {
			$html = $this->reportError ("There was a problem creating the new version:\n\n" . print_r ($version, true), 'There was a problem archiving the old version of this submission. The Webmaster has been informed and will investigate shortly.');
		}
	}
	
	
	# Function to e-mail the review outcome
	private function emailReviewOutcome ($submissionUrl, $submission, $currentReviewer, $reviewOutcome /* i.e. the new status */, $comments)
	{
		# End if this review outcome type is set not to send an e-mail
		if (!$this->reviewOutcomes[$reviewOutcome]['emailSubject']) {return;}
		
		# Assemble the reviewer's details
		$userLookupData = camUniData::lookupUser ($this->user);
		$loggedInReviewerEmail = $this->user . "@{$this->settings['emailDomain']}";
		$loggedInReviewerName  = ($userLookupData ? $userLookupData['name']  : false);
		
		# Construct the message
		$message  = '';
		$message .= ($submission['name'] ? "\nDear {$submission['name']},\n\n" : '');
		$message .= "A {$this->settings['description']} that you submitted has been reviewed.";
		$message .= "\n\n" . str_repeat ('-', 74);
		$message .= "\n\n" . strip_tags ($this->reviewOutcomes[$reviewOutcome]['text']);
		if ($comments) {$message .= "\n\n" . $comments;}
		$message .= "\n\n" . str_repeat ('-', 74);
		$message .= "\n\nAccess it at:\n{$submissionUrl}";
		if ($reviewOutcome == 'approved') {
			$message .= "\n\nYou can also download a printable PDF at:\n{$submissionUrl}assessment{$submission['id']}.pdf";
		}
		$message .= "\n\n\nThanks" . ($loggedInReviewerName ? ",\n\n{$loggedInReviewerName}" : '.');
		
		# The recipient is always the original submitter
		$to = ($submission['name'] ? "{$submission['name']} <{$submission['email']}>" : $submission['email']);
		
		# Construct the message details
		$subject = ucfirst ($this->settings['description']) . " (#{$submission['id']}) review: " . $this->reviewOutcomes[$reviewOutcome]['emailSubject'];
		$headers  = 'From: ' . ($loggedInReviewerName ? "{$loggedInReviewerName} <{$loggedInReviewerEmail}>" : $loggedInReviewerEmail);
		
		# Determine Cc
		$cc = array ();
		if ($currentReviewer != $submission['seniorPerson']) {	// i.e. Copy in the DoS if in passup
			if ($reviewOutcome == 'passup') {	// On the specific passup event, copy in the main reviewer
				$cc[] = $currentReviewer . (str_contains ($currentReviewer, '@') ? '' : "@{$this->settings['emailDomain']}");
			}
			$cc[] = $submission['seniorPerson'] . "@{$this->settings['emailDomain']}";	// Copy to DoS; this is done even at passup as a courtesy e.g. to make contact more easily with the Director
		}
		
		# On final approval (only), if additional completion e-mail addresses are specified, and the type (e.g. 'Undergraduate') matches, add that address on completion
		if ($reviewOutcome == 'approved') {
			if (trim ($this->settings['additionalCompletionCc'])) {
				$additionalCompletionCcAddresses = array ();
				$additionalCompletionCc = preg_split ("/\s*\r?\n\t*\s*/", trim ($this->settings['additionalCompletionCc']));
				foreach ($additionalCompletionCc as $additionalCompletionCcLine) {
					list ($courseMoniker, $emailAddressString) = explode (',', $additionalCompletionCcLine, 2);
					$additionalCompletionCcAddresses[$courseMoniker] = trim ($emailAddressString);	// e.g. 'Undergraduate' => 'foo@example.com, bar@example.com'
				}
				if (isSet ($additionalCompletionCcAddresses[$submission['type']])) {
					$cc[] = $additionalCompletionCcAddresses[$submission['type']];
				}
			}
		}
		
		# Add Cc if set
		if ($cc) {
			$headers .= "\r\nCc: " . implode (', ', $cc);
		}
		
		# Send the message
		application::utf8Mail ($to, $subject, wordwrap ($message), $headers);
	}
	
	
	# Function to get the most recent archived version
	private function getMostRecentArchivedVersion ($parentId, $field = '*')
	{
		# Get the data
		$result = $this->databaseConnection->selectOne ($this->settings['database'], $this->settings['table'], array ('parentId' => $parentId), array ($field), false, $orderBy = 'archivedVersion DESC', $limit = 1);
		
		# If a specific field return that; otherwise return everything
		if ($field != '*') {return $result[$field];}
		return $result;
	}
	
	
	# Function to compare versions of a submission
	private function submissionCompare ($submission)
	{
		# Start the HTML
		$html  = '';
		
		# Get any archive versions
		if (!$versionDescriptions = $this->getArchivedVersions ($submission['id'], 'text')) {
			$html = "\n<p>There are no earlier versions of this submission.</p>";
			return $html;
		}
		
		# Add on the current version
		$parentId = $submission['id'];
		$versionDescriptions[$parentId] = "Current version [{$submission['status']}] - updated " . $this->formatDate ($submission['updatedAt']);
		
		# Get a mapping of (internal) ID version to public
		$versionIds = $this->getArchivedVersions ($submission['id'], 'version');
		$versionIds[$parentId] = 'current';
		$versionIds = array_flip ($versionIds);
		
		# Create a lookup of public identifier (e.g. 1/2/3/.../current) => title; this is done to avoid exposing the internal IDs of versions
		$versions = array ();
		foreach ($versionIds as $publicIdentifier => $internalId) {
			$versions[$publicIdentifier] = $versionDescriptions[$internalId];
		}
		
		# Prepare lists
		$allExceptLast = array_slice ($versions, NULL, -1, true);
		$allExceptLastKeys = array_keys ($allExceptLast);
		$allExceptFirst = array_slice ($versions, 1, NULL, true);
		$allExceptFirstKeys = array_keys ($allExceptFirst);
		
		# Confirmation form
		#!# The form in GET mode should only check variables it cares about, not the whole environment
		unset ($_GET['action'], $_GET['item'], $_GET['do']);
		$form = new form (array (
			'databaseConnection' => $this->databaseConnection,
			'formCompleteText' => false,
			'nullText' => '',
			'display' => 'paragraphs',
			'submitButtonText' => 'Compare versions',
			'requiredFieldIndicator' => false,
			'div' => 'graybox',
			'reappear' => true,
			'get' => true,
			'name' => false,
		));
		$form->heading ('', "<p class=\"small right\"><a href=\"{$this->baseUrl}/submissions/{$submission['id']}/compare.html\">Reset this form to default</a></p>");
		$form->select (array (
			'name'				=> 'from',
			'title'				=> 'Compare from',
			'values'			=> $allExceptLast,
			'default'			=> end ($allExceptLastKeys),
			'required'			=> true,
			'forceAssociative'	=> true,
		));
		$form->select (array (
			'name'				=> 'to',
			'title'				=> '&hellip; to',
			'values'			=> $allExceptFirst,
			'default'			=> end ($allExceptFirstKeys),
			'required'			=> true,
			'forceAssociative'	=> true,
		));
		$form->validation ('different', array ('from', 'to'));
		if ($unfinalisedData = $form->getUnfinalisedData ()) {
			if ($unfinalisedData['from'] && $unfinalisedData['to']) {
				if ($unfinalisedData['to'] < $unfinalisedData['from']) {
					if ($unfinalisedData['to'] != $submission['id']) {	// The latest version, which only appears in 'to' will have a lower number; if 'to' is the submission ID, then there can never be a match
						$form->registerProblem ('order', 'You must compare an earlier version to a later version', array ('from', 'to'));
					}
				}
			}
		}
		if (!$result = $form->process ($html)) {return $html;}
		
		# Translate the public identifier values back to internal IDs
		$result['from'] = $versionIds[$result['from']];
		$result['to'] = $versionIds[$result['to']];
		
		# Get the data for each requested version
		$from = $this->getSubmission ($result['from']);
		$to   = $this->getSubmission ($result['to']);
		
		# Determine the sections that have changed
		$changes = $this->diff ($from, $to);
		
		# Show the submission
		$html .= $this->viewSubmission ($to, true, $changes);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to format the date
	private function formatDate ($date)
	{
		return date ('j/M/Y, g:ia', strtotime ($date));
	}
	
	
	# Function to determine the changes that have been made
	private function diff ($from, $to)
	{
		# Determine the changes, skipping internal fields
		$changes = array ();
		foreach ($to as $field => $currentValue) {
			
			# Do not compare internal fields
			if (in_array ($field, $this->internalFields)) {continue;}
			
			# Cast empty values to NULL to prevent NULL != '' which is not a relevant change
			$originalValue = $from[$field];
			if (!strlen ($originalValue)) {$originalValue = NULL;}
			#!# For the confirmation widget, this generates "strlen() expects parameter 1 to be string, array given"
			if (!strlen ($currentValue)) {$currentValue = NULL;}
			
			# Compare, adding differences to a list of changes noting the original value
			if ($originalValue !== $currentValue) {
				$changes[$field] = $originalValue;
			}
		}
		
		# Return the changes, if any
		return $changes;
	}
	
	
	# Function to reassign a submission
	private function submissionReassign ($submission)
	{
		# Start the HTML
		$html  = "\n<h2>Reassign a {$this->settings['description']} form by {$submission['username']} (#{$submission['id']})</h2>";
		
		# Ensure the user has reassign rights
		if (!$this->userCanReassign ($submission)) {
			$html = "\n<p>You do not appear to have rights to reassign this submission.</p>";
			return $html;
		}
		
		# Reviewing is only possible in the 'submitted' state
		if ($submission['status'] != 'submitted') {
			if ($submission['status'] == 'started' || $submission['status'] == 'reopened') {
				$html = "\n<p>The submission is <a href=\"{$this->baseUrl}/submissions/{$submission['id']}/\">open for editing</a>.</p>";
			} else {
				$html = "\n<p>The submission <a href=\"{$this->baseUrl}/submissions/{$submission['id']}/\">can be viewed</a>.</p>";
			}
			return $html;
		}
		
		# Obtain the new reviewer, or end
		if (!$newReviewer = $this->reassignForm ()) {
			$html .= $this->viewSubmission ($submission, true);
			return $html;
		}
		
		# Update the record
		$update = array (
			'seniorPerson'		=> $newReviewer,
			'currentReviewer'	=> $newReviewer,
			'updatedAt'			=> 'NOW()',	// Database library will convert from string to SQL keyword
		);
		$this->databaseConnection->update ($this->settings['database'], $this->settings['table'], $update, array ('id' => $submission['id']));
		
		# Determine the submission URL
		$submissionUrl = "{$_SERVER['_SITE_URL']}{$this->baseUrl}/submissions/{$submission['id']}/";
		
		# E-mail the reassignment notification
		$newReviewerName = $this->emailReassignNotification ($submissionUrl, $submission, $newReviewer);
		
		# Confirm success
		$html  = "\n<p>The <a href=\"{$submissionUrl}\">submission</a> has now been reassigned to {$newReviewerName}, and they have been sent an e-mail.</p>";
		$html .= "\n<p><a href=\"{$this->baseUrl}/submissions/\">Return to the list of submissions.</a></p>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to determine whether a user has rights to reassign a submission
	private function userCanReassign ($submission)
	{
		# Admins can reassign
		if ($this->userIsAdministrator) {return true;}
		
		# The director can reassign
		if ($this->user == $this->settings['directorUsername']) {return true;}
		
		# The current reviewer can reassign
		if ($this->user == $submission['currentReviewer']) {return true;}
		
		# Otherwise no rights
		return false;
	}
	
	
	# Function to create the reviewer reassignment form
	private function reassignForm ()
	{
		# Create the form
		$form = new form (array (
			'div' => 'graybox ultimateform',
			'display' => 'paragraphs',
			'displayRestrictions' => false,
			'formCompleteText' => false,
		));
		$form->heading ('p', 'You can reassign the reviewer using this form. They will be informed by e-mail. (The original submitter will not be informed.)');
		$form->input (array (
			'name'					=> 'username',
			'title'					=> 'Reassign the reviewer to',
			'required'				=> true,
			'size'					=> 50,
			'description'			=> '(This should be a username.)',
			'autocomplete'			=> $this->settings['usersAutocomplete'],
			'autocompleteOptions'	=> array ('delay' => 0),
			'regexp'				=> '^([a-z0-9]+)$',
			'placeholder'			=> 'Type a username or name to search',
		));
		
		# Process the form or end
		if (!$result = $form->process ($html)) {return false;}
		
		# Return the username of the new reviewer
		return $result['username'];
	}
	
	
	# Function to e-mail reassignment notification
	private function emailReassignNotification ($submissionUrl, $submission, $newReviewer)
	{
		# Assemble the current user's details
		$userLookupData = camUniData::lookupUser ($this->user);
		$loggedInReviewerEmail = $this->user . "@{$this->settings['emailDomain']}";
		$loggedInReviewerName  = ($userLookupData ? $userLookupData['name']  : false);
		
		# Assemble the new reviewer's details
		#!# Ideally the saluation below should just use their first name, which is more friendly
		$userLookupData = camUniData::lookupUser ($newReviewer);
		$newReviewerEmail = $newReviewer . "@{$this->settings['emailDomain']}";
		$newReviewerName  = ($userLookupData ? $userLookupData['name']  : false);
		
		# Construct the message
		$message  = '';
		$message .= ($submission['name'] ? "\nDear {$newReviewerName},\n\n" : '');
		$message .= "I have reassigned a {$this->settings['description']} to you for review.";
		$message .= "\n\nPlease kindly review it at:\n{$submissionUrl}review.html";
		$message .= "\n\n\nThanks" . ($loggedInReviewerName ? ",\n\n{$loggedInReviewerName}" : '.');
		
		# The recipient is the new receivewer
		$to = ($newReviewerName ? "{$newReviewerName} <{$newReviewerEmail}>" : $newReviewerEmail);
		
		# Construct the message details
		$subject = ucfirst ($this->settings['description']) . " (#{$submission['id']}) reassigned to you";
		$headers  = 'From: ' . ($loggedInReviewerName ? "{$loggedInReviewerName} <{$loggedInReviewerEmail}>" : $loggedInReviewerEmail);
		
		# Send the message
		application::utf8Mail ($to, $subject, wordwrap ($message), $headers);
		
		# Return the new reviewer's name, for use on the confirmation page
		return $newReviewerName;
	}
	
	
	# Function to list submissions
	private function listSubmissions ($statuses = false, $heading = false, $reviewingMode = false)
	{
		# Start the HTML
		$html = '';
		
		# Add a status filtering box if listing every submission
		$sinceMonths = false;
		if ($reviewingMode) {
			list ($statuses, $sinceMonths) = $this->statusFiltering ($html);
		}
		
		# End if none
		if (!$submissions = $this->getSubmissions ($statuses, $sinceMonths, $reviewingMode)) {
			if ($reviewingMode) {
				$html .= "\n<p><em>There are none.</em></p>";
			}
			return $html;
		}
		
		# Add heading if required
		if ($heading) {
			$html .= "\n<h2>{$heading}</h2>";
		}
		
		# Show total, if required
		if ($reviewingMode) {
			$totalSubmissions = count ($submissions);
			$html .= "\n<p class=\"small comment\">Total shown: <strong>{$totalSubmissions}</strong>.</p>";
		}
		
		# Get the archived versions (if any) for these submissions
		$archivedVersions = $this->getArchivedVersions (array_keys ($submissions));
		
		# Determine whether to show the clone/delete links
		$showCrudLinks = (!$reviewingMode || $this->userIsAdministrator);
		
		# Create a table
		$table = array ();
		foreach ($submissions as $id => $submission) {
			if ($reviewingMode) {
				$table[$id]['id'] = $submission['id'];
			}
			$isSubmitted = ($submission['status'] == 'submitted');
			$icon = $this->statuses[$submission['status']]['icon'];
			$table[$id]['title'] = "\n<a href=\"{$this->baseUrl}/submissions/{$id}/\"><img src=\"/images/icons/{$icon}.png\" alt=\"Add\" class=\"icon\" /> " . htmlspecialchars ($submission['description']) . '</a>';
			if ($reviewingMode) {
				$table[$id]['submittedBy'] = $submission['username'] . ($submission['name'] ? ' - ' . htmlspecialchars ($submission['name']) : '');
			}
			if ($this->userHasReviewableSubmissions) {	// I.e. show or hide the whole row to keep the column placement correct
				
				# Determine whether the review link can be shown
				$showReviewLink = ($isSubmitted && ($this->user == $submission['currentReviewer']));
				$table[$id]['changes'] = ($showReviewLink ? "<a href=\"{$this->baseUrl}/submissions/{$id}/review.html\">Review&hellip;</a>" : '');
				$table[$id]['type'] = $submission['type'];
			}
			$table[$id]['status'] = ucfirst ($submission['status']);
			#!# Add ability to see name on hover
			$table[$id]['Currently with'] = $submission['currentReviewer'];
			if ($this->settings['listingAdditionalFields']) {
				if ($reviewingMode) {
					foreach ($this->settings['listingAdditionalFields'] as $additionalField) {
						$table[$id][$additionalField] = htmlspecialchars ($submission[$additionalField]);
					}
				}
			}
			$table[$id]['date'] = ($reviewingMode ? $submission['updatedAt'] : $this->formatDate ($submission['updatedAt']));	// When sortability is enabled (i.e. list every submission), date needs to be ISO format so that it will sort alphabetically
			if ($showCrudLinks) {
				$table[$id]['default'] = "<a href=\"{$this->baseUrl}/submissions/{$id}/\">" . ($isSubmitted ? 'View' : 'Edit') . '</a>';
				$table[$id]['clone'] = "<a href=\"{$this->baseUrl}/new/{$id}/\" title=\"Do NOT use this for submitting corrections\">Clone&hellip;</a>";
				$table[$id]['delete'] = ($isSubmitted ? '' : "<a href=\"{$this->baseUrl}/submissions/{$id}/delete.html\">Delete&hellip;</a>");
			}
			if ($archivedVersions) {
				$table[$id]['archivedVersions'] = (isSet ($archivedVersions[$id]) ? implode (' ', $archivedVersions[$id]) . " - <a href=\"{$this->baseUrl}/submissions/{$id}/compare.html\">Compare</a>" : '');
			}
		}
		
		# Compile the HTML
		$tableHeadingSubstitutions = array ('id' => '#', 'title' => ($reviewingMode ? 'Title' : ''), 'date' => 'Last saved', 'submittedBy' => 'Submitted by', 'default' => false, 'changes' => false, 'delete' => false, 'clone' => false, 'archivedVersions' => 'Older versions');
		if ($reviewingMode) {$html .= "\n" . '<!-- Enable table sortability: --><script language="javascript" type="text/javascript" src="/sitetech/sorttable.js"></script>';}
		$html .= application::htmlTable ($table, $tableHeadingSubstitutions, 'lines listing' . ($reviewingMode ? ' sortable" id="sortable' : ''), $keyAsFirstColumn = false, $uppercaseHeadings = true, $allowHtml = true, false, $addCellClasses = true, false, array (), false, $showHeadings = true);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to provide a status filtering box
	private function statusFiltering (&$html)
	{
		# By default, show all except deleted
		$statuses = $this->statuses;
		unset ($statuses['deleted']);
		$statuses = array_keys ($statuses);
		
		# Determine the default 'since' date
		$sinceMonths = 12;	// Must be one of values in form below
		
		# Reset if required
		if (isSet ($_GET['reset'])) {
			$this->saveStatusState ($statuses, $sinceMonths);
		}
		
		# Load existing state if present
		if ($state = $this->getStatusState ($sinceMonths)) {
			list ($statuses, $sinceMonths) = $state;
		}
		
		# Create a form of checkboxes for each status
		$form = new form (array (
			'databaseConnection' => $this->databaseConnection,
			'displayRestrictions' => false,
			'formCompleteText' => false,
			'reappear' => true,
			'display' => 'template',
			'displayTemplate' => "{[[PROBLEMS]]} <strong>Show:</strong> {statuses} &nbsp; &nbsp; &mdash; saved in {sinceMonths} &nbsp; {[[SUBMIT]]} <span class=\"small\">or <a href=\"{$this->baseUrl}/submissions/?reset=true\">reset</a></span>",
			'requiredFieldIndicator' => false,
			'div' => 'graybox',
			'submitButtonText' => 'Show',
			'submitButtonAccesskey' => false,
		));
		$form->checkboxes (array (
			'name' => 'statuses',
			'title' => '<strong>Filter to</strong>',
			'required' => true,
			'values' => array_keys ($this->statuses),
			'valuesNamesAutomatic' => true,
			'default' => $statuses,
			'linebreaks' => false,
		));
		$form->select (array (
			'name'			=> 'sinceMonths',
			'title'			=> 'Since',
			'required' 		=> true,
			'values'		=> array (
				1		=> 'last month',
				3		=> 'last 3 months',
				6		=> 'last 6 months',	// Default noted above
				12		=> 'last 12 months',
				24		=> 'last 2 years',
				9999	=> '(no date filter)',
			),
			'default'	=> $sinceMonths,
		));
		if ($result = $form->process ($html)) {
			
			# Convert to list of chosen fields
			$statuses = array ();
			foreach ($result['statuses'] as $status => $selected) {
				if ($selected) {
					$statuses[] = $status;
				}
			}
			
			# Since months
			$sinceMonths = $result['sinceMonths'];
			
			# Save state
			$this->saveStatusState ($statuses, $sinceMonths);
		}
		
		# Return the filtered status list
		return array ($statuses, $sinceMonths);
	}
	
	
	# Function to save status state
	private function saveStatusState ($statuses, $sinceMonths)
	{
		# Currently available only to administrators because ordinary users have no database user profile (and in practice ordinary users will have few assessments anyway)
		if (!$this->userIsAdministrator) {return;}
		
		# Save the state
		$state = implode (',', $statuses) . ';' . $sinceMonths;
		$this->databaseConnection->update ($this->settings['database'], $this->settings['administrators'], array ('state' => $state), array ('username__JOIN__people__people__reserved' => $this->user));
		
		# Redirect to the current page (to avoid post warnings) and create failover HTML
		$url = $_SERVER['_SITE_URL'] . $_SERVER['SCRIPT_URL'];
		$html = application::sendHeader (302, $url, true);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to get status state
	private function getStatusState ($sinceMonthsDefault)
	{
		# End if not an administrator
		if (!$this->userIsAdministrator) {return false;}
		
		# If the status is empty, return the default
		if (!$this->administrators[$this->user]['state']) {
			$this->administrators[$this->user]['state'] = 'started,submitted,reopened,archived,rejected,approved,parked;12';
		}
		
		# Explode the state, which is stored as "status1,status2;sinceMonths"
		list ($statuses, $sinceMonths) = explode (';', $this->administrators[$this->user]['state'], 2);
		
		# Explode the statuses
		$statuses = explode (',', $statuses);
		
		# Ensure the months are numeric
		if (!ctype_digit ($sinceMonths)) {
			$sinceMonths = $sinceMonthsDefault;
		}
		
		# Return the values
		return array ($statuses, $sinceMonths);
	}
	
	
	# Function to determine if the user has clone/delete rights
	public function userHasEditCloneDeleteRights ($submissionUsername)
	{
		# Adminstrators have full rights
		if ($this->userIsAdministrator) {return true;}
		
		# The owner has full rights
		if ($submissionUsername == $this->user) {return true;}
		
		# No rights
		return false;
	}
	
	
	# Function to create a new assessment
	public function create ($cloneId = false)
	{
		# Start the HTML
		$html = "\n<h2>Start a new {$this->settings['description']}" . ($cloneId ? ' (based on an existing one)' : '') . "</h2>";
		
		# Build up a new submission
		$data = array ();
		
		# Ensure the user is registered the database, and look up the user's status
		$userData = $this->getUser ($this->user, $errorHtml /* returned by reference */);
		
		# End if no user data
		if (!$userData && !$this->userIsAdministrator) {	// Admins can see the form
			if ($errorHtml) {
				$html = $errorHtml;
			} else {
				$html .= "<p>In order to use this system, you must firstly be registered in our database.</p>";
				$html .= "<p>At present, the website does not have a record of your details, though this can be because of delays at certain times of year in data being added.</p>";
				$message = "Please register me for the {$this->settings['description']} system. My details are as follows:\n\nStatus [delete as appropriate]:\nstaff/student/external";
				$html .= "<p><strong>Please <a href=\"{$this->baseUrl}/feedback.html?message=" . htmlspecialchars (urlencode ($message)) . "\">contact us</a> with your details so we can add you quickly.</strong></p>";
			}
			echo $html;
			return false;
		}
		
		# If cloning, fetch a submission, ensuring the user has rights to the source ID
		if ($cloneId) {
			if ($submission = $this->getSubmission ($cloneId)) {
				
				# Check that the user has rights, or end
				if (!$this->userHasEditCloneDeleteRights ($submission['username'])) {
					$html .= "\n<p>You do not appear to have rights to clone the specified submission. Please check the URL and try again, or create a <a href=\"{$this->baseUrl}/new/\">new submission</a>.</p>";
					echo $html;
					return false;
				}
				
				# Write the submission into the data to be entered after the form
				unset ($submission['id']);
				unset ($submission['username']);
				unset ($submission['updatedAt']);
				$submission['status'] = 'started';
				$data = $submission;
			}
		}
		
		# Determine default for description
		$descriptionDefault = ($this->settings['descriptionDefault'] ? mb_ucfirst ($this->settings['description']) . ' for ' . $this->userName : false);
		
		# Create a new form
		$form = new form (array (
			'div' => 'ultimateform newassessment',
			'display' => 'paragraphs',
			'displayRestrictions' => false,
			'autofocus' => true,
			'display' => 'tables',
			'displayColons' => false,
		));
		if ($data) {
			$form->heading ('p', "This form will start a <strong>new</strong> {$this->settings['description']} based on the <a href=\"{$this->baseUrl}/submissions/{$cloneId}/\">existing {$this->settings['description']}</a> you specified.");
			$form->heading ('', "<p class=\"warning\">Do <strong>NOT</strong> use this for submitting changes to an existing assessment. Instead, <a href=\"{$this->baseUrl}/submissions/{$cloneId}/\">submit changes</a> if you have been asked to do this.</p>");
		}
		$form->input (array (
			'name'			=> 'description',
			'title'			=> "Give this {$this->settings['description']} a description (you can change this later)",
			'required'		=> true,
			'size'			=> 60,
			'maxlength'		=> $this->settings['descriptionMaxLength'],
			'default'		=> ($data ? $data['description'] : $descriptionDefault),
		));
		
		# Process the form
		if (!$result = $form->process ($html)) {
			echo $html;
			return true;
		}
		
		# Add in the form data
		$data['description'] = $result['description'];
		
		# Add in fixed data
		$data['username'] = $this->user;
		$data['name'] = $userData['name'];
		$data['email'] = $userData['username'] . '@' . $this->settings['emailDomain'];
		$data['type'] = $this->userType ($userData);	// (e.g. Undergraduate/Postgraduate/Research staff)
		
		# Set the last updated time
		$data['updatedAt'] = 'NOW()';	// Database library will convert to native function
		
		# Create a new database entry
		if (!$result = $this->databaseConnection->insert ($this->settings['database'], $this->settings['table'], $data)) {
			$html = "<p class=\"warning\">An error occured when adding the new entry. Please contact the Webmaster.</p>";
			// application::dumpData ($this->databaseConnection->error ());
			echo $html;
			return true;
		}
		
		# Redirect to the created entry
		$id = $this->databaseConnection->getLatestId ();
		$redirectTo = "{$_SERVER['_SITE_URL']}{$this->baseUrl}/submissions/{$id}/";
		$html = application::sendHeader (302, $redirectTo, true);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to assign the user type (e.g. Undergraduate/Postgraduate/Research staff); if making changes to submissions.type, this function must be kept in sync
	#!# Need to migrate the status fields in the logic so they can directly use isUndergraduate/isGraduate/isStaffInternal
	private function userType ($userData)
	{
		# Undergraduates
		if ($userData['isUndergraduate']) {return 'Undergraduate';}
		
		# Postgraduates
		if ($userData['isGraduate']) {
			
			# MPhil courses
			if (preg_match ('/^mphil/', $userData['course__JOIN__people__courses__reserved'])) {return 'MPhil';}
			
			# PhD
			if (preg_match ('/^phd/', $userData['course__JOIN__people__courses__reserved'])) {return 'PhD';}
			
			# Generic postgraduate
			return 'Other';
		}
		
		# Staff (internal)
		if ($userData['isStaff'] && $userData['isStaffInternal']) {
			
			# Academic related
			if (preg_match ('/^Academic-related/', $userData['staffType'])) {return 'Other';}
			
			# Academic staff
			if (preg_match ('/^Academic/', $userData['staffType'])) {return 'Academic staff';}
			
			# Research staff
			if (preg_match ('/^Research/', $userData['staffType'])) {return 'Research staff';}
		}
		
		# Otherwise, use other
		return 'Other';
	}
	
	
	# Function to get the actual ID of a specified version
	private function getDatabaseIdOfVersion ($parentId, $version)
	{
		# Define the conditions
		$conditions = array (
			'parentId'			=> $parentId,
			'archivedVersion'	=> $version,
		);
		
		# Get the submission
		if (!$databaseIdOfVersion = $this->databaseConnection->selectOneField ($this->settings['database'], $this->settings['table'], 'id', $conditions)) {return false;}
		
		# Return the ID
		return $databaseIdOfVersion;
	}
	
	
	# Function to retrieve an existing submission
	private function getSubmission ($id)
	{
		# Return the submission
		return $this->databaseConnection->selectOne ($this->settings['database'], $this->settings['table'], array ('id' => $id));
	}
	
	
	# Function to get the user's submissions
	private function getSubmissions ($status = false, $sinceMonths = false, $reviewingMode = false, $specificIds = array ())
	{
		# Start an array of conditions
		$conditions = array ();
		$preparedStatementValues = array ();
		
		# Never list archived versions
		$conditions[] = 'archivedVersion IS NULL';
		
		# In reviewing mode, check for reviewable submissions; otherwise, limit to the current user
		if ($reviewingMode) {
			if (!$this->userIsAdministrator) {
				$conditions[] = 'seniorPerson = :seniorPerson';
				$preparedStatementValues['seniorPerson'] = $this->user;
			}
		} else {
			$conditions[] = 'username = :username';
			$preparedStatementValues['username'] = $this->user;
		}
		
		# Status
		if ($status) {
			if (is_array ($status)) {
				$conditions[] = "status IN('" . implode ("','", $status) . "')";
			} else {
				$conditions[] = "status = '{$status}'";
			}
		}
		
		# Omit deleted
		$conditions[] = "status != 'deleted'";
		
		# Since months
		if ($sinceMonths) {
			$conditions[] = "updatedAt >= DATE_SUB(CURDATE(), INTERVAL {$sinceMonths} MONTH)";
		}
		
		# Specific IDs
		#!# Currently this resets $conditions and $preparedStatementValues, so that $this->user is not injected above; need to refactor to make supply of $this->user explicit
		if ($specificIds) {
			$conditions = array ();
			$preparedStatementValues = array ();
			$sql = array ();
			foreach ($specificIds as $index => $specificId) {
				$token = 'id' . $index;
				$sql[] = ':' . $token;
				$preparedStatementValues[$token] = $specificId;
			}
			$conditions[] = 'id IN (' . implode (',', $sql) . ')';
		}
		
		# Get the list
		$query = "SELECT
			*
			FROM {$this->settings['table']}
			" . ($conditions ? 'WHERE ' . implode (' AND ', $conditions) : '') . "
			ORDER BY " . ($status ? 'status DESC, ' : '') . "updatedAt
		;";
		$submissions = $this->databaseConnection->getData ($query, "{$this->settings['database']}.{$this->settings['table']}", true, $preparedStatementValues);
		
		# Return the list
		return $submissions;
	}
	
	
	# Function to get archived versions for a set of master records
	private function getArchivedVersions ($masterIds, $format = 'link')
	{
		# If only a single ID is being requested, convert to an array
		$oneIdOnly = (!is_array ($masterIds));
		if ($oneIdOnly) {
			$masterIds = array ($masterIds);
		}
		
		# Get the data
		#!# IN() could potentially become slow
		$query = "SELECT
				id,parentId,archivedVersion,updatedAt
			FROM {$this->settings['table']}
			WHERE parentId IN(" . implode (',', $masterIds) . ")
			ORDER BY parentId,archivedVersion;";
		if (!$data = $this->databaseConnection->getData ($query, "{$this->settings['database']}.{$this->settings['table']}")) {
			return array ();
		}
		
		# Regroup by parentId and generate the links for convenience
		$archivedVersions = array ();
		foreach ($data as $id => $record) {
			$parentId = $record['parentId'];
			$archivedVersion = $record['archivedVersion'];
			$url = "{$this->baseUrl}/submissions/{$parentId}/version{$archivedVersion}/";
			switch ($format) {
				case 'link':
					$display = "[<a href=\"{$url}\">v{$archivedVersion}</a>]";
					break;
				case 'text':
					$display = "Version {$archivedVersion} - updated " . $this->formatDate ($record['updatedAt']);
					break;
				case 'version';
					$display = $archivedVersion;
					break;
			}
			$archivedVersions[$parentId][$id] = $display;
		}
		
		# If a single ID, return that key only
		if ($oneIdOnly) {
			$archivedVersions = $archivedVersions[$parentId];
		}
		
		# Return the data
		return $archivedVersions;
	}
	
	
	# Function to view a submission, effectively a wrapper to the submission renderer but which adds an actions UI, information box, etc.
	#!# Need to rename $data to $submission for clarity
	private function viewSubmission ($data, $suppressHeader = false, $changes = array ())
	{
		# Start the HTML
		$html = '';
		
		# Suppress the header if required
		if (!$suppressHeader) {
			
			# Start an actions list for a sidebar
			$actions = array ();
			
			# Create reopen/clone button if the status makes this available
			if ($data['status'] == 'submitted') {
				if ($this->userIsAdministrator || ($this->user == $data['currentReviewer'])) {
					$actions[] = "<a class=\"actions\" href=\"{$this->baseUrl}/submissions/{$data['id']}/review.html\"><img src=\"/images/icons/application_form_edit.png\" alt=\"\" class=\"icon\" /> Review this submission</a>";
				}
				if ($this->userCanReassign ($data)) {
					$actions[] = "<a class=\"actions\" href=\"{$this->baseUrl}/submissions/{$data['id']}/reassign.html\"><img src=\"/images/icons/page_go.png\" alt=\"\" class=\"icon\" /> Reassign reviewer</a>";
				}
				if ($this->getArchivedVersions ($data['id'])) {
					$actions[] = "<a class=\"actions\" href=\"{$this->baseUrl}/submissions/{$data['id']}/compare.html\"><img src=\"/images/icons/zoom.png\" alt=\"\" class=\"icon\" /> Compare versions</a>";
				}
				$actions[] = "<a class=\"actions\" href=\"{$this->baseUrl}/new/{$data['id']}/\"><img src=\"/images/icons/page_copy.png\" alt=\"\" class=\"icon\" /> Clone to new assessment</a>";
			}
			
			# Show actions, if any
			if ($actions) {
				$html .= "\n<div class=\"right\">";
				$html .= "\n<p>You can:</p>";
				$html .= application::htmlUl ($actions, 2, 'actions spaced');
				$html .= "\n</div>";
			}
		}
		
		# If an archived version, state this, and show the action
		if ($data['archivedVersion']) {
			$html .= "\n<div class=\"graybox\">";
			$html .= "\n<p><strong>This version of the <a href=\"{$this->baseUrl}/submissions/{$data['parentId']}/\">main submission</a> has been archived and cannot be changed.</strong></p>";
			$html .= "\n</div>";
			$html .= $this->actionRequestedBox ($data);
		}
		
		# If approved or rejected, state this
		if ($data['status'] == 'rejected' || $data['status'] == 'approved') {
			$html .= $this->actionRequestedBox ($data);
		}
		
		# Note if there are changes
		if ($changes) {
			$html .= "\n<p>Changes are marked <span class=\"changed\">highlighted like this</span> below.</p>";
			$html .= "\n<hr />";
		}
		
		# Start a list of fields which should not have entity conversion done
		$htmlNoEntities = array ();
		
		# Hyperlink the full name to Lookup
		$data['name'] = "<a href=\"https://www.lookup.cam.ac.uk/person/crsid/{$data['username']}/\" target=\"_blank\">" . htmlspecialchars ($data['name']) . '</a>';
		$htmlNoEntities[] = 'name';
		
		# Replace the responsible person's username with their name also
		$data['seniorPerson'] = $this->renderResponsiblePerson ($data['seniorPerson'], $htmlNoEntities /* modified by reference */);
		
		# Replace the college database value with the visible text
		$data['college'] = $this->renderCollege ($data['college']);
		
		# Render the submission into the template
		$html .= $this->renderSubmission ($data, $htmlNoEntities, $changes);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to render the responsible person's name/username
	private function renderResponsiblePerson ($seniorPerson, &$htmlNoEntities)
	{
		if ($userLookupData = camUniData::lookupUser ($seniorPerson)) {
			$seniorPerson = htmlspecialchars ($userLookupData['name']) . " &lt;<a href=\"https://www.lookup.cam.ac.uk/person/crsid/{$seniorPerson}/\" target=\"_blank\">{$seniorPerson}</a>&gt;";
			$htmlNoEntities[] = 'seniorPerson';
		}
		return $seniorPerson;
	}
	
	
	# Function to render the college name
	private function renderCollege ($collegeId)
	{
		$colleges = $this->getColleges ();
		$collegeName = ($collegeId ? $colleges[$collegeId] : false);
		return $collegeName;
	}
	
	
	# Function to render the submission into the template
	private function renderSubmission ($data, $htmlNoEntities = array (), $changes = array ())
	{
		# Get the template
		$template = $this->formTemplate ($data, $viewMode = true);
		
		# Insert the values into the template
		$replacements = array ();
		foreach ($data as $key => $value) {
			$placeholder = '{' . $key . '}';
			if (!in_array ($key, $htmlNoEntities)) {$value = htmlspecialchars ($value);}
			$replacements[$placeholder] = ($value ? '<span class="answer">' . nl2br ($value) . '</span>' : '<span class="comment">-</span>');
			if ($changes) {
				if (array_key_exists ($key, $changes)) {
					$tooltip = str_replace ("\n", '&#10;', "In the earlier version, this was:\n\n" . (strlen ($changes[$key]) ? htmlspecialchars ($changes[$key]) : '[-]'));	// Use &#10; as line break rather than natural linebreaks: http://stackoverflow.com/questions/6502054
					$replacements[$placeholder] = "<span class=\"changed\" title=\"{$tooltip}\">" . $replacements[$placeholder] . '</span>';
				}
			}
		}
		$submission = strtr ($template, $replacements);
		
		# Return the submission
		return $submission;
	}
	
	
	# Function to create a box showing the action requested
	private function actionRequestedBox ($data)
	{
		# Hyperlink texts
		#!# /submissions/212/ has "Undefined index: in reviewableAssessments.php on [this line]
		$action = strip_tags ($this->reviewOutcomes[$data['reviewOutcome']]['text']);
		$action = str_replace ('<strong>make changes</strong> to the form', '<a href="#mainform"><strong>make changes</strong> to the form</a>', $action);
		$action = str_replace ($this->settings['stage2Info'], "<a href=\"#stage2info\">{$this->settings['stage2Info']}</a>", $action);
		
		# Compile the HTML
		$html  = "\n<div class=\"graybox\">";
		$html .= "\n<p><strong>{$action}</strong></p>";
		if ($data['comments']) {
			$html .= "\n" . application::formatTextBlock (wordwrap (htmlspecialchars ($data['comments'])));
		}
		if ($data['status'] == 'approved') {
			$html .= "\n<p class=\"noprint\"><a href=\"{$this->baseUrl}/submissions/{$data['id']}/assessment{$data['id']}.pdf\"><strong>PDF (for printing/saving)</strong></a></p>";
		}
		$html .= "\n</div>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to get the user
	private function getUser ($userId, &$errorHtml = false)
	{
		# Get the data and return it
		$callbackFunction = $this->settings['userCallback'];
		$userData = $callbackFunction ($this->databaseConnection, $userId, $errorHtml /* returned by reference */);
		return $userData;
	}
	
	
	# Function to get the colleges
	public function getColleges ()
	{
		# Get the data and return it
		$callbackFunction = $this->settings['collegesCallback'];
		$colleges = $callbackFunction ($this->databaseConnection);
		$colleges = array_merge (array ('n/a' => 'Not applicable'), $colleges);
		return $colleges;
	}
	
	
	# Function to get a list of Directors of Studies
	private function getDosList ()
	{
		# Get the data and return it
		$callbackFunction = $this->settings['dosListCallback'];
		$dosList = $callbackFunction ($this->databaseConnection);
		return $dosList;
	}
	
	
	# Function to get the countries
	public function getCountries ()
	{
		# Get the data and return it
		$countries = $this->databaseConnection->selectPairs ($this->settings['database'], 'countries', array (), array ('id', 'value'), $associative = true, $orderBy = 'label');
		$countries = array_merge (array (0 => 'United Kingdom (UK)'), $countries);
		return $countries;
	}
	
	
	# Submission processor
	private function submissionProcessor ($data)
	{
		# Start the HTML
		$html = '';
		
		# Show the action requested if the form has been reopened
		$mostRecentArchivedVersion = false;
		if ($data['status'] == 'reopened') {
			$mostRecentArchivedVersion = $this->getMostRecentArchivedVersion ($data['id']);
			$html .= $this->actionRequestedBox ($mostRecentArchivedVersion);
		}
		
		# Load the submission form
		$form = $this->submissionForm ($data);
		
		# Ensure that changes have been made if there is a previous version
		if ($mostRecentArchivedVersion) {
			if ($unfinalisedData = $form->getUnfinalisedData ()) {
				if (!$changes = $this->diff ($mostRecentArchivedVersion, $unfinalisedData)) {
					$form->registerProblem ('nochange', 'You do not appear to have made any changes.');
				}
			}
		}
		
		# Process the form or end
		if (!$submission = $form->process ($html)) {
			return $html;
		}
		
		# Determine if the submission is only a form save
		#!# Form saving bypassing validation can be problematic if, e.g. "You submitted more characters (174) than are allowed (130)" occurs, and the database then tries to save this, resulting in truncation; form save should still error on invalid data submitted (e.g. too long) but not absent values
		$isFormSave = $form->formSave;
		
		# Process the form
		$html = $this->processSubmission ($html, $data['id'], $data['status'], $submission, $isFormSave);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to process the submission
	private function processSubmission ($html, $id, $status, $submission, $isFormSave)
	{
		# Determine if this is the initial full submission (i.e. more than just the stub) of the application
		$isInitialSubmission = ($status == 'started');
		
		#!# A form save results in 'Currently with' being set, although this does not happen with the initial creation
		
		# Set the submission status to submitted if this is an actual submission
		if (!$isFormSave) {
			$submission['status'] = 'submitted';
		}
		
		# When application first submitted, add in the currentReviewer field, by cloning the seniorPerson to be the currentReviewer
		if ($isInitialSubmission) {
			$submission['currentReviewer'] = $submission['seniorPerson'];
		}
		
		# Set the last updated time
		$submission['updatedAt'] = 'NOW()';		// Database library will convert to native function
		
		# Update the data; the id and username will already be present (and should never be re-supplied in the code, so that they stay constant)
		if (!$this->databaseConnection->update ($this->settings['database'], $this->settings['table'], $submission, array ('id' => $id))) {
			$html .= $this->reportError ("There was a problem updating this submission:\n\n" . print_r ($submission, true) . "\n\n" . print_r ($this->databaseConnection->error (), true), 'There was a problem updating this submission. The Webmaster has been informed and will investigate shortly.');
			return $html;
		}
		
		# Read back the new state of the data
		$submission = $this->getSubmission ($id);
		
		# E-mail the reviewer
		if (!$isFormSave) {
			$submissionUrl = "{$_SERVER['_SITE_URL']}{$this->baseUrl}/submissions/{$id}/review.html";
			$subject = ucfirst ($this->settings['description']) . " (#{$id}) ({$submission['name']}): review needed";
			$message  = "A {$this->settings['description']} submission (#{$id}) has been " . ($isInitialSubmission ? 'made' : 'updated') . " by {$submission['name']} <{$this->user}>.\n\nPlease kindly please review it via this link:\n\n{$submissionUrl}";
			// $message .= "\n\nThe date noted was: \"{$submission['data']}\"";
			$to = $submission['currentReviewer'] . '@' . $this->settings['emailDomain'];
			$headers = "From: " . $submission['email'];
			if ($submission['currentReviewer'] != $submission['seniorPerson']) {	// i.e. Copy in the DoS if a passup has previously happened
				$headers .= "\r\nCc: " . $submission['seniorPerson'] . "@{$this->settings['emailDomain']}";
			}
			application::utf8Mail ($to, $subject, wordwrap ($message), $headers);
		}
		
		# Set a flash message
		$flashValue = ($isFormSave ? 'saved (but not finalised)' : 'finalised and sent');
		#!# This doesn't actually show the result if the redirection fails
		$message = "\n" . '<p>' . $this->tick . ' <strong>The submission has been ' . htmlspecialchars ($flashValue) . ', as below:</strong></p>';
		$redirectTo = "{$this->baseUrl}/submissions/{$id}/";
		$html = application::setFlashMessage ('submission' . $id, $flashValue, $redirectTo, $message, $this->baseUrl . '/');	// id is used to ensure the flash only appears attached to the matching submission
		
		# Return the HTML
		return $html;
	}
	
	
	# Submission form, which the implementation is likely to need to override
	public function submissionForm ($data)
	{
		# Get the template
		$template = $this->formTemplate ($data);
		
		# Add form buttons to the template
		$template = "\n<p><img src=\"/images/icons/information.png\" alt=\"\" class=\"icon\" /> You can click on {[[SAVE]]} at any time.</p>" . "\n{[[PROBLEMS]]}" . $template . "\n<p>{[[SUBMIT]]} OR you can {[[SAVE]]}</p>";
		
		# Determine the widget to be used for the senior person field
		$seniorPerson = $this->seniorPersonAttributes ($data['type'], $data['username']);
		
		# Compile the dataBinding attributes
		$dataBindingAttributes = array (
			'description'					=> array ('size' => 70, 'maxlength' => $this->settings['descriptionMaxLength']),	#!# Reduce to 80
			'name'							=> array ('editable' => false, ),
			'email'							=> array ('editable' => false, ),
			'type'							=> array ('type' => 'radiobuttons', 'disabled' => true, ),
			'college'						=> array ('type' => 'select', 'values' => $this->getColleges (), ),
			'seniorPerson' 					=> $seniorPerson['widget'],
			'contactAddress'				=> array ('rows' => 4, 'cols' => 50, ),
		);
		
		# Define the database fields that should be treated as NOT NULL when doing a full submission (rather than "Save and continue"), even though the database sets them as NULLable; this is done manually so that the "Save and continue" button is possible
		$genericFields = array ('description', 'name', 'email', 'type', 'college', 'seniorPerson', 'confirmation', );
		
		# Determine the local fields
		$allFields = array_keys ($data);
		$localFields = array_diff ($allFields, $this->internalFields, $genericFields);
		
		# Determine fields that have an associated details field, e.g. foo & fooDetail
		$detailsFields = array ();
		foreach ($localFields as $field) {
			if (preg_match ('/^(.+)Details$/', $field, $matches)) {
				$mainField = $matches[1];
				$detailsFields[$mainField] = $field;
			}
		}
		
		# Set all local fields, except detail fields to be required by default
		foreach ($localFields as $field) {
			if (in_array ($field, $detailsFields)) {continue;}	// Skip detail fields, as these are handled by dataBinding intelligence
			$dataBindingAttributes[$field]['required'] = true;
		}
		
		# Databind a form
		$form = new form (array (
			'databaseConnection' => $this->databaseConnection,
			'nullText' => false,
			'formCompleteText'	=> false,
			'display' => 'template',
			'displayTemplate' => $template,
			'size' => 60,
			'cols' => 50,
			'rows' => 4,
			'unsavedDataProtection' => true,
			'saveButton' => true,
			'div' => false,
		));
		#!# This needs to disable native required="required" handling, as that prevents true "Save and continue later"
		$form->dataBinding (array (
			'database' => $this->settings['database'],
			'table' => $this->settings['table'],
			'data'	=> $data,
			'intelligence' => true,
			'exclude' => $this->internalFields,
			'attributes' => $dataBindingAttributes,
			'notNullFields' => $genericFields,
			'int1ToCheckbox' => true,
		));
		
		# Return the form handle
		return $form;
	}
	
	
	# Function to assign reviewer details
	public function seniorPersonAttributes ($personType, $submitterUsername, $viewMode = false)
	{
		switch ($personType) {
			
			# For undergraduates, force selection from a known set of DoS (Director of Studies) values coming from the external datasource, to reduce the likelihood of an incorrect e-mail being sent out (compared to direct e-mail entry)
			case 'Undergraduate':
				$reviewer['message'] = "<strong>Your form will be reviewed by your Director of Studies</strong>, who will discuss it with you. It will also be reviewed subsequently by the {$this->settings['directorDescription']}.";
				$reviewer['label']  = 'Your Director of Studies';
				$reviewer['placeholder'] = '{seniorPerson}<br /><span class="faded">(This list is ordered by surname)</span>';
				$reviewer['widget'] = array (
					'type'		=> 'select',
					'values'	=> $this->dosList,
				);
				break;
				
			# For MPhil, the supervisor may be course-specific, dependent on the application settings; if so, force the setting; otherwise fall through to the generic Postgraduate behaviour below
			case 'MPhil':
				
				# If the peopleResponsible setting is defined, look for the MPhil courses; otherwise fall-through
				#!# This is potentially rather brittle, e.g. a space present or extension for other groupings
				if ($this->settings['peopleResponsible']) {
					
					# Define the message and label
					$reviewer['message'] = "<strong>Your form will be reviewed by the academic representative for your MPhil</strong>, who will discuss it with you. It will also be reviewed subsequently by the {$this->settings['directorDescription']}.";
					$reviewer['label'] = 'E-mail' . ($viewMode ? '/name' : '') . " of the academic representative for your MPhil";
					
					# Obtain the academic representative for the course concerned
					if ($courseDirector = $this->getMphilRep ($submitterUsername, $courseMoniker /* Returned by reference */)) {
						$reviewer['placeholder'] = '{seniorPerson}' . "@{$this->settings['emailDomain']}";
						$reviewer['widget'] = array (
							'default'	=> $courseDirector,
							'editable'	=> false,
						);
					} else {
						$this->reportError ("An user {$this->user} on MPhil course {$courseMoniker} was unable to complete their {$this->settings['description']} because that course moniker is not defined in the settings at\n\n{$_SERVER['_SITE_URL']}{$this->baseUrl}/settings.html");
						$reviewer['placeholder'] = '{seniorPerson}' . "<p class=\"warning\">Due to a technical problem, the system is unable to determine the contact for your course; the Webmaster has been informed and will be in touch shortly.</p><p class=\"warning\">In the meanwhile, please continue with the rest of the form, and use the 'Save and continue' button.</p>";
						$reviewer['widget'] = array (
							'default'	=> false,
							'editable'	=> false,
							'entities'	=> false,
						);
					}
					
					break;
				}
				
			// Otherwise, fall-through to generic postgraduate handling, i.e. all MPhils enter their supervisor
			
			# For postgrads and research staff, give an autocomplete box to enable them to self-select
			// 'MPhil' also may be present from fall-through
			case 'PhD':
			case 'Postgraduate':
			case 'Research staff':
				$peopleResponsible = array (
					'MPhil'				=> 'your Supervisor',
					'PhD'				=> 'your Supervisor',
					'Postgraduate'		=> 'your Supervisor',
					'Research staff'	=> 'the Principal Investigator (PI)',
				);
				$personResponsible = $peopleResponsible[$personType];
				$reviewer['message'] = '<strong>Your form will be reviewed by ' . $personResponsible . "</strong>, who will discuss it with you. It will also be reviewed subsequently by the {$this->settings['directorDescription']}.";
				$reviewer['label']  = 'E-mail' . ($viewMode ? '/name' : '') . ' of ' . $personResponsible;
				$reviewer['placeholder'] = '{seniorPerson}' . (!$viewMode ? "@{$this->settings['emailDomain']}" . '<br /><span class="faded">(Start typing their surname to find this, and <strong>check carefully</strong>.)</span>' : '');
				$reviewer['widget'] = array (
					'size' => 10,
					'autocomplete' => $this->settings['usersAutocomplete'],
					'autocompleteOptions' => array ('delay' => 0),
					'regexp' => '^([a-z0-9]+)$',
				);
				break;
				
			# If the person responsible is the Director, set the seniorPerson field to be uneditable
			case 'Academic staff':
			case 'Other':
				$reviewer['message'] = "<strong>Your form will be reviewed by the {$this->settings['directorDescription']}</strong>, who will discuss it with you.";
				$reviewer['label'] = 'E-mail' . ($viewMode ? '/name' : '') . " of the {$this->settings['directorDescription']}";
				$reviewer['placeholder'] = '{seniorPerson}' . "@{$this->settings['emailDomain']}";
				$reviewer['widget'] = array (
					'default'	=> $this->settings['directorUsername'],
					'editable'	=> false,
				);
				break;
		}
		
		# Return the details
		return $reviewer;
	}
	
	
	# Function to obtain the academic representative for the MPhil course of a user
	private function getMphilRep ($submitterUsername, &$courseMoniker)
	{
		# Obtain the course directors' usernames, by splitting the CSV setting string into key/value pairs
		$courseRep = array ();
		$courses = preg_split ("/\s*\r?\n\t*\s*/", trim ($this->settings['peopleResponsible']));
		foreach ($courses as $courseString) {
			list ($courseMoniker, $username) = explode (',', $courseString, 2);
			$courseRep[$courseMoniker] = trim ($username);
		}
		
		# Determine the course of the user
		#!# This is rather hacky, but the problem is that userType() has no notion of a sub-type, so the course is not stored as persistent data
		$userData = $this->getUser ($submitterUsername);
		$courseMoniker = $userData['course__JOIN__people__courses__reserved'];
		
		# Ensure the course director is defined; if not, report the problem
		if (!isSet ($courseRep[$courseMoniker])) {
			return false;
		}
		
		# Look up the course director
		$courseDirector = $courseRep[$courseMoniker];
		
		# Return the course director's CRSID
		return $courseDirector;
	}
	
	
	# Function to implement the form template
	public function formTemplate ($data, $viewMode = false)
	{
		# Define the watermark
		$watermark = ($viewMode ? '<p class="watermark">#{id}, {name}, {description}</p>' : '');
		
		# Construct the submission ID string (which is not submitted in the form)
		$idInfo = ($data['parentId'] && $data['archivedVersion'] ? "{$data['parentId']} (version {$data['archivedVersion']})" : $data['id']);
		
		# Define the template, in ultimateForm template format
		$html = '
		
		<a id="mainform"></a>
		<h3>Description of this ' . $this->settings['description'] . '</h3>
		<p>Submission no.: <strong>' . $idInfo . '</strong>.</p>
		<p>{description}</p>
		
		<h3>Section A &#8211; basic questions</h3>
		
		<table class="graybox regulated">
			<tr>
				<td>Your name:</td>
				<td>{name}</td>
			</tr>
			<tr>
				<td>Your email:</td>
				<td>{email}</td>
			</tr>
			<tr>
				<td>Staff/student status:</td>
				<td>{type}<br /><span class="small comment"><em>(Please contact us if this is wrong.)</em></span></td>
			</tr>
			<tr>
				<td>College:</td>
				<td>{college}</td>
			</tr>
		</table>
		';
		
		# Determine the widget placeholder and associated labelling to be used for the senior person field
		$seniorPerson = $this->seniorPersonAttributes ($data['type'], $data['username'], $viewMode);
		$html .= "
		<h4>Person responsible</h4>
		<table class=\"graybox regulated\">
			<tr>
				<td colspan=\"2\"><p>{$seniorPerson['message']}</p></td>
			</tr>
			<tr>
				<td>{$seniorPerson['label']}:</td>
				<td>{$seniorPerson['placeholder']}</td>
			</tr>
		</table>
		";
		
		# Add local fields
		$html .= $this->formTemplateLocal ($data, $watermark);
		
		# Surround with a div for styling purposes
		$html = "\n<div id=\"assessmentform\" class=\"ultimateform\">\n" . $html . "\n</div>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Abstract function for implementation of the form template
	# Must return $html, an HTML block representing the template
	abstract public function formTemplateLocal ($data, $watermark);
	
	
	# Download page
	public function download ()
	{
		# Start the HTML
		$html  = '';
		
		# Compile the HTML
		$html .= "\n<br />";
		$html .= "\n<p><a class=\"actions\" href=\"{$this->baseUrl}/download.csv\"><strong><img src=\"/images/icons/page_excel.png\" alt=\"\" class=\"icon\" /> Download the data as a CSV file</strong></a></p>";
		
		# Give a warning about Excel's buggy handling of UTF-8 in CSV files
		$html .= "\n<br /><br /><p class=\"warning\"><strong>Warning:</strong> You are strongly recommended to open the CSV files above using <strong>OpenOffice</strong> rather than Excel, and select '<strong>Unicode (UTF-8)</strong>' as the character encoding when opening. (This is because Microsoft Excel tends to corrupt international characters in CSV files.) You can then save the file in the .xlsx format which will preserve the Unicode encoding. Then close the CSV file and open the new .xlsx file.</p>";
		
		# Show the HTML
		echo $html;
	}
	
	
	# CSV download
	public function downloadcsv ()
	{
		# Serve the CSV file
		$query = "SELECT * FROM {$this->settings['database']}.{$this->settings['table']} ORDER BY id;";
		$this->databaseConnection->serveCsv ($query, array (), $filenameBase = 'assessments');
	}
	
	
	# Example submissions
	public function examples ($id)
	{
		# Start the HTML
		$html = '';
		
		# Get the examples
		$ids = explode ("\n", str_replace ("\r\n", "\n", trim ($this->settings['exemplars'])));
		$examples = $this->getSubmissions (false, false, false, $ids);
		
		# Show the listing of examples if no ID supplied
		if (!$id) {
			$html .= "\n<h2>" . ucfirst ($this->settings['description']) . ' form exemplars</h2>';
			$html .= $this->examplesListing ($examples);
			echo $html;
			return false;
		}
		
		# Show title
		$html .= "\n<h2>" . ucfirst ($this->settings['description']) . ' form exemplar</h2>';
		
		# In examples mode, check the ID exists
		if (!isSet ($examples[$id])) {
			$html .= "\n<p>The ID you supplied is not valid. Please check the URL and try again.</p>";
			echo $html;
			return false;
		}
		$data = $examples[$id];
		
		# Hide private data fields
		foreach ($data as $field => $value) {
			if (in_array ($field, $this->privateDataFields)) {
				$data[$field] = '[...]';
			}
		}
		
		# If present, use title field instead of description, as descriptions tend to include personal data
		if (isSet ($data['title'])) {
			$data['description'] = $data['title'];
		}
		
		# Show the form
		$html .= $this->renderSubmission ($data);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Functino to create a listing of examples
	private function examplesListing ($examples)
	{
		# Group by type
		$examplesByType = application::regroup ($examples, 'type');
		
		# Compile the HTML
		$html  = "\n<p>The following are some examples of good-quality submissions:</p>";
		foreach ($this->settings['types'] as $type) {
			if (isSet ($examplesByType[$type])) {
				$html .= "\n<h3>" . htmlspecialchars ($type) . '</h3>';
				$list = array ();
				foreach ($examplesByType[$type] as $id => $submission) {
					$title = (isSet ($submission['title']) ? $submission['title'] : $submission['description']);
					$list[] = "<a href=\"{$this->baseUrl}/examples/{$id}/\">" . htmlspecialchars ($title) . '</a>';
				}
				$html .= application::htmlUl ($list);
			}
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to override the feedback form to add a message if required
	public function feedback ($id_ignored = NULL, $error_ignored = NULL, $echoHtml = true)
	{
		# Add message, if required
		if (trim ($this->settings['feedbackHtml'])) {
			echo "<div class=\"warningbox\">
				<br />
				{$this->settings['feedbackHtml']}
				<br />
			</div>";
		}
		
		# Run the feedback form
		parent::feedback ();
	}
	
	
	# Settings
	public function settings ($dataBindingSettingsOverrides = array ())
	{
		# Define overrides
		$dataBindingSettingsOverrides = array (
			'attributes' => array (
				'approvalCoverSheetHtml'		=> array ('height' => 300, 'heading' => array (3 => 'Approval cover sheet (optional)')),
				'directorSignatureImageFile'	=> array ('directory' => $this->dataDirectory, 'forcedFileName' => 'directorSignatureImageFile', 'allowedExtensions' => array ('png'), 'preview' => true, ),
				'logoImageFile'					=> array ('directory' => $this->dataDirectory, 'forcedFileName' => 'logoImageFile',              'allowedExtensions' => array ('png'), 'preview' => true, ),
			),
		);
		
		# Run the main settings system with the overriden attributes
		return parent::settings ($dataBindingSettingsOverrides);
	}
}

?>
