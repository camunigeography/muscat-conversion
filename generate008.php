<?php

class generate008
{
	# Constructor
	public function __construct ($muscatConversion)
	{
		# Create a class property handle to the parent class
		$this->muscatConversion = $muscatConversion;
		
	}
	
	
	# Main
	public function main ($xml)
	{
		# Start the value
		$value = '';
		
		# Delegate the creation of the value for each set of positions
		$value .= $this->generate008_00_05 ($xml);
		$value .= $this->generate008_06 ($xml);
		$value .= $this->generate008_07_10 ($xml);
		$value .= $this->generate008_11_14 ($xml);
		$value .= $this->generate008_15_17 ($xml);
		$value .= $this->generate008_18_34 ($xml);
		$value .= $this->generate008_35_37 ($xml);
		$value .= $this->generate008_38 ($xml);
		$value .= $this->generate008_39 ($xml);
		
		# Return the value
		return $value;
	}
	
	
	# 008 pos. 00-05: Date entered on file
	private function generate008_00_05 ($xml)
	{
		# Date entered on system [format: yymmdd]
		return date ('ymd');
	}
	
	
	# 008 pos. 06: Type of date/Publication status
	private function generate008_06 ($xml)
	{
#!# Todo
		return '/' . str_repeat ('-', 1 - 1);
	}
	
	
	# 008 pos. 07-10: Date 1
	private function generate008_07_10 ($xml)
	{
#!# Todo
		return '/' . str_repeat ('-', 4 - 1);
	}
	
	
	# 008 pos. 11-14: Date 2
	private function generate008_11_14 ($xml)
	{
#!# Todo
		return '/' . str_repeat ('-', 4 - 1);
	}
	
	
	# 008 pos. 15-17: Place of publication, production, or execution
	private function generate008_15_17 ($xml)
	{
#!# Todo
		return '/' . str_repeat ('-', 3 - 1);
	}
	
	
	# 008 pos. 18-34: Material specific coded elements
	private function generate008_18_34 ($xml)
	{
		# Determine the record type, used by subroutines
		$recordTypes = array (
			'/art/in',
			'/art/j',
			'/doc',
			'/ser',
		);
		foreach ($recordTypes as $recordType) {
			if ($this->muscatConversion->xPathValue ($xml, $recordType)) {
				break;	// $recordType will now be set
			}
		}
		
		# Flag error if no record type
#!# Need to flag error
		if (!$recordType) {return '/' . str_repeat ('?', 17 - 1);}
		
		# Get the *form value
		$form = $this->muscatConversion->xPathValue ($xml, $recordType . '/form');
		
		# Compile the value by delegating each part
		$value  = $this->generate008_18_34__18_20 ($xml, $recordType, $form);
		$value .= $this->generate008_18_34__21    ($xml, $recordType, $form);
		$value .= $this->generate008_18_34__22    ($xml, $recordType, $form);
		$value .= $this->generate008_18_34__23    ($xml, $recordType, $form);
		$value .= $this->generate008_18_34__24_27 ($xml, $recordType, $form);
		$value .= $this->generate008_18_34__28    ($xml, $recordType, $form);
		$value .= $this->generate008_18_34__29    ($xml, $recordType, $form);
		$value .= $this->generate008_18_34__30_31 ($xml, $recordType, $form);
		$value .= $this->generate008_18_34__32    ($xml, $recordType, $form);
		$value .= $this->generate008_18_34__33    ($xml, $recordType, $form);
		$value .= $this->generate008_18_34__34    ($xml, $recordType, $form);
		
		# Return the string
		return $value;
	}
	
	
	# Helper function to determine if the record form is roughly digital/multimedia
	private function isMultimediaish ($form)
	{
		# Define forms which come under this grouping
		$forms = array (
			'3.5 floppy disk',
			'CD-ROM',
			'DVD-ROM',
			'Map',
			'CD',
			'Sound cassette',
			'Sound disc',
			'DVD',
			'Videorecording',
			'Poster',
		);
		
		# Return whether the supplied form is one of the supported types
		return (in_array ($form, $forms));
	}
	
	
	
	# 008 pos. 18-34: Material specific coded elements: 18-20
	private function generate008_18_34__18_20 ($xml, $recordType, $form)
	{
		
		return NULL;
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 21
	private function generate008_18_34__21 ($xml, $recordType, $form)
	{
		if ($this->isMultimediaish ($form)) {
			switch ($form) {
				case '3.5 floppy disk':
				case 'CD-ROM':
				case 'DVD-ROM':
				case 'DVD':
				case 'Videorecording':
				case 'Poster':
					return '#';
				default:
					return '|';
			}
		}
		
		switch ($recordType) {
			case '/doc':
			case '/art/in':
				return '#';
			case '/ser':
			case '/art/j':
				return '|';
		}
		
		# Flag error
		return NULL;
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 22
	private function generate008_18_34__22 ($xml, $recordType, $form)
	{
		if ($this->isMultimediaish ($form)) {
			switch ($form) {
				case 'DVD':
				case 'Videorecording':
				case 'Poster':
					return '#';
				default:
					return '|';
			}
		}
		
		switch ($recordType) {
			case '/doc':
			case '/art/in':
				return '|';
			case '/ser':
			case '/art/j':
				
				if (!$form) {return '#';}
				
				switch ($form) {
					case 'Internet resource':
						return 'o';
					case 'Microfiche':
						return 'b';
					case 'Microfilm':
						return 'a';
					case 'Online publication':
						return 'o';
					case 'PDF':
						return 's';
				}
		}
		
		# Flag error
		return NULL;
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 23
	private function generate008_18_34__23 ($xml, $recordType, $form)
	{
		#!# Todo
		$value = '-';
		
		
		# Return the string
		return $value;
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 24-27
	private function generate008_18_34__24_27 ($xml, $recordType, $form)
	{
#!# Todo
		$value = '-';
		
		
		# Return the string
		return $value;
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 28
	private function generate008_18_34__28 ($xml, $recordType, $form)
	{
#!# Todo
		$value = '-';
		
		
		# Return the string
		return $value;
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 29
	private function generate008_18_34__29 ($xml, $recordType, $form)
	{
#!# Todo
		$value = '-';
		
		
		# Return the string
		return $value;
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 30-31
	private function generate008_18_34__30_31 ($xml, $recordType, $form)
	{
#!# Todo
		$value = '-';
		
		
		# Return the string
		return $value;
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 32
	private function generate008_18_34__32 ($xml, $recordType, $form)
	{
		return '#';
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 33
	private function generate008_18_34__33 ($xml, $recordType, $form)
	{
#!# Todo
		$value = '-';
		
		
		# Return the string
		return $value;
	}
	
	
	# 008 pos. 18-34: Material specific coded elements: 34
	private function generate008_18_34__34 ($xml, $recordType, $form)
	{
#!# Todo
		$value = '-';
		
		
		# Return the string
		return $value;
	}
	
	# 008 pos. 35-37: Language
	private function generate008_35_37 ($xml)
	{
#!# Todo
		return '/' . str_repeat ('-', 3 - 1);
	}
	
	
	# 008 pos. 38: Modified record
	private function generate008_38 ($xml)
	{
		return '#';
	}
	
	
	# 008 pos. 39: Cataloguing source
	private function generate008_39 ($xml)
	{
		return 'd';
	}
}

?>