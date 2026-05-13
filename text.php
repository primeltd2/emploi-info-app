<?php  
session_start();  
$adminRole = $_SESSION['admin_role'] ?? '';  
$isLogged  = isset($_SESSION['admin_role']);  
$isLoggedJS = $isLogged ? 1 : 0;  
?>  
<!DOCTYPE html>  
<html lang="fr">  
<head>  
<meta charset="UTF-8">  
<meta name="viewport" content="width=device-width, initial-scale=1.0">  
<title>Admin | EMPLOI INFO</title>  
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>  
<style>  
*{margin:0;padding:0;box-sizing:border-box;font-family:Arial,sans-serif;}  
body{background:linear-gradient(135deg,#111,#3a2c00,#c9a227);color:white;min-height:100vh;}  
input,textarea,select,button{width:100%;padding:10px;margin-bottom:12px;border-radius:8px;border:none;font-size:14px;}  
button{background:gold;color:black;font-weight:bold;cursor:pointer;transition:0.3s;}  
button:hover{opacity:0.9;}  
button.delete{background:#ff4c4c;color:white;}  
button.validate{background:#4CAF50;color:white;}  
.card{background:rgba(0,0,0,.6);padding:15px;border-radius:15px;margin-bottom:20px;border-left:5px solid gold;}  
.action-btn{margin-right:6px;padding:6px 12px;border:none;border-radius:6px;font-weight:bold;cursor:pointer;}  
.cards{display:flex;gap:15px;flex-wrap:wrap;margin-bottom:20px;}  
header{display:flex;justify-content:space-between;align-items:center;background:rgba(0,0,0,.6);padding:10px 20px;border-radius:10px;margin-bottom:20px;}  
nav a{color:#fff;margin-left:15px;text-decoration:none;font-weight:bold;cursor:pointer;}  
nav a:hover{opacity:.8;}  
.section-title{margin-top:30px;margin-bottom:10px;font-size:18px;color:#FFD700;}  
ul{list-style:none;padding-left:0;}  
li{background:rgba(255,215,0,0.1);padding:8px;border-radius:6px;margin-bottom:6px;display:flex;justify-content:space-between;align-items:center;}  
#loginBox input,#loginBox button{width:100%;margin-bottom:10px;}  
#loginBox h2{margin-bottom:20px;}  
</style>  
</head>  
<body>  
  
<div id="loginBox" style="max-width:350px;margin:100px auto;background:rgba(0,0,0,.7);padding:25px;border-radius:15px;border:2px solid gold;display:none;">  
  <h2 style="text-align:center;color:gold;">ADMIN EMPLOI INFO</h2>  
  <input type="password" id="password" placeholder="Mot de passe admin">  
  <button onclick="login()">Connexion</button>  
  <p id="error" style="color:red;text-align:center;"></p>  
</div>  
  
<div id="adminPanel" style="display:none;padding:20px;">  
  
<header>  
  <strong>EMPLOI INFO – Admin</strong>  
  <nav>  
    <a onclick="showPage('dashboard')">Dashboard</a>  
    <a onclick="showPage('annonces')">Créer annonce</a>  
    <a onclick="showPage('categories')">Catégories</a>  
    <a onclick="showPage('entreprises')">Entreprises</a>  
    <a onclick="showPage('parametres')">Paramètres</a>  
    <a onclick="logout()">Déconnexion</a>  
  </nav>  
</header>  
  
<div id="contenuPrincipal">  
  
<div id="dashboard" style="display:none;">  
  <h1 style="text-align:center;margin-bottom:20px;">Dashboard | EMPLOI INFO</h1>  
  <div class="cards">  
    <div class="card"><h3>Total annonces</h3><p id="totalAnnonces">0</p></div>  
    <div class="card"><h3>Annonces publiées</h3><p id="publieesCount">0</p></div>  
    <div class="card"><h3>Annonces en attente</h3><p id="attenteCount">0</p></div>  
    <div class="card"><h3>Annonces urgentes</h3><p id="urgentAnnonces">0</p></div>  
  </div>  
  <h3 class="section-title">Statistiques</h3>  
  <canvas id="statsCanvas" width="400" height="200"></canvas>  
</div>  
  
<div id="annonces" style="display:none;">  
  <h1 style="text-align:center;margin-bottom:20px;">Créer une annonce</h1>  
  <div class="card">  
    <h3>Formulaire annonce</h3>  
    <input type="text" id="titre" placeholder="Titre">  
    <select id="categorie"></select>  
    <div style="display:flex;gap:10px;margin-bottom:10px;">  
      <button type="button" onclick="mettreGras()">Gras</button>  
      <button type="button" onclick="mettreItalic()">Italique</button>  
      <select id="alignement" onchange="updateApercu()">  
        <option value="left">Gauche</option>  
        <option value="center">Centré</option>  
        <option value="right">Droite</option>  
      </select>  
    </div>  
    <textarea id="texte" rows="6" placeholder="Description de l'annonce" oninput="updateApercu()"></textarea>  
    <input type="text" id="notice" placeholder="Notice (optionnelle)" oninput="updateApercu()">  
    <input type="file" id="banniere" onchange="updateApercu()">  
    <h4>Boutons d'action</h4>  
    <div id="boutonsContainer">  
      <div>  
        <input type="text" id="btn1_text" placeholder="Texte bouton 1" oninput="updateApercu()">  
        <select id="btn1_color" onchange="updateApercu()">  
          <option value="#FFD700">Gold</option>  
          <option value="#FF4C4C">Red</option>  
          <option value="#4CAF50">Green</option>  
          <option value="#2196F3">Blue</option>  
        </select>  
        <input type="text" id="btn1_link" placeholder="Lien bouton 1" oninput="updateApercu()">  
      </div>  
      <div>  
        <input type="text" id="btn2_text" placeholder="Texte bouton 2" oninput="updateApercu()">  
        <select id="btn2_color" onchange="updateApercu()">  
          <option value="#FFD700">Gold</option>  
          <option value="#FF4C4C">Red</option>  
          <option value="#4CAF50">Green</option>  
          <option value="#2196F3">Blue</option>  
        </select>  
        <input type="text" id="btn2_link" placeholder="Lien bouton 2" oninput="updateApercu()">  
      </div>  
      <div>  
        <input type="text" id="btn3_text" placeholder="Texte bouton 3" oninput="updateApercu()">  
        <select id="btn3_color" onchange="updateApercu()">  
          <option value="#FFD700">Gold</option>  
          <option value="#FF4C4C">Red</option>  
          <option value="#4CAF50">Green</option>  
          <option value="#2196F3">Blue</option>  
        </select>  
        <input type="text" id="btn3_link" placeholder="Lien bouton 3" oninput="updateApercu()">  
      </div>  
    </div>  
    <button type="button" onclick="publier()">Publier</button>  
  </div>  
  <h3 class="section-title">Aperçu annonce</h3>  
  <div id="apercuAnnonce" class="card"></div>  
  <h3 class="section-title">Annonces publiées</h3>  
  <div id="listePubliées"></div>  
  <h3 class="section-title">Annonces en attente</h3>  
  <div id="listeAttente"></div>  
</div>  
  
<div id="categories" style="display:none;">  
  <h1>Gestion des catégories</h1>  
  <div class="card">  
    <h3>Ajouter une catégorie</h3>  
    <input type="text" id="newCat" placeholder="Nom de la catégorie">  
    <button onclick="ajouterCategorie()">Ajouter</button>  
  </div>  
  <div class="card">  
    <h3>Catégories existantes</h3>  
    <ul id="listeCategories"></ul>  
  </div>  
</div>  
  
<div id="entreprises" style="display:none;">  
  <h1 style="text-align:center;margin-bottom:20px;">Entreprises & Recruteurs</h1>  
  <div class="card">  
    <h3>🚀 Pourquoi recruter avec EMPLOI INFO ?</h3>  
    <ul>  
      <li>Diffusion rapide de vos annonces</li>  
      <li>Tri intelligent par IA</li>  
      <li>Mise en avant des annonces urgentes</li>  
      <li>Statistiques de performance</li>  
      <li>Compatible mobile / APK</li>  
      <li>Modération & sécurité</li>  
    </ul>  
  </div>  
</div>  
  
<div id="parametres" style="display:none;">  
  <h1>Paramètres du site</h1>  
  <div class="card">  
    <h3>Changer mot de passe admin</h3>  
    <input type="password" id="oldPwd" placeholder="Mot de passe actuel">  
    <input type="password" id="newPwd" placeholder="Nouveau mot de passe">  
    <button onclick="changerMotDePasse()">Valider</button>  
  </div>  
  <div class="card">  
    <h3>Informations du site</h3>  
    <input type="text" id="siteName" placeholder="Nom du site">  
    <input type="text" id="siteEmail" placeholder="Email contact">  
    <button onclick="sauvegarderParametres()">Sauvegarder</button>  
  </div>  
</div>  
  
<h3>➕ Ajouter un administrateur</h3>  
<input type="password" id="adminPass" placeholder="Mot de passe" autocomplete="new-password">  
<select id="adminRole">  
  <option value="admin">Administrateur</option>  
  <option value="super" id="roleSuperOption">Super administrateur</option>  
</select>  
<button id="addAdminBtn">Ajouter</button>  
  
</div>  

<script>
/* =====================================================
   VARIABLES GLOBALES
===================================================== */
const currentAdminRole = "<?php echo $adminRole; ?>"; // admin | super
let myChart = null;

/* =====================================================
   OUTILS
===================================================== */
function safe(id){
  return document.getElementById(id);
}

function showLoader(){
  let l = safe("loader");
  if(!l){
    l = document.createElement("div");
    l.id = "loader";
    l.style = `
      position:fixed;top:0;left:0;width:100%;height:100%;
      background:rgba(0,0,0,.75);
      display:flex;align-items:center;justify-content:center;
      z-index:9999;color:gold;font-size:20px;font-weight:bold;
    `;
    l.innerText = "Chargement...";
    document.body.appendChild(l);
  }
  l.style.display = "flex";
}
function hideLoader(){
  safe("loader") && (safe("loader").style.display="none");
}

function requireRole(roles){
  if(!roles.includes(currentAdminRole)){
    alert("⛔ Action non autorisée pour votre rôle");
    return false;
  }
  return true;
}

/* =====================================================
   AUTH
===================================================== */
async function login(){
  const pwd = safe("password").value.trim();
  const error = safe("error");
  if(!pwd){ error.innerText="Mot de passe requis"; return; }

  showLoader();
  try{
    const res = await fetch("login_admin.php",{
      method:"POST",
      headers:{ "Content-Type":"application/json" },
      body:JSON.stringify({ password: pwd })
    });
    const data = await res.json();
    if(data.status==="ok"){
      location.reload();
    }else{
      error.innerText = data.msg || "Accès refusé";
    }
  }catch(e){
    error.innerText="Erreur serveur";
  }finally{
    hideLoader();
  }
}

function logout(){
  showLoader();
  fetch("logout.php").finally(()=>location.reload());
}

/* =====================================================
   NAVIGATION
===================================================== */
function showPage(page){
  ["dashboard","annonces","categories","entreprises","parametres"]
    .forEach(p=> safe(p) && (safe(p).style.display="none"));

  safe(page) && (safe(page).style.display="block");

  if(page==="dashboard") chargerStats();
  if(page==="annonces"){
    chargerCategories();
    chargerAnnonces();
    updateApercu();
  }
  if(page==="categories") chargerCategories();
}

/* =====================================================
   FORMAT TEXTE
===================================================== */
function mettreGras(){
  const t = safe("texte");
  const s = t.selectionStart, e = t.selectionEnd;
  if(s===e) return;
  t.value = t.value.slice(0,s)+"<b>"+t.value.slice(s,e)+"</b>"+t.value.slice(e);
  updateApercu();
}

function mettreItalic(){
  const t = safe("texte");
  const s = t.selectionStart, e = t.selectionEnd;
  if(s===e) return;
  t.value = t.value.slice(0,s)+"<i>"+t.value.slice(s,e)+"</i>"+t.value.slice(e);
  updateApercu();
}

/* =====================================================
   APERÇU
===================================================== */
function getBoutons(){
  let b=[];
  for(let i=1;i<=3;i++){
    const t=safe(`btn${i}_text`).value;
    const c=safe(`btn${i}_color`).value;
    const l=safe(`btn${i}_link`).value;
    if(t) b.push({texte:t,couleur:c,lien:l});
  }
  return b;
}

function updateApercu(){
  const box = safe("apercuAnnonce");
  if(!box) return;

  const imgFile = safe("banniere").files[0];
  const img = imgFile ? `<img src="${URL.createObjectURL(imgFile)}" style="max-width:100%">` : "";

  const btns = getBoutons().map(b=>
    `<a class="action-btn" style="background:${b.couleur};color:white" href="${b.lien}" target="_blank">${b.texte}</a>`
  ).join("");

  box.style.textAlign = safe("alignement").value;
  box.innerHTML = `
    ${img}
    <b>${safe("titre").value}</b>
    <p>${safe("texte").value}</p>
    ${safe("notice").value ? `<i>${safe("notice").value}</i>`:""}
    <div>${btns}</div>
  `;
}

/* =====================================================
   PUBLICATION
===================================================== */
function publier(){
  if(!requireRole(["admin","super"])) return;

  const fd = new FormData();
  fd.append("titre", safe("titre").value);
  fd.append("texte", safe("texte").value);
  fd.append("notice", safe("notice").value);
  fd.append("categorie", safe("categorie").value);
  fd.append("alignement", safe("alignement").value);
  fd.append("boutons", JSON.stringify(getBoutons()));
  safe("banniere").files[0] && fd.append("banniere", safe("banniere").files[0]);

  showLoader();
  fetch("save.php",{method:"POST",body:fd})
    .then(r=>r.json())
    .then(()=>{
      document.querySelectorAll("input,textarea").forEach(e=>e.value="");
      updateApercu();
      chargerAnnonces();
      chargerStats();
    })
    .finally(hideLoader);
}

/* =====================================================
   ANNONCES
===================================================== */
function chargerAnnonces(){
  showLoader();
  fetch("data.json").then(r=>r.json()).then(data=>{
    let pub="", att="";
    data.forEach(a=>{
      const html=`
      <div class="card">
        <b>${a.titre}</b>
        <p>${a.texte}</p>
        ${!a.publie?`<button class="validate" onclick="valider('${a.id}')">Valider</button>`:""}
        <button class="delete" onclick="supprimer('${a.id}')">Supprimer</button>
      </div>`;
      a.publie ? pub=html+pub : att=html+att;
    });
    safe("listePubliées").innerHTML=pub;
    safe("listeAttente").innerHTML=att;
  }).finally(hideLoader);
}

function valider(id){
  if(!requireRole(["admin","super"])) return;
  fetch("update_publish.php",{
    method:"POST",
    headers:{"Content-Type":"application/json"},
    body:JSON.stringify({id,publie:true})
  }).then(()=>{chargerAnnonces();chargerStats();});
}

function supprimer(id){
  if(!requireRole(["super"])) return;
  fetch("update_publish.php",{
    method:"POST",
    headers:{"Content-Type":"application/json"},
    body:JSON.stringify({id,supprimer:true})
  }).then(()=>{chargerAnnonces();chargerStats();});
}

/* =====================================================
   CATEGORIES
===================================================== */
function chargerCategories(){
  fetch("categories.json").then(r=>r.json()).then(data=>{
    safe("categorie").innerHTML="";
    safe("listeCategories").innerHTML="";
    data.forEach((c,i)=>{
      safe("categorie").innerHTML+=`<option value="${c}">${c}</option>`;
      safe("listeCategories").innerHTML+=`<li>${c} <button onclick="supprimerCategorie(${i})">Supprimer</button></li>`;
    });
  });
}

function ajouterCategorie(){
  if(!requireRole(["admin","super"])) return;
  fetch("categories.json").then(r=>r.json()).then(data=>{
    data.push(safe("newCat").value);
    fetch("save_categories.php",{
      method:"POST",
      headers:{"Content-Type":"application/json"},
      body:JSON.stringify(data)
    }).then(chargerCategories);
  });
}

function supprimerCategorie(i){
  if(!requireRole(["super"])) return;
  fetch("categories.json").then(r=>r.json()).then(data=>{
    data.splice(i,1);
    fetch("save_categories.php",{
      method:"POST",
      headers:{"Content-Type":"application/json"},
      body:JSON.stringify(data)
    }).then(chargerCategories);
  });
}

/* =====================================================
   STATS + GRAPHE
===================================================== */
function chargerStats(){
  fetch("data.json").then(r=>r.json()).then(a=>{
    safe("totalAnnonces").innerText = a.length;
    safe("publieesCount").innerText = a.filter(x=>x.publie).length;
    safe("attenteCount").innerText = a.filter(x=>!x.publie).length;
  });

  fetch("stats.json").then(r=>r.json()).then(d=>{
    const vues = d.vues_7_jours;
    const labels = Object.keys(vues);
    const values = Object.values(vues);

    const ctx = safe("statsCanvas").getContext("2d");
    if(myChart) myChart.destroy();

    myChart = new Chart(ctx,{
      type:"line",
      data:{
        labels:labels,
        datasets:[{
          label:"Visites (7 jours)",
          data:values,
          borderColor:"gold",
          backgroundColor:"rgba(255,215,0,.2)",
          tension:.3,
          fill:true
        }]
      },
      options:{responsive:true,scales:{y:{beginAtZero:true}}}
    });
  });
}

/* =====================================================
   INIT
===================================================== */
document.addEventListener("DOMContentLoaded",()=>{
  if(<?php echo $isLoggedJS ? 'true':'false'; ?>){
    safe("adminPanel").style.display="block";
    showPage("dashboard");

    if(currentAdminRole!=="super"){
      safe("addAdminBtn") && (safe("addAdminBtn").disabled=true);
      safe("adminRole") && (safe("adminRole").disabled=true);
    }
  }else{
    safe("loginBox").style.display="block";
  }
});
</script>
<script>
document.addEventListener("DOMContentLoaded", () => {
  // Ajouter un administrateur
  const addBtn = document.getElementById("addAdminBtn");
  if(!addBtn) return;

  addBtn.addEventListener("click", () => {
    if(!requireRole(["super"])) return; // Seul super peut ajouter

    const password = safe("adminPass").value.trim();
    const role = safe("adminRole").value;

    if(!password) { alert("Mot de passe requis"); return; }

    showLoader();
    fetch("add_admin.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ password, role })
    })
    .then(r => r.json())
    .then(res => {
      alert(res.msg);
      safe("adminPass").value = "";
    })
    .finally(hideLoader);
  });
});
    </script>
    <script>
window.OptimusBotConfig = {
  botId: "8fe59be3-8fc5-481a-8529-7b3eb912eef0",
  title: "Assistante EMPLOI INFO",
  color: "#FFC107"
};
</script>
   <!-- ZONE ABONNEMENT -->
<div id="newsflow-widget-e05a6a7a-b259-4c4c-8467-88385eaadaf7"></div>

<script>
(function(){
  var w = document.getElementById('newsflow-widget-e05a6a7a-b259-4c4c-8467-88385eaadaf7');
  var iframe = document.createElement('iframe');
  iframe.src = 'https://smart-subscribers.lovable.app/embed/e05a6a7a-b259-4c4c-8467-88385eaadaf7';
  iframe.style.border = 'none';
  iframe.style.width = '100%';
  iframe.style.minHeight = '200px';
  w.appendChild(iframe);
})();
</script>

<script>
function envoyerNotification(titre, message, lien) {
  fetch("https://fkjxptemfpfpireliszu.supabase.co/functions/v1/webhook-notify", {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify({
      user_id: "d4f5ba1b-49f8-4077-88db-eabd6707cbc4",
      title: titre,
      message: message,
      url: lien,
      tags: ["auto"]
    })
  })
  .then(res => res.json())
  .then(data => {
    console.log("Notification envoyée :", data);
  })
  .catch(error => {
    console.error("Erreur :", error);
  });
}
</script>


<button onclick="envoyerNotification(
  '🔥 Promo spéciale',
  'Profite de -50% maintenant !',
  'https://emploi-info.page.gd/admin.php'
)">
  Envoyer une notification
</button>
<script>
// Notification automatique après 10 secondes
setTimeout(function() {
  envoyerNotification(
    "👋 Bienvenue !",
    "Merci de visiter notre site",
    "https://ton-site.com"
  );
}, 10000);
</script>
</body>  
    </html>