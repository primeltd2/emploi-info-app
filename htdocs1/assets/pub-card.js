document.addEventListener("DOMContentLoaded", () => {
    const pubCard = document.getElementById("pubCard");
    let publications = [];
    let currentIndex = 0;
    let timer;

    // ===== CHARGER LES PUBLICATIONS =====
    async function chargerPublications() {
        try {
            const res = await fetch("resources.json?ts=" + Date.now(), { cache: "no-store" });
            publications = await res.json();
            if (publications.length > 0) {
                showCard();
            }
        } catch (e) {
            console.error("Erreur chargement resources.json :", e);
            pubCard.innerHTML = `<p style="color:red;text-align:center;">Impossible de charger les publicités.</p>`;
        }
    }

    // ===== AFFICHER UNE CARTE =====
    function showCard() {
        const pub = publications[currentIndex];
        if (!pub) return;

        const textePreview = pub.texte.length > 130
            ? pub.texte.slice(0, 130) + "…"
            : pub.texte;

        pubCard.innerHTML = `
            <div class="pub-card-inner">
                <button class="close-btn" onclick="this.parentElement.parentElement.classList.remove('show')">✕</button>
                ${pub.banniere ? `<img src="${pub.banniere}" alt="${pub.titre}">` : ''}
                <h3>${pub.titre}</h3>
                <p>${textePreview}</p>
                <a href="ressources.html?id=${pub.id}" class="btn" style="background:gold;color:black;">
                    Voir détails
                </a>
            </div>
        `;

        pubCard.classList.add("show");

        // ===== 10 secondes d'affichage =====
        clearTimeout(timer);
        timer = setTimeout(() => {
            pubCard.classList.remove("show");

            // ===== 15 secondes avant la prochaine pub =====
            currentIndex = (currentIndex + 1) % publications.length;
            setTimeout(showCard, 5500); // 15s de pause
        }, 10000); // 10s d'affichage
    }

    chargerPublications();
});