<?php

/**
 * Main Trait for DynaModel Package
 *
 * @package StieTotalWin\DynaModel
 * @author  Arif RH <arifrahmanhakim.net@gmail.com>
 * @license MIT
 */

namespace StieTotalWin\DynaModel\Models;

/**
 * This trait can be use inside existing \CodeIgniter\Model
 * to support any new ability like relationship feature, etc
 */

trait DynaModelTrait
{
	/**
	 * Detail Field information from active Table
	 *
	 * @var mixed[] $fieldInfo Save information of table fields
	 */
	protected $fieldInfo = null;

	/**
	 * Protected Fields that will unset during insert/update
	 * iF allowed Fields is greater than protected ones,
	 * then it will be easier to set protectedFields than allowedFields
	 *
	 * @var mixed[] $protectedFields
	 */
	protected $protectedFields = [];

	/**
	 * -------------------------
	 * Relationship Properties
	 * -------------------------
	 */

	/**
	 * Relationship Information
	 *
	 * @var array{
	 * mixed:string,
	 * mixed?:mixed[]
	 * } $relationships
	 */
	protected $relationships = [];

	/**
	 * One-to-One/Many-to-One Relationship
	 *
	 * @var array{
	 *  table:string,
	 *  primaryKey?:string,
	 *  relationId?:string
	 * } $belongsTo
	 */
	protected $belongsTo = [];

	/**
	 * One-to-Many Relationship
	 *
	 * @var array{
	 *  table:string,
	 *  primaryKey?:string,
	 *  relationId?:string,
	 *  orderBy?:mixed[]
	 * } $hasMany
	 */
	protected $hasMany = [];

	 /**
	  * Relationship Criteria
	  *
	  * @var array{
	  *  mixed?:string,
	  *  mixed?:mixed[]
	  * } whereRelations
	  */
	protected $whereRelations = [];

	 /**
	  * Callback Before Find - used for join operation in relationship
	  *
	  * @var mixed[] $beforeFind
	  */
	protected $beforeFind = [];

	/**
	 * Define Relationship JOIN Callback
	 *
	 * @var string $relationshipJoinCallback
	 */
	protected $relationshipJoinCallback = 'joinRelationship';

	/**
	 * Define one-to-many Relationship Callback
	 *
	 * @var string $relationshipCallback
	 */
	protected $relationshipCallback = 'buildRelationship';

	/**
	 * -------------------------------
	 * End of Relationship Properties
	 * ------------------------------
	 */

	/**
	 * ----------------------------------------------------------
	 * These methods below will override \CodeIgniter\Model method
	 * -----------------------------------------------------------
	 * - setTable  -> giving ability to set primaryKey and initialize table
	 * - find      -> add callback beforeFind
	 * - findAll   -> add callback beforeFind
	 */

	/**
	 * Override Set Table with extra work
	 * set primary key of table
	 * set some properties
	 * collect field info
	 * set the builder
	 *
	 * @param string  $table      table name
	 * @param string  $primaryKey primary key field name
	 * @param mixed[] $options    key-pair of properties value
	 *
	 * @return self
	 */
	public function setTable(string $table, string $primaryKey = null, array $options = null)
	{
		$this->table = $table;

		$this->setPrimaryKey($primaryKey);

		$this->_initialize($table, $options);

		return $this;
	}

	/**
	 * Initialize
	 *
	 * @param string $table
	 * @param mixed  $options
	 *
	 * @return $this
	 */
	protected function _initialize(string $table, $options = null)
	{
		helper('inflector');
		helper('array');

		$this->setOptions($options);
		$this->collectFieldInfo($table);
		$this->builder = $this->db->table($table);

		if (empty($this->allowedFields))
		{
			$this->allowedFields = array_keys($this->fieldInfo);
		}

		return $this;
	}

	/**
	 * Override find method to fix bug on using countAllResults
	 *
	 * @see https://github.com/codeigniter4/CodeIgniter4/issues/2705
	 *
	 * @param null|integer|string|mixed[] $id
	 * @param boolean                     $reset
	 *
	 * @return null|mixed[]
	 */
	public function find($id = null, bool $reset = true)
	{
		$builder = $this->builder();

		if ($this->tempUseSoftDeletes === true)
		{
			$builder->where($this->table . '.' . $this->deletedField, null);
		}

		$this->trigger('beforeFind', $this->relationships);

		if (is_array($id))
		{
			$row = $builder->whereIn($this->table . '.' . $this->primaryKey, $id)
				->get(null, 0, $reset);
			$row = $row->getResult($this->tempReturnType);
		}
		elseif (is_numeric($id) || is_string($id))
		{
			$row = $builder->where($this->table . '.' . $this->primaryKey, $id)
				->get(null, 0, $reset);

			$row = $row->getFirstRow($this->tempReturnType);
		}
		else
		{
			$row = $builder->get(null, 0, $reset);

			$row = $row->getResult($this->tempReturnType);
		}

		$eventData = $this->trigger('afterFind', ['id' => $id, 'data' => $row]);

		$this->tempReturnType     = $this->returnType;
		$this->tempUseSoftDeletes = $this->useSoftDeletes;

		return $eventData['data'];
	}

