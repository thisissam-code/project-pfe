CREATE DATABASE IF NOT EXISTS tta;
USE tta;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL, 
    last_name VARCHAR(50) NOT NULL, 
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'instructor', 'pilot', 'admin_sol' , 'admin_vol') NOT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME
);

-- Table des formations
CREATE TABLE IF NOT EXISTS formations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    duration INT NOT NULL COMMENT 'Durée en heures',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table des cours
CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    file_path VARCHAR(255) NOT NULL COMMENT 'Chemin vers le fichier stocké',
    file_format VARCHAR(10) NOT NULL COMMENT 'Ex: pdf, docx, pptx, mp4, mp3',
    duration INT(11) DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
);

-- Relation entre formations et cours
CREATE TABLE IF NOT EXISTS formation_courses (
    formation_id INT NOT NULL,
    course_id INT NOT NULL,
    PRIMARY KEY (formation_id, course_id),
    FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Relation entre formations et instructeurs
CREATE TABLE IF NOT EXISTS formation_instructors (
    formation_id INT NOT NULL,
    instructor_id INT NOT NULL,
    PRIMARY KEY (formation_id, instructor_id),
    FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE,
    FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des examens (désormais liés aux formations)
CREATE TABLE IF NOT EXISTS exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    duration INT NOT NULL, 
    passing_score INT NOT NULL DEFAULT 70, 
    date DATE DEFAULT CURDATE(),
    formation_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE
);

-- Table des questions
CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    question_text TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
);

