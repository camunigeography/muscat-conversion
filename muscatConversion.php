<?php

# Class to manage Muscat data conversion
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
	
	# Fieldsindex fields
	private $fieldsIndexFields = array (
		'title'			=> 'tc',
		'surname'		=> 'n1',
		'forename'		=> 'n2',
		'journaltitle'	=> '/art/j/tg/t',
		'seriestitle'	=> '/doc/ts',
		'region'		=> 'ks',
		'year'			=> 'd',
		'language'		=> 'lang',
		'abstract'		=> 'abs',
		'keyword'		=> 'kw',
		'isbn'			=> 'isbn',
		'location'		=> 'location',
		'anywhere'		=> '*',
	);
	
	# Define the file sets and their labels
	private $filesets = array (
		'migrate'	=> 'Migrate to Voyager',
		'suppress'	=> 'Suppress from OPAC',
		'ignore'	=> 'Ignore record',
	);
	
	# Record processing order, to ensure lookup dependencies do not fail
	private $recordProcessingOrder = array (
		'/doc',		// A whole document consisting of a book, report, volume of conference proceedings, letter, etc.
		'/ser',		// A periodical
		'/art/j',	// A part document consisting of a paper in a journal
		'/art/in',	// A part document consisting of a book chapter or conference paper
	);
	
	# Define the merge types
	private $mergeTypes = array (
		'TIP'	=> 'exact Title match and ISSN match, and top answer in Probablistic search',
		'TP'	=> 'exact Title, but not ISSN, and top answer in Probablistic search',
		'IP'	=> 'ISSN match, but not exact title, and top answer in Probablistic search',
		'P'		=> 'Probable match, unconfirmed, and top answer in Probablistic search',
		'C'		=> 'probable match, Confirmed',
	);
	
	# Define the location codes, as regexps
	private $locationCodes = array (
		'[0-9]{1,3} ?[A-Z]'							=> 'SPRI-SER',	// Serial
		'Periodical'								=> 'SPRI-SER',	// Analytics (has parent serial, or has a reference in square brackets to a serial with which it is shelved)
		'Archives'									=> 'SPRI-ARC',
		'Atlas'										=> 'SPRI-ATL',
		'Basement'									=> 'SPRI-BMT',
		"Bibliographers' Office"					=> 'SPRI-BIB',
		'Cupboard'									=> 'SPRI-CBD',
		'Folio'										=> 'SPRI-FOL',
		'Large Atlas'								=> 'SPRI-LAT',
		"Librarian's Office"						=> 'SPRI-LIO',
		'Library Office'							=> 'SPRI-LIO',
		'Map Room'									=> 'SPRI-MAP',
		'Pam'										=> 'SPRI-PAM',
		'Picture Library'							=> 'SPRI-PIC',
		'Reference'									=> 'SPRI-REF',
		'Russian Gallery'							=> 'SPRI-RUS',
		'Russian'									=> 'SPRI-RUS',
		'Shelf'										=> 'SPRI-SHF',
		'Special Collection'						=> 'SPRI-SPC',
		'Theses'									=> 'SPRI-THE',
		'Digital Repository'						=> 'SPRI-ELE',
		'F:/public/session'							=> 'SPRI-ELE',
		'F:/public/session/electronic publications'	=> 'SPRI-ELE',
		'Online'									=> 'SPRI-ELE',
		'World Wide Web'							=> 'SPRI-ELE',
		'WWW'										=> 'SPRI-ELE',
		"Friends' Room"								=> 'SPRI-FRI',
		'Museum Working Collection'					=> 'SPRI-MUS',
		'Shelved with pamphlets'					=> 'SPRI-PAM',
		'Shelved with monographs'					=> 'SPRI-SHF',
		// SPRI-NIS defined in marcConversion code
	);
	
	# Define known *ks values that represent status values rather than classifications
	private $ksStatusTokens = array (
		'MISSING',
		'PGA',		// Record intended for inclusion in next issue of PGA
		'X',		// Serial (issue(s)) not yet abstracted)
		'Y',		// Host item with analytics on card catalogue)
		'Z',		// Host item not yet analyzed)
		'C',		// Current serial
		'D',		// Dead serial
	);
	
	# Supported transliteration upgrade (BGN/PCGN -> Library of Congress) fields, at either (top/bottom) level of a record
	private $transliterationUpgradeFields = array (
		't',
		'pu',
		'n1', 'n2', 'nd',	// NB Keep these three together as generate245::classifyNdField() (as called from generate245::statementOfResponsibility() ), and generate245::roleAndSiblings() assumes they will be in sync in terms of transliteration
		'pu',
		'ts',
		'ft',
		'st',
		'ta',
	);
	
	# Define fields for transliteration name matching
	private $transliterationNameMatchingFields = array (
		'n1',
	);
	
	# Acquisition date cut-off for on-order -type items; these range from 22/04/1992 to 30/10/2015; the intention of this date is that 'recent' on-order items (intended to be 1 year ago) would be migrated but suppressed, and the rest deleted - however, this needs review
	private $acquisitionDate = '2015-01-01';
	
	# Order *status keywords
	private $orderStatusKeywords = array (
		'ON ORDER'			=> 'Item is in the acquisition process',
		'ON ORDER (O/P)'	=> 'On order, but out of print',
		'ON ORDER (O/S)'	=> 'On order, but out of stock',
		'ORDER CANCELLED'	=> 'Order has been cancelled for whatever reason',
		'RECEIVED'			=> 'Item has arrived at the library but is awaiting further processing before becoming available to users',
	);
	
	# Suppression keyword in *status
	private $suppressionStatusKeyword = 'SUPPRESS';
	
	# HTML tags potentially present in output, which will then be stripped
	private $htmlTags = array ('<em>', '</em>', '<sub>', '</sub>', '<sup>', '</sup>');
	
	
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
		$this->supportedReverseTransliterationLanguages = $this->transliteration->getSupportedReverseTransliterationLanguages ();
		
		# Load tables
		$this->diacriticsTable = $this->getDiacriticsTable ();
		
		# Create a handle to the MARC conversion module
		require_once ('marcConversion.php');
		$this->marcConversion = new marcConversion ($this, $this->transliteration, $this->supportedReverseTransliterationLanguages, $this->mergeTypes, $this->ksStatusTokens, $this->locationCodes, $this->diacriticsTable, $this->suppressionStatusKeyword, $this->getSuppressionScenarios ());
		
		# Create a handle to the reports module
		require_once ('reports.php');
		$this->reports = new reports ($this, $this->marcConversion, $this->locationCodes, $this->orderStatusKeywords, $this->suppressionStatusKeyword, $this->acquisitionDate, $this->ksStatusTokens, $this->diacriticsTable, $this->mergeTypes, $this->transliterationNameMatchingFields);
		$this->reportsList = $this->reports->getReportsList ();
		$this->listingsList = $this->reports->getListingsList ();
		
		# Determine which reports are informational reports
		$this->reportStatuses = $this->getReportStatuses ();
		
		# Merge the listings array into the main reports list
		$this->reportsList += $this->listingsList;
	}
	
	
	# Function to get the export date
	private function getExportDate ()
	{
		$tableStatus = $this->databaseConnection->getTableStatus ($this->settings['database'], 'catalogue_rawdata');
		return $tableStatus['Comment'];
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
	private function isListing ($report)
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
	
	
	# Function to show a record
	public function record ($id)
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
		
		# Obtain a MARC parsed version for the marc and presented output types
		if (in_array ($type, array ('presented', 'marc'))) {
			$data = $this->getRecords ($id, 'xml', false, false, $searchStable = (!$this->userIsAdministrator));
			$marcParserDefinition = $this->getMarcParserDefinition ();
			$mergeDefinition = $this->parseMergeDefinition ($this->getMergeDefinition ());
			$record['marc'] = $this->marcConversion->convertToMarc ($marcParserDefinition, $data['xml'], $mergeDefinition, $record['mergeType'], $record['mergeVoyagerId'], $record['suppressReasons']);		// Overwrite with dynamic read, maintaining other fields (e.g. merge data)
			$marcErrorHtml = $this->marcConversion->getErrorHtml ();
			$marcPreMerge = $this->marcConversion->getMarcPreMerge ();
			$sourceRegistry = $this->marcConversion->getSourceRegistry ();
		}
		
		# Render the result
		switch ($type) {
			
			# Presentation record
			case 'presented':
				$output = $this->presentedRecord ($record['marc']);
				break;
				
			# Text records
			case 'marc':
				$output  = '';
				if ($this->userIsAdministrator) {
					$output  = "\n<p>The MARC output uses the <a target=\"_blank\" href=\"{$this->baseUrl}/marcparser.html\">parser definition</a> to do the mapping from the XML representation.</p>";
					if ($record['bibcheckErrors']) {
						$output .= "\n<pre>" . "\n<p class=\"warning\">Bibcheck " . (substr_count ($record['bibcheckErrors'], "\n") ? 'errors' : 'error') . ":</p>" . $record['bibcheckErrors'] . "\n</pre>";
					}
					if ($marcErrorHtml) {
						$output .= $marcErrorHtml;
					}
					$output .= "\n<div class=\"graybox marc\">";
					$output .= "\n<p id=\"exporttarget\">Target <a href=\"{$this->baseUrl}/export/\">export</a> group: <strong>" . $this->migrationStatus ($id) . "</strong></p>";
					if ($record['mergeType']) {
						$output .= "\n<p>Note: this record has <strong>merge data</strong> (managed according to the <a href=\"{$this->baseUrl}/merge.html\" target=\"_blank\">merge specification</a>), shown underneath.</p>";
					}
					if ($record['mergeType']) {
						$output .= "\n" . '<p class="colourkey">Color key: <span class="sourcem">Muscat</span> / <span class="sourcev">Voyager</span></p>';
					}
				}
				$output .= "\n<pre>" . $this->showSourceRegistry ($this->highlightSubfields (htmlspecialchars ($record[$type])), $sourceRegistry) . "\n</pre>";
				if ($this->userIsAdministrator) {
					if ($record['mergeType']) {
						$output .= "\n<h3>Merge data</h3>";
						$output .= "\n<p>Merge type: {$record['mergeType']}" . (isSet ($this->mergeTypes[$record['mergeType']]) ? " ({$this->mergeTypes[$record['mergeType']]})" : '') . "\n<br />Voyager ID: #{$record['mergeVoyagerId']}.</p>";
						$output .= "\n<h4>Pre-merge record from Muscat:</h4>";
						$output .= "\n<pre>" . $this->highlightSubfields (htmlspecialchars ($marcPreMerge)) . "\n</pre>";
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
				$schemaFlattenedXmlWithContainership = $this->getSchema (true);
				$record = array ();
				$record['xml'] = xml::dropSerialRecordIntoSchema ($schemaFlattenedXmlWithContainership, $data, $xPathMatches, $xPathMatchesWithIndex, $errorHtml, $debugString);
			*/
				$output = "\n<div class=\"graybox\">" . "\n<pre>" . htmlspecialchars ($record[$type]) . "\n</pre>\n</div>";
				break;
				
			# Tabular records
			default:
				$class = $this->types[$type]['class'];
				foreach ($record as $index => $row) {
					$showHtmlTags = $this->htmlTags;
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
		$label = $this->filesets[$status];
		
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
		$xml = $this->getSchema (false, true);
		
		# Convert to HTML
		$html = "\n<pre>" . htmlspecialchars ($xml) . '</pre>';
		
		# Surround with a presentational box
		$html = "\n<div class=\"graybox\">{$html}</div>";
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to define the schema
	private function getSchema ($flattenedWithContainership = false, $formatted = false)
	{
		# Define the structure
		$structure = file_get_contents ($this->applicationRoot . '/tables/muscatSchema.xml');
		
		# Trim the structure to prevent parser errors
		$structure = trim ($structure);
		
		# Remove spaces if not formatted
		#!#C This is rather hacky
		if (!$formatted) {
			$structure = str_replace (array ("\n", "\r", "\t"), '', $structure);
		}
		
		# Flatten if required
		if ($flattenedWithContainership) {
			require_once ('xml.php');
			$structure = xml::flattenedXmlWithContainership ($structure);
		}
		
		# Return the structure
		return $structure;
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
	private function getRecords ($ids /* or single ID */, $type, $convertEntities = false, $linkFields = false, $searchStable = false)
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
						$records[$recordId][$index]['value'] = str_replace (array ('&lt;em&gt;', '&lt;/em&gt;', '&lt;sub&gt;', '&lt;/sub&gt;', '&lt;sup&gt;', '&lt;/sup&gt;'), $this->htmlTags, $records[$recordId][$index]['value']);
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
			'full'					=> 'FULL import (c. 5.7 hours)',
			'xml'					=> 'Regenerate XML only (c. 21 minutes)',
			'marc'					=> 'Regenerate MARC only (c. 1.1 hours)',
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
		# Start the HTML
		$html = '';
		
		# Start the error log
		$errorsHtml = '';
		
		# Ensure that GROUP_CONCAT fields do not overflow
		$sql = "SET SESSION group_concat_max_len := @@max_allowed_packet;";		// Otherwise GROUP_CONCAT truncates the combined strings
		$this->databaseConnection->execute ($sql);
		
		# Skip the main import if required
		if ($importType == 'full') {
			
			# Add each of the two Muscat data formats, or end on failure (e.g. UTF-8 problem)
			foreach ($exportFiles as $type => $exportFile) {
				if (!$tableComment = $this->processMuscatFile ($exportFile, $type, $errorsHtml)) {
					$this->logErrors ($errorsHtml, true);
					return false;
				}
			}
			
			# Create the processed table
			#  Dependencies: catalogue_rawdata
			$this->createProcessedTable ();
			
			# Parse special characters
			#   Dependencies: catalogue_processed
			$this->specialCharacterParsing ();
			
			# Perform mass data fixes
			#   Dependencies: catalogue_processed
			$this->massDataFixes ();
			
			# Replace location=Periodical in the processed records with the real, looked-up values
			$this->processPeriodicalLocations ($errorsHtml);
			
			# Create the UDC translations table
			$this->createUdcTranslationsTable ();
			
			# Create the transliteration table; actual transliteration of records into MARC is done on-the-fly
			$this->createTransliterationsTable ();
			
			# Upgrade the transliterations to Library of Congress
			$this->upgradeTransliterationsToLoc ();
			
			# Finish character processing stage
			$html .= "\n<p>{$this->tick} The character processing has been done.</p>";
			
			# Create the XML table; also available as a standalone option below
			#   Depencies: catalogue_processed
			if (!$this->createXmlTable (false, $errorsHtml)) {
				$this->logErrors ($errorsHtml, true);
				return false;
			}
			
			# Create the fields index table
			#  Dependencies: catalogue_processed and catalogue_xml
			$this->createFieldsindexTable ();
			
			# Create the search tables
			#  Dependencies: fieldsindex, catalogue_xml, catalogue_marc
			$this->createSearchTables ();
			
			# Create the statistics table
			$this->createStatisticsTable ($tableComment);
			
			# Confirm output
			$html .= "\n<p>{$this->tick} The data has been imported.</p>";
		}
		
		# Run option to create XML table only (included in the 'full' option above) if required
		if ($importType == 'xml') {
			if (!$this->createXmlTable (true, $errorsHtml)) {
				$this->logErrors ($errorsHtml, true);
				return false;
			}
		}
		
		# Create the external records
		if (($importType == 'full') || ($importType == 'external')) {
			if ($this->createExternalRecords ()) {
				$html .= "\n<p>{$this->tick} The external records currently in Voyager have been imported.</p>";
			}
		}
		
		# Create the MARC records
		if (($importType == 'full') || ($importType == 'marc')) {
			if (!$this->createMarcRecords ($errorsHtml /* amended by reference */)) {
				$this->logErrors ($errorsHtml, true);
				return false;
			}
			$html .= "\n<p>{$this->tick} The MARC versions of the records have been generated.</p>";
		}
		
		# Run option to set the MARC record status (included within the 'marc' (and therefore 'full') option above) if required
		if ($importType == 'outputstatus') {
			$this->marcRecordsSetStatus ();
		}
		
		# Run option to export the MARC files for export and regenerate the Bibcheck report (included within the 'marc' (and therefore 'full') option above) if required
		if ($importType == 'exports') {
			$this->createMarcExports (true);
			$html .= "\n<p>{$this->tick} The <a href=\"{$this->baseUrl}/export/\">export files and Bibcheck report</a> have been generated.</p>";
		}
		
		# Run (pre-process) the reports
		if (($importType == 'reports') || ($importType == 'full')) {
			$this->runReports ();
			$html .= "\n<p>{$this->tick} The <a href=\"{$this->baseUrl}/reports/\">reports</a> have been generated.</p>";
		}
		
		# Run (pre-process) the listings reports
		if (($importType == 'listings') || ($importType == 'full')) {
			$this->runListings ();
			$html .= "\n<p>{$this->tick} The <a href=\"{$this->baseUrl}/reports/\">listings reports</a> have been generated.</p>";
		}
		
		# Run (pre-process) the tests
		if (($importType == 'tests') || ($importType == 'full')) {
			$this->runTests ($errorsHtml /* amended by reference */);
			$html .= "\n<p>{$this->tick} The <a href=\"{$this->baseUrl}/reports/\">tests</a> have been generated.</p>";
		}
		
		# Run option to create the search tables
		if ($importType == 'searchtables') {
			$this->createSearchTables ();
			$html .= "\n<p>{$this->tick} The <a href=\"{$this->baseUrl}/search/\">search</a> tables have been (re-)generated.</p>";
		}
		
		# Write the errors to the errors log
		$this->logErrors ($errorsHtml, true);
		
		# Signal success
		return true;
	}
	
	
	# Function to provide an error logger
	public function logErrors ($errorsHtml, $reset = false)
	{
		# Log start
		$this->logger ('Writing errors file');
		
		# Append to the logfile (or start fresh if resetting)
		file_put_contents ($this->errorsFile, $errorsHtml, ($reset ? 0 : FILE_APPEND));	// Recreated freshly on each import
	}
	
	
	# Function to process each of the Muscat files into the database
	private function processMuscatFile ($exportFile, $type, &$errorsHtml)
	{
		# Log start
		$this->logger ('Starting ' . __METHOD__ . " for {$type} export file {$exportFile}");
		
		# Parse the file to a CSV
		$csvFilename = $this->exportsProcessingTmp . "catalogue_{$type}.csv";
		$this->parseFileToCsv ($exportFile, $csvFilename);
		
		# Insert the CSV data into the database
		$tableComment = 'Data from Muscat dated: ' . $this->dateString ($exportFile);
		if (!$this->insertCsvToDatabase ($csvFilename, $type, $tableComment, $errorsHtml)) {
			return false;
		}
		
		# Set the table IDs to be shard IDs, as "<recordId>:<line>", e.g. 1000:0, 1000:1, ..., 1000:39, 1003:0, 1003:1, etc.
		$sql = "ALTER TABLE {$this->settings['database']}.catalogue_{$type} CHANGE id id VARCHAR(10) NOT NULL COMMENT 'Shard ID';";		// VARCHAR(10) as records are up to 6 digits, plus colon, plus up to 999 rows per record
		$this->databaseConnection->execute ($sql);
		$sql = "UPDATE {$this->settings['database']}.catalogue_{$type} SET id = CONCAT(recordId, ':', line);";
		$this->databaseConnection->execute ($sql);
		
		# Add indexing for performance
		$sql = "ALTER TABLE {$this->settings['database']}.catalogue_{$type} ADD INDEX (`recordId`);";
		$this->databaseConnection->execute ($sql);
		$sql = "ALTER TABLE {$this->settings['database']}.catalogue_{$type} ADD INDEX (`field`);";
		$this->databaseConnection->execute ($sql);
		
		# Return the table comment
		return $tableComment;
	}
	
	
	# Function to get the export date as a string
	private function dateString ($exportFile)
	{
		# Determine the filename
		$basename = pathinfo ($exportFile, PATHINFO_FILENAME);
		$basename = preg_replace ('/^(muscatview|rawdata)/', '', $basename);
		$date = date_create_from_format ('Ymd', $basename);
		$string = date_format ($date, 'jS F Y');
		
		# Return the string
		return $string;
	}
	
	
	# Main parser
	private function parseFileToCsv ($exportFile, $csvFilename)
	{
		# Log start
		$this->logger ('Starting ' . __METHOD__ . " with export file {$exportFile}");
		
		# Create the file, doing a zero-byte write to create the file; the operations which follow are appends
		file_put_contents ($csvFilename, '');
		
		# Start a string to be used to write a CSV
		$csv = '';
		
		# Start a container for the current record
		$record = array ();
		
		# Read the file, one line at a time (file_get_contents would be too inefficient for a 220MB file if converted to an array of 215,000 records)
		$handle = fopen ($exportFile, 'rb');
		$recordCounter = 0;
		$chunkEvery = 2500;
		while (($line = fgets ($handle, 4096)) !== false) {
			
			# If the line is empty, this signifies the end of the record, so compile and process the record
			if (!strlen (trim ($line))) {
				
				# Compile the record, adding it to the CSV string
				$csv .= $this->addRecord ($record);
				
				# For every chunk, append the data to the CSV to avoid large memory usage
				$recordCounter++;
				if (($recordCounter % $chunkEvery) == 0) {
					file_put_contents ($csvFilename, $csv, FILE_APPEND);
					$csv = '';
				}
				
				# Reset the record container and move on
				$record = array ();
				continue;
			}
			
			# Add the line to the record
			$record[] = $line;
			
			# Break if the volume of lines has been reached
			if ($this->settings['debugMode']) {
				if ($recordCounter == $this->settings['debugMode']) {break;}
			}
		}
		
		# Compile the final record, adding it to the CSV string
		$csv .= $this->addRecord ($record);
		
		# Write the remaining CSV data and clear memory
		file_put_contents ($csvFilename, $csv, FILE_APPEND);
		unset ($csv);
		
		# Close the file being imported
		fclose ($handle);
	}
	
	
	# Function to add a record
	private function addRecord ($record)
	{
		# Combine lines
		$record = $this->trimCombineCarryoverLines ($record);
		
		# Rearrange to insertable datastructure, or end if the record is invalid (e.g. documentation records at start)
		if (!$record = $this->convertToCsv ($record)) {return false;}
		
		# Return the record
		return $record;
	}
	
	
	# Function to combine lines that contain carried-over text
	private function trimCombineCarryoverLines ($record)
	{
		# Define a line number above that we can join onto if necessary
		$lineNumberAboveJoinable = 0;
		
		# Work through each line
		foreach ($record as $lineNumber => $line) {
			
			# Determine if the line has a key. before trimming to avoid the situation of an intended carry-over string that begins with a * in the text
			$keyed = (mb_substr ($line, 0, 1) == '*');
			
			# Trim the line, which will be guaranteed not-empty after trimming
			$record[$lineNumber] = trim ($record[$lineNumber]);
			
			# Skip if the line is the '#' terminator line at the end
			#!# Need a check that it is the last line in the record
			if ($record[$lineNumber] == '#') {
				unset ($record[$lineNumber]);
				continue;
			}
			
			#!# Need to register cases where there isn't a *q0 at the start, which also ensures against -1 offsets
			
			# Join lines, separating by space, that do not begin with a key, and remove the orphaned carry-over
			if (!$keyed) {
				$record[$lineNumberAboveJoinable] .= ' ' . $record[$lineNumber];
				unset ($record[$lineNumber]);
				continue;
			}
			
			# Having confirmed a line that is retained, register this line number in case it is needed
			$lineNumberAboveJoinable = $lineNumber;
		}
		
		# Reindex the lines
		$record = array_values ($record);
		
		# Return the cleaned record
		return $record;
	}
	
	
	# Rearrange to insertable datastructure
	private function convertToCsv ($lines)
	{
		# Define the number of the first real record in the data
		$firstRealRecord = 1000;		// Records 1-999 are internal documentation records
		
		# Loop through each line, and split
		$record = array ();
		foreach ($lines as $lineNumber => $line) {
			
			# Split by the first whitespace
			preg_match ("/^\*([a-z0-9]+)\s*(.*)$/", $line, $matches);
			
			# Ensure the first line is *q0
			if ($lineNumber == 0) {
				if ($matches[1] != 'q0') {
					#!# Report the problem
					return false;
				}
				
				# Determine the ID, as *q0
				$recordId = $matches[2];
			}
			
			# Skip the documentation records (within range 1-999)
			if ($recordId < $firstRealRecord) {return false;}
			
			# Assemble the line as one of the inserts
			$record[$lineNumber] = array (
				'recordId' => $recordId,
				'line' => $lineNumber,
				'field' => $matches[1],
				'value' => $matches[2],
			);
		}
		
		# Convert to CSV
		require_once ('csv.php');
		$csv = csv::dataToCsv ($record, '', ',', array (), $includeHeaderRow = ($recordId == $firstRealRecord));
		
		# Return the CSV
		return $csv;
	}
	
	
	# Function to insert the CSV into the database
	private function insertCsvToDatabase ($csvFilename, $type, $tableComment, &$errorsHtml)
	{
		# Log start
		$this->logger ('Starting ' . __METHOD__ . " with CSV file {$csvFilename}");
		
		# Compile the table structure
		require_once ('csv.php');
		
		/*
		# Compile the SQL
		$sql = csv::filesToSql (dirname ($csvFilename), "catalogue_{$type}.csv", $fieldLabels = array (), $tableComment, $prefix = '', $names = array (), $errorsHtml, $highMemory = false);
		if ($errorsHtml) {
			echo "\n" . application::dumpData ($errorsHtml, false, true);
			return false;
		}
		*/
		
		# Compile the SQL; this is done manually rather than using csv::filesToSql as that is slow (it has to compute the structure) and doesn't cope well with having two CSVs in the same directory with one for each table
		$sql = "
			-- {$tableComment}
			DROP TABLE IF EXISTS `catalogue_{$type}`;
			CREATE TABLE `catalogue_{$type}` (
				id INT(11) NOT NULL AUTO_INCREMENT,
				`recordId` INT(6),
				`line` INT(3),
				`field` VARCHAR(8) COLLATE utf8_unicode_ci,
				`value` TEXT,
				PRIMARY KEY (id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='{$tableComment}';
			
			-- Data in catalogue_{$type}.csv
			LOAD DATA LOCAL INFILE '{$this->exportsProcessingTmp}catalogue_{$type}.csv'
			INTO TABLE `catalogue_{$type}`
			FIELDS TERMINATED BY ','
			ENCLOSED BY '\"'
			ESCAPED BY '\"'
			LINES TERMINATED BY '\\n'
			IGNORE 1 LINES
			(`recordId`,`line`,`field`,`value`)
			;
		";
		
		# Save the SQL to a file
		$sqlFilename = $this->exportsProcessingTmp . "catalogue_{$type}.sql";
		file_put_contents ($sqlFilename, $sql);
		
		# Execute the SQL, reporting any UTF-8 invalid character string errors (or other problems)
		if (!$this->databaseConnection->runSql ($this->settings, $sqlFilename, $isFile = true, $outputText)) {
			$errorsHtml .= "\n<p class=\"warning\">ERROR: Importing {$sqlFilename} failed with database error: <tt>" . htmlspecialchars ($outputText) . '</tt></p>';
			return false;
		}
		
		# Confirm success
		return true;
	}
	
	
	# Function to create the fields index table
	#   Dependencies: catalogue_processed and catalogue_xml
	private function createFieldsindexTable ()
	{
		# Log start
		$this->logger ('Starting ' . __METHOD__);
		
		# Now create the fields index table, based on the results of a query that combines them
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.fieldsindex;";
		$this->databaseConnection->execute ($sql);
		# CREATE TABLE AS ... wrongly results in a VARCHAR(344) column, resulting in record #195245 and others being truncated; length of at least VARCHAR(565) (as of 20/11/2012) is needed
		# $sql = "CREATE TABLE fieldsindex (PRIMARY KEY (id))
		# 	ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci	/* MyISAM forced, so that FULLTEXT search can be used */
		# 	AS
		# 	(SELECT
		# 		recordId AS id,
		# 		CONCAT('@', GROUP_CONCAT(`field` SEPARATOR '@'),'@') AS fieldslist
		# 	FROM {$this->settings['database']}.catalogue_processed
		# 	GROUP BY recordId
		# );";
		$sql = "CREATE TABLE fieldsindex (
			  id INT(6) NOT NULL COMMENT 'Record #',
			  fieldslist VARCHAR(1024) NOT NULL COMMENT 'Fields list',
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Summary statistics';
		";
		$this->databaseConnection->execute ($sql);
		$sql = "INSERT INTO fieldsindex (SELECT
				recordId AS id,
				CONCAT('@', GROUP_CONCAT(`field` SEPARATOR '@'),'@') AS fieldslist
			FROM {$this->settings['database']}.catalogue_processed
			GROUP BY recordId
		);";
		$this->databaseConnection->execute ($sql);
		
		# Add search fields, using cross-update technique at http://www.electrictoolbox.com/article/mysql/cross-table-update/ and http://dba.stackexchange.com/questions/21152/how-to-update-one-table-based-on-another-tables-values-on-the-fly
		$sql = "ALTER TABLE fieldsindex
			ADD title VARCHAR(2048) NULL COMMENT 'Title of work',
			ADD titleSortfield VARCHAR(255) NULL COMMENT 'Title of work (sort index)',
			ADD surname TEXT NULL COMMENT 'Author surname',
			ADD forename TEXT NULL COMMENT 'Author forename',
			ADD journaltitle TEXT NULL COMMENT 'Journal title',
			ADD seriestitle TEXT NULL COMMENT 'Series title',
			ADD region TEXT NULL COMMENT 'Region',
			ADD `year` TEXT NULL COMMENT 'Year',
			ADD `language` TEXT NULL COMMENT 'Language',
			ADD abstract TEXT NULL COMMENT 'Abstract',
			ADD keyword TEXT NULL COMMENT 'Keyword (UDC)',
			ADD isbn TEXT NULL COMMENT 'ISBN',
			ADD location TEXT NULL COMMENT 'Location',
			ADD anywhere TEXT NULL COMMENT 'Text anywhere within record',
			ADD INDEX(title)
		;";
		$this->databaseConnection->execute ($sql);
		foreach ($this->fieldsIndexFields as $field => $source) {
			$concatSeparator = '@';
			if ($source == 'ks') {$concatSeparator = '|';}	// Regions have '@' in them
			if ($source == 'lang') {$concatSeparator = ', ';}	// So that this is listed in report_languages_view nicely and so the search works directly from that page
			
			# Define inner select to retrieve the data, either an XPath-based lookup, or a standard field read
			if (substr_count ($source, '/')) {
				$innerSelectSql = "
					SELECT
						id,
						REPLACE( REPLACE( REPLACE( REPLACE( REPLACE( EXTRACTVALUE(xml, '{$source}'), '&amp;', '&'), '&lt;', '<'), '&gt;', '>'), '&quot;', '\"'), '&apos;', \"'\") AS value	/* Decode entities; see: https://stackoverflow.com/questions/30194976/ */
					FROM catalogue_xml
				";
			} else {
				$innerSelectSql = "
					SELECT
						recordId AS id,
						" . ($source != 'lang' ? "CONCAT('{$concatSeparator}', " : '') . "GROUP_CONCAT(value SEPARATOR '{$concatSeparator}')" . ($source != 'lang' ? ", '{$concatSeparator}')" : '') . " AS value
					FROM catalogue_processed "
					. ($source == '*' ? '' : "WHERE field = '{$source}'")
					. " GROUP BY recordId
				";
			}
			
			# Insert the values
			$sql = "
				UPDATE fieldsindex f
				INNER JOIN (
					{$innerSelectSql}
				) AS c
				ON f.id = c.id
				SET f.{$field} = c.value;";
			$this->databaseConnection->execute ($sql);
		}
		
		# Add the sortfield index, which discards quotes, HTML tags, etc.; this only needs the initial part of the string, so is limited to 200 characters, which is confirmed as fitting inside a VARCHAR(255)
		$sql = "UPDATE fieldsindex SET titleSortfield = LEFT(" . $this->databaseConnection->trimSql ('title', $this->htmlTags) . ', 200);';
		$this->databaseConnection->execute ($sql);
	}
	
	
	# Function to create the search tables
	#   Dependencies: fieldsindex, catalogue_xml, catalogue_marc
	private function createSearchTables ()
	{
		# Log start
		$this->logger ('Starting ' . __METHOD__);
		
		# Clone the fieldsindex table, including indexes, first dropping any existing table from a previous import; see: http://stackoverflow.com/a/3280042/180733
		$this->databaseConnection->query ('DROP TABLE IF EXISTS searchindex;');
		$this->databaseConnection->query ('CREATE TABLE searchindex LIKE fieldsindex;');
		$this->databaseConnection->query ('INSERT searchindex SELECT * FROM fieldsindex;');
		
		# Remove non-needed fields to improve table efficiency
		$query = 'ALTER TABLE searchindex
			DROP fieldslist,	-- Not used for searching
			DROP location		-- Not in the search field list
		;';
		$this->databaseConnection->query ($query);
		
		# Add status field to enable suppression, and populate the data
		$query = "
			ALTER TABLE searchindex
			ADD COLUMN status ENUM('migrate','suppress','ignore') COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Status' AFTER id,
			ADD INDEX(status)
		;";
		$this->databaseConnection->query ($query);
		$query = "
			UPDATE searchindex
			JOIN catalogue_marc ON searchindex.id = catalogue_marc.id
			SET searchindex.status = catalogue_marc.status
		;";
		$this->databaseConnection->query ($query);
		
		# Update Russian titles to use Cyrillic version
		#!# This probably does not catch all edge-cases, like italics, but seems to be 'good enough', with 26,319 matches
		$query = "
			UPDATE searchindex
			JOIN transliterations ON
				    searchindex.id = transliterations.recordId
				AND searchindex.title LIKE CONCAT('%@', transliterations.title_latin, '%')	-- Compare against start, rather than equals match, to deal with items with square brackets
			SET searchindex.title = REPLACE(searchindex.title, CONCAT('@', transliterations.title_latin), CONCAT('@', transliterations.title));
		;";
		$this->databaseConnection->query ($query);
		
		# Clone the XML table used for creating the MARC record dynamically, first dropping any existing table from a previous import
		$this->databaseConnection->query ('DROP TABLE IF EXISTS catalogue_xml_searchstable;');
		$this->databaseConnection->query ('CREATE TABLE catalogue_xml_searchstable LIKE catalogue_xml;');
		$this->databaseConnection->query ('INSERT catalogue_xml_searchstable SELECT * FROM catalogue_xml;');
		
		# Remove private data from the eventual MARC record in the search pages, by renaming the relevant fields in the stable XML table (used to generate MARC records on-the-fly) by prefixing an underscore (which is a valid XML name)
		$fields = array (
			'priv',		// Private note
			'pr',		// Price
		);
		$replacements = array ();
		foreach ($fields as $field) {
			$this->databaseConnection->query ("UPDATE catalogue_xml_searchstable SET xml = REPLACE(xml, '<{$field}>', '<_{$field}>');");
			$this->databaseConnection->query ("UPDATE catalogue_xml_searchstable SET xml = REPLACE(xml, '</{$field}>', '</_{$field}>');");
		}
	}
	
	
	# Function to create the processed data table
	private function createProcessedTable ()
	{
		# Log start
		$this->logger ('Starting ' . __METHOD__);
		
		# Now create the processed table, which will be used for amending the raw data, e.g. to convert special characters and upgrade the Cyrillic transliterations
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.catalogue_processed;";
		$this->databaseConnection->execute ($sql);
		$sql = "CREATE TABLE catalogue_processed LIKE catalogue_rawdata;";
		$this->databaseConnection->execute ($sql);
		$sql = "INSERT INTO catalogue_processed SELECT * FROM catalogue_rawdata;";
		$this->databaseConnection->execute ($sql);
		
		# Set a flag for each shard which show its position, either top (i.e. the main part of the record) or bottom (in the *in block)
		$sql = "ALTER TABLE catalogue_processed ADD topLevel INT(1) NOT NULL DEFAULT 1 COMMENT 'Whether the shard is within the top level part of the record' AFTER value;";
		$this->databaseConnection->execute ($sql);
		$sql = "UPDATE catalogue_processed
			LEFT JOIN (
				-- Get the *in or *j switchover point within each record that has an *in / *j; records without will be untouched
				SELECT
					recordId,line
				FROM catalogue_rawdata
				WHERE field IN('in', 'j')
			) AS lineIds ON catalogue_processed.recordId = lineIds.recordId
			SET topLevel = 0
			WHERE
				catalogue_processed.line > lineIds.line		/* I.e. set to false after the *in or *j marker, leaving 1 for all other cases */
		;";
		$this->databaseConnection->execute ($sql);
		
		# Add a field to store the XPath of the field (e.g. /doc/ts) and the same but with a numeric specifier (e.g. /doc/ts[1])
		$sql = "ALTER TABLE catalogue_processed
			ADD xPath          VARCHAR(255) NULL DEFAULT NULL COMMENT 'XPath to the field (path only)'       AFTER topLevel,
			ADD xPathWithIndex VARCHAR(255) NULL DEFAULT NULL COMMENT 'XPath to the field (path with index)' AFTER xPath
		;";
		$this->databaseConnection->execute ($sql);
		
		# Add a field to store the original pre-transliteration value
		$sql = "ALTER TABLE catalogue_processed ADD preTransliterationUpgradeValue TEXT NULL DEFAULT NULL COMMENT 'Value before transliteration changes' AFTER xPathWithIndex;";
		$this->databaseConnection->execute ($sql);
		
		# Add a field to contain the record language (first language); note that an *in or *j may also contain a *lang
		$sql = "ALTER TABLE catalogue_processed ADD recordLanguage VARCHAR(255) NULL DEFAULT 'English' COMMENT 'Record language (first language)' AFTER preTransliterationUpgradeValue;";
		$this->databaseConnection->execute ($sql);
		
		# Set the record language (first language) for each shard
		$sql = "UPDATE catalogue_processed
			LEFT JOIN (
			    SELECT
			        recordId,
			        SUBSTRING_INDEX(languages, ',', 1) AS firstLanguage
			    FROM (
			        SELECT
			            recordId,
			            GROUP_CONCAT(value) AS languages
			        FROM catalogue_rawdata
			        WHERE field = 'lang'
			        GROUP BY recordId
					) AS recordLanguages
				) AS firstLanguages
				ON firstLanguages.recordId = catalogue_processed.recordId
			SET catalogue_processed.recordLanguage = firstLanguage
			WHERE firstLanguage IS NOT NULL		/* I.e. don't overwrite the default English where no *lang specified */
		;";
		$this->databaseConnection->execute ($sql);
		$this->databaseConnection->execute ("UPDATE catalogue_processed SET recordLanguage = REPLACE(recordLanguage, 'n^t', '" . chr(0xc3).chr(0xb1) . "');");	// Fix up special characters coming from catalogue_rawdata: In^tupiaq, In^tupiat
		$this->databaseConnection->execute ("UPDATE catalogue_processed SET recordLanguage = REPLACE(recordLanguage, 'a^a', '" . chr(0xc3).chr(0xa1) . "');");	// Fix up special characters coming from catalogue_rawdata: Sa^ami
	}
	
	
	# Function to create the statistics table
	private function createStatisticsTable ($tableComment)
	{
		# Log start
		$this->logger ('Starting ' . __METHOD__);
		
		# Now create the statistics table; this is pre-compiled for performance
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.statistics;";
		$this->databaseConnection->execute ($sql);
		$sql = "
			CREATE TABLE `statistics` (
			  `id` int(1) NOT NULL COMMENT 'Identifier (always 1)',
			  `totalRecords` int(11) NOT NULL COMMENT 'Total records',
			  `totalDataEntries` int(11) NOT NULL COMMENT 'Total data entries',
			  `averageDataEntriesPerRecord` int(3) NOT NULL COMMENT 'Average data entries per record',
			  `highestNumberedRecord` int(11) NOT NULL COMMENT 'Highest-numbered record',
			  `exportDate` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Export date',
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Summary statistics';";
		$this->databaseConnection->execute ($sql);
		$sql = "
			INSERT INTO statistics VALUES (
				1,
				(SELECT COUNT(DISTINCT(recordId)) FROM catalogue_rawdata),
				(SELECT COUNT(*) FROM catalogue_rawdata),
				(SELECT ROUND( (SELECT COUNT(*) FROM catalogue_rawdata) / (SELECT COUNT(DISTINCT(recordId)) FROM catalogue_rawdata) )),
				(SELECT MAX(recordId) FROM catalogue_rawdata),
				'{$tableComment}'
			)
		;";
		$this->databaseConnection->execute ($sql);
	}
	
	
	# Run parsing of special escape characters (see section 3.4 of the Library Manual); see http://lists.mysql.com/mysql/193376 for notes on backslash handling in MySQL
	private function specialCharacterParsing ()
	{
		# Log start
		$this->logger ('Starting ' . __METHOD__);
		
		# Start a list of queries
		$queries = array ();
		
		# Define backslash characters for clarity
		# http://lists.mysql.com/mysql/193376 : "LIKE or REGEXP pattern is parsed twice, while the REPLACE pattern is parsed once"
		$literalBackslash	= '\\';										// PHP representation of one literal backslash
		$mysqlBacklash		= $literalBackslash . $literalBackslash;	// http://lists.mysql.com/mysql/193376 shows that a MySQL backlash is always written as \\
		$replaceBackslash	= $mysqlBacklash;							// http://lists.mysql.com/mysql/193376 shows that REPLACE expects a single MySQL backslash
		$likeBackslash		= $mysqlBacklash /* . $mysqlBacklash # seems to work only with one */;			// http://lists.mysql.com/mysql/193376 shows that LIKE expects a single MySQL backslash
		$regexpBackslash	= $mysqlBacklash . $mysqlBacklash;			// http://lists.mysql.com/mysql/193376
		
		# Undo Muscat escaped asterisks @*, e.g. /records/19682/ and many *ks / *location values; this is basically an SQL version of unescapeMuscatAsterisks ()
		$queries[] = "UPDATE catalogue_processed SET value = REPLACE(value,'@*','*');";
		
		# Italics, e.g. /records/205430/
		# "In order to italicise a Latin name in the middle of a line of Roman text, prefix the words to be italicised by '\v' and end the words with '\n'"
		$queries[] = "UPDATE catalogue_processed SET value = REPLACE(value,'{$replaceBackslash}v','<em>');";
		$queries[] = "UPDATE catalogue_processed SET value = REPLACE(value,'{$replaceBackslash}n','</em>');";	// \n does not mean anything special in REPLACE()
		# Also convert \V and \N similarly
		$queries[] = "UPDATE catalogue_processed SET value = REPLACE(value,'{$replaceBackslash}V','<em>');";
		$queries[] = "UPDATE catalogue_processed SET value = REPLACE(value,'{$replaceBackslash}N','</em>');";	// \n does not mean anything special in REPLACE()
		
		# Correct the use of }o{ which has mistakenly been used to mean \gdeg, except for V}o{ which is a Ordinal indicator: https://en.wikipedia.org/wiki/Ordinal_indicator
		$queries[] = "UPDATE catalogue_processed SET value = REPLACE(value,'}o{','{$replaceBackslash}gdeg') WHERE value NOT LIKE '%V}o{%';";	// NB Have manually checked that record with V}o{ has no other use of }/{ characters
		
		# Diacritics (query takes 135 seconds)
		$queries[] = "UPDATE catalogue_processed SET value = " . $this->databaseConnection->replaceSql ($this->diacriticsTable, 'value', "'") . ';';
		
		# Greek characters; see also report_specialcharscase which enables the librarians to normalise \gGamMA to \gGamma
		# Assumes this catalogue rule has been eliminated: "When '\g' is followed by a word, the case of the first letter is significant. The remaining letters can be in either upper or lower case however. Thus '\gGamma' is a capital gamma, and the forms '\gGAMMA', '\gGAmma' etc. will also represent capital gamma."
		$greekLetters = $this->greekLetters ();
		$greekLettersReplacements = array ();
		foreach ($greekLetters as $letterCaseSensitive => $unicodeCharacter) {
			$greekLettersReplacements["{$replaceBackslash}g{$letterCaseSensitive}"] = $unicodeCharacter;
		}
		$queries[] = "UPDATE catalogue_processed SET value = " . $this->databaseConnection->replaceSql ($greekLettersReplacements, 'value', "'") . ';';
		
		# Quantity/mathematical symbols
		$specialCharacters = array (
			'deg'							=> chr(0xC2).chr(0xB0),
			'min'							=> chr(0xE2).chr(0x80).chr(0xB2),
			'sec'							=> chr(0xE2).chr(0x80).chr(0xB3),
			'<-'							=> chr(0xE2).chr(0x86).chr(0x90),		// http://www.fileformat.info/info/unicode/char/2190/index.htm
			'->'							=> chr(0xE2).chr(0x86).chr(0x92),		// http://www.fileformat.info/info/unicode/char/2192/index.htm
			'+ or -'						=> chr(0xC2).chr(0xB1),					// http://www.fileformat.info/info/unicode/char/00b1/index.htm
			'>='							=> chr(0xE2).chr(0x89).chr(0xA5),		// http://www.fileformat.info/info/unicode/char/2265/index.htm
			'<='							=> chr(0xE2).chr(0x89).chr(0xA4),		// http://www.fileformat.info/info/unicode/char/2264/index.htm
			' '								=> chr(0xC2).chr(0xA0),					// http://www.fileformat.info/info/unicode/char/00a0/index.htm ; note that pasting from a browser gives a normal space, but this query will confirm a real "NO-BREAK SPACE" character: `SELECT * FROM catalogue_processed WHERE recordId = 10064 AND value LIKE BINARY CONCAT('%1900-1903',UNHEX('C2A0'),'gg%');`
			'micron'						=> chr(0xC2).chr(0xB5),					// http://www.fileformat.info/info/unicode/char/00b5/index.htm ; this appears to be more correct than "'GREEK SMALL LETTER MU' (U+03BC)" at http://www.fileformat.info/info/unicode/char/03BC/index.htm
			'epsilo' . chr(0xc5).chr(0x84)	=> chr(0xCE).chr(0xAD),					// http://www.fileformat.info/info/unicode/char/03ad/index.htm ; this is for /records/166336/ which has \gepsilon^a, and n^a is already converted to Unicode in diacriticsTable called just above
		);
		$specialCharactersReplacements = array ();
		foreach ($specialCharacters as $letter => $unicodeCharacter) {
			$specialCharactersReplacements["{$replaceBackslash}g{$letter}"] = $unicodeCharacter;
		}
		$queries[] = "UPDATE catalogue_processed SET value = " . $this->databaseConnection->replaceSql ($specialCharactersReplacements, 'value', "'") . ';';
		
		# Subscripts and superscripts, e.g. "H{2}SO{4} will print out as H2SO4 with both 2 and 4 as subscripts"
		$subscriptsSuperscriptsReplacements = $this->getSubscriptsSuperscriptsReplacementsDefinition ();
		$subscriptsSuperscriptsReplacementsChunks = array_chunk ($subscriptsSuperscriptsReplacements, $chunksOf = 25, true);
		foreach ($subscriptsSuperscriptsReplacementsChunks as $subscriptsSuperscriptsReplacementsChunk) {
			$queries[] = "UPDATE catalogue_processed SET value = " . $this->databaseConnection->replaceSql ($subscriptsSuperscriptsReplacementsChunk, 'value', "'") . ';';
		}
		
		// file_put_contents ("{$_SERVER['DOCUMENT_ROOT']}{$this->baseUrl}/debug-muscat.wri", print_r ($queries, true));
		// application::dumpData ($queries);
		// die;
		
		# Run each query
		foreach ($queries as $query) {
			$result = $this->databaseConnection->query ($query);
			// application::dumpData ($this->databaseConnection->error ());
		}
	}
	
	
	# Function to create a key/value replacement pairs for subscripts {...} and superscripts }...{
	private function getSubscriptsSuperscriptsReplacementsDefinition ()
	{
		/*
		$queries[] = "UPDATE catalogue_processed SET value = REPLACE(value,'}e{',CHAR(0xE284AF USING utf8)) WHERE `value` LIKE '%}e{%';";	// Natural exponent U+212F
		
		# Subscript: "Subscripts are entered by prefixing the number to be dropped by { and typing } after the number e.g. H{2}SO{4} will print out as H2SO4 with both 2 and 4 as subscripts."; http://en.wikipedia.org/wiki/Unicode_subscripts_and_superscripts#Superscripts_and_subscripts_block and http://www.decodeunicode.org/en/u+2072/properties
		# Superscript: "For superscripts, the procedure is reversed e.g. 4840 m}2{ will print out as 4840 m with 2 as superscript."
		// Find using: SELECT * FROM catalogue_processed WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(value,'{1}',''),'{2}',''),'{3}',''),'{4}',''),'{5}',''),'{6}',''),'{7}',''),'{8}','') REGEXP ('}([^{ ]+){') AND value NOT REGEXP ('}(1|2|-2|2-|2+|3|-3|4|5|6|-6|-8|10|11|13|14|15|18|21|22|26|32|34|36|39|41|53|85|90|137|129|210|230|226|228|234|238|241|.|~|-|\\+|a|c|B|E|o|p|ere|er|re|e\\^gre|ieme|r|me|ne|st|-1|\\\\vo\\\\n){');
		// Find using: SELECT * FROM catalogue_processed WHERE value REGEXP ('{[0-9n=()+-]+}') AND `value` NOT REGEXP ('{(0|1|2|3|4|6|7|8|10|11|12|14|15|17|18|21|22|23|25|30|31|35|37|43|45|50|60|86|90|137|200|210|238|239|240|241|500|700|0001|1010|1120|2021)}');
		for ($i = 0; $i <= 9; $i++) {
			$queries[] = "UPDATE catalogue_processed SET value = REPLACE(value,'{{$i}}',CHAR(0xE2828{$i} USING utf8)) WHERE `value` LIKE '%{{$i}}%';";	// Subscript
			$queries[] = "UPDATE catalogue_processed SET value = REPLACE(value,'}{$i}{',CHAR(0xE281B{$i} USING utf8)) WHERE `value` LIKE '%}{$i}{%';";	// Superscript
		}
		*/
		
		# Determine the unicode code points for characters 1...9+- for both subscripts and superscripts; see: http://en.wikipedia.org/wiki/Unicode_subscripts_and_superscripts#Superscripts_and_subscripts_block
		
		# Subscripts 0-9 and then special characters
		$unicodeSubscripts = array ();
		$oneDigitNumbers = range (0, 9);
		foreach ($oneDigitNumbers as $oneDigitNumber) {
			$unicodeSubscripts[$oneDigitNumber] = chr(0xE2).chr(0x82).chr(0x80 + $oneDigitNumber);
		}
		$unicodeSubscripts['+'] = chr(0xE2).chr(0x82).chr(0x8A);
		$unicodeSubscripts['-'] = chr(0xE2).chr(0x82).chr(0x8B);
		$unicodeSubscripts['='] = chr(0xE2).chr(0x82).chr(0x8C);
		$unicodeSubscripts['('] = chr(0xE2).chr(0x82).chr(0x8D);
		$unicodeSubscripts[')'] = chr(0xE2).chr(0x82).chr(0x8E);
		$unicodeSubscripts['n'] = chr(0xE2).chr(0x82).chr(0x99);	// 'LATIN SUBSCRIPT SMALL LETTER N' (U+2099) - http://www.fileformat.info/info/unicode/char/2099/index.htm
		
		# Letter/number combinations whose component characters can be represented, or partially represented, as real Unicode
		$unicodeSubscripts['++'] = $unicodeSubscripts['+'] . $unicodeSubscripts['+'];
		$unicodeSubscripts['+++'] = $unicodeSubscripts['+'] . $unicodeSubscripts['+'] . $unicodeSubscripts['+'];
		$unicodeSubscripts['2+'] = $unicodeSubscripts[2] . $unicodeSubscripts['+'];
		$unicodeSubscripts['2-'] = $unicodeSubscripts[2] . $unicodeSubscripts['-'];
		$unicodeSubscripts['239,240'] = $unicodeSubscripts[2] . $unicodeSubscripts[8] . $unicodeSubscripts[9] . '<sub>,</sub>' . $unicodeSubscripts['2'] . $unicodeSubscripts['4'] . $unicodeSubscripts['0'];
		$unicodeSubscripts['1c'] = $unicodeSubscripts[1] . '<sub>c</sub>';	// e.g. /records/81582/
		$unicodeSubscripts['2d'] = $unicodeSubscripts[2] . '<sub>d</sub>';
		$unicodeSubscripts['O2'] = '<sub>O</sub>' . $unicodeSubscripts[2];
		$unicodeSubscripts['16:0'] = $unicodeSubscripts[1] . $unicodeSubscripts[6] . '<sub>:</sub>' . $unicodeSubscripts[0];
		$unicodeSubscripts['22:6'] = $unicodeSubscripts[2] . $unicodeSubscripts[2] . '<sub>:</sub>' . $unicodeSubscripts[6];
		$unicodeSubscripts['31:9'] = $unicodeSubscripts[3] . $unicodeSubscripts[1] . '<sub>:</sub>' . $unicodeSubscripts[9];
		$unicodeSubscripts['31:9'] = $unicodeSubscripts[0] . '<sub>.</sub>' . $unicodeSubscripts[1] . $unicodeSubscripts[2] . $unicodeSubscripts[0];
		$unicodeSubscripts['10T0'] = $unicodeSubscripts[1] . $unicodeSubscripts[0] . '<sub>T</sub>' . $unicodeSubscripts[0];
		$unicodeSubscripts['14-15'] = $unicodeSubscripts[1] . $unicodeSubscripts[4] . $unicodeSubscripts['-'] . $unicodeSubscripts[1] . $unicodeSubscripts[5];
		$unicodeSubscripts['16-17'] = $unicodeSubscripts[1] . $unicodeSubscripts[6] . $unicodeSubscripts['-'] . $unicodeSubscripts[1] . $unicodeSubscripts[7];
		$unicodeSubscripts['20-22'] = $unicodeSubscripts[2] . $unicodeSubscripts[0] . $unicodeSubscripts['-'] . $unicodeSubscripts[2] . $unicodeSubscripts[2];
		$unicodeSubscripts['1/2'] = $unicodeSubscripts[1] . '<sub>/</sub>' . $unicodeSubscripts[2];
		$unicodeSubscripts['GISP2'] = '<sub>GISP</sub>' . $unicodeSubscripts[2];
		$unicodeSubscripts['s=0'] = '<sub>s=</sub>' . $unicodeSubscripts[0];
		$unicodeSubscripts['1g'] = $unicodeSubscripts[1] . '<sub>g</sub>';
		$unicodeSubscripts['0.120'] = $unicodeSubscripts[0] . '<sub>.</sub>' . $unicodeSubscripts[1] . $unicodeSubscripts[2] . $unicodeSubscripts[0];
		$unicodeSubscripts['H+'] = '<sub>H</sub>' . $unicodeSubscripts['+'];
		$unicodeSubscripts['Na+'] = '<sub>Na</sub>' . $unicodeSubscripts['+'];
		$unicodeSubscripts['8-x'] = $unicodeSubscripts[8] . $unicodeSubscripts['-'] . '<sub>x</sub>';
		$unicodeSubscripts['18-x'] = $unicodeSubscripts[1] . $unicodeSubscripts[8] . $unicodeSubscripts['-'] . '<sub>x</sub>';
		$unicodeSubscripts['2+x'] = $unicodeSubscripts[2] . $unicodeSubscripts['+'] . '<sub>x</sub>';
		$unicodeSubscripts['2<em>n</em>+1'] = $unicodeSubscripts[2] . '<em>' . $unicodeSubscripts['n'] . '</em>' . $unicodeSubscripts['+'] . $unicodeSubscripts[1];	// /records/129835/
		$unicodeSubscripts['CO3'] = '<sub>CO</sub>' . $unicodeSubscripts[3];	// /records/127819/
		
		# Subscripts not representable as real Unicode codepoints, e.g. shown as {h}, represented as HTML
		$subscriptsNonUnicodeable = array ('a', 'A', 'adv', 'an', 'apex', 'A' . chr(0xCE).chr(0xA3), 'b', 'B', 'c', 'C', 'CO', 'd', 'D', 'DN', 'DR', 'dry', 'e', 'E', 'eff', 'eq', 'ex', 'f', 'g', 'h', 'H', 'hfa', 'HOMA', 'i', 'I', 'II', 'IIIC', 'ice', 'Ic', 'IC', 'inorg', 'Jan', 'k', 'l', 'L', 'lip', 'm', 'max', 'MAX', 'min', 'Nd', 'o', 'org', 'p', 'Pb', 'p/c', 'PAR', 'POC', 'q', 'Q', 'r', 'R', 'RC', 'Re', 'rs', 's', 'S', 'sal', 'sant', 'sas', 'SL', 'ST', 'St', 'SW', 't', 'T', 'u', 'v', 'VGT', 'VI', 'w', 'W', 'w.e', 'x', 'X', 'xs', 'y', 'z', 'Z', chr(0xCE).chr(0xB4), chr(0xCE).chr(0xB6), chr(0xCE).chr(0xB8), chr(0xCE).chr(0x94) . '<em>t</em>' /* delta, for /records/78099/ */ , 'f,T=O', chr(0xCE).chr(0xB8), chr(0xCE).chr(0xB7), );	$this->databaseConnection->query ("INSERT INTO catalogue_processed (id, recordId, line, field, value) VALUES ('9189:45', 9189, 45, 'notes', ''), ('9189:46', 9189, 46, 'note', 'Have you not heard that the bird is the word?');");
		foreach ($subscriptsNonUnicodeable as $subscriptNonUnicodeable) {
			$unicodeSubscripts[$subscriptNonUnicodeable] = '<sub>' . $subscriptNonUnicodeable . '</sub>';	// HTML tags will be stripped in final record
		}
		
		# Superscripts: more awkward than subscripts as code points include three ASCII-position characters; see: http://en.wikipedia.org/wiki/Unicode_subscripts_and_superscripts#Superscripts_and_subscripts_block
		$unicodeSuperscripts = array ();
		foreach ($oneDigitNumbers as $oneDigitNumber) {
			switch ($oneDigitNumber) {
				case 1:
					$unicodeSuperscripts[$oneDigitNumber] = chr(0xC2).chr(0xB9);
					break;
				case 2:
				case 3:
					$unicodeSuperscripts[$oneDigitNumber] = chr(0xC2).chr(0xB0 + $oneDigitNumber);
					break;
				default:
					$unicodeSuperscripts[$oneDigitNumber] = chr(0xE2).chr(0x81).chr(0xB0 + $oneDigitNumber);
			}
		}
		$unicodeSuperscripts['+'] = chr(0xE2).chr(0x81).chr(0xBA);
		$unicodeSuperscripts['-'] = chr(0xE2).chr(0x81).chr(0xBB);
		$unicodeSuperscripts['='] = chr(0xE2).chr(0x81).chr(0xBC);
		$unicodeSuperscripts['('] = chr(0xE2).chr(0x81).chr(0xBD);
		$unicodeSuperscripts[')'] = chr(0xE2).chr(0x81).chr(0xBE);
		$unicodeSuperscripts['n'] = chr(0xE2).chr(0x81).chr(0xBF);
		$unicodeSuperscripts['.'] = chr(0xC2).chr(0xB7);	// Middle dot
		$unicodeSuperscripts['TM'] = chr(0xE2).chr(0x84).chr(0xA2);	// Trademark
		
		# Letter/number combinations whose component characters can be represented as real Unicode
		$unicodeSuperscripts['2+'] = $unicodeSuperscripts[2] . $unicodeSuperscripts['+'];
		$unicodeSuperscripts['2-'] = $unicodeSuperscripts[2] . $unicodeSuperscripts['-'];
		$unicodeSuperscripts['3+'] = $unicodeSuperscripts[3] . $unicodeSuperscripts['+'];
		$unicodeSuperscripts['3-'] = $unicodeSuperscripts[3] . $unicodeSuperscripts['-'];
		$unicodeSuperscripts['-n'] = $unicodeSuperscripts['-'] . $unicodeSuperscripts['n'];
		$unicodeSuperscripts['++'] = $unicodeSuperscripts['+'] . $unicodeSuperscripts['+'];	// e.g. /records/79712/
		$unicodeSuperscripts['+++'] = $unicodeSuperscripts['+'] . $unicodeSuperscripts['+'] . $unicodeSuperscripts['+'];	// e.g. /records/111029/
		$unicodeSuperscripts['1/3'] = $unicodeSuperscripts[1] . '<sup>/</sup>' . $unicodeSuperscripts[3];	// e.g. /records/169424/
		$unicodeSuperscripts['-1/12'] = $unicodeSuperscripts['-'] . $unicodeSuperscripts[1] . '<sup>/</sup>' . $unicodeSuperscripts[1] . $unicodeSuperscripts[2];	// e.g. /records/120554/
		$unicodeSuperscripts['4.17'] = $unicodeSuperscripts[4] . '<sup>.</sup>' . $unicodeSuperscripts[1] . $unicodeSuperscripts[7];	// e.g. /records/199372/
		$unicodeSuperscripts['238-240'] = $unicodeSuperscripts[2] . $unicodeSuperscripts[3] . $unicodeSuperscripts[8] . $unicodeSuperscripts['-'] . $unicodeSuperscripts[2] . $unicodeSuperscripts[4] . $unicodeSuperscripts[0];	// e.g. /records/212674/
		$unicodeSuperscripts['239+240'] = $unicodeSuperscripts[2] . $unicodeSuperscripts[3] . $unicodeSuperscripts[9] . $unicodeSuperscripts['+'] . $unicodeSuperscripts[2] . $unicodeSuperscripts[4] . $unicodeSuperscripts[0];	// e.g. /records/206908/
		$unicodeSuperscripts['239,240'] = $unicodeSuperscripts[2] . $unicodeSuperscripts[3] . $unicodeSuperscripts[9] . '<sup>,</sup>' . $unicodeSuperscripts[2] . $unicodeSuperscripts[4] . $unicodeSuperscripts[0];	// e.g. /records/167346/
		$unicodeSuperscripts['239.240'] = $unicodeSuperscripts[2] . $unicodeSuperscripts[3] . $unicodeSuperscripts[9] . '<sup>.</sup>' . $unicodeSuperscripts[2] . $unicodeSuperscripts[4] . $unicodeSuperscripts[0];
		$unicodeSuperscripts['-1h-1'] = $unicodeSuperscripts['-'] . $unicodeSuperscripts[1] . '<sup>h</sup>' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];
		$unicodeSuperscripts['2s-1'] = $unicodeSuperscripts[2] . '<sup>s</sup>' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];
		$unicodeSuperscripts['-2s-1'] = $unicodeSuperscripts['-'] . $unicodeSuperscripts[2] . '<sup>s</sup>' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];
		$unicodeSuperscripts['3y-1'] = $unicodeSuperscripts[3] . '<sup>y</sup>' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];
		$unicodeSuperscripts['16:0'] = $unicodeSuperscripts[1] . $unicodeSuperscripts[6] . '<sup>:</sup>' . $unicodeSuperscripts[0];
		$unicodeSuperscripts['22:6'] = $unicodeSuperscripts[2] . $unicodeSuperscripts[2] . '<sup>:</sup>' . $unicodeSuperscripts[6];
		$unicodeSuperscripts['-1.6'] = $unicodeSuperscripts['-'] . $unicodeSuperscripts[1] . '<sup>.</sup>' . $unicodeSuperscripts[6];
		$unicodeSuperscripts['1.2'] = $unicodeSuperscripts[1] . '<sup>.</sup>' . $unicodeSuperscripts[2];
		$unicodeSuperscripts['12-'] = $unicodeSuperscripts[2] . $unicodeSuperscripts[2] . $unicodeSuperscripts['-'];
		$unicodeSuperscripts['8-'] = $unicodeSuperscripts[8] . $unicodeSuperscripts['-'];
		$unicodeSuperscripts['1/2'] = $unicodeSuperscripts[1] . '<sup>/</sup>' . $unicodeSuperscripts[2];
		$unicodeSuperscripts['-0.75'] = $unicodeSuperscripts['-'] . $unicodeSuperscripts[0] . '<sup>.</sup>' . $unicodeSuperscripts[7] . $unicodeSuperscripts[5];
		$unicodeSuperscripts['110m'] = $unicodeSuperscripts[1] . $unicodeSuperscripts[1] . $unicodeSuperscripts[1] . $unicodeSuperscripts[0] . '<sup>m</sup>';
		$unicodeSuperscripts['1.095'] = $unicodeSuperscripts[1] . '<sup>.</sup>' . $unicodeSuperscripts[0] . $unicodeSuperscripts[9] . $unicodeSuperscripts[5];
		$unicodeSuperscripts['-0.0131 ' . chr(0xcf).chr(0x81) . 's'] = $unicodeSuperscripts['-'] . $unicodeSuperscripts[0] . '<sup>.</sup>' . $unicodeSuperscripts[0] . $unicodeSuperscripts[1] . $unicodeSuperscripts[3] . $unicodeSuperscripts[1] . '<sup> ' . chr(0xcf).chr(0x81) . 's</sup>';	// /records/193112/
		
		# Ordinal indicators; NB only a and o have proper Unicode characters: https://en.wikipedia.org/wiki/Ordinal_indicator#Usage
		$unicodeSuperscripts['a'] = chr(0xC2).chr(0xAA);	// FEMININE ORDINAL INDICATOR (U+00AA); see: http://www.fileformat.info/info/unicode/char/00aa/index.htm
		$unicodeSuperscripts['o'] = chr(0xC2).chr(0xBA);	// MASCULINE ORDINAL INDICATOR (U+00BA); see: http://www.fileformat.info/info/unicode/char/00ba/index.htm
		
		# Superscripts with no Unicode codepoints, represented as HTML
		$superscriptsNonUnicodeable = array ('b', 'B', 'c', 'dry', 'DN', 'e', 'E', 'eme', 'er', chr(0xC3).chr(0xA8) . 're' /* ère */, 'ieme', 'l', 'max', 'me', 'ne', 'p', 'r', 'R', 're', 'st', 't', 'T', 'Th', 'th', 'tot', '~', ',', chr(0xC3).chr(0xB2), );		// E.g. shown as }e{
		foreach ($superscriptsNonUnicodeable as $superscriptNonUnicodeable) {
			$unicodeSuperscripts[$superscriptNonUnicodeable] = '<sup>' . $superscriptNonUnicodeable . '</sup>';	// HTML tags will be stripped in final record
		}
		
		# Define subscripts and superscripts known to be in the data, e.g. {+}, {-}, }+{, }-{, etc.; all characters in these listings must have been defined above; the two groupings will be processed in paired order
		$groupings = array (
			array (		// 0-9, +, -, n, etc.
				array_keys ($unicodeSubscripts),
				array_keys ($unicodeSuperscripts)
			),
			array (
				$subscriptsNonUnicodeable,
				$superscriptsNonUnicodeable
			),
			array (
				array (),
				range (10, 99)
			),
			array (
				range (-99, -1),
				range (-99, -1)
			),
			array (
				array ('10', '11', '12', '13', '14', '15', '16', '17', '18', '20', '21', '22', '23', '25', '26', '27', '28', '29', '30', '31', '33', '35', '37', '40', '43', '45', '50', '60', '63', '64', '86', '90', '115', '128', '137', '200', '210', '238', '241', '500', '700', '0001', '1010', '1120', '2021', ),
				array ('103', '118', '125', '127', '129', '134', '137', '143', '144', '181', '187', '188', '204', '206', '207', '210', '222', '226', '228', '230', '231', '232', '234', '235', '238', '239', '240', '241', '548', '552', '990', )
			),
		);
		
		# Assemble key/value pairs of search=>replace, e.g. {+} => +, ordered by grouping
		$replacements = array ();
		foreach ($groupings as $grouping) {
			foreach ($grouping[0] as $subscript) {
				$find = '{' . $subscript . '}';
				$replacements[$find] = strtr ($subscript, $unicodeSubscripts);
			}
			foreach ($grouping[1] as $superscript) {
				$find = '}' . $superscript . '{';
				$replacements[$find] = strtr ($superscript, $unicodeSuperscripts);	// Definition of 0-9 will also catch 10-99
			}
		}
		
		# Add ambiguous groupings arising from the lack of robust reversibility in the original {..} }..{ specification; these take precedence over all the above
		$ambiguousGroupings['}.{H}+{'] = '10' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[2] . 's' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];	// /records/129785/
		$ambiguousGroupings['10}-2{s}-1{'] = '10' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[2] . 's' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];	// /records/129785/
		$ambiguousGroupings['10}-6{S}-1{'] = '10' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[6] . 'S' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];	// /records/136780/
		$ambiguousGroupings['10}-6{-10}-7{s}-1{'] = '10' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[6] . '-10' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[7] . 's' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];	// /records/155417/
		$ambiguousGroupings['m}-2{a}-1{'] = 'm' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[2] . 'a' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];	// /records/167154/
		$ambiguousGroupings['m}-2{d}-1{'] = 'm' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[2] . 'd' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];	// /records/174762/
		$ambiguousGroupings['L}-1{h}-1{'] = 'L' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[2] . 'h' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];	// /records/186659/
		$ambiguousGroupings['m}-2{s}-1{'] = 'm' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[2] . 'g' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];	// /records/188187/
		$ambiguousGroupings['m}2{g}-1{'] = 'm' . $unicodeSuperscripts[2] . 'g' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];	// /records/188222/
		$ambiguousGroupings['m}2{s}-1{'] = 'm' . $unicodeSuperscripts[2] . 's' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];	// /records/189759/
		$ambiguousGroupings['O}+{(}2{P'] = 'O' . $unicodeSuperscripts['+'] . '(' . $unicodeSuperscripts[2] . 'P';	// /records/124061/
		$ambiguousGroupings['km}3{y}-1{'] = 'km' . $unicodeSuperscripts['3'] . 'y' . $unicodeSuperscripts['-'] . $unicodeSuperscripts[1];	// /records/193483/
		$replacements = array_merge ($ambiguousGroupings, $replacements);	// Insert before all others
		
		// application::dumpData ($replacements);
		
		# Return the replacements
		return $replacements;
	}
	
	
	# Lookup table for diacritics
	private function getDiacriticsTable ()
	{
		# Diacritics; see also report_diacritics() and report_diacritics_view(); most are defined at http://www.ssec.wisc.edu/~tomw/java/unicode.html and this is useful: http://illegalargumentexception.blogspot.co.uk/2009/09/java-character-inspector-application.html
		$diacritics = array (
			
			// ^a acute
			'a^a' => chr(0xc3).chr(0xa1),			//  0x00E1
			'c^a' => chr(0xc4).chr(0x87),			//  0x0107
			'e^a' => chr(0xc3).chr(0xa9),			//  0x00E9
			'g^a' => chr(0xc7).chr(0xb5),			//  0x01F5
			'i^a' => chr(0xc3).chr(0xad),			//  0x00ED
			'n^a' => chr(0xc5).chr(0x84),			//  0x0144
			'o^a' => chr(0xc3).chr(0xb3),			//  0x00F3
			'r^a' => chr(0xc5).chr(0x95),			//  0x0155
			's^a' => chr(0xc5).chr(0x9b),			//  0x015B
			'u^a' => chr(0xc3).chr(0xba),			//  0x00FA
			'y^a' => chr(0xc3).chr(0xbd),			//  0x00FD
			'z^a' => chr(0xc5).chr(0xba),			//  0x017A
			'A^a' => chr(0xc3).chr(0x81),			//  0x00C1
			'C^a' => chr(0xc4).chr(0x86),			//  0x0106
			'E^a' => chr(0xc3).chr(0x89),			//  0x00C9
			'I^a' => chr(0xc3).chr(0x8d),			//  0x00CD
			'O^a' => chr(0xc3).chr(0x93),			//  0x00D3
			'S^a' => chr(0xc5).chr(0x9a),			//  0x015A
			'U^a' => chr(0xc3).chr(0x9a),			//  0x00DA
			'Z^a' => chr(0xc5).chr(0xb9),			//  0x0179
			
			// ^g grave
			'a^g' => chr(0xc3).chr(0xa0),			//  0x00E0
			'e^g' => chr(0xc3).chr(0xa8),			//  0x00E8
			'i^g' => chr(0xc3).chr(0xac),			//  0x00EC
			'o^g' => chr(0xc3).chr(0xb2),			//  0x00F2
			'u^g' => chr(0xc3).chr(0xb9),			//  0x00F9
			'A^g' => chr(0xc3).chr(0x80),			//  0x00C0
			'E^g' => chr(0xc3).chr(0x88),			//  0x00C8
			
			// ^c for Polish/Lithuanian Ogonek; see: http://www.twardoch.com/download/polishhowto/ogonek.html ]
			'a^c' => chr(0xc4).chr(0x85),			//  0x0105,	// http://www.fileformat.info/info/unicode/char/0105/index.htm ; see also http://scriptsource.org/cms/scripts/page.php?item_id=character_detail&key=SEQ0061_0327
			'i^c' => chr(0xc4).chr(0xaf),			//  0x012f,	// http://www.fileformat.info/info/unicode/char/12f/index.htm
			
			// ^c for Dene but treated as Ogonek
			'u^c' => chr(0xc5).chr(0xb3),			//  0x0173,	// http://www.fileformat.info/info/unicode/char/0173/index.htm ; see also http://scriptsource.org/cms/scripts/page.php?item_id=character_detail&key=SEQ0075_0327
			
			// ^c cedilla
			'c^c' => chr(0xc3).chr(0xa7),			//  0x00E7
			'e^c' => chr(0xc8).chr(0xa9),			//  0x0229
			'k^c' => chr(0xc4).chr(0xb7),			//  0x0137
			'l^c' => chr(0xc4).chr(0xbc),			//  0x013C
			's^c' => chr(0xc5).chr(0x9f),			//  0x015F
			't^c' => chr(0xc5).chr(0xa3),			//  0x0163
			'C^c' => chr(0xc3).chr(0x87),			//  0x00C7
			
			// ^u umlaut
			'a^u' => chr(0xc3).chr(0xa4),			//  0x00E4
			'e^u' => chr(0xc3).chr(0xab),			//  0x00EB
			'h^u' => chr(0xE1).chr(0xb8).chr(0xa7),	//  0x1E27
			'i^u' => chr(0xc3).chr(0xaf),			//  0x00EF
			'o^u' => chr(0xc3).chr(0xb6),			//  0x00F6
			'u^u' => chr(0xc3).chr(0xbc),			//  0x00FC
			'y^u' => chr(0xc3).chr(0xbf),			//  0x00FF
			'A^u' => chr(0xc3).chr(0x84),			//  0x00C4
			'O^u' => chr(0xc3).chr(0x96),			//  0x00D6
			'U^u' => chr(0xc3).chr(0x9c),			//  0x00DC
			
			// ^m macron (i.e. horizontal line over letter/number)
			'a^m' => chr(0xc4).chr(0x81),			//  0x0101
			'e^m' => chr(0xc4).chr(0x93),			//  0x0113
			'i^m' => chr(0xc4).chr(0xab),			//  0x012B
			'o^m' => chr(0xc5).chr(0x8d),			//  0x014D
			'u^m' => chr(0xc5).chr(0xab),			//  0x016B
			'y^m' => chr(0xc8).chr(0xb3),			//  0x0233
			'A^m' => chr(0xc4).chr(0x80),			//  0x0100
			'O^m' => chr(0xc5).chr(0x8c),			//  0x014C
			'U^m' => chr(0xc5).chr(0xaa),			//  0x016A
			'2^m' => '2' . chr(0xCC).chr(0x85),		//  0x0305; records 119571 and 125394, which have e.g. 112^m1 which should be 1121 where there is a line over the 2; see http://en.wikipedia.org/wiki/Overline#Unicode for Unicode handling
			
			// Standalone overline character used in a formula; only appears in record 149163
			'V^m' => 'V' . chr(0xe2).chr(0x80).chr(0xbe),		//	0x203E, // http://www.fileformat.info/info/unicode/char/203e/index.htm
			
			// ^z '/' (stroke) through letter
			'a^z' => chr(0xe2).chr(0xb1).chr(0xa5),	//  0x2C65,	// http://www.fileformat.info/info/unicode/char/2c65/index.htm
			'd^z' => chr(0xc4).chr(0x91),			//  0x0111,	// http://www.fileformat.info/info/unicode/char/0111/index.htm which does indeed have the stroke not across the middle
			'j^z' => chr(0xc9).chr(0x89),			//  0x0249,	// http://www.fileformat.info/info/unicode/char/0249/index.htm
			'l^z' => chr(0xc5).chr(0x82),			//  0x0142
			'o^z' => chr(0xc3).chr(0xb8),			//  0x00F8
			'L^z' => chr(0xc5).chr(0x81),			//  0x0141
			'O^z' => chr(0xc3).chr(0x98),			//  0x00D8
			
			// ^h for circumflex ('h' stands for 'hat')
			'a^h' => chr(0xc3).chr(0xa2),			//  0x00E2
			'e^h' => chr(0xc3).chr(0xaa),			//  0x00EA
			'g^h' => chr(0xc4).chr(0x9d),			//  0x011D
			'i^h' => chr(0xc3).chr(0xae),			//  0x00EE
			'o^h' => chr(0xc3).chr(0xb4),			//  0x00F4
			'u^h' => chr(0xc3).chr(0xbb),			//  0x00FB
			'y^h' => chr(0xc5).chr(0xb7),			//  0x0177
			'A^h' => chr(0xc3).chr(0x82),			//  0x00C2
			'E^h' => chr(0xc3).chr(0x8a),			//  0x00CA
			'I^h' => chr(0xc3).chr(0x8e),			//  0x00CE
			'U^h' => chr(0xc3).chr(0x9b),			//  0x00DB
			
			// ^v for 'v' (caron) over a letter
			'a^v' => chr(0xc7).chr(0x8e),			//  0x01CE
			'c^v' => chr(0xc4).chr(0x8d),			//  0x010D
			'e^v' => chr(0xc4).chr(0x9b),			//  0x011B
			'g^v' => chr(0xc7).chr(0xa7),			//  0x01E7
			'i^v' => chr(0xc7).chr(0x90),			//  0x01D0
			'n^v' => chr(0xc5).chr(0x88),			//  0x0148
			'o^v' => chr(0xc7).chr(0x92),			//  0x01D2
			'r^v' => chr(0xc5).chr(0x99),			//  0x0159
			's^v' => chr(0xc5).chr(0xa1),			//  0x0161
			'u^v' => chr(0xc7).chr(0x94),			//  0x01D4
			'z^v' => chr(0xc5).chr(0xbe),			//  0x017E
			'C^v' => chr(0xc4).chr(0x8c),			//  0x010C
			'D^v' => chr(0xc4).chr(0x8e),			//  0x010E
			'R^v' => chr(0xc5).chr(0x98),			//  0x0158
			'S^v' => chr(0xc5).chr(0xa0),			//  0x0160
			'Z^v' => chr(0xc5).chr(0xbd),			//  0x017D
			
			// ^o for 'o' (ring) over a letter
			'a^o' => chr(0xc3).chr(0xa5),			//  0x00E5
			'u^o' => chr(0xc5).chr(0xaf),			//  0x016F
			'A^o' => chr(0xc3).chr(0x85),			//  0x00C5
			
			// ^t tilde
			'a^t' => chr(0xc3).chr(0xa3),			//  0x00E3
			'e^t' => chr(0xe1).chr(0xba).chr(0xbd),	//  0x1EBD
			'i^t' => chr(0xc4).chr(0xa9),			//  0x0129
			'n^t' => chr(0xc3).chr(0xb1),			//  0x00F1
			'o^t' => chr(0xc3).chr(0xb5),			//  0x00F5
			'u^t' => chr(0xc5).chr(0xa9),			//  0x0169
			'O^t' => chr(0xc3).chr(0x95),			//  0x00D5
			'U^t' => chr(0xc5).chr(0xa8),			//  0x0168
			' ^t' => ' ~',
		);
		
		# Capitals (have same meaning - data is too extensive to fix up manually)
		$diacritics += array (
			
			// ^A acute (upper-case)
			'A^A' => $diacritics['A^a'],
			'E^A' => $diacritics['E^a'],
			'I^A' => $diacritics['I^a'],
			'O^A' => $diacritics['O^a'],
			'S^A' => $diacritics['S^a'],
			
			// ^G grave (upper-case)
			'A^G' => $diacritics['A^g'],
			
			// ^U umlaut (upper-case)
			'A^U' => $diacritics['A^u'],
			'O^U' => $diacritics['O^u'],
			'U^U' => $diacritics['U^u'],
			
			// ^M macron (i.e. horizontal line over letter) (upper-case)
			'O^M' => $diacritics['O^m'],
			
			// ^Z '/' through letter (upper-case)
			'L^Z' => $diacritics['L^z'],
			'O^Z' => $diacritics['O^z'],
			
			// ^H for circumflex ('h' stands for 'hat') (upper-case)
			'I^H' => $diacritics['I^h'],
			
			// ^V for 'v' over a letter (upper-case)
			'C^V' => $diacritics['C^v'],
			'S^V' => $diacritics['S^v'],
			
			// ^O for 'o' over a letter (upper-case)
			'A^O' => $diacritics['A^o'],
		);
		
		# Return the array
		return $diacritics;
	}
	
	
	# Lookup table for greek letters
	public function greekLetters ()
	{
		# Greek letters \g___; Unicode references from http://www.utf8-chartable.de/unicode-utf8-table.pl?start=896&number=128
		$greekLetters = array (
			'alpha'				=> chr(0xce).chr(0xb1),
			'beta'				=> chr(0xce).chr(0xb2),
			'gamma'				=> chr(0xce).chr(0xb3),
			'delta'				=> chr(0xce).chr(0xb4),
			'epsilon'			=> chr(0xce).chr(0xb5),
			'zeta'				=> chr(0xce).chr(0xb6),
			'eta'				=> chr(0xce).chr(0xb7),
			'theta'				=> chr(0xce).chr(0xb8),
			'iota'				=> chr(0xce).chr(0xb9),
			'kappa'				=> chr(0xce).chr(0xba),
			'lambda'			=> chr(0xce).chr(0xbb),
			'mu'				=> chr(0xce).chr(0xbc),
			'nu'				=> chr(0xce).chr(0xbd),
			'xi'				=> chr(0xce).chr(0xbe),
			'omicron'			=> chr(0xce).chr(0xbf),
			'pi'				=> chr(0xcf).chr(0x80),
			'rho'				=> chr(0xcf).chr(0x81),
			'sigma'				=> chr(0xcf).chr(0x83),
			'tau'				=> chr(0xcf).chr(0x84),
			'upsilon'			=> chr(0xcf).chr(0x85),
			'phi'				=> chr(0xcf).chr(0x86),
			'chi'				=> chr(0xcf).chr(0x87),
			'psi'				=> chr(0xcf).chr(0x88),
			'omega'				=> chr(0xcf).chr(0x89),
			'Alpha'				=> chr(0xce).chr(0x91),
			'Beta'				=> chr(0xce).chr(0x92),
			'Gamma'				=> chr(0xce).chr(0x93),
			'Delta'				=> chr(0xce).chr(0x94),
			'Epsilon'			=> chr(0xce).chr(0x95),
			'Zeta'				=> chr(0xce).chr(0x96),
			'Eta'				=> chr(0xce).chr(0x97),
			'Theta'				=> chr(0xce).chr(0x98),
			'Iota'				=> chr(0xce).chr(0x99),
			'Kappa'				=> chr(0xce).chr(0x9a),
			'Lambda'			=> chr(0xce).chr(0x9b),
			'Mu'				=> chr(0xce).chr(0x9c),
			'Nu'				=> chr(0xce).chr(0x9d),
			'Xi'				=> chr(0xce).chr(0x9e),
			'Omicron'			=> chr(0xce).chr(0x9f),
			'Pi'				=> chr(0xce).chr(0xa0),
			'Rho'				=> chr(0xce).chr(0xa1),
			'Sigma'				=> chr(0xce).chr(0xa3),
			'Tau'				=> chr(0xce).chr(0xa4),
			'Upsilon'			=> chr(0xce).chr(0xa5),
			'Phi'				=> chr(0xce).chr(0xa6),
			'Chi'				=> chr(0xce).chr(0xa7),
			'Psi'				=> chr(0xce).chr(0xa8),
			'Omega'				=> chr(0xce).chr(0xa9),
		);
		
		# Return the array
		return $greekLetters;
	}
	
	
	# Function to fix up data en-masse
	private function massDataFixes ()
	{
		# Log start
		$this->logger ('Starting ' . __METHOD__);
		
		# Remove "pub. " and "pub." from *loc
		$queries[] = "UPDATE `catalogue_processed` SET value = REPLACE(value, 'pub. ', '') WHERE field = 'location';";	// # 304 rows
		$queries[] = "UPDATE `catalogue_processed` SET value = REPLACE(value, 'pub.', '') WHERE field = 'location';";	// # 13927 rows
		
		# Run each query
		foreach ($queries as $query) {
			$result = $this->databaseConnection->query ($query);
			// application::dumpData ($this->databaseConnection->error ());
		}
	}
	
	
	# Function to create (initialise and populate) the transliteration table
	#   Dependencies: catalogue_processed
	/* 
		This is done as follows:
		1) Create a new 'transliterations' table which will hold the variants
		2) Copy relevant processed record shards (top-level titles) into the transliterations table
		3) Create reverse-transliterations from the original BGN/PCGN latin characters to Cyrillic
		4) Do a reverse transliteration check back from Cyrillic to BGN/PCGN and flag whether this failed
		5) Forward-transliterate the newly-generated Cyrillic into Library of Congress transliterations
	*/
	private function createTransliterationsTable ()
	{
		# Log start
		$this->logger ('Starting ' . __METHOD__);
		
		# Create the table
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.transliterations;";
		$this->databaseConnection->execute ($sql);
		$sql = "CREATE TABLE IF NOT EXISTS `transliterations` (
			`id` VARCHAR(10) NOT NULL COMMENT 'Processed shard ID (catalogue_processed.id)',
			`recordId` INT(11) NULL COMMENT 'Record ID',
			`field` VARCHAR(255) NULL COMMENT 'Field',
			`topLevel` INT(1) NULL COMMENT 'Whether the shard is within the top level part of the record',
			`xPath` VARCHAR(255) NULL DEFAULT NULL COMMENT 'XPath to the field (path only)',
			`lpt` VARCHAR(255) NULL COMMENT 'Parallel title languages (*lpt, adjacent in hierarchy)',
			`title_latin` TEXT COLLATE utf8_unicode_ci COMMENT 'Title (latin characters), unmodified from original data',
			`title_latin_tt` TEXT COLLATE utf8_unicode_ci COMMENT '*tt if present',
			`title` TEXT COLLATE utf8_unicode_ci NOT NULL COMMENT 'Reverse-transliterated title',
			`title_spellcheck_html` TEXT COLLATE utf8_unicode_ci NOT NULL COMMENT 'Reverse-transliterated title (spellcheck HTML)',
			`title_forward` TEXT COLLATE utf8_unicode_ci COMMENT 'Forward transliteration from generated Cyrillic (BGN/PCGN)',
			`forwardCheckFailed` INT(1) NULL COMMENT 'Forward check failed?',
			`title_loc` TEXT COLLATE utf8_unicode_ci COMMENT 'Forward transliteration from generated Cyrillic (Library of Congress)',
			`inNameAuthorityList` INT(11) SIGNED NULL DEFAULT NULL COMMENT 'Whether the title value is in the LoC name authority list',
			PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Table of transliterations'
		;";
		$this->databaseConnection->execute ($sql);
		
		# Define supported language
		#!# Need to remove explicit dependency
		$language = 'Russian';
		
		# Populate the transliterations table
		#!# Shouldn't we be transliterating (or at least upgrading from BGN to LoC) cases of e.g. "English = Russian" but where the record is marked as *lang=English, e.g. /records/135449/ ? If so, it may be that checking for recordLanguage is not enough - we should check for *lpt containing 'Russian' also
		$this->logger ('|-- In ' . __METHOD__ . ', populating the transliterations table');
		$literalBackslash = '\\';
		$query = "
			INSERT INTO transliterations (id, recordId, field, topLevel, xPath, title_latin)
				SELECT
					id,
					recordId,
					field,
					topLevel,
					xPath,
					value AS title_latin
				FROM catalogue_processed
				WHERE
					    field IN('" . implode ("', '", $this->transliterationUpgradeFields) . "')
					AND value NOT REGEXP '^{$literalBackslash}{$literalBackslash}[([^{$literalBackslash}]]+){$literalBackslash}{$literalBackslash}]$'		/* Exclude [Titles fully in brackets like this] */
					AND recordLanguage = '{$language}'
				ORDER BY recordId,id
		;";	// 54,715 rows inserted
		$this->databaseConnection->query ($query);
		
		# In the special case of the *t field, add to the shard the parallel title (*lpt) property associated with the top-level *t; this gives 210 updates, which exactly matches 210 results for `SELECT * FROM `catalogue_processed` WHERE `field` LIKE 'lpt' and recordLanguage = 'Russian';`
		$this->logger ('|-- In ' . __METHOD__ . ', adding parallel title properties (top-half title)');
		$query = "
			UPDATE transliterations
			LEFT JOIN catalogue_processed ON transliterations.recordId = catalogue_processed.recordId
			SET lpt = value
			WHERE
				    catalogue_processed.field = 'lpt'
				AND field = 't'
				AND title_latin LIKE '% = %'		-- This clause avoids e.g. both 198010:10, 198010:17 (lines 10 and 17) matching in /records/198010/
		;";
		$this->databaseConnection->query ($query);
		
		# In the special case of the *t field, where the shard is a bottom-half title, use the bottom-half *lpt when present
		$this->logger ('|-- In ' . __METHOD__ . ', adding parallel title properties (bottom-half title)');
		$query = "
			UPDATE transliterations
			INNER JOIN catalogue_processed ON
				    transliterations.recordId = catalogue_processed.recordId
				AND transliterations.field = 't'
				AND catalogue_processed.field = 'lpt'
				AND transliterations.topLevel = 0
				AND catalogue_processed.topLevel = 0
			SET transliterations.lpt = catalogue_processed.value
		;";
		$this->databaseConnection->query ($query);
		
		# In the case of fields in the second half of the record, delete each shard from the scope of transliteration where it has an associated local (*in / *j) language, e.g. where the shard is a bottom-half title, and it is marked separately as a non-relevant language (e.g. Russian record but /art/j/tg/lang = 'English'); e.g. /records/9820/ , /records/27093/ , /records/57745/
		$this->logger ('|-- In ' . __METHOD__ . ', deleting bottom-half shards with an associated non-relevant local language');
		$query = "
			DELETE FROM transliterations
			WHERE
				    topLevel = 0
				AND recordId IN(
					SELECT recordId
					FROM `catalogue_processed`
					WHERE
						    `field` LIKE 'lang'
						AND topLevel = 0
						AND recordLanguage = '{$language}'
						AND `value` != '{$language}'
				)
		;";
		$this->databaseConnection->query ($query);
		
		# In the special case of the *t field, add in *tt (translated title) where that exists
		$this->logger ('|-- In ' . __METHOD__ . ', adding translated titles');
		$query = "
			UPDATE transliterations
			LEFT JOIN catalogue_processed ON transliterations.recordId = catalogue_processed.recordId
			SET title_latin_tt = value
			WHERE
				    catalogue_processed.field = 'tt'
				AND transliterations.field = 't'
				AND topLevel = 1
		;";
		$this->databaseConnection->query ($query);
		
		# In the special case of the *pu field, clear out special tokens
		$this->logger ('|-- In ' . __METHOD__ . ', clearing out special tokens in publisher entry');
		$query = "
			DELETE FROM transliterations
			WHERE
				    field = 'pu'
				AND title_latin IN('[n.pub.]', 'n.pub.', '[n.p.]')
		;";
		$this->databaseConnection->query ($query);
		
		# For the *n1 field, clear out cases consisting of special tokens (e.g. 'Anon.') because no attempt has been made to add protected string support for name authority checking; the records themselves (e.g. /records/6451/ ) are fine as these tokens are defined in the protected strings table so will be protected from transliteration
		$this->logger ('|-- In ' . __METHOD__ . ', clearing out special tokens in name1 entry');
		$query = "
			DELETE FROM transliterations
			WHERE
				    field IN('" . implode ("', '", $this->transliterationNameMatchingFields) . "')
				AND title_latin IN('Anon.')
		;";
		$this->databaseConnection->query ($query);
		
		# Trigger a transliteration run
		$this->logger ('|-- In ' . __METHOD__ . ', running transliteration of entries in the transliterations table');
		$this->transliterateTransliterationsTable ();
		
		# Populate the Library of Congress name authority list and mark the matches (with inNameAuthorityList = -1)
		$this->logger ('|-- In ' . __METHOD__ . ', populating the Library of Congress name authority list');
		$this->populateLocNameAuthorities ();
		
		# Populate the other names data and mark the matches (with inNameAuthorityList = count)
		$this->logger ('|-- In ' . __METHOD__ . ', populating the other names data');
		$this->populateOtherNames ();
		
		# Mark items not matching a name authority as 0 (rather than leaving as NULL)
		$this->logger ('|-- In ' . __METHOD__ . ', marking items not matching a name authority');
		$query = "
			UPDATE transliterations
			SET inNameAuthorityList = -9999
			WHERE
				    transliterations.field IN('" . implode ("', '", $this->transliterationNameMatchingFields) . "')
				AND inNameAuthorityList IS NULL
		;";
		$this->databaseConnection->query ($query);
		
		# Create the ticked names table if it does not yet exist; this is persistent data between imports and should not be cleared
		$this->logger ('|-- In ' . __METHOD__ . ', creating the ticked names table');
		$sql = "
			CREATE TABLE IF NOT EXISTS tickednames (
				id VARCHAR(10) NOT NULL COMMENT 'Processed shard ID (catalogue_processed.id)',
				surname VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Surname',
				results INT(11) NOT NULL COMMENT 'Number of results',
				PRIMARY KEY (id),
				INDEX(surname)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Table of names ticked during manual checking';
		";
		$this->databaseConnection->execute ($sql);
		
		# Mark in the manually-reviewed ('ticked') names data and mark the matches (with inNameAuthorityList = -1000)
		$this->logger ('|-- In ' . __METHOD__ . ', marking the ticked names data');
		$this->markTickedNames ();
	}
	
	
	# Function to run the transliterations in the transliteration table; this never alters title_latin which should be set only in createTransliterationsTable, read from the post- second-pass XML records
	private function transliterateTransliterationsTable ()
	{
		# Obtain the raw values, indexed by shard ID
		$data = $this->databaseConnection->select ($this->settings['database'], 'transliterations', array (), array ('id', 'title_latin', 'lpt'));
		
		# Transliterate the strings (takes around 20 minutes); this has to be done string-by-string because the batcher is not safe for protected strings
		#!# This may now be safely batchable following introduction of word-boundary protection algorithm in b5265809a8dca2a1a161be2fcc26c13c926a0cda
		#!# The same issue about crosstalk in unsafe batching presumably applies to line-by-line conversions, i.e. C (etc.) will get translated later in the same line; need to check on this
		$language = 'Russian';
		$dataTransliterated = array ();
		$cyrillicPreSubstitutions = array ();
		$protectedPartsPreSubstitutions = array ();
		foreach ($data as $id => $entry) {
			$dataTransliterated[$id] = $this->transliteration->transliterateBgnLatinToCyrillic ($entry['title_latin'], $entry['lpt'], $language, $cyrillicPreSubstitutions[$id] /* passed back by reference */, $protectedPartsPreSubstitutions[$id] /* passed back by reference */);
		}
		
		# Define words to add to the dictionary
		$langCode = 'ru_RU';
		$addToDictionary = application::textareaToList ($this->applicationRoot . '/tables/' . "dictionary.{$langCode}.txt", true, true);
		
		# Obtain an HTML string with embedded spellchecking data
		$dataTransliteratedSpellcheckHtml = application::spellcheck ($cyrillicPreSubstitutions, $langCode, $this->transliteration->getProtectedSubstringsRegexp (), $this->databaseConnection, $this->settings['database'], true, $addToDictionary);
		foreach ($dataTransliteratedSpellcheckHtml as $id => $cyrillicPreSubstitution) {
			$dataTransliteratedSpellcheckHtml[$id] = $this->transliteration->reinstateProtectedSubstrings ($cyrillicPreSubstitution, $protectedPartsPreSubstitutions[$id]);
		}
		
		# Do a comparison check by forward-transliterating the generated Cyrillic (takes around 15 seconds)
		$forwardBgnTransliterations = $this->batchTransliterateStrings ($dataTransliterated, 'transliterateCyrillicToBgnLatin');
		
		# Add new Library of Congress (LoC) transliteration from the generated Cyrillic (takes around 1 second)
		$forwardLocTransliterations = $this->batchTransliterateStrings ($dataTransliterated, 'transliterateCyrillicToLocLatin');
		
		# Compile the conversions
		$conversions = array ();
		foreach ($data as $id => $entry) {
			$conversions[$id] = array (
				'title'					=> $dataTransliterated[$id],
				'title_spellcheck_html'	=> $dataTransliteratedSpellcheckHtml[$id],
				'title_forward'			=> $forwardBgnTransliterations[$id],
				'forwardCheckFailed'	=> (strtolower ($entry['title_latin']) != strtolower ($forwardBgnTransliterations[$id]) ? 1 : NULL),	// Case-insensitive comparison pending upstream fix on http://unicode.org/cldr/trac/ticket/9316
				'title_loc'				=> $forwardLocTransliterations[$id],
			);
		}
		
		# Insert the data (takes around 15 seconds)
		$this->databaseConnection->updateMany ($this->settings['database'], 'transliterations', $conversions, $chunking = 5000);
		
		# Signal success
		return true;
	}
	
	
	# Function to batch-transliterate an array strings; this is basically a wrapper which packages the strings list as a TSV which is then transliterated as a single string, then sent back unpacked
	# Datasets involving protected strings must not be batched, i.e. must not use this, because if e.g. line X contains e.g. Roman numeral 'C' and line Y contains 'Chukotke' this will result in replacements like: '<||1267||>hukotke', i.e. cross-talk between the lines
	private function batchTransliterateStrings ($strings, $transliterationFunction)
	{
		# Define supported language
		#!# Need to remove explicit dependency
		$language = 'Russian';
		
		# Compile the strings to a TSV string, tabs never appearing in the original data, so it is safe to use \t as the separator
		$tsv = array ();
		foreach ($strings as $id => $string) {
			$tsv[] = $id . "\t" . $string;
		}
		$tsv = implode ("\n", $tsv);
		
		# Reverse-transliterate the whole file
		$tsvTransliteratedRaw = $this->transliteration->{$transliterationFunction} ($tsv, $language);
		
		# Convert back to key-value pairs
		require_once ('csv.php');
		$tsvTransliteratedRaw = 'id' . "\t" . 'string' . "\n" . $tsvTransliteratedRaw;		// Add header row for csv::tsvToArray()
		$dataTransliterated = csv::tsvToArray ($tsvTransliteratedRaw, true);
		foreach ($dataTransliterated as $id => $subArray) {
			$dataTransliterated[$id] = $subArray['string'];	// Flatten the array
		}
		
		# Return the transliterated data
		return $dataTransliterated;
	}
	
	
	# Function to create XML records
	#   Depencies: catalogue_processed
	# The $pathSeedingOnly is a flag for the first run which is done simply to allocate the catalogue_processed.xPath values, essentially just needing the xml::dropSerialRecordIntoSchema routine which createXmlTable() and its delegate processXmlRecords() has to wrap
	private function createXmlTable ($pathSeedingOnly = false, &$errorsHtml)
	{
		# Log start
		$this->logger ('Starting ' . __METHOD__);
		
		# Clean out the XML table
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.catalogue_xml;";
		$this->databaseConnection->execute ($sql);
		
		# Create the new XML table
		$sql = "
			CREATE TABLE IF NOT EXISTS catalogue_xml (
				id int(11) NOT NULL COMMENT 'Record number',
				xml text COLLATE utf8_unicode_ci COMMENT 'XML representation of Muscat record',
				language VARCHAR(255) NULL COLLATE utf8_unicode_ci COMMENT 'Record language',
				parallelTitleLanguages VARCHAR(255) NULL COLLATE utf8_unicode_ci COMMENT 'Parallel title languages',
			  PRIMARY KEY (id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='XML representation of Muscat records'
		;";
		$this->databaseConnection->execute ($sql);
		
		# Cross-insert the IDs
		$this->logger ('In ' . __METHOD__ . ', cross-inserting the IDs');
		$query = "INSERT INTO catalogue_xml (id) (SELECT DISTINCT(recordId) FROM catalogue_processed);";
		$this->databaseConnection->execute ($query);
		
		# Create the XML for each record
		if (!$this->processXmlRecords ($pathSeedingOnly, $errorsHtml)) {
			return false;
		}
		
		# End if only simple path seeding of catalogue_processed.xPath values is required, as remaining steps just waste CPU
		if ($pathSeedingOnly) {return true;}
		
		# Add the language lookups
		$this->logger ('In ' . __METHOD__ . ', adding the language lookups');
		$query = "UPDATE catalogue_xml SET language = ExtractValue(xml, '/*/tg/lang[1]');";
		$this->databaseConnection->execute ($query);
		
		# Add the parallel title language lookups
		$this->logger ('In ' . __METHOD__ . ', adding the parallel title language lookups');
		$query = "UPDATE catalogue_xml
			LEFT JOIN catalogue_processed ON
				    catalogue_xml.id = catalogue_processed.recordId
				AND field = 'lang'
				AND value LIKE '% = %'
				AND value REGEXP '(" . implode ('|', array_keys ($this->supportedReverseTransliterationLanguages)) . ")'
			SET parallelTitleLanguages = value
		;";
		$this->databaseConnection->execute ($query);
		
		# Add the xPath values to the transliterations table, for the purposes of the filtering in the transliterations report
		$this->logger ('In ' . __METHOD__ . ', adding the xPath values to the transliterations table');
		$query = "
			UPDATE transliterations
			INNER JOIN catalogue_processed ON transliterations.id = catalogue_processed.id
			SET transliterations.xPath = catalogue_processed.xPath
		;";
		$this->databaseConnection->execute ($query);
		
		# Signal success
		return true;
	}
	
	
	# Function to upgrade the shards consisting of transliterated strings to Library of Congress (LoC). This copies back and over the processed table with the new LoC transliterations, saving the pre-transliteration upgrade value
	private function upgradeTransliterationsToLoc ()
	{
		# Log start
		$this->logger ('Starting ' . __METHOD__);
		
		# Upgrade the processed record shards containing transliteration to use the new Library of Congress transliterations, and save the original BGN/PCGN value
		$query = "UPDATE catalogue_processed
			INNER JOIN transliterations ON catalogue_processed.id = transliterations.id
			SET
				preTransliterationUpgradeValue = value,
				value = title_loc
		;";
		$this->databaseConnection->execute ($query);
	}
	
	
	# Function to do the XML record processing, called from within the main XML table creation function; this will process about 1,000 records a second
	private function processXmlRecords ($pathSeedingOnly = false, &$errorsHtml)
	{
		# Log start
		$this->logger ('Starting ' . __METHOD__);
		
		# Get the schema
		$schemaFlattenedXmlWithContainership = $this->getSchema (true);
		
		# Allow large queries for the chunking operation
		$maxQueryLength = (1024 * 1024 * 32);	// i.e. this many MB
		$query = 'SET SESSION max_allowed_packet = ' . $maxQueryLength . ';';
		$this->databaseConnection->execute ($query);
		
		# Create a temporary table for updating xPath and xPathWithIndex fields as a single cross-table update, as updateMany is too slow (due to a large CASE statement in SQL requiring an IN() clause)
		if ($pathSeedingOnly) {
			$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.catalogue_processed_xpaths_temp;";
			$this->databaseConnection->execute ($sql);
			$sql = "CREATE TABLE IF NOT EXISTS `catalogue_processed_xpaths_temp` (
				id VARCHAR(10) NOT NULL COMMENT 'Shard ID',
				xPath          VARCHAR(255) NULL DEFAULT NULL COMMENT 'XPath to the field (path only)',
				xPathWithIndex VARCHAR(255) NULL DEFAULT NULL COMMENT 'XPath to the field (path with index)',
				PRIMARY KEY (id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Temporary table of xPaths for joining'
			;";
			$this->databaseConnection->execute ($sql);
		}
		
		# Process the records in chunks
		$chunksOf = 500;	// Change max_allowed_packet above if necessary; records are about 1k on average
		$this->logger ("Dropping serial record into schema, in chunks of {$chunksOf} records");
		$i = 0;
		while (true) {	// Until the break
			$i++;
			
			# Get the next chunk of record IDs to update, until all are done
			$query = "SELECT id FROM catalogue_xml WHERE xml IS NULL LIMIT {$chunksOf};";
			if (!$ids = $this->databaseConnection->getPairs ($query)) {break;}
			
			# Get the records for this chunk, using the processed data (as that includes character conversions)
			$records = $this->getRecords ($ids, 'processed');
			
			# Arrange as a set of inserts
			$inserts = array ();
			$processedRecordXPaths = array ();
			foreach ($records as $recordId => $record) {
				$xml = xml::dropSerialRecordIntoSchema ($schemaFlattenedXmlWithContainership, $record, $xPathMatches, $xPathMatchesWithIndex, $errorHtml, $debugString);
				if ($errorHtml) {
					$html  = "<p class=\"warning\">Record <a href=\"{$this->baseUrl}/records/{$recordId}/\">{$recordId}</a> could not be converted to XML:</p>";
					$html .= "\n" . $errorHtml;
					$html .= "\n<div class=\"graybox\">\n<h3>Crashed record:</h3>" . "\n<pre>" . htmlspecialchars ($xml) . "\n</pre>\n</div>";
					$html .= "\n<div class=\"graybox\">\n<h3>Stack debug:</h3>" . nl2br ($debugString) . "\n</div>";
					// $html .= "\n<div class=\"graybox\">\n<h3>Target schema:</h3>" . application::dumpData ($schemaFlattenedXmlWithContainership, false, true) . "\n</div>";
					$errorsHtml .= $html;
					$xml = "<q0>{$recordId}</q0>";
					return false;
				}
				
				# Register the XML record insert
				$inserts[$recordId] = array (
					'id'	=> $recordId,
					'xml'	=> $xml,
				);
				
				# Register the XPath arrays, to be added to the processed table
				if ($pathSeedingOnly) {
					foreach ($xPathMatches as $line => $xPath) {
						$processedRecordXPaths[] = array (
							'id'				=> $recordId . ':' . $line,		// I.e. shard ID, e.g. "1000:0",
							'xPath'				=> $xPath,
							'xPathWithIndex'	=> $xPathMatchesWithIndex[$line],	// $xPathMatchesWithIndex use the same index by line, so safe to do in in the $xPathMatches loop
						);
					}
				}
			}
			
			# Update these records
			if (!$this->databaseConnection->replaceMany ($this->settings['database'], 'catalogue_xml', $inserts)) {
				$html  = "<p class=\"warning\">Error generating XML, stopping at batch ({$recordId}):</p>";
				$html .= application::dumpData ($this->databaseConnection->error (), false, true);
				$errorsHtml .= $html;
				return false;
			}
			
			# If seeding the catalogue_processed.xPath values, add to the temporary table to register the XPath values; this far faster than using updateMany (which takes an additional 6 hours in total)
			if ($pathSeedingOnly) {
				if (!$this->databaseConnection->insertMany ($this->settings['database'], 'catalogue_processed_xpaths_temp', $processedRecordXPaths)) {
					$html  = "<p class=\"warning\">Error updating processed records to add XPath values, stopping at batch ({$recordId}):</p>";
					$html .= application::dumpData ($this->databaseConnection->error (), false, true);
					$errorsHtml .= $html;
					return false;
				}
			}
		}
		
		# If seeding the catalogue_processed.xPath values, update the processed table to register the XPath values
		if ($pathSeedingOnly) {
			$this->logger ('Setting the xPath values');
			$sql = "UPDATE catalogue_processed
				INNER JOIN catalogue_processed_xpaths_temp on catalogue_processed.id = catalogue_processed_xpaths_temp.id
				SET
					catalogue_processed.xPath          = catalogue_processed_xpaths_temp.xPath,
					catalogue_processed.xPathWithIndex = catalogue_processed_xpaths_temp.xPathWithIndex
			;";
			$this->databaseConnection->execute ($sql);	// 4.5 minutes
		}
		
		# Take down the temporary table
		if ($pathSeedingOnly) {
			$sql = "DROP TABLE {$this->settings['database']}.catalogue_processed_xpaths_temp;";
			$this->databaseConnection->execute ($sql);
		}
		
		# Signal success
		return true;
	}
	
	
	# Function to replace location=Periodical in the processed records with the real, looked-up values; dependencies: catalogue_processed with xPath field populated
	# NB This matching is done before the transliteration phase, so that the /art/j/tg/t matches its parent (e.g. /records/167320/ joins to its parent /records/33585/ ) and then AFTER that it gets upgraded
	#!# There is still the problem that the target name itself does not get upgraded
	private function processPeriodicalLocations (&$errorsHtml)
	{
		# Log start
		$this->logger ('Starting ' . __METHOD__);
		
		# Assign XPaths to catalogue_processed; this unfortunate dependency means that the XML processing has to be run twice
		$this->createXmlTable ($pathSeedingOnly = true, $errorsHtml);
		
		# Create a table of periodicals, with their title and location(s), clearing it out first if existing from a previous import
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.periodicallocations;";
		$this->databaseConnection->execute ($sql);
		$sql = "CREATE TABLE IF NOT EXISTS `periodicallocations` (
			`id` INT(11) AUTO_INCREMENT NOT NULL COMMENT 'Automatic key',
			`recordId` INT(6) NOT NULL COMMENT 'Record number',
			`title` VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Title (/ser/tg/t)',
			`location` TEXT COLLATE utf8_unicode_ci NOT NULL COMMENT 'Location(s) (/ser/loc/location, grouped)',	-- TEXT due to GROUP_CONCAT field length below
			PRIMARY KEY (id),
			INDEX(recordId),
			INDEX(title)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Table of periodical locations'
		;";
		$this->databaseConnection->execute ($sql);
		
		# Insert the data; note that the query assumes presence of catalogue_processed.xPath
		$sql = "
			INSERT INTO `periodicallocations` (recordId, title, location)
			SELECT
				catalogue_processed.recordId,
				catalogue_processed.value AS title,
				location.value AS location
			FROM catalogue_processed
			LEFT JOIN (
				-- Ensure one-to-one mapping with parents that have more than one *location, e.g. /records/204332/ , by using GROUP_CONCAT to pre-group these before doing the JOIN
				SELECT
					recordId,
					GROUP_CONCAT(value SEPARATOR '; ') AS value
				FROM catalogue_processed
				WHERE xPath = '/ser/loc/location'
				GROUP BY recordId
			) AS location ON catalogue_processed.recordId = location.recordId
			WHERE catalogue_processed.xPath = '/ser/tg/t'
		;";		// 3,363 rows inserted
		$this->databaseConnection->execute ($sql);
		
		# Create the table of matches, clearing it out first if existing from a previous import
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.periodicallocationmatches;";
		$this->databaseConnection->execute ($sql);
		$sql = "CREATE TABLE IF NOT EXISTS `periodicallocationmatches` (
			`id` int(11) AUTO_INCREMENT NOT NULL COMMENT 'Automatic key',
			`recordId` int(6) NOT NULL COMMENT 'Record number of child',
			`title` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Title in child',
			`parentRecordId` int(6) NULL COMMENT 'Record number of parent',
			`parentLocation` varchar(255) COLLATE utf8_unicode_ci NULL COMMENT 'Parent location',
			`parentTitle` varchar(255) COLLATE utf8_unicode_ci NULL COMMENT 'Parent title',
			`matchTitleField` VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Field used in matching',
			PRIMARY KEY (id),
			INDEX(recordId),
			INDEX(parentRecordId)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Table of periodical location matches'
		;";
		$this->databaseConnection->execute ($sql);
		
		# Insert the data for each grouping; note that the periodicallocations table is no longer needed after this
		# For /doc records this requires at least partial match, e.g. "Annals of Glaciology ; 9" in child record's (first) /doc/ts matches "Annals of Glaciology" in parent (periodicallocations.title)
		# /records/209527/ is an example with two *ts values - the first is used in Muscat as the match
		#!# Records like /records/23120/ are now inconsistent in that they contain an explicit *kg now - need to decide what to do with these
		$groupings = array (
			'/art/j/tg/t'	=> true,	// 79,988 results; NB To permit NULL right-side results, i.e. unmatched parent (giving 82,185 results), change the HAVING clause to "HAVING value != ''"
			'/doc/ts[1]'	=> false,	//    280 results; NB To permit NULL right-side results, i.e. unmatched parent (giving    294 results), change the HAVING clause to "HAVING value IS NOT NULL"
		);
		foreach ($groupings as $titleField => $isExactMatch) {
			$sql = "
				INSERT INTO `periodicallocationmatches`
				SELECT
					NULL,	-- Auto-populate auto-increment field
					child.recordId,
					" . ($isExactMatch ? 'selfTitle.value' : "SUBSTRING_INDEX(selfTitle.value,' ; ', 1)") . " AS value,
					periodicallocations.recordId AS parentRecordId,
					periodicallocations.location AS parentLocation,
					periodicallocations.title AS parentTitle,
					'{$titleField}' AS matchTitleField
				FROM catalogue_processed AS child
				LEFT JOIN catalogue_processed AS selfTitle ON child.recordId = selfTitle.recordId AND " . ($isExactMatch ? 'selfTitle.xPath' : 'selfTitle.xPathWithIndex') . " = '{$titleField}'
				LEFT JOIN periodicallocations ON " . ($isExactMatch ? 'selfTitle.value' : "SUBSTRING_INDEX(selfTitle.value,' ; ', 1)") . " = BINARY periodicallocations.title
				WHERE child.field = 'location' AND child.value = 'Periodical'
				HAVING periodicallocations.title IS NOT NULL	-- Necessary to strip out LEFT JOIN non-matches; INNER JOIN is too slow
				ORDER BY recordId
			;";
			$this->databaseConnection->execute ($sql);
		}
		
		# Replace location=Periodical in the processed records with the real, looked-up values
		$sql = "
			UPDATE catalogue_processed
			LEFT JOIN periodicallocationmatches ON catalogue_processed.recordId = periodicallocationmatches.recordId
			SET value = parentLocation
			WHERE
				    field = 'Location' AND value = 'Periodical'
				AND parentLocation IS NOT NULL
			;";
		$this->databaseConnection->execute ($sql);
	}
	
	
	# Function to create MARC records
	# See: doc/createMarcRecords.md
	private function createMarcRecords (&$errorsHtml)
	{
		# Log start
		$this->logger ('Starting ' . __METHOD__);
		
		# Create the volume numbers table, used for observation of the effect of the generate490 macro
		$this->createVolumeNumbersTable ();
		
		# Clean out the MARC table
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.catalogue_marc;";
		$this->databaseConnection->execute ($sql);
		
		# Create the new MARC table
		$this->logger ('Creating catalogue_marc table');
		$sql = "
			CREATE TABLE IF NOT EXISTS catalogue_marc (
				id int(11) NOT NULL COMMENT 'Record number',
				type ENUM('/art/in','/art/j','/doc','/ser') DEFAULT NULL COMMENT 'Type of record',
				status ENUM('migrate','suppress','ignore') NULL DEFAULT NULL COMMENT 'Status',
				mergeType VARCHAR(255) NULL DEFAULT NULL COMMENT 'Merge type',
				mergeVoyagerId VARCHAR(255) NULL DEFAULT NULL COMMENT 'Voyager ID for merging',
				marcPreMerge TEXT NULL COLLATE utf8_unicode_ci COMMENT 'Pre-merged MARC representation of local Muscat record',
				marc TEXT COLLATE utf8_unicode_ci COMMENT 'MARC representation of Muscat record',
				bibcheckErrors TEXT NULL COLLATE utf8_unicode_ci COMMENT 'Bibcheck errors, if any',
				suppressReasons VARCHAR(255) NULL DEFAULT NULL COMMENT 'Reason(s) for status=suppress',
			  PRIMARY KEY (id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='MARC representation of Muscat records'
		;";
		$this->databaseConnection->execute ($sql);
		
		# Cross insert the IDs
		$this->logger ('Cross-inserting IDs to catalogue_marc table');
		$query = 'INSERT INTO catalogue_marc (id) (SELECT DISTINCT(recordId) FROM catalogue_rawdata);';
		$this->databaseConnection->execute ($query);
		
		# Determine and set the record type
		$query = "UPDATE catalogue_marc
			JOIN catalogue_xml ON catalogue_marc.id = catalogue_xml.id
			SET type = CASE
				WHEN LENGTH( EXTRACTVALUE(xml, '/art/in')) > 0 THEN '/art/in'
				WHEN LENGTH( EXTRACTVALUE(xml, '/art/j' )) > 0 THEN '/art/j'
				WHEN LENGTH( EXTRACTVALUE(xml, '/doc'   )) > 0 THEN '/doc'
				WHEN LENGTH( EXTRACTVALUE(xml, '/ser'   )) > 0 THEN '/ser'
			END
		;";
		$this->databaseConnection->execute ($query);
		
		# Add in the supress/migrate/ignore status for each record; also available as a standalone option in the import
		$this->marcRecordsSetStatus ();
		
		# Add in the Voyager merge data fields, retrieving the resulting data
		$mergeData = $this->marcRecordsSetMergeFields ();
		
		# Get the schema
		if (!$marcParserDefinition = $this->getMarcParserDefinition ()) {return false;}
		
		# Get the merge definition
		if (!$mergeDefinition = $this->parseMergeDefinition ($this->getMergeDefinition ())) {return false;}
		
		# Get the suppress reasons list for this chunk
		$suppressReasonsList = $this->getSuppressReasonsList ();
		
		# Allow large queries for the chunking operation
		$maxQueryLength = (1024 * 1024 * 32);	// i.e. this many MB
		$query = 'SET SESSION max_allowed_packet = ' . $maxQueryLength . ';';
		$this->databaseConnection->execute ($query);
		
		# Start a list of records which require a second-pass arising from 773 processing where the host does not exist at time of processing
		$marcSecondPass = array ();
		
		# Process records in the given order, so that processing of field 773 will have access to *doc/*ser processed records up-front
		$recordProcessingOrder = array_merge ($this->recordProcessingOrder, array ('secondpass'));
		foreach ($recordProcessingOrder as $recordType) {
			$this->logger ('In ' . __METHOD__ . ", starting {$recordType} record type group");
			
			# Process the records in chunks
			$chunksOf = 500;	// Change max_allowed_packet above if necessary
			$i = 0;
			while (true) {	// Until the break
				
				# For the standard processing groups phase, look up from the database as usual
				if ($recordType != 'secondpass') {
					
					# Get the next chunk of record IDs to update for this type, until all are done
					$query = "SELECT
							id
						FROM catalogue_marc
						WHERE
							    type = '{$recordType}'
							AND marc IS NULL
						LIMIT {$chunksOf}
					;";
					if (!$ids = $this->databaseConnection->getPairs ($query)) {break;}	// Break the while (true) loop and move to next record type
					
				# For the second pass, use the second pass list that has been generated in the standard processing phase, once only
				} else {
					
					if (!$marcSecondPass) {break;}	// End if the second-pass phase has now already been run
					
					/* Should only be a small number (84 cases as of 22/12/2016), as shown by comparing the $this->recordProcessingOrder with:
						SELECT
							REPLACE(child.xPath, '/k2/kg', '') AS childType,
							parent.xPath AS parentType,
							COUNT(*) AS total
						FROM `catalogue_processed` AS child
						LEFT JOIN catalogue_processed AS parent ON child.value  = parent.recordId AND parent.`line` = 1
						WHERE child.`field` = 'kg'
						GROUP BY childType, parentType
					*/
					$ids = $marcSecondPass;
					$this->logger ('In ' . __METHOD__ . ', second pass has ' . count ($ids) . ' records');
					$marcSecondPass = array ();	// Ensure once only by resetting
				}
				
				# Get the records for this chunk
				$records = $this->getRecords ($ids, 'xml');
				
				# Arrange as a set of inserts
				$inserts = array ();
				foreach ($records as $id => $record) {
					$mergeType       = (isSet ($mergeData[$id]) ? $mergeData[$id]['mergeType'] : false);
					$mergeVoyagerId	 = (isSet ($mergeData[$id]) ? $mergeData[$id]['mergeVoyagerId'] : false);
					$suppressReasons = (isSet ($suppressReasonsList[$id]) ? $suppressReasonsList[$id] : false);
					$marc = $this->marcConversion->convertToMarc ($marcParserDefinition, $record['xml'], $mergeDefinition, $mergeType, $mergeVoyagerId, $suppressReasons);
					$marcPreMerge = $this->marcConversion->getMarcPreMerge ();
					if ($marcErrorHtml = $this->marcConversion->getErrorHtml ()) {
						$html = $marcErrorHtml;
						$errorsHtml .= $html;
					}
					$inserts[$id] = array (
						'id' => $id,
						'marcPreMerge' => $marcPreMerge,
						'marc' => $marc,
					);
					
					# If the record has generated a second pass requirement if it has a parent, register the ID
					if ($recordType != 'secondpass') {	// Do not re-register problems on second pass, to prevent any possibility of an infinite loop
						if ($secondPassRecordId = $this->marcConversion->getSecondPassRecordId ()) {
							$marcSecondPass[] = $secondPassRecordId;
						}
					}
				}
				
				# Insert the records (or update for the second pass); ON DUPLICATE KEY UPDATE is a dirty but useful method of getting a multiple update at once (as this doesn't require a WHERE clause, which can't be used as there is more than one record to be inserted)
				$insertSize = round (mb_strlen (serialize ($inserts)) / 1024, 2);
				$memoryUsageMb = round (memory_get_usage () / 1048576, 2);
				$this->logger ('In ' . __METHOD__ . ", in {$recordType} record type group, adding " . count ($inserts) . ' records (having insert size ' . $insertSize . 'KB); marcSecondPass is currently ' . count ($marcSecondPass) . ' record(s); memory usage is currently ' . $memoryUsageMb . 'MB');
				if (!$this->databaseConnection->insertMany ($this->settings['database'], 'catalogue_marc', $inserts, false, $onDuplicateKeyUpdate = true)) {
					$html  = "<p class=\"warning\">Error generating MARC, stopping at batched ({$id}):</p>";
					$html .= application::dumpData ($this->databaseConnection->error (), false, true);
					$errorsHtml .= $html;
					return false;
				}
				
				# Detect memory leaks, enabling the import to shut down cleanly but report the problem
				if ($memoryUsageMb > 80) {
					$memoryErrorMessage = '*** Memory leak detected; import system has been stopped. ***';
					$this->logger ($memoryErrorMessage);
					$errorsHtml .= "<p class=\"warning\">{$memoryErrorMessage}</p>";
					return false;
				}
			}
		}
		
		# Generate the output files
		$this->createMarcExports ();
		
		# Signal success
		return true;
	}
	
	
	# Function to import existing Voyager records
	private function createExternalRecords ()
	{
		# Log start
		$this->logger ('Starting ' . __METHOD__);
		
		# Load the Voyager records file
		$voyagerRecordsFile = file_get_contents ($this->applicationRoot . '/tables/' . 'spri_serials_recs_with_spri_mfhd_attached.mrk');
		
		# Parse the Voyager records file into line-by-line shards
		$shards = $this->parseVoyagerRecordFile ($voyagerRecordsFile);
		
		# Clean out the external records table
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.catalogue_external;";
		$this->databaseConnection->execute ($sql);
		
		# Create the new external records table
		$sql = "
			CREATE TABLE IF NOT EXISTS catalogue_external (
				id int(11) NOT NULL AUTO_INCREMENT COMMENT 'Automatic key',
				voyagerId INT(11) NOT NULL COMMENT 'Voyager ID',
				field VARCHAR(3) NOT NULL COMMENT 'Field code',
				indicators VARCHAR(2) NOT NULL COMMENT 'First and second indicator',
				data VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Data',
			  PRIMARY KEY (id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Existing Voyager records';
		";
		$this->databaseConnection->execute ($sql);
		
		# Insert the new data
		$this->databaseConnection->insertMany ($this->settings['database'], 'catalogue_external', $shards);
		
		# Signal success
		return true;
	}
	
	
	# Function to parse the Voyager record file
	private function parseVoyagerRecordFile ($voyagerRecordsFile)
	{
		# Normalise newlines
		$voyagerRecordsFile = str_replace ("\r\n", "\n", trim ($voyagerRecordsFile));
		
		# Replace Voyager encodings with MARC characters
		$replacements = array ('\\' => '#', '$'=> $this->doubleDagger);
		$voyagerRecordsFile = strtr ($voyagerRecordsFile, $replacements);
		
		# Split by double-newline
		$records = explode ("\n\n", $voyagerRecordsFile);
		
		# Convert each line from Voyager to pure MARC format
		foreach ($records as $index => $record) {
			
			# For LDR, 001, 005, 008, add two spaces to act as a virtual two indicators, so that the regexp below matches for all lines
			$record = preg_replace ('/^(=(?:LDR|00.)  )(.+)/m', '\1  \2', $record);
			
			# Perform matches
			$matches = array ();
			$result = preg_match_all ('/^=([0-9|LDR]{3})  ([ \\0-9]{2})(.+)$/m', $record, $matches, PREG_SET_ORDER);	// Tested previously to confirm no errors
			$records[$index] = $matches;
		}
		
		# Reindex by field 001, which is the actual ID
		$voyager = array ();
		foreach ($records as $index => $record) {
			$voyagerId = $record[1][3];	// Second line, match \3 (i.e. index 4)
			$voyager[$voyagerId] = $record;
		}
		
		# Arrange as record shards
		$shards = array ();
		foreach ($voyager as $voyagerId => $record) {
			foreach ($record as $index => $line) {
				$shards[] = array (
					'voyagerId'		=> $voyagerId,
					'field'			=> $line['1'],
					'indicators'	=> $line['2'],
					'data'			=> $line['3'],
				);
			}
		}
		
		# Return the record shards
		return $shards;
	}
	
	
	# Function to set the status of each MARC record
	private function marcRecordsSetStatus ()
	{
		# Log start
		$this->logger ('Starting ' . __METHOD__);
		
		# NB Unfortunately CASE does not seem to support compound statements, so these three statements are basically a CASE in reverse; see: http://stackoverflow.com/a/18170014/180733
		
		# Default to migrate
		$query = "UPDATE catalogue_marc SET status = 'migrate';";
		$this->databaseConnection->execute ($query);
		
		# Records to suppress
		$suppressionScenarios = $this->getSuppressionScenarios ();
		foreach ($suppressionScenarios as $reasonToken => $suppressionScenario) {
			$conditions = $suppressionScenario[1];
			$query = "UPDATE catalogue_marc
				LEFT JOIN catalogue_processed ON catalogue_marc.id = catalogue_processed.recordId
				LEFT JOIN catalogue_xml ON catalogue_marc.id = catalogue_xml.id
				SET
					status = 'suppress',
					suppressReasons = IF(suppressReasons IS NULL, '{$reasonToken}', CONCAT(suppressReasons, ', {$reasonToken}'))
				WHERE
					{$conditions}
			;";
			$this->databaseConnection->execute ($query);
		}
		
		# Records to ignore (highest priority)
		#!# Currently will exclude records that are *also* held at IGS rather than *only* held at IGS - data work is in progress
		$query = "UPDATE catalogue_marc
			LEFT JOIN catalogue_processed ON catalogue_marc.id = catalogue_processed.recordId
			SET status = 'ignore'
			WHERE
				(field = 'location' AND value IN('IGS', 'International Glaciological Society', 'Basement IGS Collection'))
		;";
		$this->databaseConnection->execute ($query);
	}
	
	
	# Function to define suppression scenarios
	private function getSuppressionScenarios ()
	{
		# Records to suppress, defined as a set of scenarios represented by a token
		#!# Check whether locationCode locations with 'Periodical' are correct to suppress
		#!# Major issue: problem with e.g. /records/3929/ where two records need to be created, but not both should be suppressed; there are around 1,000 of these
		return $suppressionScenarios = array (
			
			'STATUS-RECEIVED' => array (
				# 5,376 records
				'Item is being processed, i.e. has been accessioned and is with a bibliographer for classifying and cataloguing',
				"   field = 'status' AND value = 'RECEIVED'
				"),
				
			'ORDER-CANCELLED' => array (
				# 232 records
				'Order cancelled by SPRI, but record retained for accounting/audit purposes in the event that the item arrives',
				"   field = 'status' AND value = 'ORDER CANCELLED'
				"),
				
			'EXPLICIT-SUPPRESS' => array (
				# 24,658 records
				'Record marked specifically to suppress, e.g. pamphlets needing review, etc.',
				# NB This has been achieved using a BCPL routine to mark the records as such
				"   field = 'status' AND value = '{$this->suppressionStatusKeyword}'
				"),
				
			'ON-ORDER-RECENT' => array (
				# 15 records
				'Item on order recently with expectation of being fulfilled',
				"	    EXTRACTVALUE(xml, '//status') LIKE 'ON ORDER%'
					AND EXTRACTVALUE(xml, '//acq/date') REGEXP '^[0-9]{4}/[0-9]{2}/[0-9]{2}$'	-- Merely checks correct syntax
					AND UNIX_TIMESTAMP ( STR_TO_DATE( CONCAT ( EXTRACTVALUE(xml, '//acq/date'), ' 12:00:00'), '%Y/%m/%d %h:%i:%s') ) >= UNIX_TIMESTAMP('{$this->acquisitionDate} 00:00:00')
				"),
				
			'ON-ORDER-OLD' => array (
				# 654 records; see also: /reports/onorderold/ which matches
				'Item on order recently unlikely to be fulfilled, but item remains desirable and of bibliographic interest',
				"	    EXTRACTVALUE(xml, '//status') LIKE 'ON ORDER%'
					AND EXTRACTVALUE(xml, '//acq/date') REGEXP '^[0-9]{4}/[0-9]{2}/[0-9]{2}$'	-- Merely checks correct syntax
					AND UNIX_TIMESTAMP ( STR_TO_DATE( CONCAT ( EXTRACTVALUE(xml, '//acq/date'), ' 12:00:00'), '%Y/%m/%d %h:%i:%s') ) < UNIX_TIMESTAMP('{$this->acquisitionDate} 00:00:00')
				"),
				
			#!# Needs review - concern that this means that items with more than one location could get in the suppression bucket; see e-mail 19/12/2016
			'EXTERNAL-LOCATION' => array (
				# 8,325 records
				'Item of bibliographic interest, but not held at SPRI, so no holdings record can be created',
				"	    field = 'location'
					AND value NOT REGEXP \"^(" . implode ('|', array_keys ($this->locationCodes)) . ")\"
					AND (
						   value IN('', '-', '??', 'Not in SPRI', 'Periodical')
						OR value LIKE '%?%'
						OR value LIKE '%Cambridge University%'
						OR value LIKE 'Picture Library Store : Video%'
						)
				"),
				
			#!# Needs review - 'offprint' is too restrictive, and various categories have been physically reviewed in person
			'OFFPRINT-OR-PHOTOCOPY' => array (
				# 1,553 records
				'Item needing review to determine provenance with respect to copyright',
				"	    field IN('note', 'local', 'priv')
					AND (
						   value LIKE '%offprint%'
						OR value LIKE '%photocopy%'
						)
					AND value NOT LIKE '%out of copyright%'
				"),
				
		);
	}
	
	
	# Function to add in the Voyager merge data fields
	private function marcRecordsSetMergeFields ()
	{
		# Records to suppress
		$query = "UPDATE catalogue_marc
			LEFT JOIN catalogue_xml ON catalogue_marc.id = catalogue_xml.id
			SET
				mergeType = REPLACE (EXTRACTVALUE(xml, '//ka'), 'Matchtype: ', ''),
				mergeVoyagerId = EXTRACTVALUE(xml, '//kc')
			WHERE
				   EXTRACTVALUE(xml, '//ka') != ''
				OR EXTRACTVALUE(xml, '//kc') != ''
		;";
		$this->databaseConnection->execute ($query);
		
		# Read the values back and return them
		$query = "SELECT id, mergeType, mergeVoyagerId FROM {$this->settings['database']}.catalogue_marc WHERE mergeType IS NOT NULL;";
		$mergeData = $this->databaseConnection->getData ($query, "{$this->settings['database']}.catalogue_marc");
		return $mergeData;
	}
	
	
	# Function to create a table of UDC translations
	private function createUdcTranslationsTable ()
	{
		# Log start
		$this->logger ('Starting ' . __METHOD__);
		
		# Create the table, clearing it out first if existing from a previous import
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.udctranslations;";
		$this->databaseConnection->execute ($sql);
		$sql = "CREATE TABLE IF NOT EXISTS udctranslations (
			`ks` VARCHAR(20) NOT NULL COMMENT '*ks',
			`kw` VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL COMMENT '*kw equivalent, looked-up',
			PRIMARY KEY (`ks`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Table of UDC translations'
		;";
		$this->databaseConnection->execute ($sql);
		
		# Parse the table file
		$udcTranslations = $this->parseUdcTranslationTable ();
		
		# Arrange the values
		$inserts = array ();
		foreach ($udcTranslations as $ks => $kw) {
			$inserts[] = array (
				'ks'	=> $ks,
				'kw'	=> $kw,
			);
		}
		
		# Insert the data
		$this->databaseConnection->insertMany ($this->settings['database'], 'udctranslations', $inserts);
	}
	
	
	# Function to parse the UDC translation table
	private function parseUdcTranslationTable ()
	{
		# Load the file, and normalise newlines
		$lookupTable = file_get_contents ($this->applicationRoot . '/tables/' . 'UDCMAP.TXT');
		$lookupTable = str_replace ("\r\n", "\n", $lookupTable);
		
		# Undo Muscat escaped asterisks @*
		$lookupTable = $this->unescapeMuscatAsterisks ($lookupTable);
		
		# Remove line-breaks that are not the end of a line
		$lookupTable = preg_replace ("/([^#])\n/sm", '\1 ', $lookupTable);
		
		# Parse into lines
		/*  Example lines:
		 *  *k 803.98 * *ksub Danish language #
		 *  *k 93"15" * *ksub Sixteenth century #
		 *  *k 39(091) * *ksub Ethnohistory #
		 *  *k 77.041.5 * * ksub Portrait phtography, portraits #
		 */
		preg_match_all ("/^\*k\s([^\s]+) \* \*k\s?(?:sub|geo) ([^#]+) #/sm", $lookupTable, $matches, PREG_SET_ORDER);
		
		# Do a duplicates check
		$ids = array ();
		foreach ($matches as $match) {
			$ids[] = $match[1];
		}
		$duplicates = array_diff_assoc ($ids, array_unique ($ids));
		if ($duplicates) {
			echo "\<p class=\"warning\">The following duplicates were found in the UDC loading phase: <em>" . implode ('</e>, <em>', $duplicates) . ' .</em></p>';
		}
		
		# Arrange as $ks => $kw
		$udcTranslations = array ();
		foreach ($matches as $match) {
			$ks = $match[1];
			$kw = $match[2];
			$udcTranslations[$ks] = $kw;
		}
		
		# Split off any trailing *... sections
		foreach ($udcTranslations as $ks => $kw) {
			if (substr_count ($kw, ' * ')) {
				list ($kw, $supplementaryTerm) = explode (' * ', $kw, 2);
				$udcTranslations[$ks] = $kw;
			}
		}
		
		# Split off any (...) sections
		$bracketExceptions = array ('(*501)', '(*52)');
		foreach ($udcTranslations as $ks => $kw) {
			if (in_array ($ks, $bracketExceptions)) {continue;}		// Skip listed exceptions
			if (substr_count ($kw, '(')) {
				$udcTranslations[$ks] = preg_replace ('/( ?\(([^)]+)\))/', '', $kw);
			}
		}
		
		# Return the matches; should be 3463 results
		return $udcTranslations;
	}
	
	
	# Helper function to unescape Muscat asterisks
	private function unescapeMuscatAsterisks ($string)
	{
		return str_replace ('@*', '*', $string);
	}
	
	
	# Function to create the volume numbers table, used for observation of the effect of the generate490 macro
	public function createVolumeNumbersTable ()
	{
		# Log start
		$this->logger ('Starting ' . __METHOD__);
		
		# Create the table, clearing it out first if existing from a previous import
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.volumenumbers;";
		$this->databaseConnection->execute ($sql);
		$sql = "CREATE TABLE IF NOT EXISTS volumenumbers (
			id VARCHAR(10) NOT NULL COMMENT 'Shard ID',
			recordId INT(6) NOT NULL COMMENT 'Record ID',
			line INT(3) NOT NULL COMMENT 'Line',
			ts VARCHAR(255) NOT NULL COMMENT '*ts value',
			a VARCHAR(255) DEFAULT NULL COMMENT '{$this->doubleDagger}a value in result',
			v VARCHAR(255) DEFAULT NULL COMMENT '{$this->doubleDagger}V value in result',
			result VARCHAR(255) DEFAULT NULL COMMENT 'Result of translation',
			matchedRegexp VARCHAR(255) DEFAULT NULL COMMENT 'Matched regexp',
			PRIMARY KEY (id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Table of volume numbers'
		;";
		$this->databaseConnection->execute ($sql);
		
		# Insert the data
		$sql = "
			INSERT INTO volumenumbers (id, recordId, line, ts)
			SELECT
				id,
				recordId,
				line,
				value AS ts
			FROM catalogue_processed
			WHERE xPath LIKE '%/ts'
		";
		$this->databaseConnection->execute ($sql);
		
		# Obtain the values
		$data = $this->databaseConnection->selectPairs ($this->settings['database'], 'volumenumbers', array (), array ('id', 'ts'));
		
		# Generate the result
		$updates = array ();
		foreach ($data as $recordId => $ts) {
			$result = $this->marcConversion->macro_generate490 ($ts, NULL, $errorString_ignored, $matchedRegexp, $reportGenerationMode = true);
			$subfieldValues = $this->marcConversion->parseSubfieldsToPairs ($result, $knownSingular = true);
			$updates[$recordId] = array (
				'a' => $subfieldValues['a'],
				'v' => (isSet ($subfieldValues['v']) ? $subfieldValues['v'] : NULL),
				'result' => $result,
				'matchedRegexp' => $matchedRegexp,
			);
		}
		
		# Update the table to add the results of the macro generation
		$this->databaseConnection->updateMany ($this->settings['database'], 'volumenumbers', $updates);
	}
	
	
	# Function to create all MARC exports
	private function createMarcExports ($regenerateReport = false)
	{
		# Log start
		$this->logger ('Starting ' . __METHOD__);
		
		# If regenerating, clear existing Bibcheck errors in the database
		if ($regenerateReport) {
			$this->databaseConnection->update ($this->settings['database'], 'catalogue_marc', array ('bibcheckErrors' => NULL));
		}
		
		# Generate the output files and attach errors to the database records
		require_once ('createMarcExport.php');
		$createMarcExport = new createMarcExport ($this, $this->applicationRoot, $this->recordProcessingOrder);
		foreach ($this->filesets as $fileset => $label) {
			$createMarcExport->createExport ($fileset);
		}
		
		# If required, regenerate the error reports depending on the data
		if ($regenerateReport) {
			$this->runReport ('bibcheckerrors', true);
			$this->runReport ('possiblearticle', true);
		}
	}
	
	
	# Function to run the reports
	private function runReports ()
	{
		# Log start
		$this->logger ('Starting ' . __METHOD__);
		
		# Clean out the report results table
		$query = "DROP TABLE IF EXISTS {$this->settings['database']}.reportresults;";
		$this->databaseConnection->query ($query);
		
		# Create the new results table
		$query = "CREATE TABLE IF NOT EXISTS reportresults (
			`id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Automatic key',
			`report` varchar(40) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Report type',
			`recordId` int(6) NOT NULL COMMENT 'Record number',
			PRIMARY KEY (`id`),
			KEY `report` (`report`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Results table' AUTO_INCREMENT=1 ;
		";
		$this->databaseConnection->query ($query);
		
		# Run each report and insert the results
		$reports = $this->getReports ();
		foreach ($reports as $reportId => $description) {
			
			# Skip listing type reports, which implement data handling directly (and optional countability support), and which are handled separately in runListings ()
			if ($this->isListing ($reportId)) {continue;}
			
			# Run the report
			$result = $this->runReport ($reportId);
			
			# Handle errors
			if ($result === false) {
				echo "<p class=\"warning\">Error generating report <em>{$reportId}</em>:</p>";
				echo application::dumpData ($this->databaseConnection->error (), false, true);
			}
		}
	}
	
	
	# Function to run a report
	private function runReport ($reportId, $clearFirst = false)
	{
		# If required, clear the results from any previous instantiation
		if ($clearFirst) {
			$this->databaseConnection->delete ($this->settings['database'], 'reportresults', array ('report' => $reportId));
		}
		
		# Assemble the query and insert the data
		$reportFunction = 'report_' . $reportId;
		$query = $this->reports->$reportFunction ();
		$query = "INSERT INTO reportresults (report,recordId)\n" . $query . ';';
		$result = $this->databaseConnection->query ($query);
		
		# Return the result
		return $result;
	}
	
	
	# Function to run the listing reports
	private function runListings ()
	{
		# Log start
		$this->logger ('Starting ' . __METHOD__);
		
		# Run each listing report
		foreach ($this->listingsList as $report => $description) {
			$reportFunction = 'report_' . $report;
			$this->reports->$reportFunction ();
		}
	}
	
	
	# Function to run tests and generate test results
	public function runTests (&$errorHtml = false, $regenerateMarc = false, $importMode = true)
	{
		# Log start (except in runtime mode)
		if ($importMode) {
			$this->logger ('Starting ' . __METHOD__);
		}
		
		# Now create the statistics table; this is pre-compiled for performance
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.tests;";
		$this->databaseConnection->execute ($sql);
		$sql = "
			CREATE TABLE `tests` (
			  `id` INT(1) NOT NULL AUTO_INCREMENT COMMENT 'Test #',
			  `result` INT(1) NOT NULL COMMENT 'Result',	-- 1=passed, 0=failed
			  `description` VARCHAR(255) NOT NULL COMMENT 'Description',
			  `recordId` INT(6) NOT NULL COMMENT 'Record number',
			  `marcField` VARCHAR(3) NOT NULL COMMENT 'MARC field',
			  `negativeTest` INT(1) NOT NULL COMMENT 'Negative test?',
			  `indicatorTest` INT(1) NOT NULL COMMENT 'Indicator test?',
			  `expected` VARCHAR(255) NOT NULL COMMENT 'Expected',
			  `found` TEXT NULL COMMENT 'Found line(s)',
			  PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Test results';";
		$this->databaseConnection->execute ($sql);
		
		# Load the tests definition
		$testsString = file_get_contents ($this->applicationRoot . '/tests/tests.txt');
		
		# Parse to tests
		$fields = array ('id', 'description', 'recordId', 'marcField', 'expected');	// In order of appearance in definition
		if (!$tests = application::parseBlocks ($testsString, $fields, true, $errorHtml)) {
			// $errorHtml will now be set to the error
			return false;
		}
		
		# Determine record IDs to load
		$ids = array ();
		foreach ($tests as $test) {
			$ids[] = $test['recordId'];
		}
		
		# Dynamically regenerate the MARC records
		if ($regenerateMarc) {
			
			# Retrieve the merge datasets
			$mergeDefinition = $this->parseMergeDefinition ($this->getMergeDefinition ());
			$mergeData = $this->marcRecordsSetMergeFields ();
			$suppressReasonsList = $this->getSuppressReasonsList ($ids);
			
			# Convert the records
			$marcParserDefinition = $this->getMarcParserDefinition ();
			$xmlRecords = $this->getRecords ($ids, 'xml', false, false, $searchStable = (!$this->userIsAdministrator));
			$marcRecords = array ();
			foreach ($xmlRecords as $id => $record) {
				// if (!in_array ($id, $regenerateIds)) {continue;}	// Skip non-needed IDs
				$mergeType       = (isSet ($mergeData[$id]) ? $mergeData[$id]['mergeType'] : false);
				$mergeVoyagerId	 = (isSet ($mergeData[$id]) ? $mergeData[$id]['mergeVoyagerId'] : false);
				$suppressReasons = (isSet ($suppressReasonsList[$id]) ? $suppressReasonsList[$id] : false);
				$marcRecords[$id]['marc'] = $this->marcConversion->convertToMarc ($marcParserDefinition, $record['xml'], $mergeDefinition, $mergeType, $mergeVoyagerId, $suppressReasons);
			}
			$this->databaseConnection->updateMany ($this->settings['database'], 'catalogue_marc', $marcRecords);
		}
		
		# Pre-load the MARC records
		$marcRecords = $this->databaseConnection->selectPairs ($this->settings['database'], 'catalogue_marc', array ('id' => $ids), array ('id', 'marc'));
		
		# Run each test and add in the result
		foreach ($tests as $id => $test) {
			$tests[$id]['id'] = $test['id'];
			
			# Obtain the record number
			$recordId = $test['recordId'];
			
			# Set default state
			$tests[$id]['found'] = NULL;
			$tests[$id]['result'] = 0;	// Assume failure
			$tests[$id]['negativeTest'] = NULL;
			$tests[$id]['indicatorTest'] = NULL;
			
			# Warn if record not present
			if (!isSet ($marcRecords[$recordId])) {
				$errorHtml .= "<p class=\"warning\"><strong>Error:</strong> Test #{$test['id']} defines use of record #{$recordId} which does not exist.</p>";
				continue;	// Next test
			}
			
			# Parse the record
			$record = $this->marcConversion->parseMarcRecord ($marcRecords[$recordId], false);
			// application::dumpData ($record);
			
			# Determine if the test is a negative test (i.e. fails if there is a match), starting with '!'
			if (preg_match ('/^!(.+)$/', $tests[$id]['marcField'], $matches)) {
				$tests[$id]['negativeTest'] = true;
				$tests[$id]['marcField'] = $matches[1];	// Overwrite
			}
			
			# Determine if the test is an indicator test, starting with 'i'
			if (preg_match ('/^i(.+)$/', $tests[$id]['marcField'], $matches)) {
				$tests[$id]['indicatorTest'] = true;
				$tests[$id]['marcField'] = $matches[1];	// Overwrite
			}
			
			# Obtain an array of matching field numbers (usually only one, e.g. 100, unless wildcard used, e.g. 1xx, which would match 100, 110, 111), if any
			$fieldsMatching = array ();
			foreach ($record as $field => $lines) {
				if (fnmatch ($tests[$id]['marcField'], $field)) {	// Simple match or wildcard match (e.g. testing $fieldNumber 100 against 100 or 1xx)
					$fieldsMatching[] = $field;	// Won't be duplicated, as $record is already indexed by field number
				}
			}
			
			# Add the found line(s)
			$lines = array ();
			foreach ($fieldsMatching as $field) {
				foreach ($record[$field] as $line) {
					$lines[] = $line['fullLine'];
				}
			}
			$tests[$id]['found'] = implode ("\n", $lines);
			
			# In the case of a negative test, and the test is for presence ('*'), check pass/failure
			if ($tests[$id]['negativeTest']) {
				if ($test['expected'] == '*') {
					$tests[$id]['result'] = (!$fieldsMatching);
					continue;	// Next test; report will turn $tests[$id]['found'] = NULL into a statement that the line is not present
				}
			}
			
			# Determine if the test is a regexp test (starting with a slash, e.g. '/[a-z]/i') or a simple string match
			$isRegexpTest = preg_match ('|^/|', $test['expected']);
			
			# Test each matching line for a result; comparisons are done against the line after the field number and space, i.e. tests against indicators + content
			$isFound = false;
			foreach ($fieldsMatching as $field) {
				foreach ($record[$field] as $line) {
					
					# Determine what to test
					$dataString = $line['line'];
					if ($tests[$id]['indicatorTest']) {
						$dataString = $line['indicators'];
					}
					
					# Run the test
					if ($isRegexpTest) {
						$result = (preg_match ($test['expected'], $dataString, $matches));	// Test is case-sensitive unless test sets /i
					} else if ($test['expected'] == "''") {		// Test of empty string, defined as string consisting of two single-quotes
						$result = (!strlen ($dataString));
					} else {
						$result = substr_count ($dataString, $test['expected']);	// Test is case-sensitive
					}
					
					# Register if match found
					if ($result) {
						$isFound = true;
						$tests[$id]['found'] = $line['fullLine'];	// Show only the relevant line
					}
					
					# For a positive test, stop if found, as no need to test further lines
					if (!$tests[$id]['negativeTest']) {
						if ($isFound) {
							break;
						}
					}
				}
			}
			
			# Register the result
			if ($tests[$id]['negativeTest']) {
				$tests[$id]['result'] = !$isFound;
			} else {
				$tests[$id]['result'] = $isFound;
			}
		}
		
		# Insert the results
		$this->databaseConnection->insertMany ($this->settings['database'], 'tests', $tests);
		
		# Implement countability, by adding an entry, without reference to recordId, into the report results table, first clearing any existing result in case this is being run dynamically
		$this->databaseConnection->delete ($this->settings['database'], 'reportresults', array ('report' => 'tests'));
		$sql = "
			INSERT INTO reportresults (report,recordId)
				SELECT
					-- Get as many rows as failures exist, but ignore the actual data in them:
					'tests' AS report,
					-1 AS recordId
				FROM tests
				WHERE result = 0
		;";
		$this->databaseConnection->execute ($sql);
		
		# Return the result
		return true;
	}
	
	
	# Search
	public function search ()
	{
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
			'includeOnly' => array_keys ($this->fieldsIndexFields),
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
				<!-- http://docs.jquery.com/Effects/toggle -->
				<script src="http://code.jquery.com/jquery-latest.js"></script>
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
		if (!array_key_exists ($_GET['field'], $this->fieldsIndexFields)) {return false;}
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
		
		# Get the data; use of _GET in field definition is safe against SQL injection due to previous check against $this->fieldsIndexFields
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
			'default'	=> $this->getMarcParserDefinition (),
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
		foreach ($this->filesets as $fileset => $label) {
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
		foreach ($this->filesets as $fileset => $label) {
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
	
	
	# Function to return the MARC parser definition
	private function getMarcParserDefinition ()
	{
		# Get the latest version
		$query = "SELECT definition FROM {$this->settings['database']}.marcparserdefinition ORDER BY id DESC LIMIT 1;";
		if (!$definition = $this->databaseConnection->getOneField ($query, 'definition')) {
			echo "\n<p class=\"warning\"><strong>Error:</strong> The MARC21 parser definition could not be retrieved.</p>";
			return false;
		}
		
		# Return the string
		return $definition;
	}
	
	
	# Function to get the supression reasons list
	private function getSuppressReasonsList ($ids = array ())
	{
		$query = 'SELECT id,suppressReasons FROM catalogue_marc WHERE suppressReasons IS NOT NULL' . ($ids ? " AND id IN (" . implode (', ', $ids) . ")" : '') . ';';
		$suppressReasonsList = $this->databaseConnection->getPairs ($query);
		return $suppressReasonsList;
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
		$form->heading ('p', 'Character tester: <a href="http://graphemica.com/" target="_blank">Graphemica character tester</a>.');
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
			'default'	=> $this->getMergeDefinition (),
			'wrap'		=> 'off',
		));
		
		# Validate the parser syntax
		if ($unfinalisedData = $form->getUnfinalisedData ()) {
			if ($unfinalisedData['definition']) {
				$this->parseMergeDefinition ($unfinalisedData['definition'], $errorString);
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
			if (!$this->populateLocNameAuthorities ($error)) {
				$html = "\n<p>{$this->cross} {$error}</p>";
			} else {
				$html = "\n<p>{$this->tick} The LoC name authority data was processed.</p>";
			}
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to populate the LoC name authority data
	private function populateLocNameAuthorities (&$error = false)
	{
		# Create the LoC name authority data records table
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.locnames;";
		$this->databaseConnection->execute ($sql);
		$sql = "
			CREATE TABLE locnames (
				id INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Automatic key',
				locId VARCHAR(255) NOT NULL COMMENT 'LoC ID',
				surname VARCHAR(255) NULL COMMENT 'Surname',
				name VARCHAR(1024) NOT NULL COMMENT 'Name (full string)',
				url VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'URL',
			  PRIMARY KEY (id),
			  INDEX(surname)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Table of Library of Congress name authority list';
		";
		$this->databaseConnection->execute ($sql);
		
		# Define cyrillic character list, defined at https://en.wikipedia.org/wiki/Russian_alphabet
		$cyrillicCharacters = array (
			'\u0410', '\u0430', '\u0411', '\u0431', '\u0412', '\u0432', '\u0413', '\u0433', '\u0414', '\u0434',
			'\u0415', '\u0435', '\u0401', '\u0451', '\u0416', '\u0436', '\u0417', '\u0437', '\u0418', '\u0438',
			'\u0419', '\u0439', '\u041A', '\u043A', '\u041B', '\u043B', '\u041C', '\u043C', '\u041D', '\u043D',
			'\u041E', '\u043E', '\u041F', '\u043F', '\u0420', '\u0440', '\u0421', '\u0441', '\u0422', '\u0442',
			'\u0423', '\u0443', '\u0424', '\u0444', '\u0425', '\u0445', '\u0426', '\u0446', '\u0427', '\u0447',
			'\u0428', '\u0448', '\u0429', '\u0449', '\u042A', '\u044A', '\u042B', '\u044B', '\u042C', '\u044C',
			'\u042D', '\u044D', '\u042E', '\u044E', '\u042F', '\u044F'
		);
		
		# Convert cryllic to single string for use in a regexp
		$cyrillicList = json_decode ('"' . implode ($cyrillicCharacters) . '"');
		
		# Work through each line in the data; the file is 12G, "LC Name Authority File (SKOS/RDF only)" at http://id.loc.gov/download/
		$handle = fopen ($this->applicationRoot . '/tables/authoritiesnames.nt.skos', 'r');
		if (!$handle) {
			$error = 'Error opening the LoC name authority file.';
			return false;
		}
		
		# Read through each line and chunk the inserts
		$inserts = array ();
		$chunksOf = 5000;
		$i = 0;
		while (($line = fgets ($handle)) !== false) {
			
			# Limit to Lexical Labels as defined at: http://www.w3.org/2004/02/skos/core#altLabel
			# Line looks like:
			#  <http://id.loc.gov/authorities/names/n79033037> <http://www.w3.org/2004/02/skos/core#altLabel> "\u041A\u0440\u043E\u043F\u043E\u0442\u043A\u0438\u043D, \u041F\u0435\u04420440 \u0410\u043B\u0435\u043A\u0441\u0435\u0435\u0432\u0438\u0447, kni\uFE20a\uFE21z\u02B9, 1842-1921"@EN .
			if (!substr_count ($line, '<http://www.w3.org/2004/02/skos/core#altLabel>')) {continue;}
			
			# Parse out the line
			preg_match ('|^<(http://id.loc.gov/authorities/names/([^>]+))> <http://www.w3.org/2004/02/skos/core#altLabel> "([^"]+)".+$|i', $line, $matches);
			
			# Convert the unicode character references (e.g. \u041A\u0440\u043E\u043F... ) into standard UTF-8 strings
			$name = json_decode ('"' . $matches[3] . '"');
			
			# Extract the surname, i.e. the section before the first comma
			$surname = strtok ($name, ',');
			
			# Ensure the surname is made up of Cyrillic characters only
			if (!preg_match ("/^([{$cyrillicList}]+)$/", $surname)) {continue;}
			
			# Register the insert
			$inserts[] = array (
				'id'		=> NULL,			// Assign automatically
				'locId'		=> $matches[2],		// e.g. n79033037
				'surname'	=> $surname,		
				'name'		=> $name,			
				'url'		=> $matches[1],		// e.g. http://id.loc.gov/authorities/names/n79033037
			);
			
			# Implement the chunk counter
			$i++;
			
			# Add the chunk of inserts periodically
			if ($i == $chunksOf) {
				if (!$this->databaseConnection->insertMany ($this->settings['database'], 'locnames', $inserts)) {
					$error = "Error inserting LoC authority names, stopping at batched ({$i})";
					return false;
				}
				
				# Reset for next chunk
				$i = 0;
				$inserts = array ();
			}
		}
		
		# Add residual chunk
		if ($inserts) {
			if (!$this->databaseConnection->insertMany ($this->settings['database'], 'locnames', $inserts)) {
				$error = "Error inserting LoC authority names, stopping at batched ({$i})";
				return false;
			}
		}
		
		# Close the file
		fclose ($handle);
		
		# Mark whether names (for selected fields) are in the Library of Congress name authority list
		$query = "
			UPDATE transliterations
			INNER JOIN locnames ON transliterations.title = locnames.surname
			SET inNameAuthorityList = -1
			WHERE transliterations.field IN('" . implode ("', '", $this->transliterationNameMatchingFields) . "')
		;";
		$this->databaseConnection->query ($query);
		
		# Confirm success
		return true;
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
			if (!$this->populateOtherNames ($error)) {
				$html = "\n<p class=\"warning\">ERROR: {$this->cross} {$error}</p>";
			} else {
				$html = "\n<p>{$this->tick} The other names data was processed.</p>";
			}
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to populate the other names data
	private function populateOtherNames (&$error = false)
	{
		# Define the other names data file
		$file = $this->applicationRoot . '/tables/othernames.tsv';
		
		# Initialise the other names data table with existing data
		if (!$this->buildOtherNamesTable ($file, $error)) {return false;}
		
		# Add any new values to the data file
		$this->obtainSaveOtherNamesData ($file);
		
		# Re-initialise the other names data table with existing data
		if (!$this->buildOtherNamesTable ($file, $error)) {return false;}
		
		# Perform matches
		$query = "
			UPDATE transliterations
			INNER JOIN othernames ON transliterations.title = othernames.surname
			SET inNameAuthorityList = othernames.results
			WHERE transliterations.field IN('" . implode ("', '", $this->transliterationNameMatchingFields) . "')
		;";
		$this->databaseConnection->query ($query);
		
		# Confirm success
		return true;
	}
	
	
	# Function to insert other names data
	private function buildOtherNamesTable ($file, &$error = false)
	{
		# Read in the TSV file
		if (!$tsv = @file_get_contents ($file)) {
			$error = "Could not open the {$file} other names file.";
			return false;
		}
		
		# Convert from TSV
		require_once ('csv.php');
		$otherNames = csv::tsvToArray (trim ($tsv));
		
		# End if none, e.g. if new file
		if (!$otherNames) {return true;}
		
		# Create/re-create the table
		$sql = "DROP TABLE IF EXISTS {$this->settings['database']}.othernames;";
		$this->databaseConnection->execute ($sql);
		$sql = "
			CREATE TABLE othernames (
				id INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Automatic key',
				surname VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Surname',
				results INT(11) NOT NULL COMMENT 'Number of results',
				source VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Source',
				PRIMARY KEY (id),
				INDEX(surname)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Table of names sourced from other sources';
		";
		$this->databaseConnection->execute ($sql);
		
		# Insert the data
		if (!$this->databaseConnection->insertMany ($this->settings['database'], 'othernames', $otherNames)) {
			$error = 'Error inserting other names data.';
			return false;
		}
		
		# Signal access
		return true;
	}
	
	
	# Function to obtain and save other names data
	private function obtainSaveOtherNamesData ($file)
	{
		# Get list of names needing population
		$query = "SELECT
			DISTINCT title
			FROM transliterations
			WHERE
				    field IN('" . implode ("', '", $this->transliterationNameMatchingFields) . "')
				AND inNameAuthorityList IS NULL							-- i.e. hasn't yet been allocated any value
				AND title NOT IN(
					SELECT DISTINCT surname AS title FROM othernames	-- 5965 values, so IN() performance will not be completely terrible
				)
			ORDER BY id
		;";
		$names = $this->databaseConnection->getPairs ($query);
		
		# Set user agent
		ini_set ('user_agent', 'Scott Polar Research Institute - Muscat catalogue conversion project. Obtaining (and caching) hit counts for c. 9,700 Russian author names.');
		
		# Work through each name
		$results = array ();
		$i = 0;
		foreach ($names as $name) {
			$i++;
			
			# Start a hit count and sources list
			$total = 0;
			$sources = array ();
			
			# Obtain the data for the query, as a phrase
			# Copyright note: The result data itself is *not* saved - only a count is done to determine presence or not
			# https://www.mediawiki.org/wiki/API:Search#Example
			$url = 'https://ru.wikipedia.org/w/api.php?action=query&list=search&srlimit=1&srprop=size|wordcount|timestamp|snippet&formatversion=2&srwhat=text&format=json&srsearch="' . urlencode ($name) . '"';
			$searchResult = @file_get_contents ($url);
			
			# Stop if server forbids access
			if (substr_count ($http_response_header[0], '403 Forbidden')) {
				echo "<p class=\"warning\">ERROR: Got HTTP response status <tt>{$http_response_header[0]}</tt> for request {$i}.</p>";
				break;
			}
			
			# Skip if result not OK
			// application::dumpData ($http_response_header);
			if (!substr_count ($http_response_header[0], '200 OK')) {
				echo "<p class=\"warning\">ERROR: Got HTTP response status <tt>{$http_response_header[0]}</tt> for search for <em>" . htmlspecialchars ($name) . '</em></p>';
				continue;
			}
			
			# Get the count of results
			$searchResultJson = json_decode ($searchResult, true);
			if ($searchResultJson['query']['searchinfo']['totalhits']) {
				$thisTotal = (int) $searchResultJson['query']['searchinfo']['totalhits'];
				$total += $thisTotal;
				$sources[] = "Wikipedia Russia ({$thisTotal})";
			}
			
			# Obtain the data
			$url = 'http://aleph.rsl.ru/F?func=find-a&CON_LNG=ENG&find_code=WAU&request="' . urlencode ($name) . '"';
			$searchResult = @file_get_contents ($url);
			
			# Scrape the result from the page
			if (preg_match ("/Records?\s+[0-9]+\s+\-\s+[0-9]+\s+of\s+([0-9]+)\s+/", $searchResult, $matches)) {
				$thisTotal = (int) $matches[1];
				$total += $thisTotal;
				$sources[] = "Russian State Library Catalog ({$thisTotal})";
			}
			
			# If no total, state no source
			if (!$total) {
				$sources[] = 'No results (tried Wikipedia Russia, Russian State Library Catalog)';
			}
			
			# Add the new result to the TSV
			$string = $name . "\t" . $total . "\t" . implode (', ', $sources) . "\n";
			file_put_contents ($file, $string, FILE_APPEND);
			
			# Be patient, to avoid unreasonable request rates
			$wait = 1.5;
			sleep ($wait);
		}
	}
	
	
	# Function to mark in the manually-reviewed ('ticked') names data
	private function markTickedNames ()
	{
		# Perform matches
		$query = "
			UPDATE transliterations
			INNER JOIN tickednames ON transliterations.id = tickednames.id
			SET inNameAuthorityList = tickednames.results
		;";
		$this->databaseConnection->query ($query);
		
		# Confirm success
		return true;
	}
	
	
	# Function to return the merge definition
	private function getMergeDefinition ()
	{
		# Get the latest version
		$query = "SELECT definition FROM {$this->settings['database']}.mergedefinition ORDER BY id DESC LIMIT 1;";
		if (!$definition = $this->databaseConnection->getOneField ($query, 'definition')) {
			echo "\n<p class=\"warning\"><strong>Error:</strong> The merge definition could not be retrieved.</p>";
			return false;
		}
		
		# Return the string
		return $definition;
	}
	
	
	# Function to process the merge definition
	private function parseMergeDefinition ($tsv, &$errorString = '')
	{
		# Convert the TSV to an associative array
		$tsv = trim ($tsv);
		require_once ('csv.php');
		$mergeDefinitionRaw = csv::tsvToArray ($tsv, $firstColumnIsId = true, $firstColumnIsIdIncludeInData = false, $errorMessage, $skipRowsEmptyFirstCell = true);
		
		# Rearrange by strategy
		$mergeDefinition = array ();
		foreach ($this->mergeTypes as $mergeType => $label) {
			foreach ($mergeDefinitionRaw as $marcFieldCode => $attributes) {
				$attributes['ACTION'] = trim ($attributes['ACTION']);
				$mergeDefinition[$mergeType][$marcFieldCode] = (strlen ($attributes[$mergeType]) ? $attributes['ACTION'] : false);
			}
		}
		
		# Return the definition
		return $mergeDefinition;
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
