<?php
/*
Copyright (c) 2013, Rajitha Wannigama
All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/
/**
 * JavaScript packager.
 * Requires PHP version 5.3.0 or above.
 * @author Rajitha Wannigama
 */
define('PACKJS_VERSION', '0.1.0');

$short_opts = 'wvxd:c:i:e:m:o:p:q:';
$long_opts = array(
    'create:',
    'internal-name:',
    'external-name:',
    'main-file:',
    'output-file:',
    'package-start-file:',
    'package-end-file:'
);

$opt = getopt($short_opts, $long_opts);
// Print application version and exit.
if (isset($opt['v'])) {
    // The -v option is set.
    echo 'PackJS version ' . PACKJS_VERSION . PHP_EOL;
    echo 'Copyright (c) 2013 Rajitha Wannigama' . PHP_EOL;
    exit;
}

/**
 * Convert a given string to a valid javascript identifier.
 * Invalid characters are replaced with an underscore.
 * @param string $name
 * @return string a valid javascript name
 */
function get_js_name($name) {
    $js_name = $name;
    // Convert project name into a valid javascript variable name
    if ( preg_match('/[a-z]|_|\$/i', $js_name[0]) != 1 ) {
        // Invalid start character, replace with underscore.
        $js_name[0] = '_';
    }
    for ($i = 1; $i < strlen($js_name); $i++) {
        // Replace invalid characters with an underscore.
        if ( preg_match('/[a-z]|[0-9]|_|\$/i', $js_name[$i]) != 1 ) {
            $js_name[$i] = '_';
        }
    }
    return $js_name;
}

// Create an empty project
// The folder, if it exists, must be empty.
if (isset($opt['create'])) {
    // Create the project structure
    if ( count($argv) < 3 ) {
        echo 'Error: Invalid syntax for option --create' . PHP_EOL;
        echo PHP_EOL;
        echo 'Usage: php packjs.php --create <directory>' . PHP_EOL;
        echo '<directory> can be relative or a full path. "." and ".." can be used for the ' .
             'current and parent directories, respectively.' . PHP_EOL;
        exit;
    }
    // Get the full absolute path for the project directory
    $proj_dir = realpath($opt['create']);
    if ( $proj_dir === false ) {
        echo 'Error: Invalid path given: ' . $opt['create'] . PHP_EOL;
        exit;
    }
    if ( is_file($proj_dir) ) {
        echo 'Error: Path given is a file: ' . $proj_dir . PHP_EOL;
        exit;
    }
    
    // Create the directory if it doesn't exist.
    if ( !is_dir($proj_dir) ) {
        echo 'Creating project folder ' . $proj_dir . PHP_EOL;
        if ( mkdir($proj_dir) === false ) {
            echo 'Error: could not create the project folder.' . PHP_EOL;
            exit;
        }
    }
    $num_files = count(scandir($proj_dir));
    // All directories have "." and ".." as files.
    if ( $num_files > 2 ) {
        // Directory is not empty. Cannot create project here.
        echo 'Project directory is not empty (contains ' . ($num_files-2) . ' files)' . PHP_EOL;
        echo 'Project directory: ' . $proj_dir . PHP_EOL;
        echo 'Please choose an empty directory or one that doesn\'t exist' . PHP_EOL;
        exit;
    }
    
    // Create "src" and "bin" directories.
    $src_dir = $proj_dir . DIRECTORY_SEPARATOR . 'src';
    $bin_dir = $proj_dir . DIRECTORY_SEPARATOR . 'bin';
    
    if ( mkdir($src_dir) === false ) {
        echo 'Error: could not create the "src" directory: ' . $src_dir . PHP_EOL;
        exit;
    }
    if ( mkdir($bin_dir) === false ) {
        echo 'Error: could not create the "bin" directory: ' . $bin_dir . PHP_EOL;
        exit;
    }
    
    // Create the config file
    $proj_name = pathinfo($proj_dir, PATHINFO_BASENAME);
    $js_name = get_js_name($proj_name);
    
    
    // The contents of the config file.
    $ini_str = <<<INI
; Configuration for the $proj_name project
[$proj_name]

; The variable name used internally for the main closure.
; If a main file is used, this variable must be declared as a function in <main_file>.js
internal_name = "main"

; The <external_name> is the name used for the global (javascript) variable where
; the main closure function is accessed.
; e.g. if external_name = "ME" then the window.ME property is used to access the
; packages and functions.
external_name = "$js_name"

; Project main file (use of the main file is optional). File extension must be given.
; If the main file is present, the <internal_name> MUST be used for the main
; closure.
main_file = "main.js"

; The filename of the output file, including the extension. This file is stored in
; the "bin" directory.
output_file = "$proj_name.js"

; For package declarations
; <package_start_file> is the file that is outputted at the start of each package declaration,
; before all the source files are outputted.
; <package_end_file> is the file that is outputted at the end of each package declaration, after
; all the source files are outputted.
package_start_file = "_start.js"
package_end_file = "_end.js"

INI;
    $config_file = $proj_dir . DIRECTORY_SEPARATOR . 'config.ini';
    // Create a config.ini file.
    if ( file_put_contents($config_file, $ini_str) === false ) {
        echo 'Warning: Could not create the config file: ' . $config_file . PHP_EOL;
        echo '(The config file is optional)' . PHP_EOL;
    }
    
    // Create the main file
    $main_file = $src_dir . DIRECTORY_SEPARATOR . 'main.js';
    $main_str = <<<JS
/**
 * The main file contains any "global" variables and the main closure function
 * that is used to access all the packages inside this project.
 * 
 * This file is optional.
 * 
 * This file can be used to specify custom behaviour, for example, by enabling the use
 * of the main closure as a function as well as a package.
 * Any variables used by all packages can also be declared here.
 *
 * @version 0.1.0
 * @author <Your Name>
 *
 */
/**
 * Keep track of the old property in the window object that our closure will
 * replace, in case of conflict.
 */
var _$js_name = window['$js_name'];

/**
 * The main closure function.
 */
var main = function() {};

/**
 * Resolve naming conflict.
 * @returns {Function} main closure
 */
main.noConflict = function() {
    // Only replace with the previous value if the current value is this library.
    if ( window['$js_name'] === main ) {
        window['$js_name'] = _$js_name;
    }
    return main;
}

JS;
    // Create a main.js file.
    if ( file_put_contents($main_file, $main_str) === false ) {
        echo 'Warning: Could not create the main file: ' . $main_file . PHP_EOL;
        echo '(The main file is optional)' . PHP_EOL;
    }
    
    echo PHP_EOL;
    echo 'PackJS has successfully created an empty project!' . PHP_EOL;
    
    exit;
}

