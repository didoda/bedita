<?php

/**
 * Component for importing/exporting bedita data: database, media.
 * 
 * Functions:
 * 
 * $SnapshotComponent::import($file) - import data from compressed file $file
 * $SnapshotComponent::export() - export data from snapshot of bedita (database and media) into compressed file
 */

class SnapshotComponent extends Object {
	
	//var $uses = array('BEObject', 'Stream');
	//var $components = array('Transaction');
	
	private $basepath;
	
	function __construct() {
		foreach ($this->uses as $model) {
			if(!class_exists($model))
				App::import('Model', $model) ;
			$this->{$model} = new $model() ;
		}
		foreach ($this->components as $component) {
			if(isset($this->{$component})) continue;
			$className = $component . 'Component' ;
			if(!class_exists($className))
				App::import('Component', $component);
			$this->{$component} = new $className() ;
		}
		if (!defined('SQL_SCRIPT_PATH')) {
			throw new Exception("SQL_SCRIPT_PATH has to be defined in ".APP_DIR."/config/database.php");
		}
		if (!defined('MEDIA_ROOT')) {
			throw new Exception("MEDIA_ROOT has to be defined in ".APP_DIR."/config/bedita.ini.php");
		}
	}

	public function update($sqlDataFile=null,$media=null) {
		// update database (schema,procedure,data)
		$this->executeScript(SQL_SCRIPT_PATH . "bedita_schema.sql");
		$this->executeScript(SQL_SCRIPT_PATH . "bedita_procedure.sql");
		if($sqlDataFile!=null) {
			$this->executeInsert($sqlDataFile);
		}
		
		if ($media!=null)) {
            $this->extractFile($media,MEDIA_ROOT);
    	}
		
