<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est un pilote avant d'accéder à la page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pilot') {
    header("Location: ../index.php");
    exit();
}

$pilot_id = $_SESSION['user_id'];

// Vérifier si l'ID de l'attestation est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: pilot_attestations.php");
    exit();
}

$attestation_id = intval($_GET['id']);

// Récupérer les détails de l'attestation
$query_attestation = $conn->prepare("
    SELECT a.*, 
           f.title as formation_title, f.section, f.fonction, f.duration,
           u.first_name, u.last_name
    FROM attestations a
    JOIN formations f ON a.formation_id = f.id
    JOIN users u ON a.user_id = u.id
    WHERE a.id = ? AND a.user_id = ?
");
$query_attestation->bind_param("ii", $attestation_id, $pilot_id);
$query_attestation->execute();
$result_attestation = $query_attestation->get_result();

// Vérifier si l'attestation existe et appartient au pilote
if ($result_attestation->num_rows === 0) {
    header("Location: pilot_attestations.php");
    exit();
}

$attestation = $result_attestation->fetch_assoc();

// Récupérer les examens liés à la formation
$query_exams = $conn->prepare("
    SELECT e.id, e.title, e.date, e.duration, e.passing_score,
           er.score, er.status, er.date_taken
    FROM exams e
    LEFT JOIN exam_results er ON e.id = er.exam_id AND er.pilot_id = ?
    WHERE e.formation_id = ?
    ORDER BY e.date DESC
");
$query_exams->bind_param("ii", $pilot_id, $attestation['formation_id']);
$query_exams->execute();
$result_exams = $query_exams->get_result();

// Récupérer les cours liés à la formation
$query_courses = $conn->prepare("
    SELECT c.id, c.title, c.description, c.file_format, c.duration,
           cp.status, cp.progress_percentage, cp.completed_at
    FROM courses c
    JOIN formation_courses fc ON c.id = fc.course_id
    LEFT JOIN course_progress cp ON c.id = cp.course_id AND cp.user_id = ?
    WHERE fc.formation_id = ?
    ORDER BY c.title
");
$query_courses->bind_param("ii", $pilot_id, $attestation['formation_id']);
$query_courses->execute();
$result_courses = $query_courses->get_result();

// Récupérer l'historique des actions liées à cette attestation
$query_history = $conn->prepare("
    SELECT ual.activity_type, ual.activity_details, ual.timestamp
    FROM user_activity_log ual
    WHERE ual.user_id = ? 
    AND (
        ual.activity_details LIKE CONCAT('%attestation%', ?) 
        OR ual.activity_details LIKE CONCAT('%formation%', ?)
    )
    ORDER BY ual.timestamp DESC
    LIMIT 10
");
$formation_id_str = (string)$attestation['formation_id'];
$attestation_id_str = (string)$attestation_id;
$query_history->bind_param("iss", $pilot_id, $attestation_id_str, $formation_id_str);
$query_history->execute();
$result_history = $query_history->get_result();

// Enregistrer la consultation de cette attestation
$query_log_view = $conn->prepare("
    INSERT INTO user_activity_log (user_id, activity_type, activity_details, timestamp)
    VALUES (?, 'view_attestation', CONCAT('Consultation de l\'attestation #', ?), NOW())
");
$query_log_view->bind_param("ii", $pilot_id, $attestation_id);
$query_log_view->execute();

// Fonction pour calculer le temps restant avant expiration
function getRemainingTime($expiration_date) {
    if (!$expiration_date) return null;
    
    $now = new DateTime();
    $expiration = new DateTime($expiration_date);
    $interval = $now->diff($expiration);
    
    if ($interval->invert) {
        return "Expirée depuis " . formatInterval($interval);
    } else {
        return "Expire dans " . formatInterval($interval);
    }
}

// Fonction pour formater l'intervalle de temps
function formatInterval($interval) {
    if ($interval->y > 0) {
        return $interval->format("%y an(s) et %m mois");
    } elseif ($interval->m > 0) {
        return $interval->format("%m mois et %d jour(s)");
    } elseif ($interval->d > 0) {
        return $interval->format("%d jour(s)");
    } else {
        return "moins d'un jour";
    }
}

// Déterminer la classe CSS pour le statut
$status_class = '';
$status_text = '';
switch ($attestation['statut']) {
    case 'valide':
        $status_class = 'success';
        $status_text = 'Valide';
        break;
    case 'expire':
        $status_class = 'danger';
        $status_text = 'Expirée';
        break;
    case 'bientot_expire':
        $status_class = 'warning';
        $status_text = 'Expire bientôt';
        break;
    default:
        $status_class = 'secondary';
        $status_text = 'Indéterminé';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de l'Attestation - TTA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_dashboard.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <script src="../components/alerts.js"></script>
    <style>
        .attestation-header {
            background-color: #f8f9fa;
            border-radius: 8px 8px 0 0;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            position: relative;
        }
        
        .attestation-title {
            font-size: 1.5rem;
            color: #0a3d91;
            margin-bottom: 5px;
        }
        
        .attestation-subtitle {
            color: #6c757d;
            font-size: 1rem;
        }
        
        .status-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .status-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-warning {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-secondary {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        .attestation-body {
            padding: 20px;
        }
        
        .info-section {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 1.2rem;
            color: #343a40;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 5px;
            display: block;
        }
        
        .info-value {
            font-weight: 500;
            color: #343a40;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .btn-print {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-download {
            background-color: #28a745;
            color: white;
        }
        
        .btn-back {
            background-color: transparent;
            color: #0a3d91;
            border: 1px solid #0a3d91;
        }
        
        .timeline {
            position: relative;
            margin: 20px 0;
            padding-left: 30px;
        }
        
        .timeline:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 2px;
            background-color: #e9ecef;
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 20px;
        }
        
        .timeline-item:last-child {
            padding-bottom: 0;
        }
        
        .timeline-item:before {
            content: '';
            position: absolute;
            left: -34px;
            top: 0;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: #0a3d91;
            border: 2px solid white;
        }
        
        .timeline-date {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .timeline-content {
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-radius: 4px;
        }
        
        .timeline-title {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .timeline-text {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .course-item, .exam-item {
            background-color: #f8f9fa;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .course-info, .exam-info {
            flex: 1;
        }
        
        .course-title, .exam-title {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .course-meta, .exam-meta {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .course-status, .exam-status {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-in-progress {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-not-started {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        .status-passed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-failed {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .qr-code {
            text-align: center;
            margin-top: 20px;
        }
        
        .qr-code img {
            max-width: 150px;
            border: 1px solid #e9ecef;
            padding: 10px;
            background-color: white;
        }
        
        .qr-code-text {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 10px;
        }
        
        @media print {
            .sidebar, .mobile-nav-toggle, .action-buttons, .breadcrumb {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 20px !important;
            }
            
            .attestation-header, .attestation-body {
                break-inside: avoid;
            }
        }
        .download-section {
    background-color: #f8f9fa;
    border-radius: 8px;
    padding: 30px;
    text-align: center;
}

.document-info {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.document-info p {
    margin-bottom: 20px;
    max-width: 500px;
    color: #495057;
}

.mb-3 {
    margin-bottom: 1rem;
}

.mt-3 {
    margin-top: 1rem;
}

.text-center {
    text-align: center;
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
            <h1 class="page-title">Détails de l'Attestation</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="pilot_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item"><a href="pilot_attestations.php" class="breadcrumb-link">Attestations</a></li>
                <li class="breadcrumb-item">Détails</li>
            </ul>
        </div>

        <div class="card">
            <div class="attestation-header">
                <h2 class="attestation-title"><?php echo htmlspecialchars($attestation['title']); ?></h2>
                <p class="attestation-subtitle">Formation: <?php echo htmlspecialchars($attestation['formation_title']); ?></p>
                <span class="status-badge status-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
            </div>
            
            <div class="attestation-body">
                <div class="info-section">
                    <h3 class="section-title">Informations générales</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Numéro d'attestation</span>
                            <span class="info-value">#<?php echo $attestation['id']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Type</span>
                            <span class="info-value"><?php echo $attestation['type'] === 'interne' ? 'Attestation interne' : 'Certification externe'; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Date d'émission</span>
                            <span class="info-value"><?php echo date('d/m/Y', strtotime($attestation['date_emission'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Date d'expiration</span>
                            <span class="info-value">
                                <?php 
                                    if ($attestation['date_expiration']) {
                                        echo date('d/m/Y', strtotime($attestation['date_expiration']));
                                    } else {
                                        echo 'Non applicable';
                                    }
                                ?>
                            </span>
                        </div>
                        <?php if ($attestation['date_expiration']): ?>
                        <div class="info-item">
                            <span class="info-label">Temps restant</span>
                            <span class="info-value"><?php echo getRemainingTime($attestation['date_expiration']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-section">
                    <h3 class="section-title">Détails de la formation</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Titre de la formation</span>
                            <span class="info-value"><?php echo htmlspecialchars($attestation['formation_title']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Section</span>
                            <span class="info-value"><?php echo $attestation['section'] ? ucfirst(htmlspecialchars($attestation['section'])) : 'Toutes'; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Fonction</span>
                            <span class="info-value"><?php echo $attestation['fonction'] ? htmlspecialchars($attestation['fonction']) : 'Toutes'; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Durée de la formation</span>
                            <span class="info-value"><?php echo $attestation['duration']; ?> heures</span>
                        </div>
                    </div>
                </div>
                
                <?php if ($attestation['notes']): ?>
                <div class="info-section">
                    <h3 class="section-title">Notes</h3>
                    <p><?php echo nl2br(htmlspecialchars($attestation['notes'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($result_courses && $result_courses->num_rows > 0): ?>
                <div class="info-section">
                    <h3 class="section-title">Cours associés</h3>
                    <div class="courses-list">
                        <?php while ($course = $result_courses->fetch_assoc()): ?>
                            <?php
                                $course_status_class = '';
                                $course_status_text = '';
                                
                                if ($course['status'] === 'completed') {
                                    $course_status_class = 'status-completed';
                                    $course_status_text = 'Complété';
                                } elseif ($course['status'] === 'in_progress') {
                                    $course_status_class = 'status-in-progress';
                                    $course_status_text = 'En cours';
                                } else {
                                    $course_status_class = 'status-not-started';
                                    $course_status_text = 'Non commencé';
                                }
                            ?>
                            <div class="course-item">
                                <div class="course-info">
                                    <div class="course-title"><?php echo htmlspecialchars($course['title']); ?></div>
                                    <div class="course-meta">
                                        <span><i class="fas fa-clock"></i> <?php echo $course['duration']; ?> min</span>
                                        <span><i class="fas fa-file"></i> <?php echo strtoupper($course['file_format']); ?></span>
                                        <?php if ($course['status'] === 'completed' && $course['completed_at']): ?>
                                            <span><i class="fas fa-calendar-check"></i> Complété le <?php echo date('d/m/Y', strtotime($course['completed_at'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="course-status <?php echo $course_status_class; ?>"><?php echo $course_status_text; ?></span>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($result_exams && $result_exams->num_rows > 0): ?>
                <div class="info-section">
                    <h3 class="section-title">Examens associés</h3>
                    <div class="exams-list">
                        <?php while ($exam = $result_exams->fetch_assoc()): ?>
                            <?php
                                $exam_status_class = '';
                                $exam_status_text = '';
                                
                                if ($exam['status'] === 'passed') {
                                    $exam_status_class = 'status-passed';
                                    $exam_status_text = 'Réussi';
                                } elseif ($exam['status'] === 'failed') {
                                    $exam_status_class = 'status-failed';
                                    $exam_status_text = 'Échoué';
                                } else {
                                    $exam_status_class = 'status-not-started';
                                    $exam_status_text = 'Non passé';
                                }
                            ?>
                            <div class="exam-item">
                                <div class="exam-info">
                                    <div class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></div>
                                    <div class="exam-meta">
                                        <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($exam['date'])); ?></span>
                                        <span><i class="fas fa-clock"></i> <?php echo $exam['duration']; ?> min</span>
                                        <span><i class="fas fa-check-circle"></i> Score minimum: <?php echo $exam['passing_score']; ?>%</span>
                                        <?php if ($exam['status'] === 'passed' || $exam['status'] === 'failed'): ?>
                                            <span><i class="fas fa-percent"></i> Votre score: <?php echo $exam['score']; ?>%</span>
                                            <span><i class="fas fa-calendar-check"></i> Passé le <?php echo date('d/m/Y', strtotime($exam['date_taken'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="exam-status <?php echo $exam_status_class; ?>"><?php echo $exam_status_text; ?></span>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                
                
                <div class="info-section">
                    <h3 class="section-title">Télécharger l'attestation</h3>
                    <div class="download-section text-center">
                        <?php 
                        $file_path = "../uploads/attestations/" . $attestation['fichier'];
                        if ($attestation['fichier'] && file_exists($file_path)): 
                        ?>
                            <div class="document-info">
                                <i class="fas fa-file-pdf fa-3x mb-3" style="color: #dc3545;"></i>
                                <p>Vous pouvez télécharger votre attestation officielle en cliquant sur le bouton ci-dessous.</p>
                                <a href="<?php echo $file_path; ?>" class="btn btn-download mt-3" download>
                                    <i class="fas fa-download"></i> Télécharger le fichier d'attestation
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> 
                                Le fichier d'attestation n'est pas disponible actuellement. 
                                Veuillez contacter l'administrateur si vous avez besoin d'une copie de votre attestation.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-section">
                    <h3 class="section-title">Authentification</h3>
                    <div class="qr-code">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . '/verify_attestation.php?id=' . $attestation['id']); ?>" alt="QR Code">
                        <p class="qr-code-text">Scannez ce QR code pour vérifier l'authenticité de cette attestation</p>
                    </div>
                </div>
                
                <?php if ($result_history && $result_history->num_rows > 0): ?>
                <div class="info-section">
                    <h3 class="section-title">Historique</h3>
                    <div class="timeline">
                        <?php while ($history = $result_history->fetch_assoc()): ?>
                            <div class="timeline-item">
                                <div class="timeline-date"><?php echo date('d/m/Y H:i', strtotime($history['timestamp'])); ?></div>
                                <div class="timeline-content">
                                    <div class="timeline-title">
                                        <?php
                                            switch ($history['activity_type']) {
                                                case 'view_attestation':
                                                    echo 'Consultation de l\'attestation';
                                                    break;
                                                case 'complete_course':
                                                    echo 'Cours complété';
                                                    break;
                                                case 'pass_exam':
                                                    echo 'Examen réussi';
                                                    break;
                                                case 'fail_exam':
                                                    echo 'Échec à l\'examen';
                                                    break;
                                                case 'issue_attestation':
                                                    echo 'Émission de l\'attestation';
                                                    break;
                                                default:
                                                    echo 'Activité';
                                            }
                                        ?>
                                    </div>
                                    <div class="timeline-text"><?php echo htmlspecialchars($history['activity_details']); ?></div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="action-buttons">
    <button class="btn btn-print" onclick="window.print()">
        <i class="fas fa-print"></i> Imprimer
    </button>
    <a href="pilot_attestations.php" class="btn btn-back">
        <i class="fas fa-arrow-left"></i> Retour aux attestations
    </a>
</div>
            </div>
        </div>
    </div>

    <script src="../scripts/admin_dashboard.js"></script>
</body>
</html>
