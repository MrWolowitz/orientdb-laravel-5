<?php namespace Sgpatil\Orientdb\Eloquent;

use Sgpatil\Orientdb\Eloquent\Relations\HasOne;
use Sgpatil\Orientdb\Eloquent\Relations\HasMany;
use Sgpatil\Orientdb\Eloquent\Relations\MorphTo;
use Sgpatil\Orientdb\Eloquent\Relations\BelongsTo;
use Sgpatil\Orientdb\Eloquent\Relations\HyperMorph;
use Sgpatil\Orientdb\Query\Builder as QueryBuilder;
use Sgpatil\Orientdb\Eloquent\Relations\MorphMany;
use Sgpatil\Orientdb\Eloquent\Relations\MorphedByOne;
use Sgpatil\Orientdb\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model as IlluminateModel;
use Sgpatil\Orientdb\Eloquent\Builder as EloquentBuilder;

abstract class Model extends IlluminateModel {

    /**
     * The node label
     *
     * @var string|array
     */
    protected $label = null;

    /**
     * Set the node label for this model
     *
     * @param  string|array  $labels
     */
    public function setLabel($label)
    {
        return $this->label = $label;
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  Sgpatil\Orientdb\Query\Builder $query
     * @return Sgpatil\Orientdb\Eloquent\Builder|static
     */
    public function newEloquentBuilder($query)
    {
        return new EloquentBuilder($query);
    }

    /**
	 * Get a new query builder instance for the connection.
	 *
	 * @return Sgpatil\Orientdb\Query\Builder
	 */
	protected function newBaseQueryBuilder()
	{
		$conn = $this->getConnection();
                                        $grammar = $conn->getQueryGrammar();
		return new QueryBuilder($conn, $grammar);
	}

    /**
	 * Get the format for database stored dates.
	 *
	 * @return string
	 */
    protected function getDateFormat()
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * Get the node labels
     *
     * @return array
     */
    public function getDefaultNodeLabel()
    {
        // by default we take the $label, otherwise we consider $table
        // for Eloquent's backward compatibility
        $label = (empty($this->label)) ? $this->table : $this->label;

        // The label is accepted as an array for a convenience so we need to
        // convert it to a string separated by ':' following Orientdb's labels
        if (is_array($label) && ! empty($label)) return $label;

        // since this is not an array, it is assumed to be a string
        // we check to see if it follows Orientdb's labels naming (User:Fan)
        // and return an array exploded from the ':'
        if ( ! empty($label))
        {
            $label = array_filter(explode(':', $label));

            // This trick re-indexes the array
            array_splice($label, 0, 0);

            return $label;
        }

        // Since there was no label for this model
        // we take the fully qualified (namespaced) class name and
        // pluck out backslashes to get a clean 'WordsUp' class name and use it as default
        return array(str_replace('\\', '', get_class($this)));
    }


    /**
     * Define an inverse one-to-one or many relationship.
     *
     * @param  string  $related
     * @param  string  $foreignKey
     * @param  string  $otherKey
     * @param  string  $relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function belongsTo($related, $foreignKey = null, $otherKey = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if (is_null($relation))
        {
            list(, $caller) = debug_backtrace(false);

            $relation = $caller['function'];
        }

        // If no foreign key was supplied, we can use a backtrace to guess the proper
        // foreign key name by using the name of the calling class, which
        // will be uppercased and used as a relationship label
        if (is_null($foreignKey))
        {
            $foreignKey = strtoupper($caller['class']);
        }

        $instance = new $related;

        // Once we have the foreign key names, we'll just create a new Eloquent query
        // for the related models and returns the relationship instance which will
        // actually be responsible for retrieving and hydrating every relations.
        $query = $instance->newQuery();

        $otherKey = $otherKey ?: $instance->getKeyName();

        return new BelongsTo($query, $this, $foreignKey, $otherKey, $relation);
    }

    /**
     * Define a one-to-one relationship.
     *
     * @param  string  $related
     * @param  string  $foreignKey
     * @param  string  $localKey
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function hasOne($related, $foreignKey = null, $otherKey = null, $relation = null)
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $instance = new $related;

        $otherKey = $otherKey ?: $this->getKeyName();

        return new HasOne($instance->newQuery(), $this, $instance->getTable().'.'.$foreignKey, $otherKey, $relation);
        
        
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if (is_null($relation))
        {
            list(, $caller) = debug_backtrace(false);

            $relation = $caller['function'];
        }

        // If no foreign key was supplied, we can use a backtrace to guess the proper
        // foreign key name by using the name of the calling class, which
        // will be uppercased and used as a relationship label
        if (is_null($foreignKey))
        {
            $foreignKey = strtoupper($caller['class']);
        }

        $instance = new $related;

        // Once we have the foreign key names, we'll just create a new Eloquent query
        // for the related models and returns the relationship instance which will
        // actually be responsible for retrieving and hydrating every relations.
        $query = $instance->newQuery();

        $otherKey = $otherKey ?: $instance->getKeyName();

        return new HasOne($query, $this, $foreignKey, $otherKey, $relation);
    }

    /**
     * Define a one-to-many relationship.
     *
     * @param  string  $related
     * @param  string  $type
     * @param  string  $key
     * @return \Sgpatil\Orientdb\Eloquent\Relations\HasMany
     */
    public function hasMany($related, $type = null, $key = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if (is_null($relation))
        {
            list(, $caller) = debug_backtrace(false);

            $relation = $caller['function'];
        }

        // the $type should be the UPPERCASE of the relation not the foreign key.
        $type = $type ?: mb_strtoupper($relation);

        $instance = new $related;

        $key = $key ?: $this->getKeyName();

        return new HasMany($instance->newQuery(), $this, $type, $key, $relation);
    }

    /**
     * Define a many-to-many relationship.
     *
     * @param  string  $related
     * @param  string  $type
     * @param  string  $key
     * @param  string  $relation
     * @return \Sgpatil\Orientdb\Eloquent\Relations\BelongsToMany
     */
    public function belongsToMany($related, $table = null, $foreignKey = null, $otherKey = null, $relation = null)
    {
        // To escape the error:
        // PHP Strict standards:  Declaration of Sgpatil\Orientdb\Eloquent\Model::belongsToMany() should be
        //      compatible with Illuminate\Database\Eloquent\Model::belongsToMany()
        // We'll just map them in with the variables we want.
        $type     = $table;
        $key      = $foreignKey;
        $relation = $otherKey;
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if (is_null($relation))
        {
            list(, $caller) = debug_backtrace(false);

            $relation = $caller['function'];
        }

        // If no $key was provided we will consider it the key name of this model.
        $key = $key ?: $this->getKeyName();

        // If no relationship type was provided, we can use the previously traced back
        // $relation being the function name that called this method and using it in its
        // all uppercase form.
        if (is_null($type))
        {
            $type = mb_strtoupper($relation);
        }

        $instance = new $related;

        // Now we're ready to create a new query builder for the related model and
        // the relationship instances for the relation. The relations will set
        // appropriate query constraint and entirely manages the hydrations.
        $query = $instance->newQuery();

        return new BelongsToMany($query, $this, $type, $key, $relation);
    }

    /**
     * Create a new HyperMorph relationship.
     *
     * @param  \Sgpatil\Orientdb\Eloquent\Model  $model
     * @param  string $related
     * @param  string $type
     * @param  string $morphType
     * @param  string $relation
     * @param  string $key
     * @return \Sgpatil\Orientdb\Eloquent\Relations\HyperMorph
     */
    public function hyperMorph($model, $related, $type = null, $morphType = null, $relation = null, $key = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if (is_null($relation))
        {
            list(, $caller) = debug_backtrace(false);

            $relation = $caller['function'];
        }

        // If no $key was provided we will consider it the key name of this model.
        $key = $key ?: $this->getKeyName();

        // If no relationship type was provided, we can use the previously traced back
        // $relation being the function name that called this method and using it in its
        // all uppercase form.
        if (is_null($type))
        {
            $type = mb_strtoupper($relation);
        }

        $instance = new $related;

        // Now we're ready to create a new query builder for the related model and
        // the relationship instances for the relation. The relations will set
        // appropriate query constraint and entirely manages the hydrations.
        $query = $instance->newQuery();

        return new HyperMorph($query, $this, $model, $type, $morphType, $key, $relation);
    }

    /**
     * Define a many-to-many relationship.
     *
     * @param  string  $related
     * @param  string  $type
     * @param  string  $key
     * @param  string  $relation
     * @return \Sgpatil\Orientdb\Eloquent\Relations\MorphMany
     */
    public function morphMany($related, $name, $type = null, $id = null, $localKey = null)
    {
        // To escape the error:
        // Strict standards: Declaration of Sgpatil\Orientdb\Eloquent\Model::morphMany() should be
        //          compatible with Illuminate\Database\Eloquent\Model::morphMany()
        // We'll just map them in with the variables we want.
        $relationType = $name;
        $key          = $type;
        $relation     = $id;

        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if (is_null($relation))
        {
            list(, $caller) = debug_backtrace(false);

            $relation = $caller['function'];
        }

        // If no $key was provided we will consider it the key name of this model.
        $key = $key ?: $this->getKeyName();

        // If no relationship type was provided, we can use the previously traced back
        // $relation being the function name that called this method and using it in its
        // all uppercase form.
        if (is_null($relationType))
        {
            $relationType = mb_strtoupper($relation);
        }

        $instance = new $related;

        // Now we're ready to create a new query builder for the related model and
        // the relationship instances for the relation. The relations will set
        // appropriate query constraint and entirely manages the hydrations.
        $query = $instance->newQuery();

        return new MorphMany($query, $this, $relationType, $key, $relation);
    }

    /**
     * Create an inverse one-to-one polymorphic relationship with specified model and relation.
     *
     * @param  \Sgpatil\Orientdb\Eloquent\Model $related
     * @param  string $type
     * @param  string $key
     * @param  string $relation
     * @return \Sgpatil\Orientdb\Eloquent\Relations\MorphedByOne
     */
    public function morphedByOne($related, $type, $key = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if (is_null($relation))
        {
            list(, $caller) = debug_backtrace(false);

            $relation = $caller['function'];
        }

        // If no $key was provided we will consider it the key name of this model.
        $key = $key ?: $this->getKeyName();

        // If no relationship type was provided, we can use the previously traced back
        // $relation being the function name that called this method and using it in its
        // all uppercase form.
        if (is_null($type))
        {
            $type = mb_strtoupper($relation);
        }

        $instance = new $related;

        // Now we're ready to create a new query builder for the related model and
        // the relationship instances for the relation. The relations will set
        // appropriate query constraint and entirely manages the hydrations.
        $query = $instance->newQuery();

        return new MorphedByOne($query, $this, $type, $key, $relation);
    }

    /**
     * Define a polymorphic, inverse one-to-one or many relationship.
     *
     * @param  string  $name
     * @param  string  $type
     * @param  string  $id
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function morphTo($name = null, $type = null, $id = null)
    {

        // When the name and the type are specified we'll return a MorphedByOne
        // relationship with the given arguments since we know the kind of Model
        // and relationship type we're looking for.
        if ($name && $type)
        {
            // Determine the relation function name out of the back trace
            list(, $caller) = debug_backtrace(false);
            $relation = $caller['function'];
            return $this->morphedByOne($name, $type, $id, $relation);
        }

        // If no name is provided, we will use the backtrace to get the function name
        // since that is most likely the name of the polymorphic interface. We can
        // use that to get both the class and foreign key that will be utilized.
        if (is_null($name))
        {
            list(, $caller) = debug_backtrace(false);

            $name = snake_case($caller['function']);
        }

        list($type, $id) = $this->getMorphs($name, $type, $id);

        // If the type value is null it is probably safe to assume we're eager loading
        // the relationship. When that is the case we will pass in a dummy query as
        // there are multiple types in the morph and we can't use single queries.
        if (is_null($class = $this->$type))
        {
            return new MorphTo(
                $this->newQuery(), $this, $id, null, $type, $name
            );
        }

        // If we are not eager loading the relationship we will essentially treat this
        // as a belongs-to style relationship since morph-to extends that class and
        // we will pass in the appropriate values so that it behaves as expected.
        else
        {
            $instance = new $class;

            return new MorphTo(
                with($instance)->newQuery(), $this, $id, $instance->getKeyName(), $type, $name
            );
        }
    }

    public static function createWith(array $attributes, array $relations)
    {
        $query = static::query();

        return $query->createWith($attributes, $relations);
    }
    /**
     * Get the polymorphic relationship columns.
     *
     * @param  string  $name
     * @param  string  $type
     * @param  string  $id
     * @return array
     */
    protected function getMorphs($name, $type, $id)
    {
        $type = $type ?: $name.'_type';

        $id = $this->getkeyname();

        return array($type, $id);
    }

    /**
     * Get the table qualified key name.
     *
     * @return string
     */
    public function getQualifiedKeyName()
    {
        return $this->getKeyName();
    }

    public function addTimestamps()
    {
        $this->updateTimestamps();
    }

    public function getDirty()
    {
        $dirty = parent::getDirty();

        // We need to remove the primary key from the dirty attributes since primary keys
        // never change and when updating it shouldn't be part of the attribtues.
        if (isset($dirty[$this->primaryKey])) unset($dirty[$this->primaryKey]);

        return $dirty;
    }
    
     /**
     * Save the model to the database.
     *
     * @param  array  $options
     * @return bool
     */
    public function save(array $options = [])
    {
        
        $query = $this->newQueryWithoutScopes();

        // If the "saving" event returns false we'll bail out of the save and return
        // false, indicating that the save failed. This provides a chance for any
        // listeners to cancel save operations if validations fail or whatever.
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        // If the model already exists in the database we can just update our record
        // that is already in this database using the current IDs in this "where"
        // clause to only update this model. Otherwise, we'll just insert them.
        if ($this->exists) {
          
            $saved = $this->performUpdate($query, $options);
        }

        // If the model is brand new, we'll insert it into our database and set the
        // ID attribute on the model to the value of the newly inserted row's ID
        // which is typically an auto-increment value managed by the database.
        else {
            $saved = $this->performInsert($query, $options);
        }
        if ($saved) {
            $this->finishSave($options);
        }

        return $saved;
    }

}