	/**
	 * Find All rows
	 *
	 * @param integer $limit
	 * @param integer $offset
	 *
	 * @return null|mixed[]
	 */
	public function findAll(int $limit = 0, int $offset = 0)
	{
		$builder = $this->builder();

		if ($this->tempUseSoftDeletes === true)
		{
			$builder->where($this->table . '.' . $this->deletedField, null);
		}

		$this->trigger('beforeFind', $this->relationships);

		$row = $builder->limit($limit, $offset)
			->get();

		$row = $row->getResult($this->tempReturnType);

		$eventData = $this->trigger('afterFind', ['data' => $row, 'limit' => $limit, 'offset' => $offset]);

		$this->tempReturnType     = $this->returnType;
		$this->tempUseSoftDeletes = $this->useSoftDeletes;

		return $eventData['data'];
	}

	/**
	 * Find by criteria
	 *
	 * @param mixed[] $where
	 * @param string  $return row -> single row | all -> all rows
	 * @param boolean $reset
	 *
	 * @return null|mixed[]
	 */
	public function findBy(array $where = [], string $return = 'all', bool $reset = true)
	{
		$builder = $this->builder();

		if ($this->tempUseSoftDeletes === true)
		{
			$builder->where($this->table . '.' . $this->deletedField, null);
		}

		$this->trigger('beforeFind', $this->relationships);

		if (is_array($where) && count($where) > 0)
		{
			foreach ($where as $column => $condition)
			{
				if (is_array($condition))
				{
					$builder->whereIn($this->table . '.' . $column, $condition);
				}
				else
				{
					$builder->where($this->table . '.' . $column, $condition);
				}
			}
		}

		if ($return === 'row')
		{
			$row = $builder->get(1, 0, $reset);
			$row = $row->getFirstRow($this->tempReturnType);
		}
		else
		{
			$row = $builder->get(null, 0, $reset);
			$row = $row->getResult($this->tempReturnType);
		}

		$eventData = $this->trigger('afterFind', ['id' => $where, 'data' => $row]);

		$this->tempReturnType     = $this->returnType;
		$this->tempUseSoftDeletes = $this->useSoftDeletes;

		return $eventData['data'];
	}

	/**
	 * Find One row by criteria
	 *
	 * @param mixed[] $where
	 * @param string  $return row -> single row | all -> all rows
	 * @param boolean $reset
	 *
	 * @return null|mixed[]
	 */
	public function findOneBy(array $where = [], bool $reset = true)
	{
		return $this->findBy($where, 'row', $reset);
	}

	/**
	 * An alias for builder update where
	 *
	 * @param array   $set   An associative array of update values
	 * @param mixed   $where
	 * @param integer $limit
	 *
	 * @return boolean    TRUE on success, FALSE on failure
	 */
	public function updateBy(array $set = null, $where = null, int $limit = null): bool
	{
		return $this->builder->update($this->doProtectFields($set), $where, $limit);
	}

	/**
	 * Deletes by conditions
	 *
	 * @param mixed[] $where The rows primary key(s)
	 * @param boolean $purge Allows overriding the soft deletes setting.
	 *
	 * @return mixed
	 */
	public function deleteBy(array $where = [], bool $purge = false)
	{
		$builder = $this->builder();

		if (count($where) > 0)
		{
			foreach ($where as $column => $condition)
			{
				if (is_array($condition))
				{
					$builder = $builder->whereIn($column, $condition);
				}
				else
				{
					$builder = $builder->where($column, $condition);
				}
			}
		}

		$this->trigger('beforeDelete', ['id' => $where, 'purge' => $purge]);

		if ($this->useSoftDeletes && ! $purge)
		{
			$set[$this->deletedField] = $this->setDate();

			if ($this->useTimestamps && ! empty($this->updatedField))
			{
				$set[$this->updatedField] = $this->setDate();
			}

			$result = $builder->update($set);
		}
		else
		{
			$result = $builder->delete();
		}

		$this->trigger('afterDelete', ['id' => $where, 'purge' => $purge, 'result' => $result, 'data' => null]);

		return $result;
	}

