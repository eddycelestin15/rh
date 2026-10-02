// Fonction pour mettre à jour les types de demandes et destinations
function updateBordereauFilters(targetId) {
    const DB_NIVEAU = (window.DB_NIVEAU || '').toLowerCase().trim(); 
    const DB_ROLE   = window.DB_ROLE || '';

    const tb = $('#select_type_bordereau').val();         
    const td = $('#filter_type_dos').val(); 
    const isSolde = DB_ROLE === 'resp_solde';    

    // Si le changement vient du Type de Bordereau
    if (targetId === 'select_type_bordereau' || !targetId) {
        if (tb === 'conge') {
            $('#filter_type_dos').html('<option value="conge_annuel" selected>Congé annuel</option>');
        } else {
            let demandOpts = '<option value=""> -- Choisir type demande --</option>';
            
            if (['admin', 'chef_service', 'chef_division', 'resp_personnel_crfrp'].includes(DB_ROLE)) {
                demandOpts += '<option value="renouvellement">Renouvellement</option>' +
                            '<option value="avenant">Avenant</option>' +
                            '<option value="integration">Intégration</option>' +
                            '<option value="titularisation">Titularisation</option>' +
                            '<option value="avancement_classe">Avancement de classe</option>' +
                            '<option value="avancement_echelon">Avancement d\'échelon</option>' +
                            '<option value="admission_retraite">Admission à la retraite</option>' +
                            '<option value="compensatrice">Compensatrice</option>' +
                            '<option value="installation">Installation</option>';
            } else if (DB_ROLE === 'resp_non_encadre') {
                demandOpts += '<option value="renouvellement">Renouvellement</option>' +
                            '<option value="avenant">Avenant</option>' +
                            '<option value="integration">Intégration</option>';
            } else if (DB_ROLE === 'resp_encadre') {
                demandOpts += '<option value="titularisation">Titularisation</option>' +
                            '<option value="avancement_classe">Avancement de classe</option>' +
                            '<option value="avancement_echelon">Avancement d\'échelon</option>';
            } else if (DB_ROLE === 'resp_retraite') {
                demandOpts += '<option value="admission_retraite">Admission à la retraite</option>' +
                            '<option value="compensatrice">Compensatrice</option>' +
                            '<option value="installation">Installation</option>';
            }                
            
            // Conserver la valeur sélectionnée si elle existe encore
            const currentSelectedTd = $('#filter_type_dos').val();
            $('#filter_type_dos').html(demandOpts);
            if (currentSelectedTd) {
                $('#filter_type_dos').val(currentSelectedTd);
            }
        }
    }

    const currentTd = $('#filter_type_dos').val(); 
    let opts = '<option value="">-- Destination --</option>';
    
    if (isSolde) {
        opts = '<option value="augure_dsp" selected>Solde et Pensions</option>';
        $('#service_destination').html(opts);
        return;
    }
    
    if (currentTd && tb) {
        if (tb === 'conge') {
            opts += '<option value="augure_fop">Fonction Publique</option>';
            opts += '<option value="prefecture">Préfecture</option>';
        }
        else if (DB_ROLE === 'resp_non_encadre' || DB_ROLE === 'resp_encadre' || DB_ROLE === 'resp_retraite') {
            if (tb === 'creation_projet') {
                if (DB_NIVEAU === 'district') {
                    opts += "<option value='augure_dren'>DREN</option>";
                } 
                else if (DB_NIVEAU === 'central') {
                    if (DB_ROLE === 'resp_encadre') {
                        if (['titularisation', 'avancement_classe', 'avancement_echelon'].includes(currentTd)) {
                            opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                            opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                            opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                            opts += '<option value="mtefop">Fonction Publique (MTeFOP)</option>';
                            opts += '<option value="primature">Primature</option>';
                        }
                    } 
                    else if (DB_ROLE === 'resp_non_encadre') {
                        if (['integration'].includes(currentTd)) {
                            opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                            opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                            opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                            opts += '<option value="mtefop">Fonction Publique (MTeFOP)</option>';
                            opts += '<option value="primature">Primature</option>';
                        } 
                        else if (['renouvellement', 'avenant'].includes(currentTd)) {
                            opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                            opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                        }
                    }
                    else if (DB_ROLE === 'resp_retraite') {
                        if (['admission_retraite'].includes(currentTd)) {
                            opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                            opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                            opts += '<option value="mtefop">Fonction Publique (MTeFOP)</option>';
                            opts += '<option value="primature">Primature</option>';
                        }
                        else if (['compensatrice', 'installation'].includes(currentTd)) {
                            opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                            opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                            opts += "<option value='drh'>Ministère de l'Education Nationale (MEN / DRH)</option>";
                        }
                    } 
                }
                else if (DB_NIVEAU === 'regional') {
                    if (DB_ROLE === 'resp_encadre') {
                        if (['avancement_classe', 'avancement_echelon'].includes(currentTd)) {
                            opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                            opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                            opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                            opts += '<option value="prefecture">Préfecture</option>';
                        } 
                        else if (['titularisation'].includes(currentTd)) {
                            opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                            opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                            opts += '<option value="drh">Direction des Ressources Humaines (DRH)</option>';
                        }
                    } 
                    else if (DB_ROLE === 'resp_non_encadre') {
                        if (['integration'].includes(currentTd)) {
                            opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                            opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                            opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                            opts += '<option value="drh">Direction des Ressources Humaines (DRH)</option>';
                        } 
                        else if (['renouvellement', 'avenant'].includes(currentTd)) {
                            opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                            opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                            opts += '<option value="prefecture">Préfecture</option>';
                        }
                    } else if (DB_ROLE === 'resp_retraite') {
                        if (['admission_retraite', 'installation'].includes(currentTd)) {
                            opts += '<option value="drh">Direction des Ressources Humaines (DRH)</option>';
                        }
                        else if (['compensatrice'].includes(currentTd)) {
                            opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                            opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                            opts += '<option value="prefecture">Préfecture</option>';
                        }
                    }
                }
            }
        }
        else {
            if (tb === 'creation_projet') {
                if (DB_NIVEAU === 'district') {
                    opts += "<option value='augure_dren'>DREN</option>";
                }
                else if (DB_NIVEAU === 'crfrp') {
                    if (['admission_retraite', 'installation'].includes(currentTd)) {
                        opts += "<option value='augure_dren'>DREN</option>";
                    } 
                    else if (['compensatrice'].includes(currentTd)) {
                        opts += '<option value="augure_dren">DREN</option>';
                        opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                        opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                        opts += '<option value="prefecture">Préfecture</option>';
                    }
                    else {
                        opts += "<option value='augure_dren'>DREN</option>";
                    }
                }
                else if (DB_NIVEAU === 'regional') {
                    if (['renouvellement', 'avenant', 'compensatrice'].includes(currentTd)) {
                        opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                        opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                        opts += '<option value="prefecture">Préfecture</option>';
                    } 
                    else if (['avancement_classe', 'avancement_echelon'].includes(currentTd)) {
                        opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                        opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                        opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                        opts += '<option value="prefecture">Préfecture</option>';
                    } 
                    else if (['titularisation', 'admission_retraite', 'installation'].includes(currentTd)) {
                        opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                        opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                        opts += '<option value="drh">Direction des Ressources Humaines (DRH)</option>';
                    }
                    else if (['integration'].includes(currentTd)) {
                        opts += '<option value="augure_fop">Fonction Publique (DRHEFOP)</option>';
                        opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                        opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                        opts += '<option value="drh">Direction des Ressources Humaines (DRH)</option>';
                    }
                }
                else if (DB_NIVEAU === 'central') {
                    if (['admission_retraite'].includes(currentTd)) {
                        opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                        opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                        opts += '<option value="mtefop">Fonction Publique (MTeFOP)</option>';
                        opts += '<option value="primature">Primature</option>';
                    }
                    else if (['compensatrice', 'installation'].includes(currentTd)) {
                        opts += '<option value="augure_dsp">Solde et Pensions (DSP)</option>';
                        opts += '<option value="augure_cf">Contrôle Financier (DGCF)</option>';
                        opts += "<option value='drh'>Ministère de l'Education Nationale (MEN / DRH)</option>";
                    }
                }
            } 
            else if (tb === 'mandatement') {
                opts += '<option value="augure_dsp">Solde et Pensions</option>';
            }
        }
    }
    
    $('#service_destination').html(opts);
}

