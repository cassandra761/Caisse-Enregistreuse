DROP DATABASE IF EXISTS caisse;
CREATE DATABASE IF NOT EXISTS caisse CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE caisse;

-- Table Administrateur
CREATE TABLE IF NOT EXISTS Administrateur (
    id_user CHAR(4) PRIMARY KEY,
    nom VARCHAR(50) NOT NULL,
    prenom VARCHAR(50) NOT NULL,
    login VARCHAR(50) NOT NULL UNIQUE,
    mdp VARCHAR(255) NOT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table Caisse (Caissiers)
CREATE TABLE IF NOT EXISTS Caisse (
    id_user CHAR(4) PRIMARY KEY,
    nom VARCHAR(50) NOT NULL,
    prenom VARCHAR(50) NOT NULL,
    login VARCHAR(50) NOT NULL UNIQUE,
    mdp VARCHAR(255) NOT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table Comptable
CREATE TABLE IF NOT EXISTS Comptable (
    id_user CHAR(4) PRIMARY KEY,
    nom VARCHAR(50) NOT NULL,
    prenom VARCHAR(50) NOT NULL,
    login VARCHAR(50) NOT NULL UNIQUE,
    mdp VARCHAR(255) NOT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table Categorie
CREATE TABLE IF NOT EXISTS categorie (
    id_categorie INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table Produit
CREATE TABLE IF NOT EXISTS produit (
    id_produit INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    description TEXT,
    prix DECIMAL(10, 2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    id_categorie INT NOT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_categorie) REFERENCES categorie(id_categorie) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table Vente
CREATE TABLE IF NOT EXISTS vente (
    id_vente INT AUTO_INCREMENT PRIMARY KEY,
    id_user CHAR(4) NOT NULL,
    date_vente DATE NOT NULL,
    total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    statut VARCHAR(20) DEFAULT 'en_attente',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES Caisse(id_user) ON DELETE RESTRICT,
    INDEX idx_user (id_user),
    INDEX idx_date (date_vente)
) ENGINE=InnoDB;

-- Table Ligne Vente
CREATE TABLE IF NOT EXISTS ligne_vente (
    id_ligne INT AUTO_INCREMENT PRIMARY KEY,
    id_vente INT NOT NULL,
    id_produit INT NOT NULL,
    quantite INT NOT NULL,
    prix DECIMAL(10, 2) NOT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_vente) REFERENCES vente(id_vente) ON DELETE CASCADE,
    FOREIGN KEY (id_produit) REFERENCES produit(id_produit) ON DELETE RESTRICT,
    INDEX idx_vente (id_vente)
) ENGINE=InnoDB;

-- Nettoyage des données existantes (optionnel)
TRUNCATE TABLE ligne_vente;
TRUNCATE TABLE vente;
TRUNCATE TABLE produit;
TRUNCATE TABLE categorie;
TRUNCATE TABLE Administrateur;
TRUNCATE TABLE Comptable;
TRUNCATE TABLE Caisse;

-- Insertion des catégories
INSERT INTO categorie (id_categorie, nom, description) VALUES 
(1, 'Boissons', 'Café, thé, jus et autres boissons'),
(2, 'Gâteaux', 'Pâtisseries et gâteaux'),
(3, 'Compotes', 'Fruits et compotes'),
(4, 'Snacks', 'Barres, chips et autres snacks');

-- Insertion des produits
INSERT INTO produit (id_produit, nom, description, prix, stock, id_categorie) VALUES 
(1, 'Café', 'Café noir classique', 0.50, 100, 1),
(2, 'Thé', 'Thé assortis', 0.50, 80, 1),
(3, 'Café Noisette', 'Café avec noisette', 0.60, 70, 1),
(4, 'Cappuccino', 'Cappuccino mousseux', 0.75, 90, 1),
(5, 'Capri-sun', 'Jus de fruit Capri-sun', 0.50, 120, 1),
(6, 'Pépito Pépites', 'Biscuit au chocolat', 0.50, 100, 2),
(7, 'Granola', 'Barre granola', 0.50, 110, 2),
(8, 'Donuts', 'Donuts glacés', 0.60, 60, 2),
(9, 'Compote Pomme', 'Compote de pomme', 0.50, 85, 3),
(10, 'Barre Protéine', 'Barre protéinée', 0.80, 40, 4);

-- Insertion des administrateurs
INSERT INTO Administrateur (id_user, nom, prenom, login, mdp) VALUES 
('a300', 'Térrieur', 'Alex', 'aterrieur', 'admin123'),
('a301', 'Père', 'Noël', 'pnoel', 'admin123');

-- Insertion des caissiers
INSERT INTO Caisse (id_user, nom, prenom, login, mdp) VALUES 
('a100', 'Bon', 'Jean', 'jbon', 'password'),
('a101', 'Léponge', 'Bob', 'bléponge', 'password'),
('a102', 'Simpson', 'Homer', 'hsimpson', 'password'),
('a103', 'Stark', 'Tony', 'tstark', 'password'),
('a104', 'Parker', 'Peter', 'pparker', 'password');

-- Insertion des comptables
INSERT INTO Comptable (id_user, nom, prenom, login, mdp) VALUES 
('a200', 'Petit', 'Prince', 'ppetit', 'password'),
('a201', 'Mickey', 'Mouse', 'mmickey', 'password'),
('a202', 'Duck', 'Donald', 'dduck', 'password');

-- Insertion de quelques ventes d'exemple
INSERT INTO vente (id_vente, id_user, date_vente, total, statut) VALUES 
(1, 'a100', '2026-06-18', 2.50, 'valide'),
(2, 'a101', '2026-06-17', 3.85, 'valide'),
(3, 'a102', '2026-06-16', 1.50, 'valide');

-- Insertion des lignes de vente
INSERT INTO ligne_vente (id_ligne, id_vente, id_produit, quantite, prix) VALUES 
(1, 1, 1, 2, 0.50),
(2, 1, 6, 2, 0.50),
(3, 2, 4, 1, 0.75),
(4, 2, 8, 4, 0.60),
(5, 3, 2, 3, 0.50);