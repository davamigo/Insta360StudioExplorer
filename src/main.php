<?php

echo PHP_EOL, 'Insta360 Studio Explorer', PHP_EOL, PHP_EOL;

$ARG_ACTION = 'show';
$ARG_SHOW_OK = true;
$ARG_SHOW_KO = true;
$ARG_FILTER = null;
$ARG_SOURCE = null;
$ARG_TARGET = null;
$ARG_PATH = '.';

processArguments($argc, $argv);

if ($ARG_ACTION === 'help') {
	showHelp();
	return 0;
}

if (!@chdir ($ARG_PATH)) {
	echo 'Invalid path: ', $ARG_PATH, PHP_EOL;
	return -1;
}

$path = getcwd();
switch ($ARG_ACTION) {

	case 'show':
		showProjects($path);
		return 0;

	case 'delete':
		$result = deleteProject($path, $ARG_TARGET);
		return ($result === true) ? 0: -1;

	case 'copy':
		$result = copyProject($path, $ARG_SOURCE, $ARG_TARGET);
		return ($result === true) ? 0: -1;

}

///////////////////////////////////////////////////////////////////////////////

function processArguments(int $argc, array $argv): void {
	global $ARG_ACTION;
	global $ARG_SHOW_OK;
	global $ARG_SHOW_KO;
	global $ARG_SOURCE;
	global $ARG_TARGET;
	global $ARG_FILTER;
	global $ARG_PATH;

	for ($i=1; $i < $argc; $i++) {
		
		switch(strtolower($argv[$i])) {

			case '-h':
			case '--help':
				$ARG_ACTION = 'help';
				break;

			case '-o':
			case '--ok':
				$ARG_SHOW_OK = true;
				$ARG_SHOW_KO = false;
				break;

			case '-k':
			case '--ko':
				$ARG_SHOW_OK = false;
				$ARG_SHOW_KO = true;
				break;

			case '-f':
				$ARG_FILTER = $argv[++$i];
				break;

			case '-d':
				$ARG_ACTION = 'delete';
				$ARG_TARGET = $argv[++$i];
				break;

			case '-c':
				$ARG_ACTION = 'copy';
				$ARG_SOURCE = $argv[++$i];
				$ARG_TARGET = $argv[++$i];
				break;

			default:
				$ARG_PATH = $argv[$i];
				break;
		}
	}
}


///////////////////////////////////////////////////////////////////////////////

function showHelp(): void {
	echo 'php src\main.php [OPTIONS] [PATH]', PHP_EOL;
	echo PHP_EOL, 'Options:', PHP_EOL;
	echo "\t", '-h, --help', "\t\t", 'Show this help.', PHP_EOL;
	echo "\t", '-o, --ok', "\t\t", 'Show only projects ok.', PHP_EOL;
	echo "\t", '-k, --ko', "\t\t", 'Show only projects not found.', PHP_EOL;
	echo "\t", '-f FILTER', "\t\t", 'Filter projects.', PHP_EOL;
	echo "\t", '-d TARGET', "\t\t", 'Delete project (by project ID).', PHP_EOL;
	echo "\t", '-c SOURCE TARGET', "\t", 'Copy project properties (by project ID).', PHP_EOL;
	echo PHP_EOL, 'Examples:', PHP_EOL;
	echo "\t", 'php src\main.php C:\Insta360\Studio\Project', PHP_EOL;
	echo "\t", 'php src\main.php -f foo', PHP_EOL;
	echo "\t", 'php src\main.php -d d428f2a7e54781b06eb79797ba2a64b1', PHP_EOL;
	echo "\t", 'php src\main.php -c 5d77d96ca48675594dbaf5bd987a8895 2f879a7b4eea986605420580d35130e3', PHP_EOL;
}


///////////////////////////////////////////////////////////////////////////////

function showProjects(string $path): void {

	echo 'Showing Insta360 projects in ', $path, '...', PHP_EOL;

	$projectCount = 0;
	$projectsShown = 0;
	$scan = scandir($path);
	foreach($scan as $project) {
		if (isValidProject($path, $project)) {
			$result = scanProject($path, $project);
			++$projectCount;
			if ($result) {
				++$projectsShown;
			}
		}
	}

	echo PHP_EOL, 'Total projects found: ', $projectCount, PHP_EOL;
	echo 'Total projects shown: ', $projectsShown, PHP_EOL;
}


