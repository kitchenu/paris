<?php
/**
 *
 * Paris
 *
 * http://github.com/j4mie/paris/
 *
 * A simple Active Record implementation built on top of Idiorm
 * ( http://github.com/j4mie/idiorm/ ).
 *
 * You should include Idiorm before you include this file:
 * require_once 'your/path/to/idiorm.php';
 *
 * BSD Licensed.
 *
 * Copyright (c) 2010, Jamie Matthews
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * * Redistributions of source code must retain the above copyright notice, this
 * list of conditions and the following disclaimer.
 *
 * * Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */
namespace Paris;

use Idiorm\ORM;

/**
 * Subclass of Idiorm's ORM class that supports
 * returning instances of a specified class rather
 * than raw instances of the ORM class.
 *
 * You shouldn't need to interact with this class
 * directly. It is used internally by the Model base
 * class.
 */
class ORMWrapper extends ORM
{
    /**
     * The wrapped findOne and findMany classes will
     * return an instance or instances of this class.
     *
     * @var string $class_name
     */
    protected $class_name;

    /**
     * Set the name of the class which the wrapped
     * methods should return instances of.
     *
     * @param  string $class_name
     * @return void
     */
    public function setClassName($class_name)
    {
        $this->class_name = $class_name;
    }

    /**
     * Add a custom filter to the method chain specified on the
     * model class. This allows custom queries to be added
     * to models. The filter should take an instance of the
     * ORM wrapper as its first argument and return an instance
     * of the ORM wrapper. Any arguments passed to this method
     * after the name of the filter will be passed to the called
     * filter function as arguments after the ORM class.
     *
     * @return ORMWrapper
     */
    public function filter()
    {
        $args = func_get_args();
        $filter_function = array_shift($args);
        array_unshift($args, $this);
        if (method_exists($this->class_name, $filter_function)) {
            return call_user_func_array([$this->class_name, $filter_function], $args);
        }
    }

    /**
     * Factory method, return an instance of this
     * class bound to the supplied table name.
     *
     * A repeat of content in parent::forTable, so that
     * created class is ORMWrapper, not ORM
     *
     * @param  string $table_name
     * @param  string $connection_name
     * @return ORMWrapper
     */
    public static function forTable($table_name, $connection_name = parent::DEFAULT_CONNECTION)
    {
        static::setupDb($connection_name);
        return new static($table_name, [], $connection_name);
    }

    /**
     * Method to create an instance of the model class
     * associated with this wrapper and populate
     * it with the supplied Idiorm instance.
     *
     * @param  ORM $orm
     * @return bool|Model
     */
    protected function createModelInstance($orm)
    {
        if ($orm === false) {
            return false;
        }
        $model = new $this->class_name();
        $model->setOrm($orm);
        return $model;
    }

    /**
     * Wrap Idiorm's findOne method to return
     * an instance of the class associated with
     * this wrapper instead of the raw ORM class.
     *
     * @param  null|integer $id
     * @return Model
     */
    public function findOne($id = null)
    {
        return $this->createModelInstance(parent::findOne($id));
    }

    /**
     * Wrap Idiorm's findMany method to return
     * an array of instances of the class associated
     * with this wrapper instead of the raw ORM class.
     *
     * @return Array
     */
    public function findMany()
    {
        $results = parent::findMany();
        foreach ($results as $key => $result) {
            $results[$key] = $this->createModelInstance($result);
        }
        return $results;
    }

    /**
     * Wrap Idiorm's create method to return an
     * empty instance of the class associated with
     * this wrapper instead of the raw ORM class.
     *
     *  return ORMWrapper|bool
     */
    public function create($data = null)
    {
        return $this->createModelInstance(parent::create($data));
    }
}
