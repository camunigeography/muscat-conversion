#!/usr/bin/perl 

#Test a file of MARC records for errors


use Encode;
use MARC::Record;
use MARC::Batch;
use lib "D:\\perls\\mods";
use UClint_RDA;
#use UClint;
my $file;

if ( $ARGV[0] ) {
$file=$ARGV[0];
}
else{
    print
      "\nThis script checks a file of MARC records for errors \nOutputfile = errors.txt\n";
    print "\nArguments required: input file.\n\n";
    print "Please supply input file name and path :";
    $file = <>;
}


open(OUT, ">", "errors.txt");
#open(OUT2, ">", "D:\\errors.txt");

my $batch = MARC::Batch->new( 'USMARC' , $file );
while ( my $record = $batch->next() ) {
    
	my $rdalint=UClint_RDA->new;
	if ( substr($record->leader(), 7, 1 ) =~ "[sim]" ) {
	$rdalint->check_record($record);
	my @warnings=$rdalint->warnings();
	if (@warnings){
	my @sort_warn = sort {$a <=> $b} @warnings;
print OUT "\n\n===============================================================\n\n";
print OUT encode_utf8( $record->as_formatted() );
print OUT "\n^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^\n";
# The error text itself may contain UTF-8 characters, e.g. "245: First word, hokuhyōyō, does not appear to be an article, check 2nd indicator (3).", which generates a warning about the error text
foreach my$line(@sort_warn){print OUT "$line\n"}
}

    }
    

        }
  