package UClint_RDA;

###################
# RDA controlled vocabulary is in file RDA_vocabulary.pm, new terms will need to be added to this file.
# Language codes and geographical area codes are in CodeData.pm
###################

#Alterations
#-Commented out subs check_020 and _isbn13_check_digit
#each use Business::ISBN, which requires LWP::Simple
#-Added various foreign articles to list of definite articles
#-Changed instances of period to full stops
#-Added some names etc to exception list for article checking
#-Took part of 043 checking that flags fields over 7 characters long out
#-Changed _h subfield warning in 245 check so that it reflects the check
#on the contents being either a word or two words separated by a space
#with no punctuation, as well as being enclosed in square brackets
#-Changed 245 check so that it just checks for full stops at end of
#field, rather than having a different warning for ? and !
#-Added separate check_245_fullstops - checks for full stop at
#end of field
#-Added new sub check_260 to check punctuation, tags etc for 260 field
#-Added routine to head of check_245 and check_260 which looks for _6
#linking subfields and removes them before dealing with the rest of the
#field as normal. Will need to add this to other 880 linked fields as
#required.
#-Added checks that if there is a 1XX field, then the first indicator of the
# 245 is 1, and that if there isn't, the first indicator is 0.
#-Added extra checks for the presence of a 245, 260 and 300 field.
#-Added check that there is at least one 6xx field unless the item is coded as
# fiction in the 008.
#-Added 240 check for correct indicators and punctuation
#-Added 130, 630 and 730 checks, including stripping of _2,_3 and _5
#subfields from the end of fields before performing checks.
#-Added checks on 250 and 740 fields.
#-Added check that if 490 1st indicator is one, at least one 8xx is present
#-Added 440, 490 and 830 checks.
#-Changed rules for 130, 240, 630, 730 and 830 fields, so that only 0 is
#allowed as a first indicator (non-filing counts not used for authority
#headings).
#-Added checks for 100, 600, 700 and 800 fields
#-Added checks for 110, 610, 710 and 810 fields
#-Added checks for 111, 611, 711 and 811 fields
#-Added checks for 650 and 651 fields
#-Added check for 300 field
#-Added subroutines for common checks
#-Added routine to check validity of date in 008 vs date in 260
#-Added check for blank place code in 008
#-If imprint is post-1850, added check for capitalised 'By' and 'Edited'
#at start of 245 subfield c
#-Warning if there is a 440 field (no longer valid)
#-Warning of local info in bib for post 1900 works with 541 or 561 fields
#-Warns for date codes xxu and xxk
#-Warns for cataloguing format not "a" in leader
#-Warns where 008 date type is "n" and date 1 is not "uuuu"
#-Warns where 008 date 1 is "uuuu" and date type is "s"
#-Altered 260 check so it only checks if first indicator is blank
#-Added Warning that if there is an 830 field there should also be a 490 field
#-Changed warning text for missing 260 field (might be RDA record with info in 264)
#-Warning if there is a 264 field (check record is RDA record)
#-Warning if there is a 336 field (check record is RDA record)
#-Warning if there is a 337 field (check record is RDA record)
#-Warning if there is a 338 field (check record is RDA record)
#-Added rules for 264 and 336-338
#-Altered 264 and 336-338 field checks, testing for RDA/AACR2
#-Altered cataloguing format check i/a for RDA/AACR2
#-Added 008=264 \1 date check
#-Added check for 008 pos 06 = t if 264 \4 copyright date present
#-Added warning if first 264 doesn't have 2nd indicator 1
#-Added 245 subfield h check for RDA
#-Added 100, 110, 700, 710 subfield e and relationship designator check for RDA (fields with second indicator 2 excluded) 
#-Altered 100 subfield e to warn if not proceeded br comma or dash (open date)
#-Added checks for 336 - multiples have subfield 3, subfield 2 rdacontent is used, subfield a matches controlled vocabulary, leader 06 matches
#-Added checks for 337 - multiples have subfield 3, subfield 2 rdamedia is used, subfield a matches controlled vocabulary, corresponding 007 present
#-Added checks for 338 - multiples have subfield 3, subfield 2 rdacarrier is used, subfield a matches controlled vocabulary
#-Added 264 \1 checks presence of subfields a, b and c, checks punctuation
#-Added 264 \4 checks for: subfield c,  008 pos 06 (date code) is set to "t", copyright symbol
#-Altered 300, RDA now checks for abbreviations and end punctuation (inc full stop or bracket if 490) 

use strict;
use warnings;
use integer;
use MARC::Record;
use MARC::Field;


use CodeData qw(%GeogAreaCodes %ObsoleteGeogAreaCodes %LanguageCodes %ObsoleteLanguageCodes);

#import RDA controlled vocabulary
use RDA_vocabulary qw(%contenta %contentb %mediaa %carriera @rel_des);
#my %carrierb = reverse%carriera; Not checking field b 
#my %mediab = reverse%mediaa;

our $VERSION = 1.41;

#constructor
sub new {
    my $class = shift;

    my $self = {
        _warnings => [],
    };
    bless $self, $class;

    $self->_read_rules();

    return $self;
}

#warnings

sub warnings {
        my $self = shift;

        return wantarray ? @{$self->{_warnings}} : scalar @{$self->{_warnings}};
}

=head2 clear_warnings()

Clear the list of warnings for this linter object.  It\'s automatically called
when you call C<check_record()>.

=cut

sub clear_warnings {
    my $self = shift;

    $self->{_warnings} = [];
}

=head2 warn( $str [, $str...] )

Create a warning message, built from strings passed, like a C<print>
statement.

Typically, you\'ll leave this to C<check_record()>, but industrious
programmers may want to do their own checking as well.

=cut

sub warn {
    my $self = shift;

    push( @{$self->{_warnings}}, join( "", @_ ) );

    return;
}

=head2 check_record( $marc )

Does all sorts of lint-like checks on the MARC record I<$marc>,
both on the record as a whole, and on the individual fields &
subfields.

=cut

sub check_record {
    my $self = shift;
    my $marc = shift;

    $self->clear_warnings();

    ( (ref $marc) && $marc->isa('MARC::Record') )
        or return $self->warn( "Must pass a MARC::Record object to check_record" );

 #Check for RDA
   my $rda=0;
   $rda++ if ($marc->field("264"));
   $rda++ if ($marc->field("336"));
   $rda++ if ($marc->field("337"));
   $rda++ if ($marc->field("338"));
   $rda++ if (substr($marc->leader(), 18, 1) eq "i");
   if ($rda > 1){$rda = 1} #sets the threshold for RDA records to 2 RDA attributes
   else {$rda = 0}
  # if ($rda == 1){$self->warn(">>>>>>>>>>>>>RDA RECORD<<<<<<<<<<<<<")}
   
   
   
   
   
   
    my @_1xx = $marc->field( "1.." );
    my $n1xx = scalar @_1xx;
    if ( $n1xx > 1 ) {
        $self->warn( "1XX: Only one 1XX field is allowed, but I found $n1xx of them." );
    }

#Checks for date in 008 vs date in 260/264 _c and _g subfields

    my $field008=$marc->field("008");
    
    if ($field008){
	$field008=$field008->as_string();
	my $datecode=substr($field008, 6, 1);
	my $date008=substr($field008, 7, 4);

	
	if ($datecode eq " "){
	    $self->warn("008: Lacks date code.");
	}
	
	elsif ($datecode eq "s"){

	    if ($date008 eq "uuuu"){

		$self->warn("008: If first date is uuuu date code must be n.");

	    }

           my $field_publ=$marc->field(260);
           my $field="";
	   if ($field_publ){$field="260"}
		   
	   else{
		   if (my@fields_publ=$marc->field(264)){
			   foreach $field_publ (@fields_publ){
			   
			   my $ind2=$field_publ->indicator(2);
			#if second indicator is 4 check 008 06 is set to "t"
			   if ($ind2 =~ /4/){
				if ($datecode ne "t"){$self->warn("008: If 264 \\4 copyright date is present 008 position 06 must be set to \"t\".")}
				my@subf = $field_publ->subfields();
			        my$subs = $subf[0][0];
				if ($subs ne "c"){$self->warn("264: 264 \\4 should only contain subfield _c.")}
				
			    }
		    }   
		    
			   if(($fields_publ[0]->indicator(2)) =~ /1/){
				   $field="264";
				   $field_publ=$fields_publ[0]}
			   
			   else{
				   undef $field_publ;
				   $self->warn("264: The 2nd indicator of the first 264 field isn't set to 1 (publication) please check.");
				   }}
		   }
	    if ($field_publ){
		my $field260c=$field_publ->as_string("c");
		my $date260="";
		$field260c=~ s/\-/u/g;
		if ($field260c =~ /(\d\d\d\d)/){
		    $date260= $1};
		if ($date260 ne $date008){
		    $self->warn("008: 008 date may not match $field date - please check.");
		}
            }
            
	}
	
	elsif ($datecode eq "n" && $date008 ne "uuuu"){

	    $self->warn("008: If date type is n, first date must be uuuu.");

	}

	my $placecode=substr($field008, 15, 3);

	if ($placecode eq "   "){
	    $self->warn("008: Lacks place code.");
	}

	elsif ($placecode eq "xxk"){
	    
	    $self->warn("008: Check place code xxk - please set code for specific UK member country eg England, Wales (if known).");
	    
	}
	
	elsif ($placecode eq "xxu"){
	    
	    $self->warn("008: Check place code xxu - please set code for specific state (if known).");
	    
	}

    }

#Gets date to check if item is a rare book

    my $date;
    $field008=$marc->field("008");
    my $field245;
    if ($field008){
	$field008=$field008->as_string();
	$date=substr($field008, 7, 2);
    }
     if ($date =~ /\d\d/){
#if item is post 1900, warns if local information present

    if ($date > 18){

	my $field_541=$marc->field(541);
	my $field_561=$marc->field(561);

	if ($field_541||$field_561){

	    $self->warn("Record is post 1900 but contains local information (541 or 561 fields) - please check.");
	}
    

#gets 245 field, subfield c

    my $field245=$marc->field(245);
    if ($field245){
	my $subfield_c=$field245->subfield("c");
	
#only checks for capitalisation if not a rare book (post 1900)
	if ($subfield_c){

	    if ($subfield_c=~/^By /){
		$self->warn ( "245: By should not be capitalised at start of subfield _c.");
	    }
	    if ($subfield_c=~/^Edited/){
		$self->warn ( "245: Edited should not be capitalised at start of subfield _c.");
	    }
	}
    }
    }
    }
#warn if there is a 1XX field present and the 1st indicator of the 245 is not 1, and if there are no 1XX fields, and the first indicator is 1.
    
    if (not $field245){my $field245 = $marc->field(245)}
    if ($field245){
	my $ind245=$field245->indicator(1);
    
	if (($n1xx>0) && ($ind245 ne '1')){
	    $self->warn("245: Since a 100, 110, 111 or 130 is present, 245 first indicator must be 1.");
	}
	if (($n1xx==0) && ($ind245 ne '0')){
	    $self->warn("245: If there is no 100, 110, 111 or 130 field, the first indicator in the 245 must be 0.");
	}
    }
#checks that if there is a 490 field and the first indicator is one that there is also an 8XX field

    my @_8xx = $marc->field( "8.." );
    my $n8xx = scalar @_8xx;
    my $field490=$marc->field(490);
    if ($field490){
	my $ind490=$field490->indicator(1);
	if (($ind490 eq '1') && (!$n8xx)){
	    $self->warn("490: If first indicator is 1, an 8XX field must be present.");
	}
    }

#checks that if there is an 830 field that there is also a 490 field

    my $field830=$marc->field("830");
    $field490=$marc->field(490);
    if (($field830) && (!$field490)){
	    $self->warn("830: If an 830 field is present, a 490 field must also be present.");
	}

#warns if there is a 440 field (no longer valid)

    my $field_440=$marc->field("440");

    if ($field_440){

	$self->warn("440: 440 field is no longer valid. Please use 490 and 830.");
    }

#checks for the presence of a 245 and 300 field: warns if not present

    if (not $marc->field(245)) {
        $self->warn("245: No 245 field present, record does not meet minimum standard.");
    }
    
    if (not $marc->field(300)){
	$self->warn("300: No 300 field present, record does not meet minimum standard.");
    }

#checks for presence of 260 field in AACR2 records
    
    if ($rda == 0){
    if (not $marc->field(260)){
	$self->warn("260: No 260 field present, record does not meet minimum standard.");
    }
 #check for original AACR2 records

    if (not $marc->field(035)){
	$self->warn("This AACR2 record appears to be original cataloguing, all new original records should be RDA standard.");
    }    

#check for RDA fields in AACR2 records
    
     if ($marc->field(264)){

	$self->warn("264: This AACR2 record should not contain a 264 field.");
    }

    if ($marc->field(336)){

	$self->warn("336: This AACR2 record should not contain a 336 field.");
    }

    if ($marc->field(337)){

	$self->warn("337: This AACR2 record should not contain a 337 field.");
    }

   if ($marc->field(338)){

	$self->warn("338: This AACR2 record should not contain a 338 field.");
    }
    
    }
    
    else{

    

#checks for presence of 264 field for RDA: warns if not present


    if (not $marc->field(264)){

	$self->warn("264: No 264 field present in this RDA record.");
    }
#33X fields (no advantage having these as subs as we need to check their existance, and do other checks if multiples)
 #336 checks   
    #checks presence of 336 and if multiple checks for subfield 3 carrier type
    my @content = $marc->field(336);
    my @media = $marc->field(337);
    
    if (not @content){
	$self->warn("336: No 336 field present, this RDA record does not meet minimum standard.");
    }
    else{  
	    my $warned = 0;
	    my $rdacont_warned = 0;
	    my $a336_warned = 0;
	#warn if there are multiple 336 without subfield 3, when there are multiple 337s
	    foreach my $field336 (@content){
	    if(scalar(@media) > 1){
	    if(scalar(@content) > 1){
		    if ((not $field336->subfield("3")) && ($warned < 1)){
			    $self->warn("336: Multiple 336 fields, may need to have subfield 3 (materials specified) set.");
	                    $warned++;
	            }}}
 
    #check 336 subfield 2 is set to "rdacontent" and if so check for subfield a and that it matches controlled vocabulary 
             if ($field336->subfield("2") eq "rdacontent"){
		if ($field336->subfield("a")){
		my $leader336=$contenta{$field336->subfield("a")};
               unless ($leader336){
			 my $a336=$field336->subfield("a");   
			 $self->warn("336: Subfield _a \"$a336\" does not match RDA controlled vocabulary - please check.");
		    }
		}
		elsif($a336_warned < 1){
			$self->warn("336: Subfield _a is a mandatory requirement.");
			$a336_warned++;
			}    
	    
	        }
             else{
                  if ($rdacont_warned < 1){
			    $self->warn("336: RDA records must use the controlled vocabulary and have subfield _2 set to rdacontent.");
			    $rdacont_warned++;
		    }
	        }  
	    }
	    
    #check Leader position 6 matches content in the first 336 field

	if (my $leader336=$contenta{$content[0]->subfield("a")}){
        my $leader6 = substr($marc->leader(),6,1);
            if ($leader336 !~ /$leader6/){
	       if (length($leader336) > 1){
		       my $leader3362 = substr($leader336, 1, 1);
		       $leader336 = substr($leader336,0,1)." or ".$leader3362;
	       }
	       my $field336a=$content[0]->subfield("a");
	       $self->warn("Leader: Position 06 should be set to \"$leader336\" for records with \"$field336a\" in field 336.");
            } 
        }
    }
	 
#337 checks
    #check RDA records have 337 field
    #my @media = $marc->field(337);
    if (not @media){

	$self->warn("337: No 337 field present, this RDA record does not meet minimum standard.");
    }
    #
    else{  
	    my $warned = 0;
	    my $rdacont_warned = 0;
	    my $a337_warned = 0;
	    foreach my $field337 (@media){
    #check multiple 337 have subfield 3
	    if(scalar(@media) > 1){
		    if ((not $field337->subfield("3")) && ($warned < 1)){
			    $self->warn("337: Multiple 337 fields, subfield 3 (materials specified) needs to be set.");
	                    $warned++;
	            }}
 
    #check 337 subfield 2 is set to "rdamedia" and if so subfield a matches controlled vocabulary 
             if ($field337->subfield("2") eq "rdamedia"){
		if (my $a337 = $field337->subfield("a")){
		    unless ($mediaa{$a337}){   
			 $self->warn("337: Subfield _a \"$a337\" does not match RDA controlled vocabulary - please check.");
			}
		    }
		elsif ($a337_warned < 1){
			$self->warn("337: Subfield _a is a mandatory requirement.");
			$a337_warned++;
			} 
	        }
             else{
                  if ($rdacont_warned < 1){
			    $self->warn("337: RDA records must use the controlled vocabulary and have subfield _2 set to rdamedia.");
			    $rdacont_warned++;
		    }
	        }  
	    	    
    #check for 007 if 337 subfield b would match [schgv] 
	if (my $media337=$mediaa{$field337->subfield("a")}){
            my $no007 = 0 ;
            if ($media337 =~ /[schgv]/){
		if (my @fields007 = $marc->field('007')){
		    foreach my $field007 (@fields007){
			if (my $med0 = substr($field007->as_string(), 0, 1)){
			    if ($media337 eq "g"){$media337 = "gm"}
			    if ($media337 =~ /$med0/){
			    $no007++;
			    }
			}
		    }
		    if ($no007 < 1){ 
			my $field337a=$field337->subfield("a");
			$self->warn("007: With 337 media type set to \"$field337a\" there needs to be a corresponding 007 field.");
			}   
		}
		else{
		    my $field337a=$field337->subfield("a");
		    $self->warn("007: With 337 media type set to \"$field337a\" there needs to be a corresponding 007 field.");
		    }
        }
	}

    }
    }
#338 checks
    #Check RDA record has field 338
    my @carrier = $marc->field(338);
    
    if (not @carrier){
	$self->warn("338: No 338 field present, this RDA record does not meet minimum standard.");
    }
    else{  
	    my $warned = 0;
	    my $rdacont_warned = 0;
	    my $a336_warned = 0;
	    
	    foreach my $field338 (@carrier){
	    if(scalar(@carrier) > 1){
		    if ((not $field338->subfield("3")) && ($warned < 1)){
			    $self->warn("338: Multiple 338 fields, subfield 3 (materials specified) needs to be set.");
	                    $warned++;
	            }}
 
    #check 338 subfield 2 is set to "rdacarrier" and if so check for subfield a and that it matches controlled vocabulary 
             if ($field338->subfield("2") eq "rdacarrier"){
		if ($field338->subfield("a")){
		my $leader338=$carriera{$field338->subfield("a")};
               unless ($leader338){
			 my $a338=$field338->subfield("a");   
			 $self->warn("338: Subfield _a \"$a338\" does not match RDA controlled vocabulary - please check.");
		    }
		}
		elsif($a336_warned < 1){
			$self->warn("338: Subfield a is a mandatory requirement.");
			$a336_warned++;
			}    
	    
	        }
             else{
                  if ($rdacont_warned < 1){
			    $self->warn("338: RDA records must use the controlled vocabulary and have subfield _2 set to rdacarrier.");
			    $rdacont_warned++;
		    }
	        }  
	    }
    }
   
#check for AACR2 fields in RDA record

    if ($marc->field(260)){
	$self->warn("260: For RDA records publication details should be recorded in the 264 field.");
    }


}
#checks for the presence of at least one 6xx field unless the 008 is set to a fiction coding

    $field008=$marc->field("008");
    if ($field008){
	$field008=$field008->as_string();
	my $litcode=substr($field008, 33, 1);
	unless ($litcode=~/[1cdfhjmpu]/){
	    my @_6xx=$marc->field("6..");
	    my $n6xx=scalar @_6xx;
	    if ($n6xx==0) {
		$self->warn( "6XX: Unless the Literary form in the 008 is set to one of the fiction codes, there must be at least one 6XX field (ignore if the work is a sacred text.)" );
	    }
	}
    }

    my $leader=$marc->leader();
#leader check
    if ($leader){

	my $cat_form=substr($leader, 18, 1);
        if ($rda == 0 && $cat_form ne "a"){
	    $self->warn( "Leader: Cataloguing form \"$cat_form\" in the leader should be set to \"a\" for AACR2 records" );   
	}
	if ($rda == 1 && $cat_form ne "i"){
	    $self->warn( "Leader: Cataloguing form \"$cat_form\" in the leader should be set to \"i\" for RDA records" );   
	}
    }
    



#################

    my %field_seen;
    my $rules = $self->{_rules};
    for my $field ( $marc->fields ) {
        my $tagno = $field->tag;
        my $tagrules = $rules->{$tagno} or next;

        if ( $tagrules->{NR} && $field_seen{$tagno} ) {
            $self->warn( "$tagno: Field is not repeatable." );
        }

        if ( $tagno >= 10 ) {
            for my $ind ( 1..2 ) {
                my $indvalue = $field->indicator($ind);
                if ( not ($indvalue =~ $tagrules->{"ind$ind" . "_regex"}) ) {
                    $self->warn(
                        "$tagno: Indicator $ind must be ",
                        $tagrules->{"ind$ind" . "_desc"},
                        " but it's \"$indvalue\""
                    );
                }
            } # for

            my %sub_seen;
            for my $subfield ( $field->subfields ) {
                my ($code,$data) = @$subfield;

                my $rule = $tagrules->{$code};
                if ( not defined $rule ) {
                    $self->warn( "$tagno: Subfield _$code is not allowed." );
                } elsif ( ($rule eq "NR") && $sub_seen{$code} ) {
                    $self->warn( "$tagno: Subfield _$code is not repeatable." );
                }

                if ( $data =~ /[\t\r\n]/ ) {
                    $self->warn( "$tagno: Subfield _$code has an invalid control character" );
                }

                ++$sub_seen{$code};
            } # for $subfields
        } # if $tagno >= 10

        elsif ($tagno < 10) {
            #check for subfield characters
            if ($field->data() =~ /\x1F/) {
                $self->warn( "$tagno: Subfields are not allowed in fields lower than 010" );
            } #if control field has subfield delimiter
        } #elsif $tagno < 10

        # Check to see if a check_xxx() function exists, and call it on the field if it does
        my $checker = "check_$tagno";
        if ( $self->can( $checker ) ) {
            $self->$checker( $field, $rda, $marc);
        }

        ++$field_seen{$tagno};
    } # for my $fields

    return;
}

=head2 check_I<xxx>( $field )

Various functions to check the different fields.  If the function doesn\'t exist, then it doesn\'t get checked.

=head2 check_020()

CURRENTLY COMMENTED OUT WITH HEAD/CUT - requires LWP::Simple to work

Looks at 020$a and reports errors if the check digit is wrong.
Looks at 020$z and validates number if hyphens are present.

Uses Business::ISBN to do validation. Thirteen digit checking is currently done
with the internal sub _isbn13_check_digit(), based on code from Business::ISBN.

TO DO (check_020):

 Fix 13-digit ISBN checking.




sub check_020 {


    use Business::ISBN;

    my $self = shift;
    my $field = shift;

###################################################

 break subfields into code-data array and validate data

    my @subfields = $field->subfields();

    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        my $isbnno = $data;
        #remove any hyphens
        $isbnno =~ s/\-//g;
        #remove nondigits
        $isbnno =~ s/^\D*(\d{9,12}[X\d])\b.*$/$1/;

        #report error if this is subfield 'a' 
        #and the first 10 or 13 characters are not a match for $isbnno
        if ($code eq 'a') { 
            if ((substr($data,0,length($isbnno)) ne $isbnno)) {
                $self->warn( "020: Subfield a may have invalid characters.");
            } #if first characters don't match

            #report error if no space precedes a qualifier in subfield a
            if ($data =~ /\(/) {
                $self->warn( "020: Subfield a qualifier must be preceded by space, $data.") unless ($data =~ /[X0-9] \(/);
            } #if data has parenthetical qualifier

            #report error if unable to find 10-13 digit string of digits in subfield 'a'
            if (($isbnno !~ /(?:^\d{10}$)|(?:^\d{13}$)|(?:^\d{9}X$)/)) {
                $self->warn( "020: Subfield a has the wrong number of digits, $data."); 
            } # if subfield 'a' but not 10 or 13 digit isbn
            #otherwise, check 10 and 13 digit checksums for validity
            else {
                if ((length ($isbnno) == 10)) {
                    $self->warn( "020: Subfield a has bad checksum, $data.") if (Business::ISBN::is_valid_checksum($isbnno) != 1); 
                } #if 10 digit ISBN has invalid check digit
                # do validation check for 13 digit isbn
#########################################
### Not yet fully implemented ###########
#########################################
                elsif (length($isbnno) == 13){
                    #change line below once Business::ISBN handles 13-digit ISBNs
                    my $is_valid_13 = _isbn13_check_digit($isbnno);
                    $self->warn( "020: Subfield a has bad checksum (13 digit), $data.") unless ($is_valid_13 == 1); 
                } #elsif 13 digit ISBN has invalid check digit
###################################################
            } #else subfield 'a' has 10 or 13 digits
        } #if subfield 'a'
        #look for valid isbn in 020$z
        elsif ($code eq 'z') {
            if (($data =~ /^ISBN/) || ($data =~ /^\d*\-\d+/)){
##################################################
## Turned on for now--Comment to unimplement ####
##################################################
                $self->warn( "020:  Subfield z is numerically valid.") if ((length ($isbnno) == 10) && (Business::ISBN::is_valid_checksum($isbnno) == 1)); 
            } #if 10 digit ISBN has invalid check digit
        } #elsif subfield 'z'

    } # while @subfields

} #check_020
=cut

=head2 _isbn13_check_digit($ean)

Internal sub to determine if 13-digit ISBN has a valid checksum. The code is
taken from Business::ISBN::as_ean. It is expected to be temporary until
Business::ISBN is updated to check 13-digit ISBNs itself.



sub _isbn13_check_digit { 

    my $ean = shift;
    #remove and store current check digit
    my $check_digit = chop($ean);

    #calculate valid checksum
    my $sum = 0;
    foreach my $index ( 0, 2, 4, 6, 8, 10 )
        {
        $sum +=     substr($ean, $index, 1);
        $sum += 3 * substr($ean, $index + 1, 1);
        }

    #take the next higher multiple of 10 and subtract the sum.
    #if $sum is 37, the next highest multiple of ten is 40. the
    #check digit would be 40 - 37 => 3.
    my $valid_check_digit = ( 10 * ( int( $sum / 10 ) + 1 ) - $sum ) % 10;

    return $check_digit == $valid_check_digit ? 1 : 0;

} # _isbn13_check_digit
=cut
#########################################

=head2 check_041( $field )

Warns if subfields are not evenly divisible by 3 unless second indicator is 7
(future implementation would ensure that each subfield is exactly 3 characters
unless ind2 is 7--since subfields are now repeatable. This is not implemented
here due to the large number of records needing to be corrected.). Validates
against the MARC Code List for Languages (L<http://www.loc.gov/marc/>) using the MARC::Lint::CodeData data pack to MARC::Lint (%LanguageCodes,
%ObsoleteLanguageCodes).

=cut





sub check_041 {


    my $self = shift;
    my $field = shift;

    # break subfields into code-data array (so the entire field is in one array)

    my @subfields = $field->subfields();
    my @newsubfields = ();

    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    }

    #warn if length of each subfield is not divisible by 3 unless ind2 is 7
    unless ($field->indicator(2) eq '7') {
        for (my $index = 0; $index <=$#newsubfields; $index+=2) {
            if (length ($newsubfields[$index+1]) %3 != 0) {
                $self->warn( "041: Subfield _$newsubfields[$index] must be evenly divisible by 3 or exactly three characters if ind2 is not 7, ($newsubfields[$index+1])." );
            }
##############################################
# validation against code list data
## each subfield has a multiple of 3 chars
# need to look at each group of 3 characters
            else {

                #break each character of the subfield into an array position
                my @codechars = split '', $newsubfields[$index+1];

                my $pos = 0;
                #store each 3 char code in a slot of @codes041
                my @codes041 = ();
                while ($pos <= $#codechars) {
                    push @codes041, (join '', @codechars[$pos..$pos+2]);
                    $pos += 3;
                }


                foreach my $code041 (@codes041) {
                    #see if language code matches valid code
                    my $validlang = 1 if ($LanguageCodes{$code041});
                    #look for invalid code match if valid code was not matched
                    my $obsoletelang = 1 if ($ObsoleteLanguageCodes{$code041});

                    # skip valid subfields
                    unless ($validlang) {
#report invalid matches as possible obsolete codes
                        if ($obsoletelang) {
                            $self->warn( "041: Subfield _$newsubfields[$index], $newsubfields[$index+1], may be obsolete.");
                        }
                        else {
                            $self->warn( "041: Subfield _$newsubfields[$index], $newsubfields[$index+1] ($code041), is not valid.");
                        } #else code not found 
                    } # unless found valid code
                } #foreach code in 041
            } # else subfield has multiple of 3 chars
##############################################
        } # foreach subfield
    } #unless ind2 is 7
} #check_041

