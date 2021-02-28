## About

Class reflect information about entities in PHP file: constants, functions, interfaces, traits and classes.

## Usage example

Data bridge from PHP to third-party Java/С/Pascal applications:

~~~php
#!/usr/bin/php
<?php
require_once __DIR__ .'/vendor/autoload.php';

$options = getopt('f:', ['file:']);
$response =['status' => 'error', 'error' => null, 'data' => []];

$file = $options['file'] ?? ($options['f'] ?? false);
if (!$file) {
  $response['error'] = 'Required option -f (or --file) not set or empty';
  die(json_encode(response));
}

if (dirname($file) === '.') {
   $file = getcwd() . '/' . $file;
}
if (!file_exists($file)) {
  $response['error'] = "File {$file} not found";
  die(json_encode(response));
}

$response['status'] = 'success';
$response['data'] = Ensostudio\ReflectionFile::export($file, true);
echo json_encode(response);
~~~

## Install

`composer require ensostudio\reflection-file`

## license

[MIT](LICENSE)
