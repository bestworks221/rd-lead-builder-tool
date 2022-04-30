<?php
	class db{
		var $dbhost;
		var $dbuser;
		var $dbpass;
		var $dbname;
		var $connect;

		function db($dbhost, $dbuser, $dbpass, $dbname){
			$this->dbhost = $dbhost;
			$this->dbuser = $dbuser;
			$this->dbpass = $dbpass;
			$this->dbname = $dbname;
			$this->connect = 0;
		}

		function connect(){
			$connection = mysqli_connect($this->dbhost, $this->dbuser, $this->dbpass, $this->dbname);

			if(!$connection){
				return false;
			}
			else{
				$this->connect = 1;
			}
		}

		function query($SQL){
			$connection = mysqli_connect($this->dbhost, $this->dbuser, $this->dbpass, $this->dbname);
			$query = mysqli_query($connection, $SQL);

			if(!$query){
				return false;
			}
			else{
				if(preg_match("/^select/i", $SQL)){
					$result = array();
					while($row = mysqli_fetch_array($query)){
						$result[] = $row;
					}
					return $result;
				}
				else{
					return true;
				}
			}
		}

		// GET SINGLE ROW FROM DATABASE
		function info($table_name, $SQL_condition = ""){
			global $dbprefix;
			$dbtable = $dbprefix . $table_name;

			$query = $this->query("SELECT * FROM {$dbtable}" . ($SQL_condition ? " WHERE {$SQL_condition}" : ""));

			if(count($query)){
				$row = $query[0];
				$data = array();

				foreach($row as $key=>$value){
					if(preg_match("/\d/i", $key)){
						continue;
					}

					$data[$key] = $value;
				}

				return $data;
			}
			else{
				return false;
			}
		}

		// GET LAST ID FROM TABLE PASSED IN PARAMETER
		function getauto($table_name){
			global $dbprefix, $dbname;
			$dbtable = $dbprefix . $table_name;

			$query = $this->query("SELECT auto_increment FROM information_schema.TABLES WHERE TABLE_NAME = '{$dbtable}' AND TABLE_SCHEMA = '{$dbname}'");

			return $query[0]["auto_increment"];
		}

		
	}
?>