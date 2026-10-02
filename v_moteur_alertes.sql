CREATE OR REPLACE VIEW v_moteur_alertes AS
WITH RECURSIVE progression_carriere AS (
    -- 1. ANCRAGE
    SELECT 
        psa.im,
        psa.statut_actuel,
        psa.categorie_actuel,
        psa.type_acte_actuel,
        rc.id AS corps_id,
        rg.id AS grade_id_actuel,
        rg.libelle_grade AS grade_nom_actuel,
        rg.modele_id,
        CASE 
            WHEN psa.type_avancement_actuel = 'Intégration'
                AND psa.categorie_actuel IN ('IV', 'V', 'VI', 'VIII')
            THEN 
                COALESCE(
                    (
                        SELECT pa_prev.av_date_effet
                        FROM personnel_avancements pa_prev
                        WHERE pa_prev.im = psa.im
                        AND pa_prev.av_date_effet < (
                            SELECT pa_intg.av_date_effet
                            FROM personnel_avancements pa_intg
                            WHERE pa_intg.im = psa.im
                                AND pa_intg.av_type_avancement = 'Intégration'
                            ORDER BY pa_intg.av_date_effet DESC, pa_intg.id DESC
                            LIMIT 1
                        )
                        ORDER BY pa_prev.av_date_effet DESC, pa_prev.id DESC
                        LIMIT 1
                    ), 
                    psa.date_d_effet_actuel
                )
            ELSE psa.date_d_effet_actuel 
        END AS date_effet_etape,
        rg.duree_annees AS duree_pour_prochain_palier,
        1 AS palier_niveau
    FROM personnel_situation_actuelle psa
    JOIN ref_grades_types rg ON psa.grade_actuel = rg.libelle_grade
    JOIN ref_corps rc ON psa.corps_actuel = rc.libelle_corps AND rc.modele_id = rg.modele_id
    WHERE 
    (psa.statut_actuel = 'Contractuel EFA' AND LEFT(psa.code_corps_actuel, 1) = 'U')
    OR (psa.statut_actuel = 'Fonctionnaire' AND LEFT(psa.code_corps_actuel, 1) IN ('A', 'B', 'C'))

    UNION ALL

    -- 2. RECURSION
    SELECT 
        pc.im, pc.statut_actuel, pc.categorie_actuel, pc.type_acte_actuel, pc.corps_id,
        rg_next.id, rg_next.libelle_grade, rg_next.modele_id,
        DATE_ADD(pc.date_effet_etape, INTERVAL (
            CASE 
                WHEN pc.statut_actuel = 'Contractuel EFA' 
                 AND pc.grade_nom_actuel = '2°CLASSE/2°ECHELON' 
                 AND pc.categorie_actuel IN ('IV', 'V', 'VI', 'VIII')
                 AND LOWER(pc.type_acte_actuel) = 'contrat'
                THEN 1  
                
                WHEN pc.statut_actuel = 'Contractuel EFA' 
                 AND pc.grade_nom_actuel = '2°CLASSE/2°ECHELON' 
                 AND pc.categorie_actuel IN ('IV', 'V', 'VI', 'VIII')
                 AND LOWER(pc.type_acte_actuel) = 'avenant'
                THEN 2  
                
                ELSE pc.duree_pour_prochain_palier 
            END
        ) YEAR),
        rg_next.duree_annees, 
        pc.palier_niveau + 1
    FROM progression_carriere pc
    JOIN ref_grades_types rg_next ON rg_next.modele_id = pc.modele_id AND rg_next.id = pc.grade_id_actuel + 1
    WHERE DATE_ADD(pc.date_effet_etape, INTERVAL (
            CASE 
                WHEN pc.statut_actuel = 'Contractuel EFA' 
                 AND pc.grade_nom_actuel = '2°CLASSE/2°ECHELON' 
                 AND pc.categorie_actuel IN ('IV', 'V', 'VI', 'VIII')
                 AND LOWER(pc.type_acte_actuel) = 'contrat'
                THEN 1
                WHEN pc.statut_actuel = 'Contractuel EFA' 
                 AND pc.grade_nom_actuel = '2°CLASSE/2°ECHELON' 
                 AND pc.categorie_actuel IN ('IV', 'V', 'VI', 'VIII')
                 AND LOWER(pc.type_acte_actuel) = 'avenant'
                THEN 2
                ELSE pc.duree_pour_prochain_palier 
            END
        ) YEAR) <= CURRENT_DATE
)

