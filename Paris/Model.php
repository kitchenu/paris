<?php

namespace Paris;

/**
 * Model base class. Your model objects should extend
 * this class. A minimal subclass would look like:
 *
 * class Widget extends Model {
 * }
 *
 */
class Model
{
    // Default ID column for all models. Can be overridden by adding
    // a public static _id_column property to your model classes.
    const DEFAULT_ID_COLUMN = 'id';
    // Default foreign key suffix used by relationship methods
    const DEFAULT_FOREIGN_KEY_SUFFIX = '_id';

    /**
     * Set a prefix for model names. This can be a namespace or any other
     * abitrary prefix such as the PEAR naming convention.
     *
     * @example Model::$auto_prefix_models = 'MyProject_MyModels_'; //PEAR
     * @example Model::$auto_prefix_models = '\MyProject\MyModels\'; //Namespaces
     *
     * @var string $auto_prefix_models
     */
    public static $auto_prefix_models = null;

    /**
     * The ORM instance used by this model 
     * instance to communicate with the database.
     *
     * @var ORM $orm
     */
    public $orm;

    /**
     * Retrieve the value of a static property on a class. If the
     * class or the property does not exist, returns the default
     * value supplied as the third argument (which defaults to null).
     *
     * @param  string      $class_name
     * @param  string      $property
     * @param  null|string $default
     * @return string
     */
    protected static function getStaticProperty($class_name, $property, $default = null)
    {
        if (!class_exists($class_name) || !property_exists($class_name, $property)) {
            return $default;
        }
        $properties = get_class_vars($class_name);
        return $properties[$property];
    }

    /**
     * Static method to get a table name given a class name.
     * If the supplied class has a public static property
     * named $_table, the value of this property will be
     * returned.
     *
     * If not, the class name will be converted using
     * the _class_name_to_table_name method method.
     *
     * If public static property $_table_use_short_name == true
     * then $class_name passed to _class_name_to_table_name is
     * stripped of namespace information.
     *
     * @param  string $class_name
     * @return string
     */
    protected static function getTableName($class_name)
    {
        $specified_table_name = static::getStaticProperty($class_name, '_table');
        $use_short_class_name = static::getStaticProperty($class_name, '_table_use_short_name');

        if ($use_short_class_name) {
            $exploded_class_name = explode('\\', $class_name);
            $class_name = end($exploded_class_name);
        }

        if (is_null($specified_table_name)) {
            return static::classNameToTableName($class_name);
        }
        return $specified_table_name;
    }

    /**
     * Convert a namespace to the standard PEAR underscore format.
     *
     * Then convert a class name in CapWords to a table name in
     * lowercase_with_underscores.
     *
     * Finally strip doubled up underscores
     *
     * For example, CarTyre would be converted to car_tyre. And
     * Project\Models\CarTyre would be project_models_car_tyre.
     *
     * @param  string $class_name
     * @return string
     */
    protected static function classNameToTableName($class_name)
    {
        return strtolower(preg_replace(
                ['/\\\\/', '/(?<=[a-z])([A-Z])/', '/__/'], ['_', '_$1', '_'], ltrim($class_name, '\\')
        ));
    }

    /**
     * Return the ID column name to use for this class. If it is
     * not set on the class, returns null.
     *
     * @param  string $class_name
     * @return string|null
     */
    protected static function getIdColumnName($class_name)
    {
        return static::getStaticProperty($class_name, '_id_column', static::DEFAULT_ID_COLUMN);
    }

    /**
     * Build a foreign key based on a table name. If the first argument
     * (the specified foreign key column name) is null, returns the second
     * argument (the name of the table) with the default foreign key column
     * suffix appended.
     *
     * @param  string $specified_foreign_key_name
     * @param  string $table_name
     * @return string
     */
    protected static function buildForeignKeyName($specified_foreign_key_name, $table_name)
    {
        if (!is_null($specified_foreign_key_name)) {
            return $specified_foreign_key_name;
        }
        return $table_name . static::DEFAULT_FOREIGN_KEY_SUFFIX;
    }

