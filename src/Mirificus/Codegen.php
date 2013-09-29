<?php

/**
 * @package Mirificus
 */
namespace Mirificus;

/**
 * Generates PHP classes from various structured data sources.
 * @package Mirificus\Codegen
 */
abstract class Codegen {
	/**
	 * This static array contains an array of active and executed codegen objects, based
	 * on the XML Configuration passed in to Mirificus\Codegen::Run().
	 * @var CodeGen[] Array of active/executed codegen objects.
	 * @static
	 */
	public static $CodeGenArray;

	/**
	 * @var string $SettingsFilePath Path to the settings XML file.
	 * @static
	 */
	public static $SettingsFilePath;

	/**
	 * This is the SimpleXML representation of the Settings XML file
	 * @var SimpleXmlElement $SettingsXml
	 * @access protected
	 * @static
	 */
	protected static $SettingsXml;

	/**
	 * @var string $TemplateEscapeBegin Template Escape Pattern Begin
	 * @access protected
	 * @static
	 */
	protected static $TemplateEscapeBegin;

	/**
	 * @var string $TemplateEscapeBeginLength
	 * @access protected
	 * @static
	 */
	protected static $TemplateEscapeBeginLength;

	/**
	 * @var string $TemplateEscapeEnd Template Escape Pattern End
	 * @access protected
	 * @static
	 */
	protected static $TemplateEscapeEnd;

	/**
	 * @var string $TemplateEscapeEndLength
	 * @access protected
	 * @static
	 */
	protected static $TemplateEscapeEndLength;

	/**
	 * Application Name (from CodeGen Settings)
	 * @todo Ditch this, who cares?
	 * @var string $ApplicationName
	 * @access protected
	 * @static
	 */
	protected static $ApplicationName;

	// Relative Paths to the default Template and Subtemplate Directories
	const TemplatesPath = '/codegen/templates/';
//	const SubTemplatesPath = '/codegen/subtemplates/';

	// Relative Paths to the CUSTOM Template and Subtemplate Directories
	const TemplatesPathCustom = '/codegen/templates/';

	const DebugMode = false;

	/**
	 * Array of directories to be excluded in codegen (lower cased)
	 * @var string[]
	 * @access protected
	 * @static
	 */
	protected static $DirectoriesToExcludeArray = array('.','..','.svn','svn','cvs','.git');

	/** @var string $strClassPrefix Class Name Prefix */
	protected $strClassPrefix;

	/**
	 * @var string $strClassSuffix Class Name Suffix
	 * @access protected
	 */
	protected $strClassSuffix;

	/**
	 * @var string $strErrors Holds an error log.
	 * @access protected
	 */
	protected $strErrors;

	/**
	 * Run the codegen with a settings file. The settings file is in XML.
	 * @param string $strSettingsXmlFilePath The path to the settings xml.
	 * @return void
	 * @static
	 */
	public static function Run($strSettingsXmlFilePath) {
		static::$CodeGenArray = array();
		static::$SettingsFilePath = $strSettingsXmlFilePath;

		if (!file_exists($strSettingsXmlFilePath) || !is_file($strSettingsXmlFilePath)) {
			echo 'FATAL ERROR: CodeGen Settings XML File (' . $strSettingsXmlFilePath . ') was not found.';
			return;
		}

		static::$SettingsXml = new \SimpleXMLElement(file_get_contents($strSettingsXmlFilePath));

		// Iterate Through DataSources
		if (static::$SettingsXml->dataSources->asXML()){
			foreach (static::$SettingsXml->dataSources->children() as $objChildNode) {
				switch (dom_import_simplexml($objChildNode)->nodeName) {
					case 'database':
						static::$CodeGenArray[] = new DatabaseCodeGen($objChildNode);
						break;
//					case 'restService':
//						static::$CodeGenArray[] = new Mirificus\RestServiceCodeGen($objChildNode);
//						break;
					default:
						echo sprintf("Invalid Data Source Type in CodeGen Settings XML File (%s): %s\r\n",
							$strSettingsXmlFilePath, dom_import_simplexml($objChildNode)->nodeName);
						break;
				}
			}
		}

		// Set the Template Escaping
		static::$TemplateEscapeBegin = static::LookupSetting(static::$SettingsXml, 'templateEscape', 'begin');
		static::$TemplateEscapeEnd = static::LookupSetting(static::$SettingsXml, 'templateEscape', 'end');
		static::$TemplateEscapeBeginLength = strlen(static::$TemplateEscapeBegin);
		static::$TemplateEscapeEndLength = strlen(static::$TemplateEscapeEnd);

		// Application Name - No one cares about this setting.
		static::$ApplicationName = static::LookupSetting(static::$SettingsXml, 'name', 'application');
	}