-- 3. AFFICHAGE DES ALERTES
SELECT alerte_id, im, titre, message, date_reception_technique, couleur_code, icone, type_key, nb_paliers_retard
FROM (
    SELECT 
        CONCAT(pc.im, '_AVANCEMENT_GROUPE') COLLATE utf8mb4_0900_ai_ci AS alerte_id, 
        pc.im,
        CONCAT(COUNT(*), CASE WHEN pc.statut_actuel = 'Contractuel EFA' THEN ' AVENANT(S) EN RETARD' ELSE ' AVANCEMENT(S) EN RETARD' END) COLLATE utf8mb4_0900_ai_ci AS titre,
        CONCAT(
            CASE WHEN COUNT(*) > 1 THEN "Vous êtes éligible aux avancements suivants : \n" ELSE "Vous êtes éligible à l'avancement suivant : \n " END, 
            GROUP_CONCAT(CONCAT('• ', pc.grade_nom_actuel, ' (depuis le ', DATE_FORMAT(pc.date_effet_etape, '%d/%m/%Y'), ')') SEPARATOR '\n'), 
            "\n Vous pouvez récupérer les pièces du dossier à constituer ici et passer au responsable de votre CISCO ou DREN pour finaliser la demande après."
        ) COLLATE utf8mb4_0900_ai_ci AS message,
        MIN(pc.date_effet_etape) AS date_reception_technique, 
        'sky' AS couleur_code,
        'fas fa-step-forward' AS icone,
        'ECHELON' COLLATE utf8mb4_0900_ai_ci AS type_key,
        COUNT(*) AS nb_paliers_retard
    FROM progression_carriere pc
    WHERE pc.palier_niveau > 1
    AND NOT (pc.statut_actuel IN ('Fonctionnaire') AND pc.grade_nom_actuel = '2°CLASSE/1°ECHELON' AND pc.palier_niveau = 2)
    GROUP BY pc.im, pc.statut_actuel
    HAVING COUNT(*) > 0

    UNION ALL

    SELECT 
        CONCAT('HIDDEN_', pc.im, '_AVANCEMENT_', pc.palier_niveau) COLLATE utf8mb4_0900_ai_ci, 
        pc.im, pc.grade_nom_actuel, pc.grade_nom_actuel, 
        pc.date_effet_etape, 'slate', 'fas fa-eye-slash', 'ECHELON_DETAIL', 1
    FROM progression_carriere pc
    WHERE pc.palier_niveau > 1
    AND NOT (pc.statut_actuel IN ('Fonctionnaire') AND pc.grade_nom_actuel = '2°CLASSE/1°ECHELON' AND pc.palier_niveau = 2)

    UNION ALL

    SELECT 
        CONCAT(im, '_INTG') COLLATE utf8mb4_0900_ai_ci, im, 
        "Demande d'Intégration", 
        CONCAT("Vous êtes éligible à l'intégration depuis le ", DATE_FORMAT(DATE_ADD(date_entree_admin, INTERVAL 6 YEAR), '%d/%m/%Y'), ", Veuillez passer au responsable du personnel encadré pour constituer votre dossier."), 
        DATE_ADD(date_entree_admin, INTERVAL 6 YEAR), 'purple', 'fas fa-fingerprint', 'INTG', 1
    FROM personnel_situation_actuelle 
    WHERE statut_actuel = 'Contractuel EFA' AND LEFT(code_corps_actuel, 1) = 'U' AND DATE_ADD(date_entree_admin, INTERVAL 6 YEAR) <= CURRENT_DATE
    
    UNION ALL

    SELECT 
        CONCAT(im, '_TITU') COLLATE utf8mb4_0900_ai_ci, im, 
        'Demande de Titularisation', 
        CONCAT("Vous êtes éligible à la titularisation depuis le ", DATE_FORMAT(DATE_ADD(date_d_effet_actuel, INTERVAL 1 YEAR), '%d/%m/%Y'), ", Veuillez passer au responsable du personnel encadré pour constituer votre dossier."), 
        DATE_ADD(date_d_effet_actuel, INTERVAL 1 YEAR), 'indigo', 'fas fa-medal', 'TITU', 1
    FROM personnel_situation_actuelle 
    WHERE statut_actuel IN ('Fonctionnaire') AND grade_actuel = 'STAGIAIRE' AND DATE_ADD(date_d_effet_actuel, INTERVAL 1 YEAR) <= CURRENT_DATE

    UNION ALL

    SELECT 
        CONCAT(im, '_RNC1') COLLATE utf8mb4_0900_ai_ci, 
        im, 
        'Renouvellement du 1er contrat', 
        CASE 
            WHEN DATE_SUB(DATE_ADD(date_entree_admin, INTERVAL 24 MONTH), INTERVAL 1 DAY) <= CURRENT_DATE 
            THEN CONCAT('Votre 1er contrat a EXPIRÉ le ', DATE_FORMAT(DATE_SUB(DATE_ADD(date_entree_admin, INTERVAL 24 MONTH), INTERVAL 1 DAY), '%d/%m/%Y'), '. Veuillez passer au responsable du personnel non encadré pour constituer votre dossier de renouvellement de contrat.')
            ELSE CONCAT('Votre 1er contrat finit le ', DATE_FORMAT(DATE_SUB(DATE_ADD(date_entree_admin, INTERVAL 24 MONTH), INTERVAL 1 DAY), '%d/%m/%Y'), 
                        '. Il vous reste ', 
                        PERIOD_DIFF(EXTRACT(YEAR_MONTH FROM DATE_SUB(DATE_ADD(date_entree_admin, INTERVAL 24 MONTH), INTERVAL 1 DAY)), EXTRACT(YEAR_MONTH FROM CURRENT_DATE)) - (IF(DAY(CURRENT_DATE) > DAY(DATE_SUB(DATE_ADD(date_entree_admin, INTERVAL 24 MONTH), INTERVAL 1 DAY)), 1, 0)), 
                        ' mois et ',
                        MOD(DATEDIFF(DATE_SUB(DATE_ADD(date_entree_admin, INTERVAL 24 MONTH), INTERVAL 1 DAY), CURRENT_DATE), 30),
                        ' jours. Veuillez passer au responsable du personnel non encadré pour constituer votre dossier de renouvellement de contrat.')
        END AS message, 
        DATE_ADD(date_entree_admin, INTERVAL 12 MONTH) AS date_reception_technique,
        CASE WHEN DATE_SUB(DATE_ADD(date_entree_admin, INTERVAL 24 MONTH), INTERVAL 1 DAY) <= CURRENT_DATE THEN 'red' ELSE 'orange' END, 
        'fas fa-file-contract', 'CONTRAT', 1
    FROM personnel_situation_actuelle
    WHERE statut_actuel = 'Contractuel EFA' 
      AND LEFT(code_corps_actuel, 1) IN ('L', 'K', 'J') 
      AND date_d_effet_actuel < DATE_ADD(date_entree_admin, INTERVAL 24 MONTH)
      AND DATE_ADD(date_entree_admin, INTERVAL 12 MONTH) <= CURRENT_DATE 
      AND DATE_ADD(date_entree_admin, INTERVAL 36 MONTH) > CURRENT_DATE
    
    UNION ALL

    SELECT 
        CONCAT(im, '_RNC2') COLLATE utf8mb4_0900_ai_ci, 
        im, 
        'Renouvellement du 2ème contrat', 
        CASE 
            WHEN DATE_SUB(DATE_ADD(date_entree_admin, INTERVAL 48 MONTH), INTERVAL 1 DAY) <= CURRENT_DATE 
            THEN CONCAT('Votre 2ème contrat a EXPIRÉ le ', DATE_FORMAT(DATE_SUB(DATE_ADD(date_entree_admin, INTERVAL 48 MONTH), INTERVAL 1 DAY), '%d/%m/%Y'), '. Veuillez passer au responsable du personnel non encadré pour constituer votre dossier de renouvellement de contrat.')
            ELSE CONCAT('Votre 2ème contrat finit le ', DATE_FORMAT(DATE_SUB(DATE_ADD(date_entree_admin, INTERVAL 48 MONTH), INTERVAL 1 DAY), '%d/%m/%Y'), 
                        '. Il vous reste ', 
                        PERIOD_DIFF(EXTRACT(YEAR_MONTH FROM DATE_SUB(DATE_ADD(date_entree_admin, INTERVAL 48 MONTH), INTERVAL 1 DAY)), EXTRACT(YEAR_MONTH FROM CURRENT_DATE)) - (IF(DAY(CURRENT_DATE) > DAY(DATE_SUB(DATE_ADD(date_entree_admin, INTERVAL 48 MONTH), INTERVAL 1 DAY)), 1, 0)), 
                        ' mois et ',
                        MOD(DATEDIFF(DATE_SUB(DATE_ADD(date_entree_admin, INTERVAL 48 MONTH), INTERVAL 1 DAY), CURRENT_DATE), 30),
                        ' jours. Veuillez passer au responsable du personnel non encadré pour constituer votre dossier de renouvellement de contrat.')
        END AS message, 
        DATE_ADD(date_entree_admin, INTERVAL 36 MONTH) AS date_reception_technique,
        CASE WHEN DATE_SUB(DATE_ADD(date_entree_admin, INTERVAL 48 MONTH), INTERVAL 1 DAY) <= CURRENT_DATE THEN 'red' ELSE 'orange' END, 
        'fas fa-file-contract', 'CONTRAT', 1
    FROM personnel_situation_actuelle
    WHERE statut_actuel = 'Contractuel EFA' 
      AND LEFT(code_corps_actuel, 1) IN ('L', 'K', 'J') 
      AND date_d_effet_actuel < DATE_ADD(date_entree_admin, INTERVAL 48 MONTH)
      AND DATE_ADD(date_entree_admin, INTERVAL 36 MONTH) <= CURRENT_DATE  

    UNION ALL

    -- 1. Notification Admission à la retraite (Masquée si statut_finale = 'valide')
    SELECT 
        CONCAT(pec.im, '_ADMISSION_RETRAITE') COLLATE utf8mb4_0900_ai_ci AS id_alerte, 
        pec.im, 
        "Demande d'arrêté d'admission à la retraite" AS titre, 
        "Vous êtes à la veille de la retraite, vous pouvez demander l'arrêté d'admission à la retraite à partir d'aujourd'hui, veuillez passer au responsable de votre cisco pour soumettre la demande." AS message,
        DATE_SUB(DATE_ADD(pec.date_naiss, INTERVAL 60 YEAR), INTERVAL 1 YEAR) AS date_reception_technique, 
        CASE WHEN DATE_ADD(pec.date_naiss, INTERVAL 60 YEAR) <= CURRENT_DATE THEN 'red' ELSE 'orange' END AS couleur, 
        'fas fa-user-clock' AS icone, 
        'ADMISSION_RETRAITE' AS type_alerte, 
        1 AS statut
    FROM personnel_etat_civil pec
    WHERE DATE_ADD(pec.date_naiss, INTERVAL 60 YEAR) <= DATE_ADD(CURRENT_DATE, INTERVAL 1 YEAR)
      -- MODIFIE ICI : Masque la vue/l'alerte d'origine quand le dossier est validé
      AND NOT EXISTS (
          SELECT 1 FROM suivi_agents_bordereau s_chk 
          WHERE s_chk.im_agent = pec.im 
            AND s_chk.type_bordereau = 'admission_retraite' 
            AND s_chk.statut_finale = 'valide'
      )

    UNION ALL

    -- 2. Notification Indemnité Compensatrice
    SELECT 
        CONCAT(im, '_COMPENSATRICE') COLLATE utf8mb4_0900_ai_ci AS id_alerte, 
        im, 
        "Demande de décision de compensatrice" AS titre, 
        "Vous êtes élligible pour demander la decision d'octroi de compensatrice des congés non pris si vous avez l'arrêté d'amission à la retraite." AS message,
        DATE_SUB(DATE_ADD(date_naiss, INTERVAL 60 YEAR), INTERVAL 1 YEAR) AS date_reception_technique, 
        CASE WHEN DATE_ADD(date_naiss, INTERVAL 60 YEAR) <= CURRENT_DATE THEN 'red' ELSE 'orange' END AS couleur, 
        'fas fa-file-invoice-dollar' AS icone, 
        'COMPENSATRICE' AS type_alerte, 
        1 AS statut
    FROM personnel_etat_civil
    WHERE DATE_ADD(date_naiss, INTERVAL 60 YEAR) <= DATE_ADD(CURRENT_DATE, INTERVAL 1 YEAR)

    UNION ALL

    -- 3. Notification Indemnité d'Installation
    SELECT 
        CONCAT(im, '_INSTALLATION') COLLATE utf8mb4_0900_ai_ci AS id_alerte, 
        im, 
        "Demande de décision d'installation" AS titre, 
        "Vous êtes élligible de demander la decision d'installation lorsque vous obtendrez l'arrêté d'admission à la retraite." AS message,
        DATE_SUB(DATE_ADD(date_naiss, INTERVAL 60 YEAR), INTERVAL 1 YEAR) AS date_reception_technique, 
        CASE WHEN DATE_ADD(date_naiss, INTERVAL 60 YEAR) <= CURRENT_DATE THEN 'red' ELSE 'orange' END AS couleur, 
        'fas fa-truck-loading' AS icone, 
        'INSTALLATION' AS type_alerte, 
        1 AS statut
    FROM personnel_etat_civil
    WHERE DATE_ADD(date_naiss, INTERVAL 60 YEAR) <= DATE_ADD(CURRENT_DATE, INTERVAL 1 YEAR)

    UNION ALL

    SELECT 
        CONCAT(u.im, '_BORD_DREN_', b.id) COLLATE utf8mb4_0900_ai_ci AS id,
        u.im AS im,
        'Bordereau en attente de traitement' AS titre,
        CONCAT(
            'Le bordereau n° ', b.numero_complet, 
            ' du ', DATE_FORMAT(b.date_envoi_bordereau, '%d/%m/%Y'),
            ', transmis par le ', 
            CASE 
                WHEN b.type_etablissement = 'CISCO' THEN CONCAT('CISCO ', COALESCE(b.expediteur, ''))
                WHEN b.type_etablissement = 'CRFRP' THEN COALESCE(b.expediteur, '')
                ELSE COALESCE(b.expediteur, '')
            END,
            ', contient ',
            (CHAR_LENGTH(b.liste_agents) - CHAR_LENGTH(REPLACE(b.liste_agents, ',', '')) + 1),
            IF((CHAR_LENGTH(b.liste_agents) - CHAR_LENGTH(REPLACE(b.liste_agents, ',', '')) + 1) > 1, ' dossiers d’agents relatifs à une demande de création de projet ', ' dossier d’agent relatif à une demande de création de projet '),
            CASE b.type_demande
                WHEN 'renouvellement' THEN 'de renouvellement de contrat'
                WHEN 'avenant' THEN "d'avenant"
                WHEN 'avancement_classe' THEN "d'avancement de classe et d'echelon"
                WHEN 'avancement_echelon' THEN "d'avancement d'echelon"
                WHEN 'integration' THEN "d'intégration"
                WHEN 'titularisation' THEN 'de titularisation'
                WHEN 'admission_retraite' THEN "d'admission à la retraite"
                WHEN 'compensatrice' THEN "d'indemnité compensatrice"
                WHEN 'installation' THEN "d'indemnité d'installation"
                ELSE COALESCE(b.type_demande, '')
            END,
            '. Veuillez : \n • accéder au menu « Référence des dossiers » pour attribuer une référence au bordereau, \n • puis accéder au menu « Traitement des dossiers » afin de traiter ',
            IF((CHAR_LENGTH(b.liste_agents) - CHAR_LENGTH(REPLACE(b.liste_agents, ',', '')) + 1) > 1, 'les dossiers des agents concernés.', 'le dossier de l’agent concerné.')
        ) AS message,
        b.date_envoi_bordereau AS date_reception_technique,
        'orange' AS couleur,
        'fas fa-folder-open' AS icone,
        'BORDEREAU' AS categorie,
        1 AS statut
    FROM archives_bordereaux_dren b
    LEFT JOIN ref_crfrp c ON b.type_etablissement = 'CRFRP' AND c.nom_crfrp = b.expediteur
    LEFT JOIN ref_districts d_crfrp ON b.type_etablissement = 'CRFRP' AND d_crfrp.id = c.district_id
    LEFT JOIN ref_districts d_cisco ON b.type_etablissement = 'CISCO' AND d_cisco.nom_district = b.expediteur
    LEFT JOIN ref_regions r ON r.id = COALESCE(d_crfrp.region_id, d_cisco.region_id)
    INNER JOIN utilisateurs u ON u.niveau = 'regional' AND LOWER(u.code_lieu_affectation) = LOWER(r.nom_region)
      AND u.role_specifique = CASE 
          WHEN b.type_demande IN ('renouvellement', 'avenant', 'integration') THEN 'resp_non_encadre'
          WHEN b.type_demande IN ('titularisation', 'avancement_echelon', 'avancement_classe', 'admission_retraite') THEN 'resp_encadre'
          ELSE NULL
      END
    WHERE b.statut_bordereau = 'en_attente'

    UNION ALL

    SELECT 
        CONCAT(u.im, '_BORD_DRH_', b.id) COLLATE utf8mb4_0900_ai_ci AS id,
        u.im AS im,
        'Bordereau en attente de traitement (DRH)' AS titre,
        CONCAT(
            'Le bordereau n° ', b.numero_complet, 
            ' du ', DATE_FORMAT(b.date_envoi_bordereau, '%d/%m/%Y'),
            ', transmis par la DREN ', COALESCE(b.expediteur, 'DREN'),
            ', contient ',
            (CHAR_LENGTH(b.liste_agents) - CHAR_LENGTH(REPLACE(b.liste_agents, ',', '')) + 1),
            IF((CHAR_LENGTH(b.liste_agents) - CHAR_LENGTH(REPLACE(b.liste_agents, ',', '')) + 1) > 1, ' dossiers d’agents relatifs à une demande ', ' dossier d’agent relatif à une demande '),
            CASE b.type_demande
                WHEN 'integration' THEN "d'intégration"
                WHEN 'titularisation' THEN 'de titularisation'
                ELSE COALESCE(b.type_demande, '')
            END,
            '. Veuillez : \n • accéder au menu « Référence des dossiers » pour attribuer une référence au bordereau, \n • puis accéder au menu « Traitement des dossiers » afin de traiter ',
            IF((CHAR_LENGTH(b.liste_agents) - CHAR_LENGTH(REPLACE(b.liste_agents, ',', '')) + 1) > 1, 'les dossiers des agents concernés.', 'le dossier de l’agent concerné.')
        ) AS message,
        b.date_envoi_bordereau AS date_reception_technique,
        'purple' AS couleur,
        'fas fa-folder-open' AS icone,
        'BORDEREAU' AS categorie,
        1 AS statut
    FROM archives_bordereaux_drh b
    INNER JOIN utilisateurs u ON u.niveau = 'central'
      AND u.role_specifique = CASE 
          WHEN b.type_demande = 'integration' THEN 'resp_non_encadre'
          WHEN b.type_demande = 'titularisation' THEN 'resp_encadre'
          ELSE NULL
      END
    WHERE b.statut_bordereau = 'en_attente'
      AND b.type_demande IN ('integration', 'titularisation')

    -- =========================================================================
    -- GESTION CASCADE DES STEPS ET REJETS (MASQUAGE DU STEP PRÉCÉDENT)
    -- =========================================================================

    UNION ALL

    -- STEP DREN
    SELECT CONCAT(s.im_agent, '_STEP_DREN_', s.id) COLLATE utf8mb4_0900_ai_ci, s.im_agent, 'Dépôt de dossier à la DREN',
        CONCAT('Votre demande ', 
        CASE 
            WHEN s.type_bordereau = 'renouvellement' THEN 'de renouvellement de contrat' 
            WHEN s.type_bordereau = 'avenant' THEN "d'avenant" 
            WHEN s.type_bordereau = 'avancement_classe' THEN "d'avancement de classe et d'echelon" 
            WHEN s.type_bordereau = 'avancement_echelon' THEN "d'avancement d'echelon" 
            WHEN s.type_bordereau = 'integration' THEN "d'intégration" 
            WHEN s.type_bordereau = 'titularisation' THEN 'de titularisation' 
            WHEN s.type_bordereau = 'admission_retraite' THEN "d'admission à la retraite" 
            WHEN s.type_bordereau = 'compensatrice' THEN "d'indemnité compensatrice" 
            WHEN s.type_bordereau = 'installation' THEN "d'indemnité d'installation" 
            ELSE 'administrative' 
        END, ' est bien déposée à la DREN :', CASE WHEN COALESCE(s.bordereau_cisco_dren, s.bordereau_crfrp_dren) IS NOT NULL AND COALESCE(s.bordereau_cisco_dren, s.bordereau_crfrp_dren) <> '' THEN CONCAT(' \n - Bordereau N°', COALESCE(s.bordereau_cisco_dren, s.bordereau_crfrp_dren), ',') ELSE '' END, ' \n - Référence N°', s.ref_dren, '.'),
        CURRENT_TIMESTAMP, 'blue', 'fas fa-university', 'DOS_STEP', 1
    FROM suivi_agents_bordereau s 
    WHERE s.ref_dren IS NOT NULL AND s.ref_dren <> ''
      AND (s.statut_dren IS NULL OR s.statut_dren <> 'rejete')
      AND (s.ref_fop IS NULL OR s.ref_fop = '')
      AND (s.ref_drh IS NULL OR s.ref_drh = '')
      AND (s.ref_solde IS NULL OR s.ref_solde = '')
      AND (s.ref_cde IS NULL OR s.ref_cde = '')
      AND (s.ref_prefecture IS NULL OR s.ref_prefecture = '')
      AND (s.statut_finale IS NULL OR s.statut_finale <> 'valide')

    UNION ALL

    SELECT CONCAT(s.im_agent, '_REJET_DREN_', s.id) COLLATE utf8mb4_0900_ai_ci, s.im_agent, 'Dossier rejeté par la DREN',
        CONCAT('Désolé, votre demande ',
        CASE 
            WHEN s.type_bordereau = 'renouvellement' THEN 'de renouvellement de contrat' 
            WHEN s.type_bordereau = 'avenant' THEN "d'avenant" 
            WHEN s.type_bordereau = 'avancement_classe' THEN "d'avancement de classe et d'echelon" 
            WHEN s.type_bordereau = 'avancement_echelon' THEN "d'avancement d'echelon" 
            WHEN s.type_bordereau = 'integration' THEN "d'intégration" 
            WHEN s.type_bordereau = 'titularisation' THEN 'de titularisation' 
            WHEN s.type_bordereau = 'admission_retraite' THEN "d'admission à la retraite" 
            WHEN s.type_bordereau = 'compensatrice' THEN "d'indemnité compensatrice" 
            WHEN s.type_bordereau = 'installation' THEN "d'indemnité d'installation" 
            ELSE 'administrative' 
        END, ' a été rejetée par la DREN.\n Motif du rejet : ', s.motif_dren, '.\n Veuillez régulariser votre dossier.'),
        CURRENT_TIMESTAMP, 'red', 'fas fa-times-circle', 'DOS_REJET', 1
    FROM suivi_agents_bordereau s WHERE s.statut_dren = 'rejete' AND s.motif_dren IS NOT NULL

    UNION ALL

    -- STEP DRH
    SELECT CONCAT(s.im_agent, '_STEP_DRH_', s.id) COLLATE utf8mb4_0900_ai_ci, s.im_agent, 'Dépôt de dossier à la DRH',
        CONCAT('Votre demande ', CASE WHEN s.type_bordereau = 'integration' THEN "d'intégration" WHEN s.type_bordereau = 'titularisation' THEN 'de titularisation' WHEN s.type_bordereau = 'admission_retraite' THEN "d'admission à la retraite" WHEN s.type_bordereau = 'installation' THEN "d'installation" ELSE 'administrative' END, ' est bien déposée à la Direction des Ressources Humaines :', CASE WHEN COALESCE(s.bordereau_dren_drh, s.bordereau_crfrp_drh) IS NOT NULL AND COALESCE(s.bordereau_dren_drh, s.bordereau_crfrp_drh) <> '' THEN CONCAT(' \n - Bordereau N°', COALESCE(s.bordereau_dren_drh, s.bordereau_crfrp_drh), ',') ELSE '' END, ' \n - Référence N°', s.ref_drh, '.'),
        CURRENT_TIMESTAMP, 'indigo', 'fas fa-file-export', 'DOS_STEP', 1
    FROM suivi_agents_bordereau s 
    WHERE s.ref_drh IS NOT NULL AND s.ref_drh <> '' 
      AND s.type_bordereau IN ('integration', 'titularisation', 'admission_retraite', 'installation')
      AND (s.statut_drh IS NULL OR s.statut_drh <> 'rejete')
      AND (s.ref_mtefop IS NULL OR s.ref_mtefop = '')
      AND (s.ref_primature IS NULL OR s.ref_primature = '')
      AND (s.statut_finale IS NULL OR s.statut_finale <> 'valide')

    UNION ALL

    SELECT CONCAT(s.im_agent, '_REJET_DRH_', s.id) COLLATE utf8mb4_0900_ai_ci, s.im_agent, 'Dossier rejeté par la DRH',
        CONCAT('Désolé, votre demande ', CASE WHEN s.type_bordereau = 'integration' THEN "d'intégration" WHEN s.type_bordereau = 'titularisation' THEN 'de titularisation' WHEN s.type_bordereau = 'admission_retraite' THEN "d'admission à la retraite" WHEN s.type_bordereau = 'installation' THEN "d'installation" ELSE 'administrative' END, ' a été rejetée par la Direction des Ressources Humaines.\n Motif du rejet : ', s.motif_drh, '.\n Veuillez régulariser votre dossier.'),
        CURRENT_TIMESTAMP, 'red', 'fas fa-times-circle', 'DOS_REJET', 1
    FROM suivi_agents_bordereau s WHERE s.statut_drh = 'rejete' AND s.motif_drh IS NOT NULL AND s.type_bordereau IN ('integration', 'titularisation')

    UNION ALL

    -- STEP FOP
    SELECT CONCAT(s.im_agent, '_STEP_FOP_', s.id) COLLATE utf8mb4_0900_ai_ci, s.im_agent, 'Dépôt de dossier à la Fonction publique',
        CONCAT('Votre demande ', CASE WHEN s.type_bordereau = 'avancement_classe' THEN "d'avancement de classe et d'echelon" WHEN s.type_bordereau = 'avancement_echelon' THEN "d'avancement d'echelon" WHEN s.type_bordereau = 'integration' THEN "d'intégration" WHEN s.type_bordereau = 'titularisation' THEN 'de titularisation' END, ' est déposée à la Fonction Publique,', CASE WHEN COALESCE(s.bordereau_dren_fop, s.bordereau_crfrp_fop, s.bordereau_drh_fop) IS NOT NULL AND COALESCE(s.bordereau_dren_fop, s.bordereau_crfrp_fop, s.bordereau_drh_fop) <> '' THEN CONCAT(' bordereau N°', COALESCE(s.bordereau_dren_fop, s.bordereau_crfrp_fop, s.bordereau_drh_fop), ',') ELSE '' END, ' Référence N°', s.ref_fop, '.'),
        CURRENT_TIMESTAMP, 'purple', 'fas fa-building', 'DOS_STEP', 1
    FROM suivi_agents_bordereau s 
    WHERE s.ref_fop IS NOT NULL AND s.ref_fop <> '' 
      AND s.type_bordereau IN ('avancement_classe', 'avancement_echelon', 'integration', 'titularisation')
      AND (s.statut_fop IS NULL OR s.statut_fop <> 'rejete')
      AND (s.ref_solde IS NULL OR s.ref_solde = '')
      AND (s.ref_cde IS NULL OR s.ref_cde = '')
      AND (s.statut_finale IS NULL OR s.statut_finale <> 'valide')

    UNION ALL

    SELECT CONCAT(s.im_agent, '_REJET_FOP_', s.id) COLLATE utf8mb4_0900_ai_ci, s.im_agent, 'Dossier rejeté par la Fonction Publique',
        CONCAT('Désolé, votre demande ', CASE WHEN s.type_bordereau = 'avancement_classe' THEN "d'avancement de classe et d'echelon" WHEN s.type_bordereau = 'avancement_echelon' THEN "d'avancement d'echelon" WHEN s.type_bordereau = 'integration' THEN "d'intégration" WHEN s.type_bordereau = 'titularisation' THEN 'de titularisation' ELSE 'administrative' END, ' a été rejetée par la FONCTION PUBLIQUE.\n Motif du rejet : ', s.motif_fop, '.\n Veuillez régulariser votre dossier.'),
        CURRENT_TIMESTAMP, 'red', 'fas fa-times-circle', 'DOS_REJET', 1
    FROM suivi_agents_bordereau s WHERE s.statut_fop = 'rejete' AND s.motif_fop IS NOT NULL

    UNION ALL

    -- STEP MTEFOP
    SELECT CONCAT(s.im_agent, '_STEP_MTEFOP_', s.id) COLLATE utf8mb4_0900_ai_ci, s.im_agent, 'Dépôt de dossier au MTEFOP',
        CONCAT('Votre demande ', CASE WHEN s.type_bordereau = 'integration' THEN "d'intégration" WHEN s.type_bordereau = 'titularisation' THEN 'de titularisation' WHEN s.type_bordereau = 'admission_retraite' THEN "d'admission à la retraite" WHEN s.type_bordereau = 'installation' THEN "d'installation" ELSE 'administrative' END, ' est bien déposée au MTEFOP :', CASE WHEN s.bordereau_drh_mtefop IS NOT NULL AND s.bordereau_drh_mtefop <> '' THEN CONCAT(' \n - Bordereau N°', s.bordereau_drh_mtefop, ',') ELSE '' END, ' \n - Référence N°', s.ref_mtefop, '.'),
        CURRENT_TIMESTAMP, 'purple', 'fas fa-building', 'DOS_STEP', 1
    FROM suivi_agents_bordereau s 
    WHERE s.ref_mtefop IS NOT NULL AND s.ref_mtefop <> '' 
      AND s.type_bordereau IN ('integration', 'titularisation', 'admission_retraite', 'installation')
      AND (s.statut_mtefop IS NULL OR s.statut_mtefop <> 'rejete')
      AND (s.ref_primature IS NULL OR s.ref_primature = '')
      AND (s.statut_finale IS NULL OR s.statut_finale <> 'valide')

    UNION ALL

    SELECT CONCAT(s.im_agent, '_REJET_MTEFOP_', s.id) COLLATE utf8mb4_0900_ai_ci, s.im_agent, 'Dossier rejeté par le MTEFOP',
        CONCAT('Désolé, votre demande ', CASE WHEN s.type_bordereau = 'integration' THEN "d'intégration" WHEN s.type_bordereau = 'titularisation' THEN 'de titularisation' WHEN s.type_bordereau = 'admission_retraite' THEN "d'admission à la retraite" WHEN s.type_bordereau = 'installation' THEN "d'installation" ELSE 'administrative' END, ' a été rejetée par le MTEFOP.\n Motif du rejet : ', s.motif_mtefop, '.\n Veuillez régulariser votre dossier.'),
        CURRENT_TIMESTAMP, 'red', 'fas fa-times-circle', 'DOS_REJET', 1
    FROM suivi_agents_bordereau s WHERE s.statut_mtefop = 'rejete' AND s.motif_mtefop IS NOT NULL AND s.type_bordereau IN ('integration', 'titularisation')

    UNION ALL

    -- STEP PRIMATURE
    SELECT CONCAT(s.im_agent, '_STEP_PRIMATURE_', s.id) COLLATE utf8mb4_0900_ai_ci, s.im_agent, 'Dépôt de dossier à la Primature',
        CONCAT('Votre demande ', CASE WHEN s.type_bordereau = 'integration' THEN "d'intégration" WHEN s.type_bordereau = 'titularisation' THEN 'de titularisation' WHEN s.type_bordereau = 'admission_retraite' THEN "d'admission à la retraite" WHEN s.type_bordereau = 'installation' THEN "d'installation" ELSE 'administrative' END, ' est bien déposée à la Primature :', CASE WHEN s.bordereau_drh_primature IS NOT NULL AND s.bordereau_drh_primature <> '' THEN CONCAT(' \n - Bordereau N°', s.bordereau_drh_primature, ',') ELSE '' END, ' \n - Référence N°', s.ref_primature, '.'),
        CURRENT_TIMESTAMP, 'amber', 'fas fa-landmark', 'DOS_STEP', 1
    FROM suivi_agents_bordereau s 
    WHERE s.ref_primature IS NOT NULL AND s.ref_primature <> '' 
      AND s.type_bordereau IN ('integration', 'titularisation', 'admission_retraite', 'installation')
      AND (s.statut_primature IS NULL OR s.statut_primature <> 'rejete')
      AND (s.statut_finale IS NULL OR s.statut_finale <> 'valide')

    UNION ALL

    SELECT CONCAT(s.im_agent, '_REJET_PRIMATURE_', s.id) COLLATE utf8mb4_0900_ai_ci, s.im_agent, 'Dossier rejeté par la Primature',
        CONCAT('Désolé, votre demande ', CASE WHEN s.type_bordereau = 'integration' THEN "d'intégration" WHEN s.type_bordereau = 'titularisation' THEN 'de titularisation' WHEN s.type_bordereau = 'admission_retraite' THEN "d'admission à la retraite" WHEN s.type_bordereau = 'installation' THEN "d'installation" ELSE 'administrative' END, ' a été rejetée par la Primature.\n Motif du rejet : ', s.motif_primature, '.\n Veuillez régulariser votre dossier.'),
        CURRENT_TIMESTAMP, 'red', 'fas fa-times-circle', 'DOS_REJET', 1
    FROM suivi_agents_bordereau s WHERE s.statut_primature = 'rejete' AND s.motif_primature IS NOT NULL AND s.type_bordereau IN ('integration', 'titularisation')

    UNION ALL

    -- STEP SOLDE
    SELECT CONCAT(s.im_agent, '_STEP_SOLDE_', s.id) COLLATE utf8mb4_0900_ai_ci, s.im_agent, 'Dépôt de dossier à la Solde et Pensions',
        CONCAT('Votre demande ', 
        CASE 
            WHEN s.type_bordereau = 'renouvellement' THEN 'de renouvellement de contrat' 
            WHEN s.type_bordereau = 'avenant' THEN "d'avenant" 
            WHEN s.type_bordereau = 'avancement_classe' THEN "d'avancement de classe et d'echelon" 
            WHEN s.type_bordereau = 'avancement_echelon' THEN "d'avancement d'echelon" 
            WHEN s.type_bordereau = 'integration' THEN "d'intégration" 
            WHEN s.type_bordereau = 'titularisation' THEN 'de titularisation' 
            WHEN s.type_bordereau = 'admission_retraite' THEN "d'admission à la retraite" 
            WHEN s.type_bordereau = 'compensatrice' THEN "d'indemnité compensatrice" 
            WHEN s.type_bordereau = 'installation' THEN "d'indemnité d'installation" 
            ELSE 'administrative' 
        END, ' est bien déposée au service solde et pensions pour demande de Visa :', CASE WHEN COALESCE(s.bordereau_dren_solde, s.bordereau_crfrp_solde, s.bordereau_drh_solde) IS NOT NULL AND COALESCE(s.bordereau_dren_solde, s.bordereau_crfrp_solde, s.bordereau_drh_solde) <> '' THEN CONCAT(' \n - Bordereau N°', COALESCE(s.bordereau_dren_solde, s.bordereau_crfrp_solde, s.bordereau_drh_solde), ',') ELSE '' END, ' \n - Référence N°', s.ref_solde, '.'),
        CURRENT_TIMESTAMP, 'cyan', 'fas fa-money-check-alt', 'DOS_STEP', 1
    FROM suivi_agents_bordereau s 
    WHERE s.ref_solde IS NOT NULL AND s.ref_solde <> ''
      AND (s.statut_solde IS NULL OR s.statut_solde <> 'rejete')
      AND (s.ref_cde IS NULL OR s.ref_cde = '')
      AND (s.ref_prefecture IS NULL OR s.ref_prefecture = '')
      AND (s.statut_finale IS NULL OR s.statut_finale <> 'valide')

    UNION ALL

    SELECT CONCAT(s.im_agent, '_REJET_SOLDE_', s.id) COLLATE utf8mb4_0900_ai_ci, s.im_agent, 'Dossier rejeté par la Solde et pensions',
        CONCAT('Désolé, votre demande ', 
        CASE 
            WHEN s.type_bordereau = 'renouvellement' THEN 'de renouvellement de contrat' 
            WHEN s.type_bordereau = 'avenant' THEN "d'avenant" 
            WHEN s.type_bordereau = 'avancement_classe' THEN "d'avancement de classe et d'echelon" 
            WHEN s.type_bordereau = 'avancement_echelon' THEN "d'avancement d'echelon" 
            WHEN s.type_bordereau = 'integration' THEN "d'intégration" 
            WHEN s.type_bordereau = 'titularisation' THEN 'de titularisation' 
            WHEN s.type_bordereau = 'admission_retraite' THEN "d'admission à la retraite" 
            WHEN s.type_bordereau = 'compensatrice' THEN "d'indemnité compensatrice" 
            WHEN s.type_bordereau = 'installation' THEN "d'indemnité d'installation" 
            ELSE 'administrative' 
        END, ' a été rejetée par le service solde et pensions.\n Motif du rejet : ', s.motif_solde, '.\n Veuillez régulariser votre dossier.'),
        CURRENT_TIMESTAMP, 'red', 'fas fa-times-circle', 'DOS_REJET', 1
    FROM suivi_agents_bordereau s WHERE s.statut_solde = 'rejete' AND s.motif_solde IS NOT NULL

    UNION ALL

    -- STEP CDE
    SELECT CONCAT(s.im_agent, '_STEP_CDE_', s.id) COLLATE utf8mb4_0900_ai_ci, s.im_agent, 'Dépôt de dossier à la Contrôle Financier',
        CONCAT('Votre demande ', 
        CASE 
            WHEN s.type_bordereau = 'renouvellement' THEN 'de renouvellement de contrat' 
            WHEN s.type_bordereau = 'avenant' THEN "d'avenant" 
            WHEN s.type_bordereau = 'avancement_classe' THEN "d'avancement de classe et d'echelon" 
            WHEN s.type_bordereau = 'avancement_echelon' THEN "d'avancement d'echelon" 
            WHEN s.type_bordereau = 'integration' THEN "d'intégration" 
            WHEN s.type_bordereau = 'titularisation' THEN 'de titularisation' 
            WHEN s.type_bordereau = 'admission_retraite' THEN "d'admission à la retraite" 
            WHEN s.type_bordereau = 'compensatrice' THEN "d'indemnité compensatrice" 
            WHEN s.type_bordereau = 'installation' THEN "d'indemnité d'installation" 
            ELSE 'administrative' 
        END, ' est bien déposée au contrôle financier pour demande de Visa :', CASE WHEN COALESCE(s.bordereau_dren_cde, s.bordereau_crfrp_cde, s.bordereau_drh_cde) IS NOT NULL AND COALESCE(s.bordereau_dren_cde, s.bordereau_crfrp_cde, s.bordereau_drh_cde) <> '' THEN CONCAT(' \n - Bordereau N°', COALESCE(s.bordereau_dren_cde, s.bordereau_crfrp_cde, s.bordereau_drh_cde), ',') ELSE '' END, ' \n - Référence N°', s.ref_cde, '.'),
        CURRENT_TIMESTAMP, 'indigo', 'fas fa-shield-check', 'DOS_STEP', 1
    FROM suivi_agents_bordereau s 
    WHERE s.ref_cde IS NOT NULL AND s.ref_cde <> ''
      AND (s.statut_cde IS NULL OR s.statut_cde <> 'rejete')
      AND (s.ref_prefecture IS NULL OR s.ref_prefecture = '')
      AND (s.statut_finale IS NULL OR s.statut_finale <> 'valide')

    UNION ALL

    SELECT CONCAT(s.im_agent, '_REJET_CDE_', s.id) COLLATE utf8mb4_0900_ai_ci, s.im_agent, 'Dossier rejeté par le Controle financier',
        CONCAT('Désolé, votre demande ', 
        CASE 
            WHEN s.type_bordereau = 'renouvellement' THEN 'de renouvellement de contrat' 
            WHEN s.type_bordereau = 'avenant' THEN "d'avenant" 
            WHEN s.type_bordereau = 'avancement_classe' THEN "d'avancement de classe et d'echelon" 
            WHEN s.type_bordereau = 'avancement_echelon' THEN "d'avancement d'echelon" 
            WHEN s.type_bordereau = 'integration' THEN "d'intégration" 
            WHEN s.type_bordereau = 'titularisation' THEN 'de titularisation' 
            WHEN s.type_bordereau = 'admission_retraite' THEN "d'admission à la retraite" 
            WHEN s.type_bordereau = 'compensatrice' THEN "d'indemnité compensatrice" 
            WHEN s.type_bordereau = 'installation' THEN "d'indemnité d'installation" 
            ELSE 'administrative' 
        END, ' a été rejetée par le controle financier.\n Motif du rejet : ', s.motif_cde, '.\n Veuillez régulariser votre dossier.'),
        CURRENT_TIMESTAMP, 'red', 'fas fa-times-circle', 'DOS_REJET', 1
    FROM suivi_agents_bordereau s WHERE s.statut_cde = 'rejete' AND s.motif_cde IS NOT NULL

    UNION ALL

    -- STEP PREFET
    SELECT CONCAT(s.im_agent, '_STEP_PREFET_', s.id) COLLATE utf8mb4_0900_ai_ci, s.im_agent, 'Dépôt de dossier à la Préfecture',
        CONCAT('Votre demande ', CASE WHEN s.type_bordereau = 'renouvellement' THEN 'de renouvellement de contrat' WHEN s.type_bordereau = 'avenant' THEN "d'avenant" WHEN s.type_bordereau = 'avancement_classe' THEN "d'avancement de classe et d'echelon" WHEN s.type_bordereau = 'avancement_echelon' THEN "d'avancement d'echelon" WHEN s.type_bordereau = 'compensatrice' THEN "de compensatrice" ELSE 'administrative' END, ' est bien déposée à la préfecture pour demande signature et validation finale de votre demande :', CASE WHEN COALESCE(s.bordereau_dren_prefet, s.bordereau_crfrp_prefet) IS NOT NULL AND COALESCE(s.bordereau_dren_prefet, s.bordereau_crfrp_prefet) <> '' THEN CONCAT(' \n - Bordereau N°', COALESCE(s.bordereau_dren_prefet, s.bordereau_crfrp_prefet), ',') ELSE '' END, ' \n - Référence N°', s.ref_prefecture, '.'),
        CURRENT_TIMESTAMP, 'orange', 'fas fa-stamp', 'DOS_STEP', 1
    FROM suivi_agents_bordereau s 
    WHERE s.ref_prefecture IS NOT NULL AND s.ref_prefecture <> ''
      AND (s.statut_prefet IS NULL OR s.statut_prefet <> 'rejete')
      AND (s.statut_finale IS NULL OR s.statut_finale <> 'valide')

    UNION ALL

    SELECT CONCAT(s.im_agent, '_REJET_PREFET_', s.id) COLLATE utf8mb4_0900_ai_ci, s.im_agent, 'Dossier rejeté par la Prefecture',
        CONCAT('Désolé, votre demande ', CASE WHEN s.type_bordereau = 'renouvellement' THEN 'de renouvellement de contrat' WHEN s.type_bordereau = 'avenant' THEN "d'avenant" WHEN s.type_bordereau = 'avancement_classe' THEN "d'avancement de classe et d'echelon" WHEN s.type_bordereau = 'avancement_echelon' THEN "d me d'avancement d'echelon" WHEN s.type_bordereau = 'compensatrice' THEN "de compensatrice" ELSE 'administrative' END, ' a été rejetée par la Préfecture.\n Motif du rejet : ', s.motif_prefet, '.\n Veuillez régulariser votre dossier.'),
        CURRENT_TIMESTAMP, 'red', 'fas fa-times-circle', 'DOS_REJET', 1
    FROM suivi_agents_bordereau s WHERE s.statut_prefet = 'rejete' AND s.motif_prefet IS NOT NULL

    UNION ALL

    -- SORTIE DE DOSSIER (Conservée pour afficher l'état final validé)
    SELECT CONCAT(s.im_agent, '_STEP_SORTIE_ACTE_', s.id) COLLATE utf8mb4_0900_ai_ci AS alerte_id, 
        s.im_agent AS im, 
        'Dossier Terminé' AS titre,
        CAST(CONCAT('Votre demande ', CASE WHEN s.type_bordereau = 'renouvellement' THEN 'de renouvellement de contrat' WHEN s.type_bordereau = 'avenant' THEN "d'avenant" WHEN s.type_bordereau = 'avancement_classe' THEN "d'avancement de classe et d'echelon" WHEN s.type_bordereau = 'avancement_echelon' THEN "d'avancement d'echelon" WHEN s.type_bordereau = 'integration' THEN "d'intégration" WHEN s.type_bordereau = 'titularisation' THEN 'de titularisation'  WHEN s.type_bordereau = 'admission_retraite' THEN "d'admission à la retraite" WHEN s.type_bordereau = 'compensatrice' THEN "de compensatrice" WHEN s.type_bordereau = 'installation' THEN "d'installation" ELSE 'administrative' END, ' est terminée avec succès. Vous pouvez recupérer votre acte ', CASE WHEN psa.statut_actuel = 'Contractuel EFA' THEN 'au responsable du personnel non encadré au CISCO(ou à la DREN)' WHEN psa.statut_actuel = 'Fonctionnaire' THEN 'au responsable du personnel encadré au CISCO (ou à la DREN)' ELSE 'au service RH' END, ' et mandater après.') AS CHAR) AS message,
        CURRENT_TIMESTAMP AS date_reception_technique, 
        'green' AS couleur_code, 
        'fas fa-check-double' AS icone, 
        'DOS_STEP' AS type_key, 
        1 AS nb_paliers_retard
    FROM suivi_agents_bordereau s
    LEFT JOIN personnel_situation_actuelle psa ON s.im_agent = psa.im
    WHERE s.statut_finale = 'valide'

    UNION ALL

    -- MANDATEMENT
    SELECT 
        CONCAT(a.im, '_STEP_MANDATEMENT_', a.id) COLLATE utf8mb4_0900_ai_ci AS alerte_id, 
        a.im, 
        'Dépôt de mandatement à la Solde et Pensions' AS titre,
        CONCAT('Votre mandatement de la demande ', CASE WHEN a.type_demande = 'renouvellement' THEN 'de renouvellement de contrat' WHEN a.type_demande = 'avenant' THEN "d'avenant" WHEN a.type_demande = 'avancement_classe' THEN "d'avancement de classe et d'échelon" WHEN a.type_demande = 'avancement_echelon' THEN "d'avancement d'échelon" WHEN a.type_demande = 'integration' THEN "d'intégration" WHEN a.type_demande = 'titularisation' THEN 'de titularisation' WHEN a.type_demande = 'compensatrice' THEN "de compensatrice" WHEN a.type_demande = 'installation' THEN "d'installation" ELSE COALESCE(a.type_demande, 'administrative') END, ' est déposé au service solde et pensions :  \n - Bordereau N°', a.bordereaux_mandatement, ', \n - Référence N°', a.ref_mandatement, ' du ', DATE_FORMAT(a.date_reference_bordereau, '%d/%m/%Y'), '.') AS message,
        CURRENT_TIMESTAMP AS date_reception_technique, 
        'teal' AS couleur_code, 
        'fas fa-file-invoice-dollar' AS icone, 
        'DOS_STEP' AS type_key, 
        1 AS nb_paliers_retard
    FROM acte_formate a 
    LEFT JOIN personnel_situation_actuelle psa ON a.im = psa.im
    LEFT JOIN personnel_etat_civil pec ON a.im = pec.im
    WHERE a.ref_mandatement IS NOT NULL 
      AND a.ref_mandatement <> '' 
      AND a.bordereaux_mandatement IS NOT NULL 
      AND a.bordereaux_mandatement <> ''

      AND NOT EXISTS (
          SELECT 1 FROM progression_carriere pc_chk 
          WHERE pc_chk.im = a.im AND pc_chk.palier_niveau > 1
      )

      AND NOT (
          psa.statut_actuel = 'Contractuel EFA' 
          AND LEFT(psa.code_corps_actuel, 1) IN ('L', 'K', 'J') 
          AND psa.date_d_effet_actuel < DATE_ADD(psa.date_entree_admin, INTERVAL 24 MONTH)
          AND DATE_ADD(psa.date_entree_admin, INTERVAL 12 MONTH) <= CURRENT_DATE 
          AND DATE_ADD(psa.date_entree_admin, INTERVAL 36 MONTH) > CURRENT_DATE
      )

      AND NOT (
          psa.statut_actuel = 'Contractuel EFA' 
          AND LEFT(psa.code_corps_actuel, 1) IN ('L', 'K', 'J') 
          AND psa.date_d_effet_actuel < DATE_ADD(psa.date_entree_admin, INTERVAL 48 MONTH)
          AND DATE_ADD(psa.date_entree_admin, INTERVAL 36 MONTH) <= CURRENT_DATE
      )

      AND NOT (
          psa.statut_actuel = 'Contractuel EFA' 
          AND LEFT(psa.code_corps_actuel, 1) = 'U' 
          AND DATE_ADD(psa.date_entree_admin, INTERVAL 6 YEAR) <= CURRENT_DATE
      )

      AND NOT (
          psa.statut_actuel IN ('Fonctionnaire') 
          AND psa.grade_actuel = 'STAGIAIRE' 
          AND DATE_ADD(psa.date_d_effet_actuel, INTERVAL 1 YEAR) <= CURRENT_DATE
      )

      -- MODIFIE ICI : Masque l'alerte de mandatement si le dossier retraite est validé
      AND NOT EXISTS (
          SELECT 1 FROM suivi_agents_bordereau s_retraite
          WHERE s_retraite.im_agent = a.im
            AND s_retraite.type_bordereau = 'admission_retraite'
            AND s_retraite.statut_finale = 'valide'
      )
) AS alertes_finales;