	/**
	 * Make sure to remove protected fields if exists
	 *
	 * @param mixed[] $data
	 *
	 * @return mixed[]
	 */
	protected function doProtectFields(array $data): array
	{
		// First validate to remove fields not in the table's schema
		$data = $this->validateFields($data);

		// Next filter out protected fields
		if (!empty($this->protectedFields))
		{
			foreach ($data as $key => $val)
			{
				if (in_array($key, $this->protectedFields))
				{
					unset($data[$key]);
				}
			}
		}

		// Then let the parent class handle allowedFields
		return parent::doProtectFields($data);
	}

	/**
	 * Make sure to remove invalid fields
	 *
	 * @param mixed[] $data
	 *
	 * @return mixed[]
	 */
	protected function validateFields(array $data): array
	{
		foreach ($data as $key => $val)
		{
			if (! array_key_exists($key, $this->fieldInfo))
			{
				unset($data[$key]);
			}
		}

		return $data;
	}

	/**
	 * Set the value of allowedFields
	 *
	 * @param mixed[] $allowedFields array of field to be allowed on saving, default is all
	 *
	 * @return $this
	 */
	public function setAllowedFields(array $allowedFields)
	{
		$this->allowedFields = $allowedFields;

		return $this;
	}

	/**
	 * Set the value of protectedFields
	 *
	 * @param mixed[] $protectedFields array of field to be allowed on saving, default is all
	 *
	 * @return $this
	 */
	public function setProtectedFields(array $protectedFields)
	{
		$this->protectedFields = $protectedFields;

		return $this;
	}

	/**
	 * Use Timestamps for table
	 *
	 * @param boolean $useTimestamps wether use timestamps or not
	 * @param string  $createdField  field name for created timestamp
	 * @param string  $updatedField  field name for updated timestamp
	 *
	 * @return $this
	 */
	public function useTimestamp(bool $useTimestamps = true, string $createdField = 'created_at', string $updatedField = 'updated_at')
	{
		$this->useTimestamps = $useTimestamps;
		$this->createdField  = $createdField;
		$this->updatedField  = $updatedField;

		return $this;
	}

	/**
	 * An alias to call parent resetSelect
	 *
	 * @return $this
	 */
	public function resetQuery()
	{
		$this->builder->getCompiledSelect(true);

		return $this;
	}

	/**
	 * This can be used when calling paginate
	 *
	 * @return string
	 */
	public function getDBGroup()
	{
		return $this->DBGroup;
	}

	/**
	 * ---------------------------------
	 * End of Override parent Methods
	 * ---------------------------------
	 */

	/**
	 * Collect Field Information from a table
	 *
	 * @param string $table
	 */
	protected function collectFieldInfo($table = null):void
	{
		$this->fieldInfo = [];

		$table = $table ?? $this->table;

		/**
		 * @var \CodeIgniter\Database\BaseConnection $db
		 */
		$db = $this->db;

		$fieldInfos = $db->getFieldData($table);

		if (is_array($fieldInfos))
		{
			foreach ($fieldInfos as $field)
			{
				$this->fieldInfo[$field->name] = $field;
			}
		}
	}

	/**
	 * Get All Field Information from current table
	 *
	 * @param string  $table
	 * @param boolean $primaryKey
	 *
	 * @return mixed
	 */
	public function getFieldInfo($table = null, $primaryKey = false)
	{
		$this->collectFieldInfo($table);

		if ($primaryKey)
		{
			foreach ($this->fieldInfo as $field)
			{
				if ($field->primary_key)
				{
					return $field->name;
				}
			}
		}

		return $this->fieldInfo;
	}

	/**
	 * Set Primary Key
	 *
	 * @param string $primaryKey primary key field name
	 */
	public function setPrimaryKey($primaryKey = null):self
	{
		/**
		 * @var \CodeIgniter\Database\BaseConnection $db
		 */
		$db = $this->db;

		if (! is_null($primaryKey) && $db->fieldExists($primaryKey, $this->table))
		{
			$this->primaryKey = $primaryKey;
		}
		else
		{
			$this->primaryKey = $this->fetchPrimaryKey();
		}
		return $this;
	}

