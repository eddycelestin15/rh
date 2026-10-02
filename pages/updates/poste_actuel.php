<?php 
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'fragment');
require_once __DIR__ . '/../../includes/check_session.php';
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    $im = $_SESSION['user_im'];

    $stmt = $pdo->prepare("SELECT * FROM personnel_poste_actuel WHERE im = ?");
    $stmt->execute([$im]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<style>
    #section_poste_actuel { width: 100%; padding: 10px; }
    #section_poste_actuel .form-section-title {
        font-size: 0.95rem; font-weight: 700; color: #0369a1; text-transform: uppercase;
        margin-bottom: 25px; display: flex; align-items: center;
        border-bottom: 2px solid #e0f2fe; padding-bottom: 12px;
    }
    #section_poste_actuel .form-section-title i { margin-right: 15px; color: #0ea5e9; }
    #section_poste_actuel .form-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px; }
    #section_poste_actuel .field-group {
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        gap: 8px;
    }
    #section_poste_actuel .field-group label { font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; }
    #section_poste_actuel .field-group select, #section_poste_actuel .field-group input {
        padding: 10px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 0.85rem; background-color: #f8fafc;
    }
    #section_poste_actuel .hidden { display: none !important; }
    .required-star { color: #ef4444; margin-left: 3px; }
    .pageContent-inner-wrapper { padding: 0.5mm; box-sizing: border-box; }
</style>

<div style="background-color: white; padding: 10px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
    <div class="pageContent-inner-wrapper">
        <form id="formPosteActuel" enctype="multipart/form-data">
            <div id="section_poste_actuel">
                <div class="form-section-title"><i class="fas fa-briefcase"></i> Poste et Fonction</div>
                
                <div class="form-grid-3">
                    <div class="field-group">
                        <label>Type de fonction <span class="required-star">*</span></label>
                        <select name="type_fonction" id="type_fonction" onchange="handleTypeFonction()" required>
                            <option value="">-- Choisir --</option>
                            <option value="Personnel administratif" <?php echo ($p && $p['type_fonction'] == 'Personnel administratif') ? 'selected' : ''; ?>>Personnel administratif</option>
                            <option value="Personnel enseignant" <?php echo ($p && $p['type_fonction'] == 'Personnel enseignant') ? 'selected' : ''; ?>>Personnel enseignant</option>
                        </select>
                    </div>

                    <div class="field-group">
                        <label>Structure / Type d'établissement <span class="required-star">*</span></label>
                        <select name="type_etablissement" id="type_etablissement" onchange="handleTypeEtab()" required>
                            <option value="">-- Choisir --</option>
                        </select>
                    </div>

                    <div id="col_fonction_men" class="field-group hidden">
                        <label>Rattachement MEN <span class="required-star">*</span></label>
                        <select name="fonction_men" id="fonction_men" onchange="handleFonctionMen()">
                            <option value="">-- Choisir --</option>
                            <option value="Direction">Direction</option>
                            <option value="Service">Service</option>
                        </select>
                    </div>

                    <div id="col_fonction_dren" class="field-group hidden">
                        <label>Rattachement DREN <span class="required-star">*</span></label>
                        <select name="fonction_dren" id="fonction_dren" onchange="handleFonctionDren()">
                            <option value="">-- Choisir --</option>
                            <option value="Direction">Direction</option>
                            <option value="Service">Service</option>
                        </select>
                    </div>

                    <div id="col_fonction_cisco" class="field-group hidden">
                        <label>Rattachement CISCO <span class="required-star">*</span></label>
                        <select name="fonction_cisco" id="fonction_cisco" onchange="handleFonctionCisco()">
                            <option value="">-- Choisir --</option>
                            <option value="Service">Service</option>
                            <option value="Division">Division</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid-3">
                    <div id="col_direction_men" class="field-group hidden">
                        <label>Direction <span class="required-star">*</span></label>
                        <select name="direction_men" id="direction_men" onchange="handleDirectionMen()">
                            <option value="">-- Choisir --</option>
                        </select>
                    </div>

                    <div id="col_service_men" class="field-group hidden">
                        <label>Service Central <span class="required-star">*</span></label>
                        <select name="service_men" id="service_men" onchange="handleServiceMen()">
                            <option value="">-- Choisir --</option>
                        </select>
                    </div>

                    <div id="col_service" class="field-group hidden">
                        <label>Service <span class="required-star">*</span></label>
                        <select name="service" id="service" onchange="handleService()">
                            <option value="">-- Choisir --</option>
                        </select>
                    </div>

                    <div id="col_division" class="field-group hidden">
                        <label>Division <span class="required-star">*</span></label>
                        <select name="division" id="division" onchange="handleDivision()">
                            <option value="">-- Choisir --</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid-3">
                    <div id="col_nom_fonction_direction_men" class="field-group hidden">
                        <label>Fonction Direction <span class="required-star">*</span></label>
                        <select name="nom_fonction_direction_men" id="nom_fonction_direction_men" onchange="checkAutreChamp('nom_fonction_direction_men')">
                            <option value="">-- Choisir --</option>
                            <option value="Directeur">Directeur</option>
                            <option value="Secretaire Particulier">Secrétaire Particulier</option>
                            <option value="AUTRE">Autres...</option>
                        </select>
                    </div>
                    <div id="col_nom_fonction_direction_dren" class="field-group hidden">
                        <label>Fonction Direction DREN <span class="required-star">*</span></label>
                        <select name="nom_fonction_direction_dren" id="nom_fonction_direction_dren" onchange="checkAutreChamp('nom_fonction_direction_dren')">
                            <option value="">-- Choisir --</option>
                            <option value="Directeur">Directeur</option>
                            <option value="Secretaire Particulier">Secrétaire Particulier</option>
                            <option value="AUTRE">Autres...</option>
                        </select>
                    </div>
                    <div id="col_nom_fonction_service_cisco" class="field-group hidden">
                        <label>Fonction Service CISCO <span class="required-star">*</span></label>
                        <select name="nom_fonction_service_cisco" id="nom_fonction_service_cisco" onchange="checkAutreChamp('nom_fonction_service_cisco')">
                            <option value="">-- Choisir --</option>
                            <option value="Chef Cisco">Chef Cisco</option>
                            <option value="Secretaire Particulier">Secrétaire Particulier</option>
                            <option value="Assistant Technique en Informatique">Assistant Technique en Informatique</option>
                            <option value="AUTRE">Autres...</option>
                        </select>
                    </div>

                    <div id="col_fonction" class="field-group hidden">
                        <label>Fonction précise <span class="required-star">*</span></label>
                        <select name="fonction" id="fonction" onchange="checkAutreFonction()">
                            <option value="">-- Choisir --</option>
                        </select>
                    </div>

                    <div id="group_autre_fonction" class="field-group hidden">
                        <label>Précisez la fonction <span class="required-star">*</span></label>
                        <input type="text" name="autre_fonction_saisie" id="autre_fonction_saisie" class="form-control" placeholder="Entrez le nom de la fonction">
                    </div>

                    <div class="field-group hidden" id="col_matieres">
                        <label class="form-label font-medium" id="label_matieres">Matières enseignées *</label>
                        <select name="nom_matiere" id="matiere" class="form-control" required>
                            <option value="">-- Choisir --</option>
                        </select>
                    </div>
                </div>

                <div class="form-section-title" style="margin-top: 30px;"><i class="fas fa-map-marked-alt"></i> Localisation Administrative</div>

                <div class="form-grid-3">
                    <div class="field-group">
                        <label>Type de direction</label>
                        <input type="text" name="type_direction" id="type_direction" readonly value="<?php echo $p['type_direction'] ?? ''; ?>">
                    </div>

                    <div id="col_region" class="field-group hidden">
                        <label>DREN (Région) <span class="required-star">*</span></label>
                        <select name="loc_region" id="loc_region" onchange="handleRegion()">
                            <option value="">-- Choisir --</option>
                        </select>
                    </div>

                    <div id="col_cisco" class="field-group hidden">
                        <label>CISCO (District) <span class="required-star">*</span></label>
                        <select name="loc_district" id="loc_district" onchange="handleDistrict()">
                            <option value="">-- Choisir --</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid-3">
                    <div id="col_zap" class="field-group hidden">
                        <label>ZAP <span class="required-star">*</span></label>
                        <select name="loc_zap" id="loc_zap" onchange="handleZap()">
                            <option value="">-- Choisir --</option>
                        </select>
                    </div>

                    <div id="col_etab_final" class="field-group hidden" style="grid-column: span 2;">
                        <label id="label_etab_final">Établissement précis <span class="required-star">*</span></label>
                        <select name="loc_final" id="loc_final">
                            <option value="">-- Choisir --</option>
                        </select>
                    </div>
                </div>            
            </div> 

            <div class="form-grid-modern mt-6">
                <div class="field-group col-span-12">
                    <button type="button" onclick="updatePosteActuel()" style="background-color: #0284c7; border: none; border-radius: 6px; padding: 12px 20px; color: white; cursor: pointer; font-weight: bold; width: 100%;">
                        <i class="fas fa-save mr-1"></i> Mettre à jour
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div id="successModal" style="display:none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 16px; max-width: 400px; width: 90%; text-align: center; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
        <div style="color: #0284c7; font-size: 3.5rem; margin-bottom: 20px;">
            <i class="fas fa-check-circle"></i>
        </div>
        <h3 style="color: #0f172a; font-size: 1.3rem; font-weight: 800; margin-bottom: 8px;">Enregistrement réussi !</h3>
        <p style="color: #64748b; font-size: 0.95rem; line-height: 1.4; margin-bottom: 25px;">Les informations concernant votre poste actuel ont été mises à jour avec succès.</p>
        <button onclick="closeSuccessModal()" style="background: #0284c7; color: white; border: none; padding: 12px 0; border-radius: 8px; font-weight: 600; cursor: pointer; width: 100%;">
            Fermer
        </button>
    </div>
</div>

<script>
const savedData = <?php echo json_encode($p ?? null); ?>;

initCascadePreemplissage();

function initCascadePreemplissage() {
    if (!savedData || !savedData.type_fonction) return;

    document.getElementById('type_fonction').value = savedData.type_fonction;
    handleTypeFonction(savedData.type_etablissement);

    prefillLocalisation(savedData).then(() => {
        if (savedData.type_fonction === 'Personnel administratif') {
            prefillAdministratif(savedData);
        } else {
            prefillEnseignant(savedData);
        }
    });
}

function prefillLocalisation(saved) {
    if (!saved.nom_region) return Promise.resolve();

    document.getElementById('col_region').classList.remove('hidden');
    
    return fetchPopulate('api/referentiel/get_loc.php?action=get_regions', 'loc_region')
    .then(() => {
        document.getElementById('loc_region').value = saved.nom_region;
        
        const typeEtab = saved.type_etablissement || document.getElementById('type_etablissement').value;
        
        if (typeEtab === 'DREN') {
            // On ne montre ni CISCO ni ZAP ni Établissement
            return Promise.resolve();
        }
        document.getElementById('col_cisco').classList.remove('hidden');
        return fetchPopulate(`api/referentiel/get_loc.php?action=get_districts&reg_nom=${encodeURIComponent(saved.nom_region)}&type_etablissement=${encodeURIComponent(typeEtab)}`, 'loc_district');
    })
    .then(() => {
        if (!saved.nom_district) return Promise.resolve();
        
        document.getElementById('loc_district').value = saved.nom_district;

        if (saved.type_etablissement === 'CRFRP') {
            document.getElementById('col_etab_final').classList.remove('hidden');
            return fetchPopulate(`api/referentiel/get_loc.php?action=get_crfrp_par_district&dist_nom=${encodeURIComponent(saved.nom_district)}`, 'loc_final')
                .then(() => {
                    setTimeout(() => {
                        if (saved.nom_etablissement) {
                            document.getElementById('loc_final').value = saved.nom_etablissement;
                        }
                    }, 400);
                });
        } 
        else if (saved.type_etablissement === 'CISCO') {
            return Promise.resolve();
        } 
        else {
            document.getElementById('col_zap').classList.remove('hidden');
            return fetchPopulate(`api/referentiel/get_loc.php?action=get_zaps&dist_nom=${encodeURIComponent(saved.nom_district)}`, 'loc_zap');
        }
    })
    .then(() => {
        if (saved.nom_zap && document.getElementById('loc_zap')) {
            document.getElementById('loc_zap').value = saved.nom_zap;
            
            document.getElementById('col_etab_final').classList.remove('hidden');
            
            return fetchPopulate(
                `api/referentiel/get_loc.php?action=get_etabs_par_type&zap_nom=${encodeURIComponent(saved.nom_zap)}&dist_nom=${encodeURIComponent(saved.nom_district)}&type_name=${encodeURIComponent(saved.type_etablissement)}`, 
                'loc_final'
            ).then(() => {
                // Délai supplémentaire pour garantir le chargement des options
                setTimeout(() => {
                    if (saved.nom_etablissement && document.getElementById('loc_final')) {
                        document.getElementById('loc_final').value = saved.nom_etablissement;
                    }
                }, 500);
            });
        }
    });
}

// Force la sélection après chargement complet
function forceSelectOption(selectId, value) {
    if (!value) return;
    const select = document.getElementById(selectId);
    if (!select) return;

    setTimeout(() => {
        select.value = value;
        
        // Si la valeur n'est toujours pas sélectionnée, on force manuellement
        if (select.value !== value) {
            for (let i = 0; i < select.options.length; i++) {
                if (select.options[i].value === value) {
                    select.selectedIndex = i;
                    break;
                }
            }
        }
    }, 600);
}

function prefillAdministratif(saved) {
    const te = saved.type_etablissement;

    // ==========================================
    // 1. STRUCTURE : MEN CENTRAL
    // ==========================================
    if (te === 'MEN CENTRAL' || te === 'CENTRAL') {
        document.getElementById('col_fonction_men').classList.remove('hidden');
        
        let isDirection = false;
        
        // Détection du type de rattachement (Direction ou Service)
        if (saved.nom_fonction) {
            const fonctionLower = saved.nom_fonction.toLowerCase().trim();
            if (['directeur', 'secrétaire particulier', 'secretaire particulier'].some(f => 
                fonctionLower.includes(f)
            )) {
                isDirection = true;
            } else if (saved.nom_service && saved.nom_service.trim() !== '') {
                isDirection = false;
            } else if (saved.nom_direction && saved.nom_direction.trim() !== '' && (!saved.nom_service || saved.nom_service.trim() === '')) {
                isDirection = true;
            }
        }
        
        const choixMen = isDirection ? 'Direction' : 'Service';
        document.getElementById('fonction_men').value = choixMen;

        // ==========================================
        // CAS : DIRECTION MEN CENTRAL
        // ==========================================
        if (choixMen === 'Direction') {
            handleFonctionMen().then(() => {
                // Forcer l'affichage du conteneur de la fonction de Direction MEN
                const colDirFonc = document.getElementById('col_nom_fonction_direction_men');
                if (colDirFonc) {
                    colDirFonc.classList.remove('hidden');
                }

                if (saved.nom_direction) {
                    setTimeout(() => {
                        const dirSelect = document.getElementById('direction_men');
                        if (dirSelect) {
                            let optionToSelect = [...dirSelect.options].find(o => o.text === saved.nom_direction);
                            if (optionToSelect) dirSelect.value = optionToSelect.value;
                            else dirSelect.value = saved.nom_direction;
                        }
                    }, 200);
                }
                
                if (saved.nom_fonction) {
                    setTimeout(() => {
                        const selectFoncDirMen = document.getElementById('nom_fonction_direction_men');
                        if (selectFoncDirMen) {
                            const valeursStandards = ['Directeur', 'Secretaire Particulier'];
                            
                            // Nettoyage de chaînes pour éviter les pièges d'accents ou d'espaces
                            const cleanSavedFonc = saved.nom_fonction.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim();

                            let optionTrouvee = [...selectFoncDirMen.options].find(o => 
                                o.value.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim() === cleanSavedFonc ||
                                o.text.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim() === cleanSavedFonc
                            );

                            if (optionTrouvee) {
                                // Cas standard : Directeur ou Secrétaire Particulier
                                selectFoncDirMen.value = optionTrouvee.value;
                                
                                // Masquer le champ "autre" si une option standard est trouvée
                                hideAndClear('group_autre_fonction');
                            } else {
                                // Cas "AUTRE" : La fonction est une saisie personnalisée
                                selectFoncDirMen.value = 'AUTRE';
                                
                                // Forcer l'affichage immédiat du bloc de saisie libre libre "Précisez la fonction *"
                                const groupAutre = document.getElementById('group_autre_fonction');
                                const inputAutre = document.getElementById('autre_fonction_saisie');
                                
                                if (groupAutre) {
                                    groupAutre.classList.remove('hidden');
                                }
                                if (inputAutre) {
                                    inputAutre.value = saved.nom_fonction;
                                    inputAutre.required = true;
                                }
                            }
                        }
                    }, 500);
                }
            });
        } 
        // ==========================================
        // CAS : SERVICE MEN CENTRAL
        // ==========================================
        else if (choixMen === 'Service') {
            handleFonctionMen().then(() => {
                if (saved.nom_direction) {
                    setTimeout(() => {
                        const dirSelect = document.getElementById('direction_men');
                        if (dirSelect) {
                            let optionToSelect = [...dirSelect.options].find(o => o.text === saved.nom_direction);
                            if (optionToSelect) dirSelect.value = optionToSelect.value;
                            else dirSelect.value = saved.nom_direction;
                            
                            // Déclencher le chargement des services
                            handleDirectionMen().then(() => {
                                if (saved.nom_service) {
                                    setTimeout(() => {
                                        const serSelect = document.getElementById('service_men');
                                        if (serSelect) {
                                            let optSer = [...serSelect.options].find(o => o.text === saved.nom_service);
                                            if (optSer) serSelect.value = optSer.value;
                                            else serSelect.value = saved.nom_service;
                                            
                                            // Déclencher le chargement des fonctions du service
                                            handleServiceMen().then(() => {
                                                if (saved.nom_fonction) {
                                                    setTimeout(() => {
                                                        const selectFoncService = document.getElementById('fonction');
                                                        if (selectFoncService) {
                                                            const valeursStandards = ['Chef de Service', 'Adjoint au Chef de Service', 'Chef de Section'];
                                                            if (valeursStandards.includes(saved.nom_fonction)) {
                                                                selectFoncService.value = saved.nom_fonction;
                                                                checkAutreFonction();
                                                            } else {
                                                                selectFoncService.value = 'AUTRE';
                                                                
                                                                const groupAutre = document.getElementById('group_autre_fonction');
                                                                const inputAutre = document.getElementById('autre_fonction_saisie');
                                                                if (groupAutre) groupAutre.classList.remove('hidden');
                                                                if (inputAutre) {
                                                                    inputAutre.value = saved.nom_fonction;
                                                                    inputAutre.required = true;
                                                                }
                                                            }
                                                        }
                                                    }, 300);
                                                }
                                            });
                                        }
                                    }, 300);
                                }
                            });
                        }
                    }, 400);
                }
            });
        }
    } 
    else if (te === 'DREN') {
        document.getElementById('col_fonction_dren').classList.remove('hidden');
        
        let isDirection = false;
        
        // 1. Détection automatique : Direction ou Service ?
        if (saved.nom_direction && saved.nom_direction.trim() !== '') {
            isDirection = true;
        }
        else if (saved.nom_fonction && (!saved.nom_service || saved.nom_service.trim() === '')) {
            isDirection = true;
        }
        else if (saved.nom_fonction) {
            const fonctionLower = saved.nom_fonction.toLowerCase().trim();
            if (['directeur', 'secrétaire particulier', 'secretaire particulier'].some(f => 
                fonctionLower.includes(f)
            )) {
                isDirection = true;
            }
        }
        
        const choixDren = isDirection ? 'Direction' : 'Service';
        document.getElementById('fonction_dren').value = choixDren;

        // ==========================================
        // CAS : DIRECTION DREN
        // ==========================================
        if (choixDren === 'Direction') {
            handleFonctionDren().then(() => {
                if (saved.nom_direction) {
                    setTimeout(() => {
                        const dirSelect = document.getElementById('direction_men');
                        if (dirSelect) dirSelect.value = saved.nom_direction;
                    }, 400);
                }
                
                if (saved.nom_fonction) {
                    setTimeout(() => {
                        const selectFoncDirDren = document.getElementById('nom_fonction_direction_dren');
                        if (selectFoncDirDren) {
                            const valeursStandards = ['Directeur', 'Secretaire Particulier'];
                            
                            if (valeursStandards.includes(saved.nom_fonction)) {
                                selectFoncDirDren.value = saved.nom_fonction;
                                checkAutreChamp('nom_fonction_direction_dren');
                            } else {
                                selectFoncDirDren.value = 'AUTRE';
                                checkAutreChamp('nom_fonction_direction_dren');
                                
                                const inputAutre = document.getElementById('autre_fonction_saisie');
                                if (inputAutre) {
                                    inputAutre.value = saved.nom_fonction;
                                }
                            }
                        }
                    }, 600);
                }
            });
        } 
        // ==========================================
        // CAS : SERVICE DREN
        // ==========================================
        else if (choixDren === 'Service') {
            handleFonctionDren().then(() => {
                if (saved.nom_service) {
                    setTimeout(() => {
                        document.getElementById('service').value = saved.nom_service;
                    }, 300);
                    return handleService();
                }
            }).then(() => {
                if (saved.nom_fonction) {
                    setTimeout(() => {
                        const selectFoncService = document.getElementById('fonction');
                        
                        if (selectFoncService) {
                            // Liste des options textuelles fixes/standards de votre select id="fonction"
                            const valeursStandards = ['Chef de Service', 'Adjoint au Chef de Service', 'Chef de Section']; 
                            
                            if (valeursStandards.includes(saved.nom_fonction)) {
                                // Cas classique : Option standard
                                selectFoncService.value = saved.nom_fonction;
                                checkAutreFonction();
                            } else {
                                // Cas "AUTRE" : La fonction est une saisie libre
                                selectFoncService.value = 'AUTRE';
                                
                                // FORCE L'AFFICHAGE : On retire la classe 'hidden' du bloc "Précisez la fonction *"
                                const groupAutre = document.getElementById('group_autre_fonction');
                                const inputAutre = document.getElementById('autre_fonction_saisie');
                                
                                if (groupAutre) {
                                    groupAutre.classList.remove('hidden');
                                }
                                
                                // On injecte la valeur textuelle enregistrée (nom_fonction)
                                if (inputAutre) {
                                    inputAutre.value = saved.nom_fonction;
                                    inputAutre.required = true;
                                }
                            }
                        }
                    }, 600);
                }
            });
        }
    } else if (te === 'CISCO') {
        document.getElementById('col_fonction_cisco').classList.remove('hidden');
        
        let isServiceCisco = false;
        if (saved.nom_fonction) {
            const fLower = saved.nom_fonction.toLowerCase().trim();
            if (['chef cisco', 'adjoint chef cisco', 'chef de service'].some(f => fLower.includes(f))) {
                isServiceCisco = true;
            }
            // Si la fonction est personnalisée mais qu'il n'y a pas de division, on assume "Service" par défaut
            else if (!saved.nom_division || saved.nom_division.trim() === '') {
                isServiceCisco = true;
            }
        }
        
        const choixCisco = isServiceCisco ? 'Service' : 'Division';
        document.getElementById('fonction_cisco').value = choixCisco;

        if (choixCisco === 'Service') {
            handleFonctionCisco().then(() => {
                // === TRAITEMENT AUTRE FONCTION SERVICE CISCO ===
                if (saved.nom_fonction) {
                    setTimeout(() => {
                        const selectFoncServiceCisco = document.getElementById('nom_fonction_service_cisco');
                        if (selectFoncServiceCisco) {
                            const valeursStandards = ['Chef Cisco', 'Secretaire Particulier', 'Assistant Technique en Informatique'];
                            
                            if (valeursStandards.includes(saved.nom_fonction)) {
                                selectFoncServiceCisco.value = saved.nom_fonction;
                                checkAutreChamp('nom_fonction_service_cisco');
                            } else {
                                // Forcer sur AUTRE
                                selectFoncServiceCisco.value = 'AUTRE';
                                
                                // Affichage forcé du champ de saisie textuelle
                                const groupAutre = document.getElementById('group_autre_fonction');
                                const inputAutre = document.getElementById('autre_fonction_saisie');
                                if (groupAutre) groupAutre.classList.remove('hidden');
                                
                                if (inputAutre) {
                                    inputAutre.value = saved.nom_fonction;
                                    inputAutre.required = true;
                                }
                            }
                        }
                    }, 600);
                }
            });
        } 
        else if (choixCisco === 'Division') {
            handleFonctionCisco().then(() => {
                if (saved.nom_division) {
                    setTimeout(() => {
                        document.getElementById('division').value = saved.nom_division;
                    }, 300);
                    return handleDivision();
                }
            }).then(() => {
                // === TRAITEMENT AUTRE FONCTION DIVISION CISCO ===
                if (saved.nom_fonction) {
                    setTimeout(() => {
                        const selectFoncDivision = document.getElementById('fonction');
                        if (selectFoncDivision) {
                            const valeursStandards = ['Chef de Division', 'Chef de Section'];
                            
                            if (valeursStandards.includes(saved.nom_fonction)) {
                                selectFoncDivision.value = saved.nom_fonction;
                                checkAutreFonction();
                            } else {
                                selectFoncDivision.value = 'AUTRE';
                                
                                const groupAutre = document.getElementById('group_autre_fonction');
                                const inputAutre = document.getElementById('autre_fonction_saisie');
                                if (groupAutre) groupAutre.classList.remove('hidden');
                                
                                if (inputAutre) {
                                    inputAutre.value = saved.nom_fonction;
                                    inputAutre.required = true;
                                }
                            }
                        }
                    }, 600);
                }
            });
        }
    } 
    else if (['CRFRP','LYCEE','COLLEGE','PRIMAIRE'].includes(te)) {
        document.getElementById('col_fonction').classList.remove('hidden');
        const ratt = (te === 'CRFRP') ? 'CRFRP' : 'ETAB_ADMIN';
        fetchPopulate(`api/referentiel/get_data.php?action=get_fonctions&rattachement=${ratt}`, 'fonction')
        .then(() => {
            if (saved.nom_fonction) {
                document.getElementById('fonction').value = saved.nom_fonction;
                checkAutreFonction();
            }
        });
    }
}

function prefillEnseignant(saved) {
    const te = saved.type_etablissement;
    document.getElementById('col_fonction').classList.remove('hidden');

    let ratt = 'ENSEIGNANT';
    if (te === 'CRFRP') ratt = 'FORMATEUR';
    if (te === 'PRESCOLAIRE') ratt = 'EDUCATEUR';

    fetchPopulate(`api/referentiel/get_data.php?action=get_fonctions&rattachement=${ratt}`, 'fonction')
    .then(() => {
        if (saved.nom_fonction) {
            document.getElementById('fonction').value = saved.nom_fonction;
            checkAutreFonction();
        }
        
        // === CORRECTION : Pré-remplissage des matières ===
        if (saved.nom_matiere && ['LYCEE','COLLEGE','CRFRP'].includes(te)) {
            document.getElementById('col_matieres').classList.remove('hidden');
            let niv = (te === 'CRFRP') ? 'CRFRP' : te;
            
            fetchPopulate(`api/referentiel/get_data.php?action=get_matieres&niveau=${niv}`, 'matiere')
            .then(() => {
                // Sélection de la matière sauvegardée
                if (saved.nom_matiere) {
                    document.getElementById('matiere').value = saved.nom_matiere;
                }
                updateMatieresLabel(te === 'CRFRP' ? 'Modules *' : 'Matières enseignées *');
            });
        }
    });
}

// ====================== GESTIONNAIRES DE SÉLECTION DYNAMIQUES ======================

function handleFonctionMen() {
    const fm = document.getElementById('fonction_men').value;
    
    hideAndClear('col_direction_men');
    hideAndClear('col_service_men');
    hideAndClear('col_nom_fonction_direction_men');
    hideAndClear('col_fonction');
    hideAndClear('group_autre_fonction');

    if (!fm) return Promise.resolve();

    document.getElementById('col_direction_men').classList.remove('hidden');
    return fetchPopulate('api/referentiel/get_data.php?action=get_directions_men', 'direction_men');
}

function handleDirectionMen() {
    const fm = document.getElementById('fonction_men').value;
    const dm = document.getElementById('direction_men').value;

    hideAndClear('col_service_men');
    hideAndClear('col_nom_fonction_direction_men');
    hideAndClear('col_fonction');
    hideAndClear('group_autre_fonction');

    if (!dm) return Promise.resolve();

    if (fm === 'Direction') {
        document.getElementById('col_nom_fonction_direction_men').classList.remove('hidden');
        return Promise.resolve();
    } else if (fm === 'Service') {
        document.getElementById('col_service_men').classList.remove('hidden');
        return fetchPopulate(`api/referentiel/get_data.php?action=get_services_men&id_direction=${encodeURIComponent(dm)}`, 'service_men');
    }
    return Promise.resolve();
}

function handleServiceMen() {
    const sm = document.getElementById('service_men').value;

    hideAndClear('col_fonction');
    hideAndClear('group_autre_fonction');

    // CORRECTION 1 : Retourner une promesse résolue si la valeur est vide
    if (!sm) return Promise.resolve();

    document.getElementById('col_fonction').classList.remove('hidden');
    
    // CORRECTION 2 : Ajouter impérativement le "return" devant fetchPopulate 
    // pour propager la promesse vers le .then() de prefillAdministratif
    return fetchPopulate(`api/referentiel/get_data.php?action=get_fonctions&rattachement=SERVICE&parent_nom=${encodeURIComponent(sm)}`, 'fonction');
}

function handleFonctionDren() {
    const fd = document.getElementById('fonction_dren').value;
    
    hideAndClear('col_service');
    hideAndClear('col_nom_fonction_direction_men'); // Optionnel, par sécurité
    hideAndClear('col_nom_fonction_direction_dren'); // Nettoyage du nouveau champ
    hideAndClear('col_fonction');
    hideAndClear('group_autre_fonction');

    if (!fd) return Promise.resolve();

    if (fd === 'Direction') {
        document.getElementById('col_nom_fonction_direction_dren').classList.remove('hidden'); // Affichage ici
        return Promise.resolve();
    } else if (fd === 'Service') {
        document.getElementById('col_service').classList.remove('hidden');
        return fetchPopulate('api/referentiel/get_data.php?action=get_services_dren', 'service');
    }
    return Promise.resolve();
}

function handleService() {
    const s = document.getElementById('service').value;

    hideAndClear('col_fonction');
    hideAndClear('group_autre_fonction');

    if (!s) return Promise.resolve();

    document.getElementById('col_fonction').classList.remove('hidden');
    // On passe bien rattachement=SERVICE et parent_nom
    return fetchPopulate(`api/referentiel/get_data.php?action=get_fonctions&rattachement=SERVICE&parent_nom=${encodeURIComponent(s)}`, 'fonction');
}

function handleFonctionCisco() {
    const fc = document.getElementById('fonction_cisco').value;
    
    hideAndClear('col_service');
    hideAndClear('col_division');
    hideAndClear('col_nom_fonction_service_cisco');
    hideAndClear('col_fonction');
    hideAndClear('group_autre_fonction');

    if (!fc) return Promise.resolve();

    if (fc === 'Service') {
        document.getElementById('col_nom_fonction_service_cisco').classList.remove('hidden');
        return Promise.resolve();
    } else if (fc === 'Division') {
        document.getElementById('col_division').classList.remove('hidden');
        return fetchPopulate('api/referentiel/get_data.php?action=get_divisions_cisco', 'division');
    }
    return Promise.resolve();
}

function handleDivision() {
    const d = document.getElementById('division').value;

    hideAndClear('col_fonction');
    hideAndClear('group_autre_fonction');

    if (!d) return Promise.resolve();

    document.getElementById('col_fonction').classList.remove('hidden');
    // On passe bien rattachement=DIVISION et parent_nom
    return fetchPopulate(`api/referentiel/get_data.php?action=get_fonctions&rattachement=DIVISION&parent_nom=${encodeURIComponent(d)}`, 'fonction');
}

function handleTypeFonction(preselectedValue = null) {
    const tf = document.getElementById('type_fonction').value;
    const te = document.getElementById('type_etablissement');
    te.innerHTML = '<option value="">-- Choisir --</option>';
    if(!tf) return;

    const list = (tf === 'Personnel administratif') 
        ? ['MEN CENTRAL','DREN','CISCO','CRFRP','LYCEE','COLLEGE','PRIMAIRE'] 
        : ['CRFRP','LYCEE','COLLEGE','PRIMAIRE','PRESCOLAIRE'];
    
    list.forEach(t => {
        const opt = new Option(t, t);
        if(preselectedValue === t) opt.selected = true;
        te.add(opt);
    });

    if(te.value) handleTypeEtab();
}

function hideAndClear(id) {
    const col = document.getElementById(id);
    if (col) {
        col.classList.add('hidden');
        const field = col.querySelector('select, input');
        if (field) {
            field.value = ""; 
            field.required = false;
        }
    }
}

function handleTypeEtab() {
    const te = document.getElementById('type_etablissement').value;
    const tf = document.getElementById('type_fonction').value;
    const dir = document.getElementById('type_direction');

    const fieldsToReset = [
        'col_fonction_men', 'col_fonction_dren', 'col_fonction_cisco',
        'col_direction_men', 'col_service_men', 'col_service', 'col_division',
        'col_nom_fonction_direction_men', 'col_nom_fonction_direction_dren', 
        'col_nom_fonction_service_cisco',
        'col_fonction', 'group_autre_fonction', 'col_matieres', 
        'col_region', 'col_cisco', 'col_zap', 'col_etab_final'
    ];
    fieldsToReset.forEach(hideAndClear);

    if (!te) return;

    // Gestion du type_direction
    if (te === 'MEN CENTRAL') dir.value = "MEN CENTRAL";
    else if (te === 'CRFRP') dir.value = "INFP";
    else if (te === 'DREN') dir.value = "DREN";
    else dir.value = "DREN";

    if (tf === 'Personnel enseignant') {
        let ratt = 'ENSEIGNANT';
        let defaultFonction = 'Enseignant(e)';

        if (te === 'CRFRP') {
            ratt = 'FORMATEUR';
            defaultFonction = 'Formateur';
        } else if (te === 'LYCEE') {
            defaultFonction = 'Professeur';
        } else if (te === 'COLLEGE' || te === 'PRIMAIRE' || te === 'PRESCOLAIRE') {
            defaultFonction = 'Enseignant(e)';
        } else if (te === 'PRESCOLAIRE') {
            ratt = 'EDUCATEUR';
            defaultFonction = 'Educateur(trice)';
        }

        document.getElementById('col_fonction').classList.remove('hidden');
        fetchPopulate(`api/referentiel/get_data.php?action=get_fonctions&rattachement=${ratt}`, 'fonction')
        .then(() => {
            setTimeout(() => setDefaultFonction(defaultFonction), 150);
        });

        if (['LYCEE', 'COLLEGE', 'CRFRP'].includes(te)) {
            document.getElementById('col_matieres').classList.remove('hidden');
            const niv = (te === 'CRFRP') ? 'CRFRP' : te;
            fetchPopulate(`api/referentiel/get_data.php?action=get_matieres&niveau=${niv}`, 'matiere')
            .then(() => updateMatieresLabel(te === 'CRFRP' ? 'Modules *' : 'Matières enseignées *'));
        }
    } 
    else {
        // === Partie Administrative ===
        if (te === 'MEN CENTRAL') {
            document.getElementById('col_fonction_men').classList.remove('hidden');
        } 
        else if (te === 'DREN') {
            document.getElementById('col_fonction_dren').classList.remove('hidden');
        } 
        else if (te === 'CISCO') {
            document.getElementById('col_fonction_cisco').classList.remove('hidden');
        } 
        else if (['CRFRP','LYCEE','COLLEGE','PRIMAIRE'].includes(te)) {
            document.getElementById('col_fonction').classList.remove('hidden');
            const ratt = (te === 'CRFRP') ? 'CRFRP' : 'ETAB_ADMIN';
            fetchPopulate(`api/referentiel/get_data.php?action=get_fonctions&rattachement=${ratt}`, 'fonction');
        }
    }

    // Localisation
    if (te !== 'MEN CENTRAL') {
        document.getElementById('col_region').classList.remove('hidden');
        document.getElementById('loc_region').required = true;
        fetchPopulate('api/referentiel/get_loc.php?action=get_regions', 'loc_region');
    }
}

function fetchPopulate(url, targetId) {
    const target = document.getElementById(targetId);
    if (!target) return Promise.resolve();
    
    return fetch(url)
        .then(response => {
            if (!response.ok) throw new Error(`HTTP: ${response.status}`);
            return response.text();
        })
        .then(text => {
            if (!text || text.trim() === "") return [];
            try { return JSON.parse(text); } catch (e) { return []; }
        })
        .then(data => {
            target.innerHTML = '<option value="">-- Choisir --</option>';
            if (Array.isArray(data)) {
                data.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.nom;
                    option.textContent = item.nom;
                    target.appendChild(option);
                });
            }
            if (targetId === 'fonction' || targetId === 'nom_fonction_direction_men' || targetId === 'nom_fonction_direction_dren' || targetId === 'nom_fonction_service_cisco') {
                if (![...target.options].some(o => o.value === 'AUTRE')) {
                    target.add(new Option('Autres...', 'AUTRE'));
                }
            }
        })
        .catch(err => console.error(err));
}