/*
Steps:

- Parse command line options
- Parse ini file
- Parse ignore list

If an error is found here, throw an Exception.

- Scan directory tree
    + If the project folder structure is wrong, throw an Exception.
- Read files and write to temporary file in the "bin" directory.
    + Check the ignore list before writing.
    + If there is an error reading any file or writing to the temporary file,
      throw an Exception.

If no Exception was thrown, the project can successfully be packed.

- Start closure.
- If the main file is present, print that here. Otherwise initialize the generic
    main closure.
    + If no main file is present but the src folder contains files, show a warning.
      The user might have forgot to update the main_file in config.ini.
- Declare top level package variables using var.
- Declare 2nd level packages, then 3rd level and so on, going from top to bottom.
- Add content of packages from the temp file.
- Assign top level package variables to the main closure.

- Append project code
- Determine external name for project
    + Search the main file to check if the internal_name is used. If not, show
      a warning, since the user might have forgot to change the config.ini file.
- Expose project to external window object
- Return the inner function
- Output to a file
    + Check if the file exists first! Then use a confirmation to
     overwrite.

- Write to temporary file before saving final output. In case there is an error
- and everything needs to be undone.
- Is the -w (overwrite) option set?
    + [BOTH]. If the "bin" folder does not exist, create it.
    + YES. Write to file.
    + NO. Check if output file exists. If it does, prompt user to overwrite file.

- Display a list of ignored files.

Exceptions

Warnings are shown with prefix [WARNING] and errors are shown with prefix [ERROR]


*/

