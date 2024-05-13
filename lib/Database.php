<?php

class Database {

	public static $SAVE_MODE_INSERT  = 1;
	public static $SAVE_MODE_UPDATE  = 2;
	public static $SAVE_MODE_REPLACE = 3;

	private static function &get_params($item, $keys, $skip_null=false, $skip_empty=false) {
		if (!is_array($keys)) $keys = preg_split("/[\s,;]+/", $keys);
		$arr = [];
		foreach ($keys as $k) {
			if ($skip_null && !isset($item[$k])) continue;
			if ($skip_empty && empty($item[$k])) continue;
			$arr[$k] = $item[$k];
		}
		return $arr;
	}

	public static function get(&$db_obj, string $table_name, $ref=false, string $add='', string $ref_name='id', $params=[]) {
		$index_add = isset($params['_index_hint']) ? ' '.$params['_index_hint'] : '';
		$query = 'SELECT * FROM `'.$table_name.'` AS t '.$index_add.' WHERE 1 ';
		if ($ref) {
			if (!is_array($ref)) $ref = [$ref_name => $ref];
			foreach ($ref as $k=>$v) {
				$k = str_replace('`', '', $k); /* ` */
				$query .= is_array($v) ?
					sql_pholder(' AND `'.$k.'` IN ( ?@ )', $v ) :
					( isset($v) ?
						sql_pholder(' AND `'.$k.'`=?', $v) :
						' AND `'.$k.'` IS NULL'
					);
			}
		}
		$query .= $add." LIMIT 1";
		$raw_data = [];
		try {
			$db_obj->db_GetQueryRow($query, $raw_data);
		} catch(\Exception $ex) {
			error_log('Database::get error: '.$ex->getMessage() . "\n" . $ex->getTraceAsString());
			die('Database::get error: '.$ex->getMessage());
		}
		return $raw_data;
	}

	public static function data(&$db_obj, string $table_name, $ref=false, string $add='', string $field_list='*', $params=[]) {
		$index_add = isset($params['_index_hint']) ? ' '.$params['_index_hint'] : '';
		$query = 'SELECT '.$field_list.' FROM `'.$table_name.'` AS t '.$index_add;
		if (isset($params['_join']) && $params['_join']) $query .= ' '. $params['_join'];
		$query .= ' WHERE 1 ';
		if ($ref) {
			if (!is_array($ref)) {
				return false;
			}
			foreach ($ref as $k=>$v) {
				$k = str_replace('`', '', $k);
				$query .= is_array($v) ? sql_pholder(" AND `".$k."` IN ( ?@ )",$v): (isset($v) ? sql_pholder(" AND `".$k."`=?",$v): " AND `".$k."` IS NULL");
			}
		}
		$query .= $add;
		$raw_data = [];
		try {
			$db_obj->db_GetQueryArray($query, $raw_data);
		} catch(\Exception $ex) {
			error_log('Database::data error: '.$ex->getMessage() . "\n" . $ex->getTraceAsString());
			die('Database::data error: '.$ex->getMessage());
		}
		return $raw_data;
	}

	public static function count(&$db_obj, string $table_name, $ref=false, string $add='', $params=[]) {
		$index_add = isset($params['_index_hint']) ? ' '.$params['_index_hint'] : '';
		$query = 'SELECT count(*) FROM `'.$table_name.'` AS t '.$index_add;
		if ($params['_join']) $query .= ' '. $params['_join'];
		$query .= ' WHERE 1 ';
		if ($ref) {
			if (!is_array($ref)) {
				return false;
			}
			foreach ($ref as $k=>$v) {
				$k = str_replace('`', '', $k);
				$query .= is_array($v) ? sql_pholder(" AND `".$k."` IN ( ?@ )",$v): (isset($v) ? sql_pholder(" AND `".$k."`=?",$v): " AND `".$k."` IS NULL");
			}
		}
		$query .= $add;
		$val = 0;
		try {
			$db_obj->db_GetQueryVal($query, $val);
		} catch(\Exception $ex) {
			error_log('Database::count error: '.$ex->getMessage() . "\n" . $ex->getTraceAsString());
			die('Database::count error: '.$ex->getMessage());
		}
		return intval($val);
	}

