<template OverwriteFlag="true" DocrootFlag="true" DirectorySuffix="" TargetDirectory="<?php echo __PANEL_DRAFTS__  ?>" TargetFileName="<?php echo $objTable->ClassName ?>EditPanel.tpl.php"/>
<?php print("<?php\n"); ?>
	// This is the HTML template include file (.tpl.php) for <?php echo QConvertNotation::UnderscoreFromCamelCase($objTable->ClassName)  ?>EditPanel.
	// Remember that this is a DRAFT.  It is MEANT to be altered/modified.
	// Be sure to move this out of the drafts/dashboard subdirectory before modifying to ensure that subsequent
	// code re-generations do not overwrite your changes.
?>
	<div class="form-controls">
<?php
foreach ($objTable->ColumnArray as $objColumn) {
	print('<?php $_CONTROL->'.$objCodeGen->FormControlVariableNameForColumn($objColumn).'->RenderWithName(); ?>');
}
foreach ($objTable->ReverseReferenceArray as $objReverseReference) {
	if ($objReverseReference->Unique) {
		print('<?php $_CONTROL->'.$objCodeGen->FormControlVariableNameForUniqueReverseReference($objReverseReference).'->RenderWithName(); ?>');
	}
}
foreach ($objTable->ManyToManyReferenceArray as $objManyToManyReference) {
	print('<?php $_CONTROL->'.$objCodeGen->FormControlVariableNameForManyToManyReference($objManyToManyReference).'->RenderWithName(true); ?>');
}
?>
	</div>

	<div class="form-actions">
		<div class="form-save"><?php print("<?php"); ?> $_CONTROL->btnSave->Render(); ?></div>
		<div class="form-cancel"><?php print("<?php"); ?> $_CONTROL->btnCancel->Render(); ?></div>
		<div class="form-delete"><?php print("<?php"); ?> $_CONTROL->btnDelete->Render(); ?></div>
	</div>