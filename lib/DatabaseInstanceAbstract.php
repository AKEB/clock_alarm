<?php

abstract class DatabaseInstanceAbstract {
	protected static string $tableName = '';

	public static function get($ref=false, $add='', $ref_name='id', $params=[]) {
		global $db_obj;
		return Database::get($db_obj, static::$tableName, $ref, $add, $ref_name, $params);
	}

	public static function data($ref=false, $add='', $field_list='*', $params=[]) {
		global $db_obj;
		return Database::data($db_obj, static::$tableName, $ref, $add, $field_list, $params);
	}

	public static function count($ref=false, $add='', $params=[]) {
		global $db_obj;
		return Database::count($db_obj, static::$tableName, $ref, $add, $params);
	}

	public static function save($param, $table_fields='', $ref_name='id', $add='') {
		global $db_obj;
		return Database::save($db_obj, static::$tableName, $param, $table_fields, $ref_name, $add);
	}

	public static function delete($ref=false, string $add='', string $ref_name='id') {
		global $db_obj;
		return Database::delete($db_obj, static::$tableName, $ref, $add, $ref_name);
	}

	public static function truncate() {
		global $db_obj;
		return Database::truncate($db_obj, static::$tableName);
	}
}
