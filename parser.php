<?php


class Parser
{
	private $file;
	private $make = [];
	private $ct = '';

	public $error = false;
	public $line = 0;
	public $msg = '';

	function __construct($file)
	{
		$this->file = $file;
	}


	function parse()
	{
		$this->make = ['DEF' => [], 'TGT'=>[]];
		$this->ct = '';
		$fp = @fopen($this->file,'r');
		if ($fp===false) {
			$this->error = true;
			$this->msg = "could not read makefile '{$this->file}'";
			return;
		}

		while (($line = fgets($fp)) !== false)
		{
			$this->line++;
			if ($this->parse_line(rtrim($line)) == false) {
				$this->error = true;
				return;
			}
		}
		fclose($fp);
	}


	function get_make()
	{
		return $this->make;
	}


	private function parse_line($line)
	{
		static $cmds = ['PM','OA','AS','AD','PS','DF','CA','CA!','IN','SE','ER','PR'];

		if (strlen($line)==0) {		// ignore empty lines
			return true;
		}

		if (preg_match('/^\s*#/',$line)) { // ignore comment lines
			return true;
		}

		if (preg_match('/^([A-Z][A-Z0-9]*)\s+=\s+(.*)/',$line,$d))  // this is a define
		{
			$v = trim($d[2]);
			if (($l=strlen($v)) > 1 && $v[0]=='"' && $v[$l-1]=='"') {
				$v = substr($v,1,-1);
			}
			$this->make['DEF'][$d[1]] = $v;
		}

		if (preg_match('/^([a-z][a-z0-9_]*):/',$line,$d))  // this is a target
		{
			$this->make['TGT'][$d[1]] = [];
			$this->ct = $d[1];
		}

		$line .= ' ';
		if (preg_match('/^^\s+([A-Z]{2}!?)\s+(.*)/',$line,$d))  // a command
		{
			if (in_array($d[1],$cmds))
			{
				$arg = trim($d[2]);
				$this->make['TGT'][$this->ct][] = ['C'=>$d[1], 'A'=>$arg];
			}
			else {
				$this->msg = "unrecognized command: {$d[1]} {$d[2]}";
				return false;
			}
		}
		return true;
	}
}
