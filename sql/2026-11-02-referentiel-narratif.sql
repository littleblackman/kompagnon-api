-- =====================================================================
-- Kompagnon — remise en ordre du référentiel narratif
-- À lancer dans l'ordre. Chaque mise à jour est protégée : relancer le
-- script ne change rien une seconde fois. Base : MariaDB 10.2+ / MySQL 5.7+
-- =====================================================================

-- ---------------------------------------------------------------------
-- 0. DIAGNOSTIC (lecture seule) — à regarder avant l'étape 1
-- ---------------------------------------------------------------------
SELECT VERSION();
-- Clé primaire de narrative_part : si la requête ne renvoie aucune ligne,
-- faire l'étape 1A ; si elle renvoie une ligne PRIMARY, sauter 1A.
SHOW KEYS FROM narrative_part WHERE Key_name = 'PRIMARY';
-- Doivent tous valoir 0, sinon ne pas lancer l'étape 1 et me prévenir
SELECT COUNT(*) - COUNT(DISTINCT id) AS ids_en_double, COUNT(*) - COUNT(DISTINCT code) AS codes_en_double FROM narrative_part;
SELECT COUNT(*) AS beats_orphelins FROM event_type et WHERE et.narrative_part_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM narrative_part np WHERE np.id = et.narrative_part_id);
-- Sous-genres sans beat (29 attendus en local)
SELECT g.name AS genre, s.name AS sous_genre FROM subgenre s JOIN genre g ON g.id = s.genre_id WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id) ORDER BY g.name, s.name;