=head2 check_043( $field )

Warns if each subfield a is not exactly 7 characters. Validates each code
against the MARC code list for Geographic Areas (L<http://www.loc.gov/marc/>)
using the MARC::Lint::CodeData data pack to MARC::Lint (%GeogAreaCodes,
%ObsoleteGeogAreaCodes).

=cut

sub check_043 {

    my $self = shift;
    my $field = shift;

    # break subfields into code-data array (so the entire field is in one array)

    my @subfields = $field->subfields();
    my @newsubfields = ();

    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    } # while

    #warn if length of subfield a is not exactly 7
    for (my $index = 0; $index <=$#newsubfields; $index+=2) {
#        if (($newsubfields[$index] eq 'a') && (length ($newsubfields[$index+1]) != 7)) {
#           $self->warn( "043: Subfield _a must be exactly 7 characters, $newsubfields[$index+1]" );}
	# if suba and length is not 7
        #check against code list for geographic areas.
        if ($newsubfields[$index] eq 'a') {

            #see if geog area code matches valid code
            my $validgac = 1 if ($GeogAreaCodes{$newsubfields[$index+1]});
            #look for obsolete code match if valid code was not matched
            my $obsoletegac = 1 if ($ObsoleteGeogAreaCodes{$newsubfields[$index+1]});

            # skip valid subfields
            unless ($validgac) {
                #report invalid matches as possible obsolete codes
                if ($obsoletegac) {
                    $self->warn( "043: Subfield _a, $newsubfields[$index+1], may be obsolete.");
                }
                else {
                    $self->warn( "043: Subfield _a, $newsubfields[$index+1], is not valid.");
                } #else code not found 
            } # unless found valid code

        } #elsif suba
    } #foreach subfield
} #check_043


=head2 check_100

Checks 100 field for punctuation, correct indicators and field structure.

=cut


sub check_100 {

    my $self = shift;
    my $field = shift;
    my $rda = shift;
    my @sfcodes;
    my $sfcode;

    # break subfields into code-data array (so the entire field is in one array)

    my @subfields = $field->subfields();
    my @newsubfields = ();

    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    }
    

#checks that  _a is present

    if ( not $field->subfield( "a" ) ) {
        $self->warn( "100: Must have a subfield _a." );
    }

#deal with _6 linking subfield at the start of the field and _4 and _8 subfields at end of field, removing them and dealing with the rest of the record as normal
    if ($newsubfields[0] eq '6') {
	shift(@newsubfields);
	shift(@newsubfields);
    }
    if (($newsubfields[$#newsubfields-1] eq '4') || ($newsubfields[$#newsubfields-1] eq '8')) {
	pop (@newsubfields);
	pop (@newsubfields);
    }


#Checks that field ends with full stop, dash or closing bracket

    if ($newsubfields[$#newsubfields] !~ /[\?\.\)\-\]]$/) {
        $self->warn ( "100: Should end with a full stop, dash or closing bracket");
    }

#subfield a should be first subfield
    if ($newsubfields[0] ne 'a') {
        $self->warn ( "100: First subfield must be _a, but it is _$newsubfields[0]");
    }

    
#there should be no spaces between any initials in subfield _a

if ($field->subfield("a")){

    for (my $index = 0; $index <=$#newsubfields; $index+=2){
	if ($newsubfields[$index] eq 'a') {
	    if (($newsubfields[$index+1] =~ /\b\w\.\b\w\./) && ($newsubfields[$index+1] !~ /\[\bi\.e\. \b\w\..*\]/)){
		$self->warn ( "100: Any initials in the _a subfield should be separated by a space");
	    }
	}
    }
}
    
#subfield _b, if present, should not be preceded by a mark of punctuation unless it is preceded by an abbreviation

    if ($field->subfield("b")) {
    
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'b') {
		if ($newsubfields[$index-1] !~ /[\w\)]$/){
		    $self->warn ( "100: Subfield _b must not be preceded by a mark of punctuation unless the preceding word is an abbreviation - please check");
		}
	    }
	}
    }

#each subfield c, if present, should be preceded by a comma unless it contains material in round brackets.

    if ($field->subfield("c")) {

        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'c') && ($newsubfields[$index+1] =~ /^\(.*\)/)) {
		if ($newsubfields[$index-1] =~ /,$/){
		    $self->warn ( "100: If subfield _c contains material in round brackets it should not be preceded by a comma");
	    }
	    }
	    if (($newsubfields[$index] eq 'c') && ($newsubfields[$index-1] !~ /,$/) && ($newsubfields[$index+1] !~ /^\(.*\)/)){
		$self->warn ( "100: Subfield _c must be preceded by a comma unless it contains material enclosed in round brackets");
	    }
	}
    }

#commas

@sfcodes=("d", "j");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ',$', $field, \@newsubfields, $self, "be preceded by a comma");
}

#round brackets

@sfcodes=("q");
foreach $sfcode(@sfcodes){
	nesubfcheckpost ($sfcode, '^\(.*\)', $field, \@newsubfields, $self, "contain material enclosed in round brackets");
}

#check subfield e is proceeded by comma or dash
if (my $sub=$field->subfield( "e" )){
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	if (($newsubfields[$index] eq 'e') && ($newsubfields[$index-1] !~ /[-,]$/)){
		$self->warn ( "100: Subfield _e must be precedeed by a comma or open date");
	    }
        }
        #check RDA records use standard relationship designators
	if ($rda == 1){
		my @sub=$field->subfield( "e" );
		foreach my$sub_e (@sub){
		$sub_e =~ s/[\.\,]//;
		unless (grep (/$sub_e/, @rel_des)){
			$self->warn ("100: The relationship designator in _e \"$sub_e\" is not a standard term please check");
		} }
	}
}
#warn if RDA records don't have subfield e
elsif ($rda == 1){
	$self->warn ("100: RDA records must have and a relationship designator in subfield _e.");
	}
	
    
} # check_100


=head2 check_110

Checks 110 field for punctuation, correct indicators and field structure.

=cut


sub check_110 {

    my $self = shift;
    my $field = shift;
    my $rda = shift;
    
    # break subfields into code-data array (so the entire field is in one array)

    my @subfields = $field->subfields();
    my @newsubfields = ();
    my @sfcodes;
    my $sfcode;

    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    }
    

#deal with _6 linking subfield at the start of the field and _4 and _8 subfields at end of field, removing them and dealing with the rest of the record as normal
    if ($newsubfields[0] eq '6') {
	shift(@newsubfields);
	shift(@newsubfields);
    }
    if (($newsubfields[$#newsubfields-1] eq '4') || ($newsubfields[$#newsubfields-1] eq '8')) {
	pop (@newsubfields);
	pop (@newsubfields);
    }


#checks that _a is present

    if ( not $field->subfield( "a" ) ) {
        $self->warn( "110: Must have a subfield _a." );
    }

#Checks that field ends with full stop or closing bracket

    if ($newsubfields[$#newsubfields] !~ /[\.\)\]]$/) {
        $self->warn ( "110: Should end with a full stop or closing bracket");
    }


#subfield a should be first subfield
    if ($newsubfields[0] ne 'a') {
        $self->warn ( "110: First subfield must be _a, but it is _$newsubfields[0]");
    }


#fullstops

@sfcodes=("b", "f", "h", "k", "l", "u");
foreach $sfcode(@sfcodes){
    nesubfcheckpre ($sfcode, '\.$', $field, \@newsubfields, $self, "be preceded by a full stop");
}




#check subfield e is proceeded by comma or dash
if (my $sub=$field->subfield( "e" )){
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	if (($newsubfields[$index] eq 'e') && ($newsubfields[$index-1] !~ /[-,]$/)){
		$self->warn ( "110: Subfield _e must be precedeed by a comma or open date");
	    }
        }
        #check RDA records use standard relationship designators
	if ($rda == 1){
		my@sub=$field->subfield( "e" );
		foreach my$sub_e (@sub){
		$sub_e =~ s/[\.\,]//;
		unless (grep (/$sub_e/, @rel_des)){
			$self->warn ("110: The relationship designator in _e \"$sub_e\" is not a standard term please check");
		} }
	}
}
#warn if RDA records don't have subfield e
elsif ($rda == 1){
	$self->warn ("110: RDA records must have and a relationship designator in subfield _e.");
	}
	


#each subfield c, if present, should be preceded by space-colon if _d is present, and contain material enclosed in round brackets if not


if ($field->subfield("c")){
    if ($field->subfield("d")) {
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'c') && ($newsubfields[$index-1] !~ / :$/)) {
		$self->warn ("110: If subfield _c is preceded by _d it should be preceded by space-colon");
	    }
	}
    }
    else {   
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'c') && ($newsubfields[$index+1] !~ /^\(.*\)/)) {
		$self->warn ( "110: Unless preceded by _d, _c should contain material enclosed in round brackets");
	    }
	}
    }
}

#each subfield _d, if present, should be preceded by space-semicolon if _n is present, and start witn an opening round bracket if not

if ($field->subfield("d")){
    if ($field->subfield("n")) {
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'd') && ($newsubfields[$index-1] !~ / :$/)) {
		$self->warn ("110: If subfield _d is preceded by _n it should be preceded by space-colon");
	    }
	}
    }
    elsif (($field->subfield("t")) && ($field->subfield("t")=~ /Treaties, etc\./)){
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'd') && ($newsubfields[$index-1] !~ /,$/)) {
		$self->warn ("110: If subfield _d is a treaty date it should be preceded by a comma");
	    }
	}
    }
    else {   
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'd') && ($newsubfields[$index+1] !~ /^\(.*/)) {
		$self->warn ( "110: Unless it is a treaty date, or is preceded by _n, _d should start with an opening round bracket");
	    }
	}
    }
}


#rules for _t, _g, _n and _p if a _t is present

if ($field->subfield("t")){

#subfield _t should be preceded by a full stop

    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	if ($newsubfields[$index] eq 't') {
	    if ($newsubfields[$index-1] !~ /\.$/){
		$self->warn ("110: Subfield _t must be preceded by a full stop");
	    }
	}
    }
    
#subfield _g, if present, should be preceded by a full stop
    
    if ($field->subfield("g")) {
	my $gposition;
        my $tposition;
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'g') {
                $gposition=$index;}
            }
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 't') {
                $tposition=$index;}
            }

            if (($gposition) && ($tposition)){
                if ($gposition > $tposition){
		if ($newsubfields[$gposition-1] !~ /\.$/){
		    $self->warn ("110: Following a _t, subfield _g must be preceded by a full stop");
		}
	    }
	}
    }

#subfield _n, if present, should be preceded by a full stop
    
    if ($field->subfield("n")) {
	
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'n') { 
		if ($newsubfields[$index-1] !~ /\.$/){
		    $self->warn ("110: Following a _t, subfield _n must be preceded by a full stop");
		}
	    }
	}
    }
    
    
#each subfield p, if present, must be preceded by a comma if it follows subfield n, or by a full stop  following other subfields
    
    if ($field->subfield("p")) {
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'p') {
                if (($newsubfields[$index-2] eq 'n') && ($newsubfields[$index-1] !~ /,$/)) {
                    $self->warn ("110: If there is a _t, subfield _p must be preceded by a comma when it follows subfield _n.");
                }
		elsif (($newsubfields[$index-2] ne 'n') && ($newsubfields[$index-1] !~ /\.$/)) {
		    $self->warn ( "110: If there is _t, subfield _p must be preceded by full stop when it follows a subfield other than _n.");
		}
	    }
	}
    }
}

else {

#rules for _n and _p if there is no _t subfield


#unless it follows a form heading of manuscript, _n starts with an opening round bracket

    if ($field->subfield("n")){
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	
		if (($newsubfields[$index] eq 'n') && ($newsubfields[$index+1] !~ /^\(.*/) && ($newsubfields[$index-1] !~ /Manuscript/)) {
		    $self->warn ("110: Unless it follows a _t or a form heading of Manuscript, _n should start with an opening round bracket");
		    
		}
	    }
    }

#if it follows a form heading of Manuscript, _p should not be preceded by a mark of punctuation 

    if ($field->subfield("p")){
	for (my $index = 2; $index <=$#newsubfields; $index+=2){
	    if ($newsubfields[$index] eq 'p') { 
		if (($newsubfields[$index-1] =~ /^Manuscript/) && ($newsubfields[$index-1] !~ /\w$/)){
		    $self->warn ("110: Following a _k containing Manuscript, _p should not be preceded by a mark of punctuation");
		}
	    }
	}
    }
}
    

} # check_110


=head2 check_111

Checks 111 field for punctuation, correct indicators and field structure.

=cut


sub check_111 {

    my $self = shift;
    my $field = shift;

    my @sfcodes;
    my $sfcode;

    # break subfields into code-data array (so the entire field is in one array)

    my @subfields = $field->subfields();
    my @newsubfields = ();

    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    }
    


#deal with _6 linking subfield at the start of the field and _4 and _8 subfields at end of field, removing them and dealing with the rest of the record as normal
    if ($newsubfields[0] eq '6') {
	shift(@newsubfields);
	shift(@newsubfields);
    }
    if (($newsubfields[$#newsubfields-1] eq '4') || ($newsubfields[$#newsubfields-1] eq '8')) {
	pop (@newsubfields);
	pop (@newsubfields);
    }



#checks that _a is present

    if ( not $field->subfield( "a" ) ) {
        $self->warn( "111: Must have a subfield _a." );
    }

#Checks that field ends with full stop or closing bracket

    if ($newsubfields[$#newsubfields] !~ /[\.\)\]]$/) {
        $self->warn ( "111: Should end with a full stop or closing bracket");
    }

#subfield a should be first subfield
    if ($newsubfields[0] ne 'a') {
        $self->warn ( "111: First subfield must be _a, but it is _$newsubfields[0]");
    }


#each subfield c, if present, should be preceded by space-colon if _d is present, and contain material enclosed in round brackets if not


    if ($field->subfield("c")){
	if (($field->subfield("d"))||($field->subfield("n"))) {
	    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
		if (($newsubfields[$index] eq 'c') && ($newsubfields[$index-1] !~ / :$/)) {
		    $self->warn ("111: If subfield _c is preceded by _d or _n it should be preceded by space-colon");
		}
	    }
	}
	else {   
	    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
		if (($newsubfields[$index] eq 'c') && ($newsubfields[$index+1] !~ /^\(.*\)/)) {
		    $self->warn ( "111: Unless preceded by _d or _n, _c should contain material enclosed in round brackets");
		}
	    }
	}
    }

#each subfield _d, if present, should be preceded by space-semicolon if _n is present, and start witn an opening round bracket if not

    if ($field->subfield("d")){
	if ($field->subfield("n")) {
	    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
		if (($newsubfields[$index] eq 'd') && ($newsubfields[$index-2] eq 'n') && ($newsubfields[$index-1] !~ / :$/)) {
		    $self->warn ("111: If subfield _d is preceded by _n it should be preceded by space-colon");
		}
	    }
	}

	if ($field->subfield("q")) {
	    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
		if (($newsubfields[$index] eq 'd') && ($newsubfields[$index-2] eq 'q') && ($newsubfields[$index-1] !~ /,$/)) {
		    $self->warn ("111: If subfield _d is preceded by _q it should be preceded by a comma");
		}
	    }
	}

	if ((not $field->subfield("n")) && (not $field->subfield("q"))) {   
	    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
		if (($newsubfields[$index] eq 'd') && ($newsubfields[$index+1] !~ /^\(.*/)) {
		    $self->warn ( "111: Unless preceded by _n or _q, _d should start with an opening round bracket");
		}
	    }
	}
    }


#fullstops

    @sfcodes=("e", "f", "k", "l", "u");
    foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, '\.$', $field, \@newsubfields, $self, "be preceded by a full stop");
    }

#commas
    
    @sfcodes=("g");
    foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ',$', $field, \@newsubfields, $self, "be preceded by a comma");
    }


#rules for _t, _n and _p if a _t is present

    if ($field->subfield("t")){

#subfield _t should be preceded by a full stop

	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if ($newsubfields[$index] eq 't') { 
		if ($newsubfields[$index-1] !~ /\.$/){
		    $self->warn ("111: Subfield _t must be preceded by a full stop");
		}
	    }
	}
    
    
#subfield _n, if present, should be preceded by a full stop
    
	if ($field->subfield("n")) {
	    
	    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
		if ($newsubfields[$index] eq 'n') { 
		    if ($newsubfields[$index-1] !~ /\.$/){
			$self->warn ("111: Following a _t, subfield _n must be preceded by a full stop");
		    }
		}
	    }
	}
	
	
#subfield _p, if present, should be preceded by a full stop
	
	if ($field->subfield("p")) {
	
	    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
		if ($newsubfields[$index] eq 'p') { 
		    if ($newsubfields[$index-1] !~ /\.$/){
			$self->warn ("111: Following a _t, subfield _p must be preceded by a full stop");
		    }
		}
	    }
	}

	
    }

    else {

#rules for _n if there is no _t subfield


#_n starts with an opening round bracket

	if ($field->subfield("n")){
	    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
		
		if (($newsubfields[$index] eq 'n') && ($newsubfields[$index+1] !~ /^\(.*/)) {
		    $self->warn ("111: Unless it follows a _t, _n should start with an opening round bracket");
		    
		}
	    }
	}

    }
    

} # check_111



=head2 check_130

Checks 130 field for punctuation, correct indicators and field structure.

=cut


sub check_130 {

    my $self = shift;
    my $field = shift;
    my @sfcodes;
    my $sfcode;

    # break subfields into code-data array (so the entire field is in one array)

    my @subfields = $field->subfields();
    my @newsubfields = ();

    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    }
    

#checks that  _a is present

    if ( not $field->subfield( "a" ) ) {
        $self->warn( "130: Must have a subfield _a." );
    }

#Checks that field ends with full stop or closing bracket

    if ($newsubfields[$#newsubfields] !~ /[\.\)\]]$/) {
        $self->warn ( "130: Should end with a full stop or closing bracket");
    }


#deal with _6 linking subfield at the start of the field, removing it and dealing with the rest of the record as normal
    if ($newsubfields[0] eq '6') {
	shift(@newsubfields);
	shift(@newsubfields);
    }


#subfield a should be first subfield
    if ($newsubfields[0] ne 'a') {
        $self->warn ( "130: First subfield must be _a, but it is _$newsubfields[0]");
    }



#round brackets

@sfcodes=("d");
foreach $sfcode(@sfcodes){
	nesubfcheckpost ($sfcode, '^\(.*\)', $field, \@newsubfields, $self, "contain material enclosed in round brackets");
}


#fullstops, question marks or explanation marks

    @sfcodes=("f", "g", "h", "k", "l", "s");
    foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, '[\.\?\!]$', $field, \@newsubfields, $self, "be preceded by a full stop, question mark or exclamation mark");
}

#commas

@sfcodes=("m", "r");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ',$', $field, \@newsubfields, $self, "be preceded by a comma");
}


#semicolons

@sfcodes=("o");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ';$', $field, \@newsubfields, $self, "be preceeded by a semicolon");
}

#commas or full stops


@sfcodes=("n");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, '[\.,]$', $field, \@newsubfields, $self, "be preceeded by a comma or a full stop");
}


#each subfield p, if present, must be preceded by a comma if it follows subfield n, or by a full stop  following other subfields
    if ($field->subfield("p")) {
	
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'p') {
                if (($newsubfields[$index-2] eq 'n') && ($newsubfields[$index-1] !~ /(\S,$)|(\-\- ,$)/)) {
                    $self->warn ("130: Subfield _p must be preceded by a comma when it follows subfield _n.");
                }
		elsif (($newsubfields[$index-2] ne 'n') && ($newsubfields[$index-1] !~ /(\S\.$)|(\-\- \.$)/)) {
		    $self->warn ( "130: Subfield _p must be preceded by full stop when it follows a subfield other than _n.");
		}
	    }
	}
    }



} # check_130



=head2 check_240

Checks 240 field for punctuation, correct indicators and field structure.

=cut


sub check_240 {

    my $self=shift;
    my $field=shift;
    my @sfcodes;
    my $sfcode;

    # break subfields into code-data array (so the entire field is in one array)

    my @subfields = $field->subfields();
    my @newsubfields = ();

    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    }
    

#checks that _a is present

    if ( not $field->subfield( "a" ) ) {
        $self->warn( "240: Must have a subfield _a." );
    }


    if ($newsubfields[$#newsubfields] =~ /\.$/) {
        $self->warn ( "240: Should not end with a full stop unless the final word is an abbreviation");
    }


#deal with _6 linking subfield at the start of the field, removing it and dealing with the rest of the record as normal
    if ($newsubfields[0] eq '6') {
	shift(@newsubfields);
	shift(@newsubfields);
    }


#subfield a should be first subfield
    if ($newsubfields[0] ne 'a') {
        $self->warn ( "240: First subfield must be _a, but it is _$newsubfields[0]");
    }


#round brackets

@sfcodes=("d");
foreach $sfcode(@sfcodes){
	nesubfcheckpost ($sfcode, '^\(.*\)', $field, \@newsubfields, $self, "contain material enclosed in round brackets");
}



#fullstops, question marks or explanation marks

    @sfcodes=("f", "g", "h", "k", "l", "s");
    foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, '[\.\?\!]$', $field, \@newsubfields, $self, "be preceded by a full stop, question mark or exclamation mark");
}



#commas

@sfcodes=("m", "r");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ',$', $field, \@newsubfields, $self, "be preceded by a comma");
}


#semicolons

@sfcodes=("o");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ';$', $field, \@newsubfields, $self, "be preceeded by a semicolon");
}

#commas or full stops

@sfcodes=("n");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, '[\.,]$', $field, \@newsubfields, $self, "be preceeded by a comma or a full stop");
}


#each subfield p, if present, must be preceded by a comma if it follows subfield n, or by a full stop  following other subfields
    if ($field->subfield("p")) {
	
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'p') {
                if (($newsubfields[$index-2] eq 'n') && ($newsubfields[$index-1] !~ /(\S,$)|(\-\- ,$)/)) {
                    $self->warn ("240: Subfield _p must be preceded by a comma when it follows subfield _n.");
                }
		elsif (($newsubfields[$index-2] ne 'n') && ($newsubfields[$index-1] !~ /(\S\.$)|(\-\- \.$)/)) {
		    $self->warn ( "240: Subfield _p must be preceded by full stop when it follows a subfield other than _n.");
		}
	    }
	}
    }
    

#checks that first indicator is 1

    my $firstindval=$field->indicator(1);

    if ($firstindval ne '1'){
	$self->warn("240: First indicator value should usually be one - please check");
    }

} # check_240


=head2 check_245( $field )

Checks 245 field for valid subfields, punctuation etc.

=cut

sub check_245 {

    my $self = shift;
    my $field = shift;
    my $rda = shift;

    
    my @sfcodes;
    my $sfcode;

    if ( not $field->subfield( "a" ) ) {
        $self->warn( "245: Must have a subfield _a." );
    }

    # break subfields into code-data array (so the entire field is in one array)

    my @subfields = $field->subfields();
    my @newsubfields = ();

    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    }
    
# 245 must end with a full stop (should this include exclamation and question marks?)
    if ($newsubfields[$#newsubfields] !~ /[\.]$/) {
        $self->warn ( "245: Must end with a full stop.");
    }

#deal with _6 linking subfield at the start of the field, removing it
#and dealing with the rest of the record as normal
    if ($newsubfields[0] eq '6') {
	shift(@newsubfields);
	shift(@newsubfields);
    }


#subfield a should be first subfield
    if (($newsubfields[0] ne 'a')) {
        $self->warn ( "245: First subfield must be _a, but it is _$newsubfields[0]");
    }
    
#subfield c, if present, should be last subfield
    if ($field->subfield("c")) {
	if ($newsubfields[$#newsubfields-1] ne 'c'){
	    $self->warn ("245: Subfield c, if present, must be last subfield.");
	}
    }

#subfield c, if present, must be preceded by space forward slash
#also look for space between initials
    if ($field->subfield("c")) {


        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
# 245 subfield c must be preceded by space forward slash
            if ($newsubfields[$index] eq 'c') {

                $self->warn ( "245: Subfield _c must be preceded by space forward slash") if ($newsubfields[$index-1] !~ /\s\/$/);
                # 245 subfield c initials should not have space
                $self->warn ( "245: Subfield _c initials should not have a space.") if (($newsubfields[$index+1] =~ /\b\w\. \b\w\./) && ($newsubfields[$index+1] !~ /\[\bi\.e\. \b\w\..*\]/));
                last;
            }
        }
    }



#fullstops

@sfcodes=("n");
foreach $sfcode(@sfcodes){
    nesubfcheckpre ($sfcode, '\.$', $field, \@newsubfields, $self, "be preceded by a full stop");
}


#space-colon, space-equals sign or space-colon 

@sfcodes=("b");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ' [;=:]$', $field, \@newsubfields, $self, "be preceded by space-colon, space-semicolon or space-equals sign - please check.");
}



#each subfield h, if present, should be preceded by non-space
    if ($field->subfield("h")) {
        #RDA records should not have subfield 'h'
        if ($rda == 1){$self->warn ( "245: Subfield _h is not valid in RDA records. Use fields 336-338")}
        else{
        #AACR2 check subfield h
        #245 subfield h should not be preceded by space
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            #report error if subfield 'h' is preceded by space (unless dash-space)
            if (($newsubfields[$index] eq 'h') && ($newsubfields[$index-1] !~ /(\S$)|(\-\- $)/)) {
                $self->warn ( "245: Subfield _h should not be preceded by space.");
            } #if h and not preceded by no-space (unless dash)
            #report error if subfield 'h' does not start with open square bracket with a matching close bracket
            ##could have check against list of valid values here
            if (($newsubfields[$index] eq 'h') && ($newsubfields[$index+1] !~ /^\[\w*\s*\w*\]/)) {
                $self->warn ( "245: Subfield _h may have invalid material designator, or lack square brackets, $newsubfields[$index].");
            }
        }
    }}


#each subfield p, if present, must be preceded by a comma if it follows subfield n, or by a full stop  following other subfields
    if ($field->subfield("p")) {
	
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'p') {
                if (($newsubfields[$index-2] eq 'n') && ($newsubfields[$index-1] !~ /(\S,$)|(\-\- ,$)/)) {
                    $self->warn ("245: Subfield _p must be preceded by a comma when it follows subfield _n.");
                }
		elsif (($newsubfields[$index-2] ne 'n') && ($newsubfields[$index-1] !~ /(\S\.$)|(\-\- \.$)/)) {
		    $self->warn ( "245: Subfield _p must be preceded by full stop when it follows a subfield other than _n.");
		}
	    }
	}
    }


######################################
#check for invalid 2nd indicator
$self->_check_article($field);

} # check_245



sub check_245_articles {

    my $self = shift;
    my $field = shift;

$self->_check_article($field);
}


sub check_245_fullstops {

    my $self = shift;
    my $field = shift;
    
    # break subfields into code-data array (so the entire field is in one array)

    my @subfields = $field->subfields();
    my @newsubfields = ();

    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    } # while
    
    # 245 must end in full stop (may want to make this less restrictive by allowing trailing spaces)
    #do 2 checks--for final punctuation (MARC21 rule), and for period (LCRI 1.0C, Nov. 2003)
    if ($newsubfields[$#newsubfields] !~ /[.]$/) {
        $self->warn ( "245: Must end with full stop.");
    }

} # check_245_fullstops


=head2 check_250

Checks 250 field for punctuation, correct indicators and field structure.

=cut


