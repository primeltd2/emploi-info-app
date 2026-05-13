<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin | EMPLOI INFO</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;}
body{background:linear-gradient(135deg,#111,#222,#c9a227);color:white;min-height:100vh;display:flex;flex-direction:column;}
input,textarea,select,button{width:100%;padding:12px;margin-bottom:15px;border-radius:10px;border:1px solid #555;background:#333;color:white;font-size:14px;}
button{background:gold;color:black;font-weight:bold;cursor:pointer;transition:0.3s;border:none;}
button:hover{transform:scale(1.05);box-shadow:0 4px 10px rgba(0,0,0,0.3);}
button.delete{background:#e74c3c;color:white;}
button.validate{background:#27ae60;color:white;}
.card{background:rgba(0,0,0,0.8);padding:20px;border-radius:15px;margin-bottom:25px;border-left:5px solid gold;box-shadow:0 8px 20px rgba(0,0,0,0.5);position:relative;}
.action-btn{margin-right:8px;padding:8px 15px;border:none;border-radius:8px;font-weight:bold;cursor:pointer;font-size:12px;}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:25px;}
header{display:flex;justify-content:space-between;align-items:center;background:rgba(0,0,0,0.9);padding:15px 25px;border-radius:15px;margin-bottom:25px;box-shadow:0 4px 15px rgba(0,0,0,0.4);}
nav{display:flex;gap:15px;}
nav a{color:#fff;text-decoration:none;font-weight:600;padding:10px 15px;border-radius:8px;transition:0.3s;}
nav a:hover{background:gold;color:black;}
.section-title{margin:40px 0 15px 0;font-size:22px;color:#FFD700;display:flex;align-items:center;gap:10px;}
.section-title::before{content:'📊';}
ul{list-style:none;padding:0;}
li{background:rgba(255,215,0,0.1);padding:12px;border-radius:10px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;border:1px solid #555;}
#loginBox{max-width:400px;margin:150px auto;background:rgba(0,0,0,0.9);padding:30px;border-radius:20px;border:2px solid gold;box-shadow:0 10px 30px rgba(0,0,0,0.6);}
#loginBox h2{text-align:center;color:gold;margin-bottom:25px;font-size:24px;}
#loginBox input,#loginBox button{margin-bottom:15px;}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;}
.stat-card{background:#333;padding:20px;border-radius:10px;text-align:center;border:1px solid #555;}
.stat-card h3{color:gold;margin-bottom:10px;}
.stat-card p{font-size:24px;font-weight:bold;}
@media(max-width:768px){nav{flex-direction:column;gap:10px;} .cards{grid-template-columns:1fr;}}
</style>
</head>  
<body>  

<div id="loginBox">
  <h2>🔐 ADMIN EMPLOI INFO</h2>
  <input type="password" id="password" placeholder="Mot de passe admin">
  <button onclick="login()">Connexion</button>
  <p id="error"></p>
</div>
  
<div id="usernameModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:10000;align-items:center;justify-content:center;">
  <div style="background:#333;padding:20px;border-radius:10px;max-width:400px;">
    <h3>Définir votre nom d'utilisateur</h3>
    <input type="text" id="newUsername" placeholder="Nom d'utilisateur">
    <button onclick="setUsername()">Enregistrer</button>
  </div>
</div>  
  
<div id="adminPanel" style="display:none;padding:20px;">  
  
<header>  
  <strong>EMPLOI INFO – Admin</strong>  
  <div style="display:flex;gap:10px;align-items:center;">
    <span id="usernameDisplay" style="color:gold;font-size:14px;">Utilisateur: <span id="currentUsername">Non défini</span></span>
    <nav>
      <a onclick="showPage('dashboard')">Dashboard</a>
      <a onclick="showPage('annonces')">Créer annonce</a>
      <a onclick="showPage('categories')">Catégories</a>
      <a onclick="showPage('entreprises')">Entreprises</a>
      <a onclick="showPage('parametres')">Paramètres</a>
      <span id="publicitesLink" style="display:none;"><a onclick="showPage('publicites')">Publicités</a></span>
      <a onclick="logout()">Déconnexion</a>
    </nav>
  </div>
</header>  
  
<div id="contenuPrincipal">  
  
<div id="dashboard" style="display:none;">  
  <h1 style="text-align:center;margin-bottom:30px;">📊 Dashboard | EMPLOI INFO</h1>
  <div class="stats-grid">
    <div class="stat-card"><h3>Total annonces</h3><p id="totalAnnonces">0</p></div>
    <div class="stat-card"><h3>Annonces publiées</h3><p id="publieesCount">0</p></div>
    <div class="stat-card"><h3>Annonces en attente</h3><p id="attenteCount">0</p></div>
    <div class="stat-card"><h3>Annonces urgentes</h3><p id="urgentAnnonces">0</p></div>
    <div class="stat-card"><h3>Vues totales</h3><p id="totalVues">0</p></div>
    <div class="stat-card"><h3>Clics boutons</h3><p id="totalClics">0</p></div>
  </div>
  <h3 class="section-title">� Répartition par catégories</h3>
  <canvas id="pieChart" width="400" height="200"></canvas>
  <h3 class="section-title">🔥 Top annonces populaires</h3>
  <div id="topAnnonces" class="card"></div>
  
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
    <label><input type="checkbox" id="urgent" onchange="updateApercu()"> Urgent</label>
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
  <div class="card">  
    <h3>➕ Ajouter un administrateur</h3>  
    <input type="password" id="adminPass" placeholder="Mot de passe" autocomplete="new-password">  
    <select id="adminRole">  
      <option value="admin">Administrateur</option>  
      <option value="super" id="roleSuperOption">Super administrateur</option>  
    </select>  
    <button id="addAdminBtn">Ajouter</button>  
  </div>  
  <div class="card">  
    <h3 class="section-title">👥 Administrateurs existants</h3>  
    <ul id="adminsList"></ul>  
  </div>  
</div>  

<div id="publicites" style="display:none;">  
  <h1 style="text-align:center;margin-bottom:20px;">Publicités</h1>  
  <div class="card">  
    <h3>Ajouter une publicité</h3>  
    <input type="text" id="adTitre" placeholder="Titre de la pub">  
    <input type="file" id="adImage" accept="image/*">  
    <input type="text" id="adLien" placeholder="Lien de la pub">  
    <textarea id="adDescription" rows="3" placeholder="Description"></textarea>  
    <button onclick="ajouterPub()">Ajouter</button>  
  </div>  
  <h3 class="section-title">Publicités publiées</h3>  
  <div id="listePubs"></div>  
  <h3 class="section-title">Demandes de publicité</h3>  
  <div id="listeDemandesPub"></div>  
</div>  
  
const currentAdminRole = localStorage.getItem("admin_role") || "";
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
    // Simulation de connexion avec mots de passe par défaut
    const defaults = {
      "1234": "super",
      "53535353": "super",
      "administrateurTONNY242": "admin"
    };

    if(defaults[pwd]){
      localStorage.setItem("admin_logged", "true");
      localStorage.setItem("admin_role", defaults[pwd]);
      
      // Vérifier si nom d'utilisateur défini
      if(!localStorage.getItem("admin_username") || localStorage.getItem("admin_username") === "Non défini"){
        safe("usernameModal").style.display = "flex";
      } else {
        location.reload();
      }
    } else {
      error.innerText = "Mot de passe incorrect";
    }
  }catch(e){
    error.innerText="Erreur";
  }finally{
    hideLoader();
  }
}

function logout(){
  localStorage.removeItem("admin_logged");
  localStorage.removeItem("admin_role");
  location.reload();
}

/* =====================================================
   NAVIGATION
===================================================== */
function showPage(page){
  ["dashboard","annonces","categories","entreprises","parametres","publicites"]
    .forEach(p=> safe(p) && (safe(p).style.display="none"));

  safe(page) && (safe(page).style.display="block");

  if(page==="dashboard") chargerStats();
  if(page==="annonces"){
    chargerCategories();
    chargerAnnonces();
    updateApercu();
  }
  if(page==="parametres") chargerParametres();
  if(page==="publicites") chargerPubs();
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
  fd.append("urgent", safe("urgent").checked ? "1" : "0");
  fd.append("boutons", JSON.stringify(getBoutons()));
  safe("banniere").files[0] && fd.append("banniere", safe("banniere").files[0]);

  showLoader();
  fetch("save.php",{method:"POST",body:fd})
    .then(r=>r.json())
    .then(()=>{
      document.querySelectorAll("input,textarea").forEach(e=>e.value="");
      safe("urgent").checked = false;
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
  fetch("data.json").then(r=>{
    if(!r.ok) throw new Error("Erreur "+r.status);
    return r.json();
  }).then(data=>{
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
  }).catch(e=>{
    console.error("Erreur chargerAnnonces:", e);
    safe("listePubliées").innerHTML="<p style='color:red;'>Erreur chargement annonces</p>";
    safe("listeAttente").innerHTML="<p style='color:red;'>Erreur chargement annonces</p>";
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
  fetch("categories.json").then(r=>{
    if(!r.ok) throw new Error("Erreur "+r.status);
    return r.json();
  }).then(data=>{
    safe("categorie").innerHTML="";
    safe("listeCategories").innerHTML="";
    data.forEach((c,i)=>{
      safe("categorie").innerHTML+=`<option value="${c}">${c}</option>`;
      safe("listeCategories").innerHTML+=`<li>${c} <button onclick="supprimerCategorie(${i})">Supprimer</button></li>`;
    });
  }).catch(e=>{
    console.error("Erreur chargerCategories:", e);
    safe("listeCategories").innerHTML="<p style='color:red;'>Erreur chargement catégories</p>";
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
   PUBLICITES
===================================================== */
function chargerPubs(){
  fetch("ads.json").then(r=>r.json()).then(data=>{
    safe("listePubs").innerHTML = data.filter(ad=>ad.publie).map((ad,i)=>`
      <div class="card">
        <h4>${ad.titre}</h4>
        <img src="${ad.image}" style="max-width:200px;">
        <p>${ad.description}</p>
        <a href="${ad.lien}" target="_blank">Lien</a>
        <button class="delete" onclick="supprimerPub(${i})">Supprimer</button>
      </div>
    `).join("");
  });

  // Demandes de pub (on peut créer un fichier demandes_ads.json)
  fetch("demandes_ads.json").then(r=>r.json()).then(data=>{
    safe("listeDemandesPub").innerHTML = data.map((d,i)=>`
      <div class="card">
        <h4>${d.nom} - ${d.produit} ${d.urgent == '1' ? '<span style="color:red;font-weight:bold;">[URGENT]</span>' : ''}</h4>
        <p>${d.message}</p>
        <p>Contact: ${d.email} | ${d.tel}</p>
        <button onclick="validerDemandePub(${i})">Valider</button>
        <button class="delete" onclick="rejeterDemandePub(${i})">Rejeter</button>
      </div>
    `).join("");
  }).catch(()=> safe("listeDemandesPub").innerHTML = "<p>Aucune demande</p>");
}

function ajouterPub(){
  if(!requireRole(["admin","super"])) return;
  const titre = safe("adTitre").value;
  const image = safe("adImage").files[0];
  const lien = safe("adLien").value;
  const desc = safe("adDescription").value;
  if(!titre || !lien) return alert("Titre et lien requis");

  const formData = new FormData();
  formData.append("titre", titre);
  formData.append("lien", lien);
  formData.append("description", desc);
  if(image) formData.append("image", image);

  fetch("add_ad.php", {method:"POST", body:formData}).then(r=>r.json()).then(res=>{
    if(res.status==="ok") chargerPubs();
    else alert(res.msg);
  });
}

function supprimerPub(i){
  if(!requireRole(["super"])) return;
  fetch("ads.json").then(r=>r.json()).then(data=>{
    data.splice(i,1);
    fetch("save_ads.php", {method:"POST", headers:{"Content-Type":"application/json"}, body:JSON.stringify(data)}).then(chargerPubs);
  });
}

function validerDemandePub(i){
  if(!requireRole(["super"])) return;
  // Charger demandes
  fetch("demandes_ads.json").then(r=>r.json()).then(demandes=>{
    const demande = demandes[i];
    if(!demande) return;
    // Créer la pub
    const nouvellePub = {
      titre: demande.produit,
      image: "", // À définir manuellement
      lien: "", // À définir
      description: demande.message,
      publie: true
    };
    // Charger pubs existantes
    fetch("ads.json").then(r=>r.json()).then(pubs=>{
      pubs.push(nouvellePub);
      // Sauvegarder pubs
      fetch("save_ads.php", {method:"POST", headers:{"Content-Type":"application/json"}, body:JSON.stringify(pubs)}).then(()=>{
        // Supprimer la demande
        demandes.splice(i,1);
        fetch("save_demandes_ads.php", {method:"POST", headers:{"Content-Type":"application/json"}, body:JSON.stringify(demandes)}).then(chargerPubs);
      });
    });
  });
}

function rejeterDemandePub(i){
  // Supprimer de demandes_ads.json
  fetch("demandes_ads.json").then(r=>r.json()).then(data=>{
    data.splice(i,1);
    fetch("save_demandes_ads.php", {method:"POST", headers:{"Content-Type":"application/json"}, body:JSON.stringify(data)}).then(chargerPubs);
  });
}

/* =====================================================
   STATS + GRAPHE
===================================================== */
function chargerStats(){
  fetch("data.json").then(r=>{
    if(!r.ok) throw new Error("Erreur "+r.status);
    return r.json();
  }).then(a=>{
    safe("totalAnnonces").innerText = a.length;
    safe("publieesCount").innerText = a.filter(x=>x.publie).length;
    safe("attenteCount").innerText = a.filter(x=>!x.publie).length;
    safe("urgentAnnonces").innerText = a.filter(x=>x.publie && x.notice).length;

    // Top annonces
    const top = a.filter(x=>x.publie).sort((b,c)=> (c.vues||0) - (b.vues||0)).slice(0,5);
    safe("topAnnonces").innerHTML = top.map(x=>`<p>${x.titre} - ${x.vues||0} vues</p>`).join("");
  }).catch(e=>console.error("Erreur chargerStats (data.json):", e));

  fetch("stats.json").then(r=>{
    if(!r.ok) throw new Error("Erreur "+r.status);
    return r.json();
  }).then(d=>{
    safe("totalVues").innerText = Object.values(d.vues_7_jours||{}).reduce((s,v)=>s+v,0);
    safe("totalClics").innerText = Object.values(d.clics_boutons||{}).reduce((s,v)=>s+Object.values(v||{}).reduce((t,c)=>t+c,0),0);

    const vues = d.vues_7_jours||{};
    const labels = Object.keys(vues);
    const values = Object.values(vues);

    const ctx = safe("statsCanvas");
    if(!ctx) return console.log("Canvas statsCanvas non trouvé");
    const ctxObj = ctx.getContext("2d");
    if(window.myChart) window.myChart.destroy();

    window.myChart = new Chart(ctxObj,{
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
  }).catch(e=>console.error("Erreur chargerStats (stats.json):", e));

    // Pie chart pour catégories
    fetch("categories.json").then(r=>r.json()).then(cats=>{
      const catCount = {};
      a.forEach(ann=>{ if(ann.publie) catCount[ann.categorie] = (catCount[ann.categorie]||0)+1; });
      const pieCtx = safe("pieChart").getContext("2d");
      new Chart(pieCtx,{
        type:"pie",
        data:{
          labels: Object.keys(catCount),
          datasets:[{
            data: Object.values(catCount),
            backgroundColor: ['#FFD700','#FF6B6B','#4ECDC4','#45B7D1','#96CEB4','#FECA57']
          }]
        },
        options:{responsive:true}
      });
    });
  });
}

/* =====================================================
   ADMINS
===================================================== */
function chargerParametres(){
  fetch("params.json").then(r=>r.json()).then(data=>{
    safe("siteName").value = data.nom || "";
    safe("siteEmail").value = data.email || "";
  }).catch(e=>console.error("Erreur chargement params:", e));
  chargerAdmins();
}

function sauvegarderParametres(){
  const nom = safe("siteName").value.trim();
  const email = safe("siteEmail").value.trim();
  if(!nom || !email){ alert("Nom et email requis"); return; }
  fetch("save_params.php", {
    method:"POST",
    headers:{"Content-Type":"application/json"},
    body:JSON.stringify({nom: nom, email: email})
  }).then(r=>r.json()).then(res=>{
    alert(res.message || "Sauvegardé");
  }).catch(e=>alert("Erreur"));
}

function changerMotDePasse(){
  const oldPwd = safe("oldPwd").value.trim();
  const newPwd = safe("newPwd").value.trim();
  if(!oldPwd || !newPwd){ alert("Ancien et nouveau mot de passe requis"); return; }
  fetch("change_pwd.php", {
    method:"POST",
    headers:{"Content-Type":"application/json"},
    body:JSON.stringify({old: oldPwd, new: newPwd})
  }).then(r=>r.json()).then(res=>{
    alert(res.message);
  }).catch(e=>alert("Erreur"));
}

function setUsername(){
  const username = document.getElementById("newUsername").value.trim();
  if(!username) return alert("Nom d'utilisateur requis");
  localStorage.setItem("admin_username", username);
  document.getElementById("usernameModal").style.display="none";
  location.reload();
}

function supprimerAdmin(i){
  if(!requireRole(["super"])) return;
  fetch("admins.json").then(r=>r.json()).then(data=>{
    data.splice(i,1);
    fetch("save_admins.php",{
      method:"POST",
      headers:{"Content-Type":"application/json"},
      body:JSON.stringify(data)
    }).then(chargerAdmins);
  });
}

/* =====================================================
   INIT
===================================================== */
document.addEventListener("DOMContentLoaded",()=>{
  if(localStorage.getItem("admin_logged") === "true"){
    safe("adminPanel").style.display="block";
    chargerCategories();
    chargerAnnonces();
    chargerStats();
    showPage("dashboard");

    if(currentAdminRole==="super"){
      safe("publicitesLink").style.display="inline";
    }

    if(currentAdminRole!=="super"){
      safe("addAdminBtn") && (safe("addAdminBtn").disabled=true);
      safe("adminRole") && (safe("adminRole").disabled=true);
    }

    // Afficher le nom d'utilisateur
    const username = localStorage.getItem("admin_username") || "Non défini";
    safe("currentUsername").textContent = username;

    // Vérifier si username défini, sinon afficher modal
    if(!localStorage.getItem("admin_username") || localStorage.getItem("admin_username") === "Non défini"){
      safe("usernameModal").style.display = "flex";
    }
  }else{
    safe("loginBox").style.display="block";
  }
});

safe("addAdminBtn").onclick = ajouterAdmin;

// Mise à jour automatique des stats toutes les 30 secondes
setInterval(chargerStats, 30000);
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
    <script src="https://emploi-info.lovable.app/widget.js"></script>

<!-- AVANT LA FERMETURE DU BODY -->

<!-- NEWSLETTER + NOTIFICATION + COOKIE INTELLIGENT -->

<style>
@keyframes pulse {
  0% { transform: scale(1); }
  50% { transform: scale(1.1); }
  100% { transform: scale(1); }
}
</style>

<script>
/* ========= FONCTION NOTIFICATION ========= */
function envoyerNotification(titre, message, lien) {
  fetch("https://fkjxptemfpfpireliszu.supabase.co/functions/v1/webhook-notify", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      user_id: "c0f29526-8008-4d45-9bd7-45578f38a325",
      title: titre,
      message: message,
      url: lien,
      tags: ["emploi"]
    })
  });
}
</script>

<script>
/* ========= COOKIE ========= */
function setCookie(name, value, days) {
  var expires = "";
  if (days) {
    var date = new Date();
    date.setTime(date.getTime() + (days*24*60*60*1000));
    expires = "; expires=" + date.toUTCString();
  }
  document.cookie = name + "=" + value + "; path=/" + expires;
}

function getCookie(name) {
  var nameEQ = name + "=";
  var ca = document.cookie.split(';');
  for(var i=0;i < ca.length;i++) {
    var c = ca[i];
    while (c.charAt(0)==' ') c = c.substring(1,c.length);
    if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
  }
  return null;
}
</script>

<script>
/* ========= WIDGET NEWSLETTER INTELLIGENT ========= */
(function(){

  // ❗ Si déjà vu → ne pas afficher
  if(getCookie("newsletter_seen") === "yes"){
    return;
  }

  var cfg={
    id:"f3a92e3b-529e-4031-bd0e-9525e9778ce6",
    url:"https://fkjxptemfpfpireliszu.supabase.co",
    key:"eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImZranhwdGVtZnBmcGlyZWxpc3p1Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzU0MTI5ODksImV4cCI6MjA5MDk4ODk4OX0.BsVVkbH7C_ZkA0BQ3M0VKadoSN2AUZRXqOfiZFvc324",
    h:"🔥 Ne rate aucune offre d'emploi",
    d:"Inscris-toi et reçois les opportunités en premier !",
    b:"S'inscrire",
    s:"Merci pour votre inscription !",
    pc:"#f6b604",
    bg:"#fbe4e4",
    tc:"#1e293b",
    r:20
  };

  var o=document.createElement('div');
  o.id='nf-'+cfg.id;

  o.innerHTML='<div style="position:fixed;bottom:20px;left:20px;z-index:99999;font-family:Segoe UI,Roboto,sans-serif">' +

  '<div style="margin-bottom:8px;background:#f6b604;color:black;padding:8px 12px;border-radius:10px;font-size:12px;font-weight:bold;box-shadow:0 4px 15px rgba(0,0,0,.2);animation:pulse 1.5s infinite;">📢 Abonne-toi ici</div>' +

  '<div id="nf-btn-'+cfg.id+'" style="width:56px;height:56px;border-radius:50%;background:'+cfg.pc+';cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 20px rgba(0,0,0,.3)">' +
  '<svg width="24" height="24" fill="none" stroke="white" stroke-width="2"><path d="M4 4h16v12H5.17L4 17.17V4z"/></svg>' +
  '</div>' +

  '<div id="nf-form-'+cfg.id+'" style="display:none;position:absolute;bottom:80px;left:0;width:340px;background:'+cfg.bg+';border-radius:'+cfg.r+'px;padding:24px;box-shadow:0 10px 40px rgba(0,0,0,.2)">' +

  '<div style="text-align:right;cursor:pointer;font-weight:bold;" id="nf-close-'+cfg.id+'">✖</div>' +

  '<h3 style="margin:0 0 4px;font-size:16px;font-weight:700;color:'+cfg.tc+'">'+cfg.h+'</h3>' +
  '<p style="margin:0 0 16px;font-size:13px;opacity:.8;color:'+cfg.tc+'">'+cfg.d+'</p>' +

  '<input id="nf-name-'+cfg.id+'" placeholder="Nom (optionnel)" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:'+(cfg.r/2)+'px;margin-bottom:8px;font-size:14px;box-sizing:border-box">' +
  '<input id="nf-email-'+cfg.id+'" type="email" placeholder="Email" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:'+(cfg.r/2)+'px;margin-bottom:8px;font-size:14px;box-sizing:border-box">' +

  '<button id="nf-sub-'+cfg.id+'" style="width:100%;padding:12px;background:'+cfg.pc+';color:#fff;border:none;border-radius:'+(cfg.r/2)+'px;font-size:14px;font-weight:600;cursor:pointer">'+cfg.b+'</button>' +

  '<p id="nf-msg-'+cfg.id+'" style="display:none;text-align:center;margin:12px 0 0;font-size:14px;color:'+cfg.tc+'"></p>' +
  '</div></div>';

  document.body.appendChild(o);

  var btn=document.getElementById('nf-btn-'+cfg.id);
  var form=document.getElementById('nf-form-'+cfg.id);
  var close=document.getElementById('nf-close-'+cfg.id);

  // clic bouton
  btn.onclick=function(){
    form.style.display=form.style.display==='none'?'block':'none';
  };

  // fermeture manuelle
  close.onclick=function(){
    form.style.display='none';
    setCookie("newsletter_seen","yes",7); // cacher 7 jours
  };

  // ouverture automatique (si pas vu)
  setTimeout(function(){
    form.style.display='block';
  },5000);

  // soumission
  document.getElementById('nf-sub-'+cfg.id).onclick=function(){
    var email=document.getElementById('nf-email-'+cfg.id).value;
    var name=document.getElementById('nf-name-'+cfg.id).value;

    if(!email) return;

    this.disabled=true;
    this.textContent='...';

    fetch(cfg.url+'/functions/v1/widget-subscribe',{
      method:'POST',
      headers:{
        'Content-Type':'application/json',
        'apikey':cfg.key
      },
      body:JSON.stringify({
        widget_id:cfg.id,
        email:email,
        name:name||null
      })
    })
    .then(function(r){return r.json()})
    .then(function(d){
      var msg=document.getElementById('nf-msg-'+cfg.id);
      if(d.success){
        msg.textContent='Merci pour votre inscription !';
        msg.style.display='block';
        msg.style.color='#10b981';

        // cacher après inscription
        setCookie("newsletter_seen","yes",30);
      }else{
        msg.textContent=d.error||'Erreur';
        msg.style.display='block';
        msg.style.color='#ef4444';
      }
    })
    .catch(function(){
      var msg=document.getElementById('nf-msg-'+cfg.id);
      msg.textContent='Erreur';
      msg.style.display='block';
    });
  };

})();
</script>
<!-- NewsFlow Widget -->
<div id="newsflow-widget-86c9e1a5-753e-47e6-a793-1163d4cecffa"></div>
<script>
(function(){
  var w=document.getElementById('newsflow-widget-86c9e1a5-753e-47e6-a793-1163d4cecffa');
  if(w){
    var iframe=document.createElement('iframe');
    iframe.src='https://smart-subscribers.lovable.app/embed/86c9e1a5-753e-47e6-a793-1163d4cecffa';
    iframe.style.border='none';
    iframe.style.width='100%';
    iframe.style.minHeight='200px';
    w.appendChild(iframe);
  }
})();
</script>

</body>  
    </html>