-- ---------------------------------------------------------------------
-- 1. SCHÉMA de narrative_part (sans quoi l'admin ne peut pas créer de segment)
-- ---------------------------------------------------------------------
-- 1A. Seulement si le diagnostic n'a trouvé aucune clé PRIMARY
ALTER TABLE narrative_part CHANGE id id INT AUTO_INCREMENT NOT NULL, ADD PRIMARY KEY (id);
-- 1B. Si une clé PRIMARY existait déjà, lancer à la place de 1A :
-- ALTER TABLE narrative_part CHANGE id id INT AUTO_INCREMENT NOT NULL;

-- 1C. Alignement sur l'entité (code unique, description facultative)
--     Si l'index existe déjà, MariaDB répond « Duplicate key name » : c'est sans gravité, continuer.
ALTER TABLE narrative_part CHANGE code code VARCHAR(50) NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL;
CREATE UNIQUE INDEX UNIQ_72E09ED377153098 ON narrative_part (code);
-- 1D. Lien beat → segment : l'index sous le nom attendu par Doctrine, puis la contrainte.
--     Si l'un ou l'autre existe déjà : « Duplicate key », continuer.
CREATE INDEX IDX_93151B82A6460BC7 ON event_type (narrative_part_id);
ALTER TABLE event_type ADD CONSTRAINT FK_93151B82A6460BC7 FOREIGN KEY (narrative_part_id) REFERENCES narrative_part (id) ON DELETE SET NULL;

-- ---------------------------------------------------------------------
-- 2 à 4. DONNÉES, dans une transaction
-- ---------------------------------------------------------------------
START TRANSACTION;

-- 2. Segments cités par des structures mais absents du référentiel
INSERT INTO narrative_part (code, name, description) SELECT 'REVELATION', 'Révélation', 'Une vérité cachée se dévoile et change la compréhension du récit.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM narrative_part WHERE code = 'REVELATION');
INSERT INTO narrative_part (code, name, description) SELECT 'RETURN', 'Retour', 'Retour au monde initial, ou à un nouvel état du monde, après l''épreuve.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM narrative_part WHERE code = 'RETURN');
INSERT INTO narrative_part (code, name, description) SELECT 'TRANSFORMATION', 'Transformation', 'Changement profond du protagoniste ou du monde à l''issue du récit.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM narrative_part WHERE code = 'TRANSFORMATION');

-- 3. Ordre des sections. Garde : la structure doit avoir exactement les mêmes
--    sections et segments (à l'ordre près) ; une structure modifiée depuis
--    l'admin n'est pas touchée.
UPDATE narrative_structure SET narrative_part_order = '{"Montée":["DISRUPTION","ESCALATION"],"Bascule":["TURN"],"Chute":["CRISIS"],"Climax":["CLIMAX"],"Résolution":["RESOLUTION"]}' WHERE name = 'Fichtean Curve' AND JSON_LENGTH(narrative_part_order) = 5 AND JSON_CONTAINS(narrative_part_order, '{"Montée":["DISRUPTION","ESCALATION"],"Bascule":["TURN"],"Chute":["CRISIS"],"Climax":["CLIMAX"],"Résolution":["RESOLUTION"]}') AND JSON_CONTAINS('{"Montée":["DISRUPTION","ESCALATION"],"Bascule":["TURN"],"Chute":["CRISIS"],"Climax":["CLIMAX"],"Résolution":["RESOLUTION"]}', narrative_part_order);
UPDATE narrative_structure SET narrative_part_order = '{"Départ":["SETUP"],"Perturbation":["DISRUPTION"],"Engagement":["COMMIT"],"Développement":["ESCALATION"],"Crise":["CRISIS"],"Fin":["RESOLUTION"]}' WHERE name = 'Story Spine Pixar' AND JSON_LENGTH(narrative_part_order) = 6 AND JSON_CONTAINS(narrative_part_order, '{"Départ":["SETUP"],"Perturbation":["DISRUPTION"],"Engagement":["COMMIT"],"Développement":["ESCALATION"],"Crise":["CRISIS"],"Fin":["RESOLUTION"]}') AND JSON_CONTAINS('{"Départ":["SETUP"],"Perturbation":["DISRUPTION"],"Engagement":["COMMIT"],"Développement":["ESCALATION"],"Crise":["CRISIS"],"Fin":["RESOLUTION"]}', narrative_part_order);
UPDATE narrative_structure SET narrative_part_order = '{"Mise en place":["DISRUPTION"],"Tension":["ESCALATION","TURN"],"Rupture":["CRISIS"],"Choix":["CRISIS"],"Réunion":["CLIMAX","RESOLUTION"]}' WHERE name = 'Structure Romance' AND JSON_LENGTH(narrative_part_order) = 5 AND JSON_CONTAINS(narrative_part_order, '{"Mise en place":["DISRUPTION"],"Tension":["ESCALATION","TURN"],"Rupture":["CRISIS"],"Choix":["CRISIS"],"Réunion":["CLIMAX","RESOLUTION"]}') AND JSON_CONTAINS('{"Mise en place":["DISRUPTION"],"Tension":["ESCALATION","TURN"],"Rupture":["CRISIS"],"Choix":["CRISIS"],"Réunion":["CLIMAX","RESOLUTION"]}', narrative_part_order);
UPDATE narrative_structure SET narrative_part_order = '{"Crime":["DISRUPTION"],"Enquête":["ESCALATION"],"Fausse piste":["TURN"],"Révélation":["REVELATION"],"Confrontation":["CLIMAX"],"Résolution":["RESOLUTION"]}' WHERE name = 'Structure Mystère' AND JSON_LENGTH(narrative_part_order) = 6 AND JSON_CONTAINS(narrative_part_order, '{"Crime":["DISRUPTION"],"Enquête":["ESCALATION"],"Fausse piste":["TURN"],"Révélation":["REVELATION"],"Confrontation":["CLIMAX"],"Résolution":["RESOLUTION"]}') AND JSON_CONTAINS('{"Crime":["DISRUPTION"],"Enquête":["ESCALATION"],"Fausse piste":["TURN"],"Révélation":["REVELATION"],"Confrontation":["CLIMAX"],"Résolution":["RESOLUTION"]}', narrative_part_order);
UPDATE narrative_structure SET narrative_part_order = '{"Menace":["DISRUPTION"],"Course":["ESCALATION"],"Renversement":["TURN"],"Tout semble perdu":["CRISIS"],"Final":["CLIMAX","RESOLUTION"]}' WHERE name = 'Structure Thriller' AND JSON_LENGTH(narrative_part_order) = 5 AND JSON_CONTAINS(narrative_part_order, '{"Menace":["DISRUPTION"],"Course":["ESCALATION"],"Renversement":["TURN"],"Tout semble perdu":["CRISIS"],"Final":["CLIMAX","RESOLUTION"]}') AND JSON_CONTAINS('{"Menace":["DISRUPTION"],"Course":["ESCALATION"],"Renversement":["TURN"],"Tout semble perdu":["CRISIS"],"Final":["CLIMAX","RESOLUTION"]}', narrative_part_order);
UPDATE narrative_structure SET narrative_part_order = '{"Calme":["SETUP"],"Anomalie":["DISRUPTION"],"Terreur":["ESCALATION"],"Abîme":["CRISIS"],"Final":["CLIMAX"],"Après":["RESOLUTION"]}' WHERE name = 'Structure Horreur' AND JSON_LENGTH(narrative_part_order) = 6 AND JSON_CONTAINS(narrative_part_order, '{"Calme":["SETUP"],"Anomalie":["DISRUPTION"],"Terreur":["ESCALATION"],"Abîme":["CRISIS"],"Final":["CLIMAX"],"Après":["RESOLUTION"]}') AND JSON_CONTAINS('{"Calme":["SETUP"],"Anomalie":["DISRUPTION"],"Terreur":["ESCALATION"],"Abîme":["CRISIS"],"Final":["CLIMAX"],"Après":["RESOLUTION"]}', narrative_part_order);
UPDATE narrative_structure SET narrative_part_order = '{"Ouverture":["CLIMAX"],"Retour en arrière":["DISRUPTION"],"Montée":["ESCALATION"],"Révélation":["REVELATION"],"Fin":["RESOLUTION"]}' WHERE name = 'In Medias Res' AND JSON_LENGTH(narrative_part_order) = 5 AND JSON_CONTAINS(narrative_part_order, '{"Ouverture":["CLIMAX"],"Retour en arrière":["DISRUPTION"],"Montée":["ESCALATION"],"Révélation":["REVELATION"],"Fin":["RESOLUTION"]}') AND JSON_CONTAINS('{"Ouverture":["CLIMAX"],"Retour en arrière":["DISRUPTION"],"Montée":["ESCALATION"],"Révélation":["REVELATION"],"Fin":["RESOLUTION"]}', narrative_part_order);
UPDATE narrative_structure SET narrative_part_order = '{"Boucle 1":["SETUP","DISRUPTION"],"Pivot":["CLIMAX"],"Retour":["RETURN"],"Boucle 2":["SETUP"]}' WHERE name = 'Structure Circulaire' AND JSON_LENGTH(narrative_part_order) = 4 AND JSON_CONTAINS(narrative_part_order, '{"Boucle 1":["SETUP","DISRUPTION"],"Pivot":["CLIMAX"],"Retour":["RETURN"],"Boucle 2":["SETUP"]}') AND JSON_CONTAINS('{"Boucle 1":["SETUP","DISRUPTION"],"Pivot":["CLIMAX"],"Retour":["RETURN"],"Boucle 2":["SETUP"]}', narrative_part_order);
UPDATE narrative_structure SET narrative_part_order = '{"Tour 1":["SETUP"],"Tour 2":["ESCALATION"],"Tour 3":["TURN"],"Tour 4":["CRISIS"],"Tour 5":["CRISIS"],"Fin":["RESOLUTION"]}' WHERE name = 'Structure Spirale' AND JSON_LENGTH(narrative_part_order) = 6 AND JSON_CONTAINS(narrative_part_order, '{"Tour 1":["SETUP"],"Tour 2":["ESCALATION"],"Tour 3":["TURN"],"Tour 4":["CRISIS"],"Tour 5":["CRISIS"],"Fin":["RESOLUTION"]}') AND JSON_CONTAINS('{"Tour 1":["SETUP"],"Tour 2":["ESCALATION"],"Tour 3":["TURN"],"Tour 4":["CRISIS"],"Tour 5":["CRISIS"],"Fin":["RESOLUTION"]}', narrative_part_order);
UPDATE narrative_structure SET narrative_part_order = '{"Fragment 1":["DISRUPTION"],"Fragment 2":["ESCALATION"],"Fragment 3":["REVELATION"],"Assemblage":["RESOLUTION"]}' WHERE name = 'Structure Mosaïque' AND JSON_LENGTH(narrative_part_order) = 4 AND JSON_CONTAINS(narrative_part_order, '{"Fragment 1":["DISRUPTION"],"Fragment 2":["ESCALATION"],"Fragment 3":["REVELATION"],"Assemblage":["RESOLUTION"]}') AND JSON_CONTAINS('{"Fragment 1":["DISRUPTION"],"Fragment 2":["ESCALATION"],"Fragment 3":["REVELATION"],"Assemblage":["RESOLUTION"]}', narrative_part_order);
UPDATE narrative_structure SET narrative_part_order = '{"Ki":["SETUP"],"Shō":["ESCALATION"],"Ten":["TURN"],"Ketsu":["RESOLUTION"]}' WHERE name = 'Kishōtenketsu' AND JSON_LENGTH(narrative_part_order) = 4 AND JSON_CONTAINS(narrative_part_order, '{"Ki":["SETUP"],"Shō":["ESCALATION"],"Ten":["TURN"],"Ketsu":["RESOLUTION"]}') AND JSON_CONTAINS('{"Ki":["SETUP"],"Shō":["ESCALATION"],"Ten":["TURN"],"Ketsu":["RESOLUTION"]}', narrative_part_order);
UPDATE narrative_structure SET narrative_part_order = '{"Installation":["SETUP"],"Émotion":["ESCALATION"],"Chute":["CRISIS"],"Apaisement":["RESOLUTION"]}' WHERE name = 'Structure Rasa' AND JSON_LENGTH(narrative_part_order) = 4 AND JSON_CONTAINS(narrative_part_order, '{"Installation":["SETUP"],"Émotion":["ESCALATION"],"Chute":["CRISIS"],"Apaisement":["RESOLUTION"]}') AND JSON_CONTAINS('{"Installation":["SETUP"],"Émotion":["ESCALATION"],"Chute":["CRISIS"],"Apaisement":["RESOLUTION"]}', narrative_part_order);
UPDATE narrative_structure SET narrative_part_order = '{"Enfance":["SETUP"],"Éveil":["DISRUPTION"],"Départ":["COMMIT"],"Épreuves":["ESCALATION"],"Crise":["CRISIS"],"Compréhension":["REVELATION"],"Transformation":["TRANSFORMATION"],"Nouvel équilibre":["RESOLUTION"]}' WHERE name = 'Bildungsroman' AND JSON_LENGTH(narrative_part_order) = 8 AND JSON_CONTAINS(narrative_part_order, '{"Enfance":["SETUP"],"Éveil":["DISRUPTION"],"Départ":["COMMIT"],"Épreuves":["ESCALATION"],"Crise":["CRISIS"],"Compréhension":["REVELATION"],"Transformation":["TRANSFORMATION"],"Nouvel équilibre":["RESOLUTION"]}') AND JSON_CONTAINS('{"Enfance":["SETUP"],"Éveil":["DISRUPTION"],"Départ":["COMMIT"],"Épreuves":["ESCALATION"],"Crise":["CRISIS"],"Compréhension":["REVELATION"],"Transformation":["TRANSFORMATION"],"Nouvel équilibre":["RESOLUTION"]}', narrative_part_order);
UPDATE narrative_structure SET narrative_part_order = '{"Cycle 1":["SETUP","DISRUPTION"],"Cycle 2":["SETUP"],"Cycle 3":["TURN"],"Fin":["RESOLUTION"]}' WHERE name = 'Structure Fractale' AND JSON_LENGTH(narrative_part_order) = 4 AND JSON_CONTAINS(narrative_part_order, '{"Cycle 1":["SETUP","DISRUPTION"],"Cycle 2":["SETUP"],"Cycle 3":["TURN"],"Fin":["RESOLUTION"]}') AND JSON_CONTAINS('{"Cycle 1":["SETUP","DISRUPTION"],"Cycle 2":["SETUP"],"Cycle 3":["TURN"],"Fin":["RESOLUTION"]}', narrative_part_order);
UPDATE narrative_structure SET narrative_part_order = '{"Choc":["DISRUPTION"],"Désorientation":["TURN"],"Dévoilement":["REVELATION"],"Après":["RESOLUTION"]}' WHERE name = 'Anti-structure' AND JSON_LENGTH(narrative_part_order) = 4 AND JSON_CONTAINS(narrative_part_order, '{"Choc":["DISRUPTION"],"Désorientation":["TURN"],"Dévoilement":["REVELATION"],"Après":["RESOLUTION"]}') AND JSON_CONTAINS('{"Choc":["DISRUPTION"],"Désorientation":["TURN"],"Dévoilement":["REVELATION"],"Après":["RESOLUTION"]}', narrative_part_order);
UPDATE narrative_structure SET narrative_part_order = '{"Teaser":["DISRUPTION"],"Acte 1":["ESCALATION"],"Acte 2":["TURN"],"Acte 3":["CLIMAX"],"Tag":["RESOLUTION"]}' WHERE name = 'Structure TV Drama' AND JSON_LENGTH(narrative_part_order) = 5 AND JSON_CONTAINS(narrative_part_order, '{"Teaser":["DISRUPTION"],"Acte 1":["ESCALATION"],"Acte 2":["TURN"],"Acte 3":["CLIMAX"],"Tag":["RESOLUTION"]}') AND JSON_CONTAINS('{"Teaser":["DISRUPTION"],"Acte 1":["ESCALATION"],"Acte 2":["TURN"],"Acte 3":["CLIMAX"],"Tag":["RESOLUTION"]}', narrative_part_order);
UPDATE narrative_structure SET narrative_part_order = '{"Ouverture":["SETUP"],"Tension":["ESCALATION"],"Chute":["RESOLUTION"]}' WHERE name = 'Structure Nouvelle' AND JSON_LENGTH(narrative_part_order) = 3 AND JSON_CONTAINS(narrative_part_order, '{"Ouverture":["SETUP"],"Tension":["ESCALATION"],"Chute":["RESOLUTION"]}') AND JSON_CONTAINS('{"Ouverture":["SETUP"],"Tension":["ESCALATION"],"Chute":["RESOLUTION"]}', narrative_part_order);
UPDATE narrative_structure SET narrative_part_order = '{"Déclencheur":["DISRUPTION"],"Révélation":["REVELATION"],"Twist":["TURN"]}' WHERE name = 'Structure Flash Fiction' AND JSON_LENGTH(narrative_part_order) = 3 AND JSON_CONTAINS(narrative_part_order, '{"Déclencheur":["DISRUPTION"],"Révélation":["REVELATION"],"Twist":["TURN"]}') AND JSON_CONTAINS('{"Déclencheur":["DISRUPTION"],"Révélation":["REVELATION"],"Twist":["TURN"]}', narrative_part_order);
UPDATE narrative_structure SET narrative_part_order = '{"Départ":["DISRUPTION"],"Choix":["CRISIS"],"Issue":["RESOLUTION"]}' WHERE name = 'Structure Branches' AND JSON_LENGTH(narrative_part_order) = 3 AND JSON_CONTAINS(narrative_part_order, '{"Départ":["DISRUPTION"],"Choix":["CRISIS"],"Issue":["RESOLUTION"]}') AND JSON_CONTAINS('{"Départ":["DISRUPTION"],"Choix":["CRISIS"],"Issue":["RESOLUTION"]}', narrative_part_order);

-- 4. Beats des sous-genres qui n'en ont aucun (10 types de beats chacun).
--    Garde : un sous-genre qui a déjà des beats n'est pas touché.
-- Science-Fiction — Anticipation proche
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'FINAL_CONFRONTATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'REVELATION' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'TRANSFORMATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 4 AS weight, 1 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Anticipation proche'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Science-Fiction'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Drame — Drame ouvrier
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'FINAL_CONFRONTATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'SACRIFICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'TRANSFORMATION' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 4 AS weight, 1 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Drame ouvrier'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Drame'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Drame — Chronique collective
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'FINAL_CONFRONTATION' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'REVELATION' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'TRANSFORMATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 5 AS weight, 1 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Chronique collective'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Drame'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Drame — Drame institutionnel
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 5 AS weight, 1 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'FINAL_CONFRONTATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'REVELATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'TRANSFORMATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 3 AS weight, 0 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Drame institutionnel'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Drame'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Historique — Colonial et décolonisations
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'FINAL_CONFRONTATION' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'SACRIFICE' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'REVELATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 3 AS weight, 0 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Colonial et décolonisations'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Historique'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Historique — Guerre froide
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 5 AS weight, 1 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'FINAL_CONFRONTATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'SACRIFICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'REVELATION' AS code, 5 AS weight, 1 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 2 AS weight, 0 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Guerre froide'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Historique'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Historique — Préhistoire
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'FINAL_CONFRONTATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'SACRIFICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'TRANSFORMATION' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 4 AS weight, 1 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Préhistoire'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Historique'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Policier — Noir social
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'FINAL_CONFRONTATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'SACRIFICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'REVELATION' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 2 AS weight, 0 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Noir social'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Policier'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Policier — Noir de territoire
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'FINAL_CONFRONTATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'SACRIFICE' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'REVELATION' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 3 AS weight, 0 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Noir de territoire'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Policier'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Western — Western classique
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'FINAL_CONFRONTATION' AS code, 5 AS weight, 1 AS mandatory UNION ALL SELECT 'SACRIFICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'REVELATION' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 4 AS weight, 1 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Western classique'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Western'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Western — Western crépusculaire
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 5 AS weight, 1 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'FINAL_CONFRONTATION' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'SACRIFICE' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'REVELATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 1 AS weight, 0 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Western crépusculaire'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Western'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Western — Néo-western
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'FINAL_CONFRONTATION' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'SACRIFICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'REVELATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 2 AS weight, 0 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Néo-western'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Western'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Western — Western révisionniste
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'FINAL_CONFRONTATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'REVELATION' AS code, 5 AS weight, 1 AS mandatory UNION ALL SELECT 'TRANSFORMATION' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 3 AS weight, 0 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Western révisionniste'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Western'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Épique — Fresque historique
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'FINAL_CONFRONTATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'REVELATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'TRANSFORMATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 4 AS weight, 1 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Fresque historique'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Épique'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Épique — Saga générationnelle
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'SACRIFICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'REVELATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'TRANSFORMATION' AS code, 5 AS weight, 1 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 5 AS weight, 1 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Saga générationnelle'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Épique'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Épique — Épopée collective
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'FINAL_CONFRONTATION' AS code, 5 AS weight, 1 AS mandatory UNION ALL SELECT 'SACRIFICE' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'REVELATION' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 4 AS weight, 1 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Épopée collective'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Épique'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Épique — Récit de fondation
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'FINAL_CONFRONTATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'SACRIFICE' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'TRANSFORMATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 5 AS weight, 1 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Récit de fondation'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Épique'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Épique — Chronique de lutte
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 5 AS weight, 1 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 5 AS weight, 1 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'FINAL_CONFRONTATION' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'SACRIFICE' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'REVELATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 3 AS weight, 0 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Chronique de lutte'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Épique'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Fantastique — Vampirique
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'FINAL_CONFRONTATION' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'SACRIFICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'REVELATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'TRANSFORMATION' AS code, 3 AS weight, 0 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Vampirique'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Fantastique'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Fantastique — Fantastique historique
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'FINAL_CONFRONTATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'REVELATION' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'TRANSFORMATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 3 AS weight, 0 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Fantastique historique'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Fantastique'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Fantastique — Fantastique paranoïaque
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 5 AS weight, 1 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'FINAL_CONFRONTATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'SACRIFICE' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'REVELATION' AS code, 5 AS weight, 1 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 1 AS weight, 0 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Fantastique paranoïaque'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Fantastique'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Récit du réel — Témoignage
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 4 AS weight, 0 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 5 AS weight, 1 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'SACRIFICE' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'REVELATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'TRANSFORMATION' AS code, 5 AS weight, 1 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 4 AS weight, 1 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Témoignage'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Récit du réel'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Récit du réel — Chronique de terrain
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 5 AS weight, 1 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'FINAL_CONFRONTATION' AS code, 1 AS weight, 0 AS mandatory UNION ALL SELECT 'REVELATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'TRANSFORMATION' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 3 AS weight, 0 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Chronique de terrain'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Récit du réel'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Récit du réel — Enquête littéraire
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'FINAL_CONFRONTATION' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'REVELATION' AS code, 5 AS weight, 1 AS mandatory UNION ALL SELECT 'TRANSFORMATION' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 3 AS weight, 0 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Enquête littéraire'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Récit du réel'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Récit du réel — Essai narratif
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'FINAL_CONFRONTATION' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'REVELATION' AS code, 5 AS weight, 1 AS mandatory UNION ALL SELECT 'TRANSFORMATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 3 AS weight, 0 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Essai narratif'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Récit du réel'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Récit du réel — Pamphlet
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 5 AS weight, 1 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 5 AS weight, 1 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'FINAL_CONFRONTATION' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'SACRIFICE' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'REVELATION' AS code, 5 AS weight, 1 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 2 AS weight, 0 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Pamphlet'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Récit du réel'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Récit du réel — Portrait
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'SACRIFICE' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'REVELATION' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'TRANSFORMATION' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 3 AS weight, 0 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Portrait'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Récit du réel'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Récit du réel — True crime
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 1 AS weight, 0 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 5 AS weight, 1 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'FINAL_CONFRONTATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'SACRIFICE' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'REVELATION' AS code, 5 AS weight, 1 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 2 AS weight, 0 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'True crime'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Récit du réel'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
-- Récit du réel — Biographie romancée
INSERT INTO subgenre_event_type (subgenre_id, event_type_id, event_type_code, weight, is_mandatory)
SELECT s.id, et.id, et.code, b.weight, b.mandatory
FROM (SELECT 'PERFECT_DAILY_ROUTINE' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'INCITING_INCIDENT' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'RISING_TENSIONS' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'MIDPOINT_REVERSAL' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'ALL_SEEMS_LOST' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'CRISIS_CHOICE' AS code, 4 AS weight, 1 AS mandatory UNION ALL SELECT 'FINAL_CONFRONTATION' AS code, 2 AS weight, 0 AS mandatory UNION ALL SELECT 'REVELATION' AS code, 3 AS weight, 0 AS mandatory UNION ALL SELECT 'TRANSFORMATION' AS code, 5 AS weight, 1 AS mandatory UNION ALL SELECT 'NEW_BALANCE' AS code, 4 AS weight, 1 AS mandatory) b
JOIN event_type et ON et.code = b.code
JOIN subgenre s ON s.name = 'Biographie romancée'
JOIN genre g ON g.id = s.genre_id AND g.name = 'Récit du réel'
WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);

COMMIT;

-- ---------------------------------------------------------------------
-- 5. APRÈS : vider le cache des métadonnées (sinon l'application garde
--    l'ancien référentiel en mémoire). Depuis le dossier api :
--      php bin/console cache:pool:clear cache.app
-- ---------------------------------------------------------------------

-- 6. VÉRIFICATION
SELECT name, narrative_part_order FROM narrative_structure WHERE name IN ('In Medias Res', 'Structure Mosaïque', 'Story Spine Pixar');
SELECT code, name FROM narrative_part ORDER BY id;
SELECT COUNT(*) AS sous_genres_sans_beat FROM subgenre s WHERE NOT EXISTS (SELECT 1 FROM subgenre_event_type x WHERE x.subgenre_id = s.id);
