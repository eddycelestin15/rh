# -*- coding: utf-8 -*-
"""Carte de la nouvelle arborescence : fichier racine -> dossier cible."""

MAP = {
# ---------------------------------------------------------------- bootstrap
'includes': ['config.php', 'check_session.php'],

# ------------------------------------------------------------ authentification
'auth': ['login_admin.php', 'login_agent.php', 'login_responsable.php',
         'auth_process.php', 'logout.php', 'inscription.php', 'register_agent.php',
         'process_inscription.php', 'register_process.php', 'setup_admin.php'],

# ------------------------------------------------------------------- API JSON
'api/agents': ['api_get_agent_info.php', 'search_agent.php', 'get_data.php',
               'fetch_data_agent.php', 'get_historique_agent.php',
               'update_agent_status.php', 'get_dossiers_agent.php',
               'fetch_liste_data.php', 'fetch_stats.php'],
'api/carriere': ['api_avancement.php', 'api_affectation.php', 'get_avancements.php',
                 'get_avenant_data.php', 'get_avenant_details.php',
                 'get_grade_id_by_name.php', 'get_grade_titularisation.php',
                 'get_indice.php', 'get_indice_by_libelle.php',
                 'api_mandatement.php'],
'api/dossiers': ['api_demande_dos.php', 'api_traitement.php', 'api_reference.php',
                 'api_get_references.php', 'get_references.php',
                 'gerer_considerants_arrete.php', 'gerer_considerants_decision.php'],
'api/bordereaux': ['api_bordereaux.php', 'api_agents_bordereau.php',
                   'fetch_agents_bordereau.php', 'get_last_bordereau.php',
                   'api_suivi.php'],
'api/allocation': ['api_allocation.php', 'api_update_enfant.php', 'get_region_dcf.php'],
'api/notifications': ['get_notifications.php', 'get_unread_notifs.php',
                      'mark_notif_read.php', 'mark_read.php',
                      'mark_read_notification.php'],
'api/messagerie': ['fetch_message.php', 'delete_message.php', 'send_message.php'],
'api/referentiel': ['get_grades.php', 'get_grades_par_corps.php', 'get_loc.php'],

# ------------------------------------------------------- actions (ecritures)
'actions/personnel': ['save_step.php', 'upload_photo.php', 'save_affectation.php',
                      'save_step_affectation.php', 'update_affectation.php',
                      'delete_affectation.php', 'update_dcf_data.php',
                      'import_donnees.php', 'import_fpe.php'],
'actions/carriere': ['save_avancement.php', 'save_step_avancement.php',
                     'update_avancement.php', 'delete_avancement.php',
                     'save_mandatement.php', 'save_certificat_data.php'],
'actions/dossiers': ['save_dos.php', 'save_suivi_ref.php', 'update_envoi_dren.php',
                     'mark_as_printed.php', 'delete_item.php'],
'actions/conges': ['save_conge.php', 'delete_conge.php'],
'actions/compte': ['save_responsable.php', 'update_profil.php',
                   'update_profil_action.php', 'update_password_action.php'],

# ------------------------------------------------------- pages et fragments
'pages/dashboards': ['dashboard.php', 'dashboard_admin.php', 'dashboard_agent.php',
                     'dashboard_principal.php', 'dashboard_responsable.php'],
'pages/personnel': ['etat_civil.php', 'poste_actuel.php', 'situation_admin.php',
                    'diplomes.php', 'affectations.php', 'avancements.php',
                    'allocation_familiale.php', 'renseignements.php',
                    'liste_personnel.php', 'historique_distinction.php',
                    'formulaire.php'],
'pages/dossiers': ['dossiers_en_cours.php', 'suivi_dossiers.php',
                   'traitement_dossiers.php', 'envoi_dossiers.php',
                   'reference_dossiers.php', 'liste_demandes_dos.php',
                   'liste_historique_dos.php', 'liste_agents_bordereau.php',
                   'bordereau_envoi.php', 'mandatement.php',
                   'formulaire_demande.php', 'demande_avenant.php',
                   'imprimer_projet.php', 'generate_tableau.php'],
'pages/conges': ['demande_conge.php', 'prendre_conge.php', 'historique_conge.php'],
'pages/profil': ['profil.php', 'profil_password.php', 'profil_photo.php',
                 'documents.php'],
'pages/notifications': ['notifications.php', 'archives_notifs.php',
                        'detail_notification.php', 'messagerie.php'],
'pages/admin': ['creer_responsable.php'],

# ----------------------------------------------------- generateurs de documents
'documents/actes': ['generate_acte_formate.php', 'generate_acte_allocation.php',
                    'generate_reste_acte_allocation.php',
                    'generate_acte_rappel_solde.php',
                    'generate_acte_rappel_differentiel_moins_perçu.php',
                    'generate_allocation.php', 'generate_certificat_non_paiement.php'],
'documents/carriere': ['generate_avancement.php', 'generate_integration.php',
                       'generate_titularisation.php', 'generate_accessoires.php',
                       'generate_RNC1.php', 'generate_RNC2.php', 'generate_bin.php'],
'documents/conges': ['generate_conge.php'],
'documents/bordereaux': ['generate_bordereau.php'],
'documents/mandatement': ['generate_reste_mandatement.php',
                          'generate_reste_mandatement_rappel_solde.php'],
'documents': ['export_word_logic.php'],

# ------------------------------------------------------------------- planifie
'cron': ['cron_notifications.php', 'check_notifications.php'],
}

# Restent a la racine (point d'entree unique)
KEEP_AT_ROOT = ['index.php']
