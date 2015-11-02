<?php

# Class to generate the complex author fields
class generateAuthors
{
	# Constructor
	public function __construct ($muscatConversion, $xml)
	{
		# Create a class property handle to the parent class
		$this->muscatConversion = $muscatConversion;
		$this->databaseConnection = $muscatConversion->databaseConnection;
		$this->settings = $muscatConversion->settings;
		
		# Create a handle to the XML
		$this->xml = $xml;
		
		# Define unicode symbols
		$this->doubleDagger = chr(0xe2).chr(0x80).chr(0xa1);
		
		# Load the classifier
		require_once ('generateAuthorsClassification.php');
		$this->generateAuthorsClassification = new generateAuthorsClassification ($this->muscatConversion);
		
		# Generate each field
		$fields = array (
			100,
			110,
			111,
			700,
			710,
			711,
		);
		$this->values = array ();
		foreach ($fields as $field) {
			$function = 'generate' . $field;
			$this->{$function} ();
		}
		
	}
	
	
	# Getter to return the result
	public function getResult ()
	{
		# Return the values
		return $this->values;
	}
	
	
	# Generate 100
	/*
	 * This is basically the first author.
	 * It may end up switching to 110/111 instead.
	 * Everyone else involved in the production ends put in 7xx fields.
	 *
	 */
	public function generate100 ()
	{
		# 100 is not relevant for *ser or *art/*in/*ag, so end at this point if matches these
		$ser = $this->muscatConversion->xPathValue ($this->xml, '//ser');
		$artIn = $this->muscatConversion->xPathValue ($this->xml, '//art/in');
		if ($ser || $artIn) {
			$this->values[100] = false;
			return;
		}
		
		# Do the classification; look at the first or only *doc/*ag/*a OR *art/*ag/*a
		$value = $this->generateAuthorsClassification->main ($this->xml, '/*/ag/a[1]');
		
		# Write the value into the values registry
		$this->values[100] = $value;
	}
	
	
	# Generate 110
	public function generate110 ()
	{
		# End if not enabled by the 100 process
		if (!$this->generateAuthorsClassification->getEnable110Processing ()) {
			$this->values[110] = false;
			return false;
		}
		
		# Write the value into the values registry
		$this->values[110] = 'todo-generate-110';
	}
	
	
	# Generate 111
	public function generate111 ()
	{
		# End if not enabled by the 100 process
		if (!$this->generateAuthorsClassification->getEnable111Processing ()) {
			$this->values[111] = false;
			return;
		}
		
		# Write the value into the values registry
		$this->values[111] = 'todo-generate-111';
	}
	
	
	# Generate 700; see: http://www.loc.gov/marc/bibliographic/bd700.html
	/*
	 * This is basically all the people involved in the book except the first author, which if present is covered in 100/110/111.
	 * It includes people in the analytic (child) records, but limited to the first of them for each such child record
	 * This creates multiple 700 lines, the lines being created as the outcome of the loop below
	 * Each "contributor block" referenced below refers to the author components, which are basically the 'classify' functions elsewhere in this class
	 * 
	 * - Check there is *doc/*ag or *art/*ag (i.e. *ser records will be ignored)
	 * - Loop through each *ag
	 * - Within each *ag, for each *a and *al add the contributor block
	 * - In the case of each *ag/*al, the "*al Detail" block (and ", ‡g (alternative name)", once only) is added
	 * - Loop through each *e
	 * - Within each *e, for each *n add the contributor block
	 * - In the case of each *e/*n, *role, with Relator Term lookup substitution, is incorporated
	 * - When considering the *e/*n, there is a guard clause to skip cases of 'the author' as the 100 field would have already pulled in that person (e.g. the 100 field could create "<name> $eIllustrator" indicating the author <name> is also the illustrator)
	 * - Check for a *ke which is a flag indicating that there are analytic (child) records, e.g. as present in /records/7463/
	 * - Look up the records whose *kg matches, e.g. /records/9375/ has *kg=7463, so this indicates that 9375 (which will be an *art) is a child of 7463
	 * - For each *kg's *art (i.e. child *art record): take the first *art/*ag/*a/ (only the first) in that record within the *ag block, i.e. /records/9375/ /art/ag/a "contributor block", and also add the title (i.e. *art/*tg/*t); the second indicator is set to '2' to indicate that this 700 line is an 'Analytical entry'
	 * - Every 700 has a fixed string ", ‡5 UkCU-P." at the end (representing the Institution to which field applies)
	 */
	public function generate700 ()
	{
		# Start a list of 700 line values
		$lines = array ();
		
#!# Should an /art record with no ag but with e succeed?
		# Check there is *doc/*ag or *art/*ag (i.e. *ser records will be ignored)
		$docAg = $this->muscatConversion->xPathValue ($this->xml, '/doc/ag');
		$artAg = $this->muscatConversion->xPathValue ($this->xml, '/art/ag');
		if (!$docAg && !$artAg) {
			$this->values[700] = false;
			return;
		}
		
		# Loop through each *ag
		$agIndex = 1;
		while ($this->muscatConversion->xPathValue ($this->xml, "/*/ag[$agIndex]")) {
			
			# Loop through each *a (author) in this *ag (author group)
			$aIndex = 1;	// XPaths are indexed from 1, not 0
			while ($this->muscatConversion->xPathValue ($this->xml, "/*/ag[$agIndex]/a[{$aIndex}]")) {
				
				# Skip the first /*ag/*a
				if ($agIndex == 1 && $aIndex == 1) {
					$aIndex++;
					continue;
				}
				
				# Obtain the value
				$lines[] = $this->generateAuthorsClassification->main ($this->xml, "/*/ag[$agIndex]/a[{$aIndex}]", false);
				
				# Next *a
				$aIndex++;
			}
			
			# Loop through each *al (author) in this *ag (author group)
			$alIndex = 1;	// XPaths are indexed from 1, not 0
			while ($this->muscatConversion->xPathValue ($this->xml, "/*/ag[$agIndex]/al[{$alIndex}]")) {
				
				# Obtain the value
				$line = $this->generateAuthorsClassification->main ($this->xml, "/*/ag[$agIndex]/al[{$alIndex}]", false);
				
				# The "*al Detail" block (and ", ‡g (alternative name)", once only) is added
				#!# Not yet checked cases for when a $g might already exist, to check this works
				if (!substr_count ($line, "{$this->doubleDagger}g")) {
					$line .= ", {$this->doubleDagger}g" . '(alternative name)';
				}
				
				# Register the line
				$lines[] = $line;
				
				# Next *al
				$alIndex++;
			}
			
			# Next *ag
			$agIndex++;
		}
		
		# Loop through each *e
		$eIndex = 1;
		while ($this->muscatConversion->xPathValue ($this->xml, "/*/e[$eIndex]")) {
			
			# Within each *e, for each *n add the contributor block
			$nIndex = 1;	// XPaths are indexed from 1, not 0
			while ($this->muscatConversion->xPathValue ($this->xml, "/*/e[$eIndex]/n[{$nIndex}]")) {
				
				# When considering the *e/*n, there is a guard clause to skip cases of 'the author' as the 100 field would have already pulled in that person (e.g. the 100 field could create "<name> $eIllustrator" indicating the author <name> is also the illustrator); e.g. /records/147053/
				#!# Move this check into the generateAuthorsClassification class?
				#!# Check this is as expected for e.g. /records/147053/
				$n1 = $this->muscatConversion->xPathValue ($this->xml, "/*/e[$eIndex]/n[{$nIndex}]/n1");
				if ($n1 == 'the author') {
					$nIndex++;
					continue;
				}
				
				# Obtain the value
				# In the case of each *e/*n, *role, with Relator Term lookup substitution, is incorporated; example: /records/47079/ ; this is done inside classifyAdField ()
				$line = $this->generateAuthorsClassification->main ($this->xml, "/*/e[$eIndex]/n[{$nIndex}]", false);
				
				# Register the line
				$lines[] = $line;
				
				# Next *n
				$nIndex++;
			}
			
			# Next *e
			$eIndex++;
		}
		
		# Check for a *ke which is a flag indicating that there are analytic (child) records; e.g.: /records/7463/
		if ($this->muscatConversion->xPathValue ($this->xml, '//ke')) {		// Is just a flag, not a useful value; e.g. record 7463 contains "\&lt;b&gt; Analytics \&lt;b(l) ~l 1000/&quot;ME7463&quot;/ ~&gt;" which creates a button in the Muscat GUI
			
			# Look up the records whose *kg matches, e.g. /records/9375/ has *kg=7463, so this indicates that 9375 (which will be an *art) is a child of 7463
			$currentRecordId = $this->muscatConversion->xPathValue ($this->xml, '/q0');
			if ($children = $this->getAnalyticChildren ($currentRecordId)) {	// Returns records as array (id=>xmlObject, ...)
				
				# Loop through each *kg's *art (i.e. child *art record)
				foreach ($children as $id => $childRecordXml) {
					
					# Take the first *art/*ag/*a/ (only the first) in that record within the *ag block, i.e. /records/9375/ /art/ag/a "contributor block"; the second indicator is set to '2' to indicate that this 700 line is an 'Analytical entry'
					$line = $this->generateAuthorsClassification->main ($childRecordXml, "/*/ag[1]/a[1]", false, '2');
					
					# Add the title (i.e. *art/*tg/*t)
					$line .= ", {$this->doubleDagger}2" . $this->muscatConversion->xPathValue ($childRecordXml, '/*/tg/t');
					
					# Register the line
					$lines[] = $line;
				}
			}
		}
		
		# End if no lines
		if (!$lines) {
			$this->values[700] = false;
			return;
		}
		
		# Every 700 has a fixed string ", ‡5 UkCU-P." at the end (representing the Institution to which field applies)
		foreach ($lines as $index => $line) {
			$lines[$index] .= ", {$this->doubleDagger}5" . 'UkCU-P.';
		}
		
		# Implode the lines
		$newLine = "\n" . '700 ';
		$value = implode ($newLine, $lines);
		
		# Write the value, which will be a special multiline string, into the values registry
		$this->values[700] = $value;
	}
	
	
	# Generate 710
	public function generate710 ()
	{
		# End if not enabled by the 700 process
		if (!$this->generateAuthorsClassification->getEnable710Processing ()) {
			$this->values[710] = false;
			return false;
		}
		
		# Write the value into the values registry
		$this->values[710] = 'todo-generate-710';
	}
	
	
	# Generate 711
	public function generate711 ()
	{
		# End if not enabled by the 700 process
		if (!$this->generateAuthorsClassification->getEnable711Processing ()) {
			$this->values[711] = false;
			return;
		}
		
		# Write the value into the values registry
		$this->values[711] = 'todo-generate-711';
	}
	
	
	# Function to obtain the analytic children
	private function getAnalyticChildren ($parentId)
	{
		# Get the children
		$childIds = $this->databaseConnection->selectPairs ($this->settings['database'], 'catalogue_processed', array ('field' => 'kg', 'value' => $parentId), array ('recordId'));
		
		# Load the XML records for the children
		$children = $this->databaseConnection->selectPairs ($this->settings['database'], 'catalogue_xml', array ('id' => $childIds), array ('id', 'xml'));
		
		# Convert each XML record string to an XML object
		$childrenRecords = array ();
		foreach ($children as $id => $record) {
			$childrenRecords[$id] = $this->muscatConversion->loadXmlRecord ($record);
		}
		
		# Return the records
		return $childrenRecords;
	}
}

?>
