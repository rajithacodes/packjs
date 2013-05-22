<a href="http://rajitha.co/packjs.html" target="_blank">
  <img src="http://rajitha.co/images/packjs/packjs_logo.png" width="93" height="28" alt="PackJS logo">
</a>

# [PackJS v0.1.0] (https://github.com/rajithadotco/packjs)
A simple tool to help manage JavaScript projects.

## Requirements

PHP version 5.3.0 or higher is required to use this tool.

Your project needs to be structured as shown in the diagram below.

The "src" folder contains all the JavaScript sources and the "bin" folder is used for the
output from PackJS.

    <project-name>/-|
                    |-bin/
                    |-src/-|
                    |      |-<package-name>/-|
                    |      |                 |-<package_start_file>.js [optional]
                    |      |                 |-<package_end_file>.js [optional]
                    |      |                 |-MyObject.js
                    |      |                 |-sub_folder/-|
                    |      |                               |-AnotherObject.js
                    |      |                               |-Blabla.js
                    |      |                               |-random.js
                    |      |          
                    |      |-<main-file>.js [optional]
                    |
                    |-<config>.ini


## Usage

Navigate to your project folder and use the following command:

    php packjs.php

## Documentation

For more information please see the documentation located [here] (http://rajitha.co/packjs.html).

## Project

This project doesn't have many files, here is an explanation of all the files:

* "hello" directory contains the example project explained in the documentation page located at [rajitha.co/packjs.html] (http://rajitha.co/packjs.html)
* "packjs.php" is the program itself. Simply run this script using the PHP interpreter to pack your javascript project.
* "LICENSE" contains the description of the license.

## License

This software is available under the BSD (FreeBSD) style license.