sub check_250 {

    my $self = shift;
    my $field = shift;

    my @sfcodes;
    my $sfcode;    
    
    # break subfields into code-data array (so the entire field is in one array)
    
    my @subfields = $field->subfields();
    my @newsubfields = ();
    
    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    }
    
    
#checks that  _a is present
    
    if ( not $field->subfield( "a" ) ) {
        $self->warn( "250: Must have a subfield _a." );
    }
    

#deal with _6 linking subfield at the start of the field
if ($newsubfields[0] eq '6') {
    shift(@newsubfields);
    shift(@newsubfields);
}

    
    if ($newsubfields[$#newsubfields] !~ /\.$/) {
    $self->warn ( "250: Should end with a full stop.");
}

#subfield a should be first subfield
    if ($newsubfields[0] ne 'a') {
        $self->warn ( "250: First subfield must be _a, but it is _$newsubfields[0]");
    }


#space-forward slash or space-equals sign 

@sfcodes=("b");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ' [\/=]$', $field, \@newsubfields, $self, "be preceded by space-forward slash or space-equals sign.");
}

} # check_250



=head2 check_260

Checks 260 field for punctuation and field structure.

=cut


sub check_260 {

    my $self = shift;
    my $field = shift;

    my $ind260=$field->indicator(1);

#only checks if first indicator is blank - different rules for further 260s
    if (!$ind260){


	my @sfcodes;
	my $sfcode;
	
# break subfields into code-data array (so the entire field is in one array)
	
	my @subfields = $field->subfields();
	my @newsubfields = ();
	
	while (my $subfield = pop(@subfields)) {
	    my ($code, $data) = @$subfield;
	    unshift (@newsubfields, $code, $data);
	} # while
	
	
#checks that field ends with a full stop, closing square bracket or hyphen unless $g is present
	
	if ((not $field->subfield("g")) && (not $field->subfield("f"))) {
	    if ($newsubfields[$#newsubfields] !~ /[\.\]\-]$/) {
		$self->warn ( "260: Must end with a full stop, closing square bracket or hyphen unless _f or _g are present");
	    }
	}
	
	
#checks that at least one _a, _b and _c are present
	
	if ( not $field->subfield( "a" ) ) {
	    $self->warn( "260: Must have a subfield _a." );
	}
	
	if ( not $field->subfield( "b" ) ) {
	    $self->warn( "260: Must have a subfield _b." );
	}
	
	if ( not $field->subfield( "c" ) ) {
	    $self->warn( "260: Must have a subfield _c." );
	}
	
#removes any _3 and _6 subfields at start
	if ($newsubfields[0] eq '6') {
	    shift(@newsubfields);
	    shift(@newsubfields);
	}

	if ($newsubfields[0] eq '3') {
	    shift(@newsubfields);
	    shift(@newsubfields);
	}
	
	
#subfield a should be first subfield
	if ($newsubfields[0] ne 'a') {
	    $self->warn ( "260: First subfield must be _a, but it is _$newsubfields[0]");
	}
	
	
	
#space-colons
	
	@sfcodes=("b");
	foreach $sfcode(@sfcodes){
	    nesubfcheckpre ($sfcode, ' [:]$', $field, \@newsubfields, $self, "be preceded by a space-colon");
	}
	
#commas
	
	@sfcodes=("c");
	foreach $sfcode(@sfcodes){
	    nesubfcheckpre ($sfcode, ',$', $field, \@newsubfields, $self, "be preceded by a comma");
	}
	
	
#space-semicolons
	
	@sfcodes=("a");
	foreach $sfcode(@sfcodes){
	    nesubfcheckpre ($sfcode, ' ;$', $field, \@newsubfields, $self, "be preceded by a space-semicolon");
	}
	
	
	
	
#checks for subfield _e, in relation to _f, _g etc.
	
	if ($field->subfield("e")){
	    
	    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
		
#checks that _e opens with a round bracket
		
		if (($newsubfields[$index] eq 'e') && ($newsubfields[$index+1] !~ /^\(/)) {
		    $self->warn ( "260: subfield _e must start with an opening round bracket");
		}
		
	    }
#checks that if there is a _e, there is also a _f
	    
	    if (not $field->subfield("f")){
		$self->warn ( "260: _e is normally followed by _f");
	    }	    
	    if ($field->subfield("f")) {
		for (my $index = 2; $index <=$#newsubfields; $index+=2) {
#report error if subfield 'f' is not preceded by space : colon
		    if (($newsubfields[$index] eq 'f') && ($newsubfields[$index-1] !~ / [:]$/)) {
			$self->warn ( "260: Subfield _f should be preceded by space-colon");
		    } #if
		}#for
		    
		    if ($field->subfield("g")){
			
			for (my $index = 2; $index <=$#newsubfields; $index+=2) {
#report error if there is a _f and subfield 'g' is not preceded by comma, space
			    if (($newsubfields[$index] eq 'g') && ($newsubfields[$index-1] !~ /,$/)) {
				$self->warn ( "260: Subfield _g should be preceded by comma space if preceded by a subfield _f");
			    }
			}
		    }
		
		if (not $field->subfield("g")){
		    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
#report error if there is no _g and _f does not end with a round bracket
			if (($newsubfields[$index] eq 'f') && ($newsubfields[$index+1] !~ /\)$/)) {
			    $self->warn ( "260: If there is no _g, _f must end with a closing round bracket");
			}
		    }
		}   
	    }
	}
	
	
	
#each subfield g, if present, and if there is no subfield _f, should contain material in brackets
	if ($field->subfield("g")) {
	    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
		
		if (not $field->subfield("f")){
#report error if subfield 'g' does not start with open bracket with a matching close bracket
		    
		    if (($newsubfields[$index] eq 'g') && ($newsubfields[$index+1] !~ /^\(.*\)$/)) {
			$self->warn ( "260: If there is no _f, contents of _g must be enclosed in round brackets with no full stop afterwards");
		    }
		}
	    }
	    
	}
	
#checks that _c is the last subfield unless a _f or _g are present, and that if _g is the last subfield it is preceded by a $c or $f
	
	my $last=scalar(@newsubfields);
	if ($newsubfields[$last-2] ne 'c'){
	    if (($newsubfields[$last-2] ne 'g') && ($newsubfields[$last-2] ne 'f')){
		$self->warn ( "260: Subfield c must be last subfield unless _f or _g are present");
	    }
	    
	}
	
    }

} # check_260
    
sub check_264{
	
    my $self = shift;
    my $field = shift;
    my $rda = shift;

    my $ind264=$field->indicator(2);

#only checks publisher 264 - different rules for further 264s
    if ($ind264 =~ /[123]/){
	    
	my @sfcodes;
	my $sfcode;
	
# break subfields into code-data array (so the entire field is in one array)
	
	my @subfields = $field->subfields();
	my @newsubfields = ();
	
	while (my $subfield = pop(@subfields)) {
	    my ($code, $data) = @$subfield;
	    unshift (@newsubfields, $code, $data);
	} # while

#checks that at least one _a, _b and _c are present
	
	if ( not $field->subfield( "a" ) ) {
	    $self->warn( "264: Must have a subfield _a." );
	}
	
	if ( not $field->subfield( "b" ) ) {
	    $self->warn( "264: Must have a subfield _b." );
	}
	
	my $field264c = $field->subfield("c");
	if ( not $field264c ) {
	    $self->warn( "264: Must have a subfield _c." );
	}
	#check subfield c end with full stop, hyphen or closing square bracket
	elsif ($field264c !~ /[\-\.\]]$/) {
		$self->warn( "264: Subfield _c must end with full stop, hyphen or closing square bracket.");
	}
	
	
#removes any _3 and _6 subfields at start
	if ($newsubfields[0] eq '6') {
	    shift(@newsubfields);
	    shift(@newsubfields);
	}

	if ($newsubfields[0] eq '3') {
	    shift(@newsubfields);
	    shift(@newsubfields);
	}
	
	
#subfield a should be first subfield
	if ($newsubfields[0] ne 'a') {
	    $self->warn ( "264: First subfield must be _a, but it is _$newsubfields[0]");
	}
	
#space-colons
	
	@sfcodes=("b");
	foreach $sfcode(@sfcodes){
	    nesubfcheckpre ($sfcode, ' [:]$', $field, \@newsubfields, $self, "be preceded by a space-colon");
	}
	
#commas
	
	@sfcodes=("c");
	foreach $sfcode(@sfcodes){
	    nesubfcheckpre ($sfcode, ',$', $field, \@newsubfields, $self, "be preceded by a comma");
	}
	
	
#space-semicolons
	
	@sfcodes=("a");
	foreach $sfcode(@sfcodes){
	    nesubfcheckpre ($sfcode, ' ;$', $field, \@newsubfields, $self, "be preceded by a space-semicolon");
	}
}
#copyright 264 check for symbol and punctuation
elsif ($ind264 =~ /4/){
	my $field264c = $field->as_string("c");
	if ($field264c =~ /[\.\]\)\-]$/){$self->warn("264: 264 \\4 should not end with punctuation.")}
	if (substr($field264c,0,1) =~ /\d/){$self->warn("264: 264 \\4 should have a Â© at the start subfield c.")}
}
}
=head2 check_300

Checks 300 field for punctuation, correct indicators and field structure.

=cut


sub check_300 {

    my $self=shift;
    my $field=shift;
    my $rda = shift;
    my $marc = shift;
    
    my @sfcodes;
    my $sfcode;    

    # break subfields into code-data array (so the entire field is in one array)
    
    my @subfields = $field->subfields();
    my @newsubfields = ();
    
    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    }
    
    


    


#deal with _6 linking subfield at the start of the field and _3 and _8 subfields at end of field, removing them and dealing with the rest of the record as normal
    if ($newsubfields[0] eq '6') {
	shift(@newsubfields);
	shift(@newsubfields);
    }
    if (($newsubfields[$#newsubfields-1] eq '3') || ($newsubfields[$#newsubfields-1] eq '8')) {
	pop (@newsubfields);
	pop (@newsubfields);
    }


    
#Checks that field ends with full stop or closing bracket

    if (($newsubfields[$#newsubfields] !~ /[\.\)\]]$/) && ($rda == 0)) {
        $self->warn ( "300: Should end with a full stop or closing bracket.");
    }
    
#checks that  _a is present
    
    if ( not $field->subfield( "a" ) ) {
        $self->warn( "300: Must have a subfield _a." );
    }
    
#subfield a should be first subfield
    if ($newsubfields[0] ne 'a') {
        $self->warn ( "300: First subfield must be _a, but it is _$newsubfields[0]");
    }
    else{
#checks _a for punctuation

    for (my $index = 0; $index <=$#newsubfields; $index+=2) {
    if (($newsubfields[$index] eq 'a') && ($newsubfields[$index+1] =~ /\d+[a-z]/)) {
	$self->warn ( "300: In subfield _a there should be a space between the number and the type of unit - please check.");
    }
    if (($newsubfields[$index] eq 'a') && ($newsubfields[$index+1] =~ /,\w/)) {
	$self->warn ( "300: In subfield _a there should be a space between the comma and the next set of digits.");
    }
    if ($rda == 0){
	if (($newsubfields[$index] eq 'a') && ($newsubfields[$index+1] =~ /p\s/)) {
	    $self->warn ( "300: In subfield _a, p should be followed by a full stop.");
	}
	if (($newsubfields[$index] eq 'a') && ($newsubfields[$index+1] =~ /v\s/)) {
	    $self->warn ( "300: In subfield _a, v should be followed by a full stop.");
	}
    }
    elsif (($newsubfields[$index+1] =~ /\./) && ($newsubfields[$index] eq 'a')){
	    $self->warn("300: Subfield _a should not include an abbreviation -  please check.");
	} 
	
    
    
    # if (($newsubfields[$index] eq 'a') && ($newsubfields[$index+1] =~ /\d+v/)) {
	# $self->warn ( "300: There should be a space between the number and the v. in pagination - please check");
    # }
    }
    }
if (($marc->field(490)) && ($newsubfields[$#newsubfields] !~ /[\.\)]$/) && ($rda == 1)){
		$self->warn("300: If a 490 is present the 300 field must end with a full stop or closing parenthesis.");
	}
#each subfield b, if present, should be preceded by a colon
       if ($field->subfield("b")) {

        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
#report error if subfield 'b' is not preceded by space : colon
            if (($newsubfields[$index] eq 'b') && ($newsubfields[$index-1] !~ / [:]$/)) {
                $self->warn ( "300: Subfield _b should be preceded by space-colon.");
            }

#check that ill is followed by a full stop
	        if (($newsubfields[$index] eq 'b') && ($newsubfields[$index+1] =~ /ill\b\s/)) {
	$self->warn ( "300: The abbreviation ill should be followed by a full stop.");
    }

        }
    }

#checks that  _c is present
    
    if ( not $field->subfield( "c" ) ) {
        $self->warn( "300: Must have a subfield _c." );
    }
    #each subfield c, if present, should be preceded by a semicolon
    else{

        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
#report error if subfield 'c' is not preceded by space semi colon
            if (($newsubfields[$index] eq 'c') && ($newsubfields[$index-1] !~ / [;]$/)) {
                $self->warn ( "300: Subfield _c should be preceded by space-semi colon.");
            }

#check that cm is followed by a full stop (aacr2)
	    if (($newsubfields[$index] eq 'c') && ($newsubfields[$index+1] =~ /cm\b\s/) && ($rda == 0)) {
		$self->warn ( "300: The abbreviation cm should be followed by a full stop.");
	    }
#check RDA records don't include abbreviations
	    if (($newsubfields[$index] eq 'c') && ($newsubfields[$index+1] =~ /\./) && ($rda == 1)){
		 unless(($newsubfields[$index+1] =~ /[\.\)]$/) && ($marc->field(490))){
		 $self->warn("300: Subfield _c should not include an abbreviation -  please check.");
		}
	    }  
		
#check that digits and cm. are separated by a space
	        if (($newsubfields[$index] eq 'c') && ($newsubfields[$index+1] =~ /\d+[mci][mn]/)) {
	$self->warn ( "300: In subfield _c the number and the unit term should be separated by a space.");
    }


        }
    }



#space-plus sign

@sfcodes=("e");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ' [+]$', $field, \@newsubfields, $self, "be preceeded by a space-plus sign");
}

} # check_300


=head2 check_490

Checks 490 field for punctuation, correct indicators and field structure.

=cut


sub check_490 {

    my $self=shift;
    my $field=shift;
    
    my @sfcodes;
    my $sfcode;    

    # break subfields into code-data array (so the entire field is in one array)
    
    my @subfields = $field->subfields();
    my @newsubfields = ();
    
    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    }
    
    
#checks that  _a is present
    
    if ( not $field->subfield( "a" ) ) {
        $self->warn( "490: Must have a subfield _a." );
    }
    
    
#deal with _6 linking subfield at the start of the field
    if ($newsubfields[0] eq '6') {
	shift(@newsubfields);
	shift(@newsubfields);
    }
    
    if (($newsubfields[$#newsubfields] =~ /\.$/) && ($newsubfields[$#newsubfields] !~ /etc\.$/)){    
	$self->warn ( "490: Should not end with a full stop unless it comes at the end of an abbreviation - please check.");
    }
    
    
#subfield a should be first subfield
    if ($newsubfields[0] ne 'a') {
        $self->warn ( "490: First subfield must be _a, but it is _$newsubfields[0]");
    }


#full stops or equals sign

    @sfcodes=("a");
    foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, '[\.=]$', $field, \@newsubfields, $self, "be preceded by a full stop.");
}

#commas

@sfcodes=("x");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ',$', $field, \@newsubfields, $self, "be preceded by a comma");
}


#space-semicolons

@sfcodes=("v");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ' ;$', $field, \@newsubfields, $self, "be preceeded by a space-semicolon");
}


#round brackets

@sfcodes=("l");
foreach $sfcode(@sfcodes){
	nesubfcheckpost ($sfcode, '^\(.*\)', $field, \@newsubfields, $self, "contain material enclosed in round brackets");
}

} # check_490



=head2 check_600

Checks 600 field for punctuation, correct indicators and field structure.

=cut


sub check_600 {

    my $self = shift;
    my $field = shift;

    my @sfcodes;
    my $sfcode;

# break subfields into code-data array (so the entire field is in one array)

    my @subfields = $field->subfields();
    my @newsubfields = ();

    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    }
    

#checks that  _a is present

    if ( not $field->subfield( "a" ) ) {
        $self->warn( "600: Must have a subfield _a." );
    }

#deal with _6, _2, _3, _4 and _8 subfields, removing them and dealing with the rest of the record as normal
    if (($newsubfields[0] eq '6')||($newsubfields[0] eq '2')) {
	shift(@newsubfields);
	shift(@newsubfields);
    }
    if (($newsubfields[$#newsubfields-1] eq '2') || ($newsubfields[$#newsubfields-1] eq '3') || ($newsubfields[$#newsubfields-1] eq '4') || ($newsubfields[$#newsubfields-1] eq '8')) {
	pop (@newsubfields);
	pop (@newsubfields);
    }


#Checks that field ends with full stop, question mark, exclamation mark, hyphen or closing bracket

    if ($newsubfields[$#newsubfields]  !~ /[\.\)\-\]\?!]$/) {
        $self->warn ( "600: Should end with a full stop, exclamation mark, question mark, hyphen or closing bracket");
    }


#subfield a should be first subfield
    if ($newsubfields[0] ne 'a') {
        $self->warn ( "600: First subfield must be _a, but it is _$newsubfields[0]");
    }

    
#there should be no spaces between any initials in subfield _a

if ($field->subfield("a")){

    for (my $index = 0; $index <=$#newsubfields; $index+=2){
	if ($newsubfields[$index] eq 'a') {
	    if (($newsubfields[$index+1] =~ /\b\w\.\b\w\./) && ($newsubfields[$index+1] !~ /\[\bi\.e\. \b\w\..*\]/)){
		$self->warn ( "600: Any initials in the _a subfield should be separated by a space");
	    }
	}
    }
}
    

    
#subfield _b, if present, should not be preceded by a mark of punctuation unless it is preceded by an abbreviation

    if ($field->subfield("b")) {
    
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'b') {
		if ($newsubfields[$index-1] !~ /[\w\)]$/){
		    $self->warn ( "600: Subfield _b must not be preceded by a mark of punctuation unless the preceding word is an abbreviation - please check");
		}
	    }
	}
    }



#each subfield c, if present, should be preceded by a comma unless it contains material in round brackets.

    if ($field->subfield("c")) {

        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'c') && ($newsubfields[$index+1] =~ /^\(.*\)/)) {
		if ($newsubfields[$index-1] =~ /,$/){
		    $self->warn ( "600: If subfield _c contains material in round brackets it should not be preceded by a comma");
		}
	    }
	    if (($newsubfields[$index] eq 'c') && ($newsubfields[$index-1] !~ /,$/) && ($newsubfields[$index+1] !~ /^\(.*\)/)){
		$self->warn ( "600: Subfield _c must be preceded by a comma unless it contains material enclosed in round brackets");
	    }
	}
    }



#round brackets

@sfcodes=("q");
foreach $sfcode(@sfcodes){
	nesubfcheckpost ($sfcode, '^\(.*\)', $field, \@newsubfields, $self, "contain material enclosed in round brackets");
}


#fullstops, question marks or explanation marks

    @sfcodes=("g", "h", "k", "l", "s");
    foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, '[\.\?\!]$', $field, \@newsubfields, $self, "be preceded by a full stop, question mark or exclamation mark");
}



#commas

@sfcodes=("d", "e", "j", "m", "r");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ',$', $field, \@newsubfields, $self, "be preceded by a comma");
}

#semicolons

@sfcodes=("o");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ' ;$', $field, \@newsubfields, $self, "be preceeded by a semicolon");
}

#commas or full stops

@sfcodes=("n");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, '[\.,]$', $field, \@newsubfields, $self, "be preceded by a comma or a full stop");
}

#v, x, y, z checks

@sfcodes=("v", "x", "y", "z");
foreach $sfcode(@sfcodes){
    subfvxyzcheck ($sfcode, $field, \@newsubfields, $self);
}


#each subfield p, if present, must be preceded by a comma if it follows subfield n, or by a full stop  following other subfields
    if ($field->subfield("p")) {
	
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'p') {
                if (($newsubfields[$index-2] eq 'n') && ($newsubfields[$index-1] !~ /(\S,$)|(\-\- ,$)/)) {
                    $self->warn ("600: Subfield _p must be preceded by a comma when it follows subfield _n.");
                }
		elsif (($newsubfields[$index-2] ne 'n') && ($newsubfields[$index-1] !~ /(\S\.$)|(\-\- \.$)/)) {
		    $self->warn ( "600: Subfield _p must be preceded by full stop when it follows a subfield other than _n.");
		}
	    }
	}
    }
    

#subfield _t, if present, should be preceded by a question mark, full stop, hyphen or closing parenthesis

    if ($field->subfield("t")) {
    
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 't') { 
		if ($newsubfields[$index-1] !~ /[\?\.\-\)]$/){
		    $self->warn ("600: Subfield _t must be preceded by a question mark, full stop, hyphen or closing parenthesis.");
		}
	    }
            if ($newsubfields[$index] eq 't') { 
		if ($newsubfields[$index-1] =~ /-\.$/){
		    $self->warn ("600: If subfield _t is preceded by a hyphen, this should not be followed by a full stop.");
		}
	    }
	}
    }


    
} # check_600


=head2 check_610

Checks 610 field for punctuation, correct indicators and field structure.

=cut


sub check_610 {

    my $self = shift;
    my $field = shift;

    my @sfcodes;
    my $sfcode;

    # break subfields into code-data array (so the entire field is in one array)

    my @subfields = $field->subfields();
    my @newsubfields = ();

    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    }
    
#deal with _6, _2, _3, _4 and _8 subfields, removing them and dealing with the rest of the record as normal
    if (($newsubfields[0] eq '6') || ($newsubfields[0] eq '2')) {
	shift(@newsubfields);
	shift(@newsubfields);
    }
    if (($newsubfields[$#newsubfields-1] eq '2') || ($newsubfields[$#newsubfields-1] eq '3') || ($newsubfields[$#newsubfields-1] eq '4') || ($newsubfields[$#newsubfields-1] eq '8')) {
	pop (@newsubfields);
	pop (@newsubfields);
    }


#checks that _a is present

    if ( not $field->subfield( "a" ) ) {
        $self->warn( "610: Must have a subfield _a." );
    }

#Checks that field ends with full stop, question mark, exclamation mark, hyphen or closing bracket

    if ($newsubfields[$#newsubfields] !~ /[.)\]\-\?!]$/) {
        $self->warn ( "610: Should end with a full stop, question mark, exclamation mark, hyphen or closing bracket");
    }


#subfield a should be first subfield
    if ($newsubfields[0] ne 'a') {
        $self->warn ( "610: First subfield must be _a, but it is _$newsubfields[0]");
    }



#fullstops

@sfcodes=("b", "f", "h", "k", "l", "u");
foreach $sfcode(@sfcodes){
    nesubfcheckpre ($sfcode, '\.$', $field, \@newsubfields, $self, "be preceded by a full stop");
}


#commas

    @sfcodes=("e");
    foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ',$', $field, \@newsubfields, $self, "be preceded by a comma");
    }


#v, x, y, z checks

@sfcodes=("v", "x", "y", "z");
foreach $sfcode(@sfcodes){
    subfvxyzcheck ($sfcode, $field, \@newsubfields, $self);
}


#each subfield c, if present, should be preceded by space-colon if _d is present, and contain material enclosed in round brackets if not


if ($field->subfield("c")){
    if ($field->subfield("d")) {
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'c') && ($newsubfields[$index-1] !~ / :$/)) {
		$self->warn ("610: If subfield _c is preceded by _d it should be preceded by space-colon");
	    }
	}
    }
    else {   
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'c') && ($newsubfields[$index+1] !~ /^\(.*\)/)) {
		$self->warn ( "610: Unless preceded by _d, _c should contain material enclosed in round brackets");
	    }
	}
    }
}

#each subfield _d, if present, should be preceded by space-semicolon if _n is present, and start witn an opening round bracket if not

if ($field->subfield("d")){
    if ($field->subfield("n")) {
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'd') && ($newsubfields[$index-1] !~ / :$/)) {
		$self->warn ("610: If subfield _d is preceded by _n it should be preceded by space-colon");
	    }
	}
    }


    elsif (($field->subfield("t")) && ($field->subfield("t")=~ /Treaties, etc\./)){
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'd') && ($newsubfields[$index-1] !~ /,$/)) {
		$self->warn ("610: If subfield _d is a treaty date it should be preceded by a comma");
	    }
	}
    }
    
    else {   
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'd') && ($newsubfields[$index+1] !~ /^\(.*/)) {
		$self->warn ( "610: Unless preceded by _n, _d should start with an opening round bracket");
	    }
	}
    }
}



#rules for _t, _g, _n and _p if a _t is present

if ($field->subfield("t")){

#subfield _t should be preceded by a full stop

    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	if ($newsubfields[$index] eq 't') {
	    if ($newsubfields[$index-1] !~ /\.$/){
		$self->warn ("110: Subfield _t must be preceded by a full stop");
	    }
	}
    }
    
#subfield _g, if present, should be preceded by a full stop
    
    if ($field->subfield("g")) {
	my $gposition;
        my $tposition;
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'g') {
                $gposition=$index;}
	}
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 't') {
                $tposition=$index;}
	}
	
	if (($gposition) && ($tposition)){
	    if ($gposition > $tposition){
		if ($newsubfields[$gposition-1] !~ /\.$/){
		    $self->warn ("610: Following a _t, subfield _g must be preceded by a full stop");
		}
	    }
	}
    }


#subfield _n, if present, should be preceded by a full stop
    
    if ($field->subfield("n")) {
	
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'n') { 
		if ($newsubfields[$index-1] !~ /\.$/){
		    $self->warn ("610: Following a _t, subfield _n must be preceded by a full stop");
		}
	    }
	}
    }
    
    
#each subfield p, if present, must be preceded by a comma if it follows subfield n, or by a full stop  following other subfields
    
    if ($field->subfield("p")) {
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'p') {
                if (($newsubfields[$index-2] eq 'n') && ($newsubfields[$index-1] !~ /,$/)) {
                    $self->warn ("610: If there is a _t, subfield _p must be preceded by a comma when it follows subfield _n.");
                }
		elsif (($newsubfields[$index-2] ne 'n') && ($newsubfields[$index-1] !~ /\.$/)) {
		    $self->warn ( "610: If there is _t, subfield _p must be preceded by full stop when it follows a subfield other than _n.");
		}
	    }
	}
    }
}

else {

#rules for _n and _p if there is no _t subfield


#unless it follows a form heading of manuscript, _n starts with an opening round bracket

    if ($field->subfield("n")){
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	
		if (($newsubfields[$index] eq 'n') && ($newsubfields[$index+1] !~ /^\(.*/) && ($newsubfields[$index-1] !~ /Manuscript/)) {
		    $self->warn ("610: Unless it follows a _t or a form heading of Manuscript, _n should start with an opening round bracket");
		    
		}
	    }
    }


    if ($field->subfield("p")){
	for (my $index = 2; $index <=$#newsubfields; $index+=2){
	    if ($newsubfields[$index] eq 'p') { 
		if (($newsubfields[$index-1] =~ /^Manuscript/) && ($newsubfields[$index-1] !~ /\w$/)){
		    $self->warn ("610: Following a _k containing Manuscript, _p should not be preceded by a mark of punctuation");
		}
	    }
	}
    }
}



} # check_610



=head2 check_611

Checks 611 field for punctuation, correct indicators and field structure.

=cut


sub check_611 {

    my $self = shift;
    my $field = shift;

    my @sfcodes;
    my $sfcode;

    # break subfields into code-data array (so the entire field is in one array)

    my @subfields = $field->subfields();
    my @newsubfields = ();

    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    }
    


