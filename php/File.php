<?php
require_once("String.php");
class RecursiveDotFilterIterator extends  RecursiveFilterIterator {
    public function accept() {
        return '.' !== substr($this->current()->getFilename(), 0, 1);
    }
}

class PHPFile {
    public $base_dir;

    public function __construct($base_dir = false){
        if($base_dir !== false){
            $this->base_dir = $base_dir;
        }
        else {
            $this->base_dir = './';
        }
    }

    public function file_json_content($dir){
        $dir = $this->base_dir.$dir;
        if (is_file($dir)) {
            $contents = file_get_contents($dir);
            if($contents !== false){
                return json_decode($contents, true);
            }
        }
        return false;
    }

    public function file_force_contents($dir, $contents){
        $dir = str_replace("\\", "/", $dir);
        $parts = explode('/', $dir);
        $file = array_pop($parts);
        $dir = $this->base_dir;
        foreach($parts as $part){
            if(!is_dir($dir .= "/$part")) {
                mkdir($dir, 0777);
            }
        }
            
        file_put_contents("$dir/$file", $contents);
    }

    public function file_force_copy($dir, $source, $name){
        $dir = str_replace("\\", "/", $dir);
        $parts = explode('/', $dir);
        $dir = $this->base_dir;
        foreach($parts as $part){
            if(!is_dir($dir .= "/$part")) {
                mkdir($dir, 0777);
            }
        }
        copy($source, $dir.'/'.$name);
    }

    public function delete_file($dir){
        if(!unlink($dir)) {
            echo 'Error delete file at : '. $dir; 
        }
    }
}

class PHPFileScaner {
    public $dir;
    private $mode = "type";
    public $filter = array();
    public $ignore = array();
    public $output = "";
    public $isShowLogs = false;

    public function __construct($dir, $mode="type", $filter=[], $ignore=[], $output, $isShowLogs){
        $this->dir = $dir;
        if($mode !== "type" && $mode !== "path" ) {
            $this->mode = "name";
        }
        $this->filter = $filter;
        $this->ignore = $ignore;
        $this->output = $output;
        $this->isShowLogs = $isShowLogs;
    }

    private function validate(){
        if(empty($this->dir)) {
            echo ">>>>> dir cannot empty".PHP_EOL;
            return false;
        }
        if(count($this->filter) === 0) {
            echo ">>>>> filter cannot empty".PHP_EOL;
            return false;
        }
        if(empty($this->output)) {
            echo ">>>>> output path cannot empty".PHP_EOL;
            return false;
        }
        return true;
    }

    public function scan(){
        if($this->validate() === false){die();}
        $output = array(
            "list" => array(),
    		"total" => 0,
    		"totalFiles" => 0,
            "junkSize" => 0,
    		"size" => 0,
            "size_des" => "",
            "junk_Size_des" => ""
        );
        try {
            
            $it = new RecursiveDirectoryIterator($this->dir);
            $excludeDirs = $this->ignore;

            $files = new RecursiveCallbackFilterIterator($it, function($file, $key, $iterator) use ($excludeDirs){
                //echo "{$file->getFilename()}".PHP_EOL;
                if(!in_array($file->getFilename(), $excludeDirs)){
                    return true;
                }
                return $file->isFile();
            });

            foreach(new RecursiveIteratorIterator($files) as $file) {
                if(contain_in_string("{$file}", $this->ignore) === true) { 
                    continue; 
                }
                if($this->filterFile($file) === true){
                    $this->logs("Found ".$file);

                    $info = array();
		    		$info["file_creation"] = filectime($file);
		    		$info["last_modified"] = filemtime($file);
		    		$info["last_accessed"] = fileatime($file);
		    		$info["file_name"] = basename($file);
		    		$info["file_size"] = filesize($file);
		    		$info["file_path"] = "{$file}";
		    		$info["md5_file"] = md5_file($file);
		    		$output["size"]  += $info["file_size"];

                    $key = $info["md5_file"]."_".$info["file_size"];
                    if(array_key_exists($key, $output["list"]) == false) {
		    			$output["list"][$key] = array(
		    				"item" => array(),
		    				"total" => 0
		    			);
		    		}
                    $output["list"][$key]["item"][] = $info;
		    		$output["list"][$key]["total"] += 1;
                    $output["totalFiles"] += 1;
                }
            }
            $this->mapOutput($output);

        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
            die();
        }
    }

