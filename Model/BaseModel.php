<?php 
namespace BDS\Model;

use BDS\Core\App;
use BDS\Core\Router;

class BaseModel extends \stdClass
{
	public static $table;
	public static $fields;
	
	public function __construct($id = 0)
	{
		if (!empty($id) && $id > 0) {
			$News = static::select(['where' => ['id' => $id]]);

			$this->mapDataToObject($News);
		}
	}

	public function afterSelect($data = [])
	{
		
	}

	public function upLoadFile($field = 'img_url')
	{
		if (!empty($_FILES) && !empty($_FILES[$field]) && !empty($_FILES[$field]['name'])) {
			$app = App::getInstance();
			$this->$field = time() . '_' . $_FILES[$field]['name'];

			if (!is_dir($app->pathImage . '/'.static::$table)) {
				mkdir($app->pathImage . '/'.static::$table, 0777, true);
			}

			return move_uploaded_file($_FILES[$field]['tmp_name'], $app->pathImage . '/'.static::$table . '/' . $this->$field);
		}

		return false;
	}

	public function mapDataToObject($data = [])
	{
		foreach (static::$fields as $field) {
			$this->$field = !empty($data) && isset($data[$field]) ? SafeData($data[$field], false) : '';
		}
	}

	public function save($fields = [])
	{
		$app    = App::getInstance();
		$result = true;

		$arr_dif = array_diff($fields, static::$fields);
		$fields  = !empty($arr_dif) ? array_diff($fields, $arr_dif) : $fields;

		if (!empty($this->id) && $this->id > 0) {
			$fields['id'] = $this->id;

			return $this->update($fields);
		} else {
			$fields    = static::$fields;
			$val_field = '';
			$val_value = '';

			foreach ($fields as $field) {
				if ($field != 'id') {
					$val_field .= !empty($val_field) ? ",`$field`" : "$field";

					if (property_exists($this, $field)) {
						$val_value .= !empty($val_value) ? ",'".$this->$field."'" : "'".$this->$field."'";
					} else {
						$val_value .= !empty($val_value) ? ",''" : "''";
					}
				}
			}

			$q = mysqli_query($app->db, "INSERT INTO `".static::$table."`($val_field) VALUES($val_value)");

			if (mysqli_affected_rows($app->db) > 0) $result = true;
		}


		return $result;
	}

	public function update($fields, $query = [])
	{
		$arr_dif = array_diff($fields, static::$fields);
		$fields  = !empty($arr_dif) ? array_diff($fields, $arr_dif) : static::$fields;
		$val     = '';
		$where   = '1';
		$app 	 = App::getInstance();

		foreach ($fields as $field) {
			if ($field != 'id') {
				$val .= !empty($val) ? ',' : '';

				$val .= "`$field`='".$this->$field."'";
			}
		}

		if (in_array('id', $fields)) {
			$where = "`id` = '" . $this->id . "' LIMIT 1";
		} elseif (!empty($query)) {
			$arr_dif_query = array_diff($query, static::$fields);

			if (!empty($arr_dif_query)) {
				$query = array_diff($query, $arr_dif_query);

				foreach ($query as $field) {
					if (property_exists($this, $field)) {
						$where .= " AND `$field` = '" . $this->$field . "'";
					}
				}
			} else {
				return false;
			}
		}

		$q = mysqli_query($app->db, "UPDATE `".static::$table."` SET $val WHERE $where");

		if (mysqli_affected_rows($app->db) > 0) return true;

		return false;
	}

	public static function selectAll($query = []) 
	{
		return self::select($query, true);
	}

	public static function select($query = [], $isMultiple = false)
	{
		$app     = App::getInstance();
		$data    = [];
		$select  = '*';
		$where   = '';
		$orderby = '';
		$limit   = 20;

		if (!empty($query)) {
			if (!empty($query['select'])) {
				$select = '';

				foreach ($query['select'] as $field) {
					if (in_array($field, static::$fields)) {
						$select .= !empty($select) ? ",`$field`" : "`$field`";
					}

					if (empty($select)) $select = '*';
				}
			}

			if (!empty($query['where'])) {
				foreach ($query['where'] as $search => $val) {
					if (in_array($search, static::$fields)) {
						$where .= !empty($where) ? " AND `$search` = '$val'" : " `$search` = '$val'";
					}

					if (empty($where)) $where = '1';
				}
			}

			if (!empty($query['order_by'])) {
				foreach ($query['order_by'] as $k => $v) {
					if (in_array($k, static::$fields) && in_array($v, ['ASC', 'DESC', 'asc', 'desc'])) {
						$orderby .= !empty($orderby) ? ", $k $v" : " $k $v";
					}
				}
			}
		}

		if (!$isMultiple) {
			$limit = 1;
		} elseif (!empty($query['limit']) && $query['limit'] > 0) {
			$limit = $query['limit'];
		}

		if (empty(trim($where))) $where = '1';

		if (!empty($orderby)) {
			$orderby = ' ORDER BY ' . $orderby;
		}

		$q = mysqli_query($app->db, "SELECT $select FROM `".static::$table."` WHERE $where $orderby LIMIT $limit");
		
		if ($q) {
			while($row = mysqli_fetch_assoc($q)) {
				$item = [];
				
				foreach (static::$fields as $field) {
					if (isset($row[$field])) {
						if ($field == 'img_url') {
							$row[$field] = $app->realpathImage . '/' . static::$table . '/' . $row[$field];
						}


						$item[$field] = $row[$field];
					}

					if (!empty($row['seo_name'])) {
						$item['url'] = $app->doamin . '/' . Router::reRewriteRouter(static::$table) . '/'. $row['seo_name'];
					}
				}

				$data[] = $item;
			}
		} elseif (!$isMultiple) {
			$data[0] = [];

			foreach (static::$fields as $field) {
				$data[0][$field] = '';
			}
		}

		if (!$isMultiple) {
			return $data[0];
		}

		return $data;
	}
}