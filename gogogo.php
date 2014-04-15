#!/usr/bin/env php
<?php

function sort_by_length($a,$b){
	return strlen($b)-strlen($a);
}

function do_your_magic($data) {
	preg_match('/(?P<className>draw2d\..*)[ ]?= /', $data, $matches);

	if (!empty($matches)) {
		$className = trim($matches['className']);
		$classDefinition = $matches[0];

		$pattern = <<<EOF
define("%s",
    [
%s
    ],
function(
%s
){
    return
EOF;
		$req = [];
		$req2 = [];

		preg_match_all('/draw2d\.[a-zA-Z0-9\._]+/', $data, $matches);

		$names = [];

		array_unique($matches);
		foreach($matches[0] as $name) {
			if (strpos($name, 'extend')) {
				$name = explode('.', $name);
				array_pop($name);
				$name = implode('.', $name);
			}

			if ($name !== $className && !in_array($name, $names)) {
				$names[] = $name;
			}
		}

		usort($names, 'sort_by_length');

		foreach($names as $name) {
			$req[] = sprintf('        "%s"', str_replace('.', '/', $name));
			$req2[] = sprintf('    %s', str_replace('.', '_', $name));
		}

		asort($req);
		asort($req2);

		$req = implode(",\n", $req);
		$req2 = implode(",\n", $req2);

		$pattern = sprintf(
			$pattern,
			str_replace('.', '/', $className),
			$req,
			$req2
		);
		$data = str_replace($classDefinition, $pattern . " ", $data);

		foreach($names as $name) {
			$data = str_replace($name, str_replace('.', '_', $name), $data);
		}

		$data = str_replace($className, str_replace('.', '_', $className), $data);
		$start = strpos($data, "return");
		$header = substr($data, 0, $start);
		$footer = trim(str_replace("\n", "\n    ", substr($data, $start)));

		return $header . $footer . "\n});\n";
	} else {
		throw new Exception('Unknow header!');
	}
}

if (!isset($argv[1])) {
	throw new Exception('Podaj katalog');
}

function read_all_files($root = '.'){
	$files  = array('files'=>array(), 'dirs'=>array());
	$directories  = array();
	$last_letter  = $root[strlen($root)-1];
	$root  = ($last_letter == '\\' || $last_letter == '/') ? $root : $root.DIRECTORY_SEPARATOR;

	$directories[]  = $root;

	while (sizeof($directories)) {
		$dir  = array_pop($directories);
		if ($handle = opendir($dir)) {
			while (false !== ($file = readdir($handle))) {
				if ($file == '.' || $file == '..') {
					continue;
				}
				$file  = $dir.$file;
				if (is_dir($file)) {
					$directory_path = $file.DIRECTORY_SEPARATOR;
					array_push($directories, $directory_path);
					$files['dirs'][]  = $directory_path;
				} elseif (is_file($file)) {
					$files['files'][]  = $file;
				}
			}
			closedir($handle);
		}
	}

	return $files;
}


$dirsAndFiles = read_all_files($argv[1]);

$i = 1;
foreach($dirsAndFiles['files'] as $file) {
	printf("%d: %s\n", $i++, $file);

	$fileName = explode('/', $file);
	$fileName = end($fileName);

	if (in_array($fileName, [
		'Canvas.js',
		'draw2d.js',
		'CommandType.js',
		'PositionConstants.js',
		'Rectangle.js',
		'SnapToGeometryEditPolicy.js',
		'Base64.js',
		'Blob.js',
		'Debug.js',
		'SVGUtil.js',
		'UUID.js',
		'Line.js',
		'PolyLine.js',
		'Connection.js',
		'Configuration.js'
	])) {
		continue;
	}

	file_put_contents($file, do_your_magic(file_get_contents($file)));
}
