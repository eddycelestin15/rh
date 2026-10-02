-- =====================================================================
--  ENVIRONNEMENT DE DÉVELOPPEMENT UNIQUEMENT
--  Réinitialise le mot de passe de tous les comptes à « test1234 »
--  afin de pouvoir tester les parcours de connexion en local.
--  Ce fichier n'est chargé que par docker-compose et ne doit JAMAIS
--  être exécuté sur la base de production.
-- =====================================================================
UPDATE utilisateurs SET mot_de_passe = '$2y$10$JfPW4Nlc6ii.Uyq7s5ThYOLeqRsEpmUC.yWHXXwGfhO5SWlGDGnOe';

-- Corrige un compte dont le type_compte est vide dans le dump
-- (il empêche toute redirection correcte après connexion).
UPDATE utilisateurs SET type_compte = 'agent'
 WHERE (type_compte IS NULL OR type_compte = '') AND role_specifique = 'agent';
