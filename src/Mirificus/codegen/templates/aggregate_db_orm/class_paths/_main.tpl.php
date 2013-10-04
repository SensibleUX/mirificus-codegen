<?php
	/**
	 * Some sort of intelligent comment should probably go here...
	 */
	$output = sprintf('<template OverwriteFlag="true" DocrootFlag="false" DirectorySuffix="" TargetDirectory="%s" TargetFileName="_class_paths.inc.php"/>', __MODEL_GEN__);
	$output .= "\n<?php\n";
	foreach ($objTableArray as $objTable) {
		$output .= sprintf("// ClassPaths for the %s class \n", $objTable->ClassName);
		if (__MODEL__) {
			$output .= sprintf('QApplicationBase::$ClassFile[\'%s\'] = __MODEL__ . \'%s.class.php\';', strtolower($objTable->ClassName), $objTable->ClassName);
			$output .= "\n";
			$output .= sprintf('QApplicationBase::$ClassFile[\'qqnode%s\'] = __MODEL__ . \'/%s.class.php\';', strtolower($objTable->ClassName), $objTable->ClassName);
			$output .= "\n";
			$output .= sprintf('QApplicationBase::$ClassFile[\'qqreversereferencenode%s\'] = __MODEL__ . \'/%s.class.php\';', strtolower($objTable->ClassName), $objTable->ClassName);
			$output .= "\n";
		}
		if (__META_CONTROLS__) {
			$output .= sprintf('QApplicationBase::$ClassFile[\'%smetacontrol\'] = __META_CONTROLS__ . \'/%sMetaControl.class.php\';', strtolower($objTable->ClassName), $objTable->ClassName);
			$output .= "\n";
			$output .= sprintf('QApplicationBase::$ClassFile[\'%sdatagrid\'] = __META_CONTROLS__ . \'/%sDataGrid.class.php\';', strtolower($objTable->ClassName), $objTable->ClassName);
			$output .= "\n";
		} 
	}
	
	echo $output;
