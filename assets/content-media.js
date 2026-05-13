(function(){
  function escapeHtml(value){
    return String(value || "").replace(/[&<>"']/g, function(char){
      return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[char];
    });
  }

  function normalizeUrl(url){
    const value = String(url || "").trim();
    if(!value) return "";
    if(/^(https?:|mailto:|tel:|whatsapp:|#|\/|\.)/i.test(value)) return value;
    return "https://" + value;
  }

  function youtubeId(url){
    const value = String(url || "");
    const match = value.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{6,})/);
    return match ? match[1] : "";
  }

  function fileType(path, explicit){
    if(explicit) return explicit;
    const ext = String(path || "").split("?")[0].split(".").pop().toLowerCase();
    if(["jpg","jpeg","png","gif","webp","bmp","svg"].includes(ext)) return "image";
    if(["mp4","webm","ogg","mov","m4v"].includes(ext)) return "video";
    if(ext === "pdf") return "pdf";
    if(["doc","docx"].includes(ext)) return "doc";
    if(ext === "txt") return "txt";
    if(ext === "apk") return "apk";
    return ext ? "file" : "";
  }

  function renderFile(item){
    const src = item.fichier || item.file || item.img || item.image || "";
    const type = fileType(src, item.type);
    if(!src) return "";
    const safeSrc = escapeHtml(src);
    const name = escapeHtml(item.file_name || src.split("/").pop() || "Fichier");

    if(type === "image") return `<img src="${safeSrc}" alt="${escapeHtml(item.titre || "")}">`;
    if(type === "video") return `<video controls style="width:100%;max-height:320px;object-fit:cover;border-radius:8px;background:#000"><source src="${safeSrc}"></video>`;
    if(type === "pdf") return `<iframe src="${safeSrc}" style="width:100%;height:420px;border:0;border-radius:8px;background:#fff"></iframe><a class="btn" href="${safeSrc}" target="_blank" rel="noopener">Télécharger le PDF</a>`;
    if(type === "txt") return `<iframe src="${safeSrc}" style="width:100%;height:260px;border:0;border-radius:8px;background:#fff"></iframe><a class="btn" href="${safeSrc}" target="_blank" rel="noopener">Télécharger le TXT</a>`;
    if(type === "apk") return `<a class="btn" href="${safeSrc}" target="_blank" rel="noopener" download>Télécharger l'APK : ${name}</a>`;
    if(type === "doc") return `<a class="btn" href="${safeSrc}" target="_blank" rel="noopener" download>Télécharger le document : ${name}</a>`;
    return `<a class="btn" href="${safeSrc}" target="_blank" rel="noopener" download>Télécharger ${name}</a>`;
  }

  function renderExternal(url){
    const safeUrl = normalizeUrl(url);
    const id = youtubeId(safeUrl);
    if(id){
      return `<iframe style="width:100%;aspect-ratio:16/9;border:0;border-radius:8px;background:#000" src="https://www.youtube.com/embed/${id}" allowfullscreen loading="lazy"></iframe>`;
    }
    if(safeUrl && /^https?:\/\//i.test(safeUrl)){
      return `<a class="btn" href="${escapeHtml(safeUrl)}" target="_blank" rel="noopener">Ouvrir le lien externe</a>`;
    }
    return "";
  }

  window.ContentMedia = {escapeHtml, normalizeUrl, youtubeId, fileType, renderFile, renderExternal};
})();
