<?php

# Class to generate the MARC21 output as text and attach any errors to the database records
class createMarcExport
{
	# Constructor
	public function __construct ($muscatConversion, $applicationRoot, $recordProcessingOrder)
	{
		# Create class property handles to the parent class
		$this->databaseConnection = $muscatConversion->databaseConnection;
		$this->settings = $muscatConversion->settings;
		$this->baseUrl = $muscatConversion->baseUrl;
		
		# Create other handles
		$this->applicationRoot = $applicationRoot;
		$this->recordProcessingOrder = $recordProcessingOrder;
		
		# Define unicode symbols
		$this->doubleDagger = chr(0xe2).chr(0x80).chr(0xa1);
		
	}
	
	
	
	# Main entry point
	public function createExport ($fileset, $selectionList = array (), $typeLabel, $limitToRecordTypes, &$errorsHtml)
	{
		# Clear the current file(s)
		$directory = $_SERVER['DOCUMENT_ROOT'] . $this->baseUrl;
		$filenameMarcTxt = $directory . "/spri-marc-{$fileset}-{$typeLabel}.txt";
		if (file_exists ($filenameMarcTxt)) {
			unlink ($filenameMarcTxt);
		}
		$filenameMrk = $directory . "/spri-marc-{$fileset}-{$typeLabel}.mrk";
		if (file_exists ($filenameMrk)) {
			unlink ($filenameMrk);
		}
		$filenameMrc = $directory . "/spri-marc-{$fileset}-{$typeLabel}.mrc";
		if (file_exists ($filenameMrc)) {
			unlink ($filenameMrc);
		}
		
		# Define the selection constraint
		if (!$selectionList) {
			$filterConstraint = "status = '{$fileset}'";
		} else {
			$filterConstraint = 'id IN(' . implode (', ', $selectionList) . ')';
		}
		
		# Define the record type constraint
		$recordTypeConstraint = "type IN('" . implode ("', '", $limitToRecordTypes) . "')";
		
		# Compile the constraints
		$constraintsSql = "{$filterConstraint} AND {$recordTypeConstraint}";
		
		# Get the total records in the table matching the current constraints
		$totalRecords = $this->databaseConnection->getTotal ($this->settings['database'], 'catalogue_marc', 'WHERE ' . $constraintsSql);
		
		# Start the output
		$marcText = '';
		
		# Chunk the records
		$offset = 0;
		$limit = 10000;		// This number confirmed best given indexing speed by Voyager
		$recordsRemaining = $totalRecords;
		$i = 0;
		while ($recordsRemaining > 0) {
			
			# Get the records, in groups as per the processing order
			$query = "SELECT
					id,marc
				FROM {$this->settings['database']}.catalogue_marc
				WHERE {$constraintsSql}
				ORDER BY FIELD(type, '" . implode ("','", $this->recordProcessingOrder) . "'), id
				LIMIT {$offset},{$limit}
			;";
			$data = $this->databaseConnection->getPairs ($query);
			
			# Add each record
			foreach ($data as $id => $record) {
				$marcText .= trim ($record) . "\n\n";
			}
			
			# Decrement the remaining records
			$recordsRemaining = $recordsRemaining - $limit;
			$offset += $limit;
		}
		
		# Save the file, in the standard MARC format
		file_put_contents ($filenameMarcTxt, $marcText);
		
		# Create a binary version
		$this->marcBinaryConversion ($filenameMarcTxt, $filenameMrk, $filenameMrc);
		
		# Check the output
		$errorsFilename = $this->marcLintTest ($fileset, $typeLabel, $directory, $errorsHtml /* amended by reference */);
		
		# Extract the errors from this error report, and add them to the MARC table
		if (!$selectionList) {	// Do not re-run for a selection list export
			$this->attachBibcheckErrors ($errorsFilename);
		}
	}
	
	
	# Function to convert the MARC text to binary format, which creates a temporary .mrk intermediate version
	private function marcBinaryConversion ($marcTxtFile, $mrkFile, $mrcFile)
	{
		# Copy, so that a Voyager-specific formatted version can be created
		copy ($marcTxtFile, $mrkFile);
		
		# Reformat a MARC records file to Voyager input style
		$this->reformatMarcToVoyagerStyle ($mrkFile);
		
		# Clear output file if it currently exists
		if (file_exists ($mrcFile)) {
			unlink ($mrcFile);
		}
		
		# Define and execute the command for converting the text version to binary; see: http://marcedit.reeset.net/ and http://marcedit.reeset.net/cmarcedit-exe-using-the-command-line and http://blog.reeset.net/?s=cmarcedit
		$command = "mono /usr/local/bin/marcedit/cmarcedit.exe -s {$mrkFile} -d {$mrcFile} -pd -make";
		exec ($command, $output, $unixReturnValue);
		if ($unixReturnValue == 2) {
			echo "<p class=\"warning\">Execution of <tt>/usr/local/bin/marcedit/cmarcedit.exe</tt> failed with Permission denied - ensure the webserver user can read <tt>/usr/local/bin/marcedit/</tt>.</p>";
			return false;
		}
		/*
		foreach ($output as $line) {
			if (preg_match ('/^0 records have been processed/', $line)) {
				$mrcFilename = basename ($mrcFile);
				echo "<p class=\"warning\">Error in creation of MARC binary file ({$mrcFilename}): <tt>" . htmlspecialchars ($line) . '</tt></p>';
				break;
			}
		}
		*/
		
		# Delete the .mrk file
		unlink ($mrkFile);
	}
	
	
	# Function to reformat a MARC records file to Voyager input style
	public function reformatMarcToVoyagerStyle ($filenameMrk)
	{
		# Reformat to Voyager input style; this process is done using shelled-out inline sed/perl, rather than preg_replace, to avoid an out-of-memory crash
		exec ("sed -i 's/\\$/{dollar}/g' {$filenameMrk}");												// Protect $ with {dollar}; see: https://www.loc.gov/marc/makrbrkr.html and https://blog.reeset.net/archives/1905 , e.g. /records/115595/
		exec ("sed -i 's" . "/{$this->doubleDagger}\([a-z0-9]\)/" . '\$\1' . "/g' {$filenameMrk}");		// Replace double-dagger(s) with $
		exec ("sed -i '/^LDR /s/#/\\\\/g' {$filenameMrk}");												// Replace all instances of a # marker in the LDR field with \
		exec ("sed -i '/^008 /s/#/\\\\/g' {$filenameMrk}");												// Replace all instances of a # marker in the 008 field with \
		exec ("perl -pi -e 's" . '/^([0-9]{3}) #(.) (.+)$/' . '\1 \\\\\2 \3' . "/' {$filenameMrk}");	// Replace # marker in position 1 with \
		exec ("perl -pi -e 's" . '/^([0-9]{3}) (.)# (.+)$/' . '\1 \2\\\\ \3' . "/' {$filenameMrk}");	// Replace # marker in position 2 with \
		exec ("perl -pi -e 's" . '/^([0-9]{3}|LDR) (.+)$/' . '\1  \2' . "/' {$filenameMrk}");			// Add double-space after LDR and each field number
		exec ("perl -pi -e 's" . '/^([0-9]{3})  (.)(.) (.+)$/' . '\1  \2\3\4' . "/' {$filenameMrk}");	// Remove space after first and second indicators
		exec ("perl -pi -e 's" . '/^(.+)$/' . '=\1' . "/' {$filenameMrk}");								// Add = at start of each line
	}
	
	
	# Function to do a Bibcheck lint test
	private function marcLintTest ($fileset, $typeLabel, $directory, &$errorsHtml)
	{
		# Define the filename for the raw (unfiltered) errors file and the main filtered version
		$errorsFilename = "{$directory}/spri-marc-{$fileset}-{$typeLabel}.errors.txt";
		$errorsUnfilteredFilename = str_replace ('errors.txt', 'errors-unfiltered.txt', $errorsFilename);
		
		# Clear file(s) if currently existing
		if (file_exists ($errorsFilename)) {
			unlink ($errorsFilename);
		}
		if (file_exists ($errorsUnfilteredFilename)) {
			unlink ($errorsUnfilteredFilename);
		}
		
		# Define and execute the command for converting the text version to binary, generating the errors listing file; NB errors.txt is a hard-coded location in Bibcheck, hence the file-moving requirement
		# If an error occurs, e.g. two LDRs, Bibcheck will output the errors file until the point the errors occurred, e.g. "Invalid indicators "00273nas\a22000977\\4500" forced to blanks in record 2523 for tag LDR \n no subfield data found in record 2523 for tag LDR"
		$command = "cd {$this->applicationRoot}/libraries/bibcheck/ ; PERL5LIB={$this->applicationRoot}/libraries/bibcheck/ perl lint_test.pl {$directory}/spri-marc-{$fileset}-{$typeLabel}.mrc 2>&1";	//  2>> errors.txt
		$output = shell_exec ($command);
		if ($output) {
			$errorsHtml .= "\n<p class=\"warning\">Error in Bibcheck execution for fileset {$fileset}-{$typeLabel}: " . nl2br (str_replace ("\n\n", "\n", str_replace ("\n\r", "\n", htmlspecialchars (trim ($output))))) . '</p>';
		}
		$command = "cd {$this->applicationRoot}/libraries/bibcheck/ ; mv errors.txt {$errorsUnfilteredFilename}";
		$output = shell_exec ($command);
		
		# Strip whitelisted errors and save a filtered version
		$errorsString = file_get_contents ($errorsUnfilteredFilename);
		$errorsString = $this->stripBibcheckWhitelistErrors ($errorsString);
		file_put_contents ($errorsFilename, $errorsString);
		
		# Return the filename
		return $errorsFilename;
	}
	
	
	# Function to strip whitelisted errors from the Bibcheck reports
	private function stripBibcheckWhitelistErrors ($errorsString)
	{
		# Define errors to whitelist
		$whitelistErrorRegexps = array (
			'008: Check place code xxu - please set code for specific state \(if known\).',	// E.g. /records/1199/ (test #216) which is USA but no further detail
			'008: 008 date may not match 260 date - please check.',	// E.g. /records/1150/ which has '[196-?]' which is valid (test #217) - Bibcheck isn't taking account of [...] brackets or five-digit values; see example "##$aNew York :$bHaworth,$c[198-]" at https://www.loc.gov/marc/bibliographic/bd008a.html
			'008: Check place code xxk - please set code for specific UK member country eg England, Wales \(if known\).', // E.g. /records/163302/
			'020: Subfield _q is not allowed.',		// E.g. /records/165286/
			'041: Subfield _h, esk, may be obsolete.',	// E.g. /records/65835/
			'041: Subfield _h, mol, may be obsolete.',	// E.g. /records/70623/
			'111: Subfield _j is not allowed.',		// E.g. /records/151282/
			'240: Should not end with a full stop unless the final word is an abbreviation',	// E.g. /records/32075/
			'245: Subfield _[1|2|4|5] is not allowed.',	// E.g. /records/203691/ has $100 (test #218)
			'245: Must have a subfield _a.',	// E.g. /records/174312/ has "�a$50,000 an ounce!"
			'245: Subfield _h may have invalid material designator, or lack square brackets, h.',	// /records/181410/ has �h[videorecording; electronic resource] defined in test #578
			'245: Subfield _c initials should not have a space.',	// Happens on /records/13442/ which validly has "K.C.B. K.C."
			'245: First word, en, does not appear to be an article, check 2nd indicator \(3\).',	// 54 cases, of valid 'en ' => 'Danish Norwegian Swedish'
			'245: First word, de, does not appear to be an article, check 2nd indicator \(3\).',	// 33 cases, of valid 'de ' => 'Danish Swedish': SELECT * FROM catalogue_marc WHERE bibcheckErrors = '245: First word, de, does not appear to be an article, check 2nd indicator (3).' AND marc NOT REGEXP '(041 0# �adan|041 .# �aswe)';
			'245: First word, et, does not appear to be an article, check 2nd indicator \(3\).',	// 17 cases, of valid 'et ' => 'Danish Norwegian'
			'245: First word, a, does not appear to be an article, check 2nd indicator \(2\).',		// 2 cases
			'245: First word, et, does not appear to be an article, check 2nd indicator \(4\).',	// 1 case
			'245: First word, les, may be an article, check 2nd indicator \(0\).',	// 6 cases
			'245: First word, a, may be an article, check 2nd indicator \(0\).',	// 5 cases
			'245: First word, el, may be an article, check 2nd indicator \(0\).',	// 4 cases
			'245: First word, um, may be an article, check 2nd indicator \(0\).',	// 3 cases
			'245: First word, an, may be an article, check 2nd indicator \(0\).',	// 2 cases
			'245: First word, der, may be an article, check 2nd indicator \(0\).',	// 2 cases
			'245: First word, il, may be an article, check 2nd indicator \(0\).',	// 1 case
			'245: First word, ett, may be an article, check 2nd indicator \(0\).',	// 1 case
			'245: First word, een, may be an article, check 2nd indicator \(0\).',	// 1 case
			'245: First word, ho, may be an article, check 2nd indicator \(0\).',	// 1 case
			'245: First word, uno, may be an article, check 2nd indicator \(0\).',	// 1 case
			'250: Should end with a full stop.',	// 250 dot-end is confirmed dot or punctuation
			'300: In subfield _a, p should be followed by a full stop.',	// See 8e5f9354da83b6aa7a9e338e0ba7d48e1d1e0b60 - intended "p." is already implemented correctly; see /records/54670/ (test #219)
			'300: In subfield _a there should be a space between the comma and the next set of digits.',	// E.g. /records/32362/
			'300: In subfield _a there should be a space between the number and the type of unit - please check.',	// Only /records/164582/ and /records/203582/
			'300: In subfield _a, v should be followed by a full stop.',	// E.g. /records/4061/
			'490: Should not end with a full stop unless it comes at the end of an abbreviation - please check.',	// E.g. /records/171379/ and /records/153757/ (means "Report no.")
			'500: Subfield _2 is not allowed.',	// E.g. /records/161883/ has $220 (test #558)
			'500: Subfield _- is not allowed.',	// E.g. /records/138509/ has "24-3, $-24-4"
			'500: Subfield _1 is not allowed.',	// E.g. /records/142058/ has "price: $195"
			'520: Subfield _[ 1m,2t)5] is not allowed.',	// E.g. /records/140044/ (test #223)
			'533: Subfield _5 is not allowed.',	// E.g. /records/43953/ but this is clearly defined at https://www.loc.gov/marc/bibliographic/bd533.html
			'541: Subfield _[0-9AUNC ] is not allowed.',	// E.g. /records/148863/ which has "AUS$ " (test #224); see example at: https://www.loc.gov/marc/bibliographic/bd541.html which confirms use of unescaped $
			'541: Subfield _[0-9] is not repeatable.',	// The generate541 code definitely has no horizontal repeatability - this is Bibcheck being unable to distinguish e.g. $5 (money) from double-dagger5 (subfield), e.g. /records/9220/ (test #225)
			'541: Subfield _. is not allowed.',	// Dollar bug as per other whitelistings, e.g. /records/137359/
			'Record is post 1900 but contains local information \(541 or 561 fields\) - please check.',	// For 541; confirmed fine as we are setting $5, e.g. /records/9220/ (test #226)
			'6XX: Unless the Literary form in the 008 is set to one of the fiction codes, there must be at least one 6XX field \(ignore if the work is a sacred text.\)',		// This arises because Bibcheck has a litcode check at line 602 but that assumes that the 008 is a "008 - Books" which is not always the case - see position_18_34__33 in generate008; see e-mail dated 30/Mar/2016 investigating this, and e-mail 31/Mar/2016 confirming the error is safe to suppress; e.g. /records/1061/ (test #227)
			'700: Subfield _1 is not allowed.',	// E.g. /records/194888/ has "proposed $12-billion"
			'852: Subfield _9 is not allowed.',
			'852: Subfield _0 is not allowed.',
		);
		
		# Split the file into individual reports
		$delimiter = str_repeat ('=', 63);	// i.e. the ===== line
		$reportsUnfiltered = explode ($delimiter, $errorsString);
		
		# Filter out lines for each report
		$reports = array ();
		foreach ($reportsUnfiltered as $index => $report) {
			
			# Strip out lines matching a whitelisted error type
			$lines = explode ("\n", $report);
			foreach ($lines as $lineIndex => $line) {
				foreach ($whitelistErrorRegexps as $whitelistErrorRegexp) {
					if (preg_match ('/' . addcslashes ($whitelistErrorRegexp, '/') . '/', $line)) {
						unset ($lines[$lineIndex]);
						break;	// Break out of regexps loop and move to next line
					}
				}
			}
			$report = implode ("\n", $lines);
			
			# If there are no errors remaining in this report, skip re-registering the report
			if (preg_match ('/\^{25}$/D', trim ($report))) {		// i.e. no errors if purely whitespace between ^^^^^ line and the end
				continue;	// Skip to next report
			}
			
			# Re-register the report
			$reports[$index] = $report;
		}
		
		# Reconstruct as a single listing
		$errorsString = implode ($delimiter, $reports);
		
		# Return the new listing
		return $errorsString;
	}
	
	
	# Function to extract errors from a Bibcheck error report and attach them to the MARC records in the database
	private function attachBibcheckErrors ($errorsFilename)
	{
		# Extract from the report
		# The report is in the format of the extract shown below; the SPRI value in 001 and the errors between the ^^^^^ and the ===== line need to be captured:
		/*
			===============================================================
			
			LDR 00803nas a2200265 a 4500
			001     SPRI1021
			005     20160318210532.0
			007     ta
			008     160318uuuuuuuuuxx  u |  |   |0   a|eng d
			040    _aUkCU-P
			       _beng
			       _eaacr
			041 0  _aeng
			245 00 _aCanada. Dept. of Mines and Technical Surveys. Mines Branch. [Reports]
			260    _a[S.l.] :
			       _b[s.n.]
			300    _av.
			546    _aEnglish
			650 07 _2udc
			       _a551.1/.4 -- Geology.
			650 07 _2udc
			       _a553 -- Economic geology.
			650 07 _2udc
			       _a553.042 -- Mineral resources.
			650 07 _2udc
			       _a622 -- Mining.
			651  7 _2udc
			       _a(*41) -- Canada.
			780 10 _tCanada. Dept. of Mines and Resources. Bureau of Mines. [Reports]
			852 7  _2camdept
			       _x??
			866  0 _aunknown
			917    _aUnenhanced record from Muscat, imported 2015
			948 3  _a20160318
			       _dMISSING
			^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
			245: Must end with a full stop.
			
			
			===============================================================
			
			LDR 01216nam a2200241 a 4500
			001     SPRI1334
			...
			
		*/
		$errorsString = file_get_contents ($errorsFilename);
		preg_match_all ("/\sSPRI-([0-9]+)(?U).+\^{25,}\s+((?U).+)\s+(?:={25,}|$)/sD", $errorsString, $errors, PREG_SET_ORDER);	// Records have SPRI- (test #228)
		
		# End if none
		if (!$errors) {return;}
		
		# Assemble updates
		$updates = array ();
		foreach ($errors as $error) {
			$id = $error[1];
			$updates[$id] = array ('bibcheckErrors' => $error[2]);
		}
		
		# Do the update
		$this->databaseConnection->updateMany ($this->settings['database'], 'catalogue_marc', $updates);
	}
}

?>