    /**
     * Factory method used to acquire instances of the given class.
     * The class name should be supplied as a string, and the class
     * should already have been loaded by PHP (or a suitable autoloader
     * should exist). This method actually returns a wrapped ORM object
     * which allows a database query to be built. The wrapped ORM object is
     * responsible for returning instances of the correct class when
     * its find_one or find_many methods are called.
     *
     * @param  string      $class_name
     * @param  null|string $connection_name
     * @return ORMWrapper
     */
    public static function factory($class_name, $connection_name = null)
    {
        $class_name = static::$auto_prefix_models . $class_name;
        $table_name = static::getTableName($class_name);

        if ($connection_name == null) {
            $connection_name = static::getStaticProperty(
                    $class_name, '_connection_name', ORMWrapper::DEFAULT_CONNECTION
            );
        }
        $wrapper = ORMWrapper::forTable($table_name, $connection_name);
        $wrapper->setClassName($class_name);
        $wrapper->useIdColumn(static::getIdColumnName($class_name));
        return $wrapper;
    }

    /**
     * Internal method to construct the queries for both the hasOne and
     * hasMany methods. These two types of association are identical; the
     * only difference is whether find_one or find_many is used to complete
     * the method chain.
     *
     * @param  string      $associated_class_name
     * @param  null|string $foreign_key_name
     * @param  null|string $foreign_key_name_in_current_models_table
     * @param  null|string $connection_name
     * @return ORMWrapper
     */
    protected function hasOneOrMany($associated_class_name, $foreign_key_name = null, $foreign_key_name_in_current_models_table = null, $connection_name = null)
    {
        $base_table_name = static::getTableName(get_class($this));
        $foreign_key_name = static::buildForeignKeyName($foreign_key_name, $base_table_name);

        $where_value = ''; //Value of foreign_table.{$foreign_key_name} we're 
        //looking for. Where foreign_table is the actual 
        //database table in the associated model.

        if (is_null($foreign_key_name_in_current_models_table)) {
            //Match foreign_table.{$foreign_key_name} with the value of 
            //{$this->_table}.{$this->id()}
            $where_value = $this->id();
        } else {
            //Match foreign_table.{$foreign_key_name} with the value of 
            //{$this->_table}.{$foreign_key_name_in_current_models_table}
            $where_value = $this->$foreign_key_name_in_current_models_table;
        }

        return static::factory($associated_class_name, $connection_name)->where($foreign_key_name, $where_value);
    }

    /**
     * Helper method to manage one-to-one relations where the foreign
     * key is on the associated table.
     *
     * @param  string      $associated_class_name
     * @param  null|string $foreign_key_name
     * @param  null|string $foreign_key_name_in_current_models_table
     * @param  null|string $connection_name
     * @return ORMWrapper
     */
    protected function hasOne($associated_class_name, $foreign_key_name = null, $foreign_key_name_in_current_models_table = null, $connection_name = null)
    {
        return $this->hasOneOrMany($associated_class_name, $foreign_key_name, $foreign_key_name_in_current_models_table, $connection_name);
    }

    /**
     * Helper method to manage one-to-many relations where the foreign
     * key is on the associated table.
     *
     * @param  string      $associated_class_name
     * @param  null|string $foreign_key_name
     * @param  null|string $foreign_key_name_in_current_models_table
     * @param  null|string $connection_name
     * @return ORMWrapper
     */
    protected function hasMany($associated_class_name, $foreign_key_name = null, $foreign_key_name_in_current_models_table = null, $connection_name = null)
    {
        return $this->hasOneOrMany($associated_class_name, $foreign_key_name, $foreign_key_name_in_current_models_table, $connection_name);
    }

