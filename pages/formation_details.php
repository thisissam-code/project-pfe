<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est un pilote avant d'accéder à la page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pilot') {
    header("Location: ../index.php");
    exit();
}

// Vérifier si un ID de formation est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: pilot_formations.php");
    exit();
}

$formation_id = intval($_GET['id']);
$pilot_id = $_SESSION['user_id'];
$pilot_section = $_SESSION['section'] ?? null;
$pilot_fonction = $_SESSION['fonction'] ?? null;

// Récupérer les informations de la formation
$query_formation = $conn->prepare("
    SELECT f.*, 
           COUNT(DISTINCT fc.course_id) AS course_count,
           (SELECT COUNT(*) FROM exams WHERE formation_id = f.id) AS exam_count
    FROM formations f
    LEFT JOIN formation_courses fc ON f.id = fc.formation_id
    WHERE f.id = ?
    GROUP BY f.id
");
$query_formation->bind_param("i", $formation_id);
$query_formation->execute();
$result_formation = $query_formation->get_result();

if ($result_formation->num_rows === 0) {
    header("Location: pilot_formations.php");
    exit();
}

$formation = $result_formation->fetch_assoc();

// Suppression de la vérification de section pour permettre l'accès aux formations sol et vol
// Vérifier uniquement la fonction si elle est définie
if ($formation['fonction'] !== null && $formation['fonction'] !== $pilot_fonction) {
    header("Location: pilot_formations.php");
    exit();
}

// Récupérer les cours associés à la formation
$query_courses = $conn->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM course_progress WHERE course_id = c.id AND user_id = ?) AS is_completed
    FROM courses c
    JOIN formation_courses fc ON c.id = fc.course_id
    WHERE fc.formation_id = ?
    ORDER BY c.title
");
$query_courses->bind_param("ii", $pilot_id, $formation_id);
$query_courses->execute();
$result_courses = $query_courses->get_result();

