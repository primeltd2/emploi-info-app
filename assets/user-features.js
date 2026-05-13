(function(){
  if(window.UserFeaturesLoaded) return;
  window.UserFeaturesLoaded = true;

  let csrf = "";
  function escapeHtml(value){
    return String(value || "").replace(/[&<>"']/g, function(char){
      return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[char];
    });
  }

  async function loadSession(){
    try{
      const res = await fetch("user_api.php?action=me&ts=" + Date.now(), {cache:"no-store"});
      const json = await res.json();
      csrf = json.csrf || csrf;
    }catch(e){}
  }

  function attachOfferActions(root){
    const scope = root || document;
    scope.querySelectorAll("[data-offer-id]:not([data-user-actions])").forEach(card => {
      card.dataset.userActions = "1";
      const id = card.dataset.offerId;
      const zone = document.createElement("div");
      zone.className = "user-offer-actions";
      zone.innerHTML = `
        <button type="button" class="share-copy-btn" data-copy-offer="${escapeHtml(id)}">Copier le lien</button>
      `;
      card.appendChild(zone);
    });
  }

  window.UserFeatures = {
    attachOfferActions
  };

  document.addEventListener("click", function(event){
    const copy = event.target.closest("[data-copy-offer]");
    if(copy){
      const url = `${location.origin}${location.pathname.replace(/[^/]*$/, "")}details.html?id=${encodeURIComponent(copy.dataset.copyOffer)}`;
      navigator.clipboard?.writeText(url).then(()=>alert("Lien copie"));
    }
  });

  function boot(){
    const link = document.createElement("link");
    link.rel = "stylesheet";
    link.href = "assets/user-features.css";
    document.head.appendChild(link);
    loadSession();
    if(location.pathname.endsWith("details.html")){
      const id = offerIdFromPage();
      if(id){
        const timer = setInterval(() => {
          const card = document.querySelector(".card");
          if(card && !card.dataset.offerId){
            card.dataset.offerId = id;
            attachOfferActions(card.parentNode || document);
            clearInterval(timer);
          }
        }, 500);
        setTimeout(()=>clearInterval(timer), 10000);
      }
    }
  }

  if(document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot);
  else boot();
})();