    private function mapOutput($output){
        $fileHelper = new PHPFile("");
        $content = $fileHelper->file_json_content($this->output);
        //echo(json_encode($output, JSON_PRETTY_PRINT));
        if($content === false){
            $content = $output;
        }
        else {
            $content["size"] += $output["size"];
            $content["totalFiles"] += $output["totalFiles"];
            foreach($output["list"] as $key => $value) {
                if(array_key_exists($key, $content["list"]) == false) {
                    $content["list"][$key] = array(
                        "item" => array(),
                        "total" => 0
                    );
                }
                $content["list"][$key]["item"] = array_merge($content["list"][$key]["item"], $value["item"]);
                $content["list"][$key]["total"] = count($content["list"][$key]["item"]);
                if($content["list"][$key]["total"] > 1) {
                    $content["junkSize"] += ($content["list"][$key]["total"] - 1)*$content["list"][$key]["item"][0]["file_size"];
                }
            }
        }

        $content["total"] = count($content["list"]);
        $content["size_des"] = readable_size($content["size"]);
        $content["junk_Size_des"] = readable_size($content["junkSize"]);

        $this->logs(">>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>");
        $this->logs("Found totals files: ".$content["totalFiles"]." files");
        $this->logs("Found totals file group: ".$content["total"]." groups");
        $this->logs("Totals sizes: ".$content["size_des"]);
        $this->logs("Totals junk sizes: ".$content["junk_Size_des"]);
        $this->logs(">>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>");

        $fileHelper->file_force_contents($this->output, json_encode($content, JSON_PRETTY_PRINT));
        $this->logs("Output save at : ".$this->output);
        $this->logs(">>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>");
    }

    private function filterFile($file){
        //echo "{$this->mode} - {$file}".PHP_EOL;
        if($this->mode == "type"){
            return in_array(strtolower(array_pop(explode('.', $file))), $this->filter);
        }
        else if($this->mode == "name"){
            return in_array(basename($file), $this->filter);
        }
        else if($this->mode == "path"){
            return in_array("{$file}", $this->filter);
        }
    }

    private function logs($str){
        if($this->isShowLogs){
            echo ">>>> ".$str.PHP_EOL;
        }
    }
}

class PHPFileScanerManager {
    private $shortopts = "m::f:d:o::i::l";
    private $longopts = array(
        "mode::",
        "filter:",
        "dir:",
        "output::",
        "ignore::",
        "log"
    );

    private $config = array(
        "mode" => "type",
        "filter" => array(),
        "dir" => "./",
        "output" => "./tmp.json",
        "ignore" => array("xcassets", "vendor", "node_modules", "Pods", ".git"),
        "log" => false
    );

    public function update($config){
        $this->config = array_merge($this->config, $config);
    }

    public function __construct(){
        $options = getopt($this->shortopts, $this->longopts);
        foreach($options as $key => $value) {
            if($key=="mode"|| $key=="m"){
                $config["mode"] = $value;
            }
            if($key=="filter"|| $key=="f"){
                $config["f"] = explode(",", $value);
            }
            if($key=="dir"|| $key=="d"){
                $config["dir"] = $value;
            }
            if($key=="output"|| $key=="o"){
                $config["output"] = $value;
            }
            if($key=="ignore"|| $key=="i"){
                $config["ignore"] = explode(",", $value);
            }
            if($key=="log"|| $key=="l"){
                $config["log"] = true;
            }
        }
    }

    public function startScan(){
        $fileScaner = new PHPFileScaner($this->config["dir"], $this->config["mode"], $this->config["filter"], $this->config["ignore"], $this->config["output"], $this->config["log"]);
        $fileScaner->scan();
    }
}
