<?php

# Class to generate the complex 245 (Title and statement of responsibility) field
class generate245
{
	# Constructor
	public function __construct ($muscatConversion, $xml, $authorsFields)
	{
		# Create a class property handle to the parent class
		$this->muscatConversion = $muscatConversion;
		
		# Create a handle to the XML
		$this->xml = $xml;
		
		# Create a handle to the authors fields
		$this->authorsFields = $authorsFields;
		
		# Define unicode symbols
		$this->doubleDagger = chr(0xe2).chr(0x80).chr(0xa1);
		
	}
	
	
	# Main
	public function main (&$error = false)
	{
		$value = 'todo';
		
		# Return the value
		return $value;
	}
	
}

?>