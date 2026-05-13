<?php
header("Content-Type: application/json");
$index=json_decode(file_get_contents("php://input"),true)['index'] ?? null;
$file="services.json";
$services=json_decode(file_get_contents($file),true) ?? [];
if($index!==null && isset($services[$index])){
    array_splice($services,$index,1);
    file_put_contents($file,json_encode($services,JSON_PRETTY_PRINT));
}
echo json_encode(["status"=>"success"]);
?>
