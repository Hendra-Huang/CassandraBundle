<?php

namespace CassandraBundle\Cassandra\ORM;

class SchemaException extends \Exception
{
    /**
     * @param string $mode
     *
     * @return ORMException
     */
    public static function wrongFormatForNumericTableOption($optionName, $optionValue)
    {
        return new self(sprintf('Invalid numeric format for table option "%s", "%s" is not numeric', $optionName, $optionValue));
    }
}
