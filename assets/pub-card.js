console.log("pub-card.js loaded");

(function () {
  const adsOverlay = document.getElementById("adsOverlay");
  const adsOverlayBg = document.getElementById("adsOverlayBg");
  const adsContainer = document.getElementById("adsOverlayContainer");

  console.log("Elements found:", !!adsOverlay, !!adsOverlayBg, !!adsContainer);

  let publications = [];
  let lastIndex = -1;

  function normalizeUrl(url) {
    if (!url || typeof url !== "string") return "";
    if (/^https?:\/\//i.test(url) || url.startsWith("data:") || url.startsWith("blob:")) {
      return url;
    }
    return "https://emploi-info.page.gd/" + url.replace(/^\/+/, "");
  }

  function isPublished(item) {
    const value = item && item.publie;
    return value === true || value === 1 || value === "1" || String(value).toLowerCase() === "true";
  }

  function isVideoFile(url) {
    return /\.(mp4|webm|ogg|mov|m4v)(\?.*)?$/i.test(url || "");
  }

  function detectMedia(ad) {
    const explicitVideo = normalizeUrl(ad.video || ad.videoUrl || "");
    const explicitImage = normalizeUrl(ad.image || ad.imageUrl || "");
    const fileMedia = normalizeUrl(ad.fichier || ad.media || ad.src || ad.banniere || "");

    if (explicitVideo) {
      return { type: "video", src: explicitVideo };
    }

    if (explicitImage) {
      return { type: "image", src: explicitImage };
    }

    if (fileMedia) {
      return {
        type: isVideoFile(fileMedia) || ad.type === "video" ? "video" : "image",
        src: fileMedia
      };
    }

    return { type: "", src: "" };
  }

  function normalizePublication(pub) {
    const media = detectMedia(pub);

    return {
      ...pub,
      publie: isPublished(pub),
      titre: pub.titre || "Publicité",
      description: pub.description || "",
      lien: pub.lien || "#",
      type: media.type,
      mediaSrc: media.src,
      image: media.type === "image" ? media.src : normalizeUrl(pub.image || pub.imageUrl || ""),
      video: media.type === "video" ? media.src : normalizeUrl(pub.video || pub.videoUrl || "")
    };
  }

  function syncPublications() {
    if (!Array.isArray(window.publications)) {
      return false;
    }

    publications = window.publications
      .map(normalizePublication)
      .filter(pub => pub.publie && pub.mediaSrc);

    window.publications = publications;
    return publications.length > 0;
  }

  function waitForPublications() {
    if (syncPublications()) {
      console.log("Publications ready:", publications);
      return;
    }

    console.log("Publications not ready, retrying...");
    setTimeout(waitForPublications, 150);
  }

  function getRandomPublication() {
    if (!publications.length) return null;

    let randomIndex;
    do {
      randomIndex = Math.floor(Math.random() * publications.length);
    } while (publications.length > 1 && randomIndex === lastIndex);

    lastIndex = randomIndex;
    return {
      pub: publications[randomIndex],
      index: randomIndex
    };
  }

  function buildMediaHTML(pub) {
    if (!pub || !pub.mediaSrc) {
      return "<p>Média indisponible</p>";
    }

    if (pub.type === "video") {
      return `
        <video
          class="pub-card-video"
          style="width:100%;max-height:320px;object-fit:cover;border-radius:8px;background:#000;"
          autoplay
          muted
          playsinline
          preload="auto"
          controls
        >
          <source src="${pub.mediaSrc}">
          Votre navigateur ne supporte pas la vidéo.
        </video>
      `;
    }

    return `
      <img
        src="${pub.mediaSrc}"
        alt="${pub.titre}"
        style="width:100%;max-height:320px;object-fit:cover;border-radius:8px;"
      >
    `;
  }

  function renderPreviewCard() {
    if (!adsContainer || !adsOverlay || !adsOverlayBg) return;
    if (!publications.length) return;

    const picked = getRandomPublication();
    if (!picked) return;

    const pub = picked.pub;
    const index = picked.index;

    const descPreview = pub.description && pub.description.length > 80
       pub.description.slice(0, 80) + "…"
      : pub.description || "";

    adsContainer.innerHTML = `
      <div class="ad-large" style="animation: zoomIn 0.5s ease-out;">
        <a href="${pub.lien}" target="_blank" rel="noopener">
          ${buildMediaHTML(pub)}
          <h3>${pub.titre}</h3>
          <p>${descPreview}</p>
        </a>
        <button class="btn" onclick="showAdDetails(${index})">En savoir plus</button>
      </div>
    `;
  }

  window.refreshPublicationsFromWindow = function () {
    syncPublications();
  };

  window.getRandomPublicationCard = function () {
    syncPublications();
    return getRandomPublication();
  };

  window.renderPublicationPreviewCard = function () {
    syncPublications();
    renderPreviewCard();
  };

  waitForPublications();
})();