#deal with _6, _2, _3, _4 and _8 subfields, removing them and dealing with the rest of the record as normal
    if (($newsubfields[0] eq '6') || ($newsubfields[0] eq '2')) {
	shift(@newsubfields);
	shift(@newsubfields);
    }
    if (($newsubfields[$#newsubfields-1] eq '2') || ($newsubfields[$#newsubfields-1] eq '3') || ($newsubfields[$#newsubfields-1] eq '4') || ($newsubfields[$#newsubfields-1] eq '8')) {
	pop (@newsubfields);
	pop (@newsubfields);
    }

    
    
#checks that _a is present
    
    if ( not $field->subfield( "a" ) ) {
        $self->warn( "611: Must have a subfield _a." );
    }
    
#Checks that field ends with full stop, question mark, exclamation mark, hyphen or closing bracket
    
    if ($newsubfields[$#newsubfields] !~ /[.\)\]\-\?!]$/) {
	$self->warn ( "611: Should end with a full stop, question mark, exclamation mark, hyphen or closing bracket");
    }
    
    
    
#subfield a should be first subfield
    if ($newsubfields[0] ne 'a') {
        $self->warn ( "611: First subfield must be _a, but it is _$newsubfields[0]");
    }
    
    
#fullstops
    
    @sfcodes=("e", "f", "k", "l", "u");
    foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, '\.$', $field, \@newsubfields, $self, "be preceded by a full stop");
    }
    
#commas

    @sfcodes=("g");
    foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ',$', $field, \@newsubfields, $self, "be preceded by a comma");
    }
    
    
#v, x, y, z checks
    
    @sfcodes=("v", "x", "y", "z");
    foreach $sfcode(@sfcodes){
	subfvxyzcheck ($sfcode, $field, \@newsubfields, $self);
    }
    
    
    
#each subfield c, if present, should be preceded by space-colon if _d is present, and contain material enclosed in round brackets if not
    
    
    if ($field->subfield("c")){
	if (($field->subfield("d"))||($field->subfield("n"))) {
	    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
		if (($newsubfields[$index] eq 'c') && ($newsubfields[$index-1] !~ / :$/)) {
		    $self->warn ("611: If subfield _c is preceded by _d or _n it should be preceded by space-colon");
		}
	    }
	}
	else {   
	    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
		if (($newsubfields[$index] eq 'c') && ($newsubfields[$index+1] !~ /^\(.*\)/)) {
		    $self->warn ( "611: Unless preceded by _d or _n, _c should contain material enclosed in round brackets");
		}
	    }
	}
    }
    
#each subfield _d, if present, should be preceded by space-semicolon if _n is present, and start witn an opening round bracket if not
    
    if ($field->subfield("d")){
	if ($field->subfield("n")) {
	    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
		if (($newsubfields[$index] eq 'd') && ($newsubfields[$index-2] eq 'n') && ($newsubfields[$index-1] !~ / :$/)) {
		    $self->warn ("611: If subfield _d is preceded by _n it should be preceded by space-colon");
		}
	    }
	}

	if ($field->subfield("q")) {
	    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
		if (($newsubfields[$index] eq 'd') && ($newsubfields[$index-2] eq 'q') && ($newsubfields[$index-1] !~ /,$/)) {
		    $self->warn ("611: If subfield _d is preceded by _q it should be preceded by a comma");
		}
	    }
	}
	
	if ((not $field->subfield("n")) && (not $field->subfield("q"))) {   
	    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
		if (($newsubfields[$index] eq 'd') && ($newsubfields[$index+1] !~ /^\(.*/)) {
		    $self->warn ( "611: Unless preceded by _n or _q, _d should start with an opening round bracket");
		}
	    }
	}
    }

#rules for _t, _g, _n and _p if a _t is present
    
    if ($field->subfield("t")){
	
#subfield _t should be preceded by a full stop
	
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if ($newsubfields[$index] eq 't') { 
		if ($newsubfields[$index-1] !~ /\.$/){
		    $self->warn ("611: Subfield _t must be preceded by a full stop");
		}
	    }
	}
	
	
#subfield _n, if present, should be preceded by a full stop
	
	if ($field->subfield("n")) {
	    
	    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
		if ($newsubfields[$index] eq 'n') { 
		    if ($newsubfields[$index-1] !~ /\.$/){
			$self->warn ("611: Following a _t, subfield _n must be preceded by a full stop");
		    }
		}
	    }
	}
	
	
#subfield _p, if present, should be preceded by a full stop
	
	if ($field->subfield("p")) {
	    
	    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
		if ($newsubfields[$index] eq 'p') { 
		    if ($newsubfields[$index-1] !~ /\.$/){
			$self->warn ("611: Following a _t, subfield _p must be preceded by a full stop");
		    }
		}
	    }
	}
	
    }
    
    else {
	
#rules for _n if there is no _t subfield
	
	
#_n starts with an opening round bracket
	
	if ($field->subfield("n")){
	    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
		
		if (($newsubfields[$index] eq 'n') && ($newsubfields[$index+1] !~ /^\(.*/)) {
		    $self->warn ("611: Unless it follows a _t, _n should start with an opening round bracket");
		    
		}
	    }
	}
	
    }
    
} # check_611



=head2 check_630

Checks 630 field for punctuation, correct indicators and field structure.

=cut


sub check_630 {

    my $self = shift;
    my $field = shift;

    my @sfcodes;
    my $sfcode;    
    
    # break subfields into code-data array (so the entire field is in one array)
    
    my @subfields = $field->subfields();
    my @newsubfields = ();
    
    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    }
    
    
#checks that  _a is present
    
    if ( not $field->subfield( "a" ) ) {
        $self->warn( "630: Must have a subfield _a." );
    }
    

#deal with _6, _2 and _3 subfields, removing them and dealing with the rest of the record as normal
    if (($newsubfields[0] eq '6') || ($newsubfields[0] eq '2')) {
	shift(@newsubfields);
	shift(@newsubfields);
    }

    if (($newsubfields[$#newsubfields-1] eq '2') || ($newsubfields[$#newsubfields-1] eq '3')) {
	pop (@newsubfields);
	pop (@newsubfields);
    }


    if ($newsubfields[$#newsubfields] !~ /[.)\]\?!\-]$/) {
    $self->warn ( "630: Should end with a full stop, question mark, exclamation mark, hyphen or closing bracket.");
}


#subfield a should be first subfield
    if ($newsubfields[0] ne 'a') {
        $self->warn ( "630: First subfield must be _a, but it is _$newsubfields[0]");
    }



#round brackets

@sfcodes=("d");
foreach $sfcode(@sfcodes){
	nesubfcheckpost ($sfcode, '^\(.*\)', $field, \@newsubfields, $self, "treaty date in subfield d must be enclosed in round brackets, unless the date of a protocol to a treaty - please check");
}




#fullstops, question marks or explanation marks

    @sfcodes=("f", "g", "h", "k", "l", "s");
    foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, '[\.\?\!]$', $field, \@newsubfields, $self, "be preceded by a full stop, question mark or exclamation mark");
}



#commas

@sfcodes=("m", "r");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ',$', $field, \@newsubfields, $self, "be preceded by a comma");
}


#semicolons

@sfcodes=("o");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ';$', $field, \@newsubfields, $self, "be preceeded by a semicolon");
}

#commas or full stops


@sfcodes=("n", "p");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, '[\.,]$', $field, \@newsubfields, $self, "be preceeded by a comma or a full stop");
}


#v, x, y, z checks

@sfcodes=("v", "x", "y", "z");
foreach $sfcode(@sfcodes){
    subfvxyzcheck ($sfcode, $field, \@newsubfields, $self);
}

} # check_630


=head2 check_650

Checks 650 field for punctuation, correct indicators and field structure.

=cut


sub check_650 {

    my $self = shift;
    my $field = shift;

    my @sfcodes;
    my $sfcode;
    
    
    # break subfields into code-data array (so the entire field is in one array)
    
    my @subfields = $field->subfields();
    my @newsubfields = ();
    
    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    }
    
    
#checks that  _a is present
    
    if ( not $field->subfield( "a" ) ) {
        $self->warn( "650: Must have a subfield _a." );
    }
    

#deal with _6, _2, _3 and _8 subfields, removing them and dealing with the rest of the record as normal
    if (($newsubfields[0] eq '6') || ($newsubfields[0] eq '2')) {
	shift(@newsubfields);
	shift(@newsubfields);
    }

    if (($newsubfields[$#newsubfields-1] eq '2') || ($newsubfields[$#newsubfields-1] eq '3') || ($newsubfields[$#newsubfields-1] eq '8')) {
	pop (@newsubfields);
	pop (@newsubfields);
    }


    if ($newsubfields[$#newsubfields] !~ /[.)\]\-]$/) {
    $self->warn ( "650: Should end with a full stop, hyphen or closing bracket.");
}


#subfield a should be first subfield
    if ($newsubfields[0] ne 'a') {
        $self->warn ( "650: First subfield must be _a, but it is _$newsubfields[0]");
    }


#fullstops

@sfcodes=("b");
foreach $sfcode(@sfcodes){
    nesubfcheckpre ($sfcode, '\.$', $field, \@newsubfields, $self, "be preceded by a full stop");
}

#v, x, y, z checks

@sfcodes=("v", "x", "y", "z");
foreach $sfcode(@sfcodes){
    subfvxyzcheck ($sfcode, $field, \@newsubfields, $self);
}


} # check_650


=head2 check_651

Checks 651 field for punctuation, correct indicators and field structure.

=cut


sub check_651 {

    my $self = shift;
    my $field = shift;

    my @sfcodes;
    my $sfcode;    
    
    # break subfields into code-data array (so the entire field is in one array)
    
    my @subfields = $field->subfields();
    my @newsubfields = ();
    
    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    }
    
    
#checks that  _a is present
    
    if ( not $field->subfield( "a" ) ) {
        $self->warn( "651: Must have a subfield _a." );
    }
    

#deal with _6, _2, _3 and _8 subfields, removing them and dealing with the rest of the record as normal
    if (($newsubfields[0] eq '6') || ($newsubfields[0] eq '2')) {
	shift(@newsubfields);
	shift(@newsubfields);
    }

    if (($newsubfields[$#newsubfields-1] eq '2') || ($newsubfields[$#newsubfields-1] eq '3') || ($newsubfields[$#newsubfields-1] eq '8')) {
	pop (@newsubfields);
	pop (@newsubfields);
    }


    if ($newsubfields[$#newsubfields] !~ /[.)\]\-]$/) {
    $self->warn ( "651: Should end with a full stop, hyphen or closing bracket.");
}


#subfield a should be first subfield
    if ($newsubfields[0] ne 'a') {
        $self->warn ( "651: First subfield must be _a, but it is _$newsubfields[0].");
    }

#v, x, y, z checks

@sfcodes=("v", "x", "y", "z");
foreach $sfcode(@sfcodes){
    subfvxyzcheck ($sfcode, $field, \@newsubfields, $self);
}


} # check_651



=head2 check_700

Checks 700 field for punctuation, correct indicators and field structure.

=cut


sub check_700 {

    my $self = shift;
    my $field = shift;
    my $rda = shift;
    
    my @sfcodes;
    my $sfcode;

    # break subfields into code-data array (so the entire field is in one array)

    my @subfields = $field->subfields();
    my @newsubfields = ();

    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    }
    

#checks that  _a is present

    if ( not $field->subfield( "a" ) ) {
        $self->warn( "700: Must have a subfield _a." );
    }

#deal with _6 linking subfield at the start of the field and _3, _4, _5 and _8 subfields at end of field, removing them and dealing with the rest of the record as normal
    if ($newsubfields[0] eq '6') {
	shift(@newsubfields);
	shift(@newsubfields);
    }
    if (($newsubfields[$#newsubfields-1] eq '5') || ($newsubfields[$#newsubfields-1] eq '3') || ($newsubfields[$#newsubfields-1] eq '4') || ($newsubfields[$#newsubfields-1] eq '8')) {
	pop (@newsubfields);
	pop (@newsubfields);
    }


#Checks that field ends with full stop, question mark, exclamation mark, hyphen or closing bracket

    if ($newsubfields[$#newsubfields] !~ /[\.\)\-\]\?!]$/) {
        $self->warn ( "700: Should end with a full stop, question mark, exclamation mark, hyphen or closing bracket.");
    }


#subfield a should be first subfield
    if ($newsubfields[0] ne 'a') {
        $self->warn ( "700: First subfield must be _a, but it is _$newsubfields[0].");
    }

    
#there should be no spaces between any initials in subfield _a

if ($field->subfield("a")){

    for (my $index = 0; $index <=$#newsubfields; $index+=2){
	if ($newsubfields[$index] eq 'a') {
	    if (($newsubfields[$index+1] =~ /\b\w\.\b\w\./) && ($newsubfields[$index+1] !~ /\[\bi\.e\. \b\w\..*\]/)){
		$self->warn ( "700: Any initials in the _a subfield should be separated by a space.");
	    }
	}
    }
}
    

    
#subfield _b, if present, should not be preceded by a mark of punctuation unless it is preceded by an abbreviation

    if ($field->subfield("b")) {
    
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'b') {
		if ($newsubfields[$index-1] !~ /[\w\)]$/){
		    $self->warn ( "700: Subfield _b must not be preceded by a mark of punctuation unless the preceding word is an abbreviation - please check.");
		}
	    }
	}
    }



#each subfield c, if present, should be preceded by a comma unless it contains material in round brackets.

    if ($field->subfield("c")) {

        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'c') && ($newsubfields[$index+1] =~ /^\(.*\)/)) {
		if ($newsubfields[$index-1] =~ /,$/){
		    $self->warn ( "700: If subfield _c contains material in round brackets it should not be preceded by a comma.");
		}
	    }
	    if (($newsubfields[$index] eq 'c') && ($newsubfields[$index-1] !~ /,$/) && ($newsubfields[$index+1] !~ /^\(.*\)/)){
		$self->warn ( "700: Subfield _c must be preceded by a comma unless it contains material enclosed in round brackets.");
	    }
	}
    }



#round brackets

@sfcodes=("q");
foreach $sfcode(@sfcodes){
	nesubfcheckpost ($sfcode, '^\(.*\)', $field, \@newsubfields, $self, "contain material enclosed in round brackets.");
}


#fullstops, question marks or explanation marks

    @sfcodes=("g", "h", "k", "l", "s");
    foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, '[\.\?\!]$', $field, \@newsubfields, $self, "be preceded by a full stop, question mark or exclamation mark.");
}


#commas

@sfcodes=("d", "j", "m", "r", "x");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ',$', $field, \@newsubfields, $self, "be preceded by a comma.");
}


#commas or dashes

@sfcodes=("e");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, '[\-,]$', $field, \@newsubfields, $self, "be preceded by a comma or a dash.");
}

#semicolons

@sfcodes=("o");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ';$', $field, \@newsubfields, $self, "be preceeded by a semicolon.");
}

#commas or full stops

@sfcodes=("n");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, '[\.,]$', $field, \@newsubfields, $self, "be preceeded by a comma or a full stop.");
}



#each subfield p, if present, must be preceded by a comma if it follows subfield n, or by a full stop  following other subfields
    if ($field->subfield("p")) {
	
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'p') {
                if (($newsubfields[$index-2] eq 'n') && ($newsubfields[$index-1] !~ /(\S,$)|(\-\- ,$)/)) {
                    $self->warn ("700: Subfield _p must be preceded by a comma when it follows subfield _n.");
                }
		elsif (($newsubfields[$index-2] ne 'n') && ($newsubfields[$index-1] !~ /(\S\.$)|(\-\- \.$)/)) {
		    $self->warn ( "700: Subfield _p must be preceded by full stop when it follows a subfield other than _n.");
		}
	    }
	}
    }
    


#subfield _t, if present, should be preceded by a full stop, hyphen or closing parenthesis

    if ($field->subfield("t")) {
    
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 't') {
		if ($newsubfields[$index-1] !~ /[\?\.\-\)]$/){
		    $self->warn ("700: Subfield _t must be preceded by a question mark, full stop, hyphen or closing parenthesis.");
		}
	    }
            if ($newsubfields[$index] eq 't') { 
		if ($newsubfields[$index-1] =~ /-\.$/){
		    $self->warn ("700: If subfield _t is preceded by a hyphen, this should not be followed by a full stop.");
		}
	    }
	}
    }
    
   #check RDA records have subfield e and if so content matches controlled vocabulary
    if (($rda == 1) && ($field->indicator(2) ne "2")){
	   if (my @sub=$field->subfield( "e" )){
        #check RDA records use standard relationship designators
		foreach my$sub_e (@sub){
		$sub_e =~ s/[\.\,]//;
		unless (grep (/$sub_e/, @rel_des)){
			$self->warn ("700: The relationship designator in _e \"$sub_e\" is not a standard term please check");
		} }
	}
	
     #warn if RDA records don't have subfield e
     else{
	$self->warn ("700: RDA records must have and a relationship designator in subfield _e.");
	} 
    }

} # check_700



=head2 check_710

Checks 710 field for punctuation, correct indicators and field structure.

=cut


sub check_710 {

    my $self = shift;
    my $field = shift;
    my $rda = shift;
    
    my @sfcodes;
    my $sfcode;

    # break subfields into code-data array (so the entire field is in one array)

    my @subfields = $field->subfields();
    my @newsubfields = ();

    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    }
    

#deal with _6 linking subfield at the start of the field and _3, _4, _5 and _8 subfields at end of field, removing them and dealing with the rest of the record as normal
    if ($newsubfields[0] eq '6') {
	shift(@newsubfields);
	shift(@newsubfields);
    }
    if (($newsubfields[$#newsubfields-1] eq '3') || ($newsubfields[$#newsubfields-1] eq '4') || ($newsubfields[$#newsubfields-1] eq '5') || ($newsubfields[$#newsubfields-1] eq '8')) {
	pop (@newsubfields);
	pop (@newsubfields);
    }

#checks that _a is present

    if ( not $field->subfield( "a" ) ) {
        $self->warn( "710: Must have a subfield _a." );
    }

#Checks that field ends with full stop, hyphen, question mark, exclamation mark or closing bracket

    if ($newsubfields[$#newsubfields] !~ /[.)\]\-\?!]$/) {
        $self->warn ( "710: Should end with a full stop, question mark, exclamation mark, hyphen or closing bracket");
    }

#subfield a should be first subfield
    if ($newsubfields[0] ne 'a') {
        $self->warn ( "710: First subfield must be _a, but it is _$newsubfields[0]");
    }


#fullstops

@sfcodes=("b", "f", "h", "k", "l", "u");
foreach $sfcode(@sfcodes){
    nesubfcheckpre ($sfcode, '\.$', $field, \@newsubfields, $self, "be preceded by a full stop");
}

#commas or hyphen

    @sfcodes=("e");
    foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, '[,\-]$', $field, \@newsubfields, $self, "be preceded by a comma or hyphen");
    }




#each subfield c, if present, should be preceded by space-colon if _d is present, and contain material enclosed in round brackets if not


if ($field->subfield("c")){
    if ($field->subfield("d")) {
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'c') && ($newsubfields[$index-1] !~ / :$/)) {
		$self->warn ("710: If subfield _c is preceded by _d it should be preceded by space-colon");
	    }
	}
    }
    else {   
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'c') && ($newsubfields[$index+1] !~ /^\(.*\)/)) {
		$self->warn ( "710: Unless preceded by _d, _c should contain material enclosed in round brackets");
	    }
	}
    }
}

#each subfield _d, if present, should be preceded by space-semicolon if _n is present, and start witn an opening round bracket if not

if ($field->subfield("d")){
    if ($field->subfield("n")) {
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'd') && ($newsubfields[$index-1] !~ / :$/)) {
		$self->warn ("710: If subfield _d is preceded by _n it should be preceded by space-colon");
	    }
	}
    }


    elsif (($field->subfield("t")) && ($field->subfield("t")=~ /Treaties, etc\./)){
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'd') && ($newsubfields[$index-1] !~ /,$/)) {
		$self->warn ("710: If subfield _d is a treaty date it should be preceded by a comma");
	    }
	}
    }
    else {   
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'd') && ($newsubfields[$index+1] !~ /^\(.*/)) {
		$self->warn ( "710: Unless preceded by _n, _d should start with an opening round bracket");
	    }
	}
    }
}


#rules for _t, _g, _n and _p if a _t is present

if ($field->subfield("t")){

#subfield _t should be preceded by a full stop

    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	if ($newsubfields[$index] eq 't') {
	    if ($newsubfields[$index-1] !~ /\.$/){
		$self->warn ("710: Subfield _t must be preceded by a full stop");
	    }
	}
    }
    
#subfield _g, if present, should be preceded by a full stop
    
    if ($field->subfield("g")) {
	my $gposition;
        my $tposition;
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'g') {
                $gposition=$index;}
	}
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 't') {
                $tposition=$index;}
	}
	
	if (($gposition) && ($tposition)){
	    if ($gposition > $tposition){
		if ($newsubfields[$gposition-1] !~ /\.$/){
		    $self->warn ("710: Following a _t, subfield _g must be preceded by a full stop");
		}
	    }
	}
    }
    

#subfield _n, if present, should be preceded by a full stop
    
    if ($field->subfield("n")) {
	
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'n') { 
		if ($newsubfields[$index-1] !~ /\.$/){
		    $self->warn ("710: Following a _t, subfield _n must be preceded by a full stop");
		}
	    }
	}
    }
    
    
#each subfield p, if present, must be preceded by a comma if it follows subfield n, or by a full stop  following other subfields
    
    if ($field->subfield("p")) {
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'p') {
                if (($newsubfields[$index-2] eq 'n') && ($newsubfields[$index-1] !~ /,$/)) {
                    $self->warn ("710: If there is a _t, subfield _p must be preceded by a comma when it follows subfield _n.");
                }
		elsif (($newsubfields[$index-2] ne 'n') && ($newsubfields[$index-1] !~ /\.$/)) {
		    $self->warn ( "710: If there is _t, subfield _p must be preceded by full stop when it follows a subfield other than _n.");
		}
	    }
	}
    }
}

else {

#rules for _n and _p if there is no _t subfield

#unless it follows a form heading of Manuscript, _n must start with an opening round bracket

    if ($field->subfield("n")){
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	
		if (($newsubfields[$index] eq 'n') && ($newsubfields[$index+1] !~ /^\(.*/) && ($newsubfields[$index-1] !~ /Manuscript/)) {
		    $self->warn ("710: Unless it follows a _t or a form heading of Manuscript, _n should start with an opening round bracket");
		    
		}
	    }
    }

    if ($field->subfield("p")){
	for (my $index = 2; $index <=$#newsubfields; $index+=2){
	    if ($newsubfields[$index] eq 'p') { 
		if (($newsubfields[$index-1] =~ /^Manuscript/) && ($newsubfields[$index-1] !~ /\w$/)){
		    $self->warn ("710: Following a _k containing Manuscript, _p should not be preceded by a mark of punctuation");
		}
	    }
	}
    }
}
    
#check RDA records have subfield e and if so content matches controlled vocabulary
    if (($rda == 1) && ($field->indicator(2) ne "2")){
	   if (my @sub=$field->subfield( "e" )){
        #check RDA records use standard relationship designators
        foreach my$sub_e (@sub){
		$sub_e =~ s/[\.\,]//;
		unless (grep (/$sub_e/, @rel_des)){
			$self->warn ("710: The relationship designator in _e \"$sub_e\" is not a standard term please check");
		} }
	}
	
     #warn if RDA records don't have subfield e
     else{
	$self->warn ("710: RDA records must have and a relationship designator in subfield _e.");
	} 
    }
} # check_710



=head2 check_711

Checks 711 field for punctuation, correct indicators and field structure.

=cut


sub check_711 {

    my $self = shift;
    my $field = shift;
    my @sfcodes;
    my $sfcode;

    # break subfields into code-data array (so the entire field is in one array)

    my @subfields = $field->subfields();
    my @newsubfields = ();

    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    }
    


#deal with _6 linking subfield at the start of the field and _3, _4, _5 and _8 subfields at end of field, removing them and dealing with the rest of the record as normal
    if ($newsubfields[0] eq '6') {
	shift(@newsubfields);
	shift(@newsubfields);
    }
    if (($newsubfields[$#newsubfields-1] eq '3') || ($newsubfields[$#newsubfields-1] eq '4') || ($newsubfields[$#newsubfields-1] eq '5') || ($newsubfields[$#newsubfields-1] eq '8')) {
	pop (@newsubfields);
	pop (@newsubfields);
    }



#checks that _a is present

    if ( not $field->subfield( "a" ) ) {
        $self->warn( "711: Must have a subfield _a." );
    }

#Checks that field ends with full stop, question mark, exclamation mark, hyphen or closing bracket

    if ($newsubfields[$#newsubfields] !~ /[.\)\]\-\?!]$/) {
        $self->warn ( "711: Should end with a full stop, question mark, exclamation mark, hyphen or closing bracket");
    }
    
#subfield a should be first subfield
    if ($newsubfields[0] ne 'a') {
        $self->warn ( "711: First subfield must be _a, but it is _$newsubfields[0]");
    }
    
    
#fullstops
    
    @sfcodes=("e", "f", "k", "l", "u");
    foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, '\.$', $field, \@newsubfields, $self, "be preceded by a full stop");
    }
    
#commas
    
    @sfcodes=("g");
    foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ',$', $field, \@newsubfields, $self, "be preceded by a comma");
    }
    

    
#each subfield c, if present, should be preceded by space-colon if _d is present, and contain material enclosed in round brackets if not
    
    
    if (($field->subfield("c"))||($field->subfield("n"))){
	if ($field->subfield("d")) {
	    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
		if (($newsubfields[$index] eq 'c') && ($newsubfields[$index-1] !~ / :$/)) {
		    $self->warn ("711: If subfield _c is preceded by _d or _n it should be preceded by space-colon");
		}
	    }
	}
	else {   
	    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
		if (($newsubfields[$index] eq 'c') && ($newsubfields[$index+1] !~ /^\(.*\)/)) {
		    $self->warn ( "711: Unless preceded by _d or _n, _c should contain material enclosed in round brackets");
		}
	    }
	}
    }

#each subfield _d, if present, should be preceded by space-semicolon if _n is present, and start witn an opening round bracket if not

    if ($field->subfield("d")){
	if ($field->subfield("n")) {
	    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
		if (($newsubfields[$index] eq 'd') && ($newsubfields[$index-2] eq 'n') && ($newsubfields[$index-1] !~ / :$/)) {
		    $self->warn ("711: If subfield _d is preceded by _n it should be preceded by space-colon");
		}
	    }
	}
	
	if ($field->subfield("q")) {
	    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
		if (($newsubfields[$index] eq 'd') && ($newsubfields[$index-2] eq 'q') && ($newsubfields[$index-1] !~ /,$/)) {
		    $self->warn ("711: If subfield _d is preceded by _q it should be preceded by a comma");
		}
	    }
	}
	
	if ((not $field->subfield("n")) && (not $field->subfield("q"))) {   
	    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
		if (($newsubfields[$index] eq 'd') && ($newsubfields[$index+1] !~ /^\(.*/)) {
		    $self->warn ( "711: Unless preceded by _n or _q, _d should start with an opening round bracket");
		}
	    }
	}
    }
    
    
#fullstops

    @sfcodes=("e", "f", "k", "l", "u");
    foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, '\.$', $field, \@newsubfields, $self, "be preceded by a full stop");
    }
    
#commas
    
    @sfcodes=("g");
    foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ',$', $field, \@newsubfields, $self, "be preceded by a comma");
    }
    
    
#rules for _t, _g, _n and _p if a _t is present
    
    if ($field->subfield("t")){
	
#subfield _t should be preceded by a full stop

	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if ($newsubfields[$index] eq 't') { 
		if ($newsubfields[$index-1] !~ /\.$/){
		    $self->warn ("711: Subfield _t must be preceded by a full stop");
		}
	    }
	}
	
	
#subfield _n, if present, should be preceded by a full stop
	
	if ($field->subfield("n")) {
	    
	    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
		if ($newsubfields[$index] eq 'n') { 
		    if ($newsubfields[$index-1] !~ /\.$/){
			$self->warn ("711: Following a _t, subfield _n must be preceded by a full stop");
		    }
		}
	    }
	}
	
    
#subfield _p, if present, should be preceded by a full stop
	
	if ($field->subfield("p")) {
	    
	    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
		if ($newsubfields[$index] eq 'p') { 
		    if ($newsubfields[$index-1] !~ /\.$/){
			$self->warn ("711: Following a _t, subfield _p must be preceded by a full stop");
		    }
		}
	    }
	}
	
	
    }

    else {
	
#rules for _n if there is no _t subfield
	
	
#_n starts with an opening round bracket
	
	if ($field->subfield("n")){
	    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
		
		if (($newsubfields[$index] eq 'n') && ($newsubfields[$index+1] !~ /^\(.*/)) {
		    $self->warn ("711: Unless it follows a _t, _n should start with an opening round bracket");
		    
		}
	    }
	}
	
    }

} # check_711