/*
 * Command line options.
 *      
 * Options without values
 *      -w
 *          If output file exists, overwrite without asking.
 *      -v
 *          Display the PackJS version and exit (without packing, even if other options are present)
 *      -x
 *          Do not read the config file, even if it exists! This is useful if a config file already exists
 *          with the name "config.ini" for a different purpose.
 *          If both -x and -c options are given, -x is used. i.e. no config file is read.
 * 
 * Options with values
 *      --create
 *          Create the project structure in the given directory.
 *          "." means current directory.
 *          Creates the "src" and "bin" directories, the "config.ini" file and the "main.js" file.
 *      -d
 *          Path to the project directory. If this is not given, the current directory is used.
 *          This is the path used by the PHP scandir function.
 *      -c
 *          Path to the config file for this project. By default this is a file named "config.ini" in the project
 *          root directory. The path must include the filename.
 *          If both -x and -c options are given, -x is used. i.e. no config file is read.
 *      -i, --internal-name
 *          Variable name used for the main closure.
 *          Defaults to "main".
 *      -e, --external-name
 *          Externally accessible name of the main closure.
 *          Defaults to the directory name of the project.
 *      -m, --main-file
 *          The filename of the main file for the project.
 *          Defaults to "main.js".
 *      -o, --output-file
 *          Defaults to the directory name of the project + ".js". e.g. if the directory name
 *          is "chess" then output-file = "chess.js".
 *      -p, --package-start-file
 *          The filename of the javascript file to be outputted at the START of the package declaration.
 *          Defaults to "_start.js".
 *      -q, --package-end-file
 *          The filename of the javascript file to be outputted at the END of the package declaration.
 *          Defaults to "_end.js".
 * 
 * Usage:
 *      - Change to directory of the project.
 *        e.g.
 *              if project is in "C:\Projects\myproj"
 *          then
 *              pwd must print "C:\Projects\myproj" and not "C:\Projects".
 *      - Run this script by
 *         (Assume script is localed in "C:\phpstuff\packjs.php")
 *        php.exe "C:\phpstuff\packjs.php"
 *
 */

/*
 * Define constants for errors and warnings.
 */
// Errors
const ERROR_UNKNOWN = 0;

const ERROR_PROJECT_PATH = 9;
const ERROR_CONFIG_PATH = 8;

const ERROR_CONFIG_PARSE = 10;

const ERROR_RSCAN_DIR = 11;
const ERROR_PROJECT_STRUCTURE = 12;

const ERROR_MAIN_FILE_READ = 13;
const ERROR_SOURCE_READ = 14;

const ERROR_BIN_IS_FILE = 15;
const ERROR_BIN_CREATE = 16;
const ERROR_TMP_FILE_CREATE = 17;
const ERROR_TMP_FILE_OPEN = 18;
const ERROR_TMP_FILE_WRITE = 19;
const ERROR_OUTPUT_FILE_WRITE = 20;

const INTERRUPT_PROMPT_NO = 21;

// Warnings.
const WARNING_INTERNAL_NAME_NOT_FOUND = 100;
const WARNING_MAIN_FILE_MISSED = 101;

/**
 * 
 * @param Exception $e
 */
function print_exception($e) {
    echo $e->getMessage();
}
set_exception_handler('print_exception');

/**
 * Recursively scan a directory for the list of all FILES contained within that
 * directory hierarchy.
 * Note that only files (their full path) are returned, directories are not!
 *
 * @param string filepath the full path to the directory or file
 * @param array result [optional] this parameter is used by the function itself.
 * @return array containing the full path to all the files inside this directory
 * and all its subdirectories.
 * 
 * If the $filepath is a file, an array with the full path to that file is returned.
 * If the $filepath is an empty directory, an empty array is returned.
 * @throws PackJSException if scandir fails to read a directory.
 */
function r_scan($filepath, $result = array()) {
   if ( is_dir($filepath) ) {
       // The filepath points to a DIRECTORY.
       $contents = scandir($filepath);
       if ( $contents === false ) {
           throw new PackJSException(ERROR_RSCAN_DIR);
       }
       else {
           // Go through all the files (and directories) inside this directory.
           foreach ($contents as $f) {
               // Ignore . and ..
               if ( ($f != ".") && ($f != "..") ) {
                   // Get the full path of this file.
                   $fpath = $filepath . DIRECTORY_SEPARATOR . $f;
                   // Recursively scan the file path again.
                   // If the file path is a directory, all its contents will again be
                   // recursively added to the same array.
                   $result = r_scan($fpath, $result);
               }
           }
           //return array($filepath => r_scan($filepath));
           return $result;
       }
   }
   else {
       // Base case - the filepath points to a FILE.
       //return array($filepath);
       $result[] = $filepath;
       return $result;
       //return array(basename($filepath) => $filepath);
   }
}

/**
 * Used to print warnings to the user.
 * If no warnings are present, prints empty string.
 * @author Rajitha Wannigama
 */
class PackJSWarning {
    /**
     * List of warnings to display.
     * @var array 
     */
    private $warnings;
    
    /**
     * 
     */
    public function __construct() {
        $this->warnings = array();
    }
    
    /**
     * Add a new warning.
     * @param int $code
     * @param string $detail [optional]
     */
    public function addWarning($code, $detail = '') {
        if ( isset($this->warnings[$code]) ) {
            // This warning has been set already.
            return;
        }
        else {
            switch ($code) {
                case WARNING_INTERNAL_NAME_NOT_FOUND:
                    $this->warnings[WARNING_INTERNAL_NAME_NOT_FOUND] = '[WARNING] internal_name "' . $detail . '" missing in the main file.';
                    break;
                case WARNING_MAIN_FILE_MISSED:
                    $this->warnings[WARNING_MAIN_FILE_MISSED] = '[WARNING] Main file not found but the project src directory contains files.';
                    break;
                default:
                    break;
            }
        }
        
    }
    
