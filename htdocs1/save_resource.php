<?php
header("Content-Type: application/json");
$file="resources.json";
$titre=$_POST['titre'] ?? '';
$texte=$_POST['texte'] ?? '';
$lien=$_POST['lien'] ?? '';
$imgPath='';

if(isset($_FILES['img']) && getimagesize($_FILES['img']['tmp_name'])){
    $dir="images/";
    if(!is_dir($dir)) mkdir($dir,0777,true);
    $imgPath=$dir.basename($_FILES['img']['name']);
    move_uploaded_file($_FILES['img']['tmp_name'],$imgPath);
}

$resources=json_decode(file_get_contents($file),true) ?? [];
$resources[]= [
"id"=>uniqid(),
"titre"=>$titre,
"texte"=>$texte,
"lien"=>$lien,
"img"=>$imgPath,
"date"=>date("c")
];

file_put_contents($file,json_encode($resources,JSON_PRETTY_PRINT));
echo json_encode(["status"=>"success"]);
?>