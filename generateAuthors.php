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
		
	}
	
	
	# Main
	public function generate100 (&$error = false)
	{
		# 100 1# ‡a{/*|macro:authorName(//ag/a[1])}
		$value = $this->muscatConversion->macro_authorName (NULL, $this->xml, '//ag/a[1]');
		
		# Return the value
		return $value;
	}
	
	
}

?>