///////////////////////////////////////////////////////////////////////////////

function scanProject(string $path, string $project): bool {

	$projectFile = getValidProjectFile($path, $project);
	$xml = readProjectFile($path, $project);
	if ($xml === null) {
		$errorMsg = 'Unable to parse the project file!';
		showProjectError($project, $projectFile, $errorMsg);
		return false;
	}

	$videoFolder = (string) $xml->file_group['folder'];
	$videoCount = (int) $xml->file_group['count'];
	if ($videoFolder === null || $videoCount === 0) {
		$errorMsg = 'Invalid project file format!';
		showProjectError($project, $projectFile, $errorMsg);
		return false;
	}

	$videoFiles = [];
	foreach ($xml->file_group->file as $file) {
		$videoFile = (string) $file['name'];
		$videoFiles[] = $videoFile;
	}

	$schemes = [];
	foreach ($xml->schemes->scheme as $scheme) {
		$schemeId = (string) $scheme['id'];
		$keyframeCount = count($scheme->timeline->recording->keyframes->keyframe);
		$schemes[$schemeId] = $keyframeCount;
	}

	$videoFilesStatus = checkVideoFiles($videoFolder, ...$videoFiles);
	$projectStatus = getProjectStatus($videoFilesStatus);
	$isVisible = checkVisibilityOptions($projectStatus)
		&& checkFilterOptions($project, $projectFile, $videoFolder, $videoFiles);

	if ($isVisible) {
		showProject($project, $projectFile, $videoFilesStatus, $schemes);
	}

	return $isVisible;
}

///////////////////////////////////////////////////////////////////////////////

function checkVideoFiles(string $videoFolder, string ...$videoFiles): array {

	$result = [];

	foreach ($videoFiles as $videoFile) {
	
		$videoFilePath = $videoFolder . DIRECTORY_SEPARATOR . $videoFile;

		if (file_exists($videoFilePath)) {
			$result[$videoFilePath] = true;
		}
		else {
			$result[$videoFilePath] = false;
		}
	}

	return $result;
}


///////////////////////////////////////////////////////////////////////////////

function getProjectStatus(array $videoFilesStatus): bool {
	foreach ($videoFilesStatus as $videoFilePath => $videoFileStatus) {
		if ($videoFileStatus === false) {
			return false;
		}
	}
	return true;
}


///////////////////////////////////////////////////////////////////////////////

function checkVisibilityOptions(bool $projectStatus): bool {

	global $ARG_SHOW_OK;
	global $ARG_SHOW_KO;

	if ($projectStatus === true && $ARG_SHOW_OK === true) {
		return true;
	}

	if ($projectStatus === false && $ARG_SHOW_KO === true) {
		return true;
	}

	return false;
}


///////////////////////////////////////////////////////////////////////////////

function checkFilterOptions (string $project, string $projectFile, string $videoFolder, $videoFiles): bool {

	global $ARG_FILTER;

	if ($ARG_FILTER === null) {
		return true;
	}

	if (str_contains($project, $ARG_FILTER)) {
		return true;
	}

	if (str_contains($projectFile, $ARG_FILTER)) {
		return true;
	}

	if (str_contains($videoFolder, $ARG_FILTER)) {
		return true;
	}

	foreach ($videoFiles as $videoFile) {
		if (str_contains($videoFile, $ARG_FILTER)) {
			return true;
		}
	}

	return false;
}


///////////////////////////////////////////////////////////////////////////////

function showProject(string $project, string $projectFile, array $videoFilesStatus, array $schemes): void {

	echo PHP_EOL, $project, PHP_EOL;
	echo "\t", $projectFile, PHP_EOL;

	echo "\t\t", 'Video files:', PHP_EOL;

	foreach ($videoFilesStatus as $videoFilePath => $videoFileStatus) {
		$videoFileStatusText = $videoFileStatus ? 'OK.' : 'not found!';
		echo "\t\t\t", $videoFilePath, '... ', $videoFileStatusText, PHP_EOL;
	}

	echo "\t\t", 'Schemes:', PHP_EOL;

	foreach ($schemes as $schemeId => $keyframeCount) {
		echo "\t\t\t", $schemeId, ' (', $keyframeCount, ' keyframes)', PHP_EOL;
	}
}


