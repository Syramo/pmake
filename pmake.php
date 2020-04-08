<?php

function msg_out($msg,$c=0)
{
	$f = "\e[1;32mINFO:\e[0m";
	if ($c > 0) $f = "\e[1;33mWARNING:\e[0m"; else
	if ($c < 0) $f = "\e[1;31mERROR:\e[0m";
	printf("%-20.20s%s",$f,trim($msg)."\n");
}

require_once (__DIR__.'/parser.php');
require_once (__DIR__.'/archiver.php');
require_once (__DIR__.'/exec.php');

$opt = getopt('hr');

if (isset($opt['h']))
{
	print ("\n\e[1mUsage: pmake [-h] [<target>]\e[0m\n\n");
	print ("pmake will use the makefile or pmakefile in the current directory and will execute the first target in that file.\n");
	print ("Other targets can optinally specified.\n");
	print ("For pmake to work make sure you have phar.readonly = Off in your php.ini file.\n\n");
	print ("\e[3mOptions:\e[0m\n");
	print ("-h\tprints this very information\n\n");
	exit(0).
}

$mfs = ['makefile','pmakefile'];
$mfile = '';
foreach ($mfs as $mf)
{
	if (file_exists($mf)) $mfile = $mf;
}
if ($mfile=='') {
	print ("\e[1;31mERROR\e[0m Can not open makefile\e[0m\n");
	exit(-1);
}


$p = new Parser($mfile);
$p->parse();

if ($p->error) {
	printf("\e[1;31mMAKEFILE ERROR:\e[0m Line %d => %s\n",$p->line, $p->msg);
	exit(-1);
}


$make = $p->get_make();

$tgt = $argv[count($opt)+1]??array_keys($make['TGT'])[0]??'';

if ($tgt=='') {
	print ("\e[1;31mERROR:\e[0m no target to make found \e[0m\n");
	exit(-1);
}

unset($p);




$m = new ExecMake($make);
$m->make($tgt);

printf("\e[3;33mpmake done!\e[0m %s\n",$m->error?"\e[1;31m:-(\e[0m":"\e[1;32m:-)\e[0m");