=head2 check_730

Checks 730 field for punctuation, correct indicators and field structure.

=cut


sub check_730 {

    my $self = shift;
    my $field = shift;
    my @sfcodes;
    my $sfcode;
    
    # break subfields into code-data array (so the entire field is in one array)
    
    my @subfields = $field->subfields();
    my @newsubfields = ();
    
    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    }
    
    
#checks that  _a is present
    
    if ( not $field->subfield( "a" ) ) {
        $self->warn( "730: Must have a subfield _a." );
    }
    

#deal with _6 linking subfield at the start of the field and _5 and _3 subfields at end of the field, removing them and dealing with the rest of the record as normal
if ($newsubfields[0] eq '6') {
    shift(@newsubfields);
    shift(@newsubfields);
}
if (($newsubfields[$#newsubfields-1] eq '3') || ($newsubfields[$#newsubfields-1] eq '5')) {
    pop (@newsubfields);
    pop (@newsubfields);
}

#Checks that field ends with a full stop, question mark, exclamation mark, hyphen or closing bracket
    
    if ($newsubfields[$#newsubfields] !~ /[.)\]\?!]$/) {
    $self->warn ( "730: Should end with a full stop, exclamation mark, question mark or closing bracket.");
}




#subfield a should be first subfield
    if ($newsubfields[0] ne 'a') {
        $self->warn ( "730: First subfield must be _a, but it is _$newsubfields[0]");
    }



#round brackets

@sfcodes=("d");
foreach $sfcode(@sfcodes){
	nesubfcheckpost ($sfcode, '^\(.*\)', $field, \@newsubfields, $self, "contain material enclosed in round brackets");
}


#fullstops, question marks or explanation marks

    @sfcodes=("f", "g", "h", "k", "l", "s");
    foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, '[\.\?\!]$', $field, \@newsubfields, $self, "be preceded by a full stop, question mark or exclamation mark");
}

#commas

@sfcodes=("m", "r");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ',$', $field, \@newsubfields, $self, "be preceded by a comma");
}


#semicolons

@sfcodes=("o");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ';$', $field, \@newsubfields, $self, "be preceeded by a semicolon");
}

#commas or full stops


@sfcodes=("n");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, '[\.,]$', $field, \@newsubfields, $self, "be preceded by a comma or a full stop");
}


#each subfield p, if present, must be preceded by a comma if it follows subfield n, or by a full stop  following other subfields
    if ($field->subfield("p")) {
	
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'p') {
                if (($newsubfields[$index-2] eq 'n') && ($newsubfields[$index-1] !~ /(\S,$)|(\-\- ,$)/)) {
                    $self->warn ("730: Subfield _p must be preceded by a comma when it follows subfield _n.");
                }
		elsif (($newsubfields[$index-2] ne 'n') && ($newsubfields[$index-1] !~ /(\S\.$)|(\-\- \.$)/)) {
		    $self->warn ( "730: Subfield _p must be preceded by full stop when it follows a subfield other than _n.");
		}
	    }
	}
    }

    
} # check_730


=head2 check_740

Checks 740 field for punctuation, correct indicators and field structure.

=cut


sub check_740 {

    my $self = shift;
    my $field = shift;
    my $rda = shift;
    
    my @sfcodes;
    my $sfcode;    
    
    # break subfields into code-data array (so the entire field is in one array)
    
    my @subfields = $field->subfields();
    my @newsubfields = ();
    
    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    }
    
    
#checks that  _a is present
    
    if ( not $field->subfield( "a" ) ) {
        $self->warn( "740: Must have a subfield _a." );
    }
    

#deal with _6 linking subfield at the start of the field and _5 subfields at end of the field, removing them and dealing with the rest of the record as normal
if ($newsubfields[0] eq '6') {
    shift(@newsubfields);
    shift(@newsubfields);
}
if ($newsubfields[$#newsubfields-1] eq '5') {
    pop (@newsubfields);
    pop (@newsubfields);
}


    
    if ($newsubfields[$#newsubfields] !~ /[.)\]\?!]$/) {
    $self->warn ( "740: Should end with a full stop, question mark, exclamation mark or closing bracket.");
}




#subfield a should be first subfield
    if ($newsubfields[0] ne 'a') {
        $self->warn ( "740: First subfield must be _a, but it is _$newsubfields[0]");
    }


#fullstops

    @sfcodes=("n");
    foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, '\.$', $field, \@newsubfields, $self, "be preceded by a full stop");
}



#each subfield p, if present, must be preceded by a comma if it follows subfield n, or by a full stop  following other subfields
    if ($field->subfield("p")) {
	
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'p') {
                if (($newsubfields[$index-2] eq 'n') && ($newsubfields[$index-1] !~ /(\S,$)|(\-\- ,$)/)) {
                    $self->warn ("740: Subfield _p must be preceded by a comma when it follows subfield _n.");
                }
		elsif (($newsubfields[$index-2] ne 'n') && ($newsubfields[$index-1] !~ /(\S[\.\?!]$)|(\-\- \.$)/)) {
		    $self->warn ( "740: Subfield _p must be preceded by full stop, question mark or exclamation mark when it follows a subfield other than _n.");
		}
	    }
	}
    }
    

#each subfield h, if present, should be preceded by non-space, and should contain either one or two words enclosed in square brackets.
    if ($field->subfield("h")) {
	    
	if ($rda == 1){ #check RDA records for subfield h
		$self->warn("740: RDA records should not include subfield _h."); 
	}
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'h') && ($newsubfields[$index-1] !~ /(\S$)|(\-\- $)/)) {
                $self->warn ( "740: Subfield _h should not be preceded by space.");
            }

            if (($newsubfields[$index] eq 'h') && ($newsubfields[$index+1] !~ /^\[\w*\s*\w*\]/)) {
                $self->warn ( "740: Subfield _h may have invalid material designator, or lack square brackets, $newsubfields[$index].");
            }
        }
    }


$self->_check_article($field);
    

} # check_740


=head2 check_800

Checks 800 field for punctuation, correct indicators and field structure.

=cut


sub check_800 {

    my $self = shift;
    my $field = shift;

    my @sfcodes;
    my $sfcode;

    # break subfields into code-data array (so the entire field is in one array)

    my @subfields = $field->subfields();
    my @newsubfields = ();

    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    }
    

#checks that  _a is present

    if ( not $field->subfield( "a" ) ) {
        $self->warn( "800: Must have a subfield _a." );
    }

#deal with _6 linking subfield at the start of the field and _4 and _8 subfields at end of field, removing them and dealing with the rest of the record as normal
    if ($newsubfields[0] eq '6') {
	shift(@newsubfields);
	shift(@newsubfields);
    }
    if (($newsubfields[$#newsubfields-1] eq '4') || ($newsubfields[$#newsubfields-1] eq '8')) {
	pop (@newsubfields);
	pop (@newsubfields);
    }


#Checks that field ends with full stop, hyphen, exclamation mark, question mark or closing bracket

    if ($newsubfields[$#newsubfields] !~ /[.)\]\-\?!]$/) {
        $self->warn ( "800: Should end with a full stop, hyphen, question mark, exclamation mark or closing bracket");
    }


#subfield a should be first subfield
    if ($newsubfields[0] ne 'a') {
        $self->warn ( "800: First subfield must be _a, but it is _$newsubfields[0]");
    }

    
#there should be no spaces between any initials in subfield _a

if ($field->subfield("a")){

    for (my $index = 0; $index <=$#newsubfields; $index+=2){
	if ($newsubfields[$index] eq 'a') {
	    if (($newsubfields[$index+1] =~ /\b\w\.\b\w\./) && ($newsubfields[$index+1] !~ /\[\bi\.e\. \b\w\..*\]/)){
		$self->warn ( "800: Any initials in the _a subfield should be separated by a space");
	    }
	}
    }
}
    

#fullstops, question marks or explanation marks

    @sfcodes=("g", "h", "k", "l", "s");
    foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, '[\.\?\!]$', $field, \@newsubfields, $self, "be preceded by a full stop, question mark or exclamation mark");
}

#commas

@sfcodes=("d", "e", "j", "m", "r");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ',$', $field, \@newsubfields, $self, "be preceded by a comma");
}


#semicolons

@sfcodes=("o", "v");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ' [;]$', $field, \@newsubfields, $self, "be preceded by a space-semi colon");
}

#commas or full stops


@sfcodes=("n");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, '[\.,]$', $field, \@newsubfields, $self, "be preceeded by a comma or a full stop");
}


#round brackets

@sfcodes=("q");
foreach $sfcode(@sfcodes){
	nesubfcheckpost ($sfcode, '^\(.*\)', $field, \@newsubfields, $self, "contain material enclosed in round brackets");
}

    
#subfield _b, if present, should not be preceded by a mark of punctuation unless it is preceded by an abbreviation

    if ($field->subfield("b")) {
    
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'b') {
		if ($newsubfields[$index-1] !~ /[\w\)]$/){
		    $self->warn ( "800: Subfield _b must not be preceded by a mark of punctuation unless the preceding word is an abbreviation - please check");
		}
	    }
	}
    }



#each subfield c, if present, should be preceded by a comma unless it contains material in round brackets.

    if ($field->subfield("c")) {

        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'c') && ($newsubfields[$index+1] =~ /^\(.*\)/)) {
		if ($newsubfields[$index-1] =~ /,$/){
		    $self->warn ( "800: If subfield _c contains material in round brackets it should not be preceded by a comma");
		}
	    }
	    if (($newsubfields[$index] eq 'c') && ($newsubfields[$index-1] !~ /,$/) && ($newsubfields[$index+1] !~ /^\(.*\)/)){
		$self->warn ( "800: Subfield _c must be preceded by a comma unless it contains material enclosed in round brackets");
	    }
	}
    }



#each subfield p, if present, must be preceded by a comma if it follows subfield n, or by a full stop  following other subfields
    if ($field->subfield("p")) {
	
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'p') {
                if (($newsubfields[$index-2] eq 'n') && ($newsubfields[$index-1] !~ /(\S,$)|(\-\- ,$)/)) {
                    $self->warn ("800: Subfield _p must be preceded by a comma when it follows subfield _n.");
                }
		elsif (($newsubfields[$index-2] ne 'n') && ($newsubfields[$index-1] !~ /(\S\.$)|(\-\- \.$)/)) {
		    $self->warn ( "800: Subfield _p must be preceded by full stop when it follows a subfield other than _n.");
		}
	    }
	}
    }


#subfield _t, if present, should be preceded by a full stop, hyphen or closing parenthesis

    if ($field->subfield("t")) {
    
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 't') { 
		if ($newsubfields[$index-1] !~ /[\?\.\-\)]$/){
		    $self->warn ("800: Subfield _t must be preceded by a full stop, hyphen, question mark or closing parenthesis.");
		}
	    }
            if ($newsubfields[$index] eq 't') { 
		if ($newsubfields[$index-1] =~ /-\.$/){
		    $self->warn ("800: If subfield _t is preceded by a hyphen, this should not be followed by a full stop.");
		}
	    }
	}
    }

    
} # check_800


=head2 check_810

Checks 810 field for punctuation, correct indicators and field structure.

=cut


sub check_810 {

    my $self = shift;
    my $field = shift;

    my @sfcodes;
    my $sfcode;

    # break subfields into code-data array (so the entire field is in one array)

    my @subfields = $field->subfields();
    my @newsubfields = ();

    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    }
    

#deal with _6 linking subfield at the start of the field and _4 and _8 subfields at end of field, removing them and dealing with the rest of the record as normal
    if ($newsubfields[0] eq '6') {
	shift(@newsubfields);
	shift(@newsubfields);
    }
    if (($newsubfields[$#newsubfields-1] eq '4') || ($newsubfields[$#newsubfields-1] eq '8')) {
	pop (@newsubfields);
	pop (@newsubfields);
    }



#checks that _a is present

    if ( not $field->subfield( "a" ) ) {
        $self->warn( "810: Must have a subfield _a." );
    }

#Checks that field ends with full stop or closing bracket

    if ($newsubfields[$#newsubfields] !~ /[.)\]\-\?!]$/) {
        $self->warn ( "810: Should end with a full stop, hyphen, exclamation mark, question mark or closing bracket");
    }




#subfield a should be first subfield
    if ($newsubfields[0] ne 'a') {
        $self->warn ( "810: First subfield must be _a, but it is _$newsubfields[0]");
    }


#fullstops

@sfcodes=("b", "f", "h", "k", "l", "u");
foreach $sfcode(@sfcodes){
    nesubfcheckpre ($sfcode, '\.$', $field, \@newsubfields, $self, "be preceded by a full stop");
}


#commas

    @sfcodes=("e");
    foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ',$', $field, \@newsubfields, $self, "be preceded by a comma");
    }


#space-semicolons

@sfcodes=("v");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ' ;$', $field, \@newsubfields, $self, "be preceded by a space-semicolon");
}


#each subfield c, if present, should be preceded by space-colon if _d is present, and contain material enclosed in round brackets if not


if ($field->subfield("c")){
    if ($field->subfield("d")) {
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'c') && ($newsubfields[$index-1] !~ / :$/)) {
		$self->warn ("810: If subfield _c is preceded by _d it should be preceded by space-colon");
	    }
	}
    }
    else {   
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'c') && ($newsubfields[$index+1] !~ /^\(.*\)/)) {
		$self->warn ( "810: Unless preceded by _d, _c should contain material enclosed in round brackets");
	    }
	}
    }
}

#each subfield _d, if present, should be preceded by space-semicolon if _n is present, and start witn an opening round bracket if not

if ($field->subfield("d")){
    if ($field->subfield("n")) {
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'd') && ($newsubfields[$index-1] !~ / :$/)) {
		$self->warn ("810: If subfield _d is preceded by _n it should be preceded by space-colon");
	    }
	}
    }


    elsif (($field->subfield("t")) && ($field->subfield("t")=~ /Treaties, etc\./)){
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'd') && ($newsubfields[$index-1] !~ /,$/)) {
		$self->warn ("810: If subfield _d is a treaty date it should be preceded by a comma");
	    }
	}
    }
    else {   
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'd') && ($newsubfields[$index+1] !~ /^\(.*/)) {
		$self->warn ( "810: Unless preceded by _n, _d should start with an opening round bracket");
	    }
	}
    }
}

#rules for _t, _g, _n and _p if a _t is present

if ($field->subfield("t")){

#subfield _t should be preceded by a full stop

    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	if ($newsubfields[$index] eq 't') {
	    if ($newsubfields[$index-1] !~ /\.$/){
		$self->warn ("810: Subfield _t must be preceded by a full stop");
	    }
	}
    }
    
#subfield _g, if present, should be preceded by a full stop
    
    if ($field->subfield("g")) {
	my $gposition;
        my $tposition;
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'g') {
                $gposition=$index;}
            }
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 't') {
                $tposition=$index;}
            }

            if (($gposition) && ($tposition)){
                if ($gposition > $tposition){
		if ($newsubfields[$gposition-1] !~ /\.$/){
		    $self->warn ("810: Following a _t, subfield _g must be preceded by a full stop");
		}
	    }
	}
    }
   
    
#subfield _n, if present, should be preceded by a full stop
    
    if ($field->subfield("n")) {
	
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'n') { 
		if ($newsubfields[$index-1] !~ /\.$/){
		    $self->warn ("810: Following a _t, subfield _n must be preceded by a full stop");
		}
	    }
	}
    }
    
    
#each subfield p, if present, must be preceded by a comma if it follows subfield n, or by a full stop  following other subfields
    
    if ($field->subfield("p")) {
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'p') {
                if (($newsubfields[$index-2] eq 'n') && ($newsubfields[$index-1] !~ /,$/)) {
                    $self->warn ("810: If there is a _t, subfield _p must be preceded by a comma when it follows subfield _n.");
                }
		elsif (($newsubfields[$index-2] ne 'n') && ($newsubfields[$index-1] !~ /\.$/)) {
		    $self->warn ( "810: If there is _t, subfield _p must be preceded by full stop when it follows a subfield other than _n.");
		}
	    }
	}
    }
}

else {

#rules for _n and _p if there is no _t subfield

#unless it follows a form heading of manuscript, _n starts with an opening round bracket

    if ($field->subfield("n")){
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	
		if (($newsubfields[$index] eq 'n') && ($newsubfields[$index+1] !~ /^\(.*/) && ($newsubfields[$index-1] !~ /Manuscript/)) {
		    $self->warn ("810: Unless it follows a _t or a form heading of Manuscript, _n should start with an opening round bracket");
		    
		}
	    }
    }


    if ($field->subfield("p")){
	for (my $index = 2; $index <=$#newsubfields; $index+=2){
	    if ($newsubfields[$index] eq 'p') { 
		if (($newsubfields[$index-1] =~ /^Manuscript/) && ($newsubfields[$index-1] !~ /\w$/)){
		    $self->warn ("810: Following a _k containing Manuscript, _p should not be preceded by a mark of punctuation");
		}
	    }
	}
    }
}
    

} # check_810


=head2 check_811

Checks 811 field for punctuation, correct indicators and field structure.

=cut


sub check_811 {

    my $self = shift;
    my $field = shift;

    my @sfcodes;
    my $sfcode;

    # break subfields into code-data array (so the entire field is in one array)

    my @subfields = $field->subfields();
    my @newsubfields = ();

    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    }
    


#deal with _6 linking subfield at the start of the field and _4 and _8 subfields at end of field, removing them and dealing with the rest of the record as normal

    if ($newsubfields[0] eq '6') {
	shift(@newsubfields);
	shift(@newsubfields);
    }
    if (($newsubfields[$#newsubfields-1] eq '4') || ($newsubfields[$#newsubfields-1] eq '8')) {
	pop (@newsubfields);
	pop (@newsubfields);
    }



#checks that _a is present

    if ( not $field->subfield( "a" ) ) {
        $self->warn( "811: Must have a subfield _a." );
    }

#Checks that field ends with full stop, hyphen, question mark, exclamation mark or closing bracket

    if ($newsubfields[$#newsubfields] !~ /[.)\]\-\?!]$/) {
        $self->warn ( "811: Should end with a full stop, hyphen, question mark, exclamation mark or closing bracket");
    }



#subfield a should be first subfield
    if ($newsubfields[0] ne 'a') {
        $self->warn ( "811: First subfield must be _a, but it is _$newsubfields[0]");
    }


#fullstops

@sfcodes=("e", "f", "k", "l", "u");
foreach $sfcode(@sfcodes){
    nesubfcheckpre ($sfcode, '\.$', $field, \@newsubfields, $self, "be preceded by a full stop");
}

#commas

    @sfcodes=("g");
    foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ',$', $field, \@newsubfields, $self, "be preceded by a comma");
}


#space-semicolons

@sfcodes=("v");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ' ;$', $field, \@newsubfields, $self, "be preceded by a space-semicolon");
}


#each subfield c, if present, should be preceded by space-colon if _d is present, and contain material enclosed in round brackets if not


if ($field->subfield("c")){
    if ($field->subfield("d")) {
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'c') && ($newsubfields[$index-1] !~ / :$/)) {
		$self->warn ("811: If subfield _c is preceded by _d it should be preceded by space-colon");
	    }
	}
    }
    else {   
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'c') && ($newsubfields[$index+1] !~ /^\(.*\)/)) {
		$self->warn ( "811: Unless preceded by _d, _c should contain material enclosed in round brackets");
	    }
	}
    }
}

#each subfield _d, if present, should be preceded by space-semicolon if _n is present, and start witn an opening round bracket if not

if ($field->subfield("d")){
    if ($field->subfield("n")) {
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'd') && ($newsubfields[$index-2] eq 'n') && ($newsubfields[$index-1] !~ / :$/)) {
		$self->warn ("811: If subfield _d is preceded by _n it should be preceded by space-colon");
	    }
	}
    }

    if ($field->subfield("q")) {
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'd') && ($newsubfields[$index-2] eq 'q') && ($newsubfields[$index-1] !~ /,$/)) {
		$self->warn ("811: If subfield _d is preceded by _q it should be preceded by a comma");
	    }
	}
    }

    if ((not $field->subfield("n")) && (not $field->subfield("q"))) {   
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	    if (($newsubfields[$index] eq 'd') && ($newsubfields[$index+1] !~ /^\(.*/)) {
		$self->warn ( "811: Unless preceded by _n or _q, _d should start with an opening round bracket");
	    }
	}
    }
}

#rules for _t, _g, _n and _p if a _t is present

if ($field->subfield("t")){

#subfield _t should be preceded by a full stop

    for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	if ($newsubfields[$index] eq 't') { 
	    if ($newsubfields[$index-1] !~ /\.$/){
		$self->warn ("811: Subfield _t must be preceded by a full stop");
	    }
	}
    }
    
    
#subfield _n, if present, should be preceded by a full stop
    
    if ($field->subfield("n")) {
	
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'n') { 
		if ($newsubfields[$index-1] !~ /\.$/){
		    $self->warn ("811: Following a _t, subfield _n must be preceded by a full stop");
		}
	    }
	}
    }

    
#subfield _p, if present, should be preceded by a full stop
    
    if ($field->subfield("p")) {
	
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'p') { 
		if ($newsubfields[$index-1] !~ /\.$/){
		    $self->warn ("811: Following a _t, subfield _p must be preceded by a full stop");
		}
	    }
	}
    }


}

else {

#rules for _n if there is no _t subfield


#_n starts with an opening round bracket

    if ($field->subfield("n")){
	for (my $index = 2; $index <=$#newsubfields; $index+=2) {
	
		if (($newsubfields[$index] eq 'n') && ($newsubfields[$index+1] !~ /^\(.*/)) {
		    $self->warn ("811: Unless it follows a _t, _n should start with an opening round bracket");
		    
		}
	    }
    }

}

} # check_811


=head2 check_830

Checks 830 field for punctuation, correct indicators and field structure.

=cut


sub check_830 {

    my $self = shift;
    my $field = shift;

    my @sfcodes;
    my $sfcode;

    # break subfields into code-data array (so the entire field is in one array)

    my @subfields = $field->subfields();
    my @newsubfields = ();

    while (my $subfield = pop(@subfields)) {
        my ($code, $data) = @$subfield;
        unshift (@newsubfields, $code, $data);
    }
    

#checks that  _a is present

    if ( not $field->subfield( "a" ) ) {
        $self->warn( "830: Must have a subfield _a." );
    }

#Checks that field ends with a full stop, hyphen, question mark, exclamation mark or closing bracket
    if ($newsubfields[$#newsubfields] !~ /[.)\]\?!\-]$/) {
        $self->warn ( "830: Should end with a full stop, hyphen, question mark, exclamation mark or closing bracket");
    }


#deal with _6 linking subfield at the start of the field, removing it and dealing with the rest of the record as normal
    if ($newsubfields[0] eq '6') {
	shift(@newsubfields);
	shift(@newsubfields);
    }


#subfield a should be first subfield
    if ($newsubfields[0] ne 'a') {
        $self->warn ( "830: First subfield must be _a, but it is _$newsubfields[0]");
    }


#round brackets

@sfcodes=("d");
foreach $sfcode(@sfcodes){
	nesubfcheckpost ($sfcode, '^\(.*\)', $field, \@newsubfields, $self, "contain material enclosed in round brackets");
}


#fullstops

    @sfcodes=("f", "g", "h", "k", "l", "s");
    foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, '\.$', $field, \@newsubfields, $self, "be preceded by a full stop");
}

#commas

@sfcodes=("m", "r");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ',$', $field, \@newsubfields, $self, "be preceded by a comma");
}


#semicolons

@sfcodes=("o");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ';$', $field, \@newsubfields, $self, "be preceded by a semicolon");
}


#space-semicolons

@sfcodes=("v");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, ' [;]$', $field, \@newsubfields, $self, "be preceded by a semicolon");
}


#commas or full stops


@sfcodes=("n");
foreach $sfcode(@sfcodes){
	nesubfcheckpre ($sfcode, '[\.,]$', $field, \@newsubfields, $self, "be preceded by a comma or a full stop");
}



#each subfield p, if present, must be preceded by a comma if it follows subfield n, or by a full stop  following other subfields
    if ($field->subfield("p")) {
	
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq 'p') {
                if (($newsubfields[$index-2] eq 'n') && ($newsubfields[$index-1] !~ /(\S,$)|(\-\- ,$)/)) {
                    $self->warn ("830: Subfield _p must be preceded by a comma when it follows subfield _n.");
                }
		elsif (($newsubfields[$index-2] ne 'n') && ($newsubfields[$index-1] !~ /(\S\.$)|(\-\- \.$)/)) {
		    $self->warn ( "830: Subfield _p must be preceded by full stop when it follows a subfield other than _n.");
		}
	    }
	}
    }
    
} # check_830



############
# Internal #
############

=head2 _check_article

Check of articles is based on code from Ian Hamilton. This version is more
limited in that it focuses on English, Spanish, French, Italian and German
articles. Certain possible articles have been removed if they are valid English
non-articles. This version also disregards 008_language/041 codes and just uses
the list of articles to provide warnings/suggestions.

source for articles = L<http://www.loc.gov/marc/bibliographic/bdapp-e.html>

Should work with fields 130, 240, 245, 630, 730, and 830. Reports error if
another field is passed in.

=cut

