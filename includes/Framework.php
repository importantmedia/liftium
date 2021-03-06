<?php
require dirname(__FILE__) . '/CommonSettings.php';

// F U Symfony
class Framework {

	static public function getDB ($connType = "master"){
		// shortcut
		$c = $GLOBALS['CONFIG']['db'];

		// Randomly choose between slave hosts
		shuffle($c['slavehosts']);
		$slavehost = $c['slavehosts'][0];

		switch ($connType){
		  case 'master': $dsn = "mysql:dbname={$c['dbname']};host={$c['masterhost']}"; break;
		  case 'slave': $dsn = "mysql:dbname={$c['dbname']};host=$slavehost"; break;
		  default: return false;
		}

		static $cons;
		if (empty($cons[$connType])){
			$cons[$connType] = new PDO($dsn, $c['username'], $c['password']);
			$cons[$connType]->setAttribute(PDO::ATTR_CASE, PDO::CASE_NATURAL);
			$cons[$connType]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
		}

		return $cons[$connType];
	}


	// We can't always count on ENV('HOSTNAME') being set
	static public function getHostname(){
		static $hostname;
		if (!empty($hostname)){
			return $hostname;
		} else {
			$hostname = trim(`hostname`);
			return $hostname;
		}
	}


	static public function pretty_array($array, $length = null, $separator = ', '){
		$h = '';
		if (!is_array($array)){
			// This isn't going to work out
			return false;
		} else if (isset($array[0])){
			// Numeric array, just list with implode
			$h = implode($separator, $array);
		} else {
			foreach($array as $key => $value){
				$h = "$key : " . self::pretty_array($value, $length, $separator) . "<br />";
			}
		}
		if (!empty($length) && strlen($h) > $length){
			$h = substr($h, 0, $length) . '...';
		}
		return $h;
	}

	static public function cacheDump($filename, $host,$port = 11211, $pattern = null) {
	      $fd = fopen($filename,"w+");
	      $sock = stream_socket_client("tcp://$host:$port");

	      fwrite($sock,"stats items\r\n");
	      $items = array();
	      $totalitems = 0;
	      while($buffer=fgets($sock,4096)){
		if (preg_match("/^STAT items:(\d*):number (\d*)/",$buffer, $args)) {
		  $items[$args[1]] = $args[2];
		  $totalitems += $args[2];
		}
		if ($buffer === "END\r\n")
		  break;
	      }
	      ksort($items);

	      foreach ($items as $bucket => $value) {
		fwrite($sock,"stats cachedump $bucket $items[$bucket]\r\n");
		// build key list
		$keyexp = array();
		while($buffer=fgets($sock,4096)){
		  if (preg_match("/^ITEM (.*) \[.* (\d+) s\]/",$buffer, $args)) {
		    $keyexp[$args[1]] = $args[2];
		  }
		  if ($buffer == "END\r\n")
		    break;
		}
		// loop through keys
		foreach ($keyexp as $key => $expire) {
		  fwrite($sock,"get $key\r\n");
		  $value = stream_get_line($sock,4096, "\r\n");
		  // does key exist? could of expired...
		  if ($value == "END")
		  {
		    continue;
		  }

		  preg_match("/VALUE (.*) (\d+) (\d+)/",$value,$args);
		  $flags = $args[2];
		  $vlen = $args[3];
		  if ($vlen > 0)
		  {
		    $data = fread($sock,$vlen);
		  }
		  $end = fread($sock,7);
		  if($vlen > 0) fwrite($fd,"add $key $flags $expire $vlen\r\n$data\r\n");
		}
	      }
	      fclose($fd);
	}

	static public function getIp(){
		if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
			return $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else if (!empty($_SERVER['REMOTE_ADDR'])){
			return $_SERVER['REMOTE_ADDR'];
		} else {
			return false;
		}
	}

	static public function getTimeAgo($eventTime){
		$secondsAgo = strtotime('now') - strtotime($eventTime);
		if ($secondsAgo < 120){
			return "$secondsAgo seconds ago";
		} else if ($secondsAgo < (150 * 60)){
			return round($secondsAgo/60) .  " minutes ago";
		} else if ($secondsAgo < (60 * 60 * 36)){
			return "About " . round($secondsAgo / 3600) . " hours ago";
		} else {
			return "About " . round($secondsAgo / 86400) . " days ago";
		}

	}