// Sélection automatique de la fonction par défaut
function setDefaultFonction(defaultText) {
    if (!defaultText) return;
    
    const select = document.getElementById('fonction');
    if (!select) return;

    for (let i = 0; i < select.options.length; i++) {
        if (select.options[i].textContent.trim() === defaultText) {
            select.selectedIndex = i;
            select.dispatchEvent(new Event('change'));
            break;
        }
    }
}

// Mise à jour du label des matières
function updateMatieresLabel(labelText) {
    const label = document.getElementById('label_matieres');
    if (label) label.textContent = labelText;
}

function checkAutreChamp(selectId) {
    const select = document.getElementById(selectId);
    const groupAutre = document.getElementById('group_autre_fonction');
    const inputAutre = document.getElementById('autre_fonction_saisie');

    if (select && select.value === 'AUTRE') {
        groupAutre.classList.remove('hidden');
        inputAutre.required = true;
    } else {
        const selMen = document.getElementById('nom_fonction_direction_men').value;
        const selDren = document.getElementById('nom_fonction_direction_dren') ? document.getElementById('nom_fonction_direction_dren').value : ''; // Prise en compte
        const selCisco = document.getElementById('nom_fonction_service_cisco').value;
        const selFonc = document.getElementById('fonction').value;
        
        const fm = document.getElementById('fonction_men').value;
        if (fm === 'Service' && document.getElementById('service_men').value !== '') {
            return; 
        }

        if (selMen !== 'AUTRE' && selDren !== 'AUTRE' && selCisco !== 'AUTRE' && selFonc !== 'AUTRE') {
            groupAutre.classList.add('hidden');
            inputAutre.required = false;
            inputAutre.value = '';
        }
    }
}