	/**
	 * This will lookup either the node value (if no attributename is passed in) or the attribute value
	 * for a given Tag.  Node Searches only apply from the root level of the configuration XML being passed in
	 * (e.g. it will not be able to lookup the tag name of a grandchild of the root node)
	 *
	 * If No Tag Name is passed in, then attribute/value lookup is based on the root node, itself.
	 *
	 * @static
	 * @param SimpleXmlElement $objNode
	 * @param string $strTagName
	 * @param string $strAttributeName
	 * @param string $strType
	 * @return mixed the return type depends on the Type you pass in to $strType
	 */
	static protected function LookupSetting($objNode, $strTagName, $strAttributeName = null, $strType = Type::String) {
	   if ($strTagName){
		   $objNode = $objNode->$strTagName;
	   }
	   if ($strAttributeName) {
		   switch ($strType) {
			   case Type::Integer:
				   try {
					   $intToReturn = Type::Cast($objNode[$strAttributeName], Type::Integer);
					   return $intToReturn;
				   } catch (Exception $objExc) {
					   return null;
				   }
			   case Type::Boolean:
				   try {
					   $blnToReturn = Type::Cast($objNode[$strAttributeName], Type::Boolean);
					   return $blnToReturn;
				   } catch (Exception $objExc) {
					   return null;
				   }
			   default:
				   $strToReturn = trim(Type::Cast($objNode[$strAttributeName], Type::String));
				   return $strToReturn;
		   }
	   } else {
		   $strToReturn = trim(Type::Cast($objNode, Type::String));
		   return $strToReturn;
	   }
	}

	/**
	 * Generate an aggregate of the data.
	 * @return array
	 * @static
	 */
	public static function GenerateAggregate() {
	   $objDbOrmCodeGen = array();
	   $objRestServiceCodeGen = array();

	   foreach (static::$CodeGenArray as $objCodeGen) {
			if ($objCodeGen instanceof DatabaseCodeGen) {
			   array_push($objDbOrmCodeGen, $objCodeGen);
			}
			if ($objCodeGen instanceof RestServiceCodeGen) {
			   array_push($objRestServiceCodeGen, $objCodeGen);
			}
	   }

	   $strToReturn = array();
	   array_merge($strToReturn, DatabaseCodeGen::GenerateAggregateHelper($objDbOrmCodeGen));
	//			array_push($strToReturn, QRestServiceCodeGen::GenerateAggregateHelper($objRestServiceCodeGen));

	   return $strToReturn;
	}