// Écouteur d'événement délégué sur document (compatible AJAX/SPA)
$(document).on('change', '#filter_type_dos, #select_type_bordereau', function(e) {
    updateBordereauFilters($(e.target).attr('id'));
});

// Initialisation automatique au chargement du script
$(document).ready(function() {
    // Force la première mise à jour d'après les valeurs sélectionnées par défaut
    updateBordereauFilters();
});

window.loadAgents = function() {
    const DB_NIVEAU = (window.DB_NIVEAU || '').toLowerCase().trim();
    const tb = $('#select_type_bordereau').val();
    const td = $('#filter_type_dos').val();
    const sd = $('#service_destination').val();

    if(!tb || !td || !sd) {
        Swal.fire('Champs manquants', 'Merci de remplir tous les critères de filtrage.', 'warning');
        return;
    }

    if ($.fn.DataTable.isDataTable('#tableAgents')) {
        $('#tableAgents').DataTable().destroy();
    }

    $('#tableAgents').DataTable({
        "ajax": {
            "url": "api/bordereaux/api_agents_bordereau.php",
            "type": "POST",
            "data": {
                "action": "list_agents",
                "type_bordereau": tb,
                "filter_type_dos": td,
                "service_destination": sd
            }
        },
        "columns": getColumnsByNiveau(DB_NIVEAU),
        "dom": 'rtip',
        "pageLength": 10,
        "language": {
            "sEmptyTable":     "Aucune agent en attente de bordereau pour ce type de demande",
            "sInfo":           "Affichage de l'élément _START_ à _END_ sur _TOTAL_ éléments",
            "sInfoEmpty":      "Affichage de l'élément 0 à 0 sur 0 élément",
            "sInfoFiltered":   "(filtré à partir de _MAX_ éléments au total)",
            "sInfoPostFix":    "",
            "sInfoThousands":  ",",
            "sLengthMenu":     "Afficher _MENU_ éléments",
            "sLoadingRecords": "Chargement...",
            "sProcessing":     "Traitement...",
            "sSearch":         "Rechercher :",
            "sZeroRecords":    "Aucun élément correspondant trouvé",
            "oPaginate": {
                "sFirst":    "Premier",
                "sLast":     "Dernier",
                "sNext":     "Suivant",
                "sPrevious": "Précédent"
            },
            "oAria": {
                "sSortAscending":  ": activer pour trier la colonne par ordre croissant",
                "sSortDescending": ": activer pour trier la colonne par ordre décroissant"
            }
        }
    });
};

