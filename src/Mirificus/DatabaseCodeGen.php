<?php

/**
 * @package Mirificus
 */
namespace Mirificus;

/**
 * Will generate an entire ORM from a database source.
 * The adapter(s) to use is an issue still being worked out.
 *
 * @package Mirificus/DatabaseCodeGen
 * @property-read array $TableArray The array of analyzed tables
 * @property-read array $TypeTableArray The array of analyzed type tables
 * @property-read int $DatabaseIndex The index of this DB in Mirificus/Core::$Database[]
 */
class DatabaseCodeGen extends Codegen
{
    /**
     * Association table names
     * @var array $strAssociationTableNameArray
     */
    protected $strAssociationTableNameArray;

    /**
     * The database object to Codegen against.
     */
    protected $objDb;

    /**
     * Array of database tables from analysis
     */
    protected $objTableArray;

    /**
     * Array of database type tables from analysis
     * @var array $objTypeTableArray
     */
    protected $objTypeTableArray;

    /**
     * The index of this database to be read from Mirificus/Core::$Database[].
     * @var int $intDatabaseIndex
     */
    protected $intDatabaseIndex;

    /**
     * Tables to exclude from codegen
     */
    protected $strExcludedTableArray;

    /** @var string The delimiter to be used for parsing comments on the DB tables for being used as the name of Meta control's Label */
    //protected $strCommentMetaControlLabelDelimiter;
    // Uniquely Associated Objects
    protected $strAssociatedObjectPrefix;
    protected $strAssociatedObjectSuffix;
    // Table Suffixes
    protected $strTypeTableSuffixArray;
    protected $intTypeTableSuffixLengthArray;
    protected $strAssociationTableSuffix;
    protected $intAssociationTableSuffixLength;
    // Table Prefix
    protected $strStripTablePrefix;
    protected $intStripTablePrefixLength;
    // Exclude Patterns & Lists
    protected $strExcludePattern;
    protected $strExcludeListArray;
    // Include Patterns & Lists
    protected $strIncludePattern;
    protected $strIncludeListArray;
    // Manual Query (e.g. "Beta 2 Query") Support
    protected $blnManualQuerySupport = false;
    // Relationship Scripts
    protected $strRelationships;

    /**
     * If you are not using a DB table with defined FKs,
     * then you can use a relationship script to define them.
     * @var string $strRelationshipsScriptPath
     */
    protected $strRelationshipsScriptPath;

    /** @var string $strRelationshipsScriptFormat */
    protected $strRelationshipsScriptFormat;

    /** @var bool $blnRelationshipsIgnoreCase */
    protected $blnRelationshipsIgnoreCase;

    /** @var bool $blnRelationshipsScriptIgnoreCase */
    protected $blnRelationshipsScriptIgnoreCase;

    /** @var array $strRelationshipLinesApp */
    protected $strRelationshipLinesApp = array();

    /** @var array $strRelationshipLinesSql */
    protected $strRelationshipLinesSql = array();

