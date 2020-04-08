<?php

class Archiver
{
	public $name;
	public $error;

	private $ph;
	private $prop;

	function __construct($name)
	{
		if (basename($name,'.phar') == basename($name)) { // phar archives need to have .phar suffix during build time
			$name = $name.'.phar';
		}
		$this->name = $name;
		$this->error = false;
		$this->prop = [];
		if (Phar::canWrite() == false) {
			msg_out("write option of PHAR disabled, check php.ini",-1);
			$this->error = true;
		}
		try
		{
			if (file_exists($name)) {
				unlink($name);
			}
			$this->ph = new Phar(getcwd()."/$name",0);
		}
		catch (Exception $e)
		{
			msg_out("could not open archive {$name} for writing",-1);
			$this->error = true;
		}
	}


	function close_archive($run=false,$start=null)
	{
		$stub  = $run?"#!/usr/bin/php \n<?php ":"<?php ";
		$stub .= "Phar::mapPhar('{$this->name}'); ";

		foreach ($this->prop as $prop) {
			$stub .= $prop;
		}

		if ($start != null) {
			$stub .= "include 'phar://{$this->name}/{$start}'; ";
		}

		$stub .= "__HALT_COMPILER();";
		$this->ph->setStub($stub);
	}


	function add_file($src,$dst,$code)
	{
		try
		{
			$cont = '';
			if ($code)
			{
				$res = exec("php -l {$src}");
				if (substr($res,0,25) != "No syntax errors detected")
				{
					msg_out("'{$src}' has syntax errors",-1);
					$this->error = true;
					return;
				}
				$cont =  php_strip_whitespace($src);
			}
			else {
				$cont = file_get_contents($src);
			}
			$this->ph->addFromString($dst,$cont);
		}
		catch (Exception $e)
		{
			msg_out("'{$src}' could not be added to archive",-1);
			$this->error = true;
		}
	}


	function add_property($key,$val,$path)
	{
		if ($path)
		{
			if (is_string($val) && ($l=strlen($val)) > 1
			    && (($val[0]=='"' && $val[$l-1]=='"') || ($val[0]=="'" && $val[$l-1]=="'")))
			{
				$val = (string)(substr($val,1,-1));
			}
			$val = trim($val,' /');
			$this->prop[] = "define('{$key}','phar://{$this->name}/{$val}'); ";
		}
		else {
			$this->prop[] = "define('{$key}',{$val}); ";
		}
	}
}