	/**
	 * Guess the primary key for current table
	 *
	 * @param string $table if omit $table, system will find key automatically
	 *
	 * @return mixed
	 */
	private function fetchPrimaryKey($table = null)
	{
		return $this->getFieldInfo($table, true);
	}

	public function getPrimaryKey():string
	{
		return $this->primaryKey;
	}

	public function getTableName():string
	{
		return $this->table;
	}

	/**
	 * Set Properties of Model
	 *
	 * @param mixed[] $options property-value key pair of model
	 */
	public function setOptions($options = null):self
	{
		if (is_array($options))
		{
			foreach ($options as $key => $value)
			{
				if (property_exists($this, $key))
				{
					$this->$key = $value;
				}
			}
		}
		return $this;
	}

	 /**
	  * By default, Model will remove row when it is deleted
	  * When use Soft Delete, then delete will mark record as deleted
	  *
	  * @param boolean $useSoftDeletes
	  * @param string  $deletedField   field name will be used as deleted, by default it use 'deleted_at'
	  */
	public function useSoftDelete($useSoftDeletes = true, $deletedField = null):self
	{
		$this->useSoftDeletes     = $useSoftDeletes;
		$this->tempUseSoftDeletes = $useSoftDeletes;

		/**
		 * @var \CodeIgniter\Database\BaseConnection $db
		 */
		$db = $this->db;

		if (! is_null($deletedField) && $db->fieldExists($deletedField, $this->table))
		{
			$this->deletedField = $deletedField;
		}

		return $this;
	}

	/**
	 * Function to set Order by using array of multiple values
	 *
	 * @param array|mixed[] $orderBy only accept array of column order
	 * @param boolean       $escape
	 */
	public function setOrderBy($orderBy, bool $escape = null):self
	{
		if (is_array($orderBy))
		{
			foreach ($orderBy as $column => $direction)
			{
				$this->builder->orderBy($column, $direction, $escape);
			}
		}

		return $this;
	}

	/**
	 * Get Last row from the table
	 *
	 * @return mixed[]|null
	 */
	public function last()
	{
		$builder = $this->builder();

		if ($this->tempUseSoftDeletes === true)
		{
			$builder->where($this->table . '.' . $this->deletedField, null);
		}

		// Some databases, like PostgreSQL, need order
		// information to consistently return correct results.
		if (empty($builder->QBOrderBy) && ! empty($this->primaryKey))
		{
			$builder->orderBy($this->table . '.' . $this->primaryKey, 'asc');
		}

		$row = $builder->limit()->get();

		$row = $row->getLastRow($this->tempReturnType);

		$eventData = $this->trigger('afterFind', ['data' => $row]);

		$this->tempReturnType = $this->returnType;

		return $eventData['data'];
	}

	/**
	 * ---------------------------------
	 * Begining of Relationship Methods
	 * ---------------------------------
	 */

	/**
	 * Set filtering based on related table condition
	 *
	 * Example:
	 * $model->whereRelation('child_table', ['active' => 1]);
	 *
	 * @param string  $alias relationship alias name
	 * @param mixed[] $where array of filter conditions
	 */
	public function whereRelation($alias, $where):self
	{
		if (is_array($where))
		{
			$this->whereRelations[$alias] = $where;
		}
		return $this;
	}

	/**
	 * Build Query/Result with Relationship data attached
	 *
	 * Example:
	 * $model->with('child_table', ['name as child_name', 'status']);
	 *
	 * @param string       $relationship alias of relationship
	 * @param mixed[]|null $columns      column name to display from related table
	 */
	public function with($relationship, $columns = null):self
	{
		$this->relationships[$relationship] = $columns;

		if (! in_array($this->relationshipJoinCallback, $this->beforeFind))
		{
			$this->beforeFind[] = $this->relationshipJoinCallback;
		}

		if (! in_array($this->relationshipCallback, $this->afterFind))
		{
			$this->afterFind[] = $this->relationshipCallback;
		}

		return $this;
	}

	/**
	 * One To One / Many to One Relationship
	 *
	 * @param string $relatedTable related table
	 * @param string $relationId   by default it will use {singularRelatedTableName}_id
	 * @param string $alias        relationship alias, will be used to attach relationship, by default it will use $relatedTable name
	 */
	public function belongsTo($relatedTable, $relationId = null, $alias = null):self
	{
		$relationId = $relationId ?? singular($relatedTable) . '_id';
		$alias      = $alias ?? $relatedTable;

		$this->belongsTo[$alias] = [
			'table'      => $relatedTable,
			'primaryKey' => $this->fetchPrimaryKey($relatedTable),
			'relationId' => $relationId,
		];

		return $this;
	}

