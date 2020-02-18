<?php

namespace AndrewSouthwell\Migrate;

use AndrewSouthwell\Migrate\Inflector;

use Illuminate\Support\Facades\Cache;

class SchemaShell  {


    public $Schema;


    public function startup() {

        $name = $path = $connection = $plugin = null;

        if (!empty($this->params['name'])) {
            $name = $this->params['name'];
        } elseif (!empty($this->args[0]) && $this->args[0] !== 'snapshot') {
            $name = $this->params['name'] = $this->args[0];
        }

        if (strpos($name, '.')) {
            list($this->params['plugin'], $splitName) = pluginSplit($name);
            $name = $this->params['name'] = $splitName;
        }
        if ($name && empty($this->params['file'])) {
            $this->params['file'] = Inflector::underscore($name);
        } elseif (empty($this->params['file'])) {
            $this->params['file'] = 'schema.php';
        }
        if (strpos($this->params['file'], '.php') === false) {
            $this->params['file'] .= '.php';
        }
        $file = $this->params['file'];

        if (!empty($this->params['path'])) {
            $path = $this->params['path'];
        }

        if (!empty($this->params['connection'])) {
            $connection = $this->params['connection'];
        }
        if (!empty($this->params['plugin'])) {
            $plugin = $this->params['plugin'];
            if (empty($name)) {
                $name = $plugin;
            }
        }
        $name = Inflector::camelize($name);

        $this->Schema = new Schema(compact('name', 'path', 'file', 'connection', 'plugin'));
    }

/**
 * Read and output contents of schema object
 * path to read as second arg
 *
 * @return void
 */
    public function view() {
        $File = new File($this->Schema->path .'/'. $this->params['file']);
        if ($File->exists()) {
            $this->out($File->read());
            return $this->_stop();
        }
        $file = $this->Schema->path .'/'. $this->params['file'];
        $this->err(__d('cake_console', 'Schema file (%s) could not be found.', $file));
        return $this->_stop();
    }


    public function generate($config) {
        // $this->out(__d('cake_console', 'Generating Schema...'));
        $options = array();
        $options['models'] = false;
        $options['config'] = $config;
        $snapshot = false;
      
        $content = $this->Schema->read($options);

        $content['file'] = $this->params['file'];

        if (!empty($this->params['exclude']) && !empty($content)) {
            $excluded = CakeText::tokenize($this->params['exclude']);
            foreach ($excluded as $table) {
                unset($content['tables'][$table]);
            }
        }

        if ($snapshot === true) {
            $fileName = basename($this->params['file'], '.php');
            $Folder = new Folder($this->Schema->path);
            $result = $Folder->read();

            $numToUse = false;
            if (isset($this->params['snapshot'])) {
                $numToUse = $this->params['snapshot'];
            }

            $count = 0;
            if (!empty($result[1])) {
                foreach ($result[1] as $file) {
                    if (preg_match('/' . preg_quote($fileName) . '(?:[_\d]*)?\.php$/', $file)) {
                        $count++;
                    }
                }
            }

            if ($numToUse !== false) {
                if ($numToUse > $count) {
                    $count = $numToUse;
                }
            }

            $content['file'] = $fileName . '_' . $count . '.php';
        }

        return $content['tables'];
       
    }

    public function create() {
        list($Schema, $table) = $this->_loadSchema();
        $this->_create($Schema, $table);
    }

    public function update($config = []) {
    
        list($Schema, $table) = $this->_loadSchema();
        return $this->_update($Schema, $table, $config);
    }


