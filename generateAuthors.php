<?php

# Class to generate the complex author fields
class generateAuthors
{
	# Constructor
	public function __construct ($muscatConversion, $xml)
	{
		# Create a class property handle to the parent class
		$this->muscatConversion = $muscatConversion;
		
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
			if (!isSet ($this->values[$field])) {	// May be already generated by another field whose result is mutated to another field
				$this->values[$field] = $this->{$function} ();
			}
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
			return false;
		}
		
		# Do the classification; look at the first or only *doc/*ag/*a OR *art/*ag/*a
		$value = $this->generateAuthorsClassification->main ($this->xml, '/*/ag/a[1]');
		
		# Return the value
		return $value;
	}
	
	
	# Generate 110
	public function generate110 ()
	{
		# End if not enabled by the 100 process
		if (!$this->generateAuthorsClassification->getEnable110Processing ()) {return false;}
		
		// #!# TODO
		return 'todo-generate-110';
		
	}
	
	
	# Generate 111
	public function generate111 ()
	{
		# End if not enabled by the 100 process
		if (!$this->generateAuthorsClassification->getEnable111Processing ()) {return false;}
		
		// #!# TODO
		return 'todo-generate-111';
		
	}
	
	
	# Generate 700; see: http://www.loc.gov/marc/bibliographic/bd700.html
	/*
	 * This is basically all the people involved in the book except the first author, which if present is covered in 100/110/111.
	 * It includes people in the analytic (child) records, but limited to the first of them for each such child record
	 * This creates multiple 700 lines, the lines being created as the outcome of the loop below
	 * Each "contributor block" referenced below refers to the author components, which are basically the 'classify' functions elsewhere in this class
	 * 
	 * - Checks there is *doc/*ag or *art/*ag (i.e. *ser records will be ignored)
	 * - Loops through each *ag
	 * - Within each *ag, for each *a and *al add the contributor block
	 * - In the case of each *ag/*al, the "*al Detail" block (and ", �g (alternative name)", once only) is added
	 * - Loop through each *e
	 * - Within each *e, for each *n add the contributor block
	 * - In the case of each *e/*n, *role, with Relator Term lookup substitution, is incorporated
	 * - When considering the *e/*n, there is a guard clause to skip cases of 'the author' as the 100 field would have already pulled in that person (e.g. the 100 field could create "<name> $eIllustrator" indicating the author <name> is also the illustrator)
	 * - Checks for a *ke which is a flag indicating that there are analytic (child) records, e.g. as present in /records/7463/
	 * - Looks up the records whose *kg matches, e.g. /records/9375/ has *kg=7463, so this indicates that 9375 (which will be an *art) is a child of 7463
	 * - For each *kg's *art (i.e. child *art record): take the first *art/*ag/*a/ (only the first) in that record within the *ag block, i.e. /records/9375/ /art/ag/a "contributor block", and also add the title of the (i.e. *art/*tg/*t); the second indicator is set to '2' to indicate that this 700 line is an 'Analytical entry'
	 * - Every 700 has a fixed string ", �5 UkCU-P" at the end (representing the Institution to which field applies)
	 */
	public function generate700 ()
	{
		// #!# TODO
	}
	
	
	# Generate 710
	public function generate710 ()
	{
		# End if not enabled by the 100 process
		if (!$this->generateAuthorsClassification->getEnable710Processing ()) {return false;}
		
		// #!# TODO
		return 'todo-generate-710';
		
	}
	
	
	# Generate 711
	public function generate711 ()
	{
		# End if not enabled by the 100 process
		if (!$this->generateAuthorsClassification->getEnable711Processing ()) {return false;}
		
		// #!# TODO
		return 'todo-generate-711';
		
	}
}

?>