///////////////////////////////////////////////////////////////////////////////

function showProjectError(string $project, string $projectFile,  string $errorMsg): void {
	echo PHP_EOL, $project, PHP_EOL;
	echo "\t", $projectFile, PHP_EOL;
	echo "\t\t", 'ERROR: ', $errorMsg, PHP_EOL;
}


///////////////////////////////////////////////////////////////////////////////

function deleteProject(string $path, string $project): bool {

	echo 'Deleting Insta360 project ', $project, '...', PHP_EOL;
	echo 'Projects path: ', $path, PHP_EOL;
	
	if (!isValidProject($path, $project)) {
		echo 'ERROR: Project ', $project, ' not found or invalid!', PHP_EOL;
		return false;
	}

	if (!askConfirmation()) {
		return false;
	}

	$projectFolder = getProjectFolder($path, $project);
	$result = recursiveRemove($projectFolder);
		if ($result === false) {
		echo 'ERROR: Unable to remove project folder: ', $projectFolder, PHP_EOL;
		return false;
	}

	echo PHP_EOL, 'Project ', $project, ' removed!', PHP_EOL;

	return true;
}


///////////////////////////////////////////////////////////////////////////////

function copyProject(string $path, string $source, string $target): bool {

	echo 'Copying Insta360 project ', $source, ' to ', $target, '...', PHP_EOL;
	echo 'Projects path: ', $path, PHP_EOL;
	
	if (!isValidProject($path, $source)) {
		echo 'ERROR: Source project ', $source, ' not found or invalid!', PHP_EOL;
		return false;
	}

	if (!isValidProject($path, $target)) {
		echo 'ERROR: Target project ', $target, ' not found or invalid!', PHP_EOL;
		return false;
	}

	$sourceProjectFile = getValidProjectFile($path, $source);
	$sourceXml = readProjectFile($path, $source);
	if ($sourceXml === null) {
		echo 'ERROR: Unable to parse the source project file ', $sourceProjectFile, '!', PHP_EOL;
		return false;
	}

	$targetProjectFile = getValidProjectFile($path, $target);
	$targetXml = readProjectFile($path, $target);
	if ($targetXml === null) {
		echo 'ERROR: Unable to parse the target project file ', $targetProjectFile, '!', PHP_EOL;
		return false;
	}

	if (!askConfirmation()) {
		return false;
	}

	// Remove previous "schemes" from targetXml
	unset($targetXml->schemes);

	// Copy sourceXml "schemes" to targetXml using DOM
	$targetDom = dom_import_simplexml($targetXml);
	$sourceDomSchemes = dom_import_simplexml($sourceXml->schemes);
	$targetDomSchemes = $targetDom->ownerDocument->importNode($sourceDomSchemes, true);
	$targetDom->appendChild($targetDomSchemes);

	// Save targetXML back to file
	$result = saveProjectFile($path, $target, $targetXml);
	if ($result === false) {
		echo 'ERROR: Unable to save the target project file ', $targetProjectFile, '!', PHP_EOL;
		return false;
	}

	$sourceProjectFolder = getProjectFolder($path, $source);
	$targetProjectFolder = getProjectFolder($path, $target);

	copyProjectrEntries($sourceProjectFolder, $targetProjectFolder, 'thumbnail');
	copyProjectrEntries($sourceProjectFolder, $targetProjectFolder, 'deeptrack.db');

	echo PHP_EOL, 'Source project properties successfully copied to target project ', $target, '!', PHP_EOL;

	return true;
}


///////////////////////////////////////////////////////////////////////////////

function askConfirmation(): bool {
	
	do {
		echo 'Are you sure? (y/n) ';
		$conf =  trim(strtolower(fgets(STDIN)));
	}
	while(!in_array($conf, ['y', 'n']));

	if ($conf !== 'y') {
		echo 'Cancelled by the user!', PHP_EOL;
		return false;
	}

	return true;
}


///////////////////////////////////////////////////////////////////////////////

