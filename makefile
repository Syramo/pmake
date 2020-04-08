#
# sample makefile to build pmake
#
# lines that start with # are ignored
# targets don't specify dependencies, but can specify an output file name if required
# by adding > [filename], if an output file is required and non is given, the target name is used
#
# pmake will build the first target if none specified
#
# only the following special macros are defined
# $@ ... the current target name
# $& ... the basename (filename with suffix, not including the path) of the current file
# $? ... the filename (without suffix, not including the path)
# $!U .. current user
# $!G .. current group
#
# other macros can be defined at any place starting at the first column in form of NAME = value
# they must be all upper case!
#
# commands:
#
# PM ... make another target of the same file (used in make all mostly)
#
# OA ... open archive with a filename given
# AS ... archive php code into the phar archive with syntax checking and stripping
# AD ... archive any file verbatim (no syntax checking, no stripping)
# PS ... set a define for a path inside the phar
# DF ... sets an arbitrary define inside the phar
# CA ... write stub and close archive
# CA! .. write executable stub and close archive
#
# IN .. install, 
#
# NOTE: for any archive targets OA needs to be the first command and CA(!) the last
#
# other commands
#
# SE ... shell execute executes a shell command verbatim
# ER ... enforce root (if no root print message)
# PR ... prints info on the screen

PROD = pmake
DESC = "pmake, a tool to process makefiles and build phar archives"

all:
	PR building pmake ...
	PM pmake
	
info:
	PR {$PROD}
	PR "{$DESC}"
	
pmake:
	OA $@.phar
	AS *.php > $&
	DF DEBUG = 15
	CA! $@.php
	
install:
	ER "you must run pmake $@ as root!"
	IN root:root 755 *.phar > /opt/phpdev/bin/$?
	
clean:
	SE rm -f *.phar
	
 