function checkAutreFonction() {
    checkAutreChamp('fonction');
}

function handleRegion() {
    const regNom = document.getElementById('loc_region').value; 
    const te = document.getElementById('type_etablissement').value; 

    hideAndClear('col_cisco');
    hideAndClear('col_zap');
    hideAndClear('col_etab_final');

    if (!regNom) return;

    // RÈGLE : Si type_etablissement = DREN -> Arrêt complet ici après l'affichage de la Région
    if (te === 'DREN') return;

    // Pour tous les autres cas (CISCO, LYCEE, COLLEGE, PRIMAIRE, CRFRP), on passe au District (CISCO)
    document.getElementById('col_cisco').classList.remove('hidden');
    document.getElementById('loc_district').required = true;
    fetchPopulate(`api/referentiel/get_loc.php?action=get_districts&reg_nom=${encodeURIComponent(regNom)}&type_etablissement=${encodeURIComponent(te)}`, 'loc_district');
}

function handleDistrict() {
    const distNom = document.getElementById('loc_district').value;
    const te = document.getElementById('type_etablissement').value;

    hideAndClear('col_zap');
    hideAndClear('col_etab_final');

    if (!distNom) return;

    // RÈGLE : Si type_etablissement = CISCO -> Arrêt complet ici après le District
    if (te === 'CISCO') return;

    // RÈGLE : Si type_etablissement = CRFRP -> On saute la ZAP et on affiche directement loc_final (Établissement précis)
    if (te === 'CRFRP') {
        document.getElementById('col_etab_final').classList.remove('hidden');
        document.getElementById('loc_final').required = true;
        fetchPopulate(`api/referentiel/get_loc.php?action=get_crfrp_par_district&dist_nom=${encodeURIComponent(distNom)}`, 'loc_final');
    } 
    // RÈGLE : Pour LYCEE, COLLEGE, PRIMAIRE -> On passe à la ZAP avant l'établissement
    else if (['LYCEE', 'COLLEGE', 'PRIMAIRE', 'PRESCOLAIRE'].includes(te)) {
        document.getElementById('col_zap').classList.remove('hidden');
        document.getElementById('loc_zap').required = true;
        fetchPopulate(`api/referentiel/get_loc.php?action=get_zaps&dist_nom=${encodeURIComponent(distNom)}`, 'loc_zap');
    }
}