    /**
     * @param SimpleXmlElement $objSettingsXml
     */
    public function __construct($objSettingsXml)
    {
        // Setup Local Arrays
        $this->strAssociationTableNameArray = array();
        $this->objTableArray = array();
        $this->objTypeTableArray = array();
        $this->strExcludedTableArray = array();

        // Set the DatabaseIndex
        $this->intDatabaseIndex = CodeGen::LookupSetting($objSettingsXml, null, 'index', Type::Integer);
        // Check to make sure things that are required are there
        if (!$this->intDatabaseIndex) {
            $this->strErrors .= "CodeGen Settings XML Fatal Error: databaseIndex was invalid or not set\r\n";
        }
        // Append Suffix/Prefixes
        $this->strClassPrefix = CodeGen::LookupSetting($objSettingsXml, 'className', 'prefix');
        $this->strClassSuffix = CodeGen::LookupSetting($objSettingsXml, 'className', 'suffix');
        $this->strAssociatedObjectPrefix = CodeGen::LookupSetting($objSettingsXml, 'associatedObjectName', 'prefix');
        $this->strAssociatedObjectSuffix = CodeGen::LookupSetting($objSettingsXml, 'associatedObjectName', 'suffix');

        // Table Type Identifiers
        $strTypeTableSuffixList = CodeGen::LookupSetting($objSettingsXml, 'typeTableIdentifier', 'suffix');
        $strTypeTableSuffixArray = explode(',', $strTypeTableSuffixList);
        if ($strTypeTableSuffixArray) {
            foreach ($strTypeTableSuffixArray as $strTypeTableSuffix) {
                $this->strTypeTableSuffixArray[] = trim($strTypeTableSuffix);
                $this->intTypeTableSuffixLengthArray[] = strlen(trim($strTypeTableSuffix));
            }
        }
        $this->strAssociationTableSuffix = CodeGen::LookupSetting($objSettingsXml, 'associationTableIdentifier', 'suffix');
        $this->intAssociationTableSuffixLength = strlen($this->strAssociationTableSuffix);

        // Stripping TablePrefixes
        $this->strStripTablePrefix = CodeGen::LookupSetting($objSettingsXml, 'stripFromTableName', 'prefix');
        $this->intStripTablePrefixLength = strlen($this->strStripTablePrefix);



        // Exclude/Include Tables
        $this->strExcludePattern = Codegen::LookupSetting($objSettingsXml, 'excludeTables', 'pattern');
        $strExcludeList = Codegen::LookupSetting($objSettingsXml, 'excludeTables', 'list');
        $this->strExcludeListArray = explode(',', $strExcludeList);
        array_walk($this->strExcludeListArray, create_function('&$val', '$val = trim($val);'));

        // Include Patterns
        $this->strIncludePattern = Codegen::LookupSetting($objSettingsXml, 'includeTables', 'pattern');
        $strIncludeList = Codegen::LookupSetting($objSettingsXml, 'includeTables', 'list');
        $this->strIncludeListArray = explode(',', $strIncludeList);
        array_walk($this->strIncludeListArray, create_function('&$val', '$val = trim($val);'));

        // ManualQuery Support
        $this->blnManualQuerySupport = Codegen::LookupSetting($objSettingsXml, 'manualQuery', 'support', Type::Boolean);

        // Relationship Scripts
        $this->strRelationships = Codegen::LookupSetting($objSettingsXml, 'relationships');
        $this->strRelationshipsScriptPath = Codegen::LookupSetting($objSettingsXml, 'relationshipsScript', 'filepath');
        $this->strRelationshipsScriptFormat = Codegen::LookupSetting($objSettingsXml, 'relationshipsScript', 'format');

        // Column Comment for MetaControlLabel setting.
        //$this->strCommentMetaControlLabelDelimiter = Codegen::LookupSetting($objSettingsXml, 'columnCommentForMetaControl', 'delimiter');
        // Aggregate RelationshipLinesApp and RelationshipLinesSql arrays
        if ($this->strRelationships) {
            $strLines = explode("\n", strtolower($this->strRelationships));
            if ($strLines) {
                foreach ($strLines as $strLine) {
                    $strLine = trim($strLine);

                    if (($strLine) &&
                            (strlen($strLine) > 2) &&
                            (substr($strLine, 0, 2) != '//') &&
                            (substr($strLine, 0, 2) != '--') &&
                            (substr($strLine, 0, 1) != '#')) {
                        $this->strRelationshipLinesApp[$strLine] = $strLine;
                    }
                }
            }
        }

        if ($this->strRelationshipsScriptPath) {
            if (!file_exists($this->strRelationshipsScriptPath)) {
                $this->strErrors .= sprintf("CodeGen Settings XML Fatal Error: relationshipsScript filepath \"%s\" does not exist\r\n", $this->strRelationshipsScriptPath);
            } else {
                $strScript = strtolower(trim(file_get_contents($this->strRelationshipsScriptPath)));
                switch (strtolower($this->strRelationshipsScriptFormat)) {
                    case 'app':
                        $strLines = explode("\n", $strScript);
                        if ($strLines) {
                            foreach ($strLines as $strLine) {
                                $strLine = trim($strLine);

                                if (($strLine) &&
                                        (strlen($strLine) > 2) &&
                                        (substr($strLine, 0, 2) != '//') &&
                                        (substr($strLine, 0, 2) != '--') &&
                                        (substr($strLine, 0, 1) != '#')) {
                                    $this->strRelationshipLinesApp[$strLine] = $strLine;
                                }
                            }
                        }
                        break;

                    case 'sql':
                        // Separate all commands in the script (separated by ";")
                        $strCommands = explode(';', $strScript);
                        if ($strCommands) {
                            foreach ($strCommands as $strCommand) {
                                $strCommand = trim($strCommand);

                                if ($strCommand) {
                                    // Take out all comment lines in the script
                                    $strLines = explode("\n", $strCommand);
                                    $strCommand = '';
                                    foreach ($strLines as $strLine) {
                                        $strLine = trim($strLine);
                                        if (($strLine) &&
                                                (substr($strLine, 0, 2) != '//') &&
                                                (substr($strLine, 0, 2) != '--') &&
                                                (substr($strLine, 0, 1) != '#')) {
                                            $strLine = str_replace('	', ' ', $strLine);
                                            $strLine = str_replace('        ', ' ', $strLine);
                                            $strLine = str_replace('       ', ' ', $strLine);
                                            $strLine = str_replace('      ', ' ', $strLine);
                                            $strLine = str_replace('     ', ' ', $strLine);
                                            $strLine = str_replace('    ', ' ', $strLine);
                                            $strLine = str_replace('   ', ' ', $strLine);
                                            $strLine = str_replace('  ', ' ', $strLine);
                                            $strLine = str_replace('  ', ' ', $strLine);
                                            $strLine = str_replace('  ', ' ', $strLine);
                                            $strLine = str_replace('  ', ' ', $strLine);
                                            $strLine = str_replace('  ', ' ', $strLine);

                                            $strCommand .= $strLine . ' ';
                                        }
                                    }

                                    $strCommand = trim($strCommand);
                                    if ((strpos($strCommand, 'alter table') === 0) &&
                                            (strpos($strCommand, 'foreign key') !== false)) {
                                        $this->strRelationshipLinesSql[$strCommand] = $strCommand;
                                    }
                                }
                            }
                        }
                        break;

                    default:
                        $this->strErrors .= sprintf("CodeGen Settings XML Fatal Error: relationshipsScript format \"%s\" is invalid (must be either \"qcubed\", \"qcodo\" or \"sql\")\r\n", $this->strRelationshipsScriptFormat);
                        break;
                }
            }
        }

        if ($this->strErrors) {
            return;
        }

        $this->AnalyzeDatabase();
    }

