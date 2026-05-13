<?php
session_start();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$adminEmail = "kodjaouelisee1234@gmail.com";
$dataFile = "data.json";

$response = ["status" => "error", "message" => "Erreur inconnue"];

function clean_text($text) {
    $text = trim((string)$text);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
    return function_exists("mb_substr") ? mb_substr($text, 0, 5000) : substr($text, 0, 5000);
}

function absolute_asset_url($path) {
    if (!$path) return "";
    if (preg_match('#^https?://#i', $path)) return $path;
    return "https://emploi-info.page.gd/" . ltrim($path, "/");
}

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $response['message'] = "Token CSRF invalide.";
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $nom = clean_text($_POST['nom'] ?? "");
    $whatsapp = clean_text($_POST['whatsapp'] ?? "");
    $email = clean_text($_POST['email'] ?? "");
    $titre = clean_text($_POST['titre'] ?? "");
    $categorie = clean_text($_POST['categorie'] ?? "");
    $ville = clean_text($_POST['ville'] ?? "");
    $texte = clean_text($_POST['texte'] ?? "");
    $notice = clean_text($_POST['notice'] ?? "");
    $date = $_POST['date'] ?? date("c");

    if (!$nom || (!$whatsapp && !$email) || !$titre || !$categorie || !$ville || !$texte) {
        $response['message'] = "Veuillez remplir tous les champs obligatoires.";
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $bannierePath = "";
    if (isset($_FILES['banniere']) && $_FILES['banniere']['error'] === 0) {
        if (($_FILES['banniere']['size'] ?? 0) > 10 * 1024 * 1024) {
            $response['message'] = "Fichier trop lourd, maximum 10 Mo.";
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }
        $uploadDir = "uploads/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext = strtolower(pathinfo($_FILES['banniere']['name'], PATHINFO_EXTENSION));
        $allowedExt = ["jpg","jpeg","png","gif","webp","mp4","webm","ogg","mov","m4v","pdf","doc","docx","txt"];
        if (!in_array($ext, $allowedExt, true)) {
            $response['message'] = "Format de fichier non autorisé.";
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['banniere']['tmp_name']);
        finfo_close($finfo);
        $allowedMime = [
            "image/jpeg","image/png","image/gif","image/webp",
            "video/mp4","video/webm","video/ogg","video/quicktime",
            "application/pdf","application/msword",
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            "text/plain","application/octet-stream"
        ];
        if (!in_array($mime, $allowedMime, true)) {
            $response['message'] = "Type de fichier refusé.";
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }
        $filename = uniqid("banniere_") . "." . $ext;
        $target = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['banniere']['tmp_name'], $target)) {
            $bannierePath = $target;
        }
    }

    $data = [];
    if (file_exists($dataFile)) {
        $decoded = json_decode(file_get_contents($dataFile), true);
        $data = is_array($decoded) ? $decoded : [];
    }

    $id = "ann_" . time() . rand(100, 999);

    $newOffer = [
        "id" => $id,
        "nom" => $nom,
        "whatsapp" => $whatsapp,
        "email" => $email,
        "titre" => $titre,
        "categorie" => $categorie,
        "ville" => $ville,
        "texte" => $texte,
        "notice" => $notice,
        "banniere" => $bannierePath,
        "date" => $date,
        "publie" => false,
        "urgent" => false,
        "boutons" => []
    ];

    array_unshift($data, $newOffer);

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($dataFile, $json, LOCK_EX) === false) {
        $response['message'] = "Impossible de sauvegarder l'offre. Veuillez réessayer.";
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $admins = [];
    if (file_exists("admins.json")) {
        $admins = json_decode(file_get_contents("admins.json"), true) ?? [];
    }

    $subject = "[EMPLOI INFO] Nouvelle offre en attente";
    $message = "Nouvelle offre envoyée à l'administration :\n\n";
    $message .= "Nom : $nom\n";
    if ($whatsapp) $message .= "WhatsApp : $whatsapp\n";
    if ($email) $message .= "Email : $email\n";
    $message .= "Titre : $titre\n";
    $message .= "Catégorie : $categorie\n";
    $message .= "Ville : $ville\n";
    $message .= "Texte :\n$texte\n";
    if ($notice) $message .= "\nNotice : $notice\n";
    $message .= "\nDate : $date\n";
    if ($bannierePath) $message .= "\nBannière : " . getcwd() . "/$bannierePath";

    $headers = "From: noreply@emploi-info.page.gd\r\n";
    foreach ($admins as $admin) {
        $to = filter_var($admin['email'] ?? '', FILTER_VALIDATE_EMAIL) ? $admin['email'] : $adminEmail;
        @mail($to, $subject, $message, $headers);
    }

    $response['status'] = "success";
    $response['message'] = "Votre offre a été envoyée à l'administration pour validation.";
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
