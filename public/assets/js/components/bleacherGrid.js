//gestion du mouse hover et touch start et end
let __bleacherTooltipEl = null;
function ensureBleacherTooltip() {
    if (__bleacherTooltipEl) return __bleacherTooltipEl;
    const el = document.createElement('div');
    el.className = 'bleacher-tooltip';
    el.style.opacity = '0';
    el.style.transition = 'opacity .12s ease';
    el.setAttribute('role', 'status');
    el.setAttribute('aria-hidden', 'true');
    document.body.appendChild(el);
    __bleacherTooltipEl = el;
    return el;
}
function showBleacherTooltip(target, text, touchPoint = null) {
    if (!text) return;
    const el = ensureBleacherTooltip();
    el.textContent = text;
    el.setAttribute('aria-hidden', 'false');
    el.style.opacity = '1';

    // Position: prefer touch coordinates if fournis, sinon au-dessus du target centré
    const rect = target.getBoundingClientRect();
    let left, top;
    if (touchPoint) {
        left = Math.round(touchPoint.clientX);
        top = Math.round(touchPoint.clientY - 10);
    } else {
        left = Math.round(rect.left + rect.width / 2);
        top = Math.round(rect.top - 8);
    }
    // Apply with offset and keep on-screen
    const margin = 8;
    el.style.left = Math.min(window.innerWidth - margin, Math.max(margin, left)) + 'px';
    // calc top en utilisant la hauteur connue de l'élément si disponible
    const offsetH = el.offsetHeight || 32;
    el.style.top = Math.max(margin, top - offsetH) + 'px';
    el.style.transform = 'translate(-50%, -100%)';
    clearTimeout(el.__hideTimeout);
    // auto-hide after 2.5s on touch
    el.__hideTimeout = setTimeout(() => hideBleacherTooltip(), 2500);
}
function hideBleacherTooltip() {
    const el = __bleacherTooltipEl;
    if (!el) return;
    el.style.opacity = '0';
    el.setAttribute('aria-hidden', 'true');
    clearTimeout(el.__hideTimeout);
}

/**
 * Attache les événements pour afficher une infobulle sur une cellule de gradin.
 * @param {HTMLTableCellElement} td La cellule <td> qui recevra les événements.
 * @param {HTMLButtonElement} btn Le bouton à l'intérieur de la cellule.
 * @param {string} tooltipText Le texte à afficher.
 */
function attachTooltipEvents(td, btn, tooltipText) {
    if (!td || !btn || !tooltipText) return;

    // Assigner le texte pour l'accessibilité et comme fallback
    btn.title = tooltipText;
    td.dataset.tooltip = tooltipText;

    // Événements pour la souris
    td.addEventListener('mouseenter', () => showBleacherTooltip(btn, tooltipText));
    td.addEventListener('mouseleave', () => hideBleacherTooltip());

    // Événements pour le tactile
    td.addEventListener('touchstart', (ev) => {
        const touch = ev.touches && ev.touches[0];
        showBleacherTooltip(btn, tooltipText, touch);
    }, { passive: true });
    td.addEventListener('touchend', () => hideBleacherTooltip());
    td.addEventListener('touchcancel', () => hideBleacherTooltip());
}

/**
 * Applique les états dynamiques (réservé, en cours...) à une grille de gradin déjà construite.
 * @param {HTMLElement} container - Le conteneur de la grille (ex: l'élément avec `data-bleacher-seats`).
 * @param {Object} seatStates - Un objet où les clés sont les ID des sièges et les valeurs leur statut.
 */