function isValidProject(string $path, string $project): bool {

	$projectFolder = getProjectFolder($path, $project);
	$folderExists = is_dir($projectFolder)
		&& !in_array($project, ['.', '..', 'cscache']);

	if (!$folderExists) {
		return false;
	}

	$projectFile = getValidProjectFile($path, $project);
	if ($projectFile === null) {
		return false;
	}

	return true;
}


///////////////////////////////////////////////////////////////////////////////

function getProjectFolder(string $path, string $project): string {
	return $path . DIRECTORY_SEPARATOR . $project;
}


///////////////////////////////////////////////////////////////////////////////

function getProjectFilePath(string $projectFolder, string $projectFile) {
	return $projectFolder . DIRECTORY_SEPARATOR . $projectFile;
}


///////////////////////////////////////////////////////////////////////////////

function getValidProjectFile(string $path, string $project): ?string {

	$projectFolder = getProjectFolder($path, $project);

	$scan = scandir($projectFolder);
	foreach($scan as $projectFile) {
		$projectFilePath = getProjectFilePath($projectFolder, $projectFile);
		if (!is_dir($projectFilePath) && str_ends_with($projectFile, '.insprj')) {
			return $projectFile;
		}
	}
	return null;
}


///////////////////////////////////////////////////////////////////////////////

function readProjectFile(string $path, string $project): ?SimpleXMLElement {

	$projectFile = getValidProjectFile($path, $project);
	$projectFolder = getProjectFolder($path, $project);
	$projectFilePath = getProjectFilePath($projectFolder, $projectFile);

	$xml = simplexml_load_file($projectFilePath);
	if ($xml === false) {
		return null;
	}

	return $xml;
}


///////////////////////////////////////////////////////////////////////////////

function saveProjectFile(string $path, string $project, SimpleXMLElement $xml): bool {

	$projectFile = getValidProjectFile($path, $project);
	$projectFolder = getProjectFolder($path, $project);
	$projectFilePath = getProjectFilePath($projectFolder, $projectFile);

	$result = $xml->asXml($projectFilePath);
	if ($result === false) {
		return false;
	}

	return true;
}


///////////////////////////////////////////////////////////////////////////////

function copyProjectrEntries(string $sourceProjectFolder, string $targetProjectFolder, string $entry): bool {

	$sourceEntryPath = $sourceProjectFolder . DIRECTORY_SEPARATOR . $entry;
	$targetEntryPath = $targetProjectFolder . DIRECTORY_SEPARATOR . $entry;

	$result = recursiveRemove($targetEntryPath);
	if ($result === false) {
		echo 'ERROR: Unable to remove target entry ', $entry, ': ', $targetEntryPath, PHP_EOL;
		return false;
	}

	$result = recursiveCopy($sourceEntryPath, $targetEntryPath);
	if ($result === false) {
		echo 'ERROR: Unable to copy target entry ', $entry, ': ', $targetEntryPath, PHP_EOL;
		return false;
	}

	return true;
}


///////////////////////////////////////////////////////////////////////////////

function recursiveRemove(string $path): bool {

    if (@is_dir($path)) {
        foreach (scandir($path) as $entry) {
            if (!in_array($entry, ['.', '..'], true)) {
                if (!recursiveRemove($path . DIRECTORY_SEPARATOR . $entry)) {
					return false;
				}
            }
        }
        if (!@rmdir($path)) {
			return false;
		}
    } else if (is_file($path) || is_link($path)) {
        if (!@unlink($path)) {
			return false;
		}
    }
	return true;
}


///////////////////////////////////////////////////////////////////////////////

function recursiveCopy(string $sourcePath, string $targetPath): bool {

    if (@is_dir($sourcePath)) {
		if (!@mkdir($targetPath, recursive: true)) {
			return false;
		}
        foreach (scandir($sourcePath) as $entry) {
            if (!in_array($entry, ['.', '..'], true)) {
				if (!recursiveCopy($sourcePath . DIRECTORY_SEPARATOR . $entry, $targetPath . DIRECTORY_SEPARATOR . $entry)) {
					return false;
				}
			}
		}
	}
	else if (is_file($sourcePath)) {
		if (!@copy($sourcePath, $targetPath)) {
			return false;
		}
	}
	else if (is_link($sourcePath)) {
		if (@link($targetPath, @readlink($sourcePath))) {
			return false;
		}
	}

	return true;
}