-- Table des réponses
CREATE TABLE IF NOT EXISTS answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    answer_text TEXT NOT NULL,
    is_correct BOOLEAN NOT NULL DEFAULT 0, 
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- Table d'affectation des cours aux pilotes
CREATE TABLE IF NOT EXISTS pilot_courses (
    pilot_id INT NOT NULL,
    course_id INT NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (pilot_id, course_id),
    FOREIGN KEY (pilot_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Table d'affectation des examens aux pilotes
CREATE TABLE IF NOT EXISTS pilot_exams (
    pilot_id INT NOT NULL,
    exam_id INT NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started', 
    PRIMARY KEY (pilot_id, exam_id),
    FOREIGN KEY (pilot_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
);

-- Table de suivi des cours complétés
CREATE TABLE IF NOT EXISTS course_completions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pilot_id INT NOT NULL,
    course_id INT NOT NULL,
    completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (pilot_id, course_id),
    FOREIGN KEY (pilot_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Table des résultats d'examen
CREATE TABLE IF NOT EXISTS exam_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pilot_id INT NOT NULL,
    exam_id INT NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    status ENUM('passed', 'failed') NOT NULL,
    date_taken DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    FOREIGN KEY (pilot_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
);

-- Table de logs des connexions
CREATE TABLE IF NOT EXISTS login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    browser VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des paramètres du site
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_name VARCHAR(255) NOT NULL DEFAULT 'TTA',
    site_description TEXT DEFAULT NULL,
    maintenance_mode TINYINT(1) NOT NULL DEFAULT 0,
    smtp_host VARCHAR(255) DEFAULT NULL,
    smtp_port VARCHAR(10) DEFAULT NULL,
    smtp_username VARCHAR(255) DEFAULT NULL,
    smtp_password VARCHAR(255) DEFAULT NULL,
    smtp_encryption VARCHAR(10) DEFAULT NULL
);

-- Création de l'administrateur par défaut
INSERT INTO users (first_name, last_name, email, password, role, created_at)
VALUES ('admin', 'admin', 'admin@tta.dz', '$2y$10$JXHMG7dcRJfo65e2XKROU.TMW7wCosmNx21fxvuY0HEYtEBuOIHvm', 'admin', NOW());
ALTER TABLE questions 
DROP FOREIGN KEY questions_ibfk_1, 
DROP COLUMN exam_id;
ALTER TABLE questions 
ADD COLUMN formation_id INT NOT NULL,
ADD CONSTRAINT fk_questions_formation FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE;
DROP TABLE IF EXISTS formation_instructors;

CREATE TABLE formation_instructors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    instructor_id INT NOT NULL,
    FOREIGN KEY (course_id) REFERENCES courses(id),
    FOREIGN KEY (instructor_id) REFERENCES users(id)
);
DROP TABLE IF EXISTS formation_instructors;

CREATE TABLE formation_instructors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    formation_id INT NOT NULL,
    instructor_id INT NOT NULL,
    FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE,
    FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS formation_pilots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    formation_id INT NOT NULL,
    pilot_id INT NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (formation_id, pilot_id),
    FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE,
    FOREIGN KEY (pilot_id) REFERENCES users(id) ON DELETE CASCADE
);
-- 1. Supprimer la table course_completions
DROP TABLE IF EXISTS course_completions;

-- 2. Modifier la table users
ALTER TABLE users
ADD COLUMN entreprise TEXT DEFAULT NULL, 
ADD COLUMN section ENUM('sol', 'vol') DEFAULT NULL,
ADD COLUMN fonction ENUM('BE1900D', 'C208B', 'BELL206', 'AT802') DEFAULT NULL;

-- 3. Modifier la table formations
ALTER TABLE formations
ADD COLUMN section ENUM('sol', 'vol') DEFAULT NULL,
ADD COLUMN fonction ENUM('BE1900D', 'C208B', 'BELL206', 'AT802') DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `attestations` (
       `id` int(11) NOT NULL AUTO_INCREMENT,
       `user_id` int(11) NOT NULL,
       `formation_id` int(11) NOT NULL,
       `date_emission` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
       `date_expiration` datetime DEFAULT NULL,
       `type` ENUM('interne', 'externe') NOT NULL DEFAULT 'interne',
       `fichier` varchar(255) DEFAULT NULL,
       `statut` ENUM('valide', 'expire', 'bientot_expire') NOT NULL DEFAULT 'valide',
       PRIMARY KEY (`id`),
       KEY `user_id` (`user_id`),
       KEY `formation_id` (`formation_id`),
       CONSTRAINT `attestations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
       CONSTRAINT `attestations_ibfk_2` FOREIGN KEY (`formation_id`) REFERENCES `formations` (`id`) ON DELETE CASCADE
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
   -- Modification de la table attestations pour ajouter les champs manquants
ALTER TABLE attestations
ADD COLUMN title VARCHAR(255) NOT NULL AFTER id,
ADD COLUMN status ENUM('not_started', 'pending', 'completed') DEFAULT 'not_started' AFTER fichier,
ADD COLUMN date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER status,
ADD COLUMN date_completed TIMESTAMP NULL AFTER date_created,
ADD COLUMN notes TEXT AFTER date_completed;

-- Création de la table formation_users (pour lier les étudiants aux formations)
CREATE TABLE IF NOT EXISTS formation_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    formation_id INT NOT NULL,
    user_id INT NOT NULL,
    date_assigned TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
    FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_formation_user (formation_id, user_id)
);

-- Ajout d'un index unique à la table formation_instructors
ALTER TABLE formation_instructors
ADD UNIQUE KEY unique_formation_instructor (formation_id, instructor_id);

-- Conversion des données de formation_pilots vers formation_users si nécessaire
INSERT IGNORE INTO formation_users (formation_id, user_id, date_assigned, status)
SELECT formation_id, pilot_id, assigned_at, 'not_started'
FROM formation_pilots;

-- Ajout d'un index unique à la table attestations
ALTER TABLE attestations
ADD UNIQUE KEY unique_attestation (formation_id, user_id);
-- Modification de la table questions pour ajouter un champ type
ALTER TABLE questions 
ADD COLUMN type ENUM('qcm') NOT NULL DEFAULT 'qcm' AFTER question_text;

-- Création d'une table pour la bibliothèque de documents
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    uploaded_by INT,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Création d'une table pour les tentatives d'examen
CREATE TABLE IF NOT EXISTS exam_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    exam_id INT NOT NULL,
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    score DECIMAL(5,2) DEFAULT 0,
    status ENUM('in_progress', 'completed', 'abandoned') DEFAULT 'in_progress',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
);

-- Création d'une table pour les réponses des utilisateurs
CREATE TABLE IF NOT EXISTS user_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    answer_id INT NOT NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    FOREIGN KEY (answer_id) REFERENCES answers(id) ON DELETE CASCADE
);
-- Table pour enregistrer les consultations de formations par les utilisateurs
CREATE TABLE IF NOT EXISTS formation_views (
    user_id INT NOT NULL,
    formation_id INT NOT NULL,
    view_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, formation_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE
);

-- Table pour le suivi de progression des cours
CREATE TABLE IF NOT EXISTS course_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    status ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
    progress_percentage INT DEFAULT 0,
    last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    UNIQUE KEY (user_id, course_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Table pour les logs d'activité des utilisateurs
CREATE TABLE IF NOT EXISTS user_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    activity_details TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);