function handleZap() {
    const zapNom = document.getElementById('loc_zap').value;
    const te = document.getElementById('type_etablissement').value;
    const distNom = document.getElementById('loc_district').value; // Vérifiez bien cette ligne
    
    hideAndClear('col_etab_final');
    if (!zapNom) return;

    document.getElementById('col_etab_final').classList.remove('hidden');
    document.getElementById('loc_final').required = true;
    
    // Appel mis à jour avec les 3 paramètres requis
    fetchPopulate(`api/referentiel/get_loc.php?action=get_etabs_par_type&zap_nom=${encodeURIComponent(zapNom)}&dist_nom=${encodeURIComponent(distNom)}&type_name=${encodeURIComponent(te)}`, 'loc_final');
}

function updatePosteActuel() {
    const form = document.getElementById('formPosteActuel');
    if(!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    const formData = new FormData(form);
    formData.append('step_index', '3');

    fetch('actions/personnel/save_step.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) { showSuccessModal(); } 
        else { alert('Erreur : ' + (data.message || 'Erreur inconnue')); }
    })
    .catch(error => {
        console.error(error);
        alert('Une erreur réseau est survenue.');
    });
}

function showSuccessModal() { document.getElementById('successModal').style.display = 'flex'; }
function closeSuccessModal() { document.getElementById('successModal').style.display = 'none'; location.reload(); }
window.updatePosteActuel = updatePosteActuel;
window.handleTypeFonction = handleTypeFonction;
window.handleTypeEtab = handleTypeEtab;
window.handleFonctionMen = handleFonctionMen;
window.handleDirectionMen = handleDirectionMen;
window.handleServiceMen = handleServiceMen;
window.handleFonctionDren = handleFonctionDren;
window.handleService = handleService;
window.handleFonctionCisco = handleFonctionCisco;
window.handleDivision = handleDivision;
window.checkAutreChamp = checkAutreChamp;
window.checkAutreFonction = checkAutreFonction;
window.handleRegion = handleRegion;
window.handleDistrict = handleDistrict;
window.handleZap = handleZap;
window.showSuccessModal = showSuccessModal;
window.closeSuccessModal = closeSuccessModal;
initCascadePreemplissage();
</script>