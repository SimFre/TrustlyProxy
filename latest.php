<?php

header("Content-type: application/json;charset=utf-8");
$dir = scandir("log/", SCANDIR_SORT_ASCENDING);
$dir = preg_grep('/\.json$/', $dir); 
echo file_get_contents("log/" . end($dir));

?>