    protected function _loadSchema() {
        $name = $plugin = null;
        if (!empty($this->params['name'])) {
            $name = $this->params['name'];
        }
        if (!empty($this->params['plugin'])) {
            $plugin = $this->params['plugin'];
        }

        $options = array(
            'name' => $name,
            'plugin' => $plugin,
            'connection' => $this->params['connection'],
        );

        if (!empty($this->params['snapshot'])) {
            $fileName = basename($this->Schema->file, '.php');
            $options['file'] = $fileName . '_' . $this->params['snapshot'] . '.php';
        }

        $Schema = (new class extends \AndrewSouthwell\Migrate\Schema {});

        $Schema->tables = $this->params['schema'];
      
        if (!$Schema) {
            $this->err(__d('cake_console', '<error>Error</error>: The chosen schema could not be loaded. Attempted to load:'));
           $this->err(__d('cake_console', '- file: %s', $this->Schema->path .'/'. $this->Schema->file));
            $this->err(__d('cake_console', '- name: %s', $this->Schema->name));
            return $this->_stop(2);
        }
        $table = null;
        if (isset($this->args[1])) {
            $table = $this->args[1];
        }
        return array(&$Schema, $table);
    }

/**
 * Create database from Schema object
 * Should be called via the run method
 *
 * @param Schema $Schema The schema instance to create.
 * @param string $table The table name.
 * @return void
 */
    protected function _create(Schema $Schema, $table = null) {


        $db = ConnectionManager::getDataSource($this->Schema->connection);

        $drop = $create = array();

        if (!$table) {
            foreach ($Schema->tables as $table => $fields) {
                $drop[$table] = $db->dropSchema($Schema, $table);
                $create[$table] = $db->createSchema($Schema, $table);
            }
        } elseif (isset($Schema->tables[$table])) {
            $drop[$table] = $db->dropSchema($Schema, $table);
            $create[$table] = $db->createSchema($Schema, $table);
        }
        if (empty($drop) || empty($create)) {
            $this->out(__d('cake_console', 'Schema is up to date.'));
            return $this->_stop();
        }

        $this->out("\n" . __d('cake_console', 'The following table(s) will be dropped.'));
        $this->out(array_keys($drop));

        if (!empty($this->params['yes']) ||
            $this->in(__d('cake_console', 'Are you sure you want to drop the table(s)?'), array('y', 'n'), 'n') === 'y'
        ) {
            $this->out(__d('cake_console', 'Dropping table(s).'));
            $this->_run($drop, 'drop', $Schema);
        }

        $this->out("\n" . __d('cake_console', 'The following table(s) will be created.'));
        $this->out(array_keys($create));

        if (!empty($this->params['yes']) ||
            $this->in(__d('cake_console', 'Are you sure you want to create the table(s)?'), array('y', 'n'), 'y') === 'y'
        ) {
            $this->out(__d('cake_console', 'Creating table(s).'));
            $this->_run($create, 'create', $Schema);
        }
        $this->out(__d('cake_console', 'End create.'));
    }

    protected function _update(&$Schema, $table = null, $config = []) {

        $db = ConnectionManager::getDataSource($this->Schema->connection, $config);
       
        $options = array();
        $options['models'] = false;
        $options['config'] = $config;
        
        $Old = $this->Schema->read($options);
        $compare = $this->Schema->compare($Old, $Schema);

        $contents = array();

        if (empty($table)) {
            foreach ($compare as $table => $changes) {
                if (isset($compare[$table]['create'])) {
                    $contents[$table] = $db->createSchema($Schema, $table);
                } else {
                    $contents[$table] = $db->alterSchema(array($table => $compare[$table]), $table);
                }
            }
        } elseif (isset($compare[$table])) {
            if (isset($compare[$table]['create'])) {
                $contents[$table] = $db->createSchema($Schema, $table);
            } else {
                $contents[$table] = $db->alterSchema(array($table => $compare[$table]), $table);
            }
        }
        // dd($contents);
        if (empty($contents)) {
            return false;
        }

        return $contents;

        $this->_run($contents, 'update', $Schema);

        Cache::flush();

    }

    protected function _run($contents, $event, Schema $Schema) {
        if (empty($contents)) {
            // dd($contents);
            // $this->err(__d('cake_console', 'Sql could not be run'));
            return;
        }
        // Configure::write('debug', 2);
        

        $db = ConnectionManager::getDataSource($this->Schema->connection);

        foreach ($contents as $table => $sql) {
            if (empty($sql)) {

            } else {
               
                if (!$Schema->before(array($event => $table))) {
                    return false;
                }
                $error = null;
                try {
                    $db->execute($sql);
                } catch (PDOException $e) {
                    $error = $table . ': ' . $e->getMessage();
                }

                $Schema->after(array($event => $table, 'errors' => $error));

                if (!empty($error)) {
                    $this->err($error);
                } 
                
            }
        }
    }


}
