<?php
	sprintf('<template OverwriteFlag="true" DocrootFlag="false" DirectorySuffix="" TargetDirectory="%s" TargetFileName="_class_paths.inc.php"/>', __MODEL_GEN__);
	print("\n<?php\n");

	
	foreach ($objTableArray as $objTable) {
		sprintf('// ClassPaths for the %s class', $objTable->ClassName);
		print("\n");
		if (__MODEL__) {
			sprintf('QApplicationBase::$ClassFile[\'%s\'] = __MODEL__ . \'/%s.class.php\';', strtolower($objTable->ClassName), $objTable->ClassName);
			print("\n");
			sprintf('QApplicationBase::$ClassFile[\'qqnode%s\'] = __MODEL__ . \'/%s.class.php\';', strtolower($objTable->ClassName), $objTable->ClassName);
			print("\n");
			sprintf('QApplicationBase::$ClassFile[\'qqreversereferencenode%s\'] = __MODEL__ . \'/%s.class.php\';', strtolower($objTable->ClassName), $objTable->ClassName);
			print("\n");

		}
		if (__META_CONTROLS__) { 
			sprintf('QApplicationBase::$ClassFile[\'%smetacontrol\'] = __META_CONTROLS__ . \'/%sMetaControl.class.php\';', strtolower($objTable->ClassName), $objTable->ClassName);
			print("\n");
			sprintf('QApplicationBase::$ClassFile[\'%sdatagrid\'] = __META_CONTROLS__ . \'/%sDataGrid.class.php\';', strtolower($objTable->ClassName), $objTable->ClassName);
			print("\n");
		}
	}