export function applySeatStates(container, seatStates) {
    if (!container || !seatStates) {
        return;
    }

    // Map des statuts reçus de l'API vers les classes CSS de la légende
    const statusToClassMap = {
        'occupied': 'tdplacePris',
        'in_cart_other': 'tdplaceTemp',
        'in_cart_session': 'tdplaceTempSession',
        'vip': 'tdplaceVIP',
        'benevole': 'tdplaceBenevole',
        'closed': 'tdplaceClosed',
    };

    // On parcourt les états reçus
    for (const seatId in seatStates) {
        const status = seatStates[seatId];
        const cssClass = statusToClassMap[status];
        const seatButton = container.querySelector(`button[data-seat-id="${seatId}"]`);

        // Si on trouve le siège et qu'on a une classe correspondante
        if (seatButton && cssClass) {
            const td = seatButton.closest('td');
            if (td) {
                // On retire les classes de statut précédentes pour éviter les conflits
                Object.values(statusToClassMap).forEach(c => td.classList.remove(c));
                td.classList.remove('tdplaceVide'); // On retire aussi la classe par défaut
                
                // On ajoute la nouvelle classe
                td.classList.add(cssClass);
                // On désactive le bouton SAUF si c'est une place déjà dans la sélection de l'utilisateur
                seatButton.disabled = (status !== 'in_cart_session');
                seatButton.dataset.status = status; // On met à jour le statut sur le bouton

                // Déterminer le texte de l'infobulle et l'attacher
                let tooltipText = 'Non disponible';
                if (status === 'occupied') tooltipText = 'Déjà réservée';
                else if (status === 'in_cart_other') tooltipText = 'En cours de réservation';
                else if (status === 'in_cart_session') tooltipText = 'Dans votre sélection';
                else if (status === 'vip') tooltipText = 'Place VIP';
                else if (status === 'benevole') tooltipText = 'Réservée aux bénévoles';
                else if (status === 'closed') tooltipText = 'Fermée';

                attachTooltipEvents(td, seatButton, tooltipText);
            }
        }
    }
}

