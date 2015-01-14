<?php

namespace Doctrine\Tests\ORM\Functional\Ticket;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\ToolsException;

/**
 * This class makes tests on the correct use of a database schema when entities are stored
 *
 * @group DDC-2825
 */
class DDC2825Test extends \Doctrine\Tests\OrmFunctionalTestCase
{
    /**
     * {@inheritDoc}
     */
    protected function setup()
    {
        parent::setup();

        $platform = $this->_em->getConnection()->getDatabasePlatform();

        if ( ! $platform->supportsSchemas() && ! $platform->canEmulateSchemas()) {
            $this->markTestSkipped("This test is only useful for databases that support schemas or can emulate them.");
        }
    }

    /**
     * @dataProvider getTestedClasses
     *
     * @param string $className
     * @param string $expectedSchemaName
     * @param string $expectedTableName
     */
    public function testClassSchemaMappingsValidity($className, $expectedSchemaName, $expectedTableName)
    {
        $classMetadata   = $this->_em->getClassMetadata($className);
        $platform        = $this->_em->getConnection()->getDatabasePlatform();
        $quotedTableName = $this->_em->getConfiguration()->getQuoteStrategy()->getTableName($classMetadata, $platform);

        // Check if table name and schema properties are defined in the class metadata
        $this->assertEquals($expectedTableName, $classMetadata->table['name']);
        $this->assertEquals($expectedSchemaName, $classMetadata->table['schema']);

        if ($this->_em->getConnection()->getDatabasePlatform()->supportsSchemas()) {
            $fullTableName = sprintf('%s.%s', $expectedSchemaName, $expectedTableName);
        } else {
            $fullTableName = sprintf('%s__%s', $expectedSchemaName, $expectedTableName);
        }

        $this->assertEquals($fullTableName, $quotedTableName);

        // Checks sequence name validity
        $this->assertEquals(
            $fullTableName . '_' . $classMetadata->getSingleIdentifierColumnName() . '_seq',
            $classMetadata->getSequenceName($platform)
        );
    }

    /**
     * @dataProvider getTestedClasses
     *
     * @param string $className
     */
    public function testPersistenceOfEntityWithSchemaMapping($className)
    {
        try {
            $this->_schemaTool->createSchema(array($this->_em->getClassMetadata($className)));
        } catch (ToolsException $e) {
            // table already exists
        }

        $this->_em->persist(new $className());
        $this->_em->flush();
        $this->_em->clear();

        $this->assertCount(1, $this->_em->getRepository($className)->findAll());
    }

    /**
     * Data provider
     *
     * @return string[][]
     */
    public function getTestedClasses()
    {
        return array(
            array(DDC2825ClassWithExplicitlyDefinedSchema::CLASSNAME, 'myschema', 'mytable'),
            array(DDC2825ClassWithImplicitlyDefinedSchema::CLASSNAME, 'myschema', 'mytable2'),
            array(DDC2825ClassWithImplicitlyDefinedSchemaAndQuotedTableName::CLASSNAME, 'myschema', 'order'),
        );
    }
}

/**
 * @Entity
 * @Table(name="myschema.mytable")
 */
class DDC2825ClassWithExplicitlyDefinedSchema
{
    const CLASSNAME = __CLASS__;

    /**
     * Test with a quoted column name to check that sequence names are
     * correctly handled
     *
     * @Id @GeneratedValue
     * @Column(name="`number`", type="integer")
     *
     * @var integer
     */
    public $id;
}

/**
 * @Entity
 * @Table(name="mytable2",schema="myschema")
 */
class DDC2825ClassWithImplicitlyDefinedSchema
{
    const CLASSNAME = __CLASS__;

    /**
     * @Id @GeneratedValue
     * @Column(type="integer")
     *
     * @var integer
     */
    public $id;
}


/**
 * @Entity
 * @Table(name="myschema.order")
 */
class DDC2825ClassWithImplicitlyDefinedSchemaAndQuotedTableName
{
    const CLASSNAME = __CLASS__;

    /**
     * @Id @GeneratedValue
     * @Column(type="integer")
     *
     * @var integer
     */
    public $id;
}
