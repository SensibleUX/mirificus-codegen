<?php

namespace Mirificus;

abstract class Codegen {
	/**
	 * This static array contains an array of active and executed codegen objects, based
	 * on the XML Configuration passed in to Run()
	 *
	 * @var CodeGen[] array of active/executed codegen objects
	 */
	public static $CodeGenArray;

	public static $SettingsFilePath;
	
	/**
	 * This is the SimpleXML representation of the Settings XML file
	 *
	 * @var SimpleXmlElement the XML representation
	 */
	protected static $SettingsXml;
	
	/**
	 * Template Escape Begin (from CodeGen Settings)
	 *
	 * @var string $TemplateEscapeBegin
	 */
	protected static $TemplateEscapeBegin;
	protected static $TemplateEscapeBeginLength;

	/**
	 * Template Escape End (from CodeGen Settings)
	 *
	 * @var string $TemplateEscapeEnd
	 */
	protected static $TemplateEscapeEnd;
	protected static $TemplateEscapeEndLength;
	
	/**
	 * Application Name (from CodeGen Settings)
	 *
	 * @var string $ApplicationName
	 */
	protected static $ApplicationName;
	
	// Relative Paths (from __QCUBED_CORE__) to the CORE Template and Subtemplate Directories
	const TemplatesPath = '/codegen/templates/';
//		const SubTemplatesPath = '/codegen/subtemplates/';

	// Relative Paths (from __QCUBED__) to the CUSTOM Template and Subtemplate Directories
	const TemplatesPathCustom = '/codegen/templates/';

	/**
	 * @var string[] array of directories to be excluded in codegen (lower cased)
	 * @access protected
	 */
	protected static $DirectoriesToExcludeArray = array('.','..','.svn','svn','cvs','.git');
	
	// Class Name Suffix/Prefix
	protected $strClassPrefix;
	protected $strClassSuffix;
	
	// Holds an error log.
	protected $strErrors;
	
	
	
	
	
	
	
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
	 *
	 * @return array
	 */
	public static function GenerateAggregate() {
	   $objDbOrmCodeGen = array();
	   $objRestServiceCodeGen = array();

	   foreach (static::$CodeGenArray as $objCodeGen) {
		   if ($objCodeGen instanceof DatabaseCodeGen)
			   array_push($objDbOrmCodeGen, $objCodeGen);
		   if ($objCodeGen instanceof RestServiceCodeGen)
			   array_push($objRestServiceCodeGen, $objCodeGen);
	   }

	   $strToReturn = array();
	   array_merge($strToReturn, DatabaseCodeGen::GenerateAggregateHelper($objDbOrmCodeGen));
	//			array_push($strToReturn, QRestServiceCodeGen::GenerateAggregateHelper($objRestServiceCodeGen));

	   return $strToReturn;
	}
	
	/**
	 * Given a template prefix (e.g. db_orm_, db_type_, rest_, soap_, etc.), pull
	 * all the _*.tpl templates from any subfolders of the template prefix in QCodeGen::TemplatesPath and QCodeGen::TemplatesPathCustom,
	 * and call GenerateFile() on each one.  If there are any template files that reside
	 * in BOTH TemplatesPath AND TemplatesPathCustom, then only use the TemplatesPathCustom one (which
	 * in essence overrides the one in TemplatesPath).
	 *
	 * @param string $strTemplatePrefix the prefix of the templates you want to generate against
	 * @param mixed[] $mixArgumentArray array of arguments to send to EvaluateTemplate
	 * @return boolean success/failure on whether or not all the files generated successfully
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

	   // Finally, iterate through all the TempalteFiles and call GenerateFile to Evaluate/Generate/Save them
	   $blnSuccess = true;
	   foreach ($strTemplateArray as $strModuleName => $strFileArray)
		   foreach ($strFileArray as $strFilename => $blnOverrideFlag)
			   if (!$this->GenerateFile($strTemplatePrefix . '/' . $strModuleName, $strFilename, $blnOverrideFlag, $mixArgumentArray))
				   $blnSuccess = false;

	   return $blnSuccess;
	}
	
	/**
	 * Pluralizes field names
	 * @param string $strName
	 * @return string
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
