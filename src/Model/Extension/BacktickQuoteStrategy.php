<?php

namespace RssApp\Model\Extension;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\QuoteStrategy;

/**
 * Handles backtick escaping
 */
class BacktickQuoteStrategy implements QuoteStrategy
{

    /**
     * @param string $inputString
     *
     * @return string
     */
    private function wrapStringInBackticks(string $inputString): string
    {
        return "`$inputString`";
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnName($fieldName, ClassMetadata $class, AbstractPlatform $platform)
    {
        return $this->wrapStringInBackticks($class->fieldMappings[$fieldName]['columnName']);
    }

    /**
     * {@inheritdoc}
     */
    public function getTableName(ClassMetadata $class, AbstractPlatform $platform)
    {
        return $this->wrapStringInBackticks($class->table['name']);
    }

    /**
     * {@inheritdoc}
     */
    public function getSequenceName(array $definition, ClassMetadata $class, AbstractPlatform $platform)
    {
        return $definition['sequenceName'];
    }

    /**
     * {@inheritdoc}
     */
    public function getJoinColumnName(array $joinColumn, ClassMetadata $class, AbstractPlatform $platform)
    {
        return $joinColumn['name'];
    }

    /**
     * {@inheritdoc}
     */
    public function getReferencedJoinColumnName(array $joinColumn, ClassMetadata $class, AbstractPlatform $platform)
    {
        return $joinColumn['referencedColumnName'];
    }

    /**
     * {@inheritdoc}
     */
    public function getJoinTableName(array $association, ClassMetadata $class, AbstractPlatform $platform)
    {
        return $association['joinTable']['name'];
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentifierColumnNames(ClassMetadata $class, AbstractPlatform $platform)
    {
        return $class->getIdentifierColumnNames();
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnAlias($columnName, $counter, AbstractPlatform $platform, ?ClassMetadata $class = null)
    {
        return $platform->getSQLResultCasing($columnName . '_' . $counter);
    }
}
