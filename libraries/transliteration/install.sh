# Install local copy of Lingua Translit; run from this directory
if [ ! -f ./bin/translit ] ; then
	
	# Stop on errors
	set -e
	
	#sudo apt-get install libxml2-utils
	
	mkdir cpan
	cd cpan/
	cp -pr ../Lingua-Translit-0.22.tar.gz .
	#wget http://www.cpan.org/authors/id/A/AL/ALINKE/Lingua-Translit-0.22.tar.gz
	tar zxvf Lingua-Translit-*.tar.gz
	cd Lingua-Translit*
	perl Makefile.PL PREFIX=../
	make
	make test
	make install
	
	cd ../../
	#sudo chown -R www-data cpan
	#sudo chmod g+s cpan
	
	# Workaround: The "perl Makefile.PL" installation seems not to make use of the local Tables.pm; so for now, a symlink has been created to an earlier system installation
	#mv /usr/local/share/perl/5.18.2/Lingua/Translit/Tables.pm /usr/local/share/perl/5.18.2/Lingua/Translit/Tables.pm.old
	#ln -s /path/to/libraries/transliteration/cpan/Lingua-Translit-0.22/blib/lib/Lingua/Translit/Tables.pm /usr/local/share/perl/5.18.2/Lingua/Translit/
	
fi