    /**
     * Helper method to manage one-to-one and one-to-many relations where
     * the foreign key is on the base table.
     *
     * @param  string      $associated_class_name
     * @param  null|string $foreign_key_name
     * @param  null|string $foreign_key_name_in_associated_models_table
     * @param  null|string $connection_name
     * @return $this|null
     */
    protected function belongsTo($associated_class_name, $foreign_key_name = null, $foreign_key_name_in_associated_models_table = null, $connection_name = null)
    {
        $associated_table_name = static::getTableName(static::$auto_prefix_models . $associated_class_name);
        $foreign_key_name = static::buildForeignKeyName($foreign_key_name, $associated_table_name);
        $associated_object_id = $this->$foreign_key_name;

        $desired_record = null;

        if (is_null($foreign_key_name_in_associated_models_table)) {
            //"{$associated_table_name}.primary_key = {$associated_object_id}"
            //NOTE: primary_key is a placeholder for the actual primary key column's name
            //in $associated_table_name
            $desired_record = static::factory($associated_class_name, $connection_name)->whereIdIs($associated_object_id);
        } else {
            //"{$associated_table_name}.{$foreign_key_name_in_associated_models_table} = {$associated_object_id}"
            $desired_record = static::factory($associated_class_name, $connection_name)->where($foreign_key_name_in_associated_models_table, $associated_object_id);
        }

        return $desired_record;
    }

    /**
     * Helper method to manage many-to-many relationships via an intermediate model. See
     * README for a full explanation of the parameters.
     *
     * @param  string      $associated_class_name
     * @param  null|string $join_class_name
     * @param  null|string $key_to_base_table
     * @param  null|string $key_to_associated_table
     * @param  null|string $key_in_base_table
     * @param  null|string $key_in_associated_table
     * @param  null|string $connection_name
     * @return ORMWrapper
     */
    protected function hasManyThrough($associated_class_name, $join_class_name = null, $key_to_base_table = null, $key_to_associated_table = null, $key_in_base_table = null, $key_in_associated_table = null, $connection_name = null)
    {
        $base_class_name = get_class($this);

        // The class name of the join model, if not supplied, is
        // formed by concatenating the names of the base class
        // and the associated class, in alphabetical order.
        if (is_null($join_class_name)) {
            $model = explode('\\', $base_class_name);
            $model_name = end($model);
            if (substr($model_name, 0, strlen(static::$auto_prefix_models)) == static::$auto_prefix_models) {
                $model_name = substr($model_name, strlen(static::$auto_prefix_models), strlen($model_name));
            }
            $class_names = [$model_name, $associated_class_name];
            sort($class_names, SORT_STRING);
            $join_class_name = join("", $class_names);
        }

        // Get table names for each class
        $base_table_name = static::getTableName($base_class_name);
        $associated_table_name = static::getTableName(static::$auto_prefix_models . $associated_class_name);
        $join_table_name = static::getTableName(static::$auto_prefix_models . $join_class_name);

        // Get ID column names
        $base_table_id_column = (is_null($key_in_base_table)) ?
            static::getIdColumnName($base_class_name) :
            $key_in_base_table;
        $associated_table_id_column = (is_null($key_in_associated_table)) ?
            static::getIdColumnName(static::$auto_prefix_models . $associated_class_name) :
            $key_in_associated_table;

        // Get the column names for each side of the join table
        $key_to_base_table = static::buildForeignKeyName($key_to_base_table, $base_table_name);
        $key_to_associated_table = static::buildForeignKeyName($key_to_associated_table, $associated_table_name);

        /*
          "   SELECT {$associated_table_name}.*
          FROM {$associated_table_name} JOIN {$join_table_name}
          ON {$associated_table_name}.{$associated_table_id_column} = {$join_table_name}.{$key_to_associated_table}
          WHERE {$join_table_name}.{$key_to_base_table} = {$this->$base_table_id_column} ;"
         */

        return static::factory($associated_class_name, $connection_name)
                ->select("{$associated_table_name}.*")
                ->join($join_table_name, ["{$associated_table_name}.{$associated_table_id_column}", '=', "{$join_table_name}.{$key_to_associated_table}"])
                ->where("{$join_table_name}.{$key_to_base_table}", $this->$base_table_id_column);
        ;
    }