	/**
	 * Given a template prefix (e.g. db_orm_, db_type_, rest_, soap_, etc.), pull
	 * all the _*.tpl templates from any subfolders of the template prefix in Mirificus\CodeGen::TemplatesPath and Mirificus\CodeGen::TemplatesPathCustom,
	 * and call GenerateFile() on each one.  If there are any template files that reside
	 * in BOTH TemplatesPath AND TemplatesPathCustom, then only use the TemplatesPathCustom one (which
	 * in essence overrides the one in TemplatesPath).
	 *
	 * @param string $strTemplatePrefix The prefix of the templates you want to generate against.
	 * @param mixed[] $mixArgumentArray Array of arguments to send to EvaluateTemplate.
	 * @return boolean Whether or not all the files generated successfully.
	 */
	public function GenerateFiles($strTemplatePrefix, $mixArgumentArray) {
	   // Make sure both our Template and TemplateCustom paths are valid
	   $strTemplatePath = sprintf('%s%s%s', __DIR__ , static::TemplatesPath, $strTemplatePrefix);
	   if (!is_dir($strTemplatePath))
		   throw new \Exception(sprintf("CodeGen::TemplatesPath does not appear to be a valid directory:\r\n%s", $strTemplatePath));

	   $strTemplatePathCustom = sprintf('%s%s', __DIR__, static::TemplatesPathCustom);
	   if (!is_dir($strTemplatePathCustom))
		   throw new \Exception(sprintf("CodeGen::TemplatesPathCustom does not appear to be a valid directory:\r\n%s", $strTemplatePathCustom));
	   $strTemplatePathCustom .= $strTemplatePrefix;

	   // Create an array of arrays of standard templates and custom (override) templates to process
	   // Index by [module_name][filename] => true/false where
	   // module name (e.g. "class_gen", "form_delegates) is name of folder within the prefix (e.g. "db_orm")
	   // filename is the template filename itself (in a _*.tpl format)
	   // true = override (use custom) and false = do not override (use standard)
	   $strTemplateArray = array();

	   // Go through standard templates first
	   $objDirectory = opendir($strTemplatePath);
	   while ($strModuleName = readdir($objDirectory)) {
		   if (!in_array(strtolower($strModuleName), static::$DirectoriesToExcludeArray) &&
			   is_dir($strTemplatePath . '/' . $strModuleName)) {

			   // We're in a valid Module -- look for any _*.tpl template files
			   $objModuleDirectory = opendir($strTemplatePath . '/' . $strModuleName);
			   while ($strFilename = readdir($objModuleDirectory))
				   if ((String::FirstCharacter($strFilename) == '_') &&
					   (
						   (substr($strFilename, strlen($strFilename) - 4) == '.tpl') ||
						   (substr($strFilename, strlen($strFilename) - 8) == '.tpl.php'))
					   )
					   $strTemplateArray[$strModuleName][$strFilename] = false;
		   }
	   }

	   // Go through and create or override with any custom templates
	   if (is_dir($strTemplatePathCustom)) {
		   $objDirectory = opendir($strTemplatePathCustom);
		   while ($strModuleName = readdir($objDirectory)) {
			   if (!in_array(strtolower($strModuleName), static::$DirectoriesToExcludeArray) &&
				   is_dir($strTemplatePathCustom . '/' . $strModuleName)) {
				   $objModuleDirectory = opendir($strTemplatePathCustom . '/' . $strModuleName);
				   while ($strFilename = readdir($objModuleDirectory))
					   if ((String::FirstCharacter($strFilename) == '_') &&
						   (
							   (substr($strFilename, strlen($strFilename) - 4) == '.tpl') ||
							   (substr($strFilename, strlen($strFilename) - 8) == '.tpl.php'))
						   )
						   $strTemplateArray[$strModuleName][$strFilename] = true;
			   }
		   }
	   }

	   // Finally, iterate through all the template files and call GenerateFile to Evaluate/Generate/Save them.
	   $blnSuccess = true;
	   foreach ($strTemplateArray as $strModuleName => $strFileArray)
		   foreach ($strFileArray as $strFilename => $blnOverrideFlag)
			   if (!$this->GenerateFile($strTemplatePrefix . '/' . $strModuleName, $strFilename, $blnOverrideFlag, $mixArgumentArray))
				   $blnSuccess = false;

	   return $blnSuccess;
	}

