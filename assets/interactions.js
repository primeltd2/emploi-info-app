(function(){
  const REACTIONS = ["👍", "❤️", "🎉", "🔥", "🙏", "💡"];
  const root = document.getElementById("communitySection");
  if(!root) return;

  const itemType = root.dataset.itemType || "annonce";
  const params = new URLSearchParams(location.search);
  const itemId = root.dataset.itemId || params.get("id") || "";
  let state = {reactions:{}, comments:[]};

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

  function escapeHtml(value){
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function formatDate(value){
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? "" : date.toLocaleString();
  }

  async function postInteraction(payload){
    const visitor = getVisitor();
    const res = await fetch("save_interaction.php", {
      method:"POST",
      headers:{"Content-Type":"application/json"},
      body:JSON.stringify({
        item_type:itemType,
        item_id:itemId,
        visitor_id:visitor.id,
        username:visitor.username,
        ...payload
      })
    });
    const data = await res.json();
    if(data.status !== "success") throw new Error(data.message || "Action impossible");
    if(data.message) alert(data.message);
    state = data.data;
    render();
  }

  async function sendPrivateMessage(kind, message, file){
    const visitor = getVisitor();
    const fd = new FormData();
    fd.append("kind", kind);
    fd.append("item_type", itemType);
    fd.append("item_id", itemId);
    fd.append("visitor_id", visitor.id);
    fd.append("username", visitor.username);
    fd.append("message", message);
    fd.append("page", location.href);
    if(file) fd.append("screenshot", file);
    const res = await fetch("report_problem.php", {method:"POST", body:fd});
    const data = await res.json();
    if(data.status !== "success") throw new Error(data.message || "Envoi impossible");
    alert(data.message || "Message envoyé aux administrateurs");
  }

  async function loadInteractions(){
    const visitor = getVisitor();
    const res = await fetch(`get_interactions.php?type=${encodeURIComponent(itemType)}&id=${encodeURIComponent(itemId)}&visitor_id=${encodeURIComponent(visitor.id)}&ts=${Date.now()}`, {cache:"no-store"});
    state = await res.json();
    render();
  }

  function renderReactions(){
    const visitor = getVisitor();
    const current = state.user_reaction || "";
    return `
      <div class="reaction-row">
        ${REACTIONS.map(r => `
          <button class="reaction-btn ${current === r ? "active" : ""}" data-reaction="${r}">
            <span>${r}</span> <span>${state.reactions?.[r] || 0}</span>
          </button>
        `).join("")}
      </div>
      <p class="community-note">Vous commentez avec le profil automatique : <strong>${escapeHtml(visitor.username)}</strong>.</p>
    `;
  }

  function renderComment(comment, replies){
    const tag = comment.tag && comment.tag.id
      ? `<a class="publication-tag" href="${comment.tag.type === "publicite" ? "publicite-details.html" : "details.html"}?id=${encodeURIComponent(comment.tag.id)}">#${escapeHtml(comment.tag.title || comment.tag.id)}</a>`
      : "";
    const media = comment.media
      ? (comment.media_type === "video"
        ? `<video class="comment-media" controls preload="metadata"><source src="${escapeHtml(comment.media)}"></video>`
        : `<img class="comment-media" src="${escapeHtml(comment.media)}" alt="Pièce jointe">`)
      : "";
    const link = comment.link ? `<a class="comment-link" href="${escapeHtml(comment.link)}" target="_blank" rel="noopener">${escapeHtml(comment.link)}</a>` : "";
    return `
      <div class="comment-item" data-comment-id="${escapeHtml(comment.id)}">
        <div class="comment-meta">
          <strong>@${escapeHtml(comment.username)}</strong>
          <span>${formatDate(comment.date)}</span>
        </div>
        <div class="comment-text">${comment.reply_to ? `<strong>@${escapeHtml(comment.reply_to)}</strong> ` : ""}${escapeHtml(comment.text)}</div>
        ${tag}
        ${link}
        ${media}
        <button class="reply-btn" data-reply="${escapeHtml(comment.id)}" data-user="${escapeHtml(comment.username)}">Répondre</button>
        <div class="comment-replies">
          ${replies.map(reply => renderComment(reply, [])).join("")}
        </div>
      </div>
    `;
  }

  function renderComments(){
    const comments = Array.isArray(state.comments) ? state.comments : [];
    const parents = comments.filter(c => !c.parent_id);
    const replies = comments.filter(c => c.parent_id);
    if(!comments.length) return `<div class="empty-comments">Aucun commentaire pour le moment.</div>`;
    return parents.map(comment => renderComment(comment, replies.filter(r => r.parent_id === comment.id))).join("");
  }

  function render(){
    root.innerHTML = `
      <h2>Avis, suggestions et réactions</h2>
      <p class="community-note">Partagez vos idées pour améliorer le site, signaler un besoin ou réagir à cette publication.</p>
      ${renderReactions()}
      <form class="comment-form" id="commentForm">
        <textarea id="commentText" placeholder="Votre avis ou suggestion..."></textarea>
        <button type="submit">Commenter</button>
      </form>
      <div class="private-actions">
        <button type="button" id="openReportBtn">Signaler un problème</button>
        <button type="button" id="openSuggestionBtn">Envoyer suggestion</button>
      </div>
      <div class="interaction-modal-bg" id="privateModalBg"></div>
      <div class="interaction-modal" id="privateModal">
        <h3 id="privateModalTitle">Message privé</h3>
        <textarea id="privateMessage" placeholder="Votre message à l'administration"></textarea>
        <input id="privateScreenshot" type="file" accept="image/*" title="Capture d'écran optionnelle">
        <div class="private-actions">
          <button type="button" id="sendPrivateBtn">Envoyer</button>
          <button type="button" id="closePrivateBtn">Fermer</button>
        </div>
      </div>
      <div class="comments-list">${renderComments()}</div>
    `;

    root.querySelectorAll(".reaction-btn").forEach(btn => {
      btn.onclick = () => postInteraction({action:"reaction", reaction:btn.dataset.reaction}).catch(e => alert(e.message));
    });

    root.querySelector("#commentForm").onsubmit = e => {
      e.preventDefault();
      const text = root.querySelector("#commentText").value.trim();
      if(!text){ alert("Veuillez écrire un commentaire."); return; }
      postInteraction({action:"comment", text}).catch(err => alert(err.message));
    };

    setupPrivateModal();

    root.querySelectorAll(".reply-btn").forEach(btn => {
      btn.onclick = () => showReplyForm(btn.dataset.reply, btn.dataset.user);
    });
  }

  function setupPrivateModal(){
    const modal = root.querySelector("#privateModal");
    const bg = root.querySelector("#privateModalBg");
    const title = root.querySelector("#privateModalTitle");
    let kind = "problem";
    const open = nextKind => {
      kind = nextKind;
      title.textContent = kind === "suggestion" ? "Suggestion pour améliorer la plateforme" : "Signaler un problème";
      bg.style.display = "block";
      modal.classList.add("show");
    };
    const close = () => {
      modal.classList.remove("show");
      bg.style.display = "none";
    };
    root.querySelector("#openReportBtn").onclick = () => open("problem");
    root.querySelector("#openSuggestionBtn").onclick = () => open("suggestion");
    root.querySelector("#closePrivateBtn").onclick = close;
    bg.onclick = close;
    root.querySelector("#sendPrivateBtn").onclick = () => {
      const text = root.querySelector("#privateMessage").value.trim();
      const file = root.querySelector("#privateScreenshot").files[0];
      if(!text){ alert("Veuillez écrire votre message."); return; }
      sendPrivateMessage(kind, text, file).then(()=>{
        root.querySelector("#privateMessage").value = "";
        root.querySelector("#privateScreenshot").value = "";
        close();
      }).catch(err => alert(err.message));
    };
  }

  function showReplyForm(parentId, username){
    const item = root.querySelector(`[data-comment-id="${CSS.escape(parentId)}"]`);
    if(!item) return;
    item.querySelector(".reply-form")?.remove();
    const form = document.createElement("form");
    form.className = "reply-form";
    form.innerHTML = `
      <textarea placeholder="Répondre à @${escapeHtml(username)}...">@${escapeHtml(username)} </textarea>
      <button type="submit">Répondre</button>
    `;
    form.onsubmit = e => {
      e.preventDefault();
      const text = form.querySelector("textarea").value.trim();
      if(!text){ alert("Veuillez écrire une réponse."); return; }
      postInteraction({action:"comment", text, parent_id:parentId, reply_to:username}).catch(err => alert(err.message));
    };
    item.appendChild(form);
    form.querySelector("textarea").focus();
  }

  loadInteractions().catch(() => {
    root.innerHTML = `<h2>Avis, suggestions et réactions</h2><p class="empty-comments">Impossible de charger les commentaires.</p>`;
  });
})();