    public function __toString() {
        $str = '';
        foreach ($this->warnings as $w) {
            $str .= $w . PHP_EOL;
        }
        return $str;
    }
}

/**
 * To display errors.
 * @author Rajitha Wannigama
 */
class PackJSException extends Exception {
    /**
     * 
     * @param int $code must be one of the error constants.
     * @param string $detail [optional]
     */
    public function __construct($code, $detail = '') {
        $previous = null;
        $message = '';
        switch ($code) {
            case ERROR_PROJECT_PATH:
                $message = '[ERROR] Invalid project path.';
                break;
            case ERROR_CONFIG_PATH:
                $message = '[ERROR] Invalid path for configuration file.';
                break;
            case ERROR_CONFIG_PARSE:
                $message = '[ERROR] Could not parse the config file.';
                break;
            case ERROR_RSCAN_DIR:
                $message = '[ERROR] Recursive directory scan failed.';
                break;
            case ERROR_PROJECT_STRUCTURE:
                $message = '[ERROR] Incorrect project structure.';
                break;
            case ERROR_MAIN_FILE_READ:
                $message = '[ERROR] Could not read main file.';
                break;
            case ERROR_SOURCE_READ:
                $message = '[ERROR] Could not read source file: ' . $detail;
                break;
            case ERROR_BIN_IS_FILE:
                $message = '[ERROR] "bin" is a file.';
                break;
            case ERROR_BIN_CREATE:
                $message = '[ERROR] Could not create "bin" directory.';
                break;
            case ERROR_TMP_FILE_CREATE:
                $message = '[ERROR] Could not create a temporary file.';
                break;
            case ERROR_TMP_FILE_OPEN:
                $message = '[ERROR] Could not open temporary file.';
                break;
            case ERROR_TMP_FILE_WRITE:
                $message = '[ERROR] Could not write to the temporary file.';
                break;
            case ERROR_OUTPUT_FILE_WRITE:
                $message = '[ERROR] Could not write to the output file.';
                break;
            case INTERRUPT_PROMPT_NO:
                $message = '';
                break;
            case ERROR_UNKNOWN:
            default:
                $message = '[ERROR] An unknown error occured.';
                $code = '0';
                break;
        }
        parent::__construct($message, $code, $previous);
    }
}

/**
 * Write to the output file.
 * Uses a temporary file to write output until the whole project is written out.
 * Then the temporary file is copied to the output file.
 * @author Rajitha Wannigama
 */
class PackJSWriter {
    private $tmp_fname;
    private $tmp_handle;
    private $output_fname;
    
    /**
     * Create a new PackJSWriter. Creates the directory given by the $bin_dir
     * paramater (first param) if it does not exist.
     * 
     * If the bin directory was created by this class, it will not be deleted even on failure.
     * 
     * @param string $bin_dir full path to the "bin" directory where the output file is
     * written. This is where the temporary file is created.
     * @param string $output_fname full path to the output file.
     * @throws PackJSException
     */
    public function __construct($bin_dir, $output_fname) {
        // Create the bin directory if it doesn't already exist.
        if ( !is_dir($bin_dir) ) {
            if ( is_file($bin_dir) ) {
                // There is a file named "bin" inside the project directory so we
                // cannot create a folder with the name "bin".
                // This is an error and we cannot proceed.
                throw new PackJSException(ERROR_BIN_IS_FILE);
            }
            else {
                // bin directory does not exist, create it.
                if ( mkdir($bin_dir) === false ) {
                    // Could not create bin directory.
                    throw new PackJSException(ERROR_BIN_CREATE);
                }
            }
        }
        $this->tmp_fname = tempnam($bin_dir, 'PJS');
        if ( $this->tmp_fname === false ) {
            throw new PackJSException(ERROR_TMP_FILE_CREATE);
        }
        $this->tmp_handle = fopen($this->tmp_fname, 'w');
        if ( $this->tmp_handle === false ) {
            // Remove the temporary file created.
            unlink($this->tmp_fname);
            throw new PackJSException(ERROR_TMP_FILE_OPEN);
        }
        $this->output_fname = $output_fname;
    }
    
