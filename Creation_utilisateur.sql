-- Suppression des utilisateurs
DROP USER IF EXISTS 'caissier'@'%';
DROP USER IF EXISTS 'comptable'@'%';
DROP USER IF EXISTS 'administrateur'@'%';

-- Création des utilisateurs
CREATE USER 'caissier'@'%' IDENTIFIED BY 'pwd_cais';
CREATE USER 'comptable'@'%' IDENTIFIED BY 'pwd_compt';
CREATE USER 'administrateur'@'%' IDENTIFIED BY 'pwd_admin';

--caissier : rwx rw- r--
GRANT SELECT, INSERT, UPDATE ON caisse_enregistreuse.* TO 'caissier'@'%';

-- Comptable : r-- rwx r--
GRANT SELECT ON caisse_enregistreuse.* TO 'comptable'@'%';
GRANT INSERT, UPDATE ON caisse_enregistreuse.* TO 'comptable'@'%';

-- Administrateur : rwx rwx rw-
GRANT ALL PRIVILEGES ON caisse_enregistreuse.* TO 'administrateur'@'%' WITH GRANT OPTION;

FLUSH PRIVILEGES;