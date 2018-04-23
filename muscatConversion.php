<?php

# Class to manage Muscat data conversion
#!# Need to mint a DOI as per: https://github.com/blog/1840-improving-github-for-science
require_once ('frontControllerApplication.php');
class muscatConversion extends frontControllerApplication
{
	# Define the types, describing each representation of the data as it passes through each conversion stage
	private $types = array (
		'muscatview' => array (	// Sharded records
			'label'		=> 'Muscat editing view',
			'icon'		=> 'page_white',
			'title'		=> 'The data as it would be seen if editing in Muscat',
			'errorHtml'	=> "The 'muscatview' version of record <em>%s</em> could not be retrieved, which indicates a database error. Please contact the Webmaster.",
			'fields'	=> array ('recordId', 'field', 'value'),
			'idField'	=> 'recordId',
			'orderBy'	=> 'recordId, line',
			'class'		=> 'regulated',
			'public'	=> false,
		),
		'rawdata' => array (	// Sharded records
			'label'		=> 'Raw data',
			'icon'		=> 'page_white_text',
			'title'		=> 'The raw data as exported by Muscat',
			'errorHtml'	=> "There is no such record <em>%s</em>. Please try searching again.",
			'fields'	=> array ('recordId', 'field', 'value'),
			'idField'	=> 'recordId',
			'orderBy'	=> 'recordId, line',
			'class'		=> 'compressed',	// 'regulated'
			'public'	=> false,
		),
		'processed' => array (	// Sharded records
			'label'		=> 'Processed version',
			'icon'		=> 'page',
			'title'		=> 'The data as exported by Muscat',
			'errorHtml'	=> "The 'processed' version of record <em>%s</em> could not be retrieved, which indicates a database error. Please contact the Webmaster.",
			'fields'	=> array ('recordId', 'field', 'xPath', 'value'),
			'idField'	=> 'recordId',
			'orderBy'	=> 'recordId, line',
			'class'		=> 'compressed',
			'public'	=> false,
		),
		'xml' => array (
			'label'		=> 'Muscat as XML',
			'icon'		=> 'page_white_code',
			'title'		=> 'Representation of the Muscat data as XML, via the defined Schema',
			'errorHtml'	=> "The XML representation of the Muscat record <em>%s</em> could not be retrieved, which indicates a database error. Please contact the Webmaster.",
			'fields'	=> array ('id', 'xml'),
			'idField'	=> 'id',
			'orderBy'	=> 'id',
			'class'		=> false,
			'public'	=> false,
		),
		'marc' => array (
			'label'		=> 'MARC record',
			'icon'		=> 'page_white_code_red',
			'title'		=> "The publication's record as raw MARC21 data",
			'errorHtml'	=> "The MARC21 representation of the Muscat record <em>%s</em> could not be retrieved, which indicates a database error. Please contact the Webmaster.",
			'fields'	=> array ('id', 'mergeType', 'mergeVoyagerId', 'marc', 'bibcheckErrors', 'suppressReasons'),
			'idField'	=> 'id',
			'orderBy'	=> 'id',
			'class'		=> false,
			'public'	=> true,
		),
		'presented' => array (
			'label'		=> 'Presented',		// Gets overwritten in public UI
			'icon'		=> 'page_white_star',
			'title'		=> 'Listing of the publication as an easy-to-read record',
			'errorHtml'	=> "The presented version of the Muscat record <em>%s</em> could not be retrieved, which indicates a database error. Please contact the Webmaster.",
			'fields'	=> array ('id', 'mergeType', 'mergeVoyagerId', 'marc', 'bibcheckErrors', 'suppressReasons'),
			'idField'	=> 'id',
			'orderBy'	=> 'id',
			'class'		=> false,
			'public'	=> true,
		),
	);
	
	
	# Function to assign defaults additional to the general application defaults
	public function defaults ()
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$defaults = array (
			'applicationName' => 'Muscat conversion project',
			'administrators' => true,
			'hostname' => 'localhost',
			'database' => 'muscatconversion',	// Requires SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX
			'username' => NULL,
			'password' => NULL,
			'table' => false,	// Not used
			'debugMode' => false,
			'paginationRecordsPerPageDefault' => 50,
			'div' => strtolower (__CLASS__),
			'useFeedback' => false,
			'importLog' => '%applicationRoot/exports-tmp/importlog.txt',
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
				'tab' => ($this->userIsAdministrator ? '<img src="/images/icons/house.png" alt="Home" border="0" />' : 'Search the catalogue'),
				'icon' => ($this->userIsAdministrator ? NULL : 'magnifier'),
			),
			'reports' => array (
				'description' => false,
				'url' => 'reports/',
				'tab' => 'Reports',
				'icon' => 'asterisk_orange',
				'administrator' => true,
			),
			'reportdownload' => array (
				'description' => 'Export',
				'url' => 'reports/',
				'export' => true,
				'administrator' => true,
			),
			'tests' => array (
				'description' => false,
				'url' => 'tests/',
				'tab' => 'Tests',
				'icon' => 'bug',
				'administrator' => true,
			),
			'records' => array (
				'description' => 'Browse records',
				'url' => 'records/',
				'tab' => ($this->userIsAdministrator ? 'Records' : 'Browse records'),
				'icon' => 'application_double',
			),
			'record' => array (
				'description' => 'View a record',
				'url' => 'records/%id/',
				'usetab' => ($this->userIsAdministrator ? 'records' : 'home' /* i.e. search */),
			),
			'marcxml' => array (
				'description' => 'Export a record as MARCXML',
				'url' => 'records/%id/muscat%id.marcxml.xml',
				'export' => true,
			),
			'fields' => array (
				'description' => false,
				'url' => 'fields/',
				'tab' => 'Fields',
				'icon' => 'chart_organisation',
				'administrator' => true,
			),
			'search' => array (
				'description' => 'Search the catalogue',
				'url' => 'search/',
				'tab' => ($this->userIsAdministrator ? 'Search' : NULL),
				'icon' => 'magnifier',
				'usetab' => ($this->userIsAdministrator ? false : 'home'),
			),
			'postmigration' => array (
				'description' => 'Post-migration tasks',
				'url' => 'postmigration/',
				'tab' => 'Post-migration',
				'icon' => 'script',
				'administrator' => true,
			),
			'import' => array (
				'description' => 'Import',
				'url' => 'import/',
				'tab' => 'Import',
				'icon' => 'database_refresh',
				'administrator' => true,
			),
			'schema' => array (
				'description' => 'Schema',
				'subtab' => 'Schema',
				'icon' => 'tag',
				'parent' => 'admin',
				'allowDuringImport' => true,
				'administrator' => true,
			),
			'marcparser' => array (
				'description' => 'MARC21 parser definition',
				'subtab' => 'MARC21 parser definition',
				'url' => 'marcparser.html',
				'icon' => 'chart_line',
				'parent' => 'admin',
				'allowDuringImport' => true,
				'administrator' => true,
			),
			'transliterator' => array (
				'description' => 'Reverse-transliteration definition',
				'subtab' => 'Reverse-transliteration definition',
				'url' => 'transliterator.html',
				'icon' => 'arrow_refresh',
				'parent' => 'admin',
				'allowDuringImport' => true,
				'administrator' => true,
			),
			'merge' => array (
				'description' => 'Merge definition',
				'subtab' => 'Merge definition',
				'url' => 'merge.html',
				'icon' => 'arrow_merge',
				'parent' => 'admin',
				'allowDuringImport' => true,
				'administrator' => true,
			),
			'loc' => array (
				'description' => 'LoC names',
				'subtab' => 'LoC names',
				'url' => 'loc.html',
				'icon' => 'cd',
				'parent' => 'admin',
				'administrator' => true,
			),
			'othernames' => array (
				'description' => 'Other names data',
				'subtab' => 'Other names data',
				'url' => 'othernames.html',
				'icon' => 'cd',
				'parent' => 'admin',
				'administrator' => true,
			),
			'selection' => array (
				'description' => 'List for selected import',
				'subtab' => 'Selected import',
				'url' => 'selection.html',
				'icon' => 'database_refresh',
				'parent' => 'admin',
				'allowDuringImport' => true,
				'administrator' => true,
			),
			'export' => array (
				'description' => 'Export MARC21 output',
				'tab' => 'Export',
				'url' => 'export/',
				'icon' => 'database_go',
				'allowDuringImport' => true,
				'administrator' => true,
			),
			'data' => array (
				'description' => 'AJAX endpoint',
				'url' => 'data.json',
				'export' => true,
				'allowDuringImport' => true,
			),
		);
		
		# Return the actions
		return $actions;
	}
	
	
	# Database structure definition
	public function databaseStructure ()
	{
		return "
			CREATE TABLE IF NOT EXISTS `administrators` (
			  `username` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Username' PRIMARY KEY,
			  `active` enum('','Yes','No') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Yes' COMMENT 'Currently active?',
			  `privilege` enum('Administrator','Restricted administrator') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Administrator' COMMENT 'Administrator level'
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='System administrators';
			
			CREATE TABLE IF NOT EXISTS `marcparserdefinition` (
			  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Automatic key' PRIMARY KEY,
			  `definition` text COLLATE utf8_unicode_ci NOT NULL COMMENT 'Parser definition',
			  `savedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Automatic timestamp'
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='MARC parser definition';
			
			CREATE TABLE IF NOT EXISTS `reversetransliterationdefinition` (
			  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Automatic key' PRIMARY KEY,
			  `definition` text COLLATE utf8_unicode_ci NOT NULL COMMENT 'Parser definition',
			  `savedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Automatic timestamp'
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='MARC parser definition';
			
			CREATE TABLE `selectiondefinition` (
			  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Automatic key' PRIMARY KEY,
			  `tests` int(1) NOT NULL DEFAULT '1' COMMENT 'Include records used by the test system?',
			  `definition` mediumtext COLLATE utf8_unicode_ci NOT NULL COMMENT 'Parser definition',
			  `createdBy` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Created by user',
			  `savedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Automatic timestamp'
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='MARC parser definition';
		";
	}
	
	
	# Additional processing, pre-actions
	public function mainPreActions ()
	{
		# Set title for public access for general users
		if (!$this->userIsAdministrator) {
			$this->settings['applicationName'] = 'SPRI library catalogue';
		}
		
		# Ensure any code errors are not visible to general users
		if (!$this->userIsAdministrator) {
			ini_set ('display_errors', false);
		}
		
		# Enable feedback page for search users
		if (!$this->userIsAdministrator) {
			$this->settings['useFeedback'] = true;
		}
		
		# Force tests virtual page to the tests tab
		if ($this->action == 'reports') {
			if ($this->item == 'tests') {
				$this->tabForced = 'tests';
			}
		}
		
	}
	
	
	# Additional processing
	public function main ()
	{
		# Add other settings
		$this->exportsDirectory = $this->applicationRoot . '/exports/';
		$this->exportsProcessingTmp = $this->applicationRoot . '/exports-tmp/';
		
		# Determine the import lockfile location
		$this->lockfile = $this->exportsProcessingTmp . 'lockfile.txt';
		
		# Determine the errors logfile location, used for logging import errors
		$this->errorsFile = $_SERVER['DOCUMENT_ROOT'] . $this->baseUrl . '/errors.html';
		
		# Determine if the user for search purposes is internal, so that unsuppressed data can be shown
		$hostname = gethostbyaddr ($_SERVER['REMOTE_ADDR']);
		$this->searchUserIsInternal = ($this->userIsAdministrator || preg_match ('/cam\.ac\.uk$/', $hostname));
		
		# Show if an import is running, and prevent a second import running
		if ($this->userIsAdministrator) {	// Do not show the warning to public search users or issue e-mails
			if ($importHtml = $this->importInProgress (24, $blockUi = false)) {
				if (!isSet ($this->actions[$this->action]['export'])) {		// Show the warning unless using AJAX data
					$html  = $importHtml;
					if ($this->action == 'import') {
						$html .= $this->importLogHtml ('Import progress');
					}
					echo $html;
				}
				if ($this->action == 'import') {
					return false;
				}
			}
		}
		
		# Determine and show the export date
		$isExportType = (isSet ($this->actions[$this->action]['export']) && $this->actions[$this->action]['export']);
		if (!$isExportType) {
			$this->exportDateDescription = $this->getExportDate ();
			if ($this->userIsAdministrator) {
				echo "\n<p id=\"exportdate\">{$this->exportDateDescription}</p>";
			}
		}
		
		# Define unicode symbols
		$this->doubleDagger = chr(0xe2).chr(0x80).chr(0xa1);
		
		# Create a handle to the transliteration module
		require_once ('transliteration.php');
		$this->transliteration = new transliteration ($this);
		
		# Create a handle to the MARC conversion module
		require_once ('marcConversion.php');
		$this->marcConversion = new marcConversion ($this, $this->transliteration);
		
		# Create a handle to the reports module
		require_once ('reports.php');
		$this->reports = new reports ($this, $this->marcConversion);
		$this->reportsList = $this->reports->getReportsList ();
		$this->listingsList = $this->reports->getListingsList ();
		
		# Determine which reports are informational reports
		$this->reportStatuses = $this->getReportStatuses ();
		
		# Merge the listings array into the main reports list
		$this->reportsList += $this->listingsList;
		
		# Load the import system
		require_once ('import.php');
		$this->import = new import ($this, $this->marcConversion, $this->transliteration, $this->reports, $this->exportsProcessingTmp, $this->errorsFile);
		
	}
	
	
	# Function to get the export date
	private function getExportDate ()
	{
		return $this->databaseConnection->getTableComment ($this->settings['database'], 'catalogue_rawdata');
	}
	
	
	# Function to determine the status of each report
	private function getReportStatuses ()
	{
		# Start a list of informational reports
		$reportStatuses = array ();
		
		# Start a registry of listings-type reports that implement a count
		$this->countableListings = array ();
		
		# Loop through each report and each listing, detecting the status, and rewriting the name
		$this->reportsList  = $this->parseReportNames ($this->reportsList , $reportStatuses, $this->countableListings);
		$this->listingsList = $this->parseReportNames ($this->listingsList, $reportStatuses, $this->countableListings);
		
		# Return the status list
		return $reportStatuses;
	}
	
	
	# Helper function to strip any flag from report key names
	private function parseReportNames ($reportsRaw, &$reportStatuses, &$countableListings)
	{
		# Loop through each report, detecting whether each report is informational, and rewriting the name
		$reports = array ();	// Array of report key names without flag appended
		foreach ($reportsRaw as $key => $value) {
			if (preg_match ('/^(.+)_(info|postmigration|problem|problemok)(|_countable)$/', $key, $matches)) {
				$key = $matches[1];
				$reportStatuses[$key] = $matches[2];
				$reports[$key] = $value;	// Register under new name
				if ($matches[3]) {
					$countableListings[] = $key;
				}
			} else {
				$reportStatuses[$key] = NULL;	// Unknown status
			}
			$reports[$key] = $value;	// Recreated list, with any _info stripped
		}
		
		# Return the rewritten list
		return $reports;
	}
	
	
	# Home page
	public function home ()
	{
		# If a public user, mutate to show the search page instead
		if (!$this->userIsAdministrator) {
			$this->search ();
			return;
		}
		
		# Welcome
		$html  = "\n<h2>Welcome</h2>";
		$html .= $this->reportsJumplist ();
		$html .= "\n<p>This administrative system enables Library staff at SPRI to get an overview of problems with Muscat records so that they can be prepared for eventual export to Voyager.</p>";
		
		# Reports
		$html .= "\n<p class=\"right\">Or filter to: <a href=\"{$this->baseUrl}/postmigration/\">post-migration only</a></p>";
		$html .= "\n<h3>Reports available</h3>";
		$html .= $this->reportsTable ();
		
		# Statistics
		$html .= "\n<h3>Record summary</h3>";
		$html .= $this->statisticsTable ();
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to list the reports
	public function reports ($id = false)
	{
		# Start the HTML
		$html  = '';
		
		# If no specified report, create a listing of reports
		if (!$id) {
			
			# Compile the HTML
			$html .= "\n<h2>Reports</h2>";
			$html .= $this->reportsJumplist ();
			$html .= "\n<p>This page lists the various reports that check for data errors or provide an informational overview of aspects of the data.</p>";
			$html .= $this->reportsTable ();
			
			# Show the HTML and end
			echo $html;
			return true;
		}
		
		# Ensure the report ID is valid
		if (!isSet ($this->reportsList[$id])) {
			$html .= "\n<h2>Reports</h2>";
			$html .= $this->reportsJumplist ($id);
			$html .= "\n<p>There is no such report <em>" . htmlspecialchars ($id) . "</em>. Please check the URL and try again.</p>";
			echo $html;
			return false;
		}
		
		# Show the title
		$html .= "\n<h2>Report: " . htmlspecialchars (ucfirst ($this->reportsList[$id])) . '</h2>';
		$html .= $this->reportsJumplist ($id);
		
		# View the report
		$html .= $this->viewResults ($id);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to create a reports list
	private function reportsTable ($filterStatus = false)
	{
		# Get the list of reports
		$reports = $this->getReports ();
		
		# Filter if required
		if ($filterStatus) {
			foreach ($reports as $report => $description) {
				if ($this->reportStatuses[$report] != $filterStatus) {
					unset ($reports[$report]);
				}
			}
		}
		
		# Get the counts
		$counts = $this->getCounts ();
		
		# Get the total number of records
		$stats = $this->getStats ();
		$totalRecords = $stats['totalRecords'];
		
		# Mark problem reports with no errors as OK
		foreach ($this->reportStatuses as $key => $status) {
			if ($status == 'problem' || $status == 'problemok') {
				if (array_key_exists ($key, $counts)) {
					if ($counts[$key] == 0 || $status == 'problemok') {
						$this->reportStatuses[$key] = 'ok';
					}
				}
			}
		}
		
		# Get the post-migration descriptions
		$postmigrationDescriptions = $this->reports->postmigrationDescriptions ();
		
		# Convert to an HTML list
		$table = array ();
		foreach ($reports as $report => $description) {
			$key = $report . ($this->reportStatuses[$report] ? ' ' . $this->reportStatuses[$report] : '');	// Add CSS class if status known
			$link = $this->reportLink ($report);
			$table[$key]['Description'] = "<a href=\"{$link}\">" . ucfirst (htmlspecialchars ($description)) . '</a>';
			if ($filterStatus == 'postmigration') {
				$table[$key]['Description']  = '<h4>' . $table[$key]['Description'] . '</h4>';
				$table[$key]['Description'] .= '<p>' . (isSet ($postmigrationDescriptions[$report]) ? $postmigrationDescriptions[$report] : '<em class="comment">[No description yet]</em>') . '</p>';
			}
			$table[$key]['Problems?'] = (($this->isListing ($report) && !in_array ($report, $this->countableListings)) ? '<span class="faded right">n/a</span>' : ($counts[$report] ? '<span class="warning right">' . number_format ($counts[$report]) : '<span class="success right">' . 'None') . '</span>');
			$percentage = ($counts[$report] ? round (100 * ($counts[$report] / $totalRecords), 2) . '%' : '-');
			$table[$key]['%'] = ($this->isListing ($report) ? '<span class="faded right">n/a</span>' : '<span class="comment right">' . ($percentage === '0%' ? '0.01%' : $percentage) . '</span>');
		}
		
		# Compile the HTML
		$html  = application::htmlTable ($table, array (), 'reports lines', $keyAsFirstColumn = false, false, $allowHtml = true, false, false, $addRowKeyClasses = true);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to determine if the specified report is a listing type
	public function isListing ($report)
	{
		return (array_key_exists ($report, $this->listingsList));
	}
	
	
	# Function to get the counts
	private function getCounts ()
	{
		# Get the list of reports
		$reports = $this->getReports ();
		
		# Get the counts
		$query = "SELECT report, COUNT(*) AS total FROM reportresults GROUP BY report;";
		$data = $this->databaseConnection->getPairs ($query);
		
		# Ensure that each report type has a count
		$counts = array ();
		foreach ($reports as $id => $description) {
			$counts[$id] = (isSet ($data[$id]) ? $data[$id] : 0);
		}
		
		# Return the counts
		return $counts;
	}
	
	
	# Function to create a reports jumplist
	private function reportsJumplist ($current = false)
	{
		# Determine the front reports page link
		$frontpage = $this->reportLink ();
		
		# Get the counts
		$counts = $this->getCounts ();
		
		# Create the list
		$droplist = array ();
		$droplist[$frontpage] = '';
		foreach ($this->reportsList as $report => $description) {
			$link = $this->reportLink ($report);
			$description = (mb_strlen ($description) > 50 ? mb_substr ($description, 0, 50) . '...' : $description);	// Truncate
			$droplist[$link] = ucfirst ($description) . ($this->isListing ($report) ? '' : ' (' . number_format ($counts[$report]) . ')');
		}
		
		# Create a link to the selected item
		$selected = $this->reportLink ($current);
		
		# Compile the HTML and register a processor
		$html  = pureContent::htmlJumplist ($droplist, $selected, $this->baseUrl . '/', $name = 'reportsjumplist', $parentTabLevel = 0, $class = 'reportsjumplist', 'Switch to: ');
		pureContent::jumplistProcessor ($name);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to link a report
	private function reportLink ($report = false)
	{
		return $this->baseUrl . ($report != 'tests' ? '/reports/' : '/') . ($report ? htmlspecialchars ($report) . '/' : '');
	}
	
	
	# Function to get the list of reports
	public function getReports ()
	{
		# Ensure each report exists
		foreach ($this->reportsList as $report => $description) {
			$methodName = 'report_' . $report;
			if (!method_exists ($this->reports, $methodName)) {
				unset ($this->reportsList[$report]);
			}
		}
		
		# Return the list
		return $this->reportsList;
	}
	
	
	# Function to view results of a report
	private function viewResults ($id)
	{
		# Determine the description
		$description = 'This report shows ' . $this->reportsList[$id] . '.';
		
		# Start the HTML With the description
		$html  = "\n<div class=\"graybox\">";
		if (!$this->isListing ($id)) {
			$html .= "\n<p id=\"exportlink\" class=\"right\"><a href=\"{$this->baseUrl}/reports/{$id}/{$id}.csv\">Export as CSV</a></p>";
		}
		$html .= "\n<p><strong>" . htmlspecialchars ($description) . '</strong></p>';
		$html .= "\n</div>";
		
		# Show the records for this query (having regard to any page number supplied via the URL)
		if ($this->isListing ($id)) {
			$viewMethod = "report_{$id}_view";
			$html .= $this->reports->{$viewMethod} ();
		} else {
			$baseLink = '/reports/' . $id . '/';
			$html .= $this->recordListing ($id, false, array (), $baseLink, true);
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to list the records as an index of all records
	public function records ()
	{
		# Start the HTML
		$html = '';
		
		# Show the search form
		$id = $this->recordSearchForm ($html);
		
		# If a valid record has been found, redirect to it
		if ($id) {
			$url = $_SERVER['_SITE_URL'] . $this->recordLink ($id);
			application::sendHeader (301, $url, $html);
			echo $html;
			return true;
		}
		
		# Browsing mode
		$html .= "\n<p><br /><br /><strong>Or browse</strong> through the records:</p>";
		$resultBrowse = $this->recordBrowser ($html);
		
		# Show the HTML and end
		echo $html;
		return true;
	}
	
	
	# Function to export a record as MARCXML
	public function marcxml ($id)
	{
		# Run the record page in MARCXML mode
		return $this->record ($id, true);
	}
	
	
	# Function to show a record
	public function record ($id, $marcXmlMode = false)
	{
		# Start the HTML
		$html = '';
		
		# Enable jQuery, needed for previous/next keyboard navigation, and tabbing
		$html .= "\n\n\n" . '<script type="text/javascript" src="//code.jquery.com/jquery.min.js"></script>';
		
		# Add previous/next links
		$previousNextLinks = $this->previousNextLinks ($id);
		$html .= "\n<p>Record #<strong>{$id}</strong>:</p>";
		$html .= $previousNextLinks;
		
		# Get the data, in order, starting with the most basic version, ending if any fail
		$tabs = array ();
		$i = 0;
		foreach ($this->types as $type => $attributes) {
			if (!$this->types[$type]['public'] && !$this->userIsAdministrator) {continue;}	// Hide tab if not public but viewing publicly
			if (!$tabs[$type] = $this->recordFieldValueTable ($id, $type, $errorHtml)) {
				if ($i == 0) {	// First one is the master record; if it does not exist, assume this is actually a genuinely non-existent record
					$errorHtml = "There is no such record <em>{$id}</em>.";
				}
				$html .= "\n<p>{$errorHtml}</p>";
				application::sendHeader (404);
				echo $html;
				return false;
			}
			$i++;
		}
		
		# In export mode, export the data as now assembled, and end
		if ($marcXmlMode) {
			return $this->exportMarcXML ($id, $this->marcRecordDynamic['record']);
		}
		
		# In public view, rename the presented record tab label
		if (!$this->userIsAdministrator) {
			$this->types['presented']['label'] = 'Main publication details';
		}
		
		# Compile the labels, whose ordering is used for the tabbing
		$labels = array ();
		$typesReverseOrder = array_reverse ($this->types, true);
		$i = 1;
		foreach ($typesReverseOrder as $type => $attributes) {
			if (!$this->types[$type]['public'] && !$this->userIsAdministrator) {continue;}	// Hide tab if not public but viewing publicly
			$labels[$type] = "<span accesskey=\"" . $i++ . "\" title=\"{$attributes['title']}\"><img src=\"/images/icons/{$attributes['icon']}.png\" alt=\"\" border=\"0\" /> " . $attributes['label'] . '</span>';
		}
		
		# Load into tabs and render
		require_once ('jquery.php');
		$jQuery = new jQuery (false, false, false, $jQueryLoaded = true);
		$jQuery->tabs ($labels, $tabs);
		$html .= $jQuery->getHtml ();
		
		// $html .= application::dumpData ($record, false, true);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to export MARC as MARCXML
	private function exportMarcXML ($id, $marc)
	{
		# Save the record to a temp file
		$mrkFile = "/tmp/muscat{$id}.mrk";
		file_put_contents ($mrkFile, $marc);
		
		# Convert to .mrk
		require_once ('createMarcExport.php');
		$createMarcExport = new createMarcExport ($this, $applicationRoot = NULL, $recordProcessingOrder = NULL);
		$createMarcExport->reformatMarcToVoyagerStyle ($mrkFile);
		
		# Convert to MARCXML
		$marcEditPath = '/usr/local/bin/marcedit/cmarcedit.exe';
		$mrcFile = "/tmp/muscat{$id}.mrc";
		$marcXmlFile = "/tmp/muscat{$id}.marcxml.xml";
		$command = "mono {$marcEditPath} -s {$mrkFile} -d {$mrcFile} -make && mono {$marcEditPath} -s {$mrcFile} -d {$marcXmlFile} -marcxml";
		exec ($command, $output, $unixReturnValue);
		if ($unixReturnValue == 2) {
			echo "<p class=\"warning\">Execution of <tt>/usr/local/bin/marcedit/cmarcedit.exe</tt> failed with Permission denied - ensure the webserver user can read <tt>/usr/local/bin/marcedit/</tt>.</p>";
			break;
		}
		
		# Read the MARCXML file
		$marcXml = file_get_contents ($marcXmlFile);
		
		# Remove the mrk, mrc, and MARCXML files
		unlink ($mrkFile);
		unlink ($mrcFile);
		unlink ($marcXmlFile);
		
		# Reformat the XML to be easier to read
		$dom = new DOMDocument ();
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
		$dom->loadXML ($marcXml);
		$marcXml = $dom->saveXML ();
		
		# Send XML headers
		header ('Content-type: text/xml; charset=utf8');
		
		# Force download rather than view
		$filenameBase  = "muscat{$id}.marcxml";
		$filenameBase .= '._savedAt' . date ('Ymd-His');
		$filename = $filenameBase . '.xml';
		header ('Content-disposition: attachment; filename=' . $filename);
		
		# Transmit the XML
		echo $marcXml;
	}
	
	
	
	# Function to create previous/next record links
	private function previousNextLinks ($id)
	{
		# Start the HTML
		$html = '';
		
		# Ensure records are public in public access mode
		$constraint = '';
		if (!$this->searchUserIsInternal) {
			$constraint = " AND status = 'migrate'";
		}
		
		# Get the data
		$query = "SELECT
			(SELECT MAX(id) AS id FROM searchindex WHERE id < {$id} {$constraint}) AS previous,
			(SELECT MIN(id) AS id FROM searchindex WHERE id > {$id} {$constraint}) AS next
		;";
		$data = $this->databaseConnection->getOne ($query);
		
		# Create a list
		$list = array ();
		$list[] = ($data['previous'] ? '<a id="previous" href="' . "{$this->baseUrl}/records/{$data['previous']}/" . '"><img src="/images/icons/control_rewind_blue.png" alt="Previous record" border="0" /></a>' : '');
		$list[] = '#' . $id;
		$list[] = ($data['next'] ? '<a id="next" href="' . "{$this->baseUrl}/records/{$data['next']}/" . '"><img src="/images/icons/control_fastforward_blue.png" alt="Next record" border="0" /></a>' : '');
		
		# Compile the HTML
		$html = application::htmlUl ($list, 0, 'previousnextlinks');
		
		# Add keyboard navigation; see: http://stackoverflow.com/questions/12682157/
		$html .= '
			<script language="javascript" type="text/javascript">
				$(function() {
					var keymap = {};
					keymap[ 37 ] = "#previous";		// Left
					keymap[ 39 ] = "#next";			// Right
					
					$( document ).on( "keyup", function(event) {
						var href;
						var selector = keymap[ event.which ];
						// if the key pressed was in our map, check for the href
						if ( selector ) {
							window.location = $( selector ).attr( "href" );
						}
					});
				});
			</script>
		';
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to create a record field/value table
	private function recordFieldValueTable ($id, $type, &$errorHtml = false)
	{
		# Get the data or end
		$linkFields = ($type != 'xml');
		if (!$record = $this->getRecords ($id, $type, $convertEntities = true, $linkFields)) {
			$errorHtml = sprintf ($this->types[$type]['errorHtml'], $id);
			return false;
		}
		
		# Regenerate MARC data on the fly (for MARC and presented versions), so that changes in code can be immediately viewed
		if (in_array ($type, array ('marc', 'presented'))) {
			if (!isSet ($this->marcRecordDynamic)) {	// Cache for next type that uses it, to save running convertToMarc twice
				$data = $this->getRecords ($id, 'xml', false, false, $searchStable = (!$this->userIsAdministrator));
				$marcParserDefinition = $this->import->getMarcParserDefinition ();
				$mergeDefinition = $this->import->parseMergeDefinition ($this->import->getMergeDefinition ());
				$marcRecord = $this->marcConversion->convertToMarc ($marcParserDefinition, $data['xml'], $mergeDefinition, $record['mergeType'], $record['mergeVoyagerId'], $record['suppressReasons']);		// Overwrite with dynamic read, maintaining other fields (e.g. merge data)
				$this->marcRecordDynamic = array (
					'record'			=> $marcRecord,
					'marcErrorHtml'		=> $this->marcConversion->getErrorHtml (),
					'marcPreMerge'		=> $this->marcConversion->getMarcPreMerge (),
					'sourceRegistry'	=> $this->marcConversion->getSourceRegistry (),
				);
			}
		}
		
		# Render the result
		switch ($type) {
			
			# Presentation record
			case 'presented':
				$output = $this->presentedRecord ($this->marcRecordDynamic['record']);
				break;
				
			# Text records
			case 'marc':
				$output  = '';
				$marcXmlLink = "<a href=\"{$this->baseUrl}/records/{$id}/muscat{$id}.marcxml.xml\">MARCXML</a>";
				if ($this->userIsAdministrator) {
					$output  = "\n<p>The MARC output uses the <a target=\"_blank\" href=\"{$this->baseUrl}/marcparser.html\">parser definition</a> to do the mapping from the XML representation.</p>";
					if ($record['bibcheckErrors']) {
						$output .= "\n<pre>" . "\n<p class=\"warning\">Bibcheck " . (substr_count ($record['bibcheckErrors'], "\n") ? 'errors' : 'error') . ":</p>" . $record['bibcheckErrors'] . "\n</pre>";
					}
					if ($this->marcRecordDynamic['marcErrorHtml']) {
						$output .= $this->marcRecordDynamic['marcErrorHtml'];
					}
					$output .= "\n<div class=\"graybox marc\">";
					$output .= "\n<p id=\"exporttarget\">";
					$output .= "Target <a href=\"{$this->baseUrl}/export/\">export</a> group: <strong>" . $this->migrationStatus ($id) . "</strong> &nbsp;&nbsp;";
					$output .= $marcXmlLink;
					$output .= '</p>';
					if ($record['mergeType']) {
						$output .= "\n<p>Note: this record has <strong>merge data</strong> (managed according to the <a href=\"{$this->baseUrl}/merge.html\" target=\"_blank\">merge specification</a>), shown underneath.</p>";
					}
					if ($record['mergeType']) {
						$output .= "\n" . '<p class="colourkey">Color key: <span class="sourcem">Muscat</span> / <span class="sourcev">Voyager</span></p>';
					}
				} else {
					$output .= "\n<p id=\"exporttarget\">";
					$output .= $marcXmlLink;
					$output .= '</p>';
				}
				$output .= "\n<pre>" . $this->showSourceRegistry ($this->highlightSubfields (htmlspecialchars ($this->marcRecordDynamic['record'])), $this->marcRecordDynamic['sourceRegistry']) . "\n</pre>";
				if ($this->userIsAdministrator) {
					if ($record['mergeType']) {
						$output .= "\n<h3>Merge data</h3>";
						$mergeTypes = $this->marcConversion->getMergeTypes ();
						$output .= "\n<p>Merge type: {$record['mergeType']}" . (isSet ($mergeTypes[$record['mergeType']]) ? " ({$mergeTypes[$record['mergeType']]})" : '') . "\n<br />Voyager ID: #{$record['mergeVoyagerId']}.</p>";
						$output .= "\n<h4>Pre-merge record from Muscat:</h4>";
						$output .= "\n<pre>" . $this->highlightSubfields (htmlspecialchars ($this->marcRecordDynamic['marcPreMerge'])) . "\n</pre>";
						$output .= "\n<h4>Existing Voyager record:</h4>";
						$voyagerRecord = $this->marcConversion->getExistingVoyagerRecord ($record['mergeVoyagerId'], $voyagerRecordErrorText);	// Although it is wasteful to regenerate this, the alternative is messily passing back the record and error text as references through convertToMarc()
						$output .= "\n<pre>" . ($voyagerRecord ? $this->highlightSubfields (htmlspecialchars ($voyagerRecord)) : $voyagerRecordErrorText) . "\n</pre>";
					}
					$output .= "\n</div>";
				}
				break;
				
			case 'xml':
				# Uncomment this block to compute the XML on-the-fly for testing purposes
			/*
				$data = $this->getRecords ($id, 'processed');
				$schemaFlattenedXmlWithContainership = $this->import->getSchema (true);
				$record = array ();
				$record['xml'] = xml::dropSerialRecordIntoSchema ($schemaFlattenedXmlWithContainership, $data, $xPathMatches, $xPathMatchesWithIndex, $errorHtml, $debugString);
			*/
				$output = "\n<div class=\"graybox\">" . "\n<pre>" . htmlspecialchars ($record[$type]) . "\n</pre>\n</div>";
				break;
				
			# Tabular records
			default:
				$class = $this->types[$type]['class'];
				foreach ($record as $index => $row) {
					$showHtmlTags = $this->marcConversion->getHtmlTags ();
					foreach ($showHtmlTags as $htmlTag) {
						$record[$index]['value'] = str_replace ($htmlTag, '<span style="color: #903;"><tt>' . htmlspecialchars ($htmlTag) . '</tt></span>', $record[$index]['value']);	// Show HTML as visible HTML
					}
				}
				$output = application::htmlTable ($record, array (), 'lines record' . ($class ? " {$class}" : ''), $keyAsFirstColumn = false, $uppercaseHeadings = true, $allowHtml = true, false, $addCellClasses = true);
				break;
		}
		
		# Return the HTML/XML
		return $output;
	}
	
	
	# Function to format a presented record
	private function presentedRecord ($record)
	{
		# Start the HTML
		$html = '';
		
		# Parse the record into lines
		$record = $this->marcConversion->parseMarcRecord ($record, $parseSubfieldsToPairs = true);
		
		# Start a list of values
		$table = array ();
		
		# Title
		$table['Title'] = false;
		$table['Title, transliterated'] = false;
		if (isSet ($record['245'])) {
			$field245  = $record['245'][0]['subfields']['a'][0];
			$field245 .= (isSet ($record['245'][0]['subfields']['b']) ? $record['245'][0]['subfields']['b'][0] : '');
			$field245 .= (isSet ($record['245'][0]['subfields']['c']) ? ' ' . $record['245'][0]['subfields']['c'][0] : '');
			$table['Title'] = $field245;
			
			# Prefer Cyrillic if present
			if (isSet ($record['245'][0]['subfields'][6])) {
				$linkNumber = str_replace ('880-', '', $record['245'][0]['subfields'][6][0]);	// e.g. 02
				$linkIndex = ((int) $linkNumber) - 1;		// e.g. 1
				$field880  = $record['880'][$linkIndex]['subfields']['a'][0];
				$field880 .= (isSet ($record['880'][$linkIndex]['subfields']['b']) ? $record['880'][$linkIndex]['subfields']['b'][0] : '');
				$field880 .= (isSet ($record['880'][0]['subfields']['c']) ? ' ' . $record['880'][0]['subfields']['c'][0] : '');
				$table['Title, transliterated'] = $table['Title'];
				$table['Title'] = preg_replace ('| /$|', '', $field880);	// Overwrite
			}
			
			# Normalise colon layout
			$table['Title'] = $this->normaliseColonLayout ($table['Title']);
			$table['Title, transliterated'] = $this->normaliseColonLayout ($table['Title, transliterated']);
		}
		
		# Translated title
		$table['Translated title'] = false;
		if (isSet ($record['242'])) {
			$field242 = $record['242'][0]['subfields']['a'][0] . (isSet ($record['242'][0]['subfields']['b']) ? $record['242'][0]['subfields']['b'][0] : '');
			$table['Translated title'] = preg_replace ('| /$|', '', $field242);
			
			# Normalise colon layout
			$table['Translated title'] = $this->normaliseColonLayout ($table['Translated title']);
		}
		
		# Author
		$table['Author(s)'] = false;
		$authors = array ();
		if (isSet ($record['100'])) {
			$authors[] = $record['100'][0]['subfields']['a'][0];
		}
		if (isSet ($record['700'])) {
			foreach ($record['700'] as $field700) {
				$authors[] = $field700['subfields']['a'][0];
			}
		}
		if ($authors) {
			$table['Author(s)'] = implode ('<br />', $authors);
		}
		
		# Corporate author
		if (isSet ($record['110'])) {
			$table['Author (corporate)'] = $record['110'][0]['subfields']['a'][0];
		}
		
		# Date
		$table['Date'] = false;
		if (isSet ($record['260']) && isSet ($record['260'][0]['subfields']['c'])) {
			$table['Date'] = $record['260'][0]['subfields']['c'][0];
		}
		
		# Publisher
		$table['Publisher'] = false;
		if (isSet ($record['260'])) {
			$publisher = array ();
			if (isSet ($record['260'][0]['subfields']['a'])) {
				if (!substr_count (mb_strtolower ($record['260'][0]['subfields']['a'][0]), '[s.l.]')) {
					$publisher[] = $record['260'][0]['subfields']['a'][0];
				}
			}
			if (isSet ($record['260'][0]['subfields']['b'])) {
				if (!substr_count (mb_strtolower ($record['260'][0]['subfields']['b'][0]), '[s.n.]')) {
					$publisher[] = $record['260'][0]['subfields']['b'][0];
				}
			}
			if ($publisher) {
				$table['Publisher'] = trim (str_replace (' : ', ': ', implode (' ', $publisher)), ',');
			}
		}
		
		# Language
		$table['Language'] = false;
		if (isSet ($record['546'])) {
			$table['Language'] = preg_replace ('/^In /', '', $record['546'][0]['subfields']['a'][0]);
		}
		
		# In journal
		$table['In'] = false;
		if (isSet ($record['773'])) {
			$title = $record['773'][0]['subfields']['t'][0];
			$year = $record['260'][0]['subfields']['c'][0];
			$pagination = (isSet ($record['773'][0]['subfields']['g']) ? $record['773'][0]['subfields']['g'][0] : '');
			$recordId = $record['773'][0]['subfields']['w'][0];
			$table['In'] = "<a href=\"{$this->baseUrl}/records/" . str_replace ('SPRI-', '', $recordId) . '/">' . $title . '</a> (' . $year . '),' . ($pagination ? ' ' . $pagination : '');
		}
		
		# Abstract
		$table['Abstract'] = false;
		if (isSet ($record['520'])) {
			$table['Abstract'] = $record['520'][0]['subfields']['a'][0];
		}
		
		# Notes
		$table['Notes'] = false;
		$notes = array ();
		if (isSet ($record['500'])) {
			foreach ($record['500'] as $line) {
				$notes[] = $line['subfields']['a'][0];
			}
		}
		if ($notes) {
			$table['Notes'] = '<p>' . implode ('</p><p>', $notes) . '</p>';
		}
		
		# Local notes
		$table['Local notes'] = false;
		$localNotes = array ();
		if ($this->searchUserIsInternal) {
			if (isSet ($record['876'])) {
				foreach ($record['876'] as $line) {
					if (isSet ($line['subfields']['z'])) {
						$localNotes[] = $line['subfields']['z'][0];
					}
				}
			}
		}
		if ($localNotes) {
			$table['Local notes'] = '<p>' . implode ('</p><p>', $localNotes) . '</p>';
		}
		
		# Keywords
		$table['Keywords'] = false;
		$keywords = array ();
		if (isSet ($record['650'])) {
			foreach ($record['650'] as $line) {
				$keywords[] = $line['subfields']['a'][0];
			}
		}
		if (isSet ($record['651'])) {
			foreach ($record['651'] as $line) {
				$keywords[] = $line['subfields']['a'][0];
			}
		}
		if ($keywords) {
			$table['Keywords'] = implode ('<br />', $keywords);
		}
		
		# Location
		$table['Location'] = false;
		if (isSet ($record['852'])) {
			$locations = array ();
			$supportedSubfields = array ('b', 'c', 'h');
			foreach ($supportedSubfields as $supportedSubfield) {
				if (isSet ($record['852'][0]['subfields'][$supportedSubfield])) {
					$locations[] = $record['852'][0]['subfields'][$supportedSubfield][0];
				}
			}
			if ($locations) {
				$table['Location'] = implode ('<br />', $locations);
			}
		}
		
		# ISBN
		$table['ISBN'] = false;
		if (isSet ($record['020'])) {
			if (isSet ($record['020'][0]['subfields']['a'])) {
				$table['ISBN'] = $record['020'][0]['subfields']['a'][0];
			}
		}
		
		# Record number
		$table['SPRI record no.'] = str_replace ('SPRI-', '', $record['001'][0]['line']);
		
		# Render the HTML
		if ($table['Title']) {
			$html .= "\n<h3>" . htmlspecialchars ($table['Title']) . '</h3>';
		}
		#!# Need to support allowHtml properly - currently allows through entities
		$html .= application::htmlTableKeyed ($table, array (), $omitEmpty = true, 'lines presented', $allowHtml = array ('In journal', 'Keywords', 'Notes'));
		
		# Debug info
		//$html .= application::dumpData ($record, false, true);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to normalise colon layout in public record view
	private function normaliseColonLayout ($string)
	{
		$string = str_replace (' :', ': ', $string);
		$string = str_replace ('  ', ' ', $string);
		return $string;
	}
	
	
	# Function to obtain the migration status for a MARC record
	private function migrationStatus ($id)
	{
		# Obtain the status
		$status = $this->databaseConnection->selectOneField ($this->settings['database'], 'catalogue_marc', 'status', $conditions = array ('id' => $id));
		
		# Assign label
		$filesets = $this->import->getFilesets ();
		$label = $filesets[$status];
		
		# Return the label for the status
		return $label;
	}
	
	
	# Function to provide subfield highlighting
	public function highlightSubfields ($string)
	{
		return preg_replace ("/({$this->doubleDagger}[a-z0-9])/", '<strong>\1</strong>', $string);
	}
	
	
	# Function to provide prepending of source registry indicators
	private function showSourceRegistry ($record, $sourceRegistry)
	{
		# Return unmodified if no source registry
		if (!$sourceRegistry) {return $record;}
		
		# Define titles
		$titles = array ('M' => 'Muscat', 'V' => 'Voyager');
		
		# Explode the string and add each line
		$lines = explode ("\n", $record);
		$decoratedRecord = array ();
		foreach ($lines as $index => $line) {
			$cssClass = 'source' . strtolower ($sourceRegistry[$index]);	// i.e. 'sourcem' / 'sourcev'
			$title = $titles[$sourceRegistry[$index]];
			$decoratedRecord[$index] = "<span class=\"{$cssClass}\" title=\"{$title}\">" . $line . '</span>';
		}
		
		# Return to a string
		$decoratedRecord = implode ("\n", $decoratedRecord);
		
		# Return the decorated record
		return $decoratedRecord;
	}
	
	
	# Function to display the schema
	public function schema ()
	{
		# Read the schema
		$xml = $this->import->getSchema (false, true);
		
		# Convert to HTML
		$html = "\n<pre>" . htmlspecialchars ($xml) . '</pre>';
		
		# Surround with a presentational box
		$html = "\n<div class=\"graybox\">{$html}</div>";
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to link a record
	private function recordLink ($id)
	{
		return $this->baseUrl . '/records/' . htmlspecialchars ($id) . '/';
	}
	
	
	# Function to provide a record search
	private function recordSearchForm (&$html)
	{
		# Introductory text and form
		$html .= "\n<p>You can use this form to go to a specific record, by entering a record number:</p>";
		$resultRecord = $this->recordForm ($html);
		
		# End if no result
		if (!$resultRecord) {return false;}
		
		# Get the ID
		$id = $resultRecord['q'];
		
		# State if not found
		if (!$this->getRecords ($id, 'rawdata', $convertEntities = true)) {
			$html .= "\n<p class=\"warning\">There is no such record <em>" . htmlspecialchars ($id) . '</em>. Please try searching again.</p>';
			return false;
		}
		
		# Return the ID
		return $id;
	}
	
	
	# Function to create a record search form
	private function recordForm (&$html, $miniform = false)
	{
		# Cache _GET and remove the action, to avoid ultimateForm thinking the form has been submitted
		#!#C This is a bit hacky, but is necessary because we set name=false in the ultimateForm constructor
		$get = $_GET;	// Cache
		#!#C This general scenario is best dealt with in future by adding a 'getIgnore' parameter to the ultimateForm constructor
		if (isSet ($_GET['action'])) {unset ($_GET['action']);}
		if (isSet ($_GET['item'])) {unset ($_GET['item']);}
		if (isSet ($_GET['thousand'])) {unset ($_GET['thousand']);}
		
		# Run the form module
		$form = new form (array (
			'displayRestrictions' => false,
			'get' => true,
			'name' => false,
			'nullText' => false,
			'div' => 'ultimateform ' . ($miniform ? 'miniform recordsearch' : 'largesearch'),
			'submitTo' => $this->baseUrl . '/records/',
			'display'		=> 'template',
			'displayTemplate' => ($miniform ? '<!--{[[PROBLEMS]]}-->' : '{[[PROBLEMS]]}') /* Slightly hacky way of ensuring the problems list doesn't appear twice on the page */ . '<p>{q} {[[SUBMIT]]}</p>',
			'submitButtonText' => 'Go!',
			'submitButtonAccesskey' => false,
			'formCompleteText' => false,
			'requiredFieldIndicator' => false,
			'reappear' => true,
		));
		$form->search (array (
			'name'		=> 'q',
			'size'		=> ($miniform ? 9 : 15),
			'maxlength'	=> 6,
			'title'		=> 'Search',
			'required'	=> (!$miniform),
			'placeholder' => ($miniform ? 'Record #' : 'Record number'),
			'autofocus'	=> (!$miniform),
			'regexp' => '^([0-9]+)$',
		));
		
		# Process the form
		$result = $form->process ($html);
		
		# Reinstate GET
		$_GET = $get;
		unset ($get);
		
		# Return the result
		return $result;
	}
	
	
	# Function to create a record browser, which exists mainly for the purpose of getting all the records into search engine indexes
	private function recordBrowser (&$html)
	{
		# Determine the number of records and therefore the number of listings by thousand records
		$data = $this->getStats ();
		$highestNumberedRecord = $data['highestNumberedRecord'];
		$thousands = floor ($highestNumberedRecord / 1000);
		
		# Check if a valid 'thousands' value has been supplied in the URL
		if (isSet ($_GET['thousand'])) {
			
			# If the 'thousands' value is invalid, throw a 404
			if (!ctype_digit ($_GET['thousand']) || ($_GET['thousand'] > $thousands)) {
				application::sendHeader (404);
				$html .= "\n<p>The record set you specified is invalid. Please check the URL or <a href=\"{$this->baseUrl}/records/\">pick from the index</a>.</p>";
				return false;
			}
			
			# List the values within this thousand
			$thousand = $_GET['thousand'];	// Validated as ctype_digit above
			$constraints = array ('FLOOR(id/1000) = :thousand');
			$preparedStatementValues = array ('thousand' => $thousand);
			if (!$this->searchUserIsInternal) {
				$constraints['_status'] = "status = 'migrate'";
			}
			$query = "SELECT id FROM searchindex WHERE (" . implode (")\nAND (", $constraints) . ');';
			$ids = $this->databaseConnection->getPairs ($query, false, $preparedStatementValues);
			$html .= "\n<p>Records for {$thousand},xxx [or <a href=\"{$this->baseUrl}/records/\">reset</a>]:</p>";
			$html .= $this->listNumbers ($ids);
			return true;
		}
		
		# Create the list
		$list = array ();
		for ($i = 1; $i <= $thousands; $i++) {
			$list[] = $i;
		}
		
		# Render the HTML
		$html .= $this->listNumbers ($list, 'k', ',xxx');
		
		# Return success
		return true;
	}
	
	
	# Function to link through to an ID listing of record numbers
	private function listNumbers ($numbers, $linkSuffix = false, $visibleSuffix = false)
	{
		# Create a link for each thousand, with the first record being 1,000 (i.e. 1)
		$list = array ();
		foreach ($numbers as $i) {
			$list[] = "<li><a href=\"{$this->baseUrl}/records/{$i}{$linkSuffix}/\">{$i}{$visibleSuffix}</a></li>";
		}
		
		# Compile the HTML
		$html  = "\n<div class=\"graybox\">";
		$html .= application::splitListItems ($list, 4);
		$html .= "\n</div>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Overriding function
	public function showTabs ($current, $class = 'tabs')
	{
		# Show the record number form
		$recordFormHtml = '';
		$this->recordForm ($recordFormHtml, true);
		$html = $recordFormHtml;
		
		# Run the standard tabs function
		$html .= parent::showTabs ($current, $class);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to get records (or a single record)
	public function getRecords ($ids /* or single ID */, $type, $convertEntities = false, $linkFields = false, $searchStable = false)
	{
		# Special-case for presented, which is just a View for marc
		if ($type == 'presented') {
			$type = 'marc';
		}
		
		# Determine if this is a sharded table (i.e. a record is spread across multiple entries)
		$isSharded = ($this->types[$type]['idField'] == 'recordId');
		
		# If only a single record is requested, make into an array of one for consistency with multiple-record processing
		$singleRecordId = (is_array ($ids) ? false : $ids);
		if ($singleRecordId) {$ids = array ($ids);}
		
		# For the record screen, ensure records are public in public access mode
		if (!$this->searchUserIsInternal) {
			if ($singleRecordId) {
				$constraints = array (
					'id' => $singleRecordId,
					'status' => 'migrate',
				);
				if (!$this->databaseConnection->selectOneField ($this->settings['database'], 'searchindex', 'status', $constraints)) {
					return false;
				}
			}
		}
		
		# Determine fields to retrieve; for sharded records, also retrieve the line number so that each record can be indexed by line, the line value for which is then discarded below
		$fields = $this->types[$type]['fields'];
		if ($isSharded) {
			$fields[] = 'line';
		}
		
		# Determine the table to read from
		$table = "catalogue_{$type}";
		if ($searchStable) {
			$table .= '_searchstable';
		}
		
		# Get the raw data, or end
		if (!$records = $this->databaseConnection->select ($this->settings['database'], $table, $conditions = array ($this->types[$type]['idField'] => $ids), $fields, false, $this->types[$type]['orderBy'])) {return false;}
		
		# Regroup by the record ID
		$records = application::regroup ($records, $this->types[$type]['idField'], true, $regroupedColumnKnownUnique = (!$isSharded));
		
		# If the table is a sharded table, process the shards
		if ($isSharded) {
			
			# Get the descriptions
			$descriptions = false;
			
			# For sharded records, reindex each record by line
			foreach ($records as $recordId => $record) {
				$records[$recordId] = application::reindex ($record, 'line');
			}
			
			# Process each record
			foreach ($records as $recordId => $record) {
				foreach ($record as $index => $row) {
					
					# If showing descriptions, add the description field, and shift the record's value to the end
					if ($descriptions) {
						$records[$recordId][$index]['description'] = $descriptions[$row['field']];
						$value = $row['value'];
						unset ($records[$recordId][$index]['value']);
						$records[$recordId][$index]['value'] = $value;
					}
					
					# Handle entity conversions if required
					if ($convertEntities) {
						
						# Convert entities e.g. & becomes &amp; - only those changes afterwards (below) will be allowed through HTML
						$records[$recordId][$index]['value'] = htmlspecialchars ($records[$recordId][$index]['value']);
						
						# Allow italics, subscripts and superscripts in records, by converting back entity versions to proper HTML
						// $italicsPermittedInFields = array ('local', 't', 'tc');	// Find using SELECT DISTINCT (field) FROM catalogue_processed WHERE `value` LIKE '%<em>%';
						// if (in_array ($row['field'], $italicsPermittedInFields)) {
						$records[$recordId][$index]['value'] = str_replace (array ('&lt;em&gt;', '&lt;/em&gt;', '&lt;sub&gt;', '&lt;/sub&gt;', '&lt;sup&gt;', '&lt;/sup&gt;'), $this->marcConversion->getHtmlTags (), $records[$recordId][$index]['value']);
						// }
					}
					
					# Convert the field name to a link if required
					if ($linkFields) {
						$link = $this->fieldLink ($row['field'], $convertEntities);
						$records[$recordId][$index]['field'] = "<a href=\"{$link}\">{$row['field']}</a>";
					}
					
					# Hyperlink values if required
					if ($linkFields) {
						if ($row['field'] == 'kg') {
							if (strlen ($row['value']) && ctype_digit ($row['value'])) {
								$records[$recordId][$index]['value'] = "<a href=\"{$this->baseUrl}/records/{$row['value']}/\">{$row['value']}</a>";
							}
						}
					}
					
					//application::dumpData ($record);
				}
			}
		}
		
		# If a single record, just return that item
		$recordOrRecords = ($singleRecordId ? $records[$singleRecordId] : $records);
		
		# Return the record (or false)
		return $recordOrRecords;
	}
	
	
	# Fields traversal
	public function fields ($field = false)
	{
		# Start the HTML with the title
		$html = '';
		
		# Get the distinct fields
		$fields = $this->getDistinctFields ();
		
		# If not on a specific field, show the listing as a sortable table
		if (!$field) {
			
			# Compile the HTML
			$html .= "\n<h2>Fields</h2>";
			$html .= "\n<p>The table below shows all the fields in use across all records.</p>";
			$html .= "\n<p>If you find any unrecognised fields, go into the listing for that field to find the records containing.</p>";
			$html .= "\n" . '<!-- Enable table sortability: --><script language="javascript" type="text/javascript" src="/sitetech/sorttable.js"></script>';
			$html .= application::htmlTable ($fields, array (), $class = 'fieldslisting lines compressed sortable" id="sortable', $keyAsFirstColumn = false, false, $allowHtml = true, false, $addCellClasses = true);
			
			# Show the HTML and end
			echo $html;
			return true;
		}
		
		# If a field is specified in the URL, validate it and list its contents
		if (!isSet ($fields[$field])) {
			$html .= "\n<h2>Fields</h2>";
			$html .= "\n<p>There is no such field <em>" . htmlspecialchars ($field) . "</em>.</p>";
			$html .= "\n<p>Please select from the <a href=\"{$this->baseUrl}/fields/\">listing</a> and try again.</p>";
			echo $html;
			return true;
		}
		
		# Determine whether to show a list of field totals or list of distinct values
		$showDistinctValues = (isSet ($_GET['values']) && $_GET['values'] == '1');
		
		# Determine whether to show a list of records for a distinct value
		$showDistinctRecords = ($showDistinctValues && isSet ($_GET['value']) && strlen ($_GET['value']));
		
		# Show subtabs
		$link = $this->fieldLink ($field);
		$links = array (
			'/'					=> $this->fieldsDroplist ($fields, $showDistinctValues, $field),
			"{$link}"			=> "<a href=\"{$link}\"><strong>Records</strong>: <em>{$field}</em></a>",
			"{$link}values/"	=> "<a href=\"{$link}values/\"><strong>Distinct values</strong>: <em>{$field}</em></a>",
		);
		$selected = $link . ($showDistinctValues ? 'values/' : '');;
		$html .= application::htmlUl ($links, 0, 'fieldstabs tabs subtabs right', true, false, false, false, $selected);
		
		# Show list of records for a distinct value if required
		if ($showDistinctRecords) {
			
			# Overwrite the value with a correct version that maintains URL encoding properly, specifically of + signs, by emulating it from the original REQUEST_URI
			# The problem arises because mod_rewrite decodes at the point of shifting the path fragment (e.g. "/attributes/harddisk/a+%2B+b/" results in "value=a   b"
			# See: http://stackoverflow.com/a/10999987/180733 , in particular: "In fact it is impossible to do using a rewrite rule alone. Apache decodes the URL before putting it through rewrite, but it doesn't understand plus signs"
			preg_match ('|^' . $this->baseUrl . "/fields/{$field}/values/(.+)/|", $_SERVER['REQUEST_URI'], $matches);	// e.g. /library/catalogue/fields/n1/values/a+b/
			$_GET['value'] = urldecode ($matches[1]);	// Overwrite, with the URLdecoding doing what Apache would natively do
			$value = $_GET['value'];
			
			# Define a query
			$query = "SELECT DISTINCT recordId FROM catalogue_processed WHERE field = :field AND value = BINARY :value;";
			$preparedStatementValues = array ('field' => $field, 'value' => $value);
			
			# Create a listing
			$html .= "\n<h2>Records for field with value <em>" . htmlspecialchars ($value) . "</em> in field <em>*{$field}</em></h2>";
			$html .= "\n<p>Below are records with the value <em>" . htmlspecialchars ($value) . "</em> in field <em>*{$field}</em>:</p>";
			$valueLinkEncoded = htmlspecialchars (urlencode ($value));
			$html .= $this->recordListing (false, $query, $preparedStatementValues, "/fields/{$field}/values/{$valueLinkEncoded}/");
			
		# Show distinct values if required
		} else if ($showDistinctValues) {
			
			# Get the total number of results as a known total to seed the paginator, overriding automatic counting (which will fail for a GROUP BY query)
			$countingQuery = "SELECT COUNT( DISTINCT(BINARY value) ) AS total FROM catalogue_processed WHERE field = :field;";
			$preparedStatementValues = array ('field' => $field);
			$knownTotalAvailable = $this->databaseConnection->getOneField ($countingQuery, 'total', $preparedStatementValues);
			
			# Dynamically select unique values
			$query = "SELECT
					value AS title,
					COUNT(*) AS instances
				FROM `catalogue_processed`
				WHERE field = :field
				GROUP BY BINARY value
				ORDER BY " . $this->databaseConnection->trimSql ('value') . "
			;";
			
			# Show the distinct values for this query (having regard to any page number supplied via the URL)
			$html .= "\n<h2>Distinct values for field <em>*{$field}</em></h2>";
			$html .= $this->recordListing (false, $query, $preparedStatementValues, "/fields/{$field}/values/", false, false, $view = 'valuestable', false, $knownTotalAvailable, 'distinct value');
			
		} else {
			
			# Get the total number of results as a known total to seed the paginator, overriding automatic counting (as the DISTINCT will give a wrong result)
			$countingQuery = "SELECT COUNT( DISTINCT(recordId) ) AS total FROM catalogue_processed WHERE field = :field;";
			$preparedStatementValues = array ('field' => $field);
			$knownTotalAvailable = $this->databaseConnection->getOneField ($countingQuery, 'total', $preparedStatementValues);
			
			# Define the query for records having this field
			$query = "SELECT DISTINCT(recordId) AS recordId FROM catalogue_processed WHERE field = :field ORDER BY recordId;";
			
			# Show the records for this query (having regard to any page number supplied via the URL)
			$html .= "\n<h2>Records containing field <em>*{$field}</em></h2>";
			$html .= "\n<p>Below are records which contain a field <em>{$field}</em> ('<em>" . htmlspecialchars ($fields[$field]['Description']) . "</em>'):</p>";
			$html .= $this->recordListing (false, $query, $preparedStatementValues, "/fields/{$field}/", false, false, 'listing', false, $knownTotalAvailable);
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to create a fields droplist
	private function fieldsDroplist ($fields, $showDistinctValues, $currentField)
	{
		# Create an array of field links
		$droplist = array ();
		$droplist[$this->baseUrl . '/fields/'] = 'Fields:';
		foreach ($fields as $field => $attributes) {
			$link = $this->fieldLink ($field) . ($showDistinctValues ? 'values/' : '');
			$droplist[$link] = $field;
		}
		
		# Determine the current field link
		$selected = $this->fieldLink ($currentField) . ($showDistinctValues ? 'values/' : '');
		
		# Compile the HTML and register a processor
		$html  = pureContent::htmlJumplist ($droplist, $selected, $this->baseUrl . '/', $name = 'fieldsdroplist', $parentTabLevel = 0, $class = 'fieldsjumplist', false);
		pureContent::jumplistProcessor ($name);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to format a list of records as a hyperlinked list
	private function recordList ($records, $fullInfo = false)
	{
		# Table mode
		if ($fullInfo) {
			foreach ($records as $recordId => $record) {
				$link = $this->recordLink ($recordId);
				$records[$recordId]['id'] = "<a href=\"{$link}\">{$recordId}</a>";
			}
			$headings = $this->databaseConnection->getHeadings ($this->settings['database'], 'searchindex');
			$headings['recordId'] = '#';
			$html = application::htmlTable ($records, $headings, 'lines', $keyAsFirstColumn = false, $uppercaseHeadings = true, $allowHtml = true);
			
		# List mode
		} else {
			$list = array ();
			foreach ($records as $record => $label) {
				$link = $this->recordLink ($record);
				$list[$record] = "<a href=\"{$link}\">" . htmlspecialchars ($label) . "</a>";
				$list[$record] = "<li>{$list[$record]}</li>";
			}
			$html = application::splitListItems ($list, 4);
		}
		
		# Compile the HTML
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to render a table of values
	public function valuesTable ($data, $searchField = false, $linkPrefix = false, $idField = false, $enableSortability = true, $tableHeadingSubstitutions = array ('id' => '#'))
	{
		# Add links to each title if required (search implementation)
		if ($searchField) {
			foreach ($data as $index => $record) {
				$data[$index]['title'] = "<a href=\"{$this->baseUrl}/search/?casesensitive=1&{$searchField}=" . urlencode ($record['title']) . '">' . htmlspecialchars ($record['title']) . '</a>';
			}
		}
		
		# Add links to each title if required (link prefix implementation)
		if ($linkPrefix) {
			foreach ($data as $index => $record) {
				$title = htmlspecialchars ($record['title']);
				#!# Using htmlspecialchars seems to cause double-encoding of &amp; or, e.g. the link becomes buggy on /fields/pu/values/ for value = '("Optimus")'
				# Note that a value containing only a dot is not possible to create a link for: http://stackoverflow.com/questions/3856693/a-url-resource-that-is-a-dot-2e
				$data[$index]['title'] = "<a href=\"{$this->baseUrl}{$linkPrefix}" . urlencode ($record['title']) . "/\">{$title}</a>";
			}
		}
		
		# Add links to record IDs if required
		if ($idField) {
			foreach ($data as $index => $record) {
				$data[$index][$idField] = "<a href=\"{$this->baseUrl}/records/{$record[$idField]}/\">{$record[$idField]}</a>";
			}
		}
		
		# Compile the HTML
		$html = '';
		if ($enableSortability) {
			$html .= "\n" . '<!-- Enable table sortability: --><script language="javascript" type="text/javascript" src="/sitetech/sorttable.js"></script>';
		}
		$html .= application::htmlTable ($data, $tableHeadingSubstitutions, $class = 'reportlisting lines compressed sortable" id="sortable', $keyAsFirstColumn = false, $uppercaseHeadings = true, $allowHtml = true);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to get the distinct fields
	private function getDistinctFields ()
	{
		# Get the distinct fields
		$query = "SELECT
			field AS Field, COUNT(*) AS Instances
			FROM catalogue_rawdata
			GROUP BY field
			ORDER BY field
		;";
		$data = $this->databaseConnection->getData ($query);
		
		# Reindex by field name
		$fields = array ();
		foreach ($data as $id => $field) {
			$fieldname = $field['Field'];
			$fields[$fieldname] = $field;
		}
		
		# Get the descriptions
		$descriptions = $this->getFieldDescriptions ();
		
		# Add a link to view each
		foreach ($fields as $fieldname => $field) {
			$link = $this->fieldLink ($fieldname);
			$fields[$fieldname]['Records'] = "<a href=\"{$link}\">Records: <strong>{$fieldname}</strong></a>";
			$fields[$fieldname]['Distinct values'] = "<a href=\"{$link}values/\">Distinct values: <strong>{$fieldname}</strong></a>";
		}
		
		# Merge in the descriptions
		foreach ($fields as $fieldname => $field) {
			$fields[$fieldname]['Description'] = (isSet ($descriptions[$fieldname]) ? $descriptions[$fieldname] : '?');
		}
		
		# Add commas to long numbers (which is not efficient to do in MySQL)
		foreach ($fields as $fieldname => $field) {
			$fields[$fieldname]['Instances'] = '<span class="right">' . number_format ($field['Instances']) . '</span>';
		}
		
		# Return the list
		return $fields;
	}
	
	
	# Function to get the field descriptions
	private function getFieldDescriptions ()
	{
		# Return the definitions
		return array (
			'a' => 'Author(s)',
			'abs' => 'Abstract',
			'acc' => 'Accession number',
			'acq' => 'Acquisition number',
			'ad' => "Authorial detail (usually 'and others', 'ed.', 'eds.' etc",
			'aff' => 'Affiliation',
			'ag' => 'Other affiliations',
			'al' => 'Alternative spelling of transliterated name or alternative name',
			'art' => 'Article (record type)',
			'con' => 'Condition',
			'd' => 'Date',
			'date' => 'Date of accession/acquisition (depending on context)',
			'doc' => 'Document (record type)',
			'doi' => 'DOI',
			'doifld' => 'DOI',
			'doslink' => 'DOI',
			'e' => 'Additional contributions to work (introduction, translation, editing etc.)',
			'edn' => 'Edition',
			'ee' => 'Further contributors of this specific edition',
			'form' => 'Form of item, if non-book',
			'freq' => 'Frequency of title (serials only)',
			'ft' => 'Former title',
			'fund' => 'Purchase fund',
			'hold' => 'Current holdings (serials only)',
			'in' => '"In" larger text (i.e. Chapter "in" edited work or article "in" journal)',
			'isbn' => 'ISBN',
			'issn' => 'ISSN',
			'j' => 'Denotes start of journal information, but has no information entered directly into this field',
			'k' => 'UDC classification number',
			'k2' => 'Left blank but essential if using *kb',
			'ke' => 'Analytic record link: (redundant for processing purposes) used in host record that Muscat uses for GUI purposes to link to a lookup',
			'kf' => 'Analytic record link: (redundant for processing purposes) internal Muscat GUI representation of kg',
			'kg' => 'Analytic record link: used in analytic (child) record that is basically a join to the q0 (ID) of the host',
			'kb' => 'Details of how item is received (exchange, gift etc. Mostly applied to serials)',
			'ks' => 'UDC Keywords (hidden mirror)',
			'kw' => 'UDC Keywords',
			'lang' => 'Language type',
			'loc' => 'Location',
			'local' => 'Local note',
			'location' => 'Location (mirror of *loc)',
			'lpt' => 'Languages of parallel title',
			'lto' => 'Language of *to',
			'n' => 'Name field (used in conjunction with *e and *ee fields)',
			'n1' => 'Name field (used in conjunction with *e and *ee fields)',
			'n2' => 'Name field (used in conjunction with *e and *ee fields)',
			'nd' => 'Seems to denote titles such as "Jr." and "Sir".',
			'note' => 'Note field',
			'notes' => 'Denotes start of note type field',
			'o' => 'Origin (where item came from)',
			'p' => 'Pagination',
			'pg' => 'Precedes publication details',
			'pl' => 'Place of publication',
			'pr' => 'Price',
			'priv' => 'Private note',
			'pt' => 'Part (pagination, volume etc.)',
			'pu' => 'Publisher',
			'q0' => "Muscat's unique record identifier",
			'r' => 'Range (serials only)',
			'recr' => 'Responsible bibliographer for record (bibliographer initials)',
			'ref' => 'Reference (Accession and/or aquisition number)',
			'role' => 'Additional information about contributors to work',
			'rpl' => 'PGA category',
			'ser' => 'Serial (record type)',
			'size' => 'Size of item',
			'sref' => 'Supplier reference',
			'st' => 'Subsequent title',
			'status' => 'Item status (on order/received etc.)',
			't' => 'Title',
			'ta' => 'Amendment to misspelled title (supplied by bibliographer)',
			'tc' => '[Unknown]',
			'tg' => 'Title group',
			'to' => 'Title of original (if publication is a translation)',
			'ts' => 'Title of series',
			'tt' => 'Translation of title',
			'url' => 'URL',
			'urlft' => 'URL mirror',
			'urlfull' => 'URL mirror',
			'urlgen' => 'URL mirror',
			'v' => 'Number of volumes',
			'vno' => 'Volume number',
			'winlink' => 'Linking field for URLs',
		);
	}
	
	
	# Function to export a report download
	public function reportdownload ($id)
	{
		# Get the data
		$query = "
			SELECT
				id AS 'Record number',
				ExtractValue(xml, '//status') AS 'Status',
				CONCAT_WS(', ', ExtractValue(xml, '*/ag/a[1]/n1'), ExtractValue(xml, '*/ag/a[1]/n2')) AS 'Author 1',
				ExtractValue(xml, '*/ag/a/*') AS 'All authors',
				ExtractValue(xml, '*/tg/tc') AS 'Title fields',
				ExtractValue(xml, '//pg[1]/pu') AS 'Publisher',
				ExtractValue(xml, '*/d[1]') AS 'Date of publication',
				ExtractValue(xml, '*/isbn[1]') AS 'ISBN',
				ExtractValue(xml, '//acq[1]/pr') AS 'Price',
				ExtractValue(xml, '//acq[1]/sref') AS 'Supplier reference',
				ExtractValue(xml, '//acq[1]/ref') AS 'Order number',
				ExtractValue(xml, '//acq[1]/o') AS 'Supplier/donor',
				ExtractValue(xml, '//acq[1]/recr') AS 'Bibliographer',
				ExtractValue(xml, '//acq[1]/date') AS 'Date (acq)',
				ExtractValue(xml, '//notes[1]/note') AS 'Note',
				ExtractValue(xml, '//notes[1]/priv') AS 'Note (private)',
				ExtractValue(xml, '//notes[1]/local') AS 'Note (local)'
				
			FROM catalogue_xml
			LEFT OUTER JOIN (
				SELECT recordId FROM reportresults WHERE report = '{$id}' ORDER BY recordId
			) AS matches
			ON catalogue_xml.id = matches.recordId
			WHERE recordId IS NOT NULL
		;";
		$data = $this->databaseConnection->getData ($query);
		
		// application::dumpData ($data);
		// die;
		
		# Convert to CSV
		require_once ('csv.php');
		csv::serve ($data, $id);
	}
	
	
	# Function to create a record listing based on a query, with pagination
	public function recordListing ($id, $query, $preparedStatementValues = array (), $baseLink, $listingIsProblemType = false, $queryString = false, $view = 'listing' /* listing/record/table/valuestable */, $tableViewTable = false, $knownTotalAvailable = false, $entityName = 'record', $orderingControlsHtml = false)
	{
		# Assemble the query, determining whether to use $id or $query
		if ($id) {
			$query = "SELECT * FROM reportresults WHERE report = '{$id}' ORDER BY recordId;";
			$preparedStatementValues = array ();
		}
		
		# Enable a listing type switcher, if in a supported view mode, which can override the view
		$listingTypeSwitcherHtml = false;
		$switcherSupportedViewTypes = array ('listing', 'record');
		if (in_array ($view, $switcherSupportedViewTypes)) {
			$listingTypes = array (
				'listing'	=> 'application_view_columns',
				'record'	=> 'application_tile_vertical',
			);
			$view = application::preferenceSwitcher ($listingTypeSwitcherHtml, $listingTypes);
			
			# In record mode, use a separate pagination memory
			if ($view == 'record') {
				$this->settings['paginationRecordsPerPageDefault'] = 25;
				$this->settings['paginationRecordsPerPagePresets'] = array (5, 10, 25, 50, 100);
				$this->settings['cookieName'] = 'fullrecordsperpage';
			}
		}
		
		# Load the pagination class
		require_once ('pagination.php');
		$pagination = new pagination ($this->settings, $this->baseUrl);
		
		# Determine what page
		$page = $pagination->currentPage ();
		
		# Create a form to set the number of pagination records per page
		$paginationRecordsPerPage = $pagination->recordsPerPageForm ($recordsPerPageFormHtml);
		
		# Get the data, via pagination
		list ($dataRaw, $totalAvailable, $totalPages, $page, $actualMatchesReachedMaximum) = $this->databaseConnection->getDataViaPagination ($query, $tableViewTable, true, $preparedStatementValues, array (), $paginationRecordsPerPage, $page, false, $knownTotalAvailable);
		// application::dumpData ($this->databaseConnection->error ());
		
		# Start the HTML for the record listing
		$html  = '';
		
		# Show the listing of problematic records, or report a clean record set
		if ($listingIsProblemType) {
			if (!$dataRaw) {
				$html .= "\n<p class=\"success\">{$this->tick}" . " All {$entityName}s are correct - congratulations!</p>";
				return $html;
			} else {
				$html .= "\n<p class=\"warning\">" . '<img src="/images/icons/exclamation.png" /> The following ' . (($totalAvailable == 1) ? "{$entityName} matches" : number_format ($totalAvailable) . " {$entityName}s match") . ':</p>';
			}
		} else {
			if (!$dataRaw) {
				$html .= "\n<p>There are no {$entityName}s.</p>";
			} else {
				$html .= "\n<p>" . ($totalAvailable == 1 ? "There is one {$entityName}" : 'There are ' . number_format ($totalAvailable) . " {$entityName}s") . ':</p>';
			}
		}
		
		# Add pagination links and controls
		$paginationLinks = '';
		if ($dataRaw) {
			$html .= $listingTypeSwitcherHtml;
			$html .= $recordsPerPageFormHtml;
			$paginationLinks = pagination::paginationLinks ($page, $totalPages, $this->baseUrl . $baseLink, $queryString);
			$html .= $paginationLinks;
		}
		
		# Compile the listing
		$data = array ();
		if ($dataRaw) {
			switch (true) {
				
				# List mode
				case ($view == 'listing'):
					
					# List mode needs just id=>id format
					foreach ($dataRaw as $index => $record) {
						$recordId = $record['recordId'];
						$data[$recordId] = $recordId;
					}
					$html .= $this->recordList ($data);
					$html  = "\n<div class=\"graybox\">" . $html . "\n</div>";	// Surround with a box
					break;
					
				# Record mode
				case ($view == 'record'):
					
					# Record mode shows each record
					foreach ($dataRaw as $index => $record) {
						$recordId = $record['recordId'];
						$data[$recordId] = $this->recordFieldValueTable ($recordId, 'rawdata');
						$data[$recordId] = "\n<h3>Record <a href=\"{$this->baseUrl}/records/{$recordId}/\">#{$recordId}</a>:</h3>" . "\n<div class=\"graybox\">" . $data[$recordId] . "\n</div>";	// Surround with a box
					}
					$html .= implode ($data);
					break;
					
				# Table view
				case ($view == 'searchresults'):
					
					# Replace each label with the record title, since table view shows shows titles, and format the year
					$titles = $this->getRecordTitles (array_keys ($dataRaw));
					foreach ($dataRaw as $recordId => $record) {
						$data[$recordId] = $dataRaw[$recordId];
						$data[$recordId]['title'] = $titles[$recordId];
						$data[$recordId]['authors'] = $this->compileAuthorsString ($record['surname'], $record['forename']);
						$data[$recordId]['journaltitle'] = str_replace ('@', ', ', trim ($record['journaltitle'], '@'));
						$data[$recordId]['year'] = str_replace ('@', ', ', trim ($record['year'], '@'));
					}
					
					# Show ordering controls if required
					$html .= $orderingControlsHtml;
					
					# Render as a table
					// $html .= $this->recordList ($data, true);
					
					# Render as boxes
					$html .= "\n<div class=\"clearright\">";
					foreach ($data as $record) {
						$html .= "\n<div class=\"graybox\">";
						$html .= "\n<p class=\"right comment\">#{$record['id']}</p>";
						$html .= "\n<h4><a href=\"{$this->baseUrl}/records/{$record['id']}/\">{$record['title']}</a></h4>";
						$metadata = array ();
						if ($record['journaltitle']) {
							$metadata[] = 'In: <em>' . $record['journaltitle'] . '</em>';
						}
						if ($record['authors']) {
							$metadata[] = $record['authors'];
						}
						$metadata[] = $record['year'];
						$html .= "\n" . implode ("<br />\n", $metadata);
						$html .= "\n</div>";
					}
					$html .= "\n</div>";
					
					// # Surround with a box
					// $html  = "\n<div class=\"graybox\">" . $html . "\n</div>";
					
					break;
					
				# Table view but showing values rather than records
				case ($view == 'valuestable'):
					
					# Generate the HTML
					$html .= $this->valuesTable ($dataRaw, false, $baseLink, false, false);
					break;
					
				# Self-defined
				case (preg_match ('/^callback\(([^)]+)\)/', $view, $matches)):	// e.g. callback(foo) will run $this->reports->foo ($data);
					
					# Pass the data to the callback to generate the HTML
					$callbackMethod = $matches[1];
					$html .= $this->reports->$callbackMethod ($dataRaw);
					break;
			}
		}
		
		# Surround the listing with a div for clearance purposes
		$html = "\n<div class=\"listing\">" . $html . "\n</div>";
		
		# Add the pagination controls again at the end, for long pages
		if (($view != 'listing') || count ($dataRaw) > 50) {
			$html .= $paginationLinks;
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Helper function to compile an authors string
	private function compileAuthorsString ($surnameIndexerString, $forenameIndexerString)
	{
		$surnames = explode ('@', trim ($surnameIndexerString, '@'));
		if ($surnames) {
			$forenames = explode ('@', trim ($forenameIndexerString, '@'));
			if (count ($surnames) == count ($forenames)) {
				$names = array ();
				foreach ($surnames as $index => $surname) {
					$names[] = $surname . ', ' . $forenames[$index];
				}
				$string = implode ('; ', $names);
			} else {
				$string = implode ('; ', $surnames);
			}
		}
		
		# Return the result string
		return $string;
	}
	
	
	# Function to get the record titles
	private function getRecordTitles ($ids)
	{
		# Get the titles
		$data = $this->databaseConnection->selectPairs ($this->settings['database'], 'catalogue_processed', $conditions = array ('field' => 'tc', 'recordId' => $ids), $columns = array ('recordId', 'value'), true, $orderBy = 'recordId');
		return $data;
	}
	
	
	# Function to link a field
	private function fieldLink ($fieldname, $convertEntities = true)
	{
		return $this->baseUrl . '/fields/' . ($convertEntities ? htmlspecialchars ($fieldname) : $fieldname) . '/';
	}
	
	
	# Function to get stats data
	private function getStats ()
	{
		# Get the data and return it
		$data = $this->databaseConnection->selectOne ($this->settings['database'], 'statistics', array ('id' => 1));
		unset ($data['id']);
		return $data;
	}
	
	
	# Function to create a table of overall stats
	private function statisticsTable ()
	{
		# Get the data
		$data = $this->getStats ();
		
		# Apply number format
		foreach ($data as $key => $value) {
			if (ctype_digit ($value)) {
				$data[$key] = number_format ($value);
			}
		}
		
		# Compile the HTML
		$headings = $this->databaseConnection->getHeadings ($this->settings['database'], 'statistics');
		$html = application::htmlTableKeyed ($data, $headings);
		
		# Return the HTML
		return $html;
	}
	
	
	# Post-migration tasks page
	public function postmigration ()
	{
		# Start the HTML
		$html = "\n" . '<p>This section lists the post-migration tasks.</p>';
		
		# Show the listing
		$html .= $this->reportsTable ('postmigration');
		
		# Show the HTML
		echo $html;
		
	}
	
	
	# Function to import the file, clearing any existing import
	# Needs privileges: SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP
	public function import ()
	{
		# Import files
		$importFiles = array ('muscatview', 'rawdata');
		
		# Define the import types
		$importTypes = array (
			'full'					=> 'FULL import (c. 3.95 hours)',
			'full-selection'		=> 'FULL import, filtered to selection list',
			'xml'					=> 'Regenerate XML only (c. 21 minutes)',
			'marc'					=> 'Regenerate MARC only (c. 1.1 hours)',
			'marc-selection'		=> 'Regenerate MARC only, filtered to selection list',
			'external'				=> 'Regenerate external Voyager records only (c. 5 seconds)',
			'outputstatus'			=> 'Regenerate output status only (c. 15 seconds)',
			'exports'				=> 'Regenerate MARC export files and Bibcheck report (c. 15 minutes)',
			'tests'					=> 'Run automated tests',
			'reports'				=> 'Regenerate reports only (c. 7 minutes)',
			'listings'				=> 'Regenerate listings reports only (c. 30 minutes)',
			'searchtables'			=> 'Regenerate search tables (c. 20 seconds)',
		);
		
		# Define the introduction HTML
		$fileCreationInstructionsHtml  = "\n\t" . '<p>Open a Muscat terminal and type the following. Note that this can take a while to create.</p>';
		$fileCreationInstructionsHtml .= "\n\t" . '<p>Be aware that you may have to wait until your colleagues are not using Muscat to do an export, as exporting may lock Muscat access.</p>';
		$fileCreationInstructionsHtml .= "\n\t" . "<tt>n-voyager_export</tt>";
		
		# Run the import UI
		$this->importUi ($importFiles, $importTypes, $fileCreationInstructionsHtml, 'txt');
		
		# Show errors file if present
		$html = '';
		if (is_file ($this->errorsFile)) {
			$html .= "\n<hr />";
			$html .= "\n<h3>Errors from import:</h3>";
			$html .= file_get_contents ($this->errorsFile);
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to do the actual import
	public function doImport ($exportFiles, $importType, &$html)
	{
		# Run the import in the subclass, and return the result
		return $this->import->run ($exportFiles, $importType, $html);
	}
	
	
	# Search
	public function search ()
	{
		#!# Move fieldsindex code into search index and reduce fields
		#!# Work out why search also seems to check transliterated version
		#!# htmlspecialchars on each part of result
		
		/*
		Main fields that people want to search within are:
		
		- Author (original=a/ee, compiled as: n1 for surname and n2 for forename)
		- Title (t and tt become tc, which is a superset)
		- Date (d)
		
		So use
		
		Surname (n1)
		Forename (n2)
		Title (tc)
		Journal (t - technically must follow j, but for now without)
		Date (d), must be four-digits
		Keyword (kw)
		Text within abstract (abs)
		ISBN (isbn)
		Region (ks, filtered on (*[0-9]+) ) - precompile at
			- Arctic - beginning *3 or *4 or *6
			- Antarctic - begins with *7 or *8
			- Polar - begins *2
			- Russian - begins *5 but not *55/*56/*57/*58
		*/
		
		# Determine whether to do case-sensitive matching
		#!# Currently only implemented for the anywhere key
		$caseSensitivity = (isSet ($_GET['casesensitive']) && ($_GET['casesensitive'] == '1') ? 'BINARY ' : '');
		
		# Determine whether to force matching for a complete value rather than part of a value, i.e. whether 'science' matches only 'science' rather than 'academy of science' also
		#!# Currently only implemented for the anywhere key
		#!# Doesn't seem to work
		$completeMatch = (isSet ($_GET['completematch']) && ($_GET['completematch'] == '1'));
		
		# Define the search clause templates
		$literalBackslash	= '\\';										// PHP representation of one literal backslash
		$mysqlBacklash		= $literalBackslash . $literalBackslash;	// http://lists.mysql.com/mysql/193376 shows that a MySQL backlash is always written as \\
		$searchClauses = array (
			'title'			=> "REPLACE(REPLACE(title, '<em>', ''), '</em>', '') LIKE :title",
			'title_transliterated'		=> 'title_transliterated LIKE :title_transliterated',
			'surname'		=> 'surname LIKE :surname',
			'forename'		=> 'forename LIKE :forename',
			'journaltitle'	=> 'journaltitle = :journaltitle',
			'seriestitle'	=> 'seriestitle = :seriestitle',
			'region'	=> array (
				'Polar regions'						=> "region REGEXP '{$mysqlBacklash}({$mysqlBacklash}*[2][0-9]*{$mysqlBacklash})'",				// *2
				'   Arctic'							=> "region REGEXP '{$mysqlBacklash}({$mysqlBacklash}*[3|4|5|6][0-9]*{$mysqlBacklash})'",		// *3 or *4 or *5 or *6
				'   North America'					=> "region REGEXP '{$mysqlBacklash}({$mysqlBacklash}*[40][0-9]*{$mysqlBacklash})'",				// *40
				'   Russia'							=> "region REGEXP '{$mysqlBacklash}({$mysqlBacklash}*[50|51|52|53][0-9]*{$mysqlBacklash})'",	// *50 - *53
				'   European Arctic'				=> "region REGEXP '{$mysqlBacklash}({$mysqlBacklash}*[55|56|57|58][0-9]*{$mysqlBacklash})'",	// *55/*56/*57/*58
				'   Arctic Ocean'					=> "region REGEXP '{$mysqlBacklash}({$mysqlBacklash}*[6][0-9]*{$mysqlBacklash})'",				// *6
				'   Antarctic and Southern Ocean'	=> "region REGEXP '{$mysqlBacklash}({$mysqlBacklash}*[7|8][0-9]*{$mysqlBacklash})'",			// *7/*8
				'Non-polar regions'					=> "region REGEXP '{$mysqlBacklash}([2|3|4|5|6|7|8|9][0-9]*{$mysqlBacklash})'",	// run from (2) to (97) NB without *
			),
			'year'			=> 'year LIKE :year',
			'language'		=> 'language LIKE :language',
			'abstract'		=> 'abstract LIKE :abstract',
			'keyword'		=> 'keyword LIKE :keyword',
			'isbn'			=> 'isbn LIKE :isbn',
			'location'		=> 'location LIKE :location',
			'anywhere'		=> "anywhere LIKE {$caseSensitivity} :anywhere",
		);
		
		# Clear framework variables out of the GET environment
		unset ($_GET['action']);
		if (isSet ($_GET['page'])) {
			$page = $_GET['page'];	// Cache for later
			unset ($_GET['page']);
		}
		
		# Define ordering types and capture (then clear) any setting from the environment
		$orderingOptions = array (
			'title'		=> 'Title',
			'-year'		=> 'Year (most recent first)',
			'year'		=> 'Year (oldest first)',
		);
		$orderBy = 'title';		// Default
		$maintainParameters = array ();
		if (isSet ($_GET['orderby'])) {
			if (strlen ($_GET['orderby'])) {
				if (isSet ($orderingOptions[$_GET['orderby']])) {
					$orderBy = $_GET['orderby'];
					$maintainParameters['order'] = $orderBy;
				}
			}
			unset ($_GET['orderby']);
		}
		
		# Start the HTML
		$html  = "\n<p>This search will find records that match all the query terms you enter.</p>";
		$html .= "\n<p>Searches are not case-sensitive.</p>";
		$html .= "\n<p>This catalogue covers items which were catalogued at SPRI until 2015. For later items, <a href=\"http://idiscover.lib.cam.ac.uk/primo-explore/search?vid=44CAM_PROD&amp;lang=en_US&amp;sortby=rank&amp;mode=advanced&amp;search_scope=SCO\" target=\"_blank\">search for items catalogued from 2016</a>.<br />Also still available is the old <a href=\"http://www.spri.cam.ac.uk/library/catalogue/sprilib/\">SPRILIB</a> search interface, but this covers a smaller number of records and the data is not up-to-date.</p>";
		
		# Create the search form
		$result = $this->searchForm ($html, $searchClauses, $maintainParameters);
		
		# Show results if submitted
		if ($result) {
			
			# Define the base link of the page; baseUrl will be added
			$baseLink = '/' . $this->actions['search']['url'];
			
			# Cache a build of the query string
			$queryStringComplete = http_build_query ($result);
			
			# Implement ordering UI
			$list = array ('Order by:');
			foreach ($orderingOptions as $ordering => $label) {
				$url = "{$this->baseUrl}{$baseLink}" . (isSet ($page) && is_numeric ($page) && ($page > 1) ? "page{$page}.html" : '') . "?{$queryStringComplete}&orderby=" . urlencode ($ordering);
				$list[] = "<a href=\"" . htmlspecialchars ($url) . '"' . ($ordering == $orderBy ? ' class="selected"' : '') . '>' . htmlspecialchars ($label) . '</a>';
			}
			$orderingControlsHtml = application::htmlUl ($list, 0, 'orderby');
			$queryStringComplete .= '&orderby=' . $orderBy;
			$orderBySql = $orderBy;
			if ($orderBy == 'title') {$orderBySql = 'titleSortfield';}
			if (preg_match ('/^-(.+)$/', $orderBy, $matches)) {$orderBySql = $matches[1] . ' DESC';}
			if ($orderBy != 'title') {$orderBySql .= ', titleSortfield';}	// Add secondary sorting by title
			
			# Create a list of constraints
			$constraints = array ();
			foreach ($result as $key => $value) {
				$searchClause = (is_array ($searchClauses[$key]) ? $searchClauses[$key][$value] : $searchClauses[$key]);
				if ($completeMatch) {
					$searchClause = str_replace (' LIKE ', ' = BINARY ', $searchClause);
					$result[$key] = $this->literalLikeValue ($value);
				} else {
					if (substr_count ($searchClause, 'LIKE')) {$result[$key] = '%' . $this->literalLikeValue ($value) . '%';}	// Text matches should be %search%
				}
				if (!substr_count ($searchClause, ' :')) {unset ($result[$key]);}	// Do not supply a value if there is no placeholder
				$constraints[$key] = $searchClause;
			}
			
			# Ensure records are public in public access mode
			if (!$this->searchUserIsInternal) {
				$constraints['_status'] = "status = 'migrate'";
			}
			
			# Construct the query
			$query = "SELECT
					id,
					title,
					surname,
					forename,
					journaltitle,
					year
				FROM searchindex
				WHERE \n    (" . implode (")\nAND (", $constraints) . ")
				ORDER BY {$orderBySql}
			;";
			
			# Restore $_GET['page']
			if (isSet ($page)) {$_GET['page'] = $page;}
			
			# Display the results
			$html .= $this->recordListing (false, $query, $result, $baseLink, false, $queryStringComplete, 'searchresults', "{$this->settings['database']}.searchindex", false, 'record', $orderingControlsHtml);
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to provide the search form
	private function searchForm (&$html, $searchClauses, $maintainParameters = array ())
	{
		# Define an autocomplete callback for auto-submit on select
		$titleAutocompleteOptions = array (
			'delay'		=> 0,
			'select'	=> "function (event, ui) {
				$('#searchform').append ('<input type=\"hidden\" name=\"redirect\" value=\"' + ui.item.recordId + '\">');
				$('#searchform').submit();
			}",
		);
		
		# If a redirect parameter is specified, redirect to that record ID; this strategy ensures that the number of completed searches is accurate for log statistics purposes
		if (isSet ($_GET['redirect'])) {
			if (is_numeric ($_GET['redirect'])) {
				$redirectTo = $this->baseUrl . '/records/' . $_GET['redirect'] . '/';
				$html .= application::sendHeader (302, $redirectTo, true);
				return;
			}
		}
		
		# Run the form module
		$form = new form (array (
			'displayRestrictions' => false,
			'get' => true,
			'name' => false,
			'nullText' => false,
			'submitButtonText' => 'Search',
			'formCompleteText' => false,
			'requiredFieldIndicator' => false,
			'reappear' => true,
			'id' => 'searchform',
			'databaseConnection' => $this->databaseConnection,
			'div' => 'ultimateform horizontalonly',
			'autofocus' => true,
			'size' => 40,
			'submitTo' => $this->baseUrl . '/' . $this->actions['search']['url'],
		));
		$form->dataBinding (array (
			'database' => $this->settings['database'],
			'table' => 'searchindex',
			'includeOnly' => array_keys ($this->import->getFieldsIndexFields ()),
			'textAsVarchar' => true,
			'inputAsSearch' => true,
			'autocomplete' => $this->baseUrl . '/data.html?do=searchautocomplete&field=%field',	// term=... will be added
			'attributes' => array (
				#!# type=search should not be required - not sure what bug in ultimateForm is causing this
				'title' => array ('type' => 'search', 'append' => '<input type="submit" value="Search" />', 'autocompleteOptions' => $titleAutocompleteOptions),	#!# Ideally, ultimateForm should have a natively way to add a second submit button within the form
				'region' => array ('autocomplete' => false, 'type' => 'select', 'nullText' => 'Any', 'values' => array_keys ($searchClauses['region']), ),
				'year' => array ('regexp' => '^([0-9]{4})$', 'size' => 7, 'maxlength' => 4, ),
				'anywhere' => array ('autocomplete' => false, ),
			),
		));
		$formHtml = '';
		$result = $form->process ($formHtml);
		
		# Filter to those filled-in
		if ($result) {
			foreach ($result as $key => $value) {
				if (!strlen ($value)) {
					unset ($result[$key]);
				}
			}
		}
		
		# If there is a result, redirect to a simplified version of the URL
		if ($result) {
			if (array_keys ($_GET) != array_keys ($result)) {
				$result += $maintainParameters;
				$redirectTo = $this->baseUrl . '/' . $this->actions[$this->action]['url'] . '?' . http_build_query ($result);
				$html .= application::sendHeader (302, $redirectTo, true);
				return $result;
			}
		}
		
		# If there is a result, show the hiding system first
		if ($result) {
			$html .= '
				<!-- http://api.jquery.com/toggle/ -->
				<script src="//code.jquery.com/jquery-latest.js"></script>
				<script>
					$(document).ready(function(){
						$("a#showform").click(function () {
							$("#searchform").toggle();
						});
					});
				</script>
				<style type="text/css">#searchform {display: none;}</style>
			';
			$html .= "\n" . '<p><a id="showform" name="showform" href="#"><img src="/images/icons/pencil.png" alt="" border="0" /> <strong>Refine/filter this search</strong></a> if you wish, or <a href="' . "{$this->baseUrl}/{$this->actions['search']['url']}" . '"><strong>start a new search</strong></a>.</p>' . "\n<hr />";
		}
		
		# Add the form HTML
		$html .= $formHtml;
		
		# Return the result
		return $result;
	}
	
	
	# Handler for AJAX endpoint for search autocomplete
	public function dataSearchautocomplete ()
	{
		# Ensure a field and search are both defined
		if (!isSet ($_GET['field']) || !isSet ($_GET['term'])) {return false;}
		
		# Ensure the field is supported
		if (!array_key_exists ($_GET['field'], $this->import->getFieldsIndexFields ())) {return false;}
		$field = $_GET['field'];
		
		# Ensure there are at least three characters in the search term, to avoid heavy database traffic and useless results
		if (mb_strlen ($_GET['term']) < 3) {return false;}
		
		# Start constraints
		$constraints = array ("`{$field}` LIKE :term");
		$preparedStatementValues = array ('term' => '%' . $_GET['term'] . '%');
		
		# Ensure records are public in public access mode
		if (!$this->userIsAdministrator) {
			$constraints['_status'] = "status = 'migrate'";
		}
		
		# Get the data; use of _GET in field definition is safe against SQL injection due to previous check against $this->import->fieldsIndexFields
		$query = "SELECT id, `{$field}`
			FROM searchindex
			WHERE \n    (" . implode (")\nAND (", $constraints) . ")
			LIMIT 20		-- Avoid too many results in drop-down
		;";
		$data = $this->databaseConnection->getPairs ($query, false, $preparedStatementValues);
		
		# Extract from the @ separators
		if ($field == 'title') {
			
			# For titles, retain the id as index; there is no need to use splitCombinedTokenList as all titles are known to be singular
			foreach ($data as $id => $title) {
				$data[$id] = trim ($title, '@');
			}
		} else {
			
			# For other fields, explode multiple values as new entries
			$data = application::splitCombinedTokenList ($data, $separator = '@');
			
			# Ensure the search term is present in each separated part; e.g. a search for 'Bar' which creates a match '@Foo@Bar@' should have the token 'Foo' eliminated after splitting
			foreach ($data as $index => $string) {
				if (!substr_count (mb_strtolower ($string), mb_strtolower ($_GET['term']))) {
					unset ($data[$index]);
				}
			}
		}
		
		# Strip tags
		foreach ($data as $idOrIndex => $string) {
			$data[$idOrIndex] = strip_tags ($string);
		}
		
		# Natsort the list; this maintains key numbers
		natsort ($data);
		
		# Arrange as label/value pairs, so that the visible label can be reformatted while retaining a valid search for the value; see: http://api.jqueryui.com/autocomplete/#option-source
		$results = array ();
		$i = 0;
		foreach ($data as $idOrIndex => $string) {
			$results[$i] = array ('value' => $string, 'label' => $string);
			
			# Include the record ID for the title field, so that a direct redirect can be made on select
			if ($field == 'title') {
				$results[$i]['recordId'] = $idOrIndex;
			}
			$i++;
		}
		
		# Truncate to avoid over-long autocomplete entries
		$truncateTo = 80;
		foreach ($results as $index => $result) {
			if (mb_strlen ($result['label']) > $truncateTo) {
				$results[$index]['value'] = mb_substr ($result['value'], 0, $truncateTo, 'UTF-8');
				$results[$index]['label'] = mb_substr ($result['label'], 0, $truncateTo, 'UTF-8') . chr(0xe2).chr(0x80).chr(0xa6);	// &hellip;
			}
		}
		
		# Return the data
		return $results;
	}
	
	
	# Function to ensure a value passed for use in LIKE is literal
	private function literalLikeValue ($term)
	{
		# Turn backlashes and wildcards into literals
		$replacements = array (
			'\\' => '\\\\',
			'%' => '\\%',
			'_' => '\\_',
		);
		$term = strtr ($term, $replacements);
		
		# Return the term
		return $term;
	}
	
	
	# MARC21 parser definition
	public function marcparser ()
	{
		# Start the HTML
		$html  = '';
		
		# Get the supported macros
		$supportedMacros = $this->marcConversion->getSupportedMacros ();
		
		# Display a flash message if set
		#!#C Flash message support needs to be added to ultimateForm natively, as this is a common use-case
		$successMessage = 'The definition has been updated.';
		if ($flashValue = application::getFlashMessage ('submission', $this->baseUrl . '/')) {
			$message = "\n" . "<p>{$this->tick} <strong>" . $successMessage . '</strong></p>';
			$html .= "\n<div class=\"graybox flashmessage\">" . $message . '</div>';
		}
		
		# Create a form
		$form = new form (array (
			'formCompleteText' => false,
			'reappear'	=> true,
			'display' => 'paragraphs',
			'autofocus' => true,
			'unsavedDataProtection' => true,
			'whiteSpaceTrimSurrounding' => false,
		));
		$form->heading ('p', "Here you can define the translation of the Muscat data's XML representation to MARC21 as interpreted by the <a href=\"http://www.lib.cam.ac.uk/libraries/login/bibstandard/bibstandards.htm\" target=\"_blank\">local standards</a>.");
		$form->heading ('p', 'The parser uses <a target="_blank" href="http://msdn.microsoft.com/en-us/library/ms256122.aspx">XPath operators</a>, enclosed in { } brackets, used to target parts of the <a target="_blank" href="' . $this->baseUrl . '/schema.html">schema</a>.');
		$form->heading ('p', 'Control characters may exist at the start of the line:<br />A = All (non-optional) must result in a match for the line to be displayed (ignoring indicator block macros);<br />E = Any (<em>E</em>ither) of the values must result in a match for the line to be displayed (ignoring indicator block macros);<br />R = Vertically-repeatable field.');
		$form->heading ('p', "A subfield can be set as optional by adding ?, e.g. {$this->doubleDagger}b?{//acq/ref} . Optional blocks found to be empty are removed before an A (all) control character is considered.");
		$form->heading ('p', "A subfield can be set as horizontally-repeatable by adding R, e.g. {$this->doubleDagger}b?R{//acq/ref} . Horizontal repeatability of a subfield takes precendence over vertical repeatability.");
		$form->heading ('p', 'Macros available, written as <tt>{xpath..|macro:<em>macroname</em>}</tt>, are: <tt>' . implode ('</tt>, <tt>', $supportedMacros) . '</tt>. (Those for use in the two indicator positions are prefixed with <tt>indicators</tt>).');
		$form->heading ('p', 'Lines starting with # are comments.');
		$form->heading ('p', 'Macro blocks preceeded with i indicates that this is an indicator block macro; these are for use with control character(s) A/E, as detailed above.');
		$form->textarea (array (
			'name'		=> 'definition',
			'title'		=> 'Parser definition',
			'required'	=> true,
			'rows'		=> 30,
			'cols'		=> 120,
			'default'	=> $this->import->getMarcParserDefinition (),
			'wrap'		=> 'off',
		));
		
		# Validate the parser syntax
		if ($unfinalisedData = $form->getUnfinalisedData ()) {
			if ($unfinalisedData['definition']) {
				$record = '';	// Bogus record - good enough for checking parsing
				$this->marcConversion->convertToMarc ($unfinalisedData['definition'], $record);
				if ($errorHtml = $this->marcConversion->getErrorHtml ()) {
					$form->registerProblem ('compilefailure', strip_tags ($errorHtml));
				}
			}
		}
		
		# Process the form
		if ($result = $form->process ($html)) {
			
			# Save the latest version to the filesystem as a backup for versioning purposes
			$prefixWarning = "# IMPORTANT: This file is NOT actively used by the importer, but is created when updating the definition purely for the purposes of creating an file version that can be checked into the versioning repository.\n";
			file_put_contents ($this->applicationRoot . '/tables/marcparser.txt', $prefixWarning . $result['definition']);
			
			# Save the latest version to the database
			$this->databaseConnection->insert ($this->settings['database'], 'marcparserdefinition', array ('definition' => $result['definition']));
			
			# Set a flash message
			$function = __FUNCTION__;
			$redirectTo = "{$_SERVER['_SITE_URL']}{$this->baseUrl}/{$this->actions[$function]['url']}";
			$redirectMessage = "\n{$this->tick}" . ' <strong>' . $successMessage . '</strong></p>';
			application::setFlashMessage ('submission', '1', $redirectTo, $redirectMessage, $this->baseUrl . '/');
			
			# Confirm success, resetting the HTML, and show the submission
			$html = application::sendHeader (302, $redirectTo, true);
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# MARC21 output page
	public function export ()
	{
		# End if no output
		$directory = $_SERVER['DOCUMENT_ROOT'] . $this->baseUrl;
		if (!file_exists ("{$directory}/spri-marc-migrate.txt")) {
			$html = "\n<p>There is no MARC output yet. Please <a href=\"{$this->baseUrl}/import/\">run an import</a> first.</p>";
			echo $html;
			return;
		}
		
		# Get the fileset counts
		$query = "SELECT status, COUNT(*) AS total FROM catalogue_marc GROUP BY status;";
		$totals = $this->databaseConnection->getPairs ($query);
		
		# Compile the HTML
		$html  = "\n<h3>Downloads</h3>";
		$html .= "\n<table class=\"lines spaced downloads\">";
		$filesets = $this->import->getFilesets ();
		foreach ($filesets as $fileset => $label) {
			$html .= "\n\t<tr>";
			$html .= "\n\t\t<td><strong>{$label}</strong>:<br />" . number_format ($totals[$fileset]) . ' records</td>';
			$html .= "\n\t\t<td><a href=\"{$this->baseUrl}/export/spri-marc-{$fileset}.txt\">MARC21 data<br />(text)</a></td>";
			$html .= "\n\t\t<td><a href=\"{$this->baseUrl}/export/spri-marc-{$fileset}.mrk\">MARC21 text<br />(text, .mrk)</a></td>";
			$html .= "\n\t\t<td><a href=\"{$this->baseUrl}/export/spri-marc-{$fileset}.mrc\">MARC21 data<br />(binary .mrc)</a></td>";
			$html .= "\n\t\t<td><a href=\"{$this->baseUrl}/export/spri-marc-{$fileset}.mrc.zip\"><strong>MARC21 data, blocks<br />(binary .mrc)</strong></a></td>";
			$html .= "\n\t</tr>";
		}
		$html .= "\n</table>";
		
		# Get the totals
		$totalsQuery = "SELECT status, COUNT(*) AS total FROM {$this->settings['database']}.catalogue_marc WHERE bibcheckErrors IS NOT NULL GROUP BY status;";
		$totals = $this->databaseConnection->getPairs ($totalsQuery);
		
		# Compile error listings
		$errorsHtml = '';
		$jumplist = array ();
		foreach ($filesets as $fileset => $label) {
			$filename = $directory . "/spri-marc-{$fileset}.errors.txt";
			$errors = file_get_contents ($filename);
			$errorListingHtml = htmlspecialchars (trim ($errors));
			$errorListingHtml = preg_replace ("/(\s)(SPRI-)([0-9]+)/", '\1\2<a href="' . $this->baseUrl . '/records/\3/"><strong>\3</strong></a>', $errorListingHtml);
			$totalErrors = (isSet ($totals[$fileset]) ? $totals[$fileset] : '0');
			$jumplist[] = "<a href=\"#{$fileset}\" class=\"" . ($totalErrors ? 'warning' : 'success') . "\">{$label} ({$totalErrors})</a>";
			$errorsHtml .= "\n<h4 id=\"{$fileset}\" class=\"" . ($totalErrors ? 'warning' : 'success') . "\">Errors: {$label} (" . $totalErrors . ')</h4>';
			if ($errorListingHtml) {
				$errorsHtml .= "\n<div class=\"graybox\">";
				$errorsHtml .= "\n<pre>";
				$errorsHtml .= $errorListingHtml;
				$errorsHtml .= "\n</pre>";
				$errorsHtml .= "\n</div>";
			} else {
				$errorsHtml .= "\n<p><em>No errors for this group.</em></p>";
			}
		}
		
		# List the error types, grouped with a count for each type
		$html .= "\n<h3>Bibcheck error types</h3>";
		$errorTypes = $this->getBibcheckErrorTypeCounts ();
		$errorTypesList = array ();
		foreach ($errorTypes as $type => $total) {
			$errorTypesList[] = htmlspecialchars ($type) . ' <strong>(' . $total . ')</strong>';
		}
		$html .= application::htmlUl ($errorTypesList, 0, 'smaller');
		
		# Show errors
		$html .= "\n<h3>Errors</h3>";
		$html .= "\n<p class=\"jumplist\">Jump below to: " . implode (' | ', $jumplist) . '</p>';
		$html .= $errorsHtml;
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to extract Bibcheck errors types as a list with totals
	private function getBibcheckErrorTypeCounts ()
	{
		# Get the data
		$errorsList = array ();
		$errorsQuery = "SELECT bibcheckErrors FROM {$this->settings['database']}.catalogue_marc WHERE bibcheckErrors IS NOT NULL;";
		$errorSets = $this->databaseConnection->getPairs ($errorsQuery);
		
		# Split into single errors, as an error block may contain more than one error
		foreach ($errorSets as $errorSet) {
			$errors = explode ("\n", $errorSet);
			foreach ($errors as $error) {
				$error = trim ($error);
				$errorsList[] = $error;
			}
		}
		
		# Group with counts
		$errorCounts = array_count_values ($errorsList);
		
		# Sort by highest first
		arsort ($errorCounts);
		
		# Return the counts
		return $errorCounts;
	}
	
	
	# Reverse-transliteration definition
	public function transliterator ()
	{
		# Start the HTML
		$html  = '';
		
		# Define the language
		$language = 'Russian';
		
		# Display a flash message if set
		#!#C Flash message support needs to be added to ultimateForm natively, as this is a common use-case
		$successMessage = 'The definition has been updated.';
		if ($flashValue = application::getFlashMessage ('submission', $this->baseUrl . '/')) {
			$html .= "\n<div class=\"graybox flashmessage\">";
			$html .= "\n" . "<p>{$this->tick} <strong>" . $successMessage . '</strong></p>';
			$html .= "\n" . "<p>You can <a href=\"{$this->baseUrl}/reports/transliterations/\">view the <strong>transliterated titles report</strong></a>, showing all results.</p>";
			$html .= '</div>';
		}
		
		# Create a form
		$form = new form (array (
			'formCompleteText' => false,
			'reappear'	=> true,
			'display' => 'paragraphs',
			'autofocus' => true,
			'unsavedDataProtection' => true,
			'whiteSpaceTrimSurrounding' => false,
		));
		$form->heading ('p', "Here you can define the reverse-transliteration definition.");
		$form->heading ('p', 'Character set numbers - useful references for copying and pasting: <a href="http://en.wikipedia.org/wiki/Russian_alphabet" target="_blank">Russian</a> and <a href="http://en.wikipedia.org/wiki/List_of_Unicode_characters#Basic_Latin" target="_blank">Basic latin</a>.');
		$form->heading ('p', 'Forward transliteration specification: <a href="https://en.wikipedia.org/wiki/BGN/PCGN_romanization_of_Russian" target="_blank">BGN/PCGN romanization of Russian (1947)</a>.');
		$form->heading ('p', 'Character tester: <a href="https://graphemica.com/" target="_blank">Graphemica character tester</a>.');
		$form->textarea (array (
			'name'		=> 'definition',
			'title'		=> 'Reverse-transliteration definition',
			'required'	=> true,
			'rows'		=> 40,
			'cols'		=> 120,
			'default'	=> $this->getReverseTransliterationDefinition (),
			'wrap'		=> 'off',
		));
		
		# Validate the parser syntax
		if ($unfinalisedData = $form->getUnfinalisedData ()) {
			if ($unfinalisedData['definition']) {
				require_once ('xml.php');
				if (!xml::isValid ($unfinalisedData['definition'], $errors)) {
					$form->registerProblem ('invalidxml', 'The definition was not valid XML, as per the following error(s):' . application::htmlUl ($errors));
				}
			}
		}
		
		# Process the form
		if ($result = $form->process ($html)) {
			
			# Save the latest version
			$this->databaseConnection->insert ($this->settings['database'], 'reversetransliterationdefinition', array ('definition' => $result['definition']));
			
			# Save an archive copy to the tables directory
			$prefixWarning = "<!-- IMPORTANT: This file is NOT actively used by the importer, but is created when updating the definition purely for the purposes of creating an file version that can be checked into the versioning repository. -->\n";
			file_put_contents ($this->applicationRoot . '/tables/reverseTransliteration.xml', $prefixWarning . $result['definition']);
			
			# Compile the reverse transliterator
			if (!$this->transliteration->compileReverseTransliterator ($result['definition'], $language, $errorHtml)) {
				echo "\n<p class=\"warning\">{$errorHtml}</p>";
				return false;
			}
			
			# Regenerate the transliterations report /reports/transliterations/
			// ini_set ('max_execution_time', 0);
			// $this->transliterateTransliterationsTable ();
			
			# Set a flash message
			$function = __FUNCTION__;
			$redirectTo = "{$_SERVER['_SITE_URL']}{$this->baseUrl}/{$this->actions[$function]['url']}";
			$redirectMessage = "\n{$this->tick}" . ' <strong>' . $successMessage . '</strong></p>';
			application::setFlashMessage ('submission', '1', $redirectTo, $redirectMessage, $this->baseUrl . '/');
			
			# Confirm success, resetting the HTML, and show the submission
			$html = application::sendHeader (302, $redirectTo, true);
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to return the reverse-transliteration definition
	private function getReverseTransliterationDefinition ()
	{
		# Get the latest version
		$query = "SELECT definition FROM {$this->settings['database']}.reversetransliterationdefinition ORDER BY id DESC LIMIT 1;";
		if (!$definition = $this->databaseConnection->getOneField ($query, 'definition')) {
			echo "\n<p class=\"warning\"><strong>Error:</strong> The reverse-transliteration definition could not be retrieved.</p>";
			return false;
		}
		
		# Return the string
		return $definition;
	}
	
	
	# Function to return the selected record set definition
	private function getSelectionDefinition ()
	{
		# Get the latest version
		$query = "SELECT definition FROM {$this->settings['database']}.selectiondefinition ORDER BY id DESC LIMIT 1;";
		if (!$definition = $this->databaseConnection->getOneField ($query, 'definition')) {
			echo "\n<p class=\"warning\"><strong>Error:</strong> The selected record set definition could not be retrieved.</p>";
			return false;
		}
		
		# Return the string
		return $definition;
	}
	
	
	# Merge definition
	public function merge ()
	{
		# Start the HTML
		$html  = '';
		
		# Display a flash message if set
		#!#C Flash message support needs to be added to ultimateForm natively, as this is a common use-case
		$successMessage = 'The definition has been updated.';
		if ($flashValue = application::getFlashMessage ('submission', $this->baseUrl . '/')) {
			$message = "\n" . "<p>{$this->tick} <strong>" . $successMessage . '</strong></p>';
			$html .= "\n<div class=\"graybox flashmessage\">" . $message . '</div>';
		}
		
		# Create a form
		$form = new form (array (
			'formCompleteText' => false,
			'reappear'	=> true,
			'display' => 'paragraphs',
			'autofocus' => true,
			'unsavedDataProtection' => true,
			'whiteSpaceTrimSurrounding' => false,
		));
		$form->heading ('p', "Here you can define the translation for merging.");
		$form->textarea (array (
			'name'		=> 'definition',
			'title'		=> 'Merge definition',
			'required'	=> true,
			'rows'		=> 30,
			'cols'		=> 120,
			'default'	=> $this->import->getMergeDefinition (),
			'wrap'		=> 'off',
		));
		
		# Validate the parser syntax
		if ($unfinalisedData = $form->getUnfinalisedData ()) {
			if ($unfinalisedData['definition']) {
				$this->import->parseMergeDefinition ($unfinalisedData['definition'], $errorString);
				if ($errorString) {
					$form->registerProblem ('compilefailure', $errorString);
				}
			}
		}
		
		# Process the form
		if ($result = $form->process ($html)) {
			
			# Save the latest version
			$this->databaseConnection->insert ($this->settings['database'], 'mergedefinition', array ('definition' => $result['definition']));
			
			# Save an archive copy to the tables directory
			file_put_contents ($this->applicationRoot . '/tables/mergeDefinition.tsv', $result['definition']);
			
			# Set a flash message
			$function = __FUNCTION__;
			$redirectTo = "{$_SERVER['_SITE_URL']}{$this->baseUrl}/{$this->actions[$function]['url']}";
			$redirectMessage = "\n{$this->tick}" . ' <strong>' . $successMessage . '</strong></p>';
			application::setFlashMessage ('submission', '1', $redirectTo, $redirectMessage, $this->baseUrl . '/');
			
			# Confirm success, resetting the HTML, and show the submission
			$html = application::sendHeader (302, $redirectTo, true);
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Page to preprocess the LoC name authority data
	public function loc ()
	{
		# Start the HTML
		$html = '';
		
		# Obtain confirmation from the user
		$message = '<strong>Begin processing?</strong>';
		$confirmation = 'Yes, begin';
		if ($this->areYouSure ($message, $confirmation, $html)) {
			
			# Allow long script execution
			ini_set ('max_execution_time', 0);
			
			# Allow large queries for the chunking operation
			$maxQueryLength = (1024 * 1024 * 32);	// i.e. this many MB
			$query = 'SET SESSION max_allowed_packet = ' . $maxQueryLength . ';';
			$this->databaseConnection->execute ($query);
			
			# Populate the LoC name authority data
			if (!$this->import->populateLocNameAuthorities ($error)) {
				$html = "\n<p>{$this->cross} {$error}</p>";
			} else {
				$html = "\n<p>{$this->tick} The LoC name authority data was processed.</p>";
			}
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Page to preprocess the other names data
	public function othernames ()
	{
		# Start the HTML
		$html = '';
		
		# Obtain confirmation from the user
		$message = '<strong>Begin processing?</strong>';
		$confirmation = 'Yes, begin';
		if ($this->areYouSure ($message, $confirmation, $html)) {
			
			# Populate the other names data
			if (!$this->import->populateOtherNames ($error)) {
				$html = "\n<p class=\"warning\">ERROR: {$this->cross} {$error}</p>";
			} else {
				$html = "\n<p>{$this->tick} The other names data was processed.</p>";
			}
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Page to provide the list for selected import
	public function selection ()
	{
		# Start the HTML
		$html = '';
		
		# Add introductory text
		$html .= '<p>Here you can define the list of records for a selected import.</p>';
		$html .= "<p>To use this, define the list below, then run an <a href=\"{$this->baseUrl}/import/\" class=\"actions\"><img src=\"/images/icons/database_refresh.png\" class=\"icon\" /> import</a>, selecting an option that is filtered to this selection list.</p>";
		$html .= '<p>An import using this filter list will <strong>overwrite</strong> any existing import data and MARC output files, but will <strong>leave</strong> reports and tests in place from the previous import.</p>';
		$html .= '<p>Any invalid record numbers will be ignored.</p>';
		
		# Create a form
		$form = new form (array (
			'formCompleteText' => false,
			'reappear'	=> true,
			'div' => 'graybox',
			'autofocus' => true,
			'unsavedDataProtection' => true,
			'whiteSpaceTrimSurrounding' => false,
		));
		$form->checkboxes (array (
			'name'		=> 'tests',
			'title'		=> 'Include records used by the test system?',
			'values'	=> array ('Yes'),
			'default'	=> array ('Yes'),
			'output'	=> array ('processing' => 'special-setdatatype'),
		));
		$form->textarea (array (
			'name'		=> 'definition',
			'title'		=> 'Selected record set definition, one per line',
			'required'	=> true,
			'rows'		=> 10,
			'cols'		=> 30,
			'default'	=> $this->getSelectionDefinition (),
		));
		
		# Process the form
		if ($result = $form->process ($html)) {
			
			# Arrange the insert
			$insert = array (
				'tests' => ($result['tests'] ? 1 : NULL),
				'definition' => $result['definition'],		// This needs to be at least MEDIUMTEXT
				'createdBy' => $this->user,
			);
			
			# Save the latest version
			$this->databaseConnection->insert ($this->settings['database'], 'selectiondefinition', $insert);
			
			# Confirm success, resetting the HTML, and show the submission
			$html = "<p>{$this->tick} The list has been saved.</p>";
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# AJAX endpoint
	public function data ()
	{
		# Always return JSON response
		header ('Content-Type: application/json; charset=utf-8');
		
		# Obtain the posted data
		if (isSet ($_GET['do'])) {
			
			# Construct the handler method
			$function = 'data' . ucfirst ($_GET['do']);	// e.g. dataWhitelist
			if (method_exists ($this, $function)) {
				
				# Run the handler and retrieve the response as the result
				$response = $this->$function ();
			}
		}
		
		# Error if no response defined
		if (!isSet ($response)) {
			header ('HTTP/1.1 500 Internal Server Error');
			$response = array ('error' => 'Request was invalid');
		}
		
		# Transmit the response
		echo json_encode ($response);
	}
	
	
	# Handler for AJAX endpoint for whitelisting
	public function dataWhitelist ()
	{
		# End if not an administrator
		if (!$this->userIsAdministrator) {return false;}
		
		# Ensure an ID is defined
		if (!isSet ($_GET['id'])) {return false;}
		
		# Ensure the ID is in shard format
		if (!preg_match ('/^([0-9]+):([0-9]+)$/', $_GET['id'])) {return false;}
		
		# Get the data
		if (!$data = $this->databaseConnection->selectOne ($this->settings['database'], 'transliterations', array ('id' => $_GET['id']))) {return false;}
		
		# If the name already exists, treat this as a deletion
		if ($this->databaseConnection->selectOne ($this->settings['database'], 'tickednames', array ('surname' => $data['title']))) {
			
			# Delete the record
			if (!$this->databaseConnection->delete ($this->settings['database'], 'tickednames', array ('surname' => $data['title']))) {return false;}
			
			# Dynamically update the transliterations table
			$this->databaseConnection->update ($this->settings['database'], 'transliterations', array ('inNameAuthorityList' => 0), array ('id' => $data['id']));
			
			# Return success code
			return array ('result' => -1);	// Removed
		}
		
		# Construct the new record
		$value = '-1000';	// Flag to indicate manual review
		$insert = array (
			'id' => $data['id'],	// Shard ID
			'surname' => $data['title'],
			'results' => $value,
		);
		
		# Insert the data to the persistent data table
		if (!$this->databaseConnection->insert ($this->settings['database'], 'tickednames', $insert)) {return false;}
		
		# Dynamically update the transliterations table
		$this->databaseConnection->update ($this->settings['database'], 'transliterations', array ('inNameAuthorityList' => $value), array ('id' => $data['id']));
		
		# Return success code
		return array ('result' => 1);	// Added
	}
}

?>