    /**
     * Prompt the user, if necessary, before writing to output file.
     * If the command line option -w is set, no prompt is given.
     * If not set, a prompt is given if the output file exists.
     * @param boolean $w true if option -w is set
     * @throws PackJSException
     */
    public function prompt($w) {
        if ( ($w === false) && is_file($this->output_fname) ) {
            // Prompt user to replace output_file that already exists.
            echo 'Output file "' . $this->output_fname . '" already exists.' . PHP_EOL;
            echo 'Do you want to overwrite this file? (y/n): ';
            $prompt_input = fgetc(STDIN);
            if ( $prompt_input != 'y' ) {
                // Do not overwrite
                // Remove temporary file
                $this->delete();
                throw new PackJSException(INTERRUPT_PROMPT_NO);
            }
        }
    }
    
    /**
     * Write to tmp file.
     * @param string $txt
     * @throws PackJSException
     */
    public function write($txt) {
        $check = fwrite($this->tmp_handle, $txt);
        if ( $check === false ) {
            $this->delete();
            throw new PackJSException(ERROR_TMP_FILE_WRITE);
        }
    }
    
    /**
     * Finish writing to tmp and copy to output file.
     * Deletes the tmp file.
     * @throws PackJSException
     */
    public function done() {
        // Stop writing to tmp file.
        $this->close();
        // Copy contents of tmp file to output file.
        $check = copy($this->tmp_fname, $this->output_fname);
        // Delete the tmp file since we are done (regardless of $check)
        $this->delete();
        if ( $check === false ) {
            // Could not copy to output file.
            throw new PackJSException(ERROR_OUTPUT_FILE_WRITE);
        }
    }
    
    /**
     * Close the handle to the tmp file.
     */
    private function close() {
        if ( $this->tmp_handle !== null ) {
            fclose($this->tmp_handle);
        }
        $this->tmp_handle = null;
    }
    
    /**
     * Close the handle to the tmp file and delete the file.
     */
    public function delete() {
        $this->close();
        // Remove tmp file
        unlink($this->tmp_fname);
    }
}

/**
 * The Pack JS application.
 * Project directory must follow this format:
 * 
 * <project-name>/-|
 *                 |-bin/
 *                 |-src/-|
 *                 |      |-<package-name>/-|
 *                 |      |                 |-<package_start_file>.js [optional]
 *                 |      |                 |-<package_end_file>.js [optional]
 *                 |      |                 |-MyObject.js
 *                 |      |                 |-sub_folder/-|
 *                 |      |                               |-AnotherObject.js
 *                 |      |                               |-Blabla.js
 *                 |      |                               |-random.js
 *                 |      |          
 *                 |      |-<main-file>.js [optional]
 *                 |
 *                 |-<config>.ini
 * 
 * The project must be contained in a folder which contains the two folders
 * "src" and "bin". The "src" folder contains the project source code organized
 * into packges as shown above.
 * @author Rajitha Wannigama
 */
class PackJS {
    /**
     * If an output file already exists, this value determines whether to replace
     * it or not. "true" means replace without prompt, "false" will prompt the
     * user to replace the file.
     * @var boolean 
     */
    private $w;
    /**
     * The full path to the project directory.
     * @var string 
     */
    private $project_dir;
    /**
     * Path to the config file, if different from the standard path.
     * @var string 
     */
    private $config_path;
    /**
     * The configuration settings for the project.
     * @var array 
     */
    private $config;
    /**
     * Application warnings.
     * @var PackJSWarning 
     */
    private $warnings;
    /**
     * 
     * @var Project 
     */
    private $project;
    /**
     * Writer used to write to the output file.
     * @var PackJSWriter 
     */
    private $writer;
    