	public static function save(&$db_obj, string $table_name, $param, string $table_fields='', string $ref_name='id', string $add='') {
		$ref_id = $param[$ref_name] ?? null;
		$set = '';
		$cnt = false;
		$on_duplicate = false;
		$mode = false;
		$ignore = false;
		if (isset($param['_mode'])) { $mode = $param['_mode']; unset($param['_mode']); }
		if (isset($param['_set'])) { $set .= $param['_set']; unset($param['_set']); }
		if (isset($param['_add'])) { $add .= $param['_add']; unset($param['_add']); }
		if (isset($param['_cnt'])) { $cnt = true; unset($param['_cnt']); }
		if (isset($param['_ignore'])) { $ignore = true; unset($param['_ignore']); }
		if (isset($param['_on_duplicate'])) { $on_duplicate = $param['_on_duplicate']; unset($param['_on_duplicate']);}
		if ($set && !$ref_id && !$add) return false;	// защита!
		if (!$mode) $mode =	$set || $add || $ref_id ? static::$SAVE_MODE_UPDATE : static::$SAVE_MODE_INSERT;
		$res = false;
		$die_on_error = $db_obj->die_on_error;
		if (isset($param['_noerr'])) { $db_obj->die_on_error = false; unset($param['_noerr']); }
		$index_add = isset($param['_index_hint']) ? ' '.$param['_index_hint'] : '';
		if ($table_fields) $param = static::get_params($param, $table_fields, true);

		if ($mode == static::$SAVE_MODE_INSERT) {	// INSERT
			$query = sql_pholder('INSERT '.($ignore ? 'IGNORE': '').' INTO `'.$table_name.'` (`'.implode('`,`', array_keys($param)).'`) values ( ?@ )',array_values($param));
			if ($on_duplicate) $query .= ' ON DUPLICATE KEY UPDATE '.$on_duplicate;
			$res = $db_obj->execSQL($query);
			$ref_id = $res && ($db_obj->affected_rows() > 0) ? ($param[$ref_name] ?? $db_obj->insert_id()) : false;

		} elseif ($mode == static::$SAVE_MODE_UPDATE) {	// UPDATE
			$query = 'UPDATE `'.$table_name.'` '.$index_add;
			$t = [];
			if ($param[$ref_name]) unset($param[$ref_name]);

			if ($param) $t[] = sql_pholder("?%",$param);
			if ($set) $t[] = $set;
			$query .= ' SET '.implode(', ',$t).' WHERE 1 ';
			if ($ref_id) $query .= (is_array($ref_id) ? sql_pholder(" AND `".$ref_name."` IN ( ?@ ) ",$ref_id) : sql_pholder(" AND `".$ref_name."`=?",$ref_id));
			$query .= $add;
			$res = $db_obj->execSQL($query);

		} elseif ($mode == static::$SAVE_MODE_REPLACE)  {	// REPLACE
			$query = sql_pholder('REPLACE INTO `'.$table_name.'` SET ?%',$param);
			$res = $db_obj->execSQL($query);
			$ref_id = $res && ($db_obj->affected_rows() > 0) ? $db_obj->insert_id(): false;
		}
		safe_file_rewrite('get_database_hash.txt', md5_file(constant('SQL_DB_FILE_NAME')));
		$db_obj->die_on_error = $die_on_error;
		if (!$res) return false;
		return $cnt ? $db_obj->affected_rows() : $ref_id;
	}

	public static function delete(&$db_obj, string $table_name, $ref=false, string $add='', string $ref_name='id') {
		if (!$ref && !$add) return false;
		$query = "DELETE FROM `".$table_name."` WHERE 1 ";
		if ($ref) {
			if (!is_array($ref)) {
				$ref = [$ref_name => $ref];
			}
			foreach ($ref as $k=>$v) {
				$query .= is_array($v) ? sql_pholder(" AND `".$k."` IN ( ?@ )",$v): (isset($v) ? sql_pholder(" AND `".$k."`=?",$v): " AND `".$k."` IS NULL");
			}
		}
		$query .= $add;
		$db_obj->execSQL($query);
		safe_file_rewrite('get_database_hash.txt', md5_file(constant('SQL_DB_FILE_NAME')));
		return $db_obj->affected_rows();
	}

	public static function exec_sql(&$db_obj, $query, $params=[]) {
		$die_on_error = $db_obj->die_on_error;
		if (isset($params['_noerr'])) { $db_obj->die_on_error = false; unset($params['_noerr']); }
		$res = $db_obj->execSQL($query);
		$db_obj->die_on_error = $die_on_error;
		safe_file_rewrite('get_database_hash.txt', md5_file(constant('SQL_DB_FILE_NAME')));
		return $res;
	}

	public static function truncate(&$db_obj, $table_name) {
		$query = 'TRUNCATE `'.$table_name.'` ';
		$db_obj->execSQL($query);
		safe_file_rewrite('get_database_hash.txt', md5_file(constant('SQL_DB_FILE_NAME')));
		return true;
	}
}