$('#customSearch').on('keyup', function() {
    if ($.fn.DataTable.isDataTable('#tableAgents')) {
        $('#tableAgents').DataTable().search(this.value).draw();
    }
});

function getColumnsByNiveau(niv) {
    const niveauClean = (niv || '').toString().toLowerCase().trim();
    if (niveauClean === 'central') {
        return [
            { "data": "num" },
            { "data": "lieu_service" },
            { "data": "nom_complet" },
            { "data": "im" },
            { "data": "corps_grade" },
            { "data": "action" }
        ];
    } else if (niveauClean === 'regional') {
        return [
            { "data": "num" },
            { "data": "cisco" },
            { "data": "zap" },
            { "data": "lieu_service" },
            { "data": "nom_complet" },
            { "data": "im" },
            { "data": "corps_grade" },
            { "data": "action" }
        ];
    } else if (niveauClean === 'district') {
        return [
            { "data": "num" },
            { "data": "zap" },
            { "data": "lieu_service" },
            { "data": "nom_complet" },
            { "data": "im" },
            { "data": "corps_grade" },
            { "data": "action" }
        ];
    } else {
        return [
            { "data": "num" },
            { "data": "nom_complet" },
            { "data": "im" },
            { "data": "corps_grade" },
            { "data": "action" }
        ];
    }
}