    /**
     * Initialize the application.
     * @param array $opt the command line options
     * @throws Exception if there is an error reading the config file.
     */
    public function __construct($opt) {
        // Project directory
        if ( isset($opt['d']) ) {
            // Make sure the path is the full path.
            $this->project_dir = realpath($opt['d']);
            if ( $this->project_dir === false ) {
                throw new PackJSException(ERROR_PROJECT_PATH);
            }
        }
        else {
            // Project directory not set. Use current working directory.
            $this->project_dir = getcwd();
        }
        // Config path
        if ( isset($opt['c']) ) {
            // Make sure the path is the full path.
            $this->config_path = realpath($opt['c']);
            if ( $this->config_path === false ) {
                throw new PackJSException(ERROR_CONFIG_PATH);
            }
        }
        else {
            $this->config_path = $this->project_dir . DIRECTORY_SEPARATOR . 'config.ini';
        }
        // For overriding output file automatically. true = override without prompt.
        $this->w = isset($opt['w']);
        
        // The name of the project - the name of the project directory
        // Used for external_name and output_file
        $proj_name = pathinfo($this->project_dir, PATHINFO_BASENAME);
        $js_name = get_js_name($proj_name);
        
        // Set up default config
        $this->config = array();
        $this->config['internal_name'] = 'main';
        $this->config['external_name'] = $js_name;
        $this->config['main_file'] = 'main.js';
        $this->config['output_file'] = $proj_name . '.js';
        $this->config['package_start_file'] = '_start.js';
        $this->config['package_end_file'] = '_end.js';
        
        // Used to tell user if the config file wasn't found.
        $cf_detail = '';
        // Do not read config file if the -x option is given.
        if ( !isset($opt['x']) ) {
            if ( is_file($this->config_path) ) {
                $config_ini = parse_ini_file($this->config_path);
                if ( $config_ini !== false ) {
                    // Override default values with ones from config file.
                    foreach ( $config_ini as $key => $val ) {
                        // Only override options we need, in case there are random options set
                        // in the config file.
                        if ( isset($this->config[$key]) ) {
                            $this->config[$key] = $val;
                        }
                    }
                }
                else {
                    // Error, could not parse config file
                    throw new PackJSException(ERROR_CONFIG_PARSE);
                }
            }
            else {
                // Config file does not exist, so we can ignore it.
                $cf_detail = ' [NOT FOUND!]';
            }
        }
        
        // Get all command line options for project configuration.
        $config_tmp = array();
        $short_opts = array('i','e','m','o','p','q');
        // Note command line options use a dash instead of an underscore.
        $short_to_long = array(
            'i' => 'internal-name',
            'e' => 'external-name',
            'm' => 'main-file',
            'o' => 'output-file',
            'p' => 'package-start-file',
            'q' => 'package-end-file'
        );
        $long_opts = array(
            'internal-name',
            'external-name',
            'main-file',
            'output-file',
            'package-start-file',
            'package-end-file'
        );
        foreach ($short_opts as $option) {
            if ( isset($opt[$option]) ) {
                $config_tmp[ $short_to_long[$option] ] = $opt[$option];
            }
        }
        // If both short and long options are set for the same option, the long
        // option overrides the short one.
        foreach ($long_opts as $option) {
            if ( isset($opt[$option]) ) {
                $config_tmp[$option] = $opt[$option];
            }
        }
        
        // These are the final options.
        // Command line options override config.ini, which overrides default options.
        if ( isset($config_tmp['internal-name']) ) {
            $this->config['internal_name'] = $config_tmp['internal-name'];
        }
        if ( isset($config_tmp['external-name']) ) {
            $this->config['external_name'] = $config_tmp['external-name'];
        }
        if ( isset($config_tmp['main-file']) ) {
            $this->config['main_file'] = $config_tmp['main-file'];
        }
        if ( isset($config_tmp['output-file']) ) {
            $this->config['output_file'] = $config_tmp['output-file'];
        }
        if ( isset($config_tmp['package-start-file']) ) {
            $this->config['package_start_file'] = $config_tmp['package-start-file'];
        }
        if ( isset($config_tmp['package-end-file']) ) {
            $this->config['package_end_file'] = $config_tmp['package-end-file'];
        }
        
        echo 'Project directory: ' . $this->project_dir . PHP_EOL;
        echo "Configuration file$cf_detail: " . $this->config_path . PHP_EOL;
        echo 'Settings used:' . PHP_EOL;
        foreach ($this->config as $key => $val) {
            echo "\t$key = $val" . PHP_EOL;
        }
        echo PHP_EOL;
        
        // To store all warnings
        $this->warnings = new PackJSWarning();
        
        // Create the project
        $src_dir = $this->project_dir . DIRECTORY_SEPARATOR . 'src';
        if ( !is_dir($src_dir) ) {
            // src directory not present. This is an error.
            throw new PackJSException(ERROR_PROJECT_STRUCTURE);
        }
        $files = r_scan($src_dir);
        $this->project = new Project($src_dir, $files,
                $this->config['main_file'],
                $this->config['package_start_file'],
                $this->config['package_end_file'],
                $this->warnings);
        
        // Create the writer
        $bin_dir = $this->project_dir . DIRECTORY_SEPARATOR . 'bin';
        $output_fname = $bin_dir . DIRECTORY_SEPARATOR . $this->config['output_file'];
        $this->writer = new PackJSWriter($bin_dir, $output_fname);
    }
    