sub _check_article {

    my $self = shift;
    my $field = shift;

#add articles here as needed
##Some omitted due to similarity with valid words.
    my %article = (
        'a' => 'eng glg hun por',
	'al-' => 'ara',
        'an' => 'eng',
	'ane' => 'enm',
        'das' => 'ger',
        'dem' => 'ger',
        'den' => 'ger',
        'der' => 'ger',
	'det' => 'ger',
        'die' => 'ger',
	'een' => 'dut',
	'ein' => 'ger',
        'eine' => 'ger',
        'einem' => 'ger',
        'einen' => 'ger',
        'einer' => 'ger',
        'eines' => 'ger',
        'el' => 'spa',
        'els' => 'cat',
        'gl' => 'ita',
        'gli' => 'ita',
	'ha' => 'heb',
        'het' => 'dut',
        'ho' => 'grc',
        'il' => 'ita mlt',
        'l' => 'cat fre ita mlt',
        'la' => 'cat fre ita spa',
        'las' => 'spa',
        'le' => 'fre ita',
        'les' => 'cat fre',
        'lo' => 'ita spa',
        'los' => 'spa',
        'os' => 'por',
	'ta' => 'grc',
        'ton' => 'grc',
        'the' => 'eng',
        'um' => 'por',
        'uma' => 'por',
        'un' => 'cat spa fre ita',
        'una' => 'cat spa ita',
        'une' => 'fre',
        'uno' => 'ita',
	'y' => 'wel',
    );

#add exceptions here as needed
# may want to make keys lowercase
    my %exceptions = (
        'A & E' => 1,
        'A-' => 1,
        'A+' => 1,
        'A is ' => 1,
        'A l\'' => 1,
        'A la ' => 1,
        'A posteriori' => 1,
        'A priori' => 1,
        'El Greco' => 1,
        'El Nino' => 1,
	'El Nin.o' => 1,
        'El Salvador' => 1,
        'Ha ha' => 1,
        'L-' => 1,
        'La Bruyere' => 1,
        'La Fontaine' => 1,
        'La Rochefoucauld' => 1,
	'La Rochelle' => 1,
	'La Salle' => 1,
        'Las Vegas' => 1,
	'Le Mans' => 1,
	'Le Corbusier' => 1,
        'Le Neve ' => 1,
        'Lo mein' => 1,
        'Los Alamos' => 1,
        'Los Angeles' => 1,
    );

    #get tagno to determine which indicator to check and for reporting
    my $tagno = $field->tag();

    #$ind holds nonfiling character indicator value
    my $ind = '';
    #$first_or_second holds which indicator is for nonfiling char value 
    my $first_or_second = '';
    if ($tagno !~ /^(?:130|240|245|630|730|740|830)$/) {
        print $tagno, " is not a valid field for article checking\n";
        return;
    } #if field is not one of those checked for articles
    #130, 630, 730, 740 => ind1
    elsif ($tagno =~ /^(?:130|630|730|740)$/) {
        $ind = $field->indicator(1);
        $first_or_second = '1st';
    } #if field is 130, 630, or 730
    #240, 245, 830 => ind2
    elsif ($tagno =~ /^(?:240|245|830)$/) {
        $ind = $field->indicator(2);
        $first_or_second = '2nd';
    } #if field is 240, 245, or 830


    #report non-numeric non-filing indicators as invalid
    $self->warn ( $tagno, ": Non-filing indicator is non-numeric" ) unless ($ind =~ /^[0-9]$/);
    #get subfield 'a' of the title field
    my $title = $field->subfield('a') || '';


    my $char1_notalphanum = 0;
    #check for apostrophe, quote, bracket,  or parenthesis, before first word
    #remove if found and add to non-word counter
    while ($title =~ /^["'\[\(*]/){
        $char1_notalphanum++;
        $title =~ s/^["'\[\(*]//;
    }
    # split title into first word + rest on space, apostrophe or hyphen
    my ($firstword,$separator,$etc) = $title =~ /^([^ '\-]+)([ '\-])?(.*)/i;
        $firstword = '' if ! defined( $firstword );
        $separator = '' if ! defined( $separator );
        $etc = '' if ! defined( $etc );

    #get length of first word plus the number of chars removed above plus one for the separator
    my $nonfilingchars = length($firstword) + $char1_notalphanum + 1;

    #check to see if first word is an exception
    my $isan_exception = 0;
    $isan_exception = grep {$title =~ /^\Q$_\E/i} (keys %exceptions);

    #lowercase chars of $firstword for comparison with article list
    $firstword = lc($firstword);

    my $isan_article = 0;

    #see if first word is in the list of articles and not an exception
    $isan_article = 1 if (($article{$firstword}) && !($isan_exception));

    #if article then $nonfilingchars should match $ind
    if ($isan_article) {
        #account for quotes or apostrophes before 2nd word (only checks for 1)
        if (($separator eq ' ') && ($etc =~ /^['"]/)) {
            $nonfilingchars++;
        }
        #special case for 'en' (unsure why)
        if ($firstword eq 'en') {
            $self->warn ( $tagno, ": First word, , $firstword, may be an article, check $first_or_second indicator ($ind)." ) unless (($ind eq '3') || ($ind eq '0'));
        }
        elsif ($nonfilingchars ne $ind) {
            $self->warn ( $tagno, ": First word, $firstword, may be an article, check $first_or_second indicator ($ind)." );
        } #unless ind is same as length of first word and nonfiling characters
    } #if first word is in article list
    #not an article so warn if $ind is not 0
    else {
        unless ($ind eq '0') {
            $self->warn ( $tagno, ": First word, $firstword, does not appear to be an article, check $first_or_second indicator ($ind)." );
        } #unless ind is 0
    } #else not in article list

#######################################

} #_check_article

#subfield checks

#preceding subfield - punctuation doesn't equal

sub nesubfcheckpre {
    my ($subf, $punc, $field, $newsubfields, $self, $warning)=@_;
    my @newsubfields=@$newsubfields;
    my $fieldno=$field->tag;

    if ($field->subfield("$subf")) {
    
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if (($newsubfields[$index] eq "$subf") && ($newsubfields[$index-1] !~ /$punc/)){
		    $self->warn ("$fieldno: Subfield $subf must $warning");
		}
	    }
	}
    }

#preceding subfield - punctuation equals

sub eqsubfcheckpre {
    my ($subf, $punc, $field, $newsubfields, $self, $warning)=@_;
    my @newsubfields=@$newsubfields;
    my $fieldno=$field->tag;

    if ($field->subfield("$subf")) {
    
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if (($newsubfields[$index] eq "$subf") && ($newsubfields[$index-1] =~ /$punc/)){
		    $self->warn ("$fieldno: Subfield $subf must $warning");
		}
	    }
	}
    }

#following subfield - punctuation doesn't equal

sub nesubfcheckpost {
    my ($subf, $punc, $field, $newsubfields, $self, $warning)=@_;
    my @newsubfields=@$newsubfields;
    my $fieldno=$field->tag;

    if ($field->subfield("$subf")) {
    
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if (($newsubfields[$index] eq "$subf") && ($newsubfields[$index+1] !~ /$punc/)){
		    $self->warn ("$fieldno: Subfield $subf must $warning");
		}
	    }
	}
    }

#following subfield - punctuation equals

sub eqsubfcheckpost {
    my ($subf, $punc, $field, $newsubfields, $self, $warning)=@_;
    my @newsubfields=@$newsubfields;
    my $fieldno=$field->tag;

    if ($field->subfield("$subf")) {
    
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if (($newsubfields[$index] eq "$subf") && ($newsubfields[$index+1] =~ /$punc/)){
		    $self->warn ("$fieldno: Subfield $subf must $warning");
		}
	    }
	}
    }

#checks for 6XX v, x, y, z fields    