// Création de la grille
export function createBleacherGrid(container, plan, options = {}) {
    if (!container) {
        return null;
    }
    const mode = options.mode || 'readonly';
    const zone = plan.zone;
    const rows = Array.isArray(plan.rows) ? plan.rows : [];
    const cols = plan.cols || 0;

    // Injecte un style minimal pour la colonne mobile
    if (!document.getElementById('bleacher-mobile-style')) {
        const style = document.createElement('style');
        style.id = 'bleacher-mobile-style';
        style.textContent = `
            .mobile-row-label { width: 4.5rem; vertical-align: middle; padding: .25rem; text-align: center; }
            .mobile-row-label .mobile-row-content { font-size: .85rem; white-space: nowrap; line-height: 1; }
            .mobile-row-label .zone-name { font-weight: 700; display: block; }
            .mobile-row-label .row-rank { display: block; color: #6c757d; font-size: .85rem; }
            /* Masquer sur md+ (Bootstrap utils) */
            @media (min-width: 768px) { .mobile-row-label { display: none !important; } }
            /* Optionnel : meilleure tenue visuelle sur petit écran */
            .zone-plan td { vertical-align: middle; }
        `;
        document.head.appendChild(style);
    }

    // Nettoyage
    container.innerHTML = '';
    container.dataset.mode = mode;

    // Table
    const table = document.createElement('table');
    table.className = 'zone-plan table table-bordered table-sm';
    table.dataset.zoneName = zone.zoneName;

    const tbody = document.createElement('tbody');

    // Génération des lignes
    rows.forEach(row => {
        const tr = document.createElement('tr');
        tr.dataset.rowIndex = row.index;
        if (row.rank != null) {
            tr.dataset.rowRank = row.rank;
        }

        // Calcul du "rankNumber" utilisé pour l'affichage mobile et le code
        const rankNumber = (row.rank != null && row.rank !== '') ? row.rank : row.index;

        // Colonne mobile: visible uniquement sur petits écrans, affiche zone + rang
        const mobileLabelTd = document.createElement('td');
        mobileLabelTd.className = 'mobile-row-label d-md-none';
        mobileLabelTd.setAttribute('role', 'presentation');
        const mobileContent = document.createElement('div');
        mobileContent.className = 'mobile-row-content';
        const spanZone = document.createElement('span');
        spanZone.className = 'zone-name';
        spanZone.textContent = zone.zoneName + rankNumber + 'xx';
        mobileContent.appendChild(spanZone);
        mobileLabelTd.appendChild(mobileContent);
        tr.appendChild(mobileLabelTd);

        for (let i = 0; i < row.seats.length; i++) {
            const seatData = row.seats[i];
            const td = document.createElement('td');

            // Détermination des classes CSS basées sur état
            let tdClass;
            let status = 'available';

            if (!seatData.exists) {
                tdClass = 'tdplaceNone';
                status = 'empty';
            } else {
                if (!seatData.open) {
                    tdClass = 'tdplaceClosed';
                    status = 'closed';
                } else if (seatData.pmr) {
                    tdClass = 'tdplacePMR';
                    status = 'pmr';
                } else if (seatData.vip) {
                    tdClass = 'tdplaceVIP';
                    status = 'vip';
                } else if (seatData.volunteer) {
                    tdClass = 'tdplaceBenevole';
                    status = 'benevole';
                } else {
                    tdClass = 'tdplaceVide';
                }
            }

            td.className = tdClass;

            // Construction nom complet du siège à afficher
            const seatNumber = String(i + 1).padStart(2, '0');
            const seatCode = `${zone.zoneName}${rankNumber}${seatNumber}`;

            if (!seatData.exists) {
                // Ne PAS créer de bouton pour les places inexistantes.
                td.dataset.empty = '1';
                td.setAttribute('role', 'presentation');
                // placeholder pour garder gabarit visuel si besoin (inutile si CSS gère taille)
                const placeholder = document.createElement('span');
                placeholder.className = 'seat-placeholder';
                placeholder.innerHTML = '&nbsp;';
                td.appendChild(placeholder);
            } else {
                // Bouton (même en readonly pour uniformiser DOM)
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'seat btn p-0 w-100 h-100';
                btn.textContent = seatCode;

                // Data attrs
                btn.dataset.seatCode = seatCode;
                btn.dataset.zoneName = zone.zoneName;
                btn.dataset.rowIndex = row.index;
                if (row.rank != null) {
                    btn.dataset.rowRank = row.rank;
                }
                btn.dataset.number = seatNumber;
                btn.dataset.status = status;

                if (seatData.exists) {
                    btn.dataset.seatId = seatData.id;
                    btn.dataset.open = seatData.open ? '1' : '0';
                    if (seatData.pmr) {
                        btn.dataset.pmr = '1';
                    }
                    if (seatData.vip) {
                        btn.dataset.vip = '1';
                    }
                    if (seatData.volunteer) {
                        btn.dataset.volunteer = '1';
                    }
                } else {
                    btn.dataset.empty = '1';
                }

                // Désactivation si non cliquable
                const interactive = (mode !== 'readonly') && seatData.exists && (status === 'available' || status === 'pmr');
                if (!interactive) {
                    btn.disabled = true;

                    // Déterminer texte tooltip succinct
                    let tooltipText = 'Non disponible';
                    if (!seatData.exists) tooltipText = 'Aucune place';
                    else if (!seatData.open) tooltipText = 'Fermée';
                    else if (seatData.pmr) tooltipText = 'Réservée aux PMR';
                    else if (seatData.vip) tooltipText = 'Place VIP';
                    else if (seatData.volunteer) tooltipText = 'Réservée aux bénévoles';

                    attachTooltipEvents(td, btn, tooltipText);
                }

                // Gestion clic
                if (interactive && typeof options.onSeatClick === 'function') {
                    btn.addEventListener('click', () => options.onSeatClick({
                        seatId: seatData.id,
                        code: seatCode,
                        zone: zone.zoneName,
                        rowIndex: row.index,
                        rowRank: row.rank,
                        number: seatNumber,
                        status
                    }, btn));
                }

                td.appendChild(btn);
            }
            tr.appendChild(td);
        }
        tbody.appendChild(tr);
    });

    table.appendChild(tbody);
    container.appendChild(table);

    return {
        element: table,
        updateSeatStatus(seatCode, newStatusClass) {
            const btn = table.querySelector(`[data-seat-code="${seatCode}"]`);
            if (!btn) {
                return;
            }
            const td = btn.closest('td');
            if (!td) {
                return;
            }
            // Retire anciennes classes de statut
            td.classList.remove(
                'tdplaceVide',
                'tdplaceClosed',
                'tdplacePMR',
                'tdplaceVIP',
                'tdplaceBenevole',
                'tdplacePris',
                'tdplaceTemp',
                'tdplaceTempSession'
            );
            td.classList.add(newStatusClass);
            btn.dataset.status = newStatusClass.replace(/^tdplace/, '').toLowerCase();
        },
        rebuild(newPlan) {
            createBleacherGrid(container, newPlan, options);
        }
    };
}
