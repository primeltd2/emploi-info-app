<?php
// Fichier de stockage des annonces
$dataFile = 'data.json';

// Vérifie si le fichier existe
if(file_exists($dataFile)){

    // Récupère le contenu JSON
    $data = json_decode(file_get_contents($dataFile), true);

    // Vérifie que c'est bien un tableau
    if(is_array($data)){

        $total = count($data);

        // Si on atteint 150 annonces ou plus
        if($total >= 150){

            // Supprime les 25 plus anciennes (début du tableau)
            $data = array_slice($data, 25);

            // Réécrit le fichier avec les données restantes
            file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
        }
    }
}
?>