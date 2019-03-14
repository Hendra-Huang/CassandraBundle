<?php

namespace CassandraBundle\Cassandra\ORM\Mapping;

class ClassMetadata
{
    /**
     * READ-ONLY: The name of the entity class.
     *
     * @var string
     */
    public $name;

    /**
     * The name of the custom repository class used for the entity class.
     * (Optional).
     *
     * @var string
     */
    public $customRepositoryClassName;

    /**
     * READ-ONLY: The field mappings of the class.
     * Keys are field names and values are mapping definitions.
     *
     * The mapping definition array has the following values:
     *
     * - <b>fieldName</b> (string)
     * The name of the field in the Entity.
     *
     * - <b>type</b> (string)
     * The type name of the mapped field. Can be one of Doctrine's mapping types
     * or a custom mapping type.
     *
     * - <b>columnName</b> (string, optional)
     * The column name. Optional. Defaults to the field name.
     *
     * @var array
     */
    public $fieldMappings = [];

    /**
     * READ-ONLY: An array of field names. Used to look up field names from column names.
     * Keys are column names and values are field names.
     * This is the reverse lookup map of $_columnNames.
     *
     * @var array
     */
    public $fieldNames = [];

    /**
     * READ-ONLY: The primary table definition. The definition is an array with the
     * following entries:.
     *
     * name => <tableName>
     * indexes => array
     * primaryKeys => array
     *
     * @var array
     */
    public $table;

    /**
     * Creates a string representation of this instance.
     *
     * @return string the string representation of this instance
     *
     * @todo Construct meaningful string representation.
     */
    public function __toString()
    {
        return __CLASS__.'@'.spl_object_hash($this);
    }
}
