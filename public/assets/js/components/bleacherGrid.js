// plan: réponse seatingPlanService.getZonePlan(zone)
// options: { mode: 'readonly' | 'reservation' | 'admin', onSeatClick(seatData, domButton) }
export function createBleacherGrid(container, plan, options = {}) {
    if (!container) {
        return null;
    }
    const mode = options.mode || 'readonly';
    const zone = plan.zone;
    const zoneName = zone.zoneName || zone.getZoneName || zone.getZoneName?.() || 'Z';
    const rows = Array.isArray(plan.rows) ? plan.rows : [];
    const cols = plan.cols || 0;

    // Nettoyage
    container.innerHTML = '';
    container.dataset.mode = mode;

    // Table
    const table = document.createElement('table');
    table.className = 'zone-plan table table-bordered table-sm';
    table.dataset.zoneName = zoneName;

    const tbody = document.createElement('tbody');

    // Génération des lignes
    rows.forEach(row => {
        const tr = document.createElement('tr');
        tr.dataset.rowIndex = row.index;
        if (row.rank != null) {
            tr.dataset.rowRank = row.rank;
        }

        for (let i = 0; i < row.seats.length; i++) {
            const seatData = row.seats[i];
            const td = document.createElement('td');

            // Détermination des classes CSS basées sur état
            let tdClass = 'tdplaceVide';
            let status = 'available';

            if (!seatData.exists) {
                tdClass = 'tdplaceVide';
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

            // TODO Placeholder pour futurs états
            // if (seatData.reserved) { tdClass = 'tdplacePris'; status='reserved'; }

            td.className = tdClass;

            // Construction nom complet du siège à afficher
            const rankNumber = (row.rank != null && row.rank !== '') ? row.rank : row.index;
            const seatNumber = String(i + 1).padStart(2, '0');
            const seatCode = `${zoneName}${rankNumber}${seatNumber}`;

            // Bouton (même en readonly pour uniformiser DOM)
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'seat btn p-0 w-100 h-100';
            btn.textContent = seatCode;

            // Data attrs
            btn.dataset.seatCode = seatCode;
            btn.dataset.zoneName = zoneName;
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
            const interactive = (mode !== 'readonly') && seatData.exists && status === 'available';
            if (!interactive) {
                btn.disabled = true;
            }

            // Gestion clic futur
            if (interactive && typeof options.onSeatClick === 'function') {
                btn.addEventListener('click', () => options.onSeatClick({
                    seatId: seatData.id,
                    code: seatCode,
                    zone: zoneName,
                    rowIndex: row.index,
                    rowRank: row.rank,
                    number: seatNumber,
                    status
                }, btn));
            }

            td.appendChild(btn);
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