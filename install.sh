#!/bin/bash

# SPRI library catalogue Muscat conversion - installation of software dependencies



# Ensure this script is run as root
if [ "$(id -u)" != "0" ]; then
    echo "#	This script must be run as root." 1>&2
    exit 1
fi

# Bomb out if something goes wrong
set -e

# Get the script directory see: https://stackoverflow.com/a/246128/180733
# The multi-line method of geting the script directory is needed to enable the script to be called from elsewhere.
SOURCE="${BASH_SOURCE[0]}"
DIR="$( dirname "$SOURCE" )"
while [ -h "$SOURCE" ]
do
	SOURCE="$(readlink "$SOURCE")"
	[[ $SOURCE != /* ]] && SOURCE="$DIR/$SOURCE"
	DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"
done
DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"
SCRIPTDIRECTORY=$DIR


# Install local copy of Lingua Translit CPAN module (executable)
# See: http://stackoverflow.com/a/21911478/180733 for local CPAN installation technique
apt-get install libxml2-utils
if [ ! -f "${SCRIPTDIRECTORY}/libraries/transliteration/cpan/bin/translit" ] ; then
	cd "${SCRIPTDIRECTORY}/libraries/transliteration/"
	mkdir cpan
	cd cpan/
	wget https://search.cpan.org/CPAN/authors/id/A/AL/ALINKE/Lingua-Translit-0.22.tar.gz
	tar zxvf Lingua-Translit-0.22.tar.gz
	cd Lingua-Translit-0.22/
	perl Makefile.PL PREFIX=../
	make
	make test
	make install
	cd "${SCRIPTDIRECTORY}"
	
	#sudo chown -R www-data cpan
	#sudo chmod g+s cpan
	
	# Workaround: The "perl Makefile.PL" installation seems not to make use of the local Tables.pm; so for now, a symlink has been created to an earlier system installation
	#mv /usr/local/share/perl/5.18.2/Lingua/Translit/Tables.pm /usr/local/share/perl/5.18.2/Lingua/Translit/Tables.pm.old
	#ln -s /path/to/libraries/transliteration/cpan/Lingua-Translit-0.22/blib/lib/Lingua/Translit/Tables.pm /usr/local/share/perl/5.18.2/Lingua/Translit/
fi

# Also install Enchant, for spell-checking
apt-get -y install php-enchant aspell aspell-ru

# MarcEdit via MONO; see: https://marcedit.reeset.net/marcedit-linux-installation-instructions
# See also: http://blog.reeset.net/archives/946 and http://blog.reeset.net/archives/805
apt-get -y install mono-complete
apt-get -y install mono-runtime
apt-get -y install libyaz4-dev
apt-get -y install libxml2
#service apache2 restart	# To catch libxml2
if [ ! -d "/usr/local/bin/marcedit" ]; then
	wget -P /tmp/ http://marcedit.reeset.net/software/marcedit.bin.zip
	unzip /tmp/marcedit.bin.zip -d /tmp/
	rm /tmp/marcedit.bin.zip
	mv /tmp/marcedit /usr/local/bin/
	chown -R root.root /usr/local/bin/marcedit/
	chmod -R 775 /usr/local/bin/marcedit/
fi
mono /usr/local/bin/marcedit/linux_bootloader.exe
# Check using `cd /usr/local/bin/marcedit/ ; mono cmarcedit.exe`

# Bibcheck lint checker
export PERL_MM_USE_DEFAULT=1
cpan install Encode
cpan install MARC::Record
cpan install MARC::Batch
cpan install XML::LibXML	# Lingua Translit
export PERL_MM_USE_DEFAULT=0
apt-get install dos2unix
dos2unix libraries/bibcheck/lint_test.pl
