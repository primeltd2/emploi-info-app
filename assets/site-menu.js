(function(){
  function ensureLink(rel, href, attrs){
    let link = document.querySelector(`link[rel="${rel}"][href="${href}"]`);
    if(!link){
      link = document.createElement("link");
      link.rel = rel;
      link.href = href;
      document.head.appendChild(link);
    }
    Object.entries(attrs || {}).forEach(([key, value]) => link.setAttribute(key, value));
  }

  ensureLink("icon", "/favicon.ico", {type:"image/x-icon", sizes:"any"});
  ensureLink("shortcut icon", "/favicon.ico", {type:"image/x-icon"});
  ensureLink("apple-touch-icon", "/pwa-icon-192.png", {sizes:"192x192"});
  ensureLink("manifest", "/manifest.json");
  if(!document.querySelector('meta[name="theme-color"]')){
    const theme = document.createElement("meta");
    theme.name = "theme-color";
    theme.content = "#c9a227";
    document.head.appendChild(theme);
  }

  if(document.getElementById("siteMenuButton")) return;

  window.EmploiInfoAppLink = window.EmploiInfoAppLink || {
    wrap(url){
      const target = new URL(url || location.href, location.origin);
      return "/open-app.html?url=" + encodeURIComponent(target.href);
    }
  };

  const style = document.createElement("style");
  style.textContent = `
    body>header{background:linear-gradient(90deg,#c9a227,#ffd84a,#c9a227)!important;color:#111!important;border-bottom:2px solid #111!important}
    body>header h1,body>header h2,body>header strong,body>header span,body>header p,body>header a{color:#111!important;text-shadow:none!important}
    .site-menu-button{position:fixed;left:14px;top:14px;z-index:100000;width:46px;height:42px;border:1px solid rgba(255,215,0,.45);border-radius:8px;background:#111;color:gold;display:flex;flex-direction:column;gap:5px;align-items:center;justify-content:center;box-shadow:0 8px 20px rgba(0,0,0,.35);cursor:pointer}
    .site-menu-button span{width:24px;height:3px;background:gold;border-radius:3px;display:block}
    .site-menu-panel{position:fixed;left:14px;top:64px;z-index:100000;width:min(280px,calc(100vw - 28px));background:#111;border:1px solid rgba(255,215,0,.35);border-radius:8px;box-shadow:0 18px 45px rgba(0,0,0,.5);padding:10px;display:none}
    .site-menu-panel.open{display:block}
    .site-menu-panel a{display:block;color:#fff;text-decoration:none;padding:12px 14px;border-radius:6px;font-weight:700;border-bottom:1px solid rgba(255,255,255,.06)}
    .site-menu-panel a:hover{background:gold;color:#111}
    .pwa-install-button{position:fixed;right:14px;bottom:14px;z-index:100000;border:0;border-radius:8px;background:linear-gradient(180deg,#ffdf7a,#d8a414);color:#111;font-weight:900;padding:12px 16px;box-shadow:0 12px 28px rgba(0,0,0,.38);cursor:pointer;display:none}
    .pwa-install-button.show{display:block}
    .pwa-install-button:disabled{opacity:.7;cursor:not-allowed}
    .site-install-link{background:rgba(255,215,0,.1);color:gold!important}
    .install-card{position:fixed;right:14px;bottom:124px;z-index:100000;width:min(360px,calc(100vw - 28px));background:#111;color:#fff;border:1px solid rgba(255,215,0,.42);border-radius:8px;box-shadow:0 18px 45px rgba(0,0,0,.5);padding:14px;display:none}
    .install-card.show{display:block}
    .install-card strong{display:block;color:gold;font-size:16px;margin-bottom:6px}
    .install-card p{margin:0 0 12px;line-height:1.35;font-size:14px}
    .install-card .install-legal{font-size:12px;color:#cfcfcf}
    .install-card .install-legal a{display:inline;padding:0;color:#ffdf7a!important;text-decoration:underline;border:0;font-weight:800}
    .install-card-actions{display:flex;gap:8px;align-items:center}
    .install-card button,.install-card a{border:0;border-radius:6px;padding:10px 12px;font-weight:900;text-decoration:none;cursor:pointer}
    .install-card .install-now{background:linear-gradient(180deg,#ffdf7a,#d8a414);color:#111}
    .install-card .install-later{background:rgba(255,255,255,.08);color:#fff}
    .terms-update-bg{position:fixed;inset:0;z-index:100002;background:rgba(0,0,0,.76);display:none}
    .terms-update-card{position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);z-index:100003;width:min(520px,calc(100vw - 28px));background:#111;color:#fff;border:1px solid rgba(255,215,0,.42);border-radius:8px;box-shadow:0 18px 45px rgba(0,0,0,.55);padding:18px;display:none}
    .terms-update-card strong{display:block;color:gold;font-size:18px;margin-bottom:8px}
    .terms-update-card p{margin:0 0 12px;line-height:1.45}
    .terms-update-card a{display:inline;padding:0;color:#ffdf7a!important;text-decoration:underline;border:0;font-weight:800}
    .terms-update-card button{width:100%;border:0;border-radius:6px;padding:12px 14px;font-weight:900;background:linear-gradient(180deg,#ffdf7a,#d8a414);color:#111;cursor:pointer}
    .terms-update-bg.show,.terms-update-card.show{display:block}
    .push-enable-button{position:fixed;right:14px;bottom:68px;z-index:100000;border:0;border-radius:8px;background:#111;color:gold;border:1px solid rgba(255,215,0,.45);font-weight:900;padding:12px 16px;box-shadow:0 12px 28px rgba(0,0,0,.38);cursor:pointer;display:none}
    .push-enable-button.show{display:block}
    .push-enable-button:disabled{opacity:.7;cursor:not-allowed}
    @media(max-width:620px){.pwa-install-button,.push-enable-button,.install-card{left:14px;right:14px;width:auto}.pwa-install-button{bottom:12px}.push-enable-button{bottom:64px}.install-card{bottom:116px}}
  `;
  document.head.appendChild(style);

  const button = document.createElement("button");
  button.id = "siteMenuButton";
  button.className = "site-menu-button";
  button.type = "button";
  button.setAttribute("aria-label", "Ouvrir le menu");
  button.innerHTML = "<span></span><span></span><span></span>";

  const panel = document.createElement("div");
  panel.id = "siteMenuPanel";
  panel.className = "site-menu-panel";
  panel.innerHTML = `
    <a href="index.html">Accueil</a>
    <a href="#" id="siteMenuPublish">Transmettre une offre</a>
    <a href="contact.html">Contact</a>
    <a href="blog.html">Blog</a>
    <a href="services.html">Services</a>
    <a href="ressources.html">Ressources</a>
    <a href="formations.html">Formations</a>
    <a href="statistiques.html">Statistiques</a>
    <a href="actualites.html">Actualités</a>
    <a href="publicites.html">Publicités</a>
    <a href="#" id="siteMenuInstall" class="site-install-link">Installer l'application EMPLOI INFO</a>
    <a href="comment-ca-marche.html">Comment ça marche</a>
    <a href="faq.html">FAQ</a>
    <a href="devenir-partenaire.html">Devenir partenaire</a>
  `;

  button.addEventListener("click", function(){
    panel.classList.toggle("open");
  });

  document.addEventListener("click", function(event){
    if(event.target === button || button.contains(event.target) || panel.contains(event.target)) return;
    panel.classList.remove("open");
  });

  panel.querySelector("#siteMenuPublish").addEventListener("click", function(event){
    event.preventDefault();
    panel.classList.remove("open");
    const openButton = document.getElementById("openFormBtn");
    if(openButton){
      openButton.click();
      return;
    }
    window.location.href = "index.html?publish=1";
  });

  document.body.appendChild(button);
  document.body.appendChild(panel);

  let deferredInstallPrompt = null;
  const APK_URL = "/download_app.php";
  const installButton = document.createElement("button");
  installButton.id = "pwaInstallButton";
  installButton.className = "pwa-install-button";
  installButton.type = "button";
  installButton.textContent = "Installer l'application";
  document.body.appendChild(installButton);

  const pushButton = document.createElement("button");
  pushButton.id = "pushEnableButton";
  pushButton.className = "push-enable-button";
  pushButton.type = "button";
  pushButton.textContent = "Activer les notifications";
  document.body.appendChild(pushButton);

  const installCard = document.createElement("div");
  installCard.id = "installReminderCard";
  installCard.className = "install-card";
  installCard.innerHTML = `
    <strong>Installer EMPLOI INFO</strong>
    <p>Restez connecté et ne manquez plus les offres, stages, formations et nouvelles annonces.</p>
    <p class="install-legal">En utilisant EMPLOI INFO, vous acceptez nos <a href="/conditions-utilisation.html" target="_blank" rel="noopener">conditions d'utilisation</a> et notre <a href="/politique-confidentialite.html" target="_blank" rel="noopener">politique de confidentialité</a>.</p>
    <div class="install-card-actions">
      <button type="button" class="install-now">Installer</button>
      <button type="button" class="install-later">Plus tard</button>
    </div>
  `;
  document.body.appendChild(installCard);

  const VAPID_PUBLIC_KEY = "BKT-eMQPLO-yz12kmMXHm9zIHKJxgXVOSWIsBU_LqIM_DclBekRomP22cGlM-ORZWHzyqqSVIDYzsuf4Jij6-WQ";
  const isAndroidNativeApp = navigator.userAgent.includes("EmploiInfoAndroid");
  const userAgent = navigator.userAgent || "";
  const isIOSBrowser = /iPad|iPhone|iPod/.test(userAgent) || (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);
  const isAndroidBrowser = /Android/i.test(userAgent);

  async function forceClientRefreshIfNeeded(){
    const url = "/app_version.json?force_ts=" + Date.now();
    try{
      const res = await fetch(url, {cache:"no-store"});
      if(!res.ok) return;
      const version = await res.json();
      const resetVersion = String(version.force_cache_reset_version || "");
      if(!resetVersion) return;
      const key = "emploiInfoCacheResetVersion";
      if(localStorage.getItem(key) === resetVersion) return;

      localStorage.setItem(key, resetVersion);
      localStorage.removeItem("emploiInfoAppGateDone");
      localStorage.removeItem("emploiInfoPwaInstalled");
      localStorage.removeItem("emploiInfoTermsAccepted");
      localStorage.removeItem("emploiInfoTermsVersion");

      if("caches" in window){
        const names = await caches.keys();
        await Promise.all(names.map(name => caches.delete(name)));
      }

      if("serviceWorker" in navigator){
        const registrations = await navigator.serviceWorker.getRegistrations();
        await Promise.all(registrations.map(reg => reg.unregister()));
      }

      const refreshed = new URL(location.href);
      refreshed.searchParams.set("refresh", resetVersion);
      location.replace(refreshed.href);
    }catch(e){
      console.warn("Vidange cache reportee:", e);
    }
  }

  forceClientRefreshIfNeeded();

  async function showUpdatedTermsIfNeeded(){
    try{
      const res = await fetch("/app_version.json?terms_ts=" + Date.now(), {cache:"no-store"});
      if(!res.ok) return;
      const version = await res.json();
      const termsVersion = String(version.terms_version || "");
      if(!termsVersion || localStorage.getItem("emploiInfoTermsVersion") === termsVersion) return;
      const path = location.pathname.replace(/\/+$/, "") || "/";
      if(path === "/conditions-utilisation" || path === "/conditions-utilisation.html" || path === "/politique-confidentialite" || path === "/politique-confidentialite.html") return;

      const bg = document.createElement("div");
      bg.className = "terms-update-bg show";
      const card = document.createElement("div");
      card.className = "terms-update-card show";
      card.innerHTML = `
        <strong>Nouvelles conditions EMPLOI INFO</strong>
        <p>Nous avons mis a jour les conditions d'utilisation et la politique de confidentialite. Pour continuer a utiliser le site ou l'application, veuillez les accepter.</p>
        <p><a href="/conditions-utilisation.html" target="_blank" rel="noopener">Lire les conditions</a> - <a href="/politique-confidentialite.html" target="_blank" rel="noopener">Lire la politique de confidentialite</a></p>
        <button type="button">J'accepte et je continue</button>
      `;
      document.body.appendChild(bg);
      document.body.appendChild(card);
      card.querySelector("button").addEventListener("click", function(){
        localStorage.setItem("emploiInfoTermsVersion", termsVersion);
        localStorage.setItem("emploiInfoTermsAccepted", "true");
        bg.remove();
        card.remove();
      });
    }catch(e){
      console.warn("Verification conditions reportee:", e);
    }
  }

  showUpdatedTermsIfNeeded();

  function isPwaInstalled(){
    return window.matchMedia("(display-mode: standalone)").matches ||
      window.navigator.standalone === true;
  }

  function redirectInstalledAppToPendingUrl(){
    if(!isAndroidNativeApp && !isPwaInstalled()) return false;
    const pendingUrl = localStorage.getItem("emploiInfoPendingUrl");
    if(!pendingUrl) return false;
    const target = new URL(pendingUrl, location.origin);
    const current = location.pathname + location.search + location.hash;
    const next = target.pathname + target.search + target.hash;
    localStorage.setItem("emploiInfoAppGateDone", "true");
    localStorage.removeItem("emploiInfoPendingUrl");
    if(current !== next){
      location.replace(target.href);
      return true;
    }
    return false;
  }

  if(redirectInstalledAppToPendingUrl()) return;

  function shouldShowAppGate(){
    if(isAndroidNativeApp || isPwaInstalled()) return false;
    if(isIOSBrowser || !isAndroidBrowser) return false;
    const path = location.pathname.replace(/\/+$/, "") || "/";
    const allowed = new Set([
      "/open-app",
      "/open-app.html",
      "/download_app",
      "/download_app.php",
      "/conditions-utilisation",
      "/conditions-utilisation.html",
      "/politique-confidentialite",
      "/politique-confidentialite.html",
      "/mentions-legales",
      "/mentions-legales.html",
      "/manifest.json",
      "/sw.js"
    ]);
    if(allowed.has(path)) return false;
    if(path.startsWith("/assets/") || path.startsWith("/uploads/") || path.startsWith("/images/") || path.startsWith("/favi/")) return false;
    if(/\.(png|jpg|jpeg|gif|webp|ico|css|js|json|mp4|mp3|pdf|apk)$/i.test(path)) return false;
    return true;
  }

  if(shouldShowAppGate()){
    localStorage.setItem("emploiInfoPendingUrl", location.href);
    location.replace("/open-app.html?url=" + encodeURIComponent(location.href));
    return;
  }

  function canInstallPwa(){
    return isAndroidBrowser && !isAndroidNativeApp && !isPwaInstalled() && !!deferredInstallPrompt;
  }

  function canPromoteInstall(){
    return isAndroidBrowser && !isAndroidNativeApp && !isPwaInstalled();
  }

  function updateInstallButton(){
    if(!canInstallPwa()){
      installButton.classList.remove("show");
      installButton.disabled = true;
      return;
    }
    installButton.disabled = false;
    installButton.classList.add("show");
  }

  window.addEventListener("beforeinstallprompt", function(event){
    event.preventDefault();
    deferredInstallPrompt = event;
    updateInstallButton();
  });

  async function installApp(){
    localStorage.setItem("emploiInfoPendingUrl", location.href);
    if(!deferredInstallPrompt){
      updateInstallButton();
      window.location.href = APK_URL;
      return;
    }
    installButton.disabled = true;
    deferredInstallPrompt.prompt();
    const choice = await deferredInstallPrompt.userChoice.catch(() => null);
    if(choice && choice.outcome === "accepted"){
      localStorage.setItem("emploiInfoPwaInstalled", "true");
    }else{
      updateInstallButton();
    }
    deferredInstallPrompt = null;
    installButton.disabled = false;
    updateInstallButton();
    hideInstallCard();
  }

  installButton.addEventListener("click", installApp);

  panel.querySelector("#siteMenuInstall").addEventListener("click", function(event){
    event.preventDefault();
    panel.classList.remove("open");
    installApp();
  });

  window.addEventListener("appinstalled", function(){
    localStorage.setItem("emploiInfoPwaInstalled", "true");
    deferredInstallPrompt = null;
    updateInstallButton();
    hideInstallCard();
  });

  updateInstallButton();

  function hideInstallCard(){
    installCard.classList.remove("show");
  }

  function showInstallCard(){
    if(!canPromoteInstall()) return;
    installCard.classList.add("show");
  }

  installCard.querySelector(".install-now").addEventListener("click", installApp);
  installCard.querySelector(".install-later").addEventListener("click", hideInstallCard);

  setInterval(showInstallCard, 120000);
  setTimeout(showInstallCard, 15000);

  function urlBase64ToUint8Array(base64String){
    const padding = "=".repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/");
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for(let i = 0; i < rawData.length; ++i){
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  }

  async function registerServiceWorker(){
    if(!("serviceWorker" in navigator)) return null;
    const registration = await navigator.serviceWorker.register("/sw.js", {scope: "/"});
    await navigator.serviceWorker.ready;
    return registration;
  }

  async function subscribeToPush(){
    if(!("Notification" in window) || !("PushManager" in window) || !("serviceWorker" in navigator)) return false;
    if(Notification.permission !== "granted") return false;

    const registration = await registerServiceWorker();
    if(!registration) return false;
    let subscription = await registration.pushManager.getSubscription();
    if(!subscription){
      subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
      });
    }

    await fetch("subscribe_push.php", {
      method: "POST",
      headers: {"Content-Type": "application/json"},
      body: JSON.stringify({subscription: subscription.toJSON()})
    });
    return true;
  }

  function updatePushButton(){
    if(isAndroidNativeApp || !("Notification" in window) || !("PushManager" in window)){
      pushButton.classList.remove("show");
      return;
    }
    pushButton.classList.toggle("show", Notification.permission !== "granted");
  }

  pushButton.addEventListener("click", async function(){
    pushButton.disabled = true;
    try{
      const permission = await Notification.requestPermission();
      if(permission === "granted"){
        await subscribeToPush();
      }
    }catch(e){
      console.error("Erreur notifications push:", e);
    }
    pushButton.disabled = false;
    updatePushButton();
  });

  registerServiceWorker()
    .then(() => subscribeToPush())
    .catch(e => console.error("Erreur service worker:", e))
    .finally(updatePushButton);

  if(!document.querySelector('script[src="/assets/user-features.js"],script[src="assets/user-features.js"]')){
    const userScript = document.createElement("script");
    userScript.src = "/assets/user-features.js";
    document.body.appendChild(userScript);
  }
})();
