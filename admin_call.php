<?php
header("Content-Type: text/html; charset=UTF-8");
require_once __DIR__ . "/admin_guard.php";
ei_require_admin([]);

$id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_GET["id"] ?? ""));
$storeFile = __DIR__ . "/admin_messages.json";
$store = file_exists($storeFile) ? json_decode(file_get_contents($storeFile), true) : [];
$meetings = is_array($store["meetings"] ?? null) ? $store["meetings"] : [];
$meeting = null;
foreach ($meetings as $item) {
    if (($item["id"] ?? "") === $id) {
        $meeting = $item;
        break;
    }
}
if (!$meeting || ($meeting["status"] ?? "") !== "active") {
    http_response_code(410);
    ?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Appel terminé</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0c0f14;color:#fff;font-family:Arial,sans-serif}.box{max-width:520px;padding:24px;border:1px solid rgba(255,215,0,.25);border-radius:18px;background:rgba(255,255,255,.05)}h1{color:#ffd86b}</style></head><body><div class="box"><h1>Lien d'appel invalide</h1><p>Cet appel a été arrêté ou n'est plus disponible.</p><a style="color:#ffd86b" href="admin.html">Retour au panel</a></div></body></html>
    <?php
    exit;
}
$room = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($meeting["room"] ?? ("emploi-info-admin-" . $id)));
$isHost = ($_GET["host"] ?? "") === "1";
$config = [
    "config.disableDeepLinking=true",
    "config.prejoinPageEnabled=false",
    "config.requireDisplayName=false",
    "config.enableClosePage=false",
    "interfaceConfig.SHOW_PROMOTIONAL_CLOSE_PAGE=false"
];
if (($meeting["type"] ?? "") === "audio") {
    $config[] = "config.startAudioOnly=true";
    $config[] = "config.startWithVideoMuted=true";
}
$src = "https://meet.jit.si/" . $room . "#" . implode("&", $config);
$safeTitle = htmlspecialchars($meeting["title"] ?? "Appel administrateur", ENT_QUOTES, "UTF-8");
$safeSrc = htmlspecialchars($src, ENT_QUOTES, "UTF-8");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="<?php echo $isHost ? 'width=1280, initial-scale=1.0' : 'width=device-width, initial-scale=1.0'; ?>">
<title>Appel administrateur</title>
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:grid;place-items:center;background:linear-gradient(135deg,#05070a,#141a22,#2a2108);color:#fff;font-family:Arial,sans-serif;padding:18px}
.box{width:min(620px,100%);padding:24px;border:1px solid rgba(255,215,0,.28);border-radius:8px;background:rgba(8,10,14,.95);box-shadow:0 22px 55px rgba(0,0,0,.45)}
.brand{display:flex;align-items:center;gap:12px;margin-bottom:18px}
.brand img{width:54px;height:54px;border-radius:8px;background:#fff;padding:5px;object-fit:contain}
h1{margin:0 0 8px;color:#ffd86b;font-size:25px}
p{margin:0;color:#e9edef;line-height:1.5}
.actions{display:grid;gap:10px;margin-top:20px}
a,button{display:flex;align-items:center;justify-content:center;border:0;border-radius:8px;padding:13px 14px;font-weight:900;text-decoration:none;cursor:pointer;font-size:15px}
.primary{background:linear-gradient(180deg,#ffdf7a,#d8a414);color:#111}
.secondary{background:rgba(255,255,255,.08);color:#fff;border:1px solid rgba(255,215,0,.18)}
.hint{margin-top:14px;padding:12px;border-radius:8px;background:rgba(255,215,0,.08);border:1px solid rgba(255,215,0,.16);color:#d8d8d8;font-size:14px}
.host-call-page{padding:0;display:block;background:#05070a}
.host-call-shell{min-width:1180px;width:100vw;height:100vh;display:grid;grid-template-rows:auto 1fr;background:#05070a}
.host-call-bar{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:10px 14px;background:#101820;border-bottom:1px solid rgba(255,215,0,.22)}
.host-call-bar strong{display:block;color:#ffd86b}
.host-call-bar span{display:block;color:#d8d8d8;font-size:13px}
.host-call-bar .actions{display:flex;gap:8px;margin:0}
.host-call-bar a,.host-call-bar button{padding:9px 12px;font-size:13px}
.host-call-frame{width:100%;height:100%;border:0;background:#000}
.return-panel-fab{
  position:fixed;
  right:14px;
  bottom:14px;
  z-index:20;
  padding:11px 14px;
  border-radius:8px;
  background:#ffd84d;
  color:#111;
  box-shadow:0 12px 28px rgba(0,0,0,.35);
}
</style>
</head>
<body<?php echo $isHost ? ' class="host-call-page"' : ''; ?>>
<?php if ($isHost): ?>
<main class="host-call-shell">
  <div class="host-call-bar">
    <div>
      <strong><?php echo $safeTitle; ?></strong>
      <span>Mode ordinateur pour celui qui lance l'appel</span>
    </div>
    <div class="actions">
      <button class="primary" id="openMeeting" type="button">Ouvrir hors page</button>
      <a class="secondary" href="/admin.html">Retour au panel</a>
    </div>
  </div>
  <iframe class="host-call-frame" src="<?php echo $safeSrc; ?>" allow="camera; microphone; fullscreen; display-capture; autoplay; clipboard-read; clipboard-write" allowfullscreen></iframe>
  <a class="return-panel-fab" href="/admin.html">Retour au panel</a>
</main>
<?php else: ?>
<main class="box">
  <div class="brand">
    <img src="/logo.png" alt="EMPLOI INFO">
    <div>
      <h1><?php echo $safeTitle; ?></h1>
      <p>L'appel s'ouvre directement sur Jitsi pour éviter la coupure liée à l'intégration iframe.</p>
    </div>
  </div>
  <div class="actions">
    <button class="primary" id="openMeeting" type="button">Ouvrir / rejoindre l'appel</button>
    <a class="secondary" href="/admin.html">Retour au panel d'administration</a>
  </div>
  <p class="hint" id="statusText">Si l'appel ne s'ouvre pas automatiquement, appuyez sur le bouton ci-dessus. Gardez cette page ouverte pour revenir facilement au panel.</p>
</main>
<a class="return-panel-fab" href="/admin.html">Retour au panel</a>
<?php endif; ?>
<script>
const meetingUrl = <?php echo json_encode($src, JSON_UNESCAPED_SLASHES); ?>;
const hostMode = <?php echo $isHost ? "true" : "false"; ?>;
function openMeeting(){
  const statusText = document.getElementById("statusText");
  if(statusText) statusText.textContent = "Ouverture de l'appel...";
  if(navigator.userAgent.includes("EmploiInfoAndroid")){
    location.href = meetingUrl;
    return;
  }
  const opened = window.open(meetingUrl, "_blank", "noopener");
  if(!opened){
    location.href = meetingUrl;
  }
}
document.getElementById("openMeeting").addEventListener("click", openMeeting);
if(!hostMode) setTimeout(openMeeting, 350);
</script>
</body>
</html>
