<?php

namespace AndrewSouthwell\Migrate;

use AndrewSouthwell\Migrate\ConnectionManager;

class Model 
{
    /**
     * @var mixed
     */
    public $Behaviors = null;

    /**
     * @var array
     */
    public $__backAssociation = [];

    /**
     * @var array
     */
    public $__backContainableAssociation = [];

    /**
     * @var array
     */
    public $__backInnerAssociation = [];

    /**
     * @var array
     */
    public $__backOriginalAssociation = [];

    /**
     * @var mixed
     */
    public $__safeUpdateMode = false;

    /**
     * @var mixed
     */
    public $actsAs = null;

    /**
     * @var mixed
     */
    public $alias = null;

    /**
     * @var array
     */
    public $belongsTo = [];

    /**
     * @var mixed
     */
    public $cacheQueries = false;

    /**
     * @var mixed
     */
    public $cacheSources = true;

    /**
     * @var array
     */
    public $data = [];

    /**
     * @var mixed
     */
    public $displayField = null;

    /**
     * @var array
     */
    public $findMethods = [
        'all'       => true, 'first' => true, 'count'    => true,
        'neighbors' => true, 'list'  => true, 'threaded' => true
    ];

    /**
     * @var mixed
     */
    public $findQueryType = null;

    /**
     * @var array
     */
    public $hasAndBelongsToMany = [];

    /**
     * @var array
     */
    public $hasMany = [];

    /**
     * @var array
     */
    public $hasOne = [];

    /**
     * @var mixed
     */
    public $id = false;

    /**
     * @var mixed
     */
    public $name = null;

    /**
     * @var mixed
     */
    public $order = null;

    /**
     * @var mixed
     */
    public $plugin = null;

    /**
     * @var mixed
     */
    public $primaryKey = null;

    /**
     * @var int
     */
    public $recursive = 1;

    /**
     * @var mixed
     */
    public $schemaName = null;

    /**
     * @var mixed
     */
    public $table = false;

    /**
     * @var mixed
     */
    public $tablePrefix = null;

    /**
     * @var array
     */
    public $tableToModel = [];

    /**
     * @var mixed
     */
    public $useConsistentAfterFind = true;

    /**
     * @var string
     */
    public $useDbConfig = 'default';

    /**
     * @var mixed
     */
    public $useTable = null;

    /**
     * @var array
     */
    public $validate = [];

    /**
     * @var mixed
     */
    public $validationDomain = null;

    /**
     * @var array
     */
    public $validationErrors = [];

    /**
     * @var array
     */
    public $virtualFields = [];

    /**
     * @var array
     */
    public $whitelist = [];

    /**
     * @var array
     */
    protected $_associationKeys = [
        'belongsTo'           => ['className', 'foreignKey', 'conditions', 'fields', 'order', 'counterCache'],
        'hasOne'              => ['className', 'foreignKey', 'conditions', 'fields', 'order', 'dependent'],
        'hasMany'             => ['className', 'foreignKey', 'conditions', 'fields', 'order', 'limit', 'offset', 'dependent', 'exclusive', 'finderQuery', 'counterQuery'],
        'hasAndBelongsToMany' => ['className', 'joinTable', 'with', 'foreignKey', 'associationForeignKey', 'conditions', 'fields', 'order', 'limit', 'offset', 'unique', 'finderQuery']
    ];

    /**
     * @var array
     */
    protected $_associations = ['belongsTo', 'hasOne', 'hasMany', 'hasAndBelongsToMany'];

    /**
     * @var mixed
     */
    protected $_eventManager = null;

    /**
     * @var mixed
     */
    protected $_insertID = null;

    /**
     * @var mixed
     */
    protected $_schema = null;

    /**
     * @var mixed
     */
    protected $_sourceConfigured = false;

    /**
     * @var mixed
     */
    protected $_validator = null;

