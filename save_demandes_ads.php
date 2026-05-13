<?php
session_start();
if(!isset($_SESSION['admin_role'])) die("Non autorisé");

$data = json_decode(file_get_contents("php://input"), true);
file_put_contents("demandes_ads.json", json_encode($data, JSON_PRETTY_PRINT));
echo "OK";
?>