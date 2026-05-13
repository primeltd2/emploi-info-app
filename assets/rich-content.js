(function(global){
  const ALLOWED_TAGS = new Set([
    "A","ARTICLE","B","BLOCKQUOTE","BR","DIV","EM","H1","H2","H3","H4","H5","H6",
    "HR","I","LI","OL","P","SECTION","SMALL","SPAN","STRONG","U","UL"
  ]);
  const SAFE_STYLE_PROPS = new Set([
    "text-align","line-height","font-family","font-size","font-weight","font-style",
    "color","background","background-color","max-width","margin","margin-top",
    "margin-right","margin-bottom","margin-left","padding","padding-top",
    "padding-right","padding-bottom","padding-left","border-radius","display"
  ]);

  function escapeHtml(value){
    return String(value || "").replace(/[&<>"']/g, char => ({
      "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"
    }[char]));
  }

  function isSafeUrl(value){
    const url = String(value || "").trim();
    return /^(https?:|mailto:|tel:|#|\/|uploads\/)/i.test(url);
  }

  function cleanStyle(value){
    return String(value || "")
      .split(";")
      .map(part => part.trim())
      .filter(Boolean)
      .map(part => {
        const index = part.indexOf(":");
        if(index < 1) return "";
        const prop = part.slice(0, index).trim().toLowerCase();
        const val = part.slice(index + 1).trim();
        if(!SAFE_STYLE_PROPS.has(prop)) return "";
        if(/expression\s*\(|javascript:|url\s*\(/i.test(val)) return "";
        return `${prop}:${val}`;
      })
      .filter(Boolean)
      .join(";");
  }

  function sanitizeNode(node, doc){
    if(node.nodeType === Node.TEXT_NODE) return doc.createTextNode(node.textContent || "");
    if(node.nodeType !== Node.ELEMENT_NODE) return doc.createTextNode("");

    const tag = node.tagName.toUpperCase();
    if(["SCRIPT","STYLE","IFRAME","OBJECT","EMBED"].includes(tag)) return doc.createTextNode("");

    if(!ALLOWED_TAGS.has(tag)){
      const fragment = doc.createDocumentFragment();
      Array.from(node.childNodes).forEach(child => fragment.appendChild(sanitizeNode(child, doc)));
      return fragment;
    }

    const el = doc.createElement(tag.toLowerCase());
    if(tag === "A"){
      const href = node.getAttribute("href") || "";
      if(isSafeUrl(href)){
        el.setAttribute("href", href);
        el.setAttribute("target", node.getAttribute("target") || "_blank");
        el.setAttribute("rel", "noopener noreferrer");
      }
    }
    const style = cleanStyle(node.getAttribute("style") || "");
    if(style) el.setAttribute("style", style);
    Array.from(node.childNodes).forEach(child => el.appendChild(sanitizeNode(child, doc)));
    return el;
  }

  function renderRichContent(value){
    const raw = String(value || "").trim();
    if(!raw) return "";
    if(!/[<][a-z!/]/i.test(raw)){
      return escapeHtml(raw).replace(/\r/g, "").replace(/\n/g, "<br>");
    }
    const template = document.createElement("template");
    template.innerHTML = raw;
    const fragment = document.createDocumentFragment();
    Array.from(template.content.childNodes).forEach(node => fragment.appendChild(sanitizeNode(node, document)));
    const wrapper = document.createElement("div");
    wrapper.appendChild(fragment);
    return wrapper.innerHTML;
  }

  function richContentText(value){
    const raw = String(value || "");
    if(!/[<][a-z!/]/i.test(raw)){
      return raw.replace(/\s+/g, " ").trim();
    }
    const template = document.createElement("template");
    template.innerHTML = renderRichContent(raw);
    return (template.content.textContent || "").replace(/\s+/g, " ").trim();
  }

  global.RichContent = {render: renderRichContent, text: richContentText, escape: escapeHtml};
})(window);
