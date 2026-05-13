<?php
$data = json_decode(file_get_contents('php://input'), true);
$adminPwd = "53535353"; // Mot de passe actuel
if($data['old'] !== $adminPwd){
  echo json_encode(["message"=>"Mot de passe actuel incorrect"]);
}else{
  $adminPwd = $data['new'];
  echo json_encode(["message"=>"Mot de passe changé avec succès"]);
}
?>