(function(){
  function getVisitor(){
    let visitor = localStorage.getItem("emploiInfoVisitorProfile");
    if(visitor){
      try { return JSON.parse(visitor); } catch(e){}
    }
    const suffix = Math.floor(1000 + Math.random() * 9000);
    visitor = {
      id: "visitor_" + Date.now() + "_" + Math.random().toString(16).slice(2),
      username: "Visiteur " + suffix
    };
    localStorage.setItem("emploiInfoVisitorProfile", JSON.stringify(visitor));
    return visitor;
  }

  function showBlockedScreen(data, visitor){
    document.body.innerHTML = `
      <div class="access-denied-screen">
        <div class="access-denied-card">
          <img src="logo.png" alt="EMPLOI INFO">
          <h1>${data.status === "refused" ? "Accès non autorisé" : "Accès refusé"}</h1>
          <p>${data.message || "Veuillez contacter l'administration."}</p>
          <button id="openAppealBtn">Contacter l'administration</button>
        </div>
        <div class="access-modal-bg" id="appealBg"></div>
        <div class="access-modal" id="appealModal">
          <h2>Demande d'examen</h2>
          <textarea id="appealText" placeholder="Expliquez votre demande à l'administration"></textarea>
          <button id="sendAppealBtn">Envoyer</button>
          <button id="closeAppealBtn">Fermer</button>
        </div>
      </div>
    `;
    const style = document.createElement("style");
    style.textContent = `
      body{margin:0;background:#111;color:white;font-family:Arial,sans-serif}
      .access-denied-screen{min-height:100vh;display:grid;place-items:center;background:linear-gradient(135deg,#000,#3a2c00,#c9a227);padding:18px}
      .access-denied-card,.access-modal{width:min(92vw,440px);background:rgba(0,0,0,.88);border:2px solid gold;border-radius:18px;padding:22px;text-align:center;box-shadow:0 18px 50px rgba(0,0,0,.45)}
      .access-denied-card img{width:74px;height:74px;border-radius:16px;object-fit:cover}
      .access-denied-card h1,.access-modal h2{color:gold}
      button{background:gold;color:#111;border:none;border-radius:10px;padding:12px 14px;font-weight:bold;margin:6px;cursor:pointer}
      .access-modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.72)}
      .access-modal{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%)}
      .access-modal textarea{width:100%;min-height:140px;border-radius:10px;padding:12px;margin-bottom:10px}
    `;
    document.head.appendChild(style);
    const bg = document.getElementById("appealBg");
    const modal = document.getElementById("appealModal");
    document.getElementById("openAppealBtn").onclick = () => { bg.style.display="block"; modal.style.display="block"; };
    document.getElementById("closeAppealBtn").onclick = () => { bg.style.display="none"; modal.style.display="none"; };
    document.getElementById("sendAppealBtn").onclick = async () => {
      const message = document.getElementById("appealText").value.trim();
      if(!message){ alert("Veuillez écrire votre demande."); return; }
      const res = await fetch("appeal_block.php", {
        method:"POST",
        headers:{"Content-Type":"application/json"},
        body:JSON.stringify({visitor_id:visitor.id, message})
      });
      const json = await res.json();
      alert(json.message || "Demande envoyée");
      bg.style.display="none";
      modal.style.display="none";
    };
  }

  const visitor = getVisitor();
  fetch(`access_control.php?visitor_id=${encodeURIComponent(visitor.id)}&ts=${Date.now()}`, {cache:"no-store"})
    .then(r=>r.json())
    .then(data=>{ if(data.blocked) showBlockedScreen(data, visitor); })
    .catch(()=>{});
})();
