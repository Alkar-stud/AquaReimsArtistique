//gestion du mouse hover et touch start et end
let __bleacherTooltipEl = null;
function ensureBleacherTooltip() {
    if (__bleacherTooltipEl) {
        return __bleacherTooltipEl;
    }
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
export function showBleacherTooltip(target, text, touchPoint = null) {
    if (!text) {
        return;
    }
    const el = ensureBleacherTooltip();
    el.innerHTML = text; // Utiliser innerHTML pour permettre le formatage HTML
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
        top = Math.round(rect.top + 50); // Rapproche l'infobulle de la case
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
    if (!el) {
        return;
    }
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
    if (!td || !btn || !tooltipText) {
        return;
    }

    // Assigner le texte pour l'accessibilité et comme fallback
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
 * @param {string} mode - 'reservation' (par défaut), 'occupation_plan', 'management', 'readonly'.
 */
export function applySeatStates(container, seatStates, mode = 'reservation') {
    if (!container || !seatStates) {
        return; // Ne rien faire si pas de conteneur ou d'états
    }

    // Map des statuts DYNAMIQUES reçus de l'API vers les classes CSS
    const statusToClassMap = {
        'occupied': 'tdplacePris',
        'in_cart_other': 'tdplaceTemp',
        'in_cart_session': 'tdplaceTempSession',
    };

    // --- Étape 1: Réinitialiser toutes les places ayant un statut dynamique ---
    // On cible toutes les cellules qui ont une des classes de statut dynamique.
    const dynamicClassesSelector = Object.values(statusToClassMap).map(c => `.${c}`).join(',');
    const seatsToReset = container.querySelectorAll(dynamicClassesSelector);

    seatsToReset.forEach(td => {
        // On retire toutes les classes de statut dynamique
        Object.values(statusToClassMap).forEach(c => td.classList.remove(c));

        // On réactive le bouton à l'intérieur s'il n'est pas déjà désactivé par un statut statique
        const btn = td.querySelector('button:disabled');
        if (btn && btn.dataset.status && statusToClassMap.hasOwnProperty(btn.dataset.status)) {
            btn.disabled = false;
            delete btn.dataset.status;
        }
    });

    // --- Étape 2: Appliquer les nouveaux états dynamiques ---
    for (const seatId in seatStates) {
        const seatInfo = seatStates[seatId];
        const status = typeof seatInfo === 'object' && seatInfo !== null ? seatInfo.status : seatInfo;
        const cssClass = statusToClassMap[status];
        const seatButton = container.querySelector(`button[data-seat-id="${seatId}"]`);

        if (seatButton && cssClass) {
            const td = seatButton.closest('td');
            if (td) {
                // On ajoute la classe de statut dynamique
                td.classList.add(cssClass);

                let tooltipText = 'Non disponible'; // Valeur par défaut

                // En mode 'plan d'occupation', on ne désactive pas les places occupées pour garder le clic.
                if (mode === 'occupation_plan' && status === 'occupied') {
                    seatButton.disabled = false;

                    // Si on a des infos détaillées, on construit l'infobulle directement.
                    if (seatInfo.reservationNumber) {
                        const seatCount = seatInfo.reservationSeatCount;
                        tooltipText = `
                            <strong>${seatInfo.reservationNumber}</strong><br>
                            ${seatInfo.reserverName}<br>
                            ${seatCount} place${seatCount > 1 ? 's' : ''}
                        `;
                    }
                } else {
                    // Comportement standard : on désactive le bouton (sauf pour la session en cours)
                    seatButton.disabled = (status !== 'in_cart_session');

                    // On définit le texte de l'infobulle pour les autres statuts
                    if (status === 'occupied') tooltipText = 'Déjà réservée';
                    if (status === 'in_cart_other') tooltipText = 'En cours de réservation';
                    if (status === 'in_cart_session') tooltipText = 'Dans votre sélection';
                }
                seatButton.dataset.status = status;

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
    const zone = plan.zone;
    const rows = Array.isArray(plan.rows) ? plan.rows : [];
    const cols = plan.cols || 0;
    const mode = options.mode || 'reservation'; // 'reservation', 'occupation_plan', 'management', 'readonly'

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
            } else if (mode === 'management') {
                td.dataset.seatId = seatData.id;
                const content = document.createElement('div');
                content.className = 'seat-management-content';

                const name = document.createElement('div');
                name.className = 'seat-name';
                name.textContent = seatCode;
                content.appendChild(name);

                const controls = document.createElement('div');
                controls.className = 'seat-controls';

                const createCheckbox = (label, property, checked) => {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'form-check';
                    const input = document.createElement('input');
                    input.type = 'checkbox';
                    input.className = 'form-check-input';
                    input.id = `seat-${seatData.id}-${property}`;
                    input.dataset.seatId = seatData.id;
                    input.dataset.property = property;
                    input.checked = checked;

                    const lbl = document.createElement('label');
                    lbl.className = 'form-check-label';
                    lbl.setAttribute('for', input.id);
                    lbl.textContent = label;

                    wrapper.appendChild(input);
                    wrapper.appendChild(lbl);

                    if (typeof options.onAttributeChange === 'function') {
                        input.addEventListener('change', (e) => {
                            options.onAttributeChange(seatData.id, property, e.target.checked);
                        });
                    }
                    return wrapper;
                };

                controls.appendChild(createCheckbox('Fermé', 'is_open', !seatData.open)); // Inversé : coché = fermé
                controls.appendChild(createCheckbox('PMR', 'is_pmr', seatData.pmr));
                controls.appendChild(createCheckbox('VIP', 'is_vip', seatData.vip));
                controls.appendChild(createCheckbox('Bénévole', 'is_volunteer', seatData.volunteer));
                content.appendChild(controls);
                td.appendChild(content);
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
                const interactive = seatData.exists && (status === 'available' || status === 'pmr');
                if (!interactive) {
                    btn.disabled = true;

                    // Déterminer texte tooltip succinct
                    let tooltipText = 'Non disponible';
                    if (!seatData.exists) {
                        tooltipText = 'Aucune place';
                    }
                    else {
                        if (!seatData.open) {
                            tooltipText = 'Fermée';
                        } else {
                            if (seatData.pmr) {
                                tooltipText = 'Réservée aux PMR';
                            } else {
                                if (seatData.vip) {
                                    tooltipText = 'Place VIP';
                                } else {
                                    if (seatData.volunteer) {
                                        tooltipText = 'Réservée aux bénévoles';
                                    }
                                }
                            }
                        }
                    }

                    attachTooltipEvents(td, btn, tooltipText);
                }

                // Gestion clic
                if (typeof options.onSeatClick === 'function') {
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