	static public function getBrowser($ua = null){
		if (is_null($ua)){
			$ua = $_SERVER['HTTP_USER_AGENT'];
		}

		if (preg_match("#Firefox/3.5#", $ua)){
			return "Firefox 3.5";
		} else if (preg_match("#Firefox/3#", $ua)){
			return "Firefox 3";
		} else if (preg_match("#Firefox/2#", $ua)){
			return "Firefox 2";
		} else if (preg_match("/MSIE 8/", $ua)){
			return "IE 8";
		} else if (preg_match("/MSIE 7/", $ua)){
			return "IE 7";
		} else if (preg_match("/MSIE 6/", $ua)){
			return "IE 6";
		} else if (preg_match("/Chrome/", $ua)){
			// Note that Chrome user agent contains Safari, so keep this above the safari check
			return "Chrome";
		} else if (preg_match("/Safari/", $ua)){
			return "Safari";
		} else if (preg_match("/Gecko/", $ua)){
			return "Mozilla";
		} else {
			return "Other";
		}
	}


	/* Look for the request val in GET and POST data, returning [optional] defaultVal if not found. 
         * If $type is supplied, data will be sanitized appropriately.
         * See * http://us2.php.net/manual/en/filter.constants.php for a list of filters
         */
	static public function getRequestVal($name, $defaultVal = null, $filterType = null){
		if (isset($_GET[$name])){
			$val = $_GET[$name];
		} else if (isset($_POST[$name])){
			$val = $_POST[$name];
		} else {
			return $defaultVal;
		}
		if (is_null($filterType)){
		  	return $val; // no filtering
		} else if ($filterType < 1){
			trigger_error("No such filterType " . $filterType, E_USER_WARNING);
		  	return $val; 
		} else {
			if (filter_var($val, $filterType)){
				return $val;	
			} else {
				trigger_error("Invalid data supplied for $name", E_USER_NOTICE);
				return $defaultVal;
			}
		}
	}

	static public function isDev() {
		global $DEV_HOSTS;
		return in_array(Framework::getHostname(), $DEV_HOSTS);
	}

	/* See http://www.freesoft.org/CIE/RFC/2068/185.htm for an explanation */
	static public function httpCache($mtime=null){
		// Make it easy to disable
		if (isset($_GET['noCache'])){
			return;
		}

		$debug=false;

		// When sessions are enabled, php puts a bunch of no-cache headers in place.
		session_cache_limiter('none');

		switch (@$_SERVER['REQUEST_METHOD']){
			case '': return false; // Command line?
			case 'POST': return false; // Don't use this for POST.
		}

		if (is_null($mtime)){
			// default to the timestamp of the file
			$mtime=filemtime($_SERVER['SCRIPT_FILENAME']);
			$debug && header("X-httpCache-Comment: Using timestamp of {$_SERVER['SCRIPT_FILENAME']} for mtime", false);
		} else {
			if(!preg_match("/[0-9]+/",$mtime)) { // all numbers suggests a unix timestamp
				$mtime=strtotime($mtime);
				if ($mtime==-1){
					error_log("Invalid mtime in ".  __METHOD__ ." - '$mtime' is not parseable by strtotime");
					$debug && header("X-httpCache-Comment:  mtime in httpCache - '$mtime' is not parseable by strtotime", false);
					return false;
				}
			}
		}

		$lastModified=self::httpDate($mtime);
		header("Last-Modified: $lastModified");

		if (empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])){
			$debug && header("X-httpCache-Comment: First time here", false);
			return; // First time
		}

		if (strtotime($lastModified) > strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'])){
			$debug && header("X-httpCache-Comment: Mod since times don't match: {$_SERVER['HTTP_IF_MODIFIED_SINCE']} != $lastModified", false);
			return; // Times don't match, doc has changed since their last request.
		}

		// If it gets this far, nothing has changed. serve a 304 and exit.
		header('HTTP/1.0 304 Not Modified');
		exit;
	}

	public static function httpDate($time){
		return gmdate('D, d M Y H:i:s \G\M\T', $time);
	}

}
