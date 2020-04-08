<?php


class ExecMake
{
	private $make;
	private $ar;
	private $user;
	private $group;

	public $error = false;

	function __construct($make)
	{
		$this->make = $make;
		$this->ar = NULL;

		$userInfo = posix_getpwuid(posix_getuid());
		$this->user = $userInfo['name'];

		$groupInfo = posix_getgrgid(posix_getgid());
		$this->group = $groupInfo['name'];
	}


	function make($tg)
	{
		$this->error = false;
		$this->err_msg = '';

		if (isset($this->make['TGT'][$tg]) == false) {
			$this->error = true;
			$this->err_msg = "target '{$tg}' not found";
			return;
		}

		foreach($this->make['TGT'][$tg] as $cmd)
		{
			$c = "do_".$cmd['C']??false;
			$a = $cmd['A']??'';
			$c = str_replace('!','1',$c);

			foreach ($this->make['DEF'] as $key => $val){
				$a = str_replace('{$'.$key.'}',$val,$a);
			}
			$a = str_replace('$@',$tg,$a);
			$a = str_replace('$!U',$this->user,$a);
			$a = str_replace('$!G',$this->group,$a);

			if (method_exists($this,$c)) {
				$this->$c($a);
			}
			else {
				$this->error = true;
				msg_out("make instruction \e[38;5;147m{$cmd['C']}\e[0m is not implemented",-1);
			}
			if ($this->error) {
				return;
			}
		}
	}


	//-------------------------------------------------------------------------------------
	// building archiuves

	private function do_PM($a)
	{
		$this->make($a);
	}

	private function do_OA($a)
	{
		if ($this->ar != NULL)
		{
			if ($this->ar->name = $a) {
				return;
			}
			$this->error = true;
			msg_out("recursive archive creation is not supported!",-1);
			return;
		}
		$this->ar = new Archiver($a);
		$this->error = $this->ar->error;
	}

	//-------------------------------------------------------------------------------------
	// archiving files

	private function do_AS($a)
	{
		$this->do_archive($a);
	}

	private function do_AD($a)
	{
		$this->do_archive($a,false);
	}

	private function do_archive($a,$code=true)
	{
		if ($this->ar == NULL)
		{
			msg_out("trying archiving '{$a}', with no archive open",-1);
			$this->error = true;
			return;
		}

		list($src,$dst) = explode('>',$a);
		$src=trim($src);
		$dst=trim($dst);

		if ($src=='' || $dst=='') {
			msg_out("trying archiving '{$a}', improper arguments (HINT: src > dst)",-1);
			$this->error = true;
			return;
		}

		$list = glob($src,GLOB_BRACE);
		if ($list===false) {
			msg_out("trying archiving {$a} => read error",-1);
			$this->error = true;
			return;
		}
		foreach ($list as $item)
		{
			if (is_dir($item)==false && is_readable($item))
			{
				$inf = pathinfo($item);
				$tdst = str_replace('$&',$inf['basename'],$dst);
				$tdst = str_replace('$?',$inf['filename'],$tdst);
				$this->ar->add_file($item,$tdst,$code);
				$this->error = $this->ar->error;
			}
			else {
				msg_out("'{$item}' not a readable file, skipping",1);
			}
		}
	}


	//-------------------------------------------------------------------------------------
	// setting archive properties

	private function do_PS($a)
	{
		$this->do_set_property($a,true);
	}

	private function do_DF($a)
	{
		$this->do_set_property($a,false);
	}

	private function do_set_property($prop,$path=false)
	{
		if (preg_match('/^([A-Z][A-Z0-9_]*)\s+=\s+(.*)/',$prop,$d))  // this is a property
		{
			$v = trim($d[2]);
			$this->ar->add_property($d[1],$v,$path);
			$this->error = $this->ar->error;
		}
		else {
			msg_out("'invalid property definition '{$prop}'",-1);
			$this->error = true;
		}
	}


	//-------------------------------------------------------------------------------------
	// closing the archive

	private function do_CA($a)
	{
		$this->do_close_archive($a,false);
	}

	private function do_CA1($a)
	{
		$this->do_close_archive($a,true);
	}

	private function do_close_archive($a,$exec=true)
	{
		if ($a==NULL || strlen(trim($a))==0) {
			$a = null;
		}
		if ($this->ar) {
			$this->ar->close_archive($exec,$a);
			$this->error = $this->ar->error;
		}
		else {
			$this->error = true;
			msg_out("closing archive that was never opened",-1);
		}
		unset($this->ar);
		$this->ar = null;
	}

	//-------------------------------------------------------------------------------------
	// installing

	private function do_IN($a)
	{
		$uid = $this->user;
		$gid = $this->group;
		$mod = '755';

		if (preg_match('/^(\S+:\S+\s+)?([0-7]{3}\s+)?(\S+)\s+>\s+(\S+)/',$a,$d)===false)
		{
			$this->error = true;
			msg_out("install: '{$a}' has wrong format",-1);
			return;
		}
		if ($d[1] != '') {
			list($uid,$gid) = explode(':',trim($d[1]));
		}
		if ($d[2] != '') {
			$mod = trim($d[2]);
		}
		$src = trim($d[3]);
		$dst = trim($d[4]);

		$list = glob($src,GLOB_BRACE);
		if ($list===false) {
			msg_out("trying installing {$a} => read error",-1);
			$this->error = true;
			return;
		}
		foreach ($list as $item)
		{
			if (is_dir($item)==false && is_readable($item))
			{
				$inf = pathinfo($item);
				$tdst = str_replace('$&',$inf['basename'],$dst);
				$tdst = str_replace('$?',$inf['filename'],$tdst);
				$this->do_install($item,$tdst,$uid,$gid,$mod);
			}
			else {
				msg_out("'{$item}' not a readable file, skipping",1);
			}
		}
	}

	private function do_install($src,$dst,$uid,$gid,$mod)
	{
		$inf = pathinfo($dst);

		$cmd = "install -o {$uid} -g {$gid} -d {$inf['dirname']}";
		$res = @exec($cmd);
		if ($res!='') {
			msg_out("install failed creating destination",1);
			$this->error=true;
			return;
		}
		$cmd = "install -o {$uid} -g {$gid} -m {$mod} -T {$src} {$dst}";
		$res = @exec($cmd);
		if ($res!='') {
			msg_out("install failed installing {$src} > {$dst}",1);
			$this->error=true;
		}
	}



	//-------------------------------------------------------------------------------------
	// additional stuff

	private function do_SE($a)
	{
		$res = exec($a);
		$res = trim($res);
		if ($res != '') {
			msg_out($res,-1);
		}
	}

	private function do_ER($a)
	{
		if ($this->user != 'root' || $this->group !='root') {
			msg_out(trim($a),-1);
			$this->error = true;
		}
	}

	private function do_PR($a)
	{
		print (trim($a)."\n");
	}
}
