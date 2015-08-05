<?php

class generate008
{
	# Constructor
	public function __construct ()
	{
		// Do nothing
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
#!# Todo
		return '/' . str_repeat ('-', 17 - 1);
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