	/**
	 * Generate a single file from the data source
	 *
	 * @param string $strModuleName
	 * @param string $strFilename
	 * @param boolean $blnOverrideFlag Whether we are using the _core template, or using a custom one.
	 * @param mixed[] $mixArgumentArray
	 * @param boolean $blnSave Whether or not to actually perform the save.
	 * @return mixed The evaluated template or boolean save success.
	 */
	public function GenerateFile($strModuleName, $strFilename, $blnOverrideFlag, $mixArgumentArray, $blnSave = true) {
		// Figure out the actual TemplateFilePath
		if ($blnOverrideFlag) {
			$strTemplateFilePath = __DIR__ . static::TemplatesPathCustom . $strModuleName . '/' . $strFilename;
		} else {
			$strTemplateFilePath = __DIR__ . static::TemplatesPath . $strModuleName . '/' . $strFilename;
		}
		// Setup Debug/Exception Message
		if (static::DebugMode) { echo "Evaluating $strTemplateFilePath\r\n"; }
		$strError = 'Template\'s first line must be <template OverwriteFlag="boolean" DocrootFlag="boolean" TargetDirectory="string" DirectorySuffix="string" TargetFileName="string"/>: ' . $strTemplateFilePath;

		// Check to see if the template file exists, and if it does, Load It
		if (!file_exists($strTemplateFilePath)) {
			throw new CallerException('Template File Not Found: ' . $strTemplateFilePath);
		}
		$strTemplate = file_get_contents($strTemplateFilePath);

		// Evaluate the Template
		if (substr($strFilename, strlen($strFilename) - 8) == '.tpl.php')  {
			// make sure paths are set up to pick up included files from both the override directory and _core directory
			$strSearchPath = static::TemplatesPathCustom . $strModuleName . PATH_SEPARATOR .
					static::TemplatesPath . $strModuleName . PATH_SEPARATOR .
					get_include_path();
			set_include_path ($strSearchPath);
			if ($strSearchPath != get_include_path()) {
				throw new CallerException('Can\'t override include path. Make sure your apache or server settings allow include paths to be overriden. ' );
			}
			$strTemplate = $this->EvaluatePHP($strTemplateFilePath, $strModuleName, $mixArgumentArray);
			restore_include_path();
		} else {
			$strTemplate = $this->EvaluateTemplate($strTemplate, $strModuleName, $mixArgumentArray);
		}

		// Parse out the first line (which contains path and overwriting information)
		$intPosition = strpos($strTemplate, "\n");
		if ($intPosition === false) {
			throw new \Exception($strError);
		}
		$strFirstLine = trim(substr($strTemplate, 0, $intPosition));
		$strTemplate = substr($strTemplate, $intPosition + 1);

		$objTemplateXml = null;
		// Attempt to Parse the First Line as XML
		try {
			@$objTemplateXml = new SimpleXMLElement($strFirstLine);
		} catch (\Exception $objExc) {}

		if (is_null($objTemplateXml) || (!($objTemplateXml instanceof SimpleXMLElement))) {
			throw new \Exception($strError);
		}
		$blnOverwriteFlag = Type::Cast($objTemplateXml['OverwriteFlag'], QType::Boolean);
		$blnDocrootFlag = Type::Cast($objTemplateXml['DocrootFlag'], QType::Boolean);
		$strTargetDirectory = Type::Cast($objTemplateXml['TargetDirectory'], QType::String);
		$strDirectorySuffix = Type::Cast($objTemplateXml['DirectorySuffix'], QType::String);
		$strTargetFileName = Type::Cast($objTemplateXml['TargetFileName'], QType::String);

		if (is_null($blnOverwriteFlag) || is_null($strTargetFileName) || is_null($strTargetDirectory) || is_null($strDirectorySuffix) || is_null($blnDocrootFlag)) {
			throw new \Exception($strError);
		}
		if ($blnSave && $strTargetDirectory) {
			// Figure out the REAL target directory
			if ($blnDocrootFlag) {
				$strTargetDirectory = __DOCROOT__ . $strTargetDirectory . $strDirectorySuffix;
			} else {
				$strTargetDirectory = $strTargetDirectory . $strDirectorySuffix;
			}
			// Create Directory (if needed)
			if (!is_dir($strTargetDirectory)) {
				if (!Core::MakeDirectory($strTargetDirectory, 0777)) {
					throw new \Exception('Unable to mkdir ' . $strTargetDirectory);
				}
			}
			// Save to Disk
			$strFilePath = sprintf('%s/%s', $strTargetDirectory, $strTargetFileName);
			if ($blnOverwriteFlag || (!file_exists($strFilePath))) {
				$intBytesSaved = file_put_contents($strFilePath, $strTemplate);

				$this->setGeneratedFilePermissions($strFilePath);
				return ($intBytesSaved == strlen($strTemplate));
			} else {
				// Because we are not supposed to overwrite, we should return "true" by default
				return true;
			}
		}

		// Why Did We Not Save?
		if ($blnSave) {
			// We WANT to Save, but QCubed Configuration says that this functionality/feature should no longer be generated
			// By definition, we should return "true"
			return true;
		} else {
			// Running GenerateFile() specifically asking it not to save -- so return the evaluated template instead
			return $strTemplate;
		}
	}

	/**
	 * Pluralizes field names.
	 * @param string $strName The non-pluralized field name.
	 * @return string The pluralized field name.
	 * @access protected
	 */
	protected function Pluralize($strName) {
		// Special Rules go Here
		switch (true) {
			case ($strName === 'person'):
				return 'people';
			case ($strName === 'Person'):
				return 'People';
			case ($strName === 'PERSON'):
				return 'PEOPLE';
			case (strtolower($strName) == 'play'):
				return $strName . 's';
			// Otherwise...
			default:
				$intLength = strlen($strName);
				if (substr($strName, $intLength - 1) == "y"){
					return substr($strName, 0, $intLength - 1) . "ies";
				}
				if (substr($strName, $intLength - 1) == "s"){
					return $strName . "es";
				}
				if (substr($strName, $intLength - 1) == "x"){
					return $strName . "es";
				}
				if (substr($strName, $intLength - 1) == "z"){
					return $strName . "zes";
				}
				if (substr($strName, $intLength - 2) == "sh"){
					return $strName . "es";
				}
				if (substr($strName, $intLength - 2) == "ch"){
					return $strName . "es";
				}
				return $strName . "s";
		}
	}
}
