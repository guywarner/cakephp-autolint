#!/usr/bin/php -q
<?php
if (!array_key_exists(1, $argv)) {
	echo "\n\nI need a directory to fix...\n\n";
	die;
}
array_shift($argv);

if (is_dir($argv[0])) {
	$files = findAllFiles($argv[0]);
} else {
	$files = $argv;
}
$count = count($files);
for ($i = 0; $i < $count; $i++) {
	$fixTabs = array();
	$fixDocs = array();
	$oneSpaceCurlyTabs = array();
	$openingBraceTabs = array();
	$file = $files[$i];
	if (substr($file, -4) != ".php") {
		continue;
	}
	echo "\n\nChecking file " . $file . "... \n\n";

	$results = array();
	exec("phpcs $file", $results);

	foreach ($results as $line) {
		if (strpos($line, " | ")) {
			list($lineNumber, $type, $msg) = explode(" | ", $line);
			if ($msg == "Tabs must be used to indent lines; spaces are not allowed") {
				$fixTabs[] = $lineNumber;
			}

			if ($msg == "Doc blocks must not be indented") {
				$fixDocs[] = $lineNumber;
			}

			if ($msg == "Expected 1 space before curly opening bracket") {
				$oneSpaceCurlyTabs[] = $lineNumber + 0;
			}

			if ($msg == "Opening brace should be on the same line as the declaration") {
				$openingBraceTabs[] = $lineNumber - 1;
			}
		}
	}

	echo "Tab issue: " . count($fixTabs) . "\n";
	echo "Silly doc comment issue: " . count($fixDocs) . "\n";
	echo "No space before curly: " . count($oneSpaceCurlyTabs) . "\n";
	echo "Lonely curly brackets: " . count($openingBraceTabs) . "\n";

	$theFile = file($file, FILE_IGNORE_NEW_LINES);
	if (count($fixTabs) > 0) {
		$theFile = fixTabs($theFile, $oneSpaceCurlyTabs);
	}
	$theFile = fixHeader($theFile);
	if (count($fixDocs) > 0) {
		$theFile = fixDoc($theFile, $oneSpaceCurlyTabs);
	}
	if (count($oneSpaceCurlyTabs) > 0) {
		$theFile = openBrace($theFile, $oneSpaceCurlyTabs);
	}
	if (count($openingBraceTabs) > 0) {
		$theFile = openBrace($theFile, $openingBraceTabs);
	}

	$newFile = "";
	foreach ($theFile as $theLine) {
		if ($theLine != "//DELETE") {
			$newFile .= "$theLine\n";
		}
	}
	$fp = fopen($file, 'w');
	fwrite($fp, $newFile);
	fclose($fp);
	echo "Fixed all I could.";
	echo "\n\n";
}

function fixHeader($theFile) {
	$fixed = 0;
	$working = false;
	foreach ($theFile as $k => $line) {
		if ($k == 1 && $line != "/**") {
			return $theFile;
		}
		if (isset($line[3]) && $working === false) {
			if ($line[3] == '@') {
				$working = true;
				$subjs = array();
				$msgs = array();

			}
		}
		if ($working === true) {
			$line = str_replace("\t", " ", $line);
			$len = strlen($line) - 1;
			$subj = "";
			$msg = "";
			for ($i = 3; $i <= $len; $i++) {
				if ($line[$i] == " ") {
					$subj = substr($line, 0, $i);
					break;
				}
			}
			for ($i = $i; $i <= $len; $i++) {
				if ($line[$i] != " ") {
					$msg = substr($line, $i);
					break;
				}
			}
			if ($subj) {
				$subjs[$k] = $subj;
				$msgs[$k] = $msg;
			}
		}
		if ($line == " */") {
			$longest = 0;
			foreach ($subjs as $k => $v) {
				$len = strlen($v);
				if ($longest < $len) {
					$longest = $len;
				}
			}
			foreach ($subjs as $k => $v) {
				$spaces = $longest - strlen($v);
				$newLine = "";
				$newLine .= "$v";
				for ($i = 0; $i <= $spaces; $i++) {
					$newLine .= " ";
				}
				if ($v == " * @package") {
					$newLine .= "Web";
				} elseif ($v == " * @author") {
					$newLine .= "Blendtec <noreply@blendtec.com>";
				} else {
					$newLine .= $msgs[$k];
				}
				$theFile[$k] = $newLine;
			}
			$working = false;
			$fixed++;
		}
		if ($fixed == 2) {
			break;
		}
	}

	return $theFile;
}

function openBrace($theFile, $lines) {
	foreach ($lines as $line) {
		$theFile[$line - 1] = $theFile[$line - 1] . " {";
		if (substr($theFile[$line - 1], 0, 5) != 'class') {
			$theFile[$line] = "//DELETE";
		} else {
			$theFile[$line] = "";
		}
		$nextLine = $line + 1;
		while ($theFile[$nextLine] == "" || $theFile[$nextLine] == "\n") {
			$theFile[$nextLine] = "//DELETE";
			$nextLine++;
		}
	}
	return $theFile;
}

function fixTabs($theFile) {
	foreach ($theFile as $k => $line) {
		$line = str_replace("    ", "\t", $line);
		$line = str_replace("   ", "\t", $line);
		$line = str_replace("  ", "\t", $line);

		$theFile[$k] = $line;
	}
	return $theFile;
}

function fixDoc($theFile) {
	$fix = false;
	foreach ($theFile as $k => $line) {
		if ($line === "\t/**" || $line === "    /**" || $fix === true) {
			$line = trim($line);
			if ($line == "*/") {
				$fix = false;
			} else {
				$fix = true;
			}
			if ($line != "/**") {
				$line = " $line";
			}
			$theFile[$k] = $line;
		}
	}
	return $theFile;
}

function findAllFiles($dir) {
	$root = scandir($dir);
	foreach ($root as $value) {
		if ($value === '.' || $value === '..') {
			continue;
		}
		if (is_file("$dir/$value")) {
			$result[] = "$dir/$value";
			continue;
		}
		foreach (findAllFiles("$dir/$value") as $value) {
			$result[] = $value;
		}
	}
	return $result;
}
