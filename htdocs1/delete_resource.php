<?php
header("Content-Type: application/json");
$index=json_decode(file_get_contents("php://input"),true)['index'] ?? null;
$file="resources.json";
$resources=json_decode(file_get_contents($file),true) ?? [];
if($index!==null && isset($resources[$index])){
    array_splice($resources,$index,1);
    file_put_contents($file,json_encode($resources,JSON_PRETTY_PRINT));
}
echo json_encode(["status"=>"success"]);
?>