window.confirmerActionAgent = function(im, destination, valeur) {
    let messageStr = valeur === 1 
        ? "Voulez-vous ajouter cet agent dans le bordereau ?" 
        : "Voulez-vous retirer cet agent du bordereau ?";
        
    let confirmBtnColor = valeur === 1 ? '#2563eb' : '#dc2626';

    Swal.fire({
        title: 'Confirmation',
        text: messageStr,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: confirmBtnColor,
        cancelButtonColor: '#4b5563',
        confirmButtonText: valeur === 1 ? 'Oui, ajouter' : 'Oui, retirer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'api/bordereaux/api_agents_bordereau.php',
                type: 'POST',
                data: {
                    action: 'ajout_agent',
                    im: im,
                    destination: destination,
                    valeur: valeur
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Succès !', 'Opération effectuée avec succès.', 'success');
                        $('#tableAgents').DataTable().ajax.reload(null, false);
                    } else {
                        Swal.fire('Erreur', response.message || 'Une erreur est survenue', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Erreur', 'Erreur de communication avec le serveur.', 'error');
                }
            });
        }
    });
}

function updateAgentStatus(im, destination, valeur) {
    $.ajax({
        url: 'api/bordereaux/api_agents_bordereau.php',
        type: 'POST',
        data: {
            action: 'ajout_agent',
            im: im,
            destination: destination,
            valeur: valeur
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#tableAgents').DataTable().ajax.reload(null, false); 
                
                const message = valeur === 1 ? 'Agent ajouté au bordereau' : 'Agent retiré du bordereau';
                const toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000
                });
                toast.fire({ icon: 'success', title: message });
            } else {
                Swal.fire('Erreur', response.message, 'error');
            }
        }
    });
}

window.genererBordereauFinal = function() {
    const tb = $('select[name="type_bordereau"]').val(); 
    const td = $('select[name="filter_type_dos"]').val();
    const sd = $('#service_destination').val();
    
    if(!tb || !td || !sd) {
        Swal.fire('Attention', 'Veuillez remplir tous les filtres avant de générer.', 'warning');
        return;
    }

    const labelsDossiers = {
        'renouvellement': 'Renouvellement de contrat',
        'avenant': 'Avenant',
        'avancement_classe': 'Avancement de classe',
        'avancement_echelon': "Avancement d'échelon",
        'integration': 'Intégration',
        'titularisation': 'Titularisation',
        'compensatrice': 'Compensatrice',
        'installation': 'Installation',
        'conge_annuel': 'Congé annuel'
    };

    const labelDossier = labelsDossiers[td] || "Dossier";
    const labelDest = $("#service_destination option:selected").text();

    $.ajax({
        url: 'api/bordereaux/get_last_bordereau.php',
        method: 'GET',
        data: { 
            type_dos: td, 
            dest: sd,
            type_bordereau: tb          
        },
        dataType: 'json',
        success: function(res) {
            $('#mod_titre_dossier').text("Dossier de : " + labelDossier);
            $('#mod_destination_label').val(labelDest);
            $('#mod_dernier_num').text(res.dernier_complet || "Premier numéro");
            $('#mod_prochain_num').val(res.next_num);
            
            const sigleInput = $('#mod_sigle');
            sigleInput.val(res.sigle || '');
            if(res.sigle && res.sigle !== "") {
                sigleInput.attr('readonly', true).addClass('bg-gray-100 cursor-not-allowed');
            } else {
                sigleInput.attr('readonly', false).removeClass('bg-gray-100 cursor-not-allowed');
            }

            $('#modalBordereau').removeClass('hidden').addClass('flex');
        },
        error: function() {
            Swal.fire('Erreur', 'Impossible de récupérer les infos de numérotation.', 'error');
        }
    });
}

window.closeModalBordereau = function() {
    $('#modalBordereau').addClass('hidden').removeClass('flex');
}

window.lancerImpressionBordereau = function() {
    const num = $('#mod_prochain_num').val();
    const sigle = $('#mod_sigle').val();
    const dest = $('#service_destination').val();
    const filter = $('#filter_type_dos').val();
    const type_b = $('#select_type_bordereau').val();
    const civilite = $('#mod_civilite').val();

    if(!num || !sigle) {
        Swal.fire('Champs requis', 'Le numéro et le sigle sont obligatoires.', 'info');
        return;
    }
    closeModalBordereau();
    const url = `documents/bordereaux/generate_bordereau.php?num=${num}&sigle=${encodeURIComponent(sigle)}&dest=${dest}&filter=${filter}&type_bordereau=${type_b}&civilite=${civilite}`;
    window.open(url, '_blank');
}