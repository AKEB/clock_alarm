<?php


class SQLite3_Database {
	private $dbh;
	private $database_name = "";
	public $die_on_error = true;
	public $connection_error='';
	public $last_error='';

	private function connect(){
		if ($this->dbh) return true;
		// error_log('Connecting to SQLite: '. $this->database_name);
		if (!$this->dbh = new \SQLite3($this->database_name)){
			$this->connection_error="Can't connect to SQLite: $this->database_name";
			die(date('[Y-m-d H:i:s] ').$this->connection_error."\n");
		}
		$this->dbh->busyTimeout(5000);
		$this->dbh->exec("PRAGMA busy_timeout=5000");
		return true;
	}

	public function __construct($database_name) {
		$this->database_name = $database_name;
	}

	private function close() {
		$this->dbh->close();
	}


	/**
	 * execute sql string
	 * additional ~ "and visible=0 limit 100,10"
	 */
	public function execSQL($sql, $additional = "",$exec=false) {
		// error_log($sql);
		if (!$this->dbh && !$this->connect()) return false;
		// error_log("connect");
		if ($additional) $sql .= " " . $additional;

		if ($exec) {
			$autoExec = true;
		} else {
			$autoExec = false;
			foreach (['insert','create','delete','update','replace'] as $subSqlHeader) {
				if (stripos($sql, $subSqlHeader) !== false) {
					$autoExec = true;
					break;
				}
			}
		}
		// error_log('$autoExec='.$autoExec);
		if (!(@$result = ($autoExec ? $this->dbh->exec($sql) :$this->dbh->query($sql)))) {
			$is_error = true;
			$error = $this->dbh->lastErrorMsg();
			if(!$this->connect()) die($this->connection_error);
			if (@$result = ($autoExec ? $this->dbh->exec($sql) :$this->dbh->query($sql))) {
				$is_error = false;
			}

			if ($is_error) {
				$this->last_error = $error;

				if ($this->die_on_error) {
					$error_text = sprintf("[%s] %s (%s)", date('Y-m-d H:i:s'), $this->dbh->lastErrorCode(), $this->last_error, $sql);
					error_log($error_text);
					die($error_text);
				}
			}
		}

		return $result;
	}

	public function getTables() {
		if (!$this->dbh && !$this->connect()) return false;

		$list = [];

		$res = [];
		$result = $this->execSQL("select * from sqlite_master");
		if ($result instanceof Sqlite3Result) {
			while (is_array($row = $result->fetchArray(SQLITE3_ASSOC))) $res[] = $row;
		}
		$n = count($res);
		for ($i = 0; $i < $n; $i++) {
			if ($res[$i]['type'] == 'table') $list[] = strtoupper($res[$i]['tbl_name']);
		}
		$result->reset();
		unset($res);
		unset($result);
		return $list;
	}

	public function getFields($table_name) {
		if (!$this->dbh && !$this->connect()) return false;

		$list = [];
		$row = [];
		$result = $this->execSQL("PRAGMA table_info(\"".$table_name."\")");
		if ($result instanceof Sqlite3Result) {
			while (is_array($row = $result->fetchArray(SQLITE3_ASSOC))) $res[] = $row;
		}

		$n = count($res);
		$i = 0;
		for ($i = 0; $i < $n; $i++) {
			$field = new Field_SQLite3();
			$field->name = $res[$i]['name'];
			$field->type = $res[$i]['type'];
			$field->name = $res[$i]['name'];
			if ($res[$i]['notnull']) $field->flags .= " NOT NULL ";
			if ($res[$i]['pk']) $field->flags .= " PRIMARY KEY ";
			$list[] = $field;
		}

		$result->reset();
		unset($res);
		unset($result);
		return $list;
	}

	public function TableExists($table_name) {
		$t = $this->die_on_error;
		$this->die_on_error = false;
		$status = ($this->execSQL("SELECT COUNT(*) FROM $table_name",'',true));
		$this->die_on_error = $t;
		if ($status === false) {
			return false;
		}
		return true;
	}

	public function FieldExists($table_name, $field_name) {
		if (!($this->execSQL("SELECT COUNT($field_name) FROM $table_name",'',true))) {
			return false;
		}
		return true;
	}

# ******************************************************************************

	public function db_GetQueryArray($sql, &$result)  {
		$result = [];
		if (!($db_result = $this->execSQL($sql))) return false;
		if (!($db_result instanceof Sqlite3Result)) {
			error_log("db_GetQueryArray Error");
			return false;
		}
		while (is_array($row = $db_result->fetchArray(SQLITE3_ASSOC))) $result[] = $row;
		$db_result->reset();
		unset($db_result);
		return true;
	}

	public function db_GetQueryRow($sql, &$result)  {
		$result = [];
		if (!($db_result = $this->execSQL($sql))) return false;
		if (!($db_result instanceof Sqlite3Result)) return false;
		if (is_array($row = $db_result->fetchArray(SQLITE3_ASSOC))) $result = $row;
		$db_result->reset();
		unset($db_result);
		return true;
	}

	public function db_GetQueryCol($sql, &$result)  {
		$result = [];
		if (!($db_result = $this->execSQL($sql))) return false;
		if (!($db_result instanceof Sqlite3Result)) return false;
		if (is_array($row = $db_result->fetchArray())) $result[] = $row[0];
		$db_result->reset();
		unset($db_result);
		return true;
	}

	public function db_GetQueryHash($sql, &$result)  {
		$result = [];
		if (!($db_result = $this->execSQL($sql))) return false;
		if (!($db_result instanceof Sqlite3Result)) return false;
		if (is_array($row = $db_result->fetchArray())) $result[$row[0]] = $row[1];
		$db_result->reset();
		unset($db_result);
		return true;
	}

	public function db_GetQueryVal($sql, &$result, $default='')  {
		$result = $default;
		if (!($db_result = $this->execSQL($sql))) return false;
		if (!($db_result instanceof Sqlite3Result)) return false;
		$row = $db_result->fetchArray();
		$db_result->reset();
		unset($db_result);
		if (!$row) return false;
		$result = $row[0];
		return true;
	}

	private function db_ExecQuery($sql)  {
		if (!($this->execSQL($sql))) return false;
		$res = $this->dbh->changes();
		if (!$res) $res=-1;
		return $res;
	}

	public function insert_id() {
		return $this->dbh->lastInsertRowID();
	}

	public function affected_rows() {
		return $this->dbh->changes();
	}

	private function db_NextRow(&$result) {
		return $result->fetchArray(SQLITE3_ASSOC);
	}

}
