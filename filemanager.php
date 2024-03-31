<?php
/*
*
* FileManager Simplest All-in-one
* CQRS: CommandFileManager & DirectoryBrowser PHP classes
* @author prologos1
* @date 2024/03/31
* 
*/


class CommandFileManager {
	private $dir;

	public function __construct($dir = "./") {
		$this->dir = str_replace('\\', '/', urldecode($dir));
	}

	public function deleteFile($filename) {
		if (!file_exists($filename)) return 'File does not exist';
		if (is_dir($filename)) return 'Cannot delete directories using this method';

		$base_url = (!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
		if (isset($filename)) {
			unlink($filename);
			header('Location: ' . $base_url);
			exit();
		}
	}

	public function saveChanges($filename, $newContent)
	{
		$filename = str_replace('\\', '/', urldecode($filename));

		if (!file_exists($filename))  return 'File does not exist.';
		if (is_dir($filename)) return 'Cannot update directories using this method.';

		$updated = file_put_contents($this->dir . '/' . $filename, $newContent); //$updated = file_put_contents($filename, $newContent);

		if ($updated) {
			return true;
		} else {
			return false;
		}
	}

	public function createFile($filename, $contents, $folder) {
		if (isset($filename) && isset($contents) && isset($folder)) {
			$folder = str_replace(array('../', './/'),'', $this->dir .'/'. urldecode($folder));
			if (file_exists($this->dir .'/'. $folder . '/' . $filename)) {
				echo "File already exists";
				exit();
			} else {
				file_put_contents($this->dir .'/'. $folder . '/' . $filename, $contents);
				header('Location: ?open='.$folder . '/' . $filename);
			}
		}
	}

	public function renameFile($oldName, $newName) {
		if (file_exists($this->dir .'/'. $oldName)) {
			rename($this->dir .'/'. $oldName, $this->dir .'/'. $newName);
			header('Location: ?open='.$newName);
			exit();
		}
	}

	function createFolder($folderName, $path) {
		$folder = str_replace(array('../', './/'),'', $this->dir .'/'. urldecode($path));
		$directory = $this->dir .'/'. $folder . '/' . $folderName;

		if (!is_dir($directory)) {
			if (mkdir($directory, 0777, true)) {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	public function renameFolder($oldName, $newName) {
		$oldName = str_replace(array('../', './/'),'', $this->dir .'/'. urldecode($oldName));
		$newName = str_replace(array('../', './/'),'', $this->dir .'/'. urldecode($newName));

		if (is_dir($oldName)) {
			rename($oldName, $newName);
			sleep(1);
			header('Location: ?directory='.dirname($newName));
			exit();
		}
	}


	function deleteFolder($folderPath) {
		if (!is_dir($folderPath)) {
			echo "Folder '$folderPath' doesn't exist.";
			return;
		}
		$files = array_diff(scandir($folderPath), array('.', '..'));
		foreach ($files as $file) {
			$filePath = $folderPath . '/' . $file;
			if (is_dir($filePath)) {
				$this->deleteFolder($filePath);
			} else {
				unlink($filePath);
			}
		}
		rmdir($folderPath);
	}
}

// Run commands
$path = isset($_GET['directory']) ? $_GET['directory'] : (
isset($_GET['open']) ? dirname($_GET['open']) : '.'
);
$fileManager = new CommandFileManager($path);
if (isset($_GET['delete'])) $fileManager->deleteFile($_GET['delete']);
if (isset($_POST['newContent']) && isset($_POST['filename'])) $fileManager->saveChanges($_REQUEST['filename'], $_REQUEST['newContent'], $_REQUEST['folder']);
if (isset($_GET['create']) && $_GET['create'] === 'folder' && !empty($_GET['foldername']) && !empty($_GET['path'])) $fileManager->createFolder($_REQUEST['foldername'], $_REQUEST['path']);
if (isset($_POST['contents']) && isset($_POST['filename'])) $fileManager->createFile($_REQUEST['filename'], $_REQUEST['contents'], $_REQUEST['folder']);
if (isset($_GET['oldName']) && isset($_GET['newName'])) $fileManager->renameFile($_GET['oldName'], $_GET['newName']);
if (isset($_GET['oldFolderName']) && isset($_GET['newFolderName'])) $fileManager->renameFolder($_GET['oldFolderName'], $_GET['newFolderName']);
if (isset($_GET['deleteFolder'])) $fileManager->deleteFolder($_GET['deleteFolder']);




class DirectoryBrowser
{
	private $currentDirectory;

	public function __construct($directory)
	{
		$this->currentDirectory = $directory;
	}

	public function listDirectoryContents()
	{
		if (is_dir($this->currentDirectory)) {
			$files = scandir($this->currentDirectory);

			usort($files, function($a, $b) {
				$aIsDir = is_dir($a);
				$bIsDir = is_dir($b);
				// If $a and $b are both directories or files, sort alphabetically
				if ($aIsDir === $bIsDir) {
					return strnatcasecmp($a, $b);
				}
				// Otherwise, directories always come before files
				return $aIsDir ? -1 : 1;
			});
		} else {
			return false;
		}

		return $files;
	}

	public function display() {
		$directoryContents = $this->listDirectoryContents();
		if (!$directoryContents) {
			$output = null; // "Directory: " . $this->currentDirectory . " is not exist!";
			return $output;
		}
		$output = "<h2>Current Directory: " . $this->currentDirectory . "</h2><br />";

		$divider = (strstr($_SERVER['SERVER_SOFTWARE'], "Win")) ? "\\" : "/";
		$folders = explode($divider, $this->currentDirectory);
		$paths = [];
		$currentPath = '';
		foreach($folders as $folder) {
			$currentPath .= $folder . "\\";
			$currentPath = rtrim($currentPath, "\\");
			$k = ($currentPath === '.') ? 'Home' : str_replace(array('.\\','./'), '', $currentPath);
			$lastSlashPos = strrpos($k, '\\');
			if ($lastSlashPos !== false) $k = substr($k, strrpos($k, "\\") + 1);
			$lastSlashPos = strrpos($k, '/');
			if ($lastSlashPos !== false) $k = substr($k, strrpos($k, "\\") + 1);
			$paths[$k] = $currentPath;
			$currentPath .= "\\";
		}
		foreach($paths as $k => $path) {
			if (strstr($_SERVER['SERVER_SOFTWARE'], "Win") === false) {
				$path = str_replace(array('\\'), '/', $path);
			}
			$output .="\ <a href='?directory={$path}'>{$k}</a>";
		}

		$output .= '<br /><table class="table" id="fileTable">';
		$output .= '<thead><tr><th>File</th><th>Size</th><th>Creation Date</th><th>Last Modification Date</th><th>Action</th></tr></thead>';
		$output .= '<tbody>';

		foreach ($directoryContents as $item) {
			$output .= '<tr>';
			$itemPath = $this->currentDirectory . DIRECTORY_SEPARATOR . $item;

			if ($item != '.') { //   && $item != '..'
				$itemDateCreated = date("Y-m-d H:i:s", filectime($itemPath));
				$itemDateModified = date("Y-m-d H:i:s", filemtime($itemPath));

				if (is_dir($itemPath)) {
					$output .=  "<td>📁 <b><a href='?directory={$itemPath}'>{$item}</a></b></td> <td></td> <td>{$itemDateCreated}</td> <td>{$itemDateModified}</td>";
					$output .= "<td><a class='btn btn-primary' href='?directory={$itemPath}'>Open folder</a>
						<button class='btn btn-danger' onclick=\"confirmDeleteFolder('".urlencode($itemPath)."')\">Delete folder</button>
						</td> ";
				} else {
					$fileSize = filesize($itemPath);
					$output .=  "<td>📄 <a href='?open={$itemPath}'>{$item}</a></td> <td>". $fileSize ." bytes</td> <td>{$itemDateCreated}</td> <td>{$itemDateModified}</td>";
					$output .= "<td><button class='btn btn-primary' onclick=\"document.location='?open=".urlencode($itemPath)."'\">Open file</button>";
					$output .= " ";
					$output .= "<button class='btn btn-danger' onclick=\"confirmDelete('".urlencode($itemPath)."')\">Delete</button></td>";
				}
			}
			$output .= '</tr>';
		}

		$output .= '</tbody>';
		$output .=  '</table>';

		if ($this->currentDirectory !== '.') {
			$output .=  "<p><a class='btn btn-light' href='?directory=" . dirname($this->currentDirectory) . "'>⬆️ Go Up</a></p><br />";
		}
		return $output;
	}


	public function recursiveSearch($filename)
	{
		$directory = new RecursiveDirectoryIterator($this->currentDirectory, RecursiveDirectoryIterator::SKIP_DOTS);
		$iterator = new RecursiveIteratorIterator($directory);

		foreach($iterator as $file) {
			if (strpos($file->getFilename(), $filename) !== false) {
				return $file->getPathname();
			}
		}
		return null;
	}

	public function openFile($filename)
	{
		if (!file_exists($filename)) return false;
		$fileContents = file_get_contents($filename);
		$fileContents = htmlspecialchars($fileContents);
		return $fileContents;
	}

}

// Starting display
$path = isset($_GET['directory']) ? $_GET['directory'] : (
isset($_GET['open']) ? dirname($_GET['open']) : '.'
);
$directoryLister = new DirectoryBrowser($path);
$result_catalog = $directoryLister->display();

$result_search = "";
$search_filename = isset($_GET['search']) ? $_GET['search'] : null;
if ($search_filename !== null) {
	$path = $directoryLister->recursiveSearch($search_filename);

	if ($path !== null) {
		$result_search = "<p><a href='?directory=" . dirname($path) . "'>Folder</a> <a href='?open=" . ($path) . "'>File</a></p>"; ; // "Found file at folder: " . dirname($path); $this->currentDirectory
	}
}

// Setting up dynamic links
$base_url = (!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
$request_full_url = (!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>File Manager</title>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
	<style>
		.alert {
			display: none;
			position: fixed;
			z-index: 1;
			left: 50%;
			top: 50%;
			transform: translate(-50%, -50%);
			background-color: #707173;
			color: white;
			padding: 15px;
			border-radius: 10px;
			box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
			min-width: 300px;
		}
		.close {
			color: #ccc;
			float: right;
			font-size: 20px;
			font-weight: bold;
		}
		.close:hover {
			color: black;
			cursor: pointer;
		}

		.editor {
			display: inline-flex;
			gap: 10px;
			font-family: monospace;
			line-height: 21px;
			background: #282a3a;
			border-radius: 2px;
			padding: 20px 10px;
			height: 500px;
			overflow-y: auto;
		}
		.line-numbers {
			width: 20px;
			text-align: right;
			height: 9999px;
		}
		.line-numbers span {
			counter-increment: linenumber;
		}
		.line-numbers span::before {
			content: counter(linenumber);
			display: block;
			color: #506882;
		}
		textarea {
			height: 9999px;
			line-height: 21px;
			overflow-y: hidden;
			padding: 0;
			border: 0;
			background: #282a3a;
			color: #FFF;
			min-width: 90%;
			outline: none;
			resize: none;
		}
		.table tbody tr:hover {
			background-color: d2eef3;
			border-color: 0dcaf0;
		}
	</style>

	<script>
		window.addEventListener('load', function () {
			const textarea = document.querySelector('textarea');
			const lineNumbers = document.querySelector('.line-numbers');

			if (textarea) {
				textarea.addEventListener('input', handleInput);
				textarea.addEventListener('keydown', handleTabKey);
				handleInput({target: textarea});
			}

			function handleInput(event) {
				const numberOfLines = event.target.value.split('\n').length + 1;
				lineNumbers.innerHTML = Array(numberOfLines).fill('<span></span>').join('');
			}

			function handleTabKey(event) {
				if (event.key === 'Tab') {
					const start = textarea.selectionStart;
					const end = textarea.selectionEnd;

					textarea.value = textarea.value.substring(0, start) + '\t' + textarea.value.substring(end);
					textarea.focus();

					event.preventDefault();
				}
			}
		});

		function createNewFolder() {
			let foldername = document.querySelector('#foldername').value;
			let rootfolder = document.querySelector('#rootfolder').value;
			if (foldername.length === 0 || rootfolder.length === 0) {
				showAlert("Folder name is empty!");
				return false;
			}
			fetch(`?create=folder&path=${rootfolder}&foldername=${encodeURIComponent(foldername)}`)
				.then(response => response.text())
				.then(data => {
					console.log(data);
					if (data === "Folder already exists") {
						showAlert("The Folder already exists! Delete it to create a new one");
					} else {
						showAlert("Folder created!");
						setTimeout(() => { location.reload(); }, 1000);
					}
				});
			return false;
		}

		const url_self = '<?php echo $base_url; ?>';
		function saveChanges(filename) {
			let newContent = document.querySelector('textarea').value;
			let folder = document.querySelector('#folder').value;

			let data = new FormData();
			data.append("save", "file");
			data.append("folder", folder);
			data.append("filename", filename);
			data.append("newContent", newContent);

			fetch(url_self, {
				method: 'POST',
				body: data,
			})
				.then(response => response.text())
				.then(data => {
					showAlert("Changes have been saved!");
				});
			return false;
		}

		function createNewFile() {
			let folder = document.querySelector('#folder').value;
			let filename = document.querySelector('#filename').value;
			let contents = document.querySelector('#fileContents').value;
			if (filename.length === 0) {
				showAlert("Filename name is empty!");
				return false;
			}
			let data = new FormData();
			data.append("folder", folder);
			data.append("filename", filename);
			data.append("contents", contents);
			console.log(filename);

			fetch(url_self, {
				method: 'POST',
				body: data,
			})
				.then(response => (response.text()))
				.then(data => {
					if (data === "File already exists") {
						showAlert("The file already exists! Delete it to create a new one");
					} else {
						showAlert("File created!");
						setTimeout(() => { location.reload(); }, 1000);
					}
				});

			return false;
		}


		function confirmDelete(fileName) {
			document.getElementById('text-alert').innerText = "Are you sure you want to delete the file " + decodeURIComponent(fileName) + "?";
			document.getElementById('custom-alert').style.display = 'block';
			document.getElementById('confirm_buttons').style.display = 'block';

			const okButton = document.getElementById('okButton');
			const cancelButton = document.getElementById('cancelButton');

			okButton.onclick = function() {
				document.location='?delete=' + fileName;
				document.getElementById('confirm_buttons').style.display = 'none';
			}

			cancelButton.onclick = function() {
				document.getElementById('custom-alert').style.display = 'none';
				document.getElementById('confirm_buttons').style.display = 'none';
			}
		}

		function confirmDeleteFolder(folderName) {
			document.getElementById('text-alert').innerText = "!!! Are you sure to delete the folder " + decodeURIComponent(folderName) + "?";
			document.getElementById('custom-alert').style.display = 'block';
			document.getElementById('confirm_buttons').style.display = 'block';

			const okButton = document.getElementById('okButton');
			const cancelButton = document.getElementById('cancelButton');

			okButton.onclick = function() {
				document.location='?deleteFolder=' + folderName;
				document.getElementById('confirm_buttons').style.display = 'none';
			}

			cancelButton.onclick = function() {
				document.getElementById('custom-alert').style.display = 'none';
				document.getElementById('confirm_buttons').style.display = 'none';
			}
		}

		function showAlert(msg) {
			document.getElementById('text-alert').innerHTML = msg;
			document.getElementById('custom-alert').style.display = 'block';
			document.getElementById('confirm_buttons').style.display = 'none';
		}
		function closeAlert() {
			document.getElementById('custom-alert').style.display = 'none';
		}
	</script>
</head>
<body data-bs-theme="dark">
<div id="custom-alert" class="alert">
	<span class="close" onclick="closeAlert()">&times;</span><br /><p id='text-alert'></p><br />
	<div id="confirm_buttons"><button id="okButton" class="btn btn-success">ОК</button> <button id="cancelButton" class="btn btn-danger">Cancel</button></div>
</div>
<div class="container">
	<h1 onclick="location.href='<?php echo $base_url; ?>'" role="button">File Manager</h1>

	<?php
	if (!empty($_GET['create'])) {
		?>

		<h2>Create New File</h2>
		<form id="createFile" onsubmit="return false;">
			<input type="hidden" class="hidden" id="folder" autocomplete="off" value="<?php echo $path; ?>" />
			<input type="text" id="filename" class="form-control" placeholder="Filename" autocomplete="off" />
			<div class="editor form-control">
				<div class="line-numbers"><span></span></div>
				<textarea id="fileContents" class="form-control-off" placeholder="File contents" rows=25></textarea><br />
			</div>
			<br />
			<button class="btn btn-success mt-4" onclick="createNewFile()">Create File</button>
		</form>
		<br />
		<hr />

		<h2>Create New Folder</h2>
		<div class="row">
			<div class="col-xs-8 col-sm-8">
				<form id="createFolder" class="d-flex" onsubmit="return false;">
					<input type="text" id="rootfolder" class="form-control" disabled autocomplete="off" value="<?php echo $path; ?>" />
					<input type="text" id="foldername" class="form-control" autocomplete="off" placeholder="Folder name" />
					<button type="submit" class="btn btn-warning w-50" onclick="createNewFolder()" onsubmit="return false;">Create Folder</button>
				</form>
			</div>
		</div>
		<br />
		<hr />

		<?php
		exit("</div></body></html>");
	}
	?>
	<div class="row">
		<div class="col-xs-8 col-sm-6">
			<form class="d-flex" method="get" action="">
				<input type="text" class="form-control me-2" name="search" placeholder="Search by name" autocomplete="off" value="<?php echo $search_filename; ?>" />
				<input type="hidden" name="directory" value="<?php echo $path; ?>">
				<button type="submit" class="btn btn-primary">Search</button>
			</form>
		</div>
		<div class="col-xs-2 col-sm-2"></div>
		<div class="col-xs-2 col-sm-4">
			<a class="btn btn-info text-end" href="<?php if (!empty($_GET['directory'])) { echo $request_full_url . '&create=new'; } else { echo '?create=new'; } ?>">Create New File or Folder</a>
		</div>
	</div>
	<hr />
	<?php
	if (!empty($_GET['open'])) {
		$fileContents = $directoryLister->openFile($_GET["open"]);
		echo '<br />
			<h3>Contents of: <u>' . $_GET["open"] . '</u></h3> <a href="?directory=' . ($path) . '">⬆️ Go Up</a>  
			<input type="hidden" class="hidden" id="folder" autocomplete="off" value="' . dirname($path). '" />	
 			<div class="editor form-control">
			  <div class="line-numbers"><span></span></div>
			  <textarea id="fileContents" class="form-control-off" placeholder="File contents" rows=25>' . $fileContents . '</textarea><br />
			</div><br /><br />
			<button class="btn btn-success" onclick=\'saveChanges("' . urlencode($_GET["open"]) . '")\' onsubmit="return false;">Save Changes</button>';

		echo '<br /><hr />
			<h3>Rename or move file</h3>
			<form class="d-flex m-4" method="get" action="">
				<input type="text" name="oldName" class="form-control mx-2" placeholder="Old Name" autocomplete="off" value="' . ($_GET["open"]) . '" />
				<input type="text" name="newName" class="form-control mx-2" placeholder="New Name" autocomplete="off" value="' . ($_GET["open"]) . '" />
				<button type="submit" class="btn btn-primary mx-2">Rename/move</button>
			</form><span>*add file path</span>
			<br /><hr />';
	}

	echo $result_search;
	echo $result_catalog;

	if (!empty($_GET['directory']) && $result_catalog !== null && empty($_GET['open'])) {
		echo '<br /><hr />
			<h3>Rename or move folder</h3>
			<form class="d-flex m-4" method="get" action="">
				<input type="text" name="oldName" class="form-control mx-2" placeholder="Old Name" autocomplete="off" value="' . ($_GET["directory"]) . '" />
				<input type="text" name="newName" class="form-control mx-2" placeholder="New Name" autocomplete="off" value="' . ($_GET["directory"]) . '" />
				<button type="submit" class="btn btn-primary mx-2">Rename/move</button>
			</form> 
			<br />';
	}
	?>

</div></body></html>
