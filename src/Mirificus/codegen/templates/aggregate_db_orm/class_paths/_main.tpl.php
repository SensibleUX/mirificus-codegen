<template OverwriteFlag="true" DocrootFlag="false" DirectorySuffix="" TargetDirectory="<?php echo $this->GeneratedOutputDirectory . "/generated";  ?>" TargetFileName="_class_paths.inc.php"/>
<?php
/*
 * I believe this is for autoloading purposes. Will create an array of paths for the generated classes.
 */
print("<?php\n");
foreach ($objTableArray as $objTable) {
	echo '// ClassPaths for the '.$objTable->ClassName.' class';
	if ($this->GeneratedOutputDirectory) { ?>
		Core::$ClassFile['<?php echo strtolower($objTable->ClassName)  ?>'] = $this->GeneratedOutputDirectory . '/<?php echo $objTable->ClassName  ?>.class.php';
		Core::$ClassFile['node<?php echo strtolower($objTable->ClassName)  ?>'] = $this->GeneratedOutputDirectory . '/<?php echo $objTable->ClassName  ?>.class.php';
		Core::$ClassFile['reversereferencenode<?php echo strtolower($objTable->ClassName)  ?>'] = $this->GeneratedOutputDirectory . '/<?php echo $objTable->ClassName  ?>.class.php';
<?php }
}?>