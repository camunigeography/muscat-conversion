<?php

# Class to handle conversion of the data to MARC format
class import
{
	# Record processing order, to ensure lookup dependencies do not fail
	private $recordProcessingOrder = array (
		'/doc',		// A whole document consisting of a book, report, volume of conference proceedings, letter, etc.
		'/ser',		// A periodical
		'/art/j',	// A part document consisting of a paper in a journal
		'/art/in',	// A part document consisting of a book chapter or conference paper
	);
	
	# Record groupings for export
	private $recordGroupings = array (
		'serials'				=> array ('/ser'),
		'monographsarticles'	=> array ('/doc', '/art/in', '/art/j'),
	);
	
	# Define the file sets and their labels
	private $filesets = array (
		'migratewithitem'	=> 'Migrate to Alma, with item record(s)',
		'migrate'			=> 'Migrate to Alma',
		'suppresswithitem'	=> 'Suppress from OPAC, with item record(s)',
		'suppress'			=> 'Suppress from OPAC',
		'ignore'			=> 'Ignore record',
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
	
	
	
	# Constructor
	public function __construct ($muscatConversion, $marcConversion, $transliteration, $reports, $exportsProcessingTmp, $errorsFile)
	{
		# Create class property handles to the parent class
		$this->muscatConversion = $muscatConversion;
		$this->databaseConnection = $muscatConversion->databaseConnection;
		$this->settings = $muscatConversion->settings;
		$this->applicationRoot = $muscatConversion->applicationRoot;
		$this->baseUrl = $muscatConversion->baseUrl;
		$this->userIsAdministrator = $muscatConversion->userIsAdministrator;
		
		# Transliteration property handle
		$this->transliteration = $transliteration;
		
		# Other property handles
		$this->marcConversion = $marcConversion;
		$this->reports = $reports;
		$this->exportsProcessingTmp = $exportsProcessingTmp;
		$this->errorsFile = $errorsFile;
		
		# Define unicode symbols
		$this->doubleDagger = chr(0xe2).chr(0x80).chr(0xa1);
		
	}
	
	
	# Getters
	
	public function getFieldsIndexFields ()
	{
		return $this->fieldsIndexFields;
	}
	
	public function getRecordGroupings ()
	{
		return $this->recordGroupings;
	}
	
	public function getFilesets ()
	{
		return $this->filesets;
	}
	
	
	
	
	# Function to do the actual import
	public function run ($exportFiles, $importType, &$html)
	{
		# Start the HTML
		$html = '';
		
		# Define tick symbol
		$tick = '<img src="/images/icons/tick.png" alt="Tick" class="icon" />';
		
		# Start the error log
		$errorsHtml = '';
		
		# Ensure that GROUP_CONCAT fields do not overflow
		$sql = "SET SESSION group_concat_max_len := @@max_allowed_packet;";		// Otherwise GROUP_CONCAT truncates the combined strings, e.g. in createFieldsindexTable()
		$this->databaseConnection->execute ($sql);
		
		# Treat selection import as if it were full, but set a flag to filter during the MARC phase
		$isSelection = (substr_count ($importType, '-selection'));
		$importType = str_replace ('-selection', '', $importType);
		
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
			$html .= "\n<p>{$tick} The character processing has been done.</p>";
			
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
			$html .= "\n<p>{$tick} The data has been imported.</p>";
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
				$html .= "\n<p>{$tick} The external records currently in Voyager have been imported.</p>";
			}
		}
		
		# Create the MARC records, including their status
		if (($importType == 'full') || ($importType == 'marc')) {
			if (!$this->createMarcRecords ($isSelection, $errorsHtml /* amended by reference */)) {
				$this->logErrors ($errorsHtml, true);
				return false;
			}
			$html .= "\n<p>{$tick} The MARC versions of the records have been generated.</p>";
		}
		
		# Run option to export the MARC files for export and regenerate the Bibcheck report (included within the 'marc' (and therefore 'full') option above) if required
		if ($importType == 'exports') {
			$this->createMarcExports (true, !$isSelection, $errorsHtml /* amended by reference */);
			$html .= "\n<p>{$tick} The <a href=\"{$this->baseUrl}/export/\">export files and Bibcheck report</a> have been generated.</p>";
		}
		
		# End at this point if doing a partial selection, to avoid overwriting reports, listings and test unnecessarily
		if ($isSelection) {
			return true;
		}
		
		# Run (pre-process) the reports
		if (($importType == 'reports') || ($importType == 'full')) {
			$this->runReports ();
			$html .= "\n<p>{$tick} The <a href=\"{$this->baseUrl}/reports/\">reports</a> have been generated.</p>";
		}
		
		# Run (pre-process) the listings reports
		if (($importType == 'listings') || ($importType == 'full')) {
			$this->runListings ();
			$html .= "\n<p>{$tick} The <a href=\"{$this->baseUrl}/reports/\">listings reports</a> have been generated.</p>";
		}
		
		# Run (pre-process) the tests
		if (($importType == 'tests') || ($importType == 'full')) {
			$this->runTests ($errorsHtml /* amended by reference */);
			$html .= "\n<p>{$tick} The <a href=\"{$this->baseUrl}/reports/\">tests</a> have been generated.</p>";
		}
		
		# Run option to create the search tables
		if ($importType == 'searchtables') {
			$this->createSearchTables ();
			$html .= "\n<p>{$tick} The <a href=\"{$this->baseUrl}/search/\">search</a> tables have been (re-)generated.</p>";
		}
		
		# Write the errors to the errors log
		$this->logErrors ($errorsHtml, true);
		