sub subfvxyzcheck {
    my ($subf, $field, $newsubfields, $self)=@_;
    my @newsubfields=@$newsubfields;
    my $fieldno=$field->tag;

    if ($field->subfield("$subf")) {
    
        for (my $index = 2; $index <=$#newsubfields; $index+=2) {
            if ($newsubfields[$index] eq "$subf"){
		if (($newsubfields[$index-1] !~ /[\w\)\?\-\']$/) && ($newsubfields[$index-1] !~ /etc\.$/) && ($newsubfields[$index-1] !~ /O\.T\.$/) && ($newsubfields[$index-1] !~ /N\.T\.$/) && ($newsubfields[$index-1] !~ /A\.\D\.$/) && ($newsubfields[$index-1] !~ /B\.C\.$/) && ($newsubfields[$index-1] !~ /cent\.$/)){
		    $self->warn ("$fieldno: Subfield $subf should not be preceded by a mark of punctuation, unless it is a closing bracket, hyphen, question mark, apostrophe, or a full stop at the end of an abbreviation.");
		}
	    }
	}
    }
}
	   	
    

############

=head1 SEE ALSO

Check the docs for L<MARC::Record>.  All software links are there.

=head1 TODO

=over 4

=item * ISBN and ISSN checking

We can check the 020 and 022 fields with the C<Business::ISBN> and
C<Business::ISSN> modules, respectively.

=item * check_041 cleanup

Splitting subfield code strings every 3 chars could probably be written more efficiently.

=item * check_245 cleanup

The article checking in particular.

=item * Method for turning off checks

Provide a way for users to skip checks more easily when using check_record, or a
specific check_xxx method (e.g. skip article checking).

=back

=head1 LICENSE

This code may be distributed under the same terms as Perl itself.

Please note that these modules are not products of or supported by the
employers of the various contributors to the code.

=cut

# Used only to read the stuff from __DATA__
sub _read_rules() {
    my $self = shift;

    my $tell = tell(DATA);  # Stash the position so we can reset it for next time

    local $/ = "";
    while ( my $tagblock = <DATA> ) {
        my @lines = split( /\n/, $tagblock );
        s/\s+$// for @lines;

        next unless @lines >= 4; # Some of our entries are tag-only

        my $tagline = shift @lines;
        my @keyvals = split( /\s+/, $tagline, 3 );
        my $tagno = shift @keyvals;
        my $repeatable = shift @keyvals;

        $self->_parse_tag_rules( $tagno, $repeatable, @lines );
    } # while

    # Set the pointer back to where it was, in case we do this again
    seek( DATA, $tell, 0 );
}

sub _parse_tag_rules {
    my $self = shift;
    my $tagno = shift;
    my $repeatable = shift;
    my @lines = @_;

    my $rules = ($self->{_rules}->{$tagno} ||= {});
    $rules->{$repeatable} = $repeatable;

    for my $line ( @lines ) {
        my @keyvals = split( /\s+/, $line, 3 );
        my $key = shift @keyvals;
        my $val = shift @keyvals;

        # Do magic for indicators
        if ( $key =~ /^ind/ ) {
            $rules->{$key} = $val;

            my $desc;
            my $regex;

            if ( $val eq "blank" ) {
                $desc = "blank";
                $regex = qr/^ $/;
            } else {
                $desc = _nice_list($val);
                $val =~ s/^b/ /;
                $regex = qr/^[$val]$/;
            }

            $rules->{$key."_desc"} = $desc;
            $rules->{$key."_regex"} = $regex;
        } # if indicator
        else {
            if ( $key =~ /(.)-(.)/ ) {
                my ($min,$max) = ($1,$2);
                $rules->{$_} = $val for ($min..$max);
            } else {
                $rules->{$key} = $val;
            }
        } # not an indicator
    } # for $line
}


sub _nice_list($) {
    my $str = shift;

    if ( $str =~ s/(\d)-(\d)/$1 thru $2/ ) {
        return $str;
    }

    my @digits = split( //, $str );
    $digits[0] = "blank" if $digits[0] eq "b";

    my $last = pop @digits;
    if ($last eq '0'){
	return "$last";}
    else{
    return join( ", ", @digits ) . " or $last";

}
}

sub _ind_regex($) {
    my $str = shift;

    return qr/^ $/ if $str eq "blank";

    return qr/^[$str]$/;
}


1;

__DATA__
001     NR      CONTROL NUMBER
ind1    blank   Undefined
ind2    blank   Undefined
        NR      Undefined

002     NR      LOCALLY DEFINED (UNOFFICIAL)
ind1    blank   Undefined
ind2    blank   Undefined
        NR      Undefined

003     NR      CONTROL NUMBER IDENTIFIER
ind1    blank   Undefined
ind2    blank   Undefined
        NR      Undefined

005     NR      DATE AND TIME OF LATEST TRANSACTION
ind1    blank   Undefined
ind2    blank   Undefined
        NR      Undefined

006     R       FIXED-LENGTH DATA ELEMENTS--ADDITIONAL MATERIAL CHARACTERISTICS--GENERAL INFORMATION
ind1    blank   Undefined
ind2    blank   Undefined
        R       Undefined

007     R       PHYSICAL DESCRIPTION FIXED FIELD--GENERAL INFORMATION
ind1    blank   Undefined
ind2    blank   Undefined
        R       Undefined

008     NR      FIXED-LENGTH DATA ELEMENTS--GENERAL INFORMATION
ind1    blank   Undefined
ind2    blank   Undefined
        NR      Undefined

010     NR      LIBRARY OF CONGRESS CONTROL NUMBER
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      LC control number 
b       R       NUCMC control number 
z       R       Canceled/invalid LC control number 
8       R       Field link and sequence number 

013     R       PATENT CONTROL INFORMATION
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Number 
b       NR      Country 
c       NR      Type of number 
d       R       Date 
e       R       Status 
f       R       Party to document 
6       NR      Linkage 
8       R       Field link and sequence number 

015     R       NATIONAL BIBLIOGRAPHY NUMBER
ind1    blank   Undefined
ind2    blank   Undefined
a       R       National bibliography number 
2       NR      Source 
6       NR      Linkage 
8       R       Field link and sequence number 

016     R       NATIONAL BIBLIOGRAPHIC AGENCY CONTROL NUMBER
ind1    b7      National bibliographic agency
ind2    blank   Undefined
a       NR      Record control number 
z       R       Canceled or invalid record control number 
2       NR      Source 
8       R       Field link and sequence number 

017     R       COPYRIGHT OR LEGAL DEPOSIT NUMBER
ind1    blank   Undefined
ind2    b8      Undefined
a       R       Copyright or legal deposit number
b       NR      Assigning agency
d       NR      Date
i       NR      Display text
2       NR      Source 
6       NR      Linkage 
8       R       Field link and sequence number 

018     NR      COPYRIGHT ARTICLE-FEE CODE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Copyright article-fee code 
6       NR      Linkage 
8       R       Field link and sequence number 

020     R       INTERNATIONAL STANDARD BOOK NUMBER
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      International Standard Book Number 
c       NR      Terms of availability 
z       R       Canceled/invalid ISBN 
6       NR      Linkage 
8       R       Field link and sequence number 

022     R       INTERNATIONAL STANDARD SERIAL NUMBER
ind1    b01     Level of international interest
ind2    blank   Undefined
a       NR      International Standard Serial Number 
y       R       Incorrect ISSN 
z       R       Canceled ISSN 
2       NR      Source 
6       NR      Linkage 
8       R       Field link and sequence number 

024     R       OTHER STANDARD IDENTIFIER
ind1    0123478    Type of standard number or code
ind2    b01     Difference indicator
a       NR      Standard number or code 
c       NR      Terms of availability 
d       NR      Additional codes following the standard number or code 
z       R       Canceled/invalid standard number or code 
2       NR      Source of number or code 
6       NR      Linkage 
8       R       Field link and sequence number 

025     R       OVERSEAS ACQUISITION NUMBER
ind1    blank   Undefined
ind2    blank   Undefined
a       R       Overseas acquisition number 
8       R       Field link and sequence number 

026     R       FINGERPRINT IDENTIFIER
ind1    blank   Undefined
ind2    blank   Undefined
a       R       First and second groups of characters 
b       R       Third and fourth groups of characters 
c       NR      Date 
d       R       Number of volume or part 
e       NR      Unparsed fingerprint 
2       NR      Source 
5       R       Institution to which field applies 
6       NR      Linkage 
8       R       Field link and sequence number 

027     R       STANDARD TECHNICAL REPORT NUMBER
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Standard technical report number 
z       R       Canceled/invalid number 
6       NR      Linkage 
8       R       Field link and sequence number 

028     R       PUBLISHER NUMBER
ind1    012345  Type of publisher number
ind2    0123    Note/added entry controller
a       NR      Publisher number 
b       NR      Source 
6       NR      Linkage 
8       R       Field link and sequence number 

030     R       CODEN DESIGNATION
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      CODEN 
z       R       Canceled/invalid CODEN 
6       NR      Linkage 
8       R       Field link and sequence number 

031     R       MUSICAL INCIPITS INFORMATION
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Number of work
b       NR      Number of movement
c       NR      Number of excerpt
d       R       Caption or heading
e       NR      Role
g       NR      Clef
m       NR      Voice/instrument
n       NR      Key signature
o       NR      Time signature
p       NR      Musical notation
q       R       General note
r       NR      Key or mode
s       R       Coded validity note
t       R       Text incipit
u       R       Uniform Resource Identifier
y       R       Link text
z       R       Public note
2       NR      System code
6       NR      Linkage
8       R       Field link and sequence number

032     R       POSTAL REGISTRATION NUMBER
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Postal registration number 
b       NR      Source (agency assigning number) 
6       NR      Linkage 
8       R       Field link and sequence number 

033     R       DATE/TIME AND PLACE OF AN EVENT
ind1    b012    Type of date in subfield $a
ind2    b012    Type of event
a       R       Formatted date/time 
b       R       Geographic classification area code 
c       R       Geographic classification subarea code 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

034     R       CODED CARTOGRAPHIC MATHEMATICAL DATA
ind1    013     Type of scale
ind2    b01     Type of ring
a       NR      Category of scale 
b       R       Constant ratio linear horizontal scale 
c       R       Constant ratio linear vertical scale 
d       NR      Coordinates--westernmost longitude 
e       NR      Coordinates--easternmost longitude 
f       NR      Coordinates--northernmost latitude 
g       NR      Coordinates--southernmost latitude 
h       R       Angular scale 
j       NR      Declination--northern limit 
k       NR      Declination--southern limit 
m       NR      Right ascension--eastern limit 
n       NR      Right ascension--western limit 
p       NR      Equinox 
s       R       G-ring latitude 
t       R       G-ring longitude 
6       NR      Linkage 
8       R       Field link and sequence number 

035     R       SYSTEM CONTROL NUMBER
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      System control number 
z       R       Canceled/invalid control number 
6       NR      Linkage 
8       R       Field link and sequence number 
9       NR      Previous system control number (Endeavor defined)

036     NR      ORIGINAL STUDY NUMBER FOR COMPUTER DATA FILES
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Original study number 
b       NR      Source (agency assigning number) 
6       NR      Linkage 
8       R       Field link and sequence number 

037     R       SOURCE OF ACQUISITION
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Stock number 
b       NR      Source of stock number/acquisition 
c       R       Terms of availability 
f       R       Form of issue 
g       R       Additional format characteristics 
n       R       Note 
6       NR      Linkage 
8       R       Field link and sequence number 

038     NR      RECORD CONTENT LICENSOR
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Record content licensor 
6       NR      Linkage 
8       R       Field link and sequence number 

040     NR      CATALOGING SOURCE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Original cataloging agency 
b       NR      Language of cataloging 
c       NR      Transcribing agency 
d       R       Modifying agency 
e       NR      Description conventions 
6       NR      Linkage 
8       R       Field link and sequence number 

041     R       LANGUAGE CODE
ind1    01      Translation indication
ind2    b7      Source of code
a       R       Language code of text/sound track or separate title 
b       R       Language code of summary or abstract/overprinted title or subtitle 
d       R       Language code of sung or spoken text 
e       R       Language code of librettos 
f       R       Language code of table of contents 
g       R       Language code of accompanying material other than librettos 
h       R       Language code of original and/or intermediate translations of text 
2       NR      Source of code 
6       NR      Linkage 
8       R       Field link and sequence number 

042     NR      AUTHENTICATION CODE
ind1    blank   Undefined
ind2    blank   Undefined
a       R       Authentication code 

043     NR      GEOGRAPHIC AREA CODE
ind1    blank   Undefined
ind2    blank   Undefined
a       R       Geographic area code 
b       R       Local GAC code 
c       R       ISO code 
2       R       Source of local code 
6       NR      Linkage 
8       R       Field link and sequence number 

044     NR      COUNTRY OF PUBLISHING/PRODUCING ENTITY CODE
ind1    blank   Undefined
ind2    blank   Undefined
a       R       MARC country code 
b       R       Local subentity code 
c       R       ISO country code 
2       R       Source of local subentity code 
6       NR      Linkage 
8       R       Field link and sequence number 

045     NR      TIME PERIOD OF CONTENT
ind1    b012    Type of time period in subfield $b or $c
ind2    blank   Undefined
a       R       Time period code 
b       R       Formatted 9999 B.C. through C.E. time period 
c       R       Formatted pre-9999 B.C. time period 
6       NR      Linkage 
8       R       Field link and sequence number 

046     R       SPECIAL CODED DATES
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Type of date code 
b       NR      Date 1 (B.C. date) 
c       NR      Date 1 (C.E. date) 
d       NR      Date 2 (B.C. date) 
e       NR      Date 2 (C.E. date) 
j       NR      Date resource modified 
k       NR      Beginning or single date created 
l       NR      Ending date created 
m       NR      Beginning of date valid 
n       NR      End of date valid 
2       NR      Source of date 
6       NR      Linkage 
8       R       Field link and sequence number 

047     NR      FORM OF MUSICAL COMPOSITION CODE
ind1    blank   Undefined
ind2    blank   Undefined
a       R       Form of musical composition code 
8       R       Field link and sequence number 

048     R       NUMBER OF MUSICAL INSTRUMENTS OR VOICES CODE
ind1    blank   Undefined
ind2    blank   Undefined
a       R       Performer or ensemble 
b       R       Soloist 
8       R       Field link and sequence number

050     R       LIBRARY OF CONGRESS CALL NUMBER
ind1    b01     Existence in LC collection
ind2    04      Source of call number
a       R       Classification number 
b       NR      Item number 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

051     R       LIBRARY OF CONGRESS COPY, ISSUE, OFFPRINT STATEMENT
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Classification number 
b       NR      Item number 
c       NR      Copy information 
8       R       Field link and sequence number 

052     R       GEOGRAPHIC CLASSIFICATION
ind1    b17     Code source
ind2    blank   Undefined
a       NR      Geographic classification area code 
b       R       Geographic classification subarea code 
d       R       Populated place name 
2       NR      Code source 
6       NR      Linkage 
8       R       Field link and sequence number 

055     R       CLASSIFICATION NUMBERS ASSIGNED IN CANADA
ind1    b01     Existence in LAC collection
ind2    0123456789   Type, completeness, source of class/call number
a       NR      Classification number 
b       NR      Item number 
2       NR      Source of call/class number 
8       R       Field link and sequence number 

060     R       NATIONAL LIBRARY OF MEDICINE CALL NUMBER
ind1    b01     Existence in NLM collection
ind2    04      Source of call number
a       R       Classification number 
b       NR      Item number 
8       R       Field link and sequence number 

061     R       NATIONAL LIBRARY OF MEDICINE COPY STATEMENT
ind1    blank   Undefined
ind2    blank   Undefined
a       R       Classification number 
b       NR      Item number 
c       NR      Copy information 
8       R       Field link and sequence number 

066     NR      CHARACTER SETS PRESENT
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Primary G0 character set 
b       NR      Primary G1 character set 
c       R       Alternate G0 or G1 character set 

070     R       NATIONAL AGRICULTURAL LIBRARY CALL NUMBER
ind1    01      Existence in NAL collection
ind2    blank   Undefined
a       R       Classification number 
b       NR      Item number 
8       R       Field link and sequence number 

071     R       NATIONAL AGRICULTURAL LIBRARY COPY STATEMENT
ind1    blank   Undefined
ind2    blank   Undefined
a       R       Classification number 
b       NR      Item number 
c       NR      Copy information 
8       R       Field link and sequence number 

072     R       SUBJECT CATEGORY CODE
ind1    blank   Undefined
ind2    07      Source specified in subfield $2
a       NR      Subject category code 
x       R       Subject category code subdivision 
2       NR      Source 
6       NR      Linkage 
8       R       Field link and sequence number 

074     R       GPO ITEM NUMBER
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      GPO item number 
z       R       Canceled/invalid GPO item number 
8       R       Field link and sequence number 

080     R       UNIVERSAL DECIMAL CLASSIFICATION NUMBER
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Universal Decimal Classification number 
b       NR      Item number 
x       R       Common auxiliary subdivision 
2       NR      Edition identifier 
6       NR      Linkage 
8       R       Field link and sequence number 

082     R       DEWEY DECIMAL CLASSIFICATION NUMBER
ind1    b01     Type of edition
ind2    b04     Source of classification number
a       R       Classification number 
b       NR      Item number 
2       NR      Edition number 
6       NR      Linkage 
8       R       Field link and sequence number 

084     R       OTHER CLASSIFICATION NUMBER
ind1    blank   Undefined
ind2    blank   Undefined
a       R       Classification number 
b       NR      Item number 
2       NR      Source of number 
6       NR      Linkage 
8       R       Field link and sequence number 

086     R       GOVERNMENT DOCUMENT CLASSIFICATION NUMBER
ind1    b01     Number source
ind2    blank   Undefined
a       NR      Classification number 
z       R       Canceled/invalid classification number 
2       NR      Number source 
6       NR      Linkage 
8       R       Field link and sequence number 

088     R       REPORT NUMBER
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Report number 
z       R       Canceled/invalid report number 
6       NR      Linkage 
8       R       Field link and sequence number 

100     NR      MAIN ENTRY--PERSONAL NAME
ind1    013     Type of personal name entry element
ind2    blank   Undefined
a       NR      Personal name 
b       NR      Numeration 
c       R       Titles and other words associated with a name 
d       NR      Dates associated with a name 
e       R       Relator term 
f       NR      Date of a work 
g       NR      Miscellaneous information 
j       R       Attribution qualifier 
k       R       Form subheading 
l       NR      Language of a work 
n       R       Number of part/section of a work 
p       R       Name of part/section of a work 
q       NR      Fuller form of name 
t       NR      Title of a work 
u       NR      Affiliation 
4       R       Relator code 
6       NR      Linkage 
8       R       Field link and sequence number 

110     NR      MAIN ENTRY--CORPORATE NAME
ind1    012     Type of corporate name entry element
ind2    blank   Undefined
a       NR      Corporate name or jurisdiction name as entry element 
b       R       Subordinate unit 
c       NR      Location of meeting 
d       R       Date of meeting or treaty signing 
e       R       Relator term 
f       NR      Date of a work 
g       NR      Miscellaneous information 
k       R       Form subheading 
l       NR      Language of a work 
n       R       Number of part/section/meeting 
p       R       Name of part/section of a work 
t       NR      Title of a work 
u       NR      Affiliation 
4       R       Relator code 
6       NR      Linkage 
8       R       Field link and sequence number 

111     NR      MAIN ENTRY--MEETING NAME
ind1    012     Type of meeting name entry element
ind2    blank   Undefined
a       NR      Meeting name or jurisdiction name as entry element 
c       NR      Location of meeting 
d       NR      Date of meeting 
e       R       Subordinate unit 
f       NR      Date of a work 
g       NR      Miscellaneous information 
k       R       Form subheading 
l       NR      Language of a work 
n       R       Number of part/section/meeting 
p       R       Name of part/section of a work 
q       NR      Name of meeting following jurisdiction name entry element 
t       NR      Title of a work 
u       NR      Affiliation 
4       R       Relator code 
6       NR      Linkage 
8       R       Field link and sequence number 

130     NR      MAIN ENTRY--UNIFORM TITLE
ind1    0       Nonfiling characters
ind2    blank   Undefined
a       NR      Uniform title 
d       R       Date of treaty signing 
f       NR      Date of a work 
g       NR      Miscellaneous information 
h       NR      Medium 
k       R       Form subheading 
l       NR      Language of a work 
m       R       Medium of performance for music 
n       R       Number of part/section of a work 
o       NR      Arranged statement for music 
p       R       Name of part/section of a work 
r       NR      Key for music 
s       NR      Version 
t       NR      Title of a work 
6       NR      Linkage 
8       R       Field link and sequence number 

210     R       ABBREVIATED TITLE
ind1    01      Title added entry
ind2    b0      Type
a       NR      Abbreviated title 
b       NR      Qualifying information 
2       R       Source 
6       NR      Linkage 
8       R       Field link and sequence number 

222     R       KEY TITLE
ind1    blank   Specifies whether variant title and/or added entry is required
ind2    0-9     Nonfiling characters
a       NR      Key title 
b       NR      Qualifying information 
6       NR      Linkage 
8       R       Field link and sequence number 

240     NR      UNIFORM TITLE
ind1    01      Uniform title printed or displayed
ind2    0       Nonfiling characters
a       NR      Uniform title 
d       R       Date of treaty signing 
f       NR      Date of a work 
g       NR      Miscellaneous information 
h       NR      Medium 
k       R       Form subheading 
l       NR      Language of a work 
m       R       Medium of performance for music 
n       R       Number of part/section of a work 
o       NR      Arranged statement for music 
p       R       Name of part/section of a work 
r       NR      Key for music 
s       NR      Version 
6       NR      Linkage 
8       R       Field link and sequence number 

242     R       TRANSLATION OF TITLE BY CATALOGING AGENCY
ind1    01    Title added entry
ind2    0-9    Nonfiling characters
a       NR      Title 
b       NR      Remainder of title 
c       NR      Statement of responsibility, etc. 
h       NR      Medium 
n       R       Number of part/section of a work 
p       R       Name of part/section of a work 
y       NR      Language code of translated title 
6       NR      Linkage 
8       R       Field link and sequence number 

243     NR      COLLECTIVE UNIFORM TITLE
ind1    01    Uniform title printed or displayed
ind2    0-9    Nonfiling characters
a       NR      Uniform title 
d       R       Date of treaty signing 
f       NR      Date of a work 
g       NR      Miscellaneous information 
h       NR      Medium 
k       R       Form subheading 
l       NR      Language of a work 
m       R       Medium of performance for music 
n       R       Number of part/section of a work 
o       NR      Arranged statement for music 
p       R       Name of part/section of a work 
r       NR      Key for music 
s       NR      Version 
6       NR      Linkage 
8       R       Field link and sequence number 

245     NR      TITLE STATEMENT
ind1    01    Title added entry
ind2    0-9    Nonfiling characters
a       NR      Title 
b       NR      Remainder of title 
c       NR      Statement of responsibility, etc. 
f       NR      Inclusive dates 
g       NR      Bulk dates 
h       NR      Medium 
k       R       Form 
n       R       Number of part/section of a work 
p       R       Name of part/section of a work 
s       NR      Version 
6       NR      Linkage 
8       R       Field link and sequence number 

246     R       VARYING FORM OF TITLE
ind1    0123    Note/added entry controller
ind2    b012345678    Type of title
a       NR      Title proper/short title 
b       NR      Remainder of title 
f       NR      Date or sequential designation 
g       NR      Miscellaneous information 
h       NR      Medium 
i       NR      Display text 
n       R       Number of part/section of a work 
p       R       Name of part/section of a work 
5       NR      Institution to which field applies 
6       NR      Linkage 
8       R       Field link and sequence number 

247     R       FORMER TITLE
ind1    01      Title added entry
ind2    01      Note controller
a       NR      Title 
b       NR      Remainder of title 
f       NR      Date or sequential designation 
g       NR      Miscellaneous information 
h       NR      Medium 
n       R       Number of part/section of a work 
p       R       Name of part/section of a work 
x       NR      International Standard Serial Number 
6       NR      Linkage 
8       R       Field link and sequence number 

250     R       EDITION STATEMENT
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Edition statement 
b       NR      Remainder of edition statement 
6       NR      Linkage 
8       R       Field link and sequence number 

254     NR      MUSICAL PRESENTATION STATEMENT
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Musical presentation statement 
6       NR      Linkage 
8       R       Field link and sequence number 

255     R       CARTOGRAPHIC MATHEMATICAL DATA
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Statement of scale 
b       NR      Statement of projection 
c       NR      Statement of coordinates 
d       NR      Statement of zone 
e       NR      Statement of equinox 
f       NR      Outer G-ring coordinate pairs 
g       NR      Exclusion G-ring coordinate pairs 
6       NR      Linkage 
8       R       Field link and sequence number 

256     NR      COMPUTER FILE CHARACTERISTICS
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Computer file characteristics 
6       NR      Linkage 
8       R       Field link and sequence number 

257     NR      COUNTRY OF PRODUCING ENTITY FOR ARCHIVAL FILMS
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Country of producing entity 
6       NR      Linkage 
8       R       Field link and sequence number 

258     R       PHILATELIC ISSUE DATE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Issuing jurisdiction
b       NR      Denomination
6       NR      Linkage
8       R       Field link and sequence number

260     R       PUBLICATION, DISTRIBUTION, ETC. (IMPRINT)
ind1    b23     Sequence of publishing statements
ind2    blank   Undefined
a       R       Place of publication, distribution, etc. 
b       R       Name of publisher, distributor, etc. 
c       R       Date of publication, distribution, etc. 
d       NR      Plate or publisher's number for music (Pre-AACR 2)
e       R       Place of manufacture 
f       R       Manufacturer 
g       R       Date of manufacture 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

261     NR      IMPRINT STATEMENT FOR FILMS (Pre-AACR 1 Revised)
ind1    blank   Undefined
ind2    blank   Undefined
a       R       Producing company 
b       R       Releasing company (primary distributor) 
d       R       Date of production, release, etc. 
e       R       Contractual producer 
f       R       Place of production, release, etc. 
6       NR      Linkage 
8       R       Field link and sequence number 

262     NR      IMPRINT STATEMENT FOR SOUND RECORDINGS (Pre-AACR 2)
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Place of production, release, etc. 
b       NR      Publisher or trade name 
c       NR      Date of production, release, etc. 
k       NR      Serial identification 
l       NR      Matrix and/or take number 
6       NR      Linkage 
8       R       Field link and sequence number 

263     NR      PROJECTED PUBLICATION DATE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Projected publication date 
6       NR      Linkage 
8       R       Field link and sequence number 

264     R       PUBLICATION, DISTRIBUTION, ETC. (IMPRINT) RDA
ind1    b23     Sequence of publishing statements
ind2    01234   Undefined
a       R       Place of publication, distribution, etc. 
b       R       Name of publisher, distributor, etc. 
c       R       Date of publication, distribution, etc. 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

270     R       ADDRESS
ind1    b12     Level
ind2    b07     Type of address
a       R       Address 
b       NR      City 
c       NR      State or province 
d       NR      Country 
e       NR      Postal code 
f       NR      Terms preceding attention name 
g       NR      Attention name 
h       NR      Attention position 
i       NR      Type of address 
j       R       Specialized telephone number 
k       R       Telephone number 
l       R       Fax number 
m       R       Electronic mail address 
n       R       TDD or TTY number 
p       R       Contact person 
q       R       Title of contact person 
r       R       Hours 
z       R       Public note 
4       R       Relator code 
6       NR      Linkage 
8       R       Field link and sequence number 

300     R       PHYSICAL DESCRIPTION
ind1    blank   Undefined
ind2    blank   Undefined
a       R       Extent 
b       NR      Other physical details 
c       R       Dimensions 
e       NR      Accompanying material 
f       R       Type of unit 
g       R       Size of unit 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

306     NR      PLAYING TIME
ind1    blank   Undefined
ind2    blank   Undefined
a       R       Playing time 
6       NR      Linkage 
8       R       Field link and sequence number 

307     R       HOURS, ETC.
ind1    b8      Display constant controller
ind2    blank   Undefined
a       NR      Hours 
b       NR      Additional information 
6       NR      Linkage 
8       R       Field link and sequence number 

310     NR      CURRENT PUBLICATION FREQUENCY
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Current publication frequency 
b       NR      Date of current publication frequency 
6       NR      Linkage 
8       R       Field link and sequence number 

321     R       FORMER PUBLICATION FREQUENCY
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Former publication frequency 
b       NR      Dates of former publication frequency 
6       NR      Linkage 
8       R       Field link and sequence number 

336     R       CONTENT TYPE (RDA)
ind1    blank   Undefined
ind2    blank   Undefined
a       R       Content type term
b       R       Content type code
2       NR      Source
3       NR      Materials specified
6       NR      Linkage
8       R       Field link and sequence number

337     R       MEDIA TYPE (RDA)
ind1    blank   Undefined
ind2    blank   Undefined
a       R       Media type term
b       R       Media type code
2       NR      Source
3       NR      Materials specified
6       NR      Linkage
8       R       Field link and sequence number

338     R       CARRIER TYPE (RDA)
ind1    blank   Undefined
ind2    blank   Undefined
a       R       Carrier type term
b       R       Carrier type code
2       NR      Source
3       NR      Materials specified
6       NR      Linkage
8       R       Field link and sequence number

340     R       PHYSICAL MEDIUM
ind1    blank   Undefined
ind2    blank   Undefined
a       R       Material base and configuration 
b       R       Dimensions 
c       R       Materials applied to surface 
d       R       Information recording technique 
e       R       Support 
f       R       Production rate/ratio 
h       R       Location within medium 
i       R       Technical specifications of medium 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

342     R       GEOSPATIAL REFERENCE DATA
ind1    01      Geospatial reference dimension
ind2    012345678    Geospatial reference method
a       NR      Name 
b       NR      Coordinate or distance units 
c       NR      Latitude resolution 
d       NR      Longitude resolution 
e       R       Standard parallel or oblique line latitude 
f       R       Oblique line longitude 
g       NR      Longitude of central meridian or projection center 
h       NR      Latitude of projection origin or projection center 
i       NR      False easting 
j       NR      False northing 
k       NR      Scale factor 
l       NR      Height of perspective point above surface 
m       NR      Azimuthal angle 
o       NR      Landsat number and path number 
p       NR      Zone identifier 
q       NR      Ellipsoid name 
r       NR      Semi-major axis 
s       NR      Denominator of flattening ratio 
t       NR      Vertical resolution 
u       NR      Vertical encoding method 
v       NR      Local planar, local, or other projection or grid description 
w       NR      Local planar or local georeference information 
2       NR      Reference method used 
6       NR      Linkage 
8       R       Field link and sequence number 

343     R       PLANAR COORDINATE DATA
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Planar coordinate encoding method 
b       NR      Planar distance units 
c       NR      Abscissa resolution 
d       NR      Ordinate resolution 
e       NR      Distance resolution 
f       NR      Bearing resolution 
g       NR      Bearing units 
h       NR      Bearing reference direction 
i       NR      Bearing reference meridian 
6       NR      Linkage 
8       R       Field link and sequence number 

351     R       ORGANIZATION AND ARRANGEMENT OF MATERIALS
ind1    blank   Undefined
ind2    blank   Undefined
a       R       Organization 
b       R       Arrangement 
c       NR      Hierarchical level 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

352     R       DIGITAL GRAPHIC REPRESENTATION
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Direct reference method 
b       R       Object type 
c       R       Object count 
d       NR      Row count 
e       NR      Column count 
f       NR      Vertical count 
g       NR      VPF topology level 
i       NR      Indirect reference description 
q       R       Format of the digital image 
6       NR      Linkage 
8       R       Field link and sequence number 

355     R       SECURITY CLASSIFICATION CONTROL
ind1    0123458    Controlled element
ind2    blank   Undefined
a       NR      Security classification 
b       R       Handling instructions 
c       R       External dissemination information 
d       NR      Downgrading or declassification event 
e       NR      Classification system 
f       NR      Country of origin code 
g       NR      Downgrading date 
h       NR      Declassification date 
j       R       Authorization 
6       NR      Linkage 
8       R       Field link and sequence number 

357     NR      ORIGINATOR DISSEMINATION CONTROL
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Originator control term 
b       R       Originating agency 
c       R       Authorized recipients of material 
g       R       Other restrictions 
6       NR      Linkage 
8       R       Field link and sequence number 

362     R       DATES OF PUBLICATION AND/OR SEQUENTIAL DESIGNATION
ind1    01      Format of date
ind2    blank   Undefined
a       NR      Dates of publication and/or sequential designation 
z       NR      Source of information 
6       NR      Linkage 
8       R       Field link and sequence number 

365     R       TRADE PRICE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Price type code 
b       NR      Price amount 
c       NR      Currency code 
d       NR      Unit of pricing 
e       NR      Price note 
f       NR      Price effective from 
g       NR      Price effective until 
h       NR      Tax rate 1 
i       NR      Tax rate 2 
j       NR      ISO country code 
k       NR      MARC country code 
m       NR      Identification of pricing entity 
2       NR      Source of price type code 
6       NR      Linkage 
8       R       Field link and sequence number 

366     R       TRADE AVAILABILITY INFORMATION
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Publishers' compressed title identification 
b       NR      Detailed date of publication 
c       NR      Availability status code 
d       NR      Expected next availability date 
e       NR      Note 
f       NR      Publishers' discount category 
g       NR      Date made out of print 
j       NR      ISO country code 
k       NR      MARC country code 
m       NR      Identification of agency 
2       NR      Source of availability status code 
6       NR      Linkage 
8       R       Field link and sequence number 

400     R       SERIES STATEMENT/ADDED ENTRY--PERSONAL NAME 
ind1    013     Type of personal name entry element
ind2    01      Pronoun represents main entry
a       NR      Personal name 
b       NR      Numeration 
c       R       Titles and other words associated with a name 
d       NR      Dates associated with a name 
e       R       Relator term 
f       NR      Date of a work 
g       NR      Miscellaneous information 
k       R       Form subheading 
l       NR      Language of a work 
n       R       Number of part/section of a work 
p       R       Name of part/section of a work 
t       NR      Title of a work 
u       NR      Affiliation 
v       NR      Volume number/sequential designation  
x       NR      International Standard Serial Number  
4       R       Relator code 
6       NR      Linkage 
8       R       Field link and sequence number  

410     R       SERIES STATEMENT/ADDED ENTRY--CORPORATE NAME
ind1    012     Type of corporate name entry element
ind2    01      Pronoun represents main entry
a       NR      Corporate name or jurisdiction name as entry element 
b       R       Subordinate unit 
c       NR      Location of meeting 
d       R       Date of meeting or treaty signing 
e       R       Relator term 
f       NR      Date of a work 
g       NR      Miscellaneous information 
k       R       Form subheading 
l       NR      Language of a work 
n       R       Number of part/section/meeting 
p       R       Name of part/section of a work 
t       NR      Title of a work 
u       NR      Affiliation 
v       NR      Volume number/sequential designation  
x       NR      International Standard Serial Number 
4       R       Relator code 
6       NR      Linkage 
8       R       Field link and sequence number 

411     R       SERIES STATEMENT/ADDED ENTRY--MEETING NAME
ind1    012     Type of meeting name entry element
ind2    01      Pronoun represents main entry
a       NR      Meeting name or jurisdiction name as entry element 
c       NR      Location of meeting 
d       NR      Date of meeting 
e       R       Subordinate unit 
f       NR      Date of a work 
g       NR      Miscellaneous information 
k       R       Form subheading 
l       NR      Language of a work 
n       R       Number of part/section/meeting 
p       R       Name of part/section of a work 
q       NR      Name of meeting following jurisdiction name entry element 
t       NR      Title of a work 
u       NR      Affiliation 
v       NR      Volume number/sequential designation  
x       NR      International Standard Serial Number 
4       R       Relator code 
6       NR      Linkage 
8       R       Field link and sequence number 

440     R       SERIES STATEMENT/ADDED ENTRY--TITLE
ind1    blank   Undefined
ind2    0-9     Nonfiling characters
a       NR      Title 
n       R       Number of part/section of a work 
p       R       Name of part/section of a work 
v       NR      Volume number/sequential designation  
x       NR      International Standard Serial Number 
6       NR      Linkage 
8       R       Field link and sequence number 

490     R       SERIES STATEMENT
ind1    01      Specifies whether series is traced
ind2    blank   Undefined
a       R       Series statement 
l       NR      Library of Congress call number 
v       R       Volume number/sequential designation  
x       NR      International Standard Serial Number 
6       NR      Linkage 
8       R       Field link and sequence number 

500     R       GENERAL NOTE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      General note 
3       NR      Materials specified 
5       NR      Institution to which field applies 
6       NR      Linkage 
8       R       Field link and sequence number 

501     R       WITH NOTE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      With note 
5       NR      Institution to which field applies 
6       NR      Linkage 
8       R       Field link and sequence number 

502     R       DISSERTATION NOTE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Dissertation note 
6       NR      Linkage 
8       R       Field link and sequence number 

504     R       BIBLIOGRAPHY, ETC. NOTE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Bibliography, etc. note 
b       NR      Number of references 
6       NR      Linkage 
8       R       Field link and sequence number 

505     R       FORMATTED CONTENTS NOTE
ind1    0128    Display constant controller
ind2    b0      Level of content designation
a       NR      Formatted contents note 
g       R       Miscellaneous information 
r       R       Statement of responsibility 
t       R       Title 
u       R       Uniform Resource Identifier 
6       NR      Linkage 
8       R       Field link and sequence number 

506     R       RESTRICTIONS ON ACCESS NOTE
ind1    b01     Undefined
ind2    blank   Undefined
a       NR      Terms governing access 
b       R       Jurisdiction 
c       R       Physical access provisions 
d       R       Authorized users 
e       R       Authorization 
u       R       Uniform Resource Identifier 
3       NR      Materials specified 
5       NR      Institution to which field applies 
6       NR      Linkage 
8       R       Field link and sequence number 

507     NR      SCALE NOTE FOR GRAPHIC MATERIAL
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Representative fraction of scale note 
b       NR      Remainder of scale note 
6       NR      Linkage 
8       R       Field link and sequence number 

508     R       CREATION/PRODUCTION CREDITS NOTE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Creation/production credits note 
6       NR      Linkage 
8       R       Field link and sequence number 

510     R       CITATION/REFERENCES NOTE
ind1    01234   Coverage/location in source
ind2    blank   Undefined
a       NR      Name of source 
b       NR      Coverage of source 
c       NR      Location within source 
x       NR      International Standard Serial Number 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

511     R       PARTICIPANT OR PERFORMER NOTE
ind1    01      Display constant controller
ind2    blank   Undefined
a       NR      Participant or performer note 
6       NR      Linkage 
8       R       Field link and sequence number 

513     R       TYPE OF REPORT AND PERIOD COVERED NOTE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Type of report 
b       NR      Period covered 
6       NR      Linkage 
8       R       Field link and sequence number 

514     NR      DATA QUALITY NOTE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Attribute accuracy report 
b       R       Attribute accuracy value 
c       R       Attribute accuracy explanation 
d       NR      Logical consistency report 
e       NR      Completeness report 
f       NR      Horizontal position accuracy report 
g       R       Horizontal position accuracy value 
h       R       Horizontal position accuracy explanation 
i       NR      Vertical positional accuracy report 
j       R       Vertical positional accuracy value 
k       R       Vertical positional accuracy explanation 
m       NR      Cloud cover 
u       R       Uniform Resource Identifier 
z       R       Display note 
6       NR      Linkage 
8       R       Field link and sequence number 

515     R       NUMBERING PECULIARITIES NOTE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Numbering peculiarities note 
6       NR      Linkage 
8       R       Field link and sequence number 

516     R       TYPE OF COMPUTER FILE OR DATA NOTE
ind1    b8      Display constant controller
ind2    blank   Undefined
a       NR      Type of computer file or data note 
6       NR      Linkage 
8       R       Field link and sequence number 

518     R       DATE/TIME AND PLACE OF AN EVENT NOTE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Date/time and place of an event note 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

520     R       SUMMARY, ETC.
ind1    b01238    Display constant controller
ind2    blank   Undefined
a       NR      Summary, etc. note 
b       NR      Expansion of summary note 
u       R       Uniform Resource Identifier 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

521     R       TARGET AUDIENCE NOTE
ind1    b012348    Display constant controller
ind2    blank   Undefined
a       R       Target audience note 
b       NR      Source 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

522     R       GEOGRAPHIC COVERAGE NOTE
ind1    b8      Display constant controller
ind2    blank   Undefined
a       NR      Geographic coverage note 
6       NR      Linkage 
8       R       Field link and sequence number 

524     R       PREFERRED CITATION OF DESCRIBED MATERIALS NOTE
ind1    b8      Display constant controller
ind2    blank   Undefined
a       NR      Preferred citation of described materials note 
2       NR      Source of schema used 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

525     R       SUPPLEMENT NOTE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Supplement note 
6       NR      Linkage 
8       R       Field link and sequence number 

526     R       STUDY PROGRAM INFORMATION NOTE
ind1    08      Display constant controller
ind2    blank   Undefined
a       NR      Program name 
b       NR      Interest level 
c       NR      Reading level 
d       NR      Title point value 
i       NR      Display text 
x       R       Nonpublic note 
z       R       Public note 
5       NR      Institution to which field applies 
6       NR      Linkage 
8       R       Field link and sequence number 

530     R       ADDITIONAL PHYSICAL FORM AVAILABLE NOTE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Additional physical form available note 
b       NR      Availability source 
c       NR      Availability conditions 
d       NR      Order number 
u       R       Uniform Resource Identifier 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

533     R       REPRODUCTION NOTE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Type of reproduction 
b       R       Place of reproduction 
c       R       Agency responsible for reproduction 
d       NR      Date of reproduction 
e       NR      Physical description of reproduction 
f       R       Series statement of reproduction 
m       R       Dates and/or sequential designation of issues reproduced 
n       R       Note about reproduction 
3       NR      Materials specified 
6       NR      Linkage 
7       NR      Fixed-length data elements of reproduction 
8       R       Field link and sequence number 

534     R       ORIGINAL VERSION NOTE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Main entry of original 
b       NR      Edition statement of original 
c       NR      Publication, distribution, etc. of original 
e       NR      Physical description, etc. of original 
f       R       Series statement of original 
k       R       Key title of original 
l       NR      Location of original 
m       NR      Material specific details 
n       R       Note about original 
p       NR      Introductory phrase 
t       NR      Title statement of original 
x       R       International Standard Serial Number 
z       R       International Standard Book Number 
6       NR      Linkage 
8       R       Field link and sequence number 

535     R       LOCATION OF ORIGINALS/DUPLICATES NOTE
ind1    12      Additional information about custodian
ind2    blank   Undefined
a       NR      Custodian 
b       R       Postal address 
c       R       Country 
d       R       Telecommunications address 
g       NR      Repository location code 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

536     R       FUNDING INFORMATION NOTE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Text of note 
b       R       Contract number 
c       R       Grant number 
d       R       Undifferentiated number 
e       R       Program element number 
f       R       Project number 
g       R       Task number 
h       R       Work unit number 
6       NR      Linkage 
8       R       Field link and sequence number 

538     R       SYSTEM DETAILS NOTE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      System details note 
i       NR      Display text 
u       R       Uniform Resource Identifier 
3       NR      Materials specified  
6       NR      Linkage 
8       R       Field link and sequence number 

540     R       TERMS GOVERNING USE AND REPRODUCTION NOTE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Terms governing use and reproduction 
b       NR      Jurisdiction 
c       NR      Authorization 
d       NR      Authorized users 
u       R       Uniform Resource Identifier 
3       NR      Materials specified 
5       NR      Institution to which field applies 
6       NR      Linkage 
8       R       Field link and sequence number 

541     R       IMMEDIATE SOURCE OF ACQUISITION NOTE
ind1    b01     Undefined
ind2    blank   Undefined
a       NR      Source of acquisition 
b       NR      Address 
c       NR      Method of acquisition 
d       NR      Date of acquisition 
e       NR      Accession number 
f       NR      Owner 
h       NR      Purchase price 
n       R       Extent 
o       R       Type of unit 
3       NR      Materials specified 
5       NR      Institution to which field applies 
6       NR      Linkage 
8       R       Field link and sequence number 

544     R       LOCATION OF OTHER ARCHIVAL MATERIALS NOTE
ind1    b01     Relationship
ind2    blank   Undefined
a       R       Custodian 
b       R       Address 
c       R       Country 
d       R       Title 
e       R       Provenance 
n       R       Note 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

545     R       BIOGRAPHICAL OR HISTORICAL DATA
ind1    b01     Type of data
ind2    blank   Undefined
a       NR      Biographical or historical note 
b       NR      Expansion 
u       R       Uniform Resource Identifier 
6       NR      Linkage 
8       R       Field link and sequence number 

546     R       LANGUAGE NOTE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Language note 
b       R       Information code or alphabet 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

547     R       FORMER TITLE COMPLEXITY NOTE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Former title complexity note 
6       NR      Linkage 
8       R       Field link and sequence number 

550     R       ISSUING BODY NOTE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Issuing body note 
6       NR      Linkage 
8       R       Field link and sequence number 

552     R       ENTITY AND ATTRIBUTE INFORMATION NOTE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Entity type label 
b       NR      Entity type definition and source 
c       NR      Attribute label 
d       NR      Attribute definition and source 
e       R       Enumerated domain value 
f       R       Enumerated domain value definition and source 
g       NR      Range domain minimum and maximum 
h       NR      Codeset name and source 
i       NR      Unrepresentable domain 
j       NR      Attribute units of measurement and resolution 
k       NR      Beginning date and ending date of attribute values 
l       NR      Attribute value accuracy 
m       NR      Attribute value accuracy explanation 
n       NR      Attribute measurement frequency 
o       R       Entity and attribute overview 
p       R       Entity and attribute detail citation 
u       R       Uniform Resource Identifier 
z       R       Display note 
6       NR      Linkage 
8       R       Field link and sequence number 

555     R       CUMULATIVE INDEX/FINDING AIDS NOTE
ind1    b08     Display constant controller
ind2    blank   Undefined
a       NR      Cumulative index/finding aids note 
b       R       Availability source 
c       NR      Degree of control 
d       NR      Bibliographic reference 
u       R       Uniform Resource Identifier 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

556     R       INFORMATION ABOUT DOCUMENTATION NOTE
ind1    b8      Display constant controller
ind2    blank   Undefined
a       NR      Information about documentation note 
z       R       International Standard Book Number 
6       NR      Linkage 
8       R       Field link and sequence number 

561     R       OWNERSHIP AND CUSTODIAL HISTORY
ind1    b01     Undefined
ind2    blank   Undefined
a       NR      History 
3       NR      Materials specified 
5       NR      Institution to which field applies 
6       NR      Linkage 
8       R       Field link and sequence number 

562     R       COPY AND VERSION IDENTIFICATION NOTE
ind1    blank   Undefined
ind2    blank   Undefined
a       R       Identifying markings 
b       R       Copy identification 
c       R       Version identification 
d       R       Presentation format 
e       R       Number of copies 
3       NR      Materials specified 
5       NR      Institution to which field applies 
6       NR      Linkage 
8       R       Field link and sequence number 

563     R       BINDING INFORMATION
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Binding note 
u       R       Uniform Resource Identifier 
3       NR      Materials specified 
5       NR      Institution to which field applies 
6       NR      Linkage 
8       R       Field link and sequence number 

565     R       CASE FILE CHARACTERISTICS NOTE
ind1    b08     Display constant controller
ind2    blank   Undefined
a       NR      Number of cases/variables 
b       R       Name of variable 
c       R       Unit of analysis 
d       R       Universe of data 
e       R       Filing scheme or code 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

567     R       METHODOLOGY NOTE
ind1    b8      Display constant controller
ind2    blank   Undefined
a       NR      Methodology note 
6       NR      Linkage 
8       R       Field link and sequence number 

580     R       LINKING ENTRY COMPLEXITY NOTE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Linking entry complexity note 
6       NR      Linkage 
8       R       Field link and sequence number 

581     R       PUBLICATIONS ABOUT DESCRIBED MATERIALS NOTE
ind1    b8      Display constant controller
ind2    blank   Undefined
a       NR      Publications about described materials note
z       R       International Standard Book Number 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

583     R       ACTION NOTE
ind1    b01     Undefined
ind2    blank   Undefined
a       NR      Action 
b       R       Action identification 
c       R       Time/date of action 
d       R       Action interval 
e       R       Contingency for action 
f       R       Authorization 
h       R       Jurisdiction 
i       R       Method of action 
j       R       Site of action 
k       R       Action agent 
l       R       Status 
n       R       Extent 
o       R       Type of unit 
u       R       Uniform Resource Identifier 
x       R       Nonpublic note 
z       R       Public note 
2       NR      Source of term 
3       NR      Materials specified 
5       NR      Institution to which field applies 
6       NR      Linkage 
8       R       Field link and sequence number 

584     R       ACCUMULATION AND FREQUENCY OF USE NOTE
ind1    blank   Undefined
ind2    blank   Undefined
a       R       Accumulation 
b       R       Frequency of use 
3       NR      Materials specified 
5       NR      Institution to which field applies 
6       NR      Linkage 
8       R       Field link and sequence number 

585     R       EXHIBITIONS NOTE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Exhibitions note 
3       NR      Materials specified 
5       NR      Institution to which field applies 
6       NR      Linkage 
8       R       Field link and sequence number 

586     R       AWARDS NOTE
ind1    b8      Display constant controller
ind2    blank   Undefined
a       NR      Awards note 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

600     R       SUBJECT ADDED ENTRY--PERSONAL NAME
ind1    013     Type of personal name entry element
ind2    01234567    Thesaurus
a       NR      Personal name 
b       NR      Numeration 
c       R       Titles and other words associated with a name
d       NR      Dates associated with a name 
e       R       Relator term 
f       NR      Date of a work 
g       NR      Miscellaneous information 
h       NR      Medium 
j       R       Attribution qualifier 
k       R       Form subheading 
l       NR      Language of a work 
m       R       Medium of performance for music 
n       R       Number of part/section of a work 
o       NR      Arranged statement for music 
p       R       Name of part/section of a work 
q       NR      Fuller form of name 
r       NR      Key for music 
s       NR      Version 
t       NR      Title of a work 
u       NR      Affiliation 
v       R       Form subdivision 
x       R       General subdivision 
y       R       Chronological subdivision 
z       R       Geographic subdivision 
2       NR      Source of heading or term 
3       NR      Materials specified 
4       R       Relator code 
6       NR      Linkage 
8       R       Field link and sequence number 

610     R       SUBJECT ADDED ENTRY--CORPORATE NAME
ind1    012     Type of corporate name entry element
ind2    01234567    Thesaurus
a       NR      Corporate name or jurisdiction name as entry element 
b       R       Subordinate unit 
c       NR      Location of meeting 
d       R       Date of meeting or treaty signing 
e       R       Relator term 
f       NR      Date of a work 
g       NR      Miscellaneous information 
h       NR      Medium 
k       R       Form subheading 
l       NR      Language of a work 
m       R       Medium of performance for music 
n       R       Number of part/section/meeting 
o       NR      Arranged statement for music 
p       R       Name of part/section of a work 
r       NR      Key for music 
s       NR      Version 
t       NR      Title of a work 
u       NR      Affiliation 
v       R       Form subdivision 
x       R       General subdivision 
y       R       Chronological subdivision 
z       R       Geographic subdivision 
2       NR      Source of heading or term 
3       NR      Materials specified 
4       R       Relator code 
6       NR      Linkage 
8       R       Field link and sequence number 

611     R       SUBJECT ADDED ENTRY--MEETING NAME
ind1    012     Type of meeting name entry element
ind2    01234567    Thesaurus
a       NR      Meeting name or jurisdiction name as entry element 
c       NR      Location of meeting 
d       NR      Date of meeting 
e       R       Subordinate unit 
f       NR      Date of a work 
g       NR      Miscellaneous information 
h       NR      Medium 
k       R       Form subheading 
l       NR      Language of a work 
n       R       Number of part/section/meeting 
p       R       Name of part/section of a work 
q       NR      Name of meeting following jurisdiction name entry element 
s       NR      Version 
t       NR      Title of a work 
u       NR      Affiliation 
v       R       Form subdivision 
x       R       General subdivision 
y       R       Chronological subdivision 
z       R       Geographic subdivision 
2       NR      Source of heading or term 
3       NR      Materials specified 
4       R       Relator code 
6       NR      Linkage 
8       R       Field link and sequence number 

630     R       SUBJECT ADDED ENTRY--UNIFORM TITLE
ind1    0       Nonfiling characters
ind2    01234567    Thesaurus
a       NR      Uniform title 
d       R       Date of treaty signing 
f       NR      Date of a work 
g       NR      Miscellaneous information 
h       NR      Medium 
k       R       Form subheading 
l       NR      Language of a work 
m       R       Medium of performance for music 
n       R       Number of part/section of a work 
o       NR      Arranged statement for music 
p       R       Name of part/section of a work 
r       NR      Key for music 
s       NR      Version 
t       NR      Title of a work 
v       R       Form subdivision 
x       R       General subdivision 
y       R       Chronological subdivision 
z       R       Geographic subdivision 
2       NR      Source of heading or term 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

648     R       SUBJECT ADDED ENTRY--CHRONOLOGICAL TERM
ind1    blank   Undefined
ind2    01234567    Thesaurus
a       NR      Chronological term 
v       R       Form subdivision 
x       R       General subdivision 
y       R       Chronological subdivision 
z       R       Geographic subdivision 
2       NR      Source of heading or term 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

650     R       SUBJECT ADDED ENTRY--TOPICAL TERM
ind1    b012    Level of subject
ind2    01234567    Thesaurus
a       NR      Topical term or geographic name as entry element 
b       NR      Topical term following geographic name as entry element 
c       NR      Location of event 
d       NR      Active dates 
e       NR      Relator term 
v       R       Form subdivision 
x       R       General subdivision 
y       R       Chronological subdivision 
z       R       Geographic subdivision 
2       NR      Source of heading or term 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

651     R       SUBJECT ADDED ENTRY--GEOGRAPHIC NAME
ind1    blank   Undefined
ind2    01234567    Thesaurus
a       NR      Geographic name 
v       R       Form subdivision 
x       R       General subdivision 
y       R       Chronological subdivision 
z       R       Geographic subdivision 
2       NR      Source of heading or term 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

653     R       INDEX TERM--UNCONTROLLED
ind1    b012    Level of index term
ind2    blank   Undefined
a       R       Uncontrolled term 
6       NR      Linkage 
8       R       Field link and sequence number 

654     R       SUBJECT ADDED ENTRY--FACETED TOPICAL TERMS
ind1    b012    Level of subject
ind2    blank   Undefined
a       R       Focus term 
b       R       Non-focus term 
c       R       Facet/hierarchy designation 
v       R       Form subdivision 
y       R       Chronological subdivision 
z       R       Geographic subdivision 
2       NR      Source of heading or term 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

655     R       INDEX TERM--GENRE/FORM
ind1    b0      Type of heading
ind2    01234567    Thesaurus
a       NR      Genre/form data or focus term 
b       R       Non-focus term 
c       R       Facet/hierarchy designation 
v       R       Form subdivision 
x       R       General subdivision 
y       R       Chronological subdivision 
z       R       Geographic subdivision 
2       NR      Source of term 
3       NR      Materials specified 
5       NR      Institution to which field applies 
6       NR      Linkage 
8       R       Field link and sequence number 

656     R       INDEX TERM--OCCUPATION
ind1    blank   Undefined
ind2    7       Source of term
a       NR      Occupation 
k       NR      Form 
v       R       Form subdivision 
x       R       General subdivision 
y       R       Chronological subdivision 
z       R       Geographic subdivision 
2       NR      Source of term 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

657     R       INDEX TERM--FUNCTION
ind1    blank   Undefined
ind2    7       Source of term
a       NR      Function 
v       R       Form subdivision 
x       R       General subdivision 
y       R       Chronological subdivision 
z       R       Geographic subdivision 
2       NR      Source of term 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

658     R       INDEX TERM--CURRICULUM OBJECTIVE
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Main curriculum objective 
b       R       Subordinate curriculum objective 
c       NR      Curriculum code 
d       NR      Correlation factor 
2       NR      Source of term or code 
6       NR      Linkage 
8       R       Field link and sequence number 

700     R       ADDED ENTRY--PERSONAL NAME
ind1    013     Type of personal name entry element
ind2    b2      Type of added entry
a       NR      Personal name 
b       NR      Numeration 
c       R       Titles and other words associated with a name 
d       NR      Dates associated with a name 
e       R       Relator term 
f       NR      Date of a work 
g       NR      Miscellaneous information 
h       NR      Medium 
j       R       Attribution qualifier 
k       R       Form subheading 
l       NR      Language of a work 
m       R       Medium of performance for music 
n       R       Number of part/section of a work 
o       NR      Arranged statement for music 
p       R       Name of part/section of a work 
q       NR      Fuller form of name 
r       NR      Key for music 
s       NR      Version 
t       NR      Title of a work 
u       NR      Affiliation 
x       NR      International Standard Serial Number 
3       NR      Materials specified 
4       R       Relator code 
5       NR      Institution to which field applies 
6       NR      Linkage 
8       R       Field link and sequence number 

710     R       ADDED ENTRY--CORPORATE NAME
ind1    012     Type of corporate name entry element
ind2    b2      Type of added entry
a       NR      Corporate name or jurisdiction name as entry element 
b       R       Subordinate unit 
c       NR      Location of meeting 
d       R       Date of meeting or treaty signing 
e       R       Relator term 
f       NR      Date of a work 
g       NR      Miscellaneous information 
h       NR      Medium 
k       R       Form subheading 
l       NR      Language of a work 
m       R       Medium of performance for music 
n       R       Number of part/section/meeting 
o       NR      Arranged statement for music 
p       R       Name of part/section of a work 
r       NR      Key for music 
s       NR      Version 
t       NR      Title of a work 
u       NR      Affiliation 
x       NR      International Standard Serial Number 
3       NR      Materials specified 
4       R       Relator code 
5       NR      Institution to which field applies 
6       NR      Linkage 
8       R       Field link and sequence number 

711     R       ADDED ENTRY--MEETING NAME
ind1    012     Type of meeting name entry element
ind2    b2      Type of added entry
a       NR      Meeting name or jurisdiction name as entry element 
c       NR      Location of meeting 
d       NR      Date of meeting 
e       R       Subordinate unit 
f       NR      Date of a work 
g       NR      Miscellaneous information 
h       NR      Medium 
k       R       Form subheading 
l       NR      Language of a work 
n       R       Number of part/section/meeting 
p       R       Name of part/section of a work 
q       NR      Name of meeting following jurisdiction name entry element 
s       NR      Version 
t       NR      Title of a work 
u       NR      Affiliation 
x       NR      International Standard Serial Number 
3       NR      Materials specified 
4       R       Relator code 
5       NR      Institution to which field applies 
6       NR      Linkage 
8       R       Field link and sequence number 

720     R       ADDED ENTRY--UNCONTROLLED NAME
ind1    b12     Type of name
ind2    blank   Undefined
a       NR      Name 
e       R       Relator term 
4       R       Relator code 
6       NR      Linkage 
8       R       Field link and sequence number 

730     R       ADDED ENTRY--UNIFORM TITLE
ind1    0       Nonfiling characters
ind2    b2      Type of added entry
a       NR      Uniform title 
d       R       Date of treaty signing 
f       NR      Date of a work 
g       NR      Miscellaneous information 
h       NR      Medium 
k       R       Form subheading 
l       NR      Language of a work 
m       R       Medium of performance for music 
n       R       Number of part/section of a work 
o       NR      Arranged statement for music 
p       R       Name of part/section of a work 
r       NR      Key for music 
s       NR      Version 
t       NR      Title of a work 
x       NR      International Standard Serial Number 
3       NR      Materials specified 
5       NR      Institution to which field applies 
6       NR      Linkage 
8       R       Field link and sequence number 

740     R       ADDED ENTRY--UNCONTROLLED RELATED/ANALYTICAL TITLE
ind1    0-9     Nonfiling characters
ind2    b2      Type of added entry
a       NR      Uncontrolled related/analytical title 
h       NR      Medium 
n       R       Number of part/section of a work 
p       R       Name of part/section of a work 
5       NR      Institution to which field applies 
6       NR      Linkage 
8       R       Field link and sequence number 

752     R       ADDED ENTRY--HIERARCHICAL PLACE NAME
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Country 
b       NR      State, province, territory 
c       NR      County, region, islands area 
d       NR      City 
6       NR      Linkage 
8       R       Field link and sequence number 

753     R       SYSTEM DETAILS ACCESS TO COMPUTER FILES
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Make and model of machine 
b       NR      Programming language 
c       NR      Operating system 
6       NR      Linkage 
8       R       Field link and sequence number 

754     R       ADDED ENTRY--TAXONOMIC IDENTIFICATION
ind1    blank   Undefined
ind2    blank   Undefined
a       R       Taxonomic name 
c       R       Taxonomic category 
d       R       Common or alternative name 
x       R       Non-public note 
z       R       Public note 
2       NR      Source of taxonomic identification 
6       NR      Linkage 
8       R       Field link and sequence number 

760     R       MAIN SERIES ENTRY
ind1    01      Note controller
ind2    b8      Display constant controller
a       NR      Main entry heading 
b       NR      Edition 
c       NR      Qualifying information 
d       NR      Place, publisher, and date of publication 
g       R       Relationship information 
h       NR      Physical description 
i       NR      Display text 
m       NR      Material-specific details 
n       R       Note 
o       R       Other item identifier 
s       NR      Uniform title 
t       NR      Title 
w       R       Record control number 
x       NR      International Standard Serial Number 
y       NR      CODEN designation 
6       NR      Linkage 
7       NR      Control subfield 
8       R       Field link and sequence number 

762     R       SUBSERIES ENTRY
ind1    01      Note controller
ind2    b8      Display constant controller
a       NR      Main entry heading 
b       NR      Edition 
c       NR      Qualifying information 
d       NR      Place, publisher, and date of publication 
g       R       Relationship information 
h       NR      Physical description 
i       NR      Display text 
m       NR      Material-specific details 
n       R       Note 
o       R       Other item identifier 
s       NR      Uniform title 
t       NR      Title 
w       R       Record control number 
x       NR      International Standard Serial Number 
y       NR      CODEN designation 
6       NR      Linkage 
7       NR      Control subfield 
8       R       Field link and sequence number 

765     R       ORIGINAL LANGUAGE ENTRY
ind1    01      Note controller
ind2    b8      Display constant controller
a       NR      Main entry heading 
b       NR      Edition 
c       NR      Qualifying information 
d       NR      Place, publisher, and date of publication 
g       R       Relationship information 
h       NR      Physical description 
i       NR      Display text 
k       R       Series data for related item 
m       NR      Material-specific details 
n       R       Note 
o       R       Other item identifier 
r       R       Report number 
s       NR      Uniform title 
t       NR      Title 
u       NR      Standard Technical Report Number 
w       R       Record control number 
x       NR      International Standard Serial Number 
y       NR      CODEN designation 
z       R       International Standard Book Number 
6       NR      Linkage 
7       NR      Control subfield 
8       R       Field link and sequence number 

767     R       TRANSLATION ENTRY
ind1    01      Note controller
ind2    b8      Display constant controller
a       NR      Main entry heading 
b       NR      Edition 
c       NR      Qualifying information 
d       NR      Place, publisher, and date of publication 
g       R       Relationship information 
h       NR      Physical description 
i       NR      Display text 
k       R       Series data for related item 
m       NR      Material-specific details 
n       R       Note 
o       R       Other item identifier 
r       R       Report number 
s       NR      Uniform title 
t       NR      Title 
u       NR      Standard Technical Report Number 
w       R       Record control number 
x       NR      International Standard Serial Number 
y       NR      CODEN designation 
z       R       International Standard Book Number 
6       NR      Linkage 
7       NR      Control subfield 
8       R       Field link and sequence number 

770     R       SUPPLEMENT/SPECIAL ISSUE ENTRY
ind1    01      Note controller
ind2    b8      Display constant controller
a       NR      Main entry heading 
b       NR      Edition 
c       NR      Qualifying information 
d       NR      Place, publisher, and date of publication 
g       R       Relationship information 
h       NR      Physical description 
i       NR      Display text 
k       R       Series data for related item 
m       NR      Material-specific details 
n       R       Note 
o       R       Other item identifier 
r       R       Report number 
s       NR      Uniform title 
t       NR      Title 
u       NR      Standard Technical Report Number 
w       R       Record control number 
x       NR      International Standard Serial Number 
y       NR      CODEN designation 
z       R       International Standard Book Number 
6       NR      Linkage 
7       NR      Control subfield 
8       R       Field link and sequence number 

772     R       SUPPLEMENT PARENT ENTRY
ind1    01      Note controller
ind2    b08     Display constant controller
a       NR      Main entry heading 
b       NR      Edition 
c       NR      Qualifying information 
d       NR      Place, publisher, and date of publication 
g       R       Relationship information 
h       NR      Physical description 
i       NR      Display text 
k       R       Series data for related item 
m       NR      Material-specific details 
n       R       Note 
o       R       Other item identifier 
r       R       Report number 
s       NR      Uniform title 
t       NR      Title 
u       NR      Standard Technical Report Number 
w       R       Record control number 
x       NR      International Standard Serial Number 
y       NR      CODEN designation 
z       R       International Stan dard Book Number 
6       NR      Linkage 
7       NR      Control subfield 
8       R       Field link and sequence number 

773     R       HOST ITEM ENTRY
ind1    01      Note controller
ind2    b8      Display constant controller
a       NR      Main entry heading 
b       NR      Edition 
d       NR      Place, publisher, and date of publication 
g       R       Relationship information 
h       NR      Physical description 
i       NR      Display text 
k       R       Series data for related item 
m       NR      Material-specific details 
n       R       Note 
o       R       Other item identifier 
p       NR      Abbreviated title 
q       NR      Enumeration and first page 
r       R       Report number 
s       NR      Uniform title 
t       NR      Title 
u       NR      Standard Technical Report Number 
w       R       Record control number 
x       NR      International Standard Serial Number 
y       NR      CODEN designation 
z       R       International Standard Book Number 
3       NR      Materials specified 
6       NR      Linkage 
7       NR      Control subfield 
8       R       Field link and sequence number 

774     R       CONSTITUENT UNIT ENTRY
ind1    01      Note controller
ind2    b8      Display constant controller
a       NR      Main entry heading 
b       NR      Edition 
c       NR      Qualifying information 
d       NR      Place, publisher, and date of publication 
g       R       Relationship information 
h       NR      Physical description 
i       NR      Display text 
k       R       Series data for related item 
m       NR      Material-specific details 
n       R       Note 
o       R       Other item identifier 
r       R       Report number 
s       NR      Uniform title 
t       NR      Title 
u       NR      Standard Technical Report Number 
w       R       Record control number 
x       NR      International Standard Serial Number 
y       NR      CODEN designation 
z       R       International Standard Book Number 
6       NR      Linkage 
7       NR      Control subfield 
8       R       Field link and sequence number 

775     R       OTHER EDITION ENTRY
ind1    01      Note controller
ind2    b8      Display constant controller
a       NR      Main entry heading 
b       NR      Edition 
c       NR      Qualifying information 
d       NR      Place, publisher, and date of publication 
e       NR      Language code 
f       NR      Country code 
g       R       Relationship information 
h       NR      Physical description 
i       NR      Display text 
k       R       Series data for related item 
m       NR      Material-specific details 
n       R       Note 
o       R       Other item identifier 
r       R       Report number 
s       NR      Uniform title 
t       NR      Title 
u       NR      Standard Technical Report Number 
w       R       Record control number 
x       NR      International Standard Serial Number 
y       NR      CODEN designation 
z       R       International Standard Book Number 
6       NR      Linkage 
7       NR      Control subfield 
8       R       Field link and sequence number 

776     R       ADDITIONAL PHYSICAL FORM ENTRY
ind1    01      Note controller
ind2    b8      Display constant controller
a       NR      Main entry heading 
b       NR      Edition 
c       NR      Qualifying information 
d       NR      Place, publisher, and date of publication 
g       R       Relationship information 
h       NR      Physical description 
i       NR      Display text 
k       R       Series data for related item 
m       NR      Material-specific details 
n       R       Note 
o       R       Other item identifier 
r       R       Report number 
s       NR      Uniform title 
t       NR      Title 
u       NR      Standard Technical Report Number 
w       R       Record control number 
x       NR      International Standard Serial Number 
y       NR      CODEN designation 
z       R       International Standard Book Number 
6       NR      Linkage 
7       NR      Control subfield 
8       R       Field link and sequence number 

777     R       ISSUED WITH ENTRY
ind1    01      Note controller
ind2    b8      Display constant controller
a       NR      Main entry heading 
b       NR      Edition 
c       NR      Qualifying information 
d       NR      Place, publisher, and date of publication 
g       R       Relationship information 
h       NR      Physical description 
i       NR      Display text 
k       R       Series data for related item 
m       NR      Material-specific details 
n       R       Note 
o       R       Other item identifier 
s       NR      Uniform title 
t       NR      Title 
w       R       Record control number 
x       NR      International Standard Serial Number 
y       NR      CODEN designation 
6       NR      Linkage 
7       NR      Control subfield 
8       R       Field link and sequence number 

780     R       PRECEDING ENTRY
ind1    01      Note controller
ind2    01234567    Type of relationship
a       NR      Main entry heading 
b       NR      Edition 
c       NR      Qualifying information 
d       NR      Place, publisher, and date of publication 
g       R       Relationship information 
h       NR      Physical description 
i       NR      Display text 
k       R       Series data for related item 
m       NR      Material-specific details 
n       R       Note 
o       R       Other item identifier 
r       R       Report number 
s       NR      Uniform title 
t       NR      Title 
u       NR      Standard Technical Report Number 
w       R       Record control number 
x       NR      International Standard Serial Number 
y       NR      CODEN designation 
z       R       International Standard Book Number 
6       NR      Linkage 
7       NR      Control subfield 
8       R       Field link and sequence number 

785     R       SUCCEEDING ENTRY
ind1    01      Note controller
ind2    012345678    Type of relationship
a       NR      Main entry heading 
b       NR      Edition 
c       NR      Qualifying information 
d       NR      Place, publisher, and date of publication 
g       R       Relationship information 
h       NR      Physical description 
i       NR      Display text 
k       R       Series data for related item 
m       NR      Material-specific details 
n       R       Note 
o       R       Other item identifier 
r       R       Report number 
s       NR      Uniform title 
t       NR      Title 
u       NR      Standa rd Technical Report Number 
w       R       Record control number 
x       NR      International Standard Serial Number 
y       NR      CODEN designation 
z       R       International Standard Book Number 
6       NR      Linkage 
7       NR      Control subfield 
8       R       Field link and sequence number 

786     R       DATA SOURCE ENTRY
ind1    01      Note controller
ind2    b8      Display constant controller
a       NR      Main entry heading 
b       NR      Edition 
c       NR      Qualifying information 
d       NR      Place, publisher, and date of publication 
g       R       Relationship information 
h       NR      Physical description 
i       NR      Display text 
j       NR      Period of content 
k       R       Series data for related item 
m       NR      Material-specific details 
n       R       Note 
o       R       Other item identifier 
p       NR      Abbreviated title 
r       R       Report number 
s       NR      Uniform title 
t       NR      Title 
u       NR      Standard Technical Report Number 
v       NR      Source Contribution 
w       R       Record control number 
x       NR      International Standard Serial Number 
y       NR      CODEN designation 
z       R       International Standard Book Number 
6       NR      Linkage 
7       NR      Control subfield 
8       R       Field link and sequence number 

787     R       NONSPECIFIC RELATIONSHIP ENTRY
ind1    01      Note controller
ind2    b8      Display constant controller
a       NR      Main entry heading 
b       NR      Edition 
c       NR      Qualifying information 
d       NR      Place, publisher, and date of publication 
g       R       Relationship information 
h       NR      Physical description 
i       NR      Display text 
k       R       Series data for related item 
m       NR      Material-specific details 
n       R       Note 
o       R       Other item identifier 
r       R       Report number 
s       NR      Uniform title 
t       NR      Title 
u       NR      Standard Technical Report Number 
w       R       Record control number 
x       NR      International Standard Serial Number 
y       NR      CODEN designation 
z       R       International Standard Book Number 
6       NR      Linkage 
7       NR      Control subfield 
8       R       Field link and sequence number 

800     R       SERIES ADDED ENTRY--PERSONAL NAME
ind1    013     Type of personal name entry element
ind2    blank   Undefined
a       NR      Personal name 
b       NR      Numeration 
c       R       Titles and other words associated with a name 
d       NR      Dates associated with a name 
e       R       Relator term 
f       NR      Date of a work 
g       NR      Miscellaneous information 
h       NR      Medium 
j       R       Attribution qualifier 
k       R       Form subheading 
l       NR      Language of a work 
m       R       Medium of performance for music 
n       R       Number of part/section of a work  
o       NR      Arranged statement for music 
p       R       Name of part/section of a work 
q       NR      Fuller form of name 
r       NR      Key for music 
s       NR      Version 
t       NR      Title of a work 
u       NR      Affiliation 
v       NR      Volume/sequential designation  
4       R       Relator code 
6       NR      Linkage 
8       R       Field link and sequence number 

810     R       SERIES ADDED ENTRY--CORPORATE NAME
ind1    012     Type of corporate name entry element
ind2    blank   Undefined
a       NR      Corporate name or jurisdiction name as entry element 
b       R       Subordinate unit 
c       NR      Location of meeting 
d       R       Date of meeting or treaty signing 
e       R       Relator term 
f       NR      Date of a work 
g       NR      Miscellaneous information 
h       NR      Medium 
k       R       Form subheading 
l       NR      Language of a work 
m       R       Medium of performance for music 
n       R       Number of part/section/meeting 
o       NR      Arranged statement for music 
p       R       Name of part/section of a work 
r       NR      Key for music 
s       NR      Version 
t       NR      Title of a work 
u       NR      Affiliation 
v       NR      Volume/sequential designation 
4       R       Relator code 
6       NR      Linkage 
8       R       Field link and sequence number 

811     R       SERIES ADDED ENTRY--MEETING NAME
ind1    012     Type of meeting name entry element
ind2    blank   Undefined
a       NR      Meeting name or jurisdiction name as entry element 
c       NR      Location of meeting 
d       NR      Date of meeting 
e       R       Subordinate unit 
f       NR      Date of a work 
g       NR      Miscellaneous information 
h       NR      Medium 
k       R       Form subheading 
l       NR      Language of a work 
n       R       Number of part/section/meeting 
p       R       Name of part/section of a work 
q       NR      Name of meeting following jurisdiction name entry element 
s       NR      Version 
t       NR      Title of a work 
u       NR      Affiliation 
v       NR      Volume/sequential designation 
4       R       Relator code 
6       NR      Linkage 
8       R       Field link and sequence number 

830     R       SERIES ADDED ENTRY--UNIFORM TITLE
ind1    blank   Undefined
ind2    0       Nonfiling characters
a       NR      Uniform title 
d       R       Date of treaty signing 
f       NR      Date of a work 
g       NR      Miscellaneous information 
h       NR      Medium 
k       R       Form subheading 
l       NR      Language of a work 
m       R       Medium of performance for music 
n       R       Number of part/section of a work 
o       NR      Arranged statement for music 
p       R       Name of part/section of a work 
r       NR      Key for music 
s       NR      Version 
t       NR      Title of a work 
v       NR      Volume/sequential designation
5       NR      Institution to which field applies 
6       NR      Linkage 
8       R       Field link and sequence number 

841     NR      HOLDINGS CODED DATA VALUES

842     NR      TEXTUAL PHYSICAL FORM DESIGNATOR

843     R       REPRODUCTION NOTE

844     NR      NAME OF UNIT

845     R       TERMS GOVERNING USE AND REPRODUCTION NOTE

850     R       HOLDING INSTITUTION
ind1    blank   Undefined
ind2    blank   Undefined
a       R       Holding institution 
8       R       Field link and sequence number 

852     R       LOCATION
ind1    b012345678    Shelving scheme
ind2    b012    Shelving order
a       NR      Location 
b       R       Sublocation or collection 
c       R       Shelving location 
e       R       Address 
f       R       Coded location qualifier 
g       R       Non-coded location qualifier 
h       NR      Classification part 
i       R       Item part 
j       NR      Shelving control number 
k       R       Call number prefix 
l       NR      Shelving form of title 
m       R       Call number suffix 
n       NR      Country code 
p       NR      Piece designation 
q       NR      Piece physical condition 
s       R       Copyright article-fee code 
t       NR      Copy number 
x       R       Nonpublic note 
z       R       Public note 
2       NR      Source of classification or shelving scheme 
3       NR      Materials specified 
6       NR      Linkage 
8       NR      Sequence number 

853     R       CAPTIONS AND PATTERN--BASIC BIBLIOGRAPHIC UNIT

854     R       CAPTIONS AND PATTERN--SUPPLEMENTARY MATERIAL

855     R       CAPTIONS AND PATTERN--INDEXES

856     R       ELECTRONIC LOCATION AND ACCESS
ind1    b012347    Access method
ind2    b0128   Relationship
a       R       Host name 
b       R       Access number 
c       R       Compression information 
d       R       Path 
f       R       Electronic name 
h       NR      Processor of request 
i       R       Instruction 
j       NR      Bits per second 
k       NR      Password 
l       NR      Logon 
m       R       Contact for access assistance 
n       NR      Name of location of host 
o       NR      Operating system 
p       NR      Port 
q       NR      Electronic format type 
r       NR      Settings 
s       R       File size 
t       R       Terminal emulation 
u       R       Uniform Resource Identifier 
v       R       Hours access method available 
w       R       Record control number 
x       R       Nonpublic note 
y       R       Link text 
z       R       Public note 
2       NR      Access method 
3       NR      Materials specified 
6       NR      Linkage 
8       R       Field link and sequence number 

863     R       ENUMERATION AND CHRONOLOGY--BASIC BIBLIOGRAPHIC UNIT

864     R       ENUMERATION AND CHRONOLOGY--SUPPLEMENTARY MATERIAL

865     R       ENUMERATION AND CHRONOLOGY--INDEXES

866     R       TEXTUAL HOLDINGS--BASIC BIBLIOGRAPHIC UNIT

867     R       TEXTUAL HOLDINGS--SUPPLEMENTARY MATERIAL

868     R       TEXTUAL HOLDINGS--INDEXES

876     R       ITEM INFORMATION--BASIC BIBLIOGRAPHIC UNIT

877     R       ITEM INFORMATION--SUPPLEMENTARY MATERIAL

878     R       ITEM INFORMATION--INDEXES

#880     R       ALTERNATE GRAPHIC REPRESENTATION
#ind1            Same as associated field
#ind2            Same as associated field
#6       NR      Linkage

886     R       FOREIGN MARC INFORMATION FIELD
ind1    012     Type of field
ind2    blank   Undefined
a       NR      Tag of the foreign MARC field
b       NR      Content of the foreign MARC field
c-z     NR      Foreign MARC subfield
0-1     NR      Foreign MARC subfield
2       NR      Source of data
4       NR      Source of data
3-9     NR      Source of data

887     R       NON-MARC INFORMATION FIELD
ind1    blank   Undefined
ind2    blank   Undefined
a       NR      Content of non-MARC field
2       NR      Source of data