    /**
     * Pack the project into one JavaScript file.
     * This function saves the output to the "bin" directory of the project
     * and uses the filename set in the config file. 
     * @throws PackJSException
     */
    public function pack() {
        // Prompt the user before writing to output file (if necessary).
        $this->writer->prompt($this->w);
        
        // Start writing the output.
        $code = '(function(window) {' . PHP_EOL;
        $this->writer->write($code);
        
        // Get main file data
        $code = '';
        $main_file = $this->project->getMainFile();
        if ( $main_file === null ) {
            // No main file. Output standard code.
            $code = 'var ' . $this->config['internal_name'] . ' = function() {};' . PHP_EOL . PHP_EOL;
        }
        else {
            $code = file_get_contents($main_file);
            if ( $code === false ) {
                $this->writer->delete();
                throw new PackJSException(ERROR_MAIN_FILE_READ);
            }
            $code .= PHP_EOL;
            // Check if the internal_name is present in the main file.
            $match = preg_match('/\s' . $this->config['internal_name'] . '\s/', $code);
            if ( $match == 0 ) {
                // No internal_name found in the main file. This could be a mistake
                // by the programmer.
                // Show a warning at the end.
                $this->warnings->addWarning(WARNING_INTERNAL_NAME_NOT_FOUND, $this->config['internal_name']);
            }
        }
        // Write the main file output.
        $this->writer->write($code);
        
        $code = '';
        $expose_code = '';
        $total_files = 0;
        $packages = $this->project->getPackages();
        // Ouput packages
        foreach ( $packages as $p ) {
            $files = $p->getFiles();
            $name = $p->getName();
            
            // Add packages to the main closure
            $expose_code .= $this->config['internal_name'] . ".$name = $name;" . PHP_EOL;
            
            $count_files = count($files);
            
            $code = 'function _' . $name . '() {' . PHP_EOL;
            $this->writer->write($code);
            
            // Since the start file is optional, we do not care if it doesn't exist.
            if ( $p->getStart() != '' ) {
                $code = file_get_contents($p->getStart());
                $count_files++;
            }
            else {
                $code = '';
            }
            $this->writer->write($code . PHP_EOL);
            
            // Write the source files
            foreach ($files as $f) {
                $code = file_get_contents($f);
                if ( $code === false ) {
                    $this->writer->delete();
                    throw new PackJSException(ERROR_SOURCE_READ, $f);
                }
                $code .= PHP_EOL;
                $this->writer->write($code);
            }
            
            // Since the end file is optional, we do not care if it doesn't exist.
            if ( $p->getEnd() != '' ) {
                $code = file_get_contents($p->getEnd());
                $count_files++;
            }
            else {
                $code = '';
            }
            $this->writer->write($code . PHP_EOL . '}' . PHP_EOL);
            $this->writer->write("var $name = new _$name();" . PHP_EOL . PHP_EOL);
            
            $total_files += $count_files;
            echo 'Packing "' . $name . '" package... (' . number_format($count_files) . ' files)' . PHP_EOL;
        }
        
        $this->writer->write(PHP_EOL . $expose_code);
        
        // Expose the main closure to the window.
        $code = PHP_EOL;
        $code .= 'window.' . $this->config['external_name'] . ' = ' . $this->config['internal_name'] . ';' . PHP_EOL;
        $code .= 'return ' . $this->config['internal_name'] . ';' . PHP_EOL;
        $code .= '})(window);' . PHP_EOL;
        $this->writer->write($code);
        
        // Finish writing - create output file.
        $this->writer->done();
        
        // Finally print the warnings
        echo $this->warnings;
        echo PHP_EOL;
        echo 'Project "' . pathinfo($this->project_dir, PATHINFO_BASENAME) .
                '" successfully packed! (' . number_format($total_files) . ' files)';
    }
}

/**
 * Represents a JavaScript "package".
 */
class Package {
    /**
     * Package name. Might differ from the folder name since the folder name
     * is converted to a valid javascript name.
     * @var string 
     */
    private $name;
    /**
     * Full path of the <package_end_file>
     * @var string 
     */
    private $start;
    /**
     * Full path of the <package_start_file>
     * @var string 
     */
    private $end;
    /**
     * Source files.
     * @var array 
     */
    private $files;
    public function __construct($name, $start = '', $end = '', $files = array()) {
        $this->name = $name;
        $this->start = $start;
        $this->end = $end;
        $this->files = $files;
    }
    
    public function setName($name) {
        $this->name = $name;
    }
    public function setStart($start) {
        $this->start = $start;
    }
    public function setEnd($end) {
        $this->end = $end;
    }
    public function addFile($file) {
        $this->files[] = $file;
    }
    
    public function getName() {
        return $this->name;
    }
    public function getStart() {
        return $this->start;
    }
    public function getEnd() {
        return $this->end;
    }
    public function getFiles() {
        return $this->files;
    }
}

/**
 * For each top-level package create a new variable with "var" to make it local.
 * All sub-packages are simply properties of this variable.
 * 
 * The DIRECTORY_SEPARATOR constant is used to separate packages.
 * @author Rajitha Wannigama
 */
