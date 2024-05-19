<?php

class ClockAlarm extends DatabaseInstanceAbstract{
	protected static string $tableName = 'alarms';

	public static function create_table() {
		global $db_obj;
		$sql = 'CREATE TABLE IF NOT EXISTS "'.static::$tableName.'" (
			"id"             INTEGER,
			"hour"           INTEGER,
			"minute"         INTEGER,
			"volume"         INTEGER,
			"status"         INTEGER,
			"sound"          TEXT,
			"repeat"         TEXT,
			"play_last_time" INTEGER,
			"update_time"    INTEGER,
			"create_time"    INTEGER,
			PRIMARY KEY("id" AUTOINCREMENT)
		);';
		return Database::exec_sql($db_obj, $sql );
	}

	public static function get($ref=false, $add='', $ref_name='id', $params=[]) {
		global $db_obj;
		$data = Database::get($db_obj, static::$tableName, $ref, $add, $ref_name, $params);
		if (isset($data['repeat']) && $data['repeat'] && is_string($data['repeat'])) $data['repeat'] = json_decode($data['repeat'], true);
		return $data;
	}

	public static function data($ref=false, $add='', $field_list='*', $params=[]) {
		global $db_obj;
		$data = Database::data($db_obj, static::$tableName, $ref, $add, $field_list, $params);
		foreach ($data as $key => $value) {
			if (isset($value['repeat']) && $value['repeat'] && is_string($value['repeat'])) {
				$data[$key]['repeat'] = json_decode($value['repeat'], true);
			}
		}
		return $data;
	}

	public static function save($param, $table_fields='', $ref_name='id', $add='') {
		global $db_obj;
		if (isset($param['repeat']) && $param['repeat'] && is_array($param['repeat'])) $param['repeat'] = json_encode($param['repeat']);
		return Database::save($db_obj, static::$tableName, $param, $table_fields, $ref_name, $add);
	}

	public static function getAlarmsJson() {
		$alarms = \ClockAlarm::data();
		error_log('$alarms = ' . var_export($alarms, true));
		$data = [];
		foreach( $alarms as $alarm ) {
			if (isset($alarm['repeat'])) {
				$alarm['repeat_text'] = alarm_get_repeat_text($alarm['repeat']);
			} else {
				$time = mktime($alarm['hour'], $alarm['minute'], 0, date('m'), date('d'), date("Y"));
				if ($time < time()) $time += 86400;
				$alarm['repeat_text'] = alarm_get_onetime_text($time);
			}
			$data[$alarm['id']] = $alarm;
		}
		return json_encode($data);
	}

}