    /**
     * @param $id
     * @param false $table
     * @param null $ds
     */
    public function __construct($id = false, $table = null, $ds = null)
    {
       
        if (is_array($id)) {
            extract(array_merge(
                [
                    'id'   => $this->id, 'table'   => $this->useTable, 'ds'  => $this->useDbConfig,
                    'name' => $this->name, 'alias' => $this->alias, 'plugin' => $this->plugin
                ],
                $id
            ));
        }

        if ($this->plugin === null) {
            $this->plugin = (isset($plugin) ? $plugin : $this->plugin);
        }

        if ($this->name === null) {
            $this->name = (isset($name) ? $name : get_class($this));
        }

        if ($this->alias === null) {
            $this->alias = (isset($alias) ? $alias : $this->name);
        }

        if ($this->primaryKey === null) {
            $this->primaryKey = 'id';
        }

        // ClassRegistry::addObject($this->alias, $this);

        $this->id = $id;
        unset($id);

        if ($table === false) {
            $this->useTable = false;
        } elseif ($table) {
            $this->useTable = $table;
        }

        if ($ds !== null) {
            $this->useDbConfig = $ds;
        }

        if (is_subclass_of($this, 'AppModel')) {
            $merge       = ['actsAs', 'findMethods'];
            $parentClass = get_parent_class($this);
            if ($parentClass !== 'AppModel') {
                // $this->_mergeVars($merge, $parentClass);
            }
            // $this->_mergeVars($merge, 'AppModel');
        }
        // $this->_mergeVars(['findMethods'], 'Model');

        // $this->Behaviors = new BehaviorCollection();

        if ($this->useTable !== false) {

            if ($this->useTable === null) {
                $this->useTable = Inflector::tableize($this->name);
            }

            if ( ! $this->displayField) {
                unset($this->displayField);
            }
            $this->table                      = $this->useTable;
            $this->tableToModel[$this->table] = $this->alias;
        } elseif ($this->table === false) {
            $this->table = Inflector::tableize($this->name);
        }

        if ($this->tablePrefix === null) {
            unset($this->tablePrefix);
        }

    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        if ($name === 'displayField') {
            return $this->displayField = $this->hasField(['title', 'name', $this->primaryKey]);
        }

        if ($name === 'tablePrefix') {
            $this->setDataSource();
            if (property_exists($this, 'tablePrefix') && ! empty($this->tablePrefix)) {
                return $this->tablePrefix;
            }

            return $this->tablePrefix = null;
        }

        if (isset($this->{$name})) {
            return $this->{$name};
        }
    }

    public function getDataSource()
    {
        if ( ! $this->_sourceConfigured && $this->useTable !== false) {
            $this->_sourceConfigured = true;
            $this->setSource($this->useTable);
        }

        return ConnectionManager::getDataSource($this->useDbConfig);
    }

    /**
     * @param $field
     * @return mixed
     */
    public function schema($field = false)
    {
        if ($this->useTable !== false && ( ! is_array($this->_schema) || $field === true)) {
            $db               = $this->getDataSource();
            $db->cacheSources = ($this->cacheSources && $db->cacheSources);
            if (method_exists($db, 'describe')) {
                $this->_schema = $db->describe($this);
            }
        }

        if ( ! is_string($field)) {
            return $this->_schema;
        }

        if (isset($this->_schema[$field])) {
            return $this->_schema[$field];
        }

        return null;
    }

    /**
     * @param $dataSource
     */
    public function setDataSource($dataSource = null)
    {
        $oldConfig = $this->useDbConfig;

        if ($dataSource) {
            $this->useDbConfig = $dataSource;
        }

        $db = ConnectionManager::getDataSource($this->useDbConfig);
        if ( ! empty($oldConfig) && isset($db->config['prefix'])) {
            $oldDb = ConnectionManager::getDataSource($oldConfig);

            if ( ! isset($this->tablePrefix) || ( ! isset($oldDb->config['prefix']) || $this->tablePrefix === $oldDb->config['prefix'])) {
                $this->tablePrefix = $db->config['prefix'];
            }
        } elseif (isset($db->config['prefix'])) {
            $this->tablePrefix = $db->config['prefix'];
        }

        $schema            = $db->getSchemaName();
        $defaultProperties = get_class_vars(get_class($this));
        if (isset($defaultProperties['schemaName'])) {
            $schema = $defaultProperties['schemaName'];
        }
        $this->schemaName = $schema;
    }

    /**
     * Sets a custom table for your model class. Used by your controller to select a database table.
     *
     * @param  string                $tableName Name of the custom table
     * @throws MissingTableException when database table $tableName is not found on data source
     * @return void
     */
    public function setSource($tableName)
    {
        $this->setDataSource($this->useDbConfig);
        $db = ConnectionManager::getDataSource($this->useDbConfig);

        if (method_exists($db, 'listSources')) {
            $restore          = $db->cacheSources;
            $db->cacheSources = ($restore && $this->cacheSources);
            $sources          = $db->listSources();
            $db->cacheSources = $restore;

            if (is_array($sources) && ! in_array(strtolower($this->tablePrefix . $tableName), array_map('strtolower', $sources))) {
                throw new MissingTableException([
                    'table' => $this->tablePrefix . $tableName,
                    'class' => $this->alias,
                    'ds'    => $this->useDbConfig
                ]);
            }

            if ($sources) {
                $this->_schema = null;
            }
        }

        $this->table                      = $this->useTable                      = $tableName;
        $this->tableToModel[$this->table] = $this->alias;
    }
}