	/**
	 * One To Many Relationship
	 *
	 * @param string  $relatedTable related/child table
	 * @param string  $relationId   by default it will use {singularParentTableName}_id
	 * @param string  $alias        relationship alias, will be used to attach relationship, by default it will use $relatedTable name
	 * @param mixed[] $orderBy
	 */
	public function hasMany($relatedTable, $relationId = null, $alias = null, $orderBy = null):self
	{
		$relationId = $relationId ?? singular($this->table) . '_id';
		$alias      = $alias ?? $relatedTable;

		$this->hasMany[$alias] = [
			'table'      => $relatedTable,
			'primaryKey' => $this->fetchPrimaryKey($relatedTable),
			'relationId' => $relationId,
			'orderBy'    => is_array($orderBy) ? $orderBy : [],
		];

		return $this;
	}

	/**
	 * Join the relationship one-to-one / many-to-one
	 */
	protected function joinRelationship():void
	{
		/**
		 * @var string $alias
		 * @var array{
		 *  table:string,
		 *  primaryKey: string,
		 *  relationId: string
		 * } $relationInfo
		 */
		foreach ($this->belongsTo as $alias => $relationInfo)
		{
			if (array_key_exists($alias, $this->relationships))
			{
				$this->addRelation($alias, $relationInfo);
			}

			/**
			 * @var \StieTotalWin\DynaModel\Models\DynaModel $this
			 */
			$this->filterRelationship($alias, $this, $alias);
		}
	}

	/**
	 * Add Relation one-to-one / many-to-one
	 *
	 * @param string $alias
	 *
	 * @param array{
	 *  table:string,
	 *  primaryKey: string,
	 *  relationId: string,
	 *  orderBy?:mixed
	 * } $relationInfo
	 */
	protected function addRelation($alias, $relationInfo):void
	{
		$parentFields = $this->getFieldInfo();

		$columns = [];

		foreach ($parentFields as $column => $info)
		{
			$columns[] = $this->table . '.' . $column;
		}

		if (empty($this->relationships[$alias]))
		{
			$related = \StieTotalWin\DynaModel\DB::table($relationInfo['table']);

			$fields = $related->getFieldInfo();

			foreach ($fields as $field => $info)
			{
				if ($info->primary_key !== 1)
				{
					$columns[] = $alias . '.' . (array_key_exists($field, $parentFields) ? $field . ' AS ' . singular($alias) . '_' . $field : $field);
				}
			}
		}
		else
		{
			$selected = is_array($this->relationships[$alias]) ? $this->relationships[$alias] : explode(',', $this->relationships[$alias]);

			foreach ($selected as $field)
			{
				$columns[] = $alias . '.' . (array_key_exists(trim($field), $parentFields) ? trim($field) . ' AS ' . $alias . '_' . trim($field) : $field);
			}
		}

		$this->builder->select($columns, false);

		/**
		 * @var \CodeIgniter\Database\BaseConnection $db
		 */
		$db = $this->db;

		// Use proper prefixed table names in the join condition
		$this->builder->join(
			$db->prefixTable($relationInfo['table']) . " AS {$alias}", 
			"{$alias}.{$relationInfo['primaryKey']} = {$this->table}.{$relationInfo['relationId']}", 
			'left'
		);
	}

	/**
	 * Build Relationship One to Many
	 *
	 * @param mixed[]|null $data result data from builder
	 *
	 * @return mixed[]|null
	 */
	protected function buildRelationship($data)
	{
		if (empty($data['data']))
		{
			return $data;
		}

		/**
		 * @var array{
		 *  table:string,
		 *  primaryKey: string,
		 *  relationId: string,
		 *  orderBy:mixed[]
		 * } $relationInfo
		 *
		 * @var string $alias
		 */
		foreach ($this->hasMany as $alias => $relationInfo)
		{
			if (array_key_exists($alias, $this->relationships))
			{
				$parentData = $data['data'];

				if (! empty($parentData))
				{
					if ($this->isSingleResult($parentData))
					{
						$parentData = [$parentData];
					}

					$keys = $this->getColumns($parentData, $relationInfo['primaryKey']);

					$related = \StieTotalWin\DynaModel\DB::table($relationInfo['table']);

					$related->setOrderBy($relationInfo['orderBy']);
					$related->builder->whereIn($relationInfo['relationId'], $keys);

					$this->filterRelationship($alias, $related, $relationInfo['table']);

					$relationData = $related->findAll();

					$data['data'] = $this->attachRelationData($data, $relationData, $alias, $relationInfo['relationId'], $relationInfo['primaryKey']);
				}
			}
		}

		$this->resetRelationship();

		return $data;
	}