    /**
     * Analyze the Database
     */
    protected function AnalyzeDatabase()
    {
        // Set aside the Database object
        if (array_key_exists($this->intDatabaseIndex, Core::$Database)) {
            $this->objDb = Core::$Database[$this->intDatabaseIndex];
        }
        // @todo You are here...
    }

    /**
     * Generate Aggregate Helper
     * @param Codegen[] $objCodeGenArray Array of Codegen objects.
     */
    public static function GenerateAggregateHelper($objCodeGenArray)
    {
        $strToReturn = array();

        if (count($objCodeGenArray)) {
            // Standard ORM Tables
            $objTableArray = array();
            foreach ($objCodeGenArray as $objCodeGen) {
                $objCurrentTableArray = $objCodeGen->TableArray;
                if ($objCurrentTableArray) {
                    foreach ($objCurrentTableArray as $objTable) {
                        $objTableArray[$objTable->ClassName] = $objTable;
                    }
                }
            }

            $mixArgumentArray = array('objTableArray' => $objTableArray);
            if ($objCodeGenArray[0]->GenerateFiles('aggregate_db_orm', $mixArgumentArray)) {
                $strToReturn[] = 'Successfully generated Aggregate DB ORM file(s)';
            } else {
                $strToReturn[] = 'FAILED to generate Aggregate DB ORM file(s)';
            }

            // Type Tables
            $objTableArray = array();
            foreach ($objCodeGenArray as $objCodeGen) {
                $objCurrentTableArray = $objCodeGen->TypeTableArray;
                if ($objCurrentTableArray) {
                    foreach ($objCurrentTableArray as $objTable) {
                        $objTableArray[$objTable->ClassName] = $objTable;
                    }
                }
            }

            $mixArgumentArray = array('objTableArray' => $objTableArray);
            if ($objCodeGenArray[0]->GenerateFiles('aggregate_db_type', $mixArgumentArray)) {
                $strToReturn[] = 'Successfully generated Aggregate DB Type file(s)';
            } else {
                $strToReturn[] = 'FAILED to generate Aggregate DB Type file(s)';
            }
        }

        return $strToReturn;
    }

    /**
     * Override method to perform a property "Get"
     * This will get the value of $strName
     *
     * @param string $strName Name of the property to get.
     * @return mixed
     */
    public function __get($strName)
    {
        switch ($strName) {
            case 'TableArray':
                return $this->objTableArray;
            case 'TypeTableArray':
                return $this->objTypeTableArray;
            case 'DatabaseIndex':
                return $this->intDatabaseIndex;
//		   case 'CommentMetaControlLabelDelimiter':
//			   return $this->strCommentMetaControlLabelDelimiter;
            default:
                try {
                    return parent::__get($strName);
                } catch (CallerException $objExc) {
                    $objExc->IncrementOffset();
                    throw $objExc;
                }
        }
    }

    public function __set($strName, $mixValue)
    {
        try {
            switch($strName) {
                default:
                    return parent::__set($strName, $mixValue);
            }
        } catch (CallerException $objExc) {
            $objExc->IncrementOffset();
        }
    }
}