		# Signal success
		return true;
	}
	
	
	# Logger
	private function logger ($message, $reset = false)
	{
		# Use the FCA logger
		$this->muscatConversion->logger ($message, $reset);
	}
	
	
	# Function to provide an error logger
	private function logErrors ($errorsHtml, $reset = false)
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
	
	
	# Function to create the export date as a string
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
			if ($record[$lineNumber] == '#') {
				unset ($record[$lineNumber]);
				continue;
			}
			
			# Join lines, separating by space, that do not begin with a key, and remove the orphaned carry-over
			if (!$keyed) {
				$record[$lineNumberAboveJoinable] .= ' ' . $record[$lineNumber];	// NB *urlgen space handling is fixed up later in convertToCsv ()
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
					return false;
				}
				
				# Determine the ID, as *q0
				$recordId = $matches[2];
			}
			
			# Skip the documentation records (within range 1-999)
			if ($recordId < $firstRealRecord) {return false;}
			
			# Fix up line-break handling shortcoming in trimCombineCarryoverLines for *urlgen fields (*doslink and *winlink have the same problem, but never used in conversion, and harder to deal with, so are ignored here), which are space-sensitive, e.g. /records/5265/ (test #910)
			if ($matches[1] == 'urlgen') {	// i.e. field = *urlgen
				$matches[2] = str_replace (' ', '', $matches[2]);
			}
			
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
		# CREATE TABLE AS ... wrongly results in a VARCHAR(344) column, resulting in record #195245 and others being truncated; length of at least VARCHAR(579) (as of Jan/2018) is needed
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
			ADD status VARCHAR(255) NULL COMMENT 'Status',
			ADD anywhere TEXT NULL COMMENT 'Text anywhere within record',
			ADD INDEX(title(255))	-- See: https://stackoverflow.com/a/8747703/180733
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
		$query = "UPDATE fieldsindex SET titleSortfield = LEFT(" . $this->databaseConnection->trimSql ('title', $this->marcConversion->getHtmlTags ()) . ', 200);';
		$this->databaseConnection->execute ($query);
		
		# Add the status value, excluding the overloaded 'SUPPRESS' status
		$query = "UPDATE fieldsindex
			LEFT JOIN catalogue_processed ON fieldsindex.id = catalogue_processed.recordId AND field = 'status'
			SET fieldsindex.status = catalogue_processed.value
			WHERE
				    fieldslist LIKE '%@status@%'	-- Filter for efficiency (30s -> 5s)
				AND value != 'SUPPRESS'	-- Exclude overloaded *status value
		;";
		$this->databaseConnection->execute ($query);
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
			DROP location,		-- Not in the search field list
			DROP status			-- ON ORDER etc., not needed, but different status field (for migration status) added below
		;';
		$this->databaseConnection->query ($query);
		
		# Add status field to enable suppression, and populate the data
		$query = "
			ALTER TABLE searchindex
			ADD COLUMN status ENUM('migratewithitem','migrate','suppresswithitem','suppress','ignore') COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Status' AFTER id,
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
		#!#I This probably does not catch all edge-cases, like italics, but seems to be 'good enough', with 26,319 matches
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
		
		# Add a field to contain the record language (first language); note that an *in or *j may also contain a *lang, so the top half should be used
		$sql = "ALTER TABLE catalogue_processed ADD recordLanguage VARCHAR(255) NOT NULL DEFAULT 'English' COMMENT 'Record language (first language)' AFTER preTransliterationUpgradeValue;";
		$this->databaseConnection->execute ($sql);
		
		# Set the main (top-level) record language for each shard
		$sql = "UPDATE catalogue_processed
			LEFT JOIN (
				SELECT
					recordId,
					value AS mainLanguage
				FROM catalogue_processed
				WHERE field = 'lang'
				AND topLevel = 1
			) AS mainLanguages
			ON mainLanguages.recordId = catalogue_processed.recordId
			SET catalogue_processed.recordLanguage = mainLanguage
			WHERE mainLanguage IS NOT NULL		/* I.e. don't overwrite the default English where no *lang specified */
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
		
		# Undo Muscat escaped asterisks @*, e.g. /records/19682/ (test #705) and many *ks / *location values; this is basically an SQL version of unescapeMuscatAsterisks ()
		$queries[] = "UPDATE catalogue_processed SET value = REPLACE(value,'@*','*');";
		
		# Italics, e.g. /records/205430/ (test #706)
		# "In order to italicise a Latin name in the middle of a line of Roman text, prefix the words to be italicised by '\v' and end the words with '\n'"
		$queries[] = "UPDATE catalogue_processed SET value = REPLACE(value,'{$replaceBackslash}v','<em>');";
		$queries[] = "UPDATE catalogue_processed SET value = REPLACE(value,'{$replaceBackslash}n','</em>');";	// \n does not mean anything special in REPLACE()
		# Also convert \V and \N similarly, e.g. /records/131259/ (test #707)
		$queries[] = "UPDATE catalogue_processed SET value = REPLACE(value,'{$replaceBackslash}V','<em>');";
		$queries[] = "UPDATE catalogue_processed SET value = REPLACE(value,'{$replaceBackslash}N','</em>');";	// \n does not mean anything special in REPLACE()
		
		# Correct the use of }o{ which has mistakenly been used to mean \gdeg, except for V}o{ (e.g. /records/163845/ (test #708) and N}o{ (e.g. /records/29493/ (test #709) which are a Ordinal indicator: https://en.wikipedia.org/wiki/Ordinal_indicator
		$queries[] = "UPDATE catalogue_processed SET value = REPLACE(value,'}o{','{$replaceBackslash}gdeg') WHERE value NOT LIKE '%V}o{%' AND value NOT LIKE '%n}o{%';";	// NB Have manually checked that record with V}o{ / N}o{ has no other use of }/{ characters
		
		# Diacritics (query takes 135 seconds), e.g. /records/148511/ (test #711), tidle in /records/207146/ (test #712), upper-case in /records/4932/ (test #713)
		$diacriticsTable = $this->marcConversion->getDiacriticsTable ();
		$queries[] = "UPDATE catalogue_processed SET value = " . $this->databaseConnection->replaceSql ($diacriticsTable, 'value', "'") . ';';
		
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
		
		# Rename locations of cases where the item has a location which has been destroyed during an audit, but which were erroneously marked as *location=Periodical
		$destroyed = application::textareaToList ($this->applicationRoot . '/tables/' . 'destroyed.txt', true, true);
		$queries[] = "UPDATE catalogue_processed
			SET value = 'Destroyed during audit'
			WHERE
				    recordId IN(" . implode (', ', $destroyed) . ")
				AND field = 'location'
				AND value = 'Periodical'
		;";		// 1,422 updates
		
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
			`language` VARCHAR(255) NOT NULL COMMENT 'Language of shard',
			`lpt` VARCHAR(255) NULL COMMENT 'Parallel title languages (*lpt, adjacent in hierarchy)',
			`title_latin` TEXT COLLATE utf8_unicode_ci COMMENT 'Title (latin characters), unmodified from original data',
			`title_latin_tt` TEXT COLLATE utf8_unicode_ci COMMENT '*tt if present',
			`title` TEXT COLLATE utf8_unicode_ci NULL DEFAULT NULL COMMENT 'Reverse-transliterated title',
			`title_spellcheck_html` TEXT COLLATE utf8_unicode_ci NULL DEFAULT NULL COMMENT 'Reverse-transliterated title (spellcheck HTML)',
			`title_forward` TEXT COLLATE utf8_unicode_ci COMMENT 'Forward transliteration from generated Cyrillic (BGN/PCGN)',
			`forwardCheckFailed` INT(1) NULL COMMENT 'Forward check failed?',
			`title_loc` TEXT COLLATE utf8_unicode_ci COMMENT 'Forward transliteration from generated Cyrillic (Library of Congress)',
			`inNameAuthorityList` INT(11) SIGNED NULL DEFAULT NULL COMMENT 'Whether the title value is in the LoC name authority list',
			PRIMARY KEY (`id`),
			INDEX(`field`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Table of transliterations'
		;";
		$this->databaseConnection->execute ($sql);
		
		# Populate the transliterations table with transliterable shards
		$this->populateTransliterableShards ();
		
		# Trigger a transliteration run
		$this->logger ('|-- In ' . __METHOD__ . ', running transliteration of entries in the transliterations table');
		$this->transliterateTransliterationsTable ();
		
		# Get the transliteration name matching fields
		$transliterationNameMatchingFields = $this->marcConversion->getTransliterationNameMatchingFields ();
		
		# Populate the Library of Congress name authority list and mark the matches (with inNameAuthorityList = -1)
		$this->logger ('|-- In ' . __METHOD__ . ', populating the Library of Congress name authority list');
		$this->populateLocNameAuthorities ($transliterationNameMatchingFields);
		
		# Populate the other names data and mark the matches (with inNameAuthorityList = count)
		$this->logger ('|-- In ' . __METHOD__ . ', populating the other names data');
		$this->populateOtherNames ($transliterationNameMatchingFields);
		
		# Mark items not matching a name authority as 0 (rather than leaving as NULL)
		$this->logger ('|-- In ' . __METHOD__ . ', marking items not matching a name authority');
		$query = "
			UPDATE transliterations
			SET inNameAuthorityList = -9999
			WHERE
				    transliterations.field IN('" . implode ("', '", $transliterationNameMatchingFields) . "')
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
	
	
	# Function to populate the transliterations table with transliterable shards, determining which are transliterable
	# This table essentially serves the purpose of (1) upgrading to LoC, as BGN/PCGN->Cyrillic->LoC, and (2) providing a debugging listing; it is not used in the live transliteration to Cyrillic (from now LoC in catalogue_processed)
	private function populateTransliterableShards ()
	{
		/* The algorithm, implemented below, for correctly determining which shards are transliterable, is:
			
			- Copy all shards in from the processed records table
			- Delete all shards whose field type is not in the fields in scope of transliteration list (e.g. *note)
			- Set the language of all shards to the first language (the record language, though this could be done freshly)
			
			- If the second half has a language (thus overriding the main language), set the language of all second-half to the second-half language
			- Handle the special case of *lpt for *t - if there is an *lpt in the same half of the record, update the language of that *t shared to the *lpt string
			- Handle the special case of *lto for *to - if there is an *lto in the same half of the record, update the language of that *to shared to the *lto value
			- Handle the special case of *nt=BGNRus: if there is an *nt=BNGRus, adjust the language of the n1/n2/nd values to Russian
			- Handle the special case of *nt=None: if there is an *nt=None, adjust the language of the n1/n2/nd values to None (or anything really, as it will be deleted), e.g. /records/151048/
			- Handle the special case of *nt=LOCRus: if there is an *nt=None, adjust the language of the n1/n2/nd values to LOCRus
			
			- Delete all shards with [Titles fully in square brackets like this]
			- Delete all *pu shards whose value is a token: '[n.pub.]', 'n.pub.', '[n.p.]'
			- Delete all *n1 shards whose value is token: 'Anon.'
			
			- Delete all shards in the list of special-case shard numbers that have been manually reviewed
			
			- Finally, delete anything that doesn't contain 'Russian' (e.g. equals Russian or contains in 'A = B' style format) or LOCRus as the language
			- We now only have shards with either (a) Russian, or (b) LOCRus, or (c) Parallel title list
			
			- Set the title_latin_tt value as before (needs checking)
			- Run all through the transliterator as before
		*/
		
		# Populate the transliterations table, firstly copying all rows in
		# Exclude shards whose field type is not in the scope of transliteration
		# Set the language of all shards to the record (top-level) language
		$this->logger ('|-- In ' . __METHOD__ . ', populating the transliterations table');
		$query = "
			INSERT INTO transliterations (id, recordId, field, topLevel, xPath, language, title_latin)
				SELECT
					id,
					recordId,
					field,
					topLevel,
					xPath,
					recordLanguage AS language,
					value AS title_latin
				FROM catalogue_processed
				WHERE field IN('" . implode ("', '", $this->marcConversion->getTransliterationUpgradeFields ()) . "')
				ORDER BY recordId, LENGTH(id), id
		;";
		$this->databaseConnection->query ($query);	// 1,198,204 rows inserted
		
		# If the second half has a language (thus overriding the main language), set the language of all second-half to the second-half language (e.g. Russian record but /art/j/tg/lang = 'English'); e.g. /records/9820/ , /records/27093/ , /records/57745/
		$this->logger ('|-- In ' . __METHOD__ . ', setting bottom-half shards with an associated local language to that language');
		$query = "
			UPDATE transliterations
			LEFT JOIN (
				SELECT
					recordId,
					value AS lowerLanguage
				FROM catalogue_processed
				WHERE
					    topLevel = 0
					AND field = 'lang'
					AND value != recordLanguage
			) AS lowerLanguages		-- 365 records
			ON lowerLanguages.recordId = transliterations.recordId
			SET transliterations.language = lowerLanguage
			WHERE
				    lowerLanguage IS NOT NULL		/* I.e. don't overwrite the default where not in the list */
				AND transliterations.topLevel = 0
		;";
		$this->databaseConnection->query ($query);	// 413 rows affected
		
		# Handle the special case of *lpt for *t - if there is an *lpt in the same half of the record, update the language of that *t shared to the *lpt string, e.g. /records/6498/
		# This gives 724 updates, which exactly matches 724 results for `SELECT * FROM `catalogue_processed` WHERE `field` = 'lpt';`
		# Works fine for "English = Russian" but where the record is marked as *lang=English, e.g. /records/135449/ or /records/172050/ (test #845)
		$this->logger ('|-- In ' . __METHOD__ . ', setting the *lpt parallel title language for *t');
		$query = "
			UPDATE transliterations
			JOIN catalogue_processed ON transliterations.recordId = catalogue_processed.recordId
			SET
				language = value,
				lpt = value
			WHERE
				    catalogue_processed.field = 'lpt'
				AND transliterations.field = 't'
				AND catalogue_processed.topLevel = transliterations.topLevel
		;";
		$this->databaseConnection->query ($query);	// 724 rows updated
		
		# Handle the special case of *lto for *to - if there is an *lto in the same half of the record, update the language of that *to shared to the *lto value, e.g. /records/52557/
		# 1549 shards; matches 1546 results for `SELECT recordId FROM catalogue_processed where field = 'lto';` plus three records (51402, 88661, 188949) which have multiple *to
		#!# Not yet used in MARC conversion
		$this->logger ('|-- In ' . __METHOD__ . ', retaining *to with relevant *lto');
		$query = "
			UPDATE transliterations
			JOIN catalogue_processed ON transliterations.recordId = catalogue_processed.recordId
			SET
				language = value
			WHERE
				    catalogue_processed.field = 'lto'
				AND transliterations.field = 'to'
				AND catalogue_processed.topLevel = transliterations.topLevel
		;";
		$this->databaseConnection->query ($query);	// 1540 rows affected
		$query = "UPDATE transliterations SET language = 'French' WHERE recordId = 88661 AND field = 'to' AND title_latin LIKE 'Voyage autour%';";	// Three records have multiple *to; 88661 is the only one of these three where the *to language varies
		$this->databaseConnection->query ($query);
		
		# Add support for *nt (within *a, *al and *n), e.g. None/BGNRus/LOCRus/BGNYak
		# E.g. /records/150203/ which has cases of *nt = 'None' (meaning do not transliterate fields at the same level of the hierarchy)
		# E.g. /records/178377/ (test #729) and *nt = 'BGNRus' (which is an explicit override to whatever the language is, enabling Russian people in an English record to be handled properly)
		# E.g. /records/102036/ (test #728); other values such as "BGNYak" should be ignored
		$this->logger ('|-- In ' . __METHOD__ . ', applying *nt language values');
		$this->applyNtLanguageValues ();
		
		# Delete non-Russian records
		$query = "
			DELETE FROM transliterations
			WHERE language NOT LIKE BINARY '%Russian%' AND language NOT LIKE '%LOCRus%'
		;";
		$this->databaseConnection->query ($query);	// 1,054,823 rows affected, leaving 143,381
		
		# Exclude [Titles fully in square brackets like this], except known special cases
		$this->logger ('|-- In ' . __METHOD__ . ', excluding titles fully in square brackets');
		$transliterableFullStringsInBrackets = $this->transliteration->getTransliterableFullStringsInBrackets ();	// Dependency: catalogue_processed
		$query = "
			DELETE FROM transliterations
			WHERE
				    LEFT (title_latin, 1) = '['
				AND RIGHT(title_latin, 1) = ']'
				AND title_latin NOT IN '" . implode ("','", $transliterableFullStringsInBrackets) . "'
		;";
		$this->databaseConnection->query ($query);	// 198 rows deleted
		
		# In the special case of the *pu field, clear out special tokens
		$this->logger ('|-- In ' . __METHOD__ . ', clearing out special tokens in publisher entry');
		$query = "
			DELETE FROM transliterations
			WHERE
				    field = 'pu'
				AND title_latin IN('[n.pub.]', 'n.pub.', '[n.p.]')
		;";
		$this->databaseConnection->query ($query);
		
		# For the *n1 field, clear out cases consisting of special tokens (e.g. 'Anon.') because no attempt has been made to add protected string support for name authority checking
		# The records themselves (e.g. /records/6451/ ) are fine as these tokens are defined in the protected strings table so will be protected from transliteration
		$this->logger ('|-- In ' . __METHOD__ . ', clearing out special tokens in *n1 entry');
		$query = "
			DELETE FROM transliterations
			WHERE
				    field IN('" . implode ("', '", $this->marcConversion->getTransliterationNameMatchingFields ()) . "')
				AND title_latin IN('Anon.')
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
		
		# Handle the special case of *note, which should only retain 'Contents: ' notes known to be Russian
		$this->logger ('|-- In ' . __METHOD__ . ', retaining *note with relevant Contents note known to be in Russian');
		$query = "
			DELETE FROM transliterations
			WHERE
					field = 'note'
				AND (
					   title_latin NOT LIKE 'Contents:%'
					OR language != 'Russian'
					OR recordId IN(183257,197702,204261,210284,212106,212133,212246)	/* NB If updating, the same list of numbers should also be updated in macro_generate505Note */
				)
		;";		// 29343 rows affected (from original 29369 inserted), leaving 26, which correctly matches `SELECT * FROM `catalogue_processed` WHERE `field` LIKE 'note' and value like 'Contents:%' AND `recordLanguage` LIKE 'Russian' AND recordId NOT IN(183257,197702,204261,210284,212106,212133,212246);`
		$this->databaseConnection->query ($query);
		
		# Delete all shards in the list of special-case shard numbers that have been manually reviewed; e.g. *pu in /records/1888/ (test #789)
		$transliterationProtectedShards = application::textareaToList ($this->applicationRoot . '/tables/' . 'transliterationProtectedShards.txt', true, true, true);
		$query = "DELETE FROM transliterations WHERE id IN('" . implode ("', '", $transliterationProtectedShards) . "');";
		$this->databaseConnection->query ($query);
	}
	
	
	# Subroutine to apply *nt values to their adjacent *n1/*n2/*nd values, done outside SQL as the xPathWithIndex is not yet available
	private function applyNtLanguageValues ()
	{
		# Get all shards of all ~300 records which contain *nt, sorted backwards for easier subsequent looping (*nt is always after *n1/*n2/*nd)
		$query = "
			SELECT
				catalogue_processed.id,
				catalogue_processed.recordId,
				catalogue_processed.field,
				catalogue_processed.value
			FROM catalogue_processed
			LEFT JOIN fieldsindex ON catalogue_processed.recordId = fieldsindex.id
			WHERE fieldslist LIKE '%@nt@%'
			ORDER BY recordId DESC, line DESC
		;";
		$shards = $this->databaseConnection->getData ($query);
		
		# Regroup by record ID to make looping easier
		$records = application::regroup ($shards, 'recordId', false);
		
		# For each record, loop through the shards (backwards) to find each *nt, and mark the immediate subsequent shards (before a space) as acquiring that *nt value for its language
		$updates = array ();
		foreach ($records as $shards) {
			$language = NULL;
			foreach ($shards as $shard) {
				
				# Switch on the language when found, and skip to next
				if ($shard['field'] == 'nt') {
					$language = $shard['value'];
					continue;
				}
				
				# Switch off the language when a container is reached, and skip to next
				if (!strlen ($shard['value'])) {
					$language = NULL;
					continue;
				}
				
				# If the language is on, assign it to the present shard
				if ($language) {
					$id = $shard['id'];
					if ($language == 'BGNRus') {$language = 'Russian';}		// Rewrite token name
					$updates[$id] = array ('language' => $language);
				}
			}
		}
		
		# Apply the updates to the transliterations table
		$this->databaseConnection->updateMany ($this->settings['database'], 'transliterations', $updates);
	}
	
	
	# Function to run the transliterations in the transliteration table; this never alters title_latin which should be set only in createTransliterationsTable, read from the post- second-pass XML records
	private function transliterateTransliterationsTable ()
	{
		# Obtain the raw values, indexed by shard ID; LOCRus cases are not transliterated and are handled below
		$query = "SELECT id,title_latin,lpt FROM transliterations WHERE language != 'LOCRus';";
		$data = $this->databaseConnection->getData ($query, "{$this->settings['database']}.transliterations");
		
		# Transliterate the strings (takes around 4 minutes)
		$this->logger ('  |-- In ' . __METHOD__ . ', running transliterateBgnLatinToCyrillicBatch');
		$language = 'Russian';
		$dataTransliterated = $this->transliteration->transliterateBgnLatinToCyrillicBatch ($data, $language, $cyrillicPreSubstitutions /* passed back by reference */, $protectedPartsPreSubstitutions /* passed back by reference */);
		
		# Start spellchecking data phase
		$this->logger ('  |-- In ' . __METHOD__ . ', running spellchecking data phase');
		
		# Define words to add to the dictionary
		$langCode = 'ru_RU';
		$addToDictionary = application::textareaToList ($this->applicationRoot . '/tables/' . "dictionary.{$langCode}.txt", true, true);
		
		# Obtain an HTML string with embedded spellchecking data
		$dataTransliteratedSpellcheckHtml = application::spellcheck ($cyrillicPreSubstitutions, $langCode, $this->transliteration->getProtectedSubstringsRegexp (), $this->databaseConnection, $this->settings['database'], true, $addToDictionary);
		foreach ($dataTransliteratedSpellcheckHtml as $id => $cyrillicPreSubstitution) {
			$dataTransliteratedSpellcheckHtml[$id] = $this->transliteration->reinstateProtectedSubstrings ($cyrillicPreSubstitution, $protectedPartsPreSubstitutions[$id]);
		}
		
		# Do a comparison check by forward-transliterating the generated Cyrillic (takes around 15 seconds)
		$this->logger ('  |-- In ' . __METHOD__ . ', running transliterateCyrillicToBgnLatin');
		$forwardBgnTransliterations = $this->batchTransliterateStrings ($dataTransliterated, 'transliterateCyrillicToBgnLatin');
		
		# Add new Library of Congress (LoC) transliteration from the generated Cyrillic (takes around 1 second)
		$this->logger ('  |-- In ' . __METHOD__ . ', running transliterateCyrillicToLocLatin');
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
		$this->logger ('  |-- In ' . __METHOD__ . ', batch-inserting the compiled transliterations data');
		$this->databaseConnection->updateMany ($this->settings['database'], 'transliterations', $conversions, $chunking = 5000);
		
		# Set the title_loc for LOCRus cases, simply by copying them across, as the data is already in LOC; e.g. /records/7702/ (test #783)
		$query = "UPDATE transliterations SET title_loc = title_latin WHERE language = 'LOCRus';";	// 61 lines updated
		$data = $this->databaseConnection->query ($query);
		
		# Fix up special case of Yakut title for Russian book to avoid erroneous reversibility check failure in /reports/transliterations/?filter=1 report; see: /records/65817/ (tests #904, #905)
		$query = "UPDATE transliterations SET title_forward = title_latin, forwardCheckFailed = NULL, title_spellcheck_html = title WHERE id = '65817:7';";
		$data = $this->databaseConnection->query ($query);
		
		# Signal success
		return true;
	}
	
	
	# Function to batch-transliterate an array strings; this is basically a wrapper which packages the strings list as a TSV which is then transliterated as a single string, then sent back unpacked
	# Datasets involving protected strings must not be batched, i.e. must not use this, because if e.g. line X contains e.g. Roman numeral 'C' and line Y contains 'Chukotke' this will result in replacements like: '<||1267||>hukotke', i.e. cross-talk between the lines
	private function batchTransliterateStrings ($strings, $transliterationFunction)
	{
		# Define supported language
		#!#H Need to remove explicit dependency
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
	
	
	# Function to populate the LoC name authority data
	private function populateLocNameAuthorities ($transliterationNameMatchingFields, &$error = false)
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
			WHERE transliterations.field IN('" . implode ("', '", $transliterationNameMatchingFields) . "')
		;";
		$this->databaseConnection->query ($query);
		
		# Confirm success
		return true;
	}
	
	
	# Function to populate the other names data
	private function populateOtherNames ($transliterationNameMatchingFields, &$error = false)
	{
		# Define the other names data file
		$file = $this->applicationRoot . '/tables/othernames.tsv';
		
		# Initialise the other names data table with existing data
		if (!$this->buildOtherNamesTable ($file, $error)) {return false;}
		
		# Add any new values to the data file
		$this->obtainSaveOtherNamesData ($file, $transliterationNameMatchingFields);
		
		# Re-initialise the other names data table with existing data
		if (!$this->buildOtherNamesTable ($file, $error)) {return false;}
		
		# Perform matches
		$query = "
			UPDATE transliterations
			INNER JOIN othernames ON transliterations.title = othernames.surname
			SET inNameAuthorityList = othernames.results
			WHERE transliterations.field IN('" . implode ("', '", $transliterationNameMatchingFields) . "')
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
	private function obtainSaveOtherNamesData ($file, $transliterationNameMatchingFields)
	{
		# Get list of names needing population
		$query = "SELECT
			DISTINCT title
			FROM transliterations
			WHERE
				    field IN('" . implode ("', '", $transliterationNameMatchingFields) . "')
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
	public function getMergeDefinition ()
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
	
	
	# Function to return the MARC parser definition
	public function getMarcParserDefinition ()
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
	
	
	# Function to process the merge definition
	public function parseMergeDefinition ($tsv, &$errorString = '')
	{
		# Convert the TSV to an associative array
		$tsv = trim ($tsv);
		require_once ('csv.php');
		$mergeDefinitionRaw = csv::tsvToArray ($tsv, $firstColumnIsId = true, $firstColumnIsIdIncludeInData = false, $errorMessage, $skipRowsEmptyFirstCell = true);
		
		# Rearrange by strategy
		$mergeDefinition = array ();
		$mergeTypes = $this->marcConversion->getMergeTypes ();
		foreach ($mergeTypes as $mergeType => $label) {
			foreach ($mergeDefinitionRaw as $marcFieldCode => $attributes) {
				$attributes['ACTION'] = trim ($attributes['ACTION']);
				$mergeDefinition[$mergeType][$marcFieldCode] = (strlen ($attributes[$mergeType]) ? $attributes['ACTION'] : false);
			}
		}
		
		# Return the definition
		return $mergeDefinition;
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
		$supportedReverseTransliterationLanguages = $this->transliteration->getSupportedReverseTransliterationLanguages ();
		$query = "UPDATE catalogue_xml
			LEFT JOIN catalogue_processed ON
				    catalogue_xml.id = catalogue_processed.recordId
				AND field = 'lang'
				AND value LIKE '% = %'
				AND value REGEXP '(" . implode ('|', array_keys ($supportedReverseTransliterationLanguages)) . ")'
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
	
	
	# Function to upgrade the shards consisting of transliterated strings to Library of Congress (LoC), e.g. /records/1043/ (test #991). This copies back and over the processed table with the new LoC transliterations, saving the pre-transliteration upgrade value
	private function upgradeTransliterationsToLoc ()
	{
		# Log start
		$this->logger ('Starting ' . __METHOD__);
		
		# Upgrade the processed record shards containing transliteration to use the new Library of Congress transliterations, and save the original BGN/PCGN value, e.g. /records/1043/ (test #991)
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
			$records = $this->muscatConversion->getRecords ($ids, 'processed');
			
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
			if (!$this->databaseConnection->execute ($sql)) {	// 4.5 minutes
				$html  = "<p class=\"warning\">Setting the xPath values query failed.</p>";
				$errorsHtml .= $html;
				return false;
			}
		}
		
		# Take down the temporary table
		if ($pathSeedingOnly) {
			$sql = "DROP TABLE {$this->settings['database']}.catalogue_processed_xpaths_temp;";
			$this->databaseConnection->execute ($sql);
		}
		
		# Signal success
		return true;
	}
	
	
	# Function to define the schema
	public function getSchema ($flattenedWithContainership = false, $formatted = false)
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
	
	
	# Function to replace location=Periodical in the processed records with the real, looked-up values; dependencies: catalogue_processed with xPath field populated
	# NB This matching is done before the transliteration phase, so that the /art/j/tg/t matches its parent (e.g. /records/167320/ joins to its parent /records/33585/ ) and then AFTER that it gets upgraded
	#!# There is still the problem that the target name itself does not get upgraded; UPDATE 26/1/2018: Believe this is now done - and the comment is wrong, as the target is *t which was always being done - create a test /records/30493/ to /records/33201/ (which is a pair of a /doc/ts[1] in Russian with location=Russian and location=Russian and not explicit *kg link
	private function processPeriodicalLocations (&$errorsHtml)
	{
		# Log start
		$this->logger ('Starting ' . __METHOD__);
		
		# Assign XPaths to catalogue_processed; this unfortunate dependency means that the XML processing has to be run twice
		$this->createXmlTable ($pathSeedingOnly = true, $errorsHtml);
		
		# Firstly handle explicit matches, by replacing *kg in the processed records with the real, looked-up values; e.g. /records/23120/ (test #1060)
		# Explicit matches using *kg take priority; this switches off the title-baed lookup for replacing *location=Periodical, e.g. /records/43303/ (test #990), which has *kg=23052 rather than wrongly matching using *ts from /records/72770/
		# This maintains the field = 'location' AND value = 'Periodical' constraint so that records like /record/15523/ (tests #1061, #1062) and /records/39757/ do not wipe out another *location (e.g. hard-coded shelf location)
		# *kg parents are: 2270 *doc, 1455 *ser, 11 *art, e.g. `SELECT * FROM catalogue_processed WHERE field = 'doc' and recordId IN( SELECT distinct value FROM catalogue_processed WHERE field = 'kg' ORDER BY value DESC);`
		#!# Currently there are some parent records with location[2] - should that be ignored? - see `SELECT * FROM catalogue_processed WHERE field = 'location' and xPathWithIndex LIKE '%/location[2]' AND recordId IN( SELECT DISTINCT value FROM `catalogue_processed` WHERE field = 'kg' ORDER BY value DESC);`
		#!# Should we overwrite just location=Periodical or any other location value, e.g. hard-coded as particularly exist with *doc records?
		$this->logger ('Replacing location=Periodical for explicit match with *kg');
		$sql = "
			UPDATE catalogue_processed
			LEFT JOIN catalogue_processed AS kgLookup ON
				    catalogue_processed.recordId = kgLookup.recordId
				AND kgLookup.field = 'kg'
			LEFT JOIN catalogue_processed AS valueLookup ON
				    kgLookup.value = valueLookup.recordId
				AND valueLookup.field = 'location'
				AND valueLookup.xPathWithIndex LIKE '%/location[1]'
			SET catalogue_processed.value = valueLookup.value
			WHERE
				    catalogue_processed.field = 'location' AND catalogue_processed.value = 'Periodical'
				AND kgLookup.value IS NOT NULL
				AND valueLookup.value IS NOT NULL
		;";
		$this->databaseConnection->execute ($sql);
		
		# Start implicit match
		$this->logger ('Replacing location=Periodical for implicit match using title');
		
		# Create a table of periodicals, with their title and location(s), clearing it out first if existing from a previous import
		# Both *art and *doc are able to link to *ser
		# Link for *doc found with: SELECT * FROM `catalogue_rawdata` join catalogue_rawdata AS lookup ON catalogue_rawdata.recordId = lookup.recordId AND lookup.field = 'doc' where catalogue_rawdata.field = 'location' and catalogue_rawdata.value = 'Periodical'
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
		# Confirmed (30/8/2019) that all *ser end up in this list, and end up with the correct location
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
		# NB /records/209527/ is an example with two *ts values - the first is used in Muscat as the match - there are 1170 cases of /ts[2] but these are ignored as per Muscat
		#!# In a three-level hiearchy (article in AoG1, which is in AoG), we cannot be sure that the longest is found first, e.g. *ts="Annals of Glaciology 1" should find (parent *t=Annals of Glaciology 1" before parent *t="Annals of Glaciology" if both exist, and it should not match against *t="Annals of Glaciology 10"
		#!# Tests needed here
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
	private function createMarcRecords ($isSelection, &$errorsHtml)
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
				status ENUM('migratewithitem','migrate','suppresswithitem','suppress','ignore') NULL DEFAULT NULL COMMENT 'Status',
				itemRecords INT(4) NULL COMMENT 'Create item record?',
				mergeType VARCHAR(255) NULL DEFAULT NULL COMMENT 'Merge type',
				mergeVoyagerId VARCHAR(255) NULL DEFAULT NULL COMMENT 'Voyager ID for merging',
				marcPreMerge TEXT NULL COLLATE utf8_unicode_ci COMMENT 'Pre-merged MARC representation of local Muscat record',
				marc TEXT COLLATE utf8_unicode_ci COMMENT 'MARC representation of Muscat record',
				bibcheckErrors TEXT NULL COLLATE utf8_unicode_ci COMMENT 'Bibcheck errors, if any',
				filterTokens VARCHAR(255) NULL DEFAULT NULL COMMENT 'Filtering tokens for suppression/ignoration',
			  PRIMARY KEY (id)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='MARC representation of Muscat records'
		;";
		$this->databaseConnection->execute ($sql);
		
		# Cross insert the IDs
		$this->logger ('Cross-inserting IDs to catalogue_marc table');
		$query = 'INSERT INTO catalogue_marc (id) (SELECT DISTINCT(recordId) FROM catalogue_rawdata);';
		$this->databaseConnection->execute ($query);
		
		# Add support for selection list
		$selectionList = array ();
		if ($isSelection) {
			
			# Obtain the list
			$selectionList = $this->getSelectionList ();
			$this->logger ('In ' . __METHOD__ . ', using filter list, of ' . number_format (count ($selectionList)) . ' records');
			
			# Delete unwanted rows
			$sql = 'DELETE FROM catalogue_marc WHERE id NOT IN(' . implode (',', $selectionList) . ');';
			$this->databaseConnection->execute ($sql);
		}
		
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
		
		# Add in the Voyager merge data fields, retrieving the resulting data
		$mergeData = $this->marcRecordsSetMergeFields ();
		
		# Get the schema
		if (!$marcParserDefinition = $this->getMarcParserDefinition ()) {return false;}
		
		# Get the merge definition
		if (!$mergeDefinition = $this->parseMergeDefinition ($this->getMergeDefinition ())) {return false;}
		
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
				$records = $this->muscatConversion->getRecords ($ids, 'xml');
				
				# Arrange as a set of inserts
				$inserts = array ();
				foreach ($records as $id => $record) {
					
					# Convert to MARC, and retrieve metadata
					$mergeType       = (isSet ($mergeData[$id]) ? $mergeData[$id]['mergeType'] : false);
					$mergeVoyagerId	 = (isSet ($mergeData[$id]) ? $mergeData[$id]['mergeVoyagerId'] : false);
					$marc = $this->marcConversion->convertToMarc ($marcParserDefinition, $record['xml'], $mergeDefinition, $mergeType, $mergeVoyagerId);
					$marcPreMerge = $this->marcConversion->getMarcPreMerge ();
					$filterTokens = $this->marcConversion->getFilterTokensString ();
					$itemRecords = $this->marcConversion->getItemRecords ();
					$status = $this->marcConversion->getStatus ();
					if ($marcErrorHtml = $this->marcConversion->getErrorHtml ()) {
						$html = $marcErrorHtml;
						$errorsHtml .= $html;
					}
					
					# Assemble the insert for this record
					$inserts[$id] = array (
						'id' => $id,
						'marcPreMerge' => $marcPreMerge,
						'marc' => $marc,
						'itemRecords' => $itemRecords,
						'filterTokens' => $filterTokens,	// E.g. examples: "SUPPRESS-MISSINGQ" or multiple "IGNORE-NOTINSPRI, IGNORE-LOCATIONUL"
						'status' => $status,				// E.g. 'migratewithitem', derived from filterTokens and itemRecords count
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
				$this->logger ('|- In ' . __METHOD__ . ": {$recordType}, adding " . count ($inserts) . 'r; second pass: @' . count ($marcSecondPass) . 'r; memory: ' . $memoryUsageMb . 'MB');
				if (!$this->databaseConnection->insertMany ($this->settings['database'], 'catalogue_marc', $inserts, false, $onDuplicateKeyUpdate = true)) {
					$html  = "<p class=\"warning\">Error generating MARC, stopping at batched ({$id}):</p>";
					$html .= application::dumpData ($this->databaseConnection->error (), false, true);
					$errorsHtml .= $html;
					return false;
				}
				
				# Detect memory leaks, enabling the import to shut down cleanly but report the problem
				if (!$selectionList && $memoryUsageMb > 80) {	// Selection list mode allows memory leak to continue
					$memoryErrorMessage = '*** Memory leak detected; import system has been stopped. ***';
					$this->logger ($memoryErrorMessage);
					$errorsHtml .= "<p class=\"warning\">{$memoryErrorMessage}</p>";
					return false;
				}
			}
		}
		
		# Generate the output files
		$this->createMarcExports (false, !$isSelection, $errorsHtml /* amended by reference */);
		
		# Signal success
		return true;
	}
	
	
	# Function to get the selection list, essentially a parsed version of muscatConversion::getSelectionDefinition ()
	public function getSelectionList ()
	{
		# Get the latest version and whether tests are required
		$query = "SELECT * FROM {$this->settings['database']}.selectiondefinition ORDER BY id DESC LIMIT 1;";
		$definition = $this->databaseConnection->getOne ($query, 'definition');
		
		# Convert to list
		$selection = application::textareaToList ($definition['definition']);
		
		# Add in records from the test system if required
		if ($definition['tests']) {
			$query = "SELECT DISTINCT recordId FROM tests ORDER BY recordId;";
			$recordIdsInTests = $this->databaseConnection->getPairs ($query);
			$selection = array_merge ($selection, $recordIdsInTests);
			$selection = array_unique ($selection);
		}
		
		# Return the list
		return $selection;
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
	
	
	# Function to add in the Voyager merge data fields
	private function marcRecordsSetMergeFields ()
	{
		# Records to suppress; this deliberately uses //ka rather than //k2[1]/ka (and ditto kc), so that the multiple types are picked up as "unsupported merge type" errors
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
	
	
	# Helper function to unescape Muscat asterisks; e.g. /records/1005/ (test #485)
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
			v VARCHAR(255) DEFAULT NULL COMMENT '{$this->doubleDagger}v value in result',
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
			$result = $this->marcConversion->macro_generate490 ($ts, NULL, $errorString_ignored, $matchedRegexp);
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
	private function createMarcExports ($regenerateReport = false, $isFullSet = true, &$errorsHtml)
	{
		# Log start
		$this->logger ('Starting ' . __METHOD__);
		
		# If regenerating, clear existing Bibcheck errors in the database
		if ($regenerateReport) {
			$this->databaseConnection->update ($this->settings['database'], 'catalogue_marc', array ('bibcheckErrors' => NULL));
		}
		
		# Create a set for each of the record type groups, defined as label => record types
		foreach ($this->recordGroupings as $type => $limitToRecordTypes) {
			
			# Generate the output files and attach errors to the database records
			require_once ('createMarcExport.php');
			$createMarcExport = new createMarcExport ($this->muscatConversion, $this->applicationRoot, $this->recordProcessingOrder);
			foreach ($this->filesets as $fileset => $label) {
				$createMarcExport->createExport ($fileset, array (), $type, $limitToRecordTypes, $errorsHtml /* amended by reference */);
			}
			
			# Create a selected export group also
			if ($isFullSet) {
				$selectionList = $this->getSelectionList ();
				$createMarcExport->createExport ('selection', $selectionList, $type, $limitToRecordTypes, $errorsHtml /* amended by reference */);
			}
		}
		
		# If required, regenerate the error reports depending on the data
		if ($regenerateReport) {
			$this->runReport ('bibcheckerrors', true);
			$this->runReport ('article245', true);
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
		$reports = $this->muscatConversion->getReports ();
		foreach ($reports as $reportId => $description) {
			
			# Skip listing type reports, which implement data handling directly (and optional countability support), and which are handled separately in runListings ()
			if ($this->muscatConversion->isListing ($reportId)) {continue;}
			
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
	public function runReport ($reportId, $clearFirst = false)
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
		$reports = $this->muscatConversion->getReports ();
		foreach ($reports as $reportId => $description) {
			if ($this->muscatConversion->isListing ($reportId)) {
				$reportFunction = 'report_' . $reportId;
				$this->reports->$reportFunction ();
			}
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
			  `negativeTest` INT(1) DEFAULT NULL COMMENT 'Negative test?',
			  `indicatorTest` INT(1) DEFAULT NULL COMMENT 'Indicator test?',
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
		
		# Disable merging-related tests if required
		if (!$this->settings['mergingEnabled']) {
			foreach ($tests as $id => $test) {
				if (preg_match ('/^Merging/', $test['description'])) {
					unset ($tests[$id]);
				}
			}
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
			
			# Convert the records
			$marcParserDefinition = $this->getMarcParserDefinition ();
			$xmlRecords = $this->muscatConversion->getRecords ($ids, 'xml', false, false, $searchStable = (!$this->userIsAdministrator));
			$marcRecords = array ();
			foreach ($xmlRecords as $id => $record) {
				$mergeType       = (isSet ($mergeData[$id]) ? $mergeData[$id]['mergeType'] : false);
				$mergeVoyagerId	 = (isSet ($mergeData[$id]) ? $mergeData[$id]['mergeVoyagerId'] : false);
				$marcRecords[$id]['marc']			= $this->marcConversion->convertToMarc ($marcParserDefinition, $record['xml'], $mergeDefinition, $mergeType, $mergeVoyagerId);
				$marcRecords[$id]['itemRecords']	= $this->marcConversion->getItemRecords ();
				$marcRecords[$id]['filterTokens']	= $this->marcConversion->getFilterTokensString ();
				$marcRecords[$id]['status']			= $this->marcConversion->getStatus ();
			}
			$this->databaseConnection->updateMany ($this->settings['database'], 'catalogue_marc', $marcRecords);
		}
		
		# Pre-load the MARC records
		if (!$marcRecords = $this->databaseConnection->select ($this->settings['database'], 'catalogue_marc', array ('id' => $ids), array ('id', 'marc', 'status'))) {
			$errorHtml .= "<p class=\"warning\"><strong>Error:</strong> Could not obtain MARC records used in tests for test result generation.</p>";
			return false;
		}
		
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
			$record = $this->marcConversion->parseMarcRecord ($marcRecords[$recordId]['marc'], false);
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
			if ($tests[$id]['marcField'] == 's') {	// Status
				$lines[] = $marcRecords[$recordId]['status'];	// For display
			} else {
				foreach ($fieldsMatching as $field) {
					foreach ($record[$field] as $line) {
						$lines[] = $line['fullLine'];
					}
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
			
			# Status test
			if ($tests[$id]['marcField'] == 's') {
				if ($test['expected'] == $marcRecords[$recordId]['status']) {
					$tests[$id]['result'] = true;	// isFound
				}
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
	
	
}

?>