		// check media
		$this->checkMedia();
	}

	/**
	 * 3 steps import:
	 * 1 - extract compressed file
	 * 2 - execute sql (schema,procedure,data)
	 * 3 - copy media data folder into media_root folder
	 */
	public function import($exportFile) {
		
		$this->$basepath = $this->setupTempDir();
		
		// step 1 - extract file
		$this->extractFile($exportFile,$this->basepath);
		
		// step 2 - import database (schema,procedure,data)
		$this->executeScript(SQL_SCRIPT_PATH . "bedita_schema.sql");
		$this->executeScript(SQL_SCRIPT_PATH . "bedita_procedure.sql");
		$this->executeInsert($this->basepath . "bedita-data.sql");
		
		// step 3 - copy media folder into MEDIA_ROOT
		$this->copyFolder($this->basepath.'media',MEDIA_ROOT);
	}
	
	/**
	 * 3 steps export:
	 * 1 - create sql data dump in export folder
	 * 2 - save media_root to export folder
	 * 3 - zip export folder
	 */
	public function export() {
		
		$this->$basepath = $this->setupTempDir();

		// step 1 - save db data to sql file
		$sqlFileName = $this->basepath."bedita-data.sql";
		$this->saveDump($sqlFileName);
		
		// step 2 - save MEDIA_ROOT to export folder
		$this->copyFolder(MEDIA_ROOT,$this->basepath.'media');
		
		// step 3 - compress export folder
		$this->compressFolder($this->basepath);
	}
	
	function saveDump($sqlFileName) {
		$dbDump = new DbDump();
		$tables = $dbDump->tableList();
		$handle = fopen($sqlFileName, "w");
		if($handle === FALSE) 
			throw new Exception("Error opening file: ".$sqlFileName);
		$dbDump->tableDetails($tables, $handle);
		fclose($handle);
	}
	
	function extractFile($file,$destPath) {
		$zipFile = self::DEFAULT_ZIP_FILE;
    	if (isset($file)) {
            $zipFile = $file;
    	}
    	$zip = new ZipArchive;
		if ($zip->open($zipFile) === TRUE) {
			$zip->extractTo($destPath);
			$zip->close();
		} else {
			throw new Exception("Error extracting file: ".$zipFile);
		}
	}
	
	function compressFolder($folderPath,$expFile) {
		$zip = new ZipArchive;
		$res = $zip->open($expFile, ZIPARCHIVE::CREATE);
		$folder=& new Folder($folderPath);
        $tree= $folder->tree($folderPath, false);
        foreach ($tree as $files) {
            foreach ($files as $file) {
                if (!is_dir($file)) {
       				$contents = file_get_contents($file);
        			if ( $contents === false ) {
						throw new Exception("Error reading file content: $file");
        			}
					$p = substr($file, strlen($folderPath));
					if(!$zip->addFromString("media".DS.$p, $contents )) {
						throw new Exception("Error adding $p to zip file");
					}
					unset($contents);
                }
            }
        }
		$zip->close();
	}
	
	function executeScript($script) {
		$db =& ConnectionManager::getDataSource('default');
		$sql = file_get_contents($script);
		$queries = array();
		$SplitterSql = new SplitterSql() ;
		$SplitterSql->parse($queries, $sql) ;
		foreach($queries as $q) {	
			if(strlen($q)>1) {
				$res = $db->execute($q);
				if($res === false) {
					throw new Exception("Error executing query: ".$q);
				}
			}
		}
	}
	
	function executeInsert($sqlFileName) {
		$db =& ConnectionManager::getDataSource('default');
		$handle = fopen($sqlFileName, "r");
		if($handle === FALSE) 
			throw new Exception("Error opening file: ".$sqlFileName);
		$q = "";
		while(!feof($handle)) {
			$line = fgets($handle);
			if($line === FALSE && !feof($handle)) {
				throw new Exception("Error reading file line");
			}
			if(strncmp($line, "INSERT INTO ", 12) == 0) {
				if(strlen($q) > 0) {
					$res = $db->execute($q);
					if($res === false) {
						throw new Exception("Error executing query: ".$q."\n");
					}
				}
				$q="";
			}
			$q .= $line;
		}
		// last query...
		if(strlen($q) > 0) {
			$res = $db->execute($q);
			if($res === false) {
				throw new Exception("Error executing query: ".$q."\n");
			}
		}
	}
	
	function copyFolder($from,$to) {
		$folder = new Folder($to);
		$ls = $folder->ls();
		if(count($ls[0]) > 0 || count($dls[1]) > 0) {
			$this->removeMediaFiles();
		}
		$copts=array('to'=>$to,'from'=>$from,'chmod'=>0755);
		$res = $folder->copy($copts);
	}

	private function setupTempDir() {
    	$basePath = getcwd().DS."export-tmp".DS;
		if(!is_dir($basePath)) {
			if(!mkdir($basePath))
				throw new Exception("Error creating temp dir: ".$basePath);
		} else {
    		$this->__clean($basePath);
		}
    	return $basePath;
    }
	
    private function removeMediaFiles() {
       $this->__clean(MEDIA_ROOT . DS. 'imgcache');
       $folder= new Folder(MEDIA_ROOT);
       $dirs = $folder->ls();
       foreach ($dirs[0] as $d) {
       	    if($d !== 'imgcache') {
       	    	$folder->delete(MEDIA_ROOT . DS. $d);
       	    }
       }  	
    }
	
    private function __clean($path) {
        $folder=& new Folder($path);
        $list = $folder->ls();
        foreach ($list[0] as $d) {
        	if($d[0] != '.') { // don't delete hidden dirs (.svn,...)
	        	if(!$folder->delete($folder->path.DS.$d)) {
	                throw new Exception("Error deleting dir $d");
	            }
        	}
        }
        foreach ($list[1] as $f) {
        	$file = new File($folder->path.DS.$f);
        	if(!$file->delete()) {
                throw new Exception("Error deleting file $f");
            }
        }
        return ;
    }
	
	public function checkMedia() {
		$stream = new Stream();
        // check filesystem
		$folder=& new Folder(MEDIA_ROOT);
        $tree= $folder->tree(MEDIA_ROOT, false);
		$mediaOk = true;
        foreach ($tree as $files) {
            foreach ($files as $file) {
                if (!is_dir($file)) {
                    $file=& new File($file);
					$p = substr($file->pwd(), strlen(MEDIA_ROOT));
					if(stripos($p, "/imgcache/") !== 0) {
						$f = $stream->findByPath($p);
						if($f === false) {
							$mediaOk = false;
						}
					}
                }
            }
        }
        // check db
		$allStream = $stream->findAll();
		$mediaOk = true;
        foreach ($allStream as $v) {
        	$p = $v['Stream']['path'];
        	if(!file_exists(MEDIA_ROOT.$p)) {
					$mediaOk = false;
        	}
        }
	}
}

class DumpModel extends AppModel {
	var $useTable = "objects";
}

class DbDump {
	
	private $model = NULL;
	
	public function __construct() {
		$this->model = new DumpModel();
	}
	
	public function tableList() {
   		$tables = $this->model->execute("show tables");
    	$res = array();
    	foreach ($tables as $k=>$v) {
    		$t1 = array_values($v);
    		$t2 = array_values($t1[0]);
    		if (strncasecmp($t2[0], 'view_', 5) !== 0) // exclude views
    			$res[]=$t2[0] ;
    	}
    	return $res;
    }
    
    public function tableDetails($tables, $handle) {
    	fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
    	foreach ($tables as $t) {
    		$this->model->setSource($t); 
    		$select = $this->model->find('all');
			foreach ($select as $sel) {
				$fields = "";
				$values = "";
				$count = 0;
				foreach ($sel['DumpModel'] as $k=>$v) {
					if($count > 0) {
						$fields .= ",";
						$values .= ",";
					}
					$fields .= "`$k`";
					if($v == NULL)
						$values .= "NULL";					
					else 
						$values .= "'".addslashes($v)."'";
					$count++;
				}
				$res = "INSERT INTO $t (".$fields.") VALUES ($values);\n";
    			fwrite($handle, $res);
			}
    	}
    	return $res;
    }
}
?>