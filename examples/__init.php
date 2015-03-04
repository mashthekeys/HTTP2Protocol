<?php
date_default_timezone_set('UTC');
$MATCHES = [
    '/../HTTP2.php',
    '/../HTTP2/*.php',
    '/../HTTP2/Frame/*.php'
];
foreach ($MATCHES as $MATCH) {
    foreach (glob(__DIR__.$MATCH) as $file) {
//        echo "AUTOLOADED: $file\n";
        include_once $file;
    }
}

//echo "---\n";
//var_export(get_declared_classes());
//echo "\n";