// Récupérer les examens associés à la formation
$query_exams = $conn->prepare("
    SELECT e.*, 
           (SELECT COUNT(*) FROM exam_results WHERE exam_id = e.id AND pilot_id = ?) AS attempts,
           (SELECT MAX(score) FROM exam_results WHERE exam_id = e.id AND pilot_id = ?) AS best_score,
           (SELECT status FROM exam_results WHERE exam_id = e.id AND pilot_id = ? ORDER BY score DESC LIMIT 1) AS status
    FROM exams e
    WHERE e.formation_id = ?
    ORDER BY e.title
");
$query_exams->bind_param("iiii", $pilot_id, $pilot_id, $pilot_id, $formation_id);
$query_exams->execute();
$result_exams = $query_exams->get_result();

// Récupérer les instructeurs associés à la formation
$query_instructors = $conn->prepare("
    SELECT u.id, u.first_name, u.last_name, u.email, u.section, u.fonction
    FROM users u
    JOIN formation_instructors fi ON u.id = fi.instructor_id
    WHERE fi.formation_id = ?
    ORDER BY u.last_name, u.first_name
");
$query_instructors->bind_param("i", $formation_id);
$query_instructors->execute();
$result_instructors = $query_instructors->get_result();

// Enregistrer la consultation de la formation
$query_log_view = $conn->prepare("
    INSERT INTO formation_views (user_id, formation_id, view_date)
    VALUES (?, ?, NOW())
    ON DUPLICATE KEY UPDATE view_date = NOW()
");
$query_log_view->bind_param("ii", $pilot_id, $formation_id);
$query_log_view->execute();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($formation['title']); ?> - TTA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_dashboard.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <script src="../components/alerts.js"></script>
    <style>
        .formation-header {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .formation-title {
            margin-bottom: 15px;
            color: #0a3d91;
        }
        
        .formation-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
        }
        
        .meta-label {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .meta-value {
            font-weight: 500;
            font-size: 1.1rem;
        }
        
        .section-title {
            margin-top: 30px;
            margin-bottom: 20px;
            color: #0a3d91;
            font-size: 1.5rem;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .course-card {
            background-color: #fff;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s ease;
            overflow: hidden;
        }
        
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .course-header {
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .course-title {
            margin: 0;
            font-size: 1.25rem;
            color: #0a3d91;
        }
        
        .course-body {
            padding: 20px;
        }
        
        .course-description {
            margin-bottom: 15px;
            color: #495057;
        }
        
        .course-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .course-duration {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .course-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .exam-card {
            background-color: #fff;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s ease;
            overflow: hidden;
        }
        
        .exam-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .exam-header {
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .exam-title {
            margin: 0;
            font-size: 1.25rem;
            color: #0a3d91;
        }
        
        .exam-body {
            padding: 20px;
        }
        
        .exam-description {
            margin-bottom: 15px;
            color: #495057;
        }
        
        .exam-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .exam-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .badge {
            display: inline-block;
            padding: 0.25em 0.6em;
            font-size: 75%;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
            color: #fff;
        }
        
        .bg-success {
            background-color: #28a745;
        }
        
        .bg-danger {
            background-color: #dc3545;
        }
        
        .bg-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .bg-secondary {
            background-color: #6c757d;
        }
        
        .instructor-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .instructor-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .instructor-name {
            font-weight: 600;
            margin-bottom: 5px;
            color: #0a3d91;
        }
        
        .instructor-email {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .instructor-details {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 0.85rem;
        }
        
        .instructor-detail {
            background-color: #f8f9fa;
            padding: 5px 10px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <!-- Mobile Navigation Toggle -->
    <button class="mobile-nav-toggle">
        <i class="fas fa-bars"></i>
    </button>

    <?php include '../includes/pilote_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Détails de la Formation</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="pilot_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item"><a href="pilot_formations.php" class="breadcrumb-link">Formations</a></li>
                <li class="breadcrumb-item"><?php echo htmlspecialchars($formation['title']); ?></li>
            </ul>
        </div>

        <!-- Formation Header -->
        <div class="formation-header">
            <h2 class="formation-title"><?php echo htmlspecialchars($formation['title']); ?></h2>
            
            <div class="formation-meta">
                <div class="meta-item">
                    <span class="meta-label">Section</span>
                    <span class="meta-value"><?php echo $formation['section'] ? ucfirst(htmlspecialchars($formation['section'])) : 'Toutes'; ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Fonction</span>
                    <span class="meta-value"><?php echo $formation['fonction'] ? htmlspecialchars($formation['fonction']) : 'Toutes'; ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Durée</span>
                    <span class="meta-value"><?php echo $formation['duration']; ?> heures</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Cours</span>
                    <span class="meta-value"><?php echo $formation['course_count']; ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Examens</span>
                    <span class="meta-value"><?php echo $formation['exam_count']; ?></span>
                </div>
            </div>
        </div>

        <!-- Courses Section -->
        <h3 class="section-title">Cours de la formation</h3>
        
        <div class="row">
            <?php if ($result_courses && $result_courses->num_rows > 0): ?>
                <?php while ($course = $result_courses->fetch_assoc()): ?>
                    <div class="col-md-6">
                        <div class="course-card">
                            <div class="course-header">
                                <h4 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h4>
                                <?php if ($course['is_completed']): ?>
                                    <span class="badge bg-success">Complété</span>
                                <?php endif; ?>
                            </div>
                            <div class="course-body">
                                <div class="course-description">
                                    <?php echo htmlspecialchars(substr($course['description'], 0, 150)) . (strlen($course['description']) > 150 ? '...' : ''); ?>
                                </div>
                                
                                <div class="course-info">
                                    <span class="course-duration">
                                        <i class="fas fa-clock"></i> <?php echo $course['duration']; ?> heures
                                    </span>
                                    
                                    <?php if (!empty($course['file_format'])): ?>
                                        <span class="course-format">
                                            <i class="fas fa-file"></i> <?php echo strtoupper($course['file_format']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="course-actions">
                                    <?php if (!empty($course['file_path'])): ?>
                                        <a href="<?php echo htmlspecialchars($course['file_path']); ?>" target="_blank" class="btn btn-primary">
                                            <i class="fas fa-book-open"></i> Consulter
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!$course['is_completed']): ?>
                                        <a href="mark_course_completed.php?id=<?php echo $course['id']; ?>&formation_id=<?php echo $formation_id; ?>" class="btn btn-outline-success">
                                            <i class="fas fa-check"></i> Marquer comme terminé
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Aucun cours disponible pour cette formation.
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Exams Section -->
        <h3 class="section-title">Examens de la formation</h3>
        
        <div class="row">
            <?php if ($result_exams && $result_exams->num_rows > 0): ?>
                <?php while ($exam = $result_exams->fetch_assoc()): ?>
                    <div class="col-md-6">
                        <div class="exam-card">
                            <div class="exam-header">
                                <h4 class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></h4>
                                <?php if ($exam['attempts'] > 0): ?>
                                    <?php if ($exam['status'] === 'passed'): ?>
                                        <span class="badge bg-success">Réussi</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Échoué</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Non passé</span>
                                <?php endif; ?>
                            </div>
                            <div class="exam-body">
                                <div class="exam-description">
                                    <?php echo htmlspecialchars($exam['description'] ?? 'Aucune description disponible.'); ?>
                                </div>
                                
                                <div class="exam-info">
                                    <div class="info-item">
                                        <span class="info-label">Durée</span>
                                        <span class="info-value"><?php echo $exam['duration']; ?> minutes</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Score requis</span>
                                        <span class="info-value"><?php echo $exam['passing_score']; ?>%</span>
                                    </div>
                                    <?php if ($exam['attempts'] > 0): ?>
                                    <div class="info-item">
                                        <span class="info-label">Meilleur score</span>
                                        <span class="info-value"><?php echo round($exam['best_score'], 1); ?>%</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Tentatives</span>
                                        <span class="info-value"><?php echo $exam['attempts']; ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="exam-actions">
                                    <?php if ($exam['attempts'] > 0): ?>
                                        <a href="exam_results.php?id=<?php echo $exam['id']; ?>" class="btn btn-outline-primary">
                                            <i class="fas fa-chart-bar"></i> Voir les résultats
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($exam['attempts'] == 0 || $exam['status'] !== 'passed'): ?>
                                        <a href="take_formation_exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-pen"></i> Passer l'examen
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Aucun examen disponible pour cette formation.
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Instructors Section -->
        <h3 class="section-title">Instructeurs de la formation</h3>
        
        <div class="instructor-list">
            <?php if ($result_instructors && $result_instructors->num_rows > 0): ?>
                <?php while ($instructor = $result_instructors->fetch_assoc()): ?>
                    <div class="instructor-card">
                        <div class="instructor-name">
                            <?php echo htmlspecialchars($instructor['first_name'] . ' ' . $instructor['last_name']); ?>
                        </div>
                        <div class="instructor-email">
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($instructor['email']); ?>
                        </div>
                        <div class="instructor-details">
                            <?php if (!empty($instructor['section'])): ?>
                                <span class="instructor-detail">
                                    <i class="fas fa-layer-group"></i> <?php echo ucfirst(htmlspecialchars($instructor['section'])); ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($instructor['fonction'])): ?>
                                <span class="instructor-detail">
                                    <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($instructor['fonction']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Aucun instructeur assigné à cette formation.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../scripts/admin_dashboard.js"></script>
</body>
</html>