	/**
	 * Attach Relationship Data to Parent
	 *
	 * @param mixed[]      $resultData result array of parent table
	 * @param mixed[]|null $childData  result of related table
	 * @param string       $fieldAlias relationship alias
	 * @param string       $relationId foreign key in parent table
	 * @param string       $primaryKey primary key of related table, which related to foreign key
	 *
	 * @return mixed[]
	 */
	protected function attachRelationData($resultData, $childData, $fieldAlias, $relationId, $primaryKey)
	{
		$parentData = $resultData['data'] ?? $resultData;

		if (is_array($childData) && count($childData) > 0)
		{
			$relationData = array_group_by($childData, $relationId);

			$singleRow = $this->isSingleResult($parentData);

			if (! $singleRow)
			{
				foreach ($parentData as $i => $row)
				{
					if ($this->returnIsObject())
					{
						$parentData[$i]->{$fieldAlias} = [];

						$relationValue = $parentData[$i]->{$primaryKey};

						if (isset($relationData[$relationValue]))
						{
							$parentData[$i]->{$fieldAlias} = $relationData[$relationValue];
						}
					}
					else
					{
						$parentData[$i][$fieldAlias] = [];

						$relationValue = $parentData[$i][$primaryKey];

						if (isset($relationData[$relationValue]))
						{
							$parentData[$i][$fieldAlias] = $relationData[$relationValue];
						}
					}
				}
			}
			else
			{
				if ($this->returnIsObject())
				{
					$parentData->{$fieldAlias} = [];

					$relationValue = $parentData->{$primaryKey};

					if (isset($relationData[$relationValue]))
					{
						$parentData->{$fieldAlias} = $relationData[$relationValue];
					}
				}
				else
				{
					$parentData[$fieldAlias] = [];

					$relationValue = $parentData[$primaryKey];

					if (isset($relationData[$relationValue]))
					{
						$parentData[$fieldAlias] = $relationData[$relationValue];
					}
				}
			}
		}

		return $parentData;
	}

	/**
	 * @param null|mixed[] $resultData
	 */
	protected function isSingleResult($resultData = null):bool
	{
		$resultData = (array) $resultData;

		return isset($resultData['id']) && (is_numeric($resultData['id']) || is_string($resultData['id']));
	}
	/**
	 * Filter relationship data
	 *
	 * @param string                             $alias relationship alias
	 * @param \StieTotalWin\DynaModel\Models\DynaModel $model model to be filtered
	 * @param string                             $table
	 */
	protected function filterRelationship($alias, $model, $table):void
	{
		$builder = $model->builder();
		if (array_key_exists($alias, $this->whereRelations))
		{
			foreach ($this->whereRelations[$alias] as $where => $condition)
			{
				if (is_array($condition))
				{
					$builder->whereIn($table . '.' . $where, $condition);
				}
				else
				{
					$aliasWhere = [$table . '.' . $where => $condition];

					$builder->where($aliasWhere);
				}
			}
		}
	}

	/**
	 * Reset Relationship Callback to avoid re-building relationship in each find/get call
	 */
	protected function resetRelationship():void
	{
		if (($key = array_search($this->relationshipJoinCallback, $this->beforeFind)) !== false)
		{
			unset($this->beforeFind[$key]);
		}

		if (($key = array_search($this->relationshipCallback, $this->afterFind)) !== false)
		{
			unset($this->afterFind[$key]);
		}
	}

	/**
	 * Get all of the primary keys for an array of data.
	 *
	 * @param mixed[]     $data
	 * @param string|null $field
	 *
	 * @return mixed[]|null
	 */
	protected function getColumns($data, $field = null)
	{
		$field = $field ?? $this->primaryKey;

		$columns = [];
		foreach ($data as $row)
		{
			$value = false;

			if ($this->returnIsObject())
			{
				if (isset($row->{$field}))
				{
					$value = $row->{$field};
				}
			}
			else
			{
				if (isset($row[$field]))
				{
					$value = $row[$field];
				}
			}

			if ($value)
			{
				$columns[] = $value;
			}
		}
		return array_unique($columns);
	}

	protected function returnIsObject():bool
	{
		return $this->tempReturnType === 'object';
	}

	/**
	 * ------------------------------
	 * End of Relationship Methods
	 * ------------------------------
	 */
}