class Project {
    /**
     * Reference to the warnings object used by the PackJS class.
     * This is so that all warnings can be kept in one place and printed at the
     * end.
     * @var PackJSWarning 
     */
    private $warnings;
    /**
     * Full path to the main file.
     * @var string 
     */
    private $main_file;
    /**
     * array(
     *      0 => array('chess','ajax'), // these are the top level packages
     *      1 => array('chess\ai','chess\networking'), // 2nd level
     *      2 => array('chess\ai\ext','chess\networking\http') // third level
     * )
     * @var array 
     */
    private $packages;
    
    /**
     * Create a new JavaScript project.
     * @param string $src_dir the full path to the project src directory.
     * @param array $files the files in the src directory (full paths)
     * @param string $main_file the filename of the main file (as defined in config file). This is NOT the
     * full path.
     * @param string $start_file the filename of the package start file.
     * @param string $end_file the filename of the package end file.
     * @param PackJSWarning $warnings reference to warnings object of PackJS class.
     * @throws PackJSException
     */
    public function __construct($src_dir, $files, $main_file, $start_file, $end_file, $warnings) {
        //$this->project_dir = $project_dir;
        $this->warnings = $warnings;
        $this->packages = array();
        $this->main_file = null;
        
        $len_srcdir = strlen($src_dir);
        
        $main_file_missed = false;
        
        // Go through all files in the src directory.
        // .js files are filtered here.
        foreach ($files as $f) {
            // We only care about .js files.
            if ( pathinfo($f, PATHINFO_EXTENSION) == 'js' ) {
                // Get the path relative to the src directory.
                // c:\projects\web\jschess\src\chess\game.js = \chess\game.js
                // c:\projects\web\jschess\src\main.js = \main.js
                $rpath = substr($f, $len_srcdir);
                // Count number of DIRECTORY_SEPARATOR characters.
                $dsc = substr_count($rpath, DIRECTORY_SEPARATOR);
                if ( $dsc == 1 ) {
                    // This is the src directory.
                    if ( $rpath == (DIRECTORY_SEPARATOR . $main_file) ) {
                        // Main file found! Store the full path.
                        $this->main_file = $f;
                        $main_file_missed = false;
                    }
                    else {
                        // This is not the main file, but src dir doesn't seem
                        // to be empty so add a warning.
                        $main_file_missed = true;
                    }
                }
                else if ( $dsc > 1 ) {
                    // Get relative folder path (get dirname and remove leading DIRECTORY_SEPARATOR)
                    $pname = substr(pathinfo($rpath, PATHINFO_DIRNAME), 1);
                    $tmp = explode(DIRECTORY_SEPARATOR, $pname);
                    // Actual package name
                    $js_pname = get_js_name($tmp[0]);
                    if ( !isset($this->packages[$js_pname]) ) {
                        $this->packages[$js_pname] = new Package($js_pname);
                    }
                    
                    $package = $this->packages[$js_pname];
                    if ( pathinfo($f, PATHINFO_BASENAME) == $start_file ) {
                        $package->setStart($f);
                    }
                    else if ( pathinfo($f, PATHINFO_BASENAME) == $end_file ) {
                        $package->setEnd($f);
                    }
                    else {
                        // This is a source file inside this package.
                        $package->addFile($f);
                    }
                }
                else {
                    // This is an ERROR.
                    // This would happen if $dsc is <= 0.
                    // Which can only happen if one of the file paths is outside
                    // the project directory.
                    // Therefore this wouldn't usually happen unless there is an
                    // error in the function that reads the project directory.
                    throw new PackJSException(ERROR_PROJECT_STRUCTURE);
                }
            }
        }
        if ( $main_file_missed ) {
            $this->warnings->addWarning(WARNING_MAIN_FILE_MISSED);
        }
    }
    
    /**
     * Get the project's main file.
     * @return string the full path to the main file or null if no main file
     * is present.
     */
    public function getMainFile() {
        return $this->main_file;
    }
    
    /**
     * Get all the packages for this project (including top level) as an array
     * of arrays. The first index represents the level of the package and the
     * second is the actual package.
     * 
     * e.g.
     * 
     * array(
     *      0 => array('chess','ajax'), // these are the top level packages
     *      1 => array('chess\ai','chess\networking'), // 2nd level
     *      2 => array('chess\ai\ext','chess\networking\http') // third level
     * )
     * @return array of Package objects. Keys are the package names.
     */
    public function getPackages() {
        return $this->packages;
    }
}

$pjs = new PackJS($opt);
$pjs->pack();
?>