    /**
     * Set the wrapped ORM instance associated with this Model instance.
     *
     * @param  ORM $orm
     * @return void
     */
    public function setOrm($orm)
    {
        $this->orm = $orm;
    }

    /**
     * Magic getter method, allows $model->property access to data.
     *
     * @param  string $property
     * @return null|string
     */
    public function __get($property)
    {
        return $this->orm->get($property);
    }

    /**
     * Magic setter method, allows $model->property = 'value' access to data.
     *
     * @param  string $property
     * @param  string $value
     * @return void
     */
    public function __set($property, $value)
    {
        $this->orm->set($property, $value);
    }

    /**
     * Magic isset method, allows isset($model->property) to work correctly.
     *
     * @param  string $property
     * @return bool
     */
    public function __isset($property)
    {
        return $this->orm->__isset($property);
    }

    /**
     * Getter method, allows $model->get('property') access to data
     *
     * @param  string $property
     * @return string
     */
    public function get($property)
    {
        return $this->orm->get($property);
    }

    /**
     * Setter method, allows $model->set('property', 'value') access to data.
     *
     * @param  string|array $property
     * @param  string|null  $value
     * @return Model
     */
    public function set($property, $value = null)
    {
        $this->orm->set($property, $value);
        return $this;
    }

    /**
     * Setter method, allows $model->setExpr('property', 'value') access to data.
     *
     * @param  string|array $property
     * @param  string|null  $value
     * @return Model
     */
    public function setExpr($property, $value = null)
    {
        $this->orm->setExpr($property, $value);
        return $this;
    }

    /**
     * Check whether the given field has changed since the object was created or saved
     *
     * @param  string $property
     * @return bool
     */
    public function isDirty($property)
    {
        return $this->orm->isDirty($property);
    }

    /**
     * Check whether the model was the result of a call to create() or not
     *
     * @return bool
     */
    public function isNew()
    {
        return $this->orm->isNew();
    }

    /**
     * Wrapper for Idiorm's as_array method.
     *
     * @return Array
     */
    public function asArray()
    {
        $args = func_get_args();
        return call_user_func_array([$this->orm, 'asArray'], $args);
    }

    /**
     * Save the data associated with this model instance to the database.
     *
     * @return null
     */
    public function save()
    {
        return $this->orm->save();
    }

    /**
     * Delete the database row associated with this model instance.
     *
     * @return null
     */
    public function delete()
    {
        return $this->orm->delete();
    }

    /**
     * Get the database ID of this model instance.
     *
     * @return integer
     */
    public function id()
    {
        return $this->orm->id();
    }

    /**
     * Hydrate this model instance with an associative array of data.
     * WARNING: The keys in the array MUST match with columns in the
     * corresponding database table. If any keys are supplied which
     * do not match up with columns, the database will throw an error.
     *
     * @param  Array $data
     * @return void
     */
    public function hydrate($data)
    {
        $this->orm->hydrate($data)->forceAllDirty();
    }

    /**
     * Calls static methods directly on the ORMWrapper
     *
     * @param  string $method
     * @param  Array  $parameters
     * @return Array
     */
    public static function __callStatic($method, $parameters)
    {
        throw new MethodMissingException("Method $method() does not exist in class " . get_class($this));
    }

    /**
     * Magic method to capture calls to undefined class methods.
     * In this case we are attempting to convert camel case formatted
     * methods into underscore formatted methods.
     *
     * This allows us to call methods using camel case and remain
     * backwards compatible.
     *
     * @param  string $method
     * @param  array  $arguments
     * @throws MethodMissingException
     * @return bool|ORMWrapper
     */
    public function __call($method, $arguments)
    {
        throw new MethodMissingException("Method $method() does not exist in class " . get_class($this));
    }
}
