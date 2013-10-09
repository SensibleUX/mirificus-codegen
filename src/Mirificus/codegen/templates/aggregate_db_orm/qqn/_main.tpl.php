<?php

	$output = sprintf('<template OverwriteFlag="true" DocrootFlag="false" DirectorySuffix="" TargetDirectory="%s" TargetFileName="QQN.class.php"/>', __MODEL_GEN__);
	$output .= "\n<?php\n";
	$output .= "class QQN {\n";
	foreach ($objTableArray as $objTable) {
		$output .= "/**\n";
		$output .= sprintf("* @return QQNode%s", $objTable->ClassName);
		$output .= "\n";
		$output .= "*/\n";
		$output .= sprintf("static public function %s() {\n", $objTable->ClassName);
			$output .= sprintf("return new QQNode%s('%s', null, null);\n", $objTable->ClassName, $objTable->Name);
		$output .= "}\n"; // End function
	}
	$output .= "}\n"; // End class
	
	echo $output;
