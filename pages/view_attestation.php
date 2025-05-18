<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est un pilote avant d'accéder à la page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pilot') {
    header("Location: ../index.php");
    exit();
}

// Vérifier si un ID d'attestation est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: pilot_attestations.php");
    exit();
}

$attestation_id = intval($_GET['id']);
$pilot_id = $_SESSION['user_id'];

// Récupérer les détails de l'attestation
$query_attestation = $conn->prepare("
    SELECT a.*, f.title as formation_title, f.section, f.fonction, f.duration
    FROM attestations a
    JOIN formations f ON a.formation_id = f.id
    WHERE a.id = ? AND a.user_id = ?
");
$query_attestation->bind_param("ii", $attestation_id, $pilot_id);
$query_attestation->execute();
$result_attestation = $query_attestation->get_result();

if ($result_attestation->num_rows === 0) {
    header("Location: pilot_attestations.php");
    exit();
}

$attestation = $result_attestation->fetch_assoc();

// Récupérer les examens liés à la formation de cette attestation
$query_exams = $conn->prepare("
    SELECT er.*, e.title as exam_title, e.passing_score
    FROM exam_results er
    JOIN exams e ON er.exam_id = e.id
    WHERE er.pilot_id = ? AND e.formation_id = ? AND er.status = 'passed'
    ORDER BY er.date_taken DESC
");
$query_exams->bind_param("ii", $pilot_id, $attestation['formation_id']);
$query_exams->execute();
$result_exams = $query_exams->get_result();

// Enregistrer la consultation dans les logs
$log_query = $conn->prepare("
    INSERT INTO user_activity_log (user_id, activity_type, activity_details, timestamp)
    VALUES (?, 'view_attestation', CONCAT('Consultation de l\'attestation #', ?), NOW())
");
$log_query->bind_param("ii", $pilot_id, $attestation_id);
$log_query->execute();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de l'Attestation - TTA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_dashboard.css">
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
        
        .status-warning {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-danger {
            background-color: #f8d7da;
            color: #721c24;
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
        
        .attestation-document {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .document-icon {
            font-size: 3rem;
            color: #0a3d91;
            margin-bottom: 15px;
        }
        
        .document-info {
            margin-bottom: 15px;
        }
        
        .document-actions {
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        
        .exams-list {
            margin-top: 20px;
        }
        
        .exam-item {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .exam-info {
            flex: 1;
        }
        
        .exam-title {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .exam-details {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .exam-score {
            font-size: 1.2rem;
            font-weight: bold;
            color: #28a745;
            padding: 0 15px;
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
        
        .btn-back {
            background-color: transparent;
            color: #0a3d91;
            border: 1px solid #0a3d91;
        }
        
        .expiration-info {
            background-color: #fff3cd;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            display: flex;
            align-items: center;
        }
        
        .expiration-info i {
            font-size: 1.5rem;
            color: #856404;
            margin-right: 15px;
        }
        
        .expiration-text {
            flex: 1;
        }
        
        .expiration-title {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .expiration-details {
            font-size: 0.9rem;
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
                <h2 class="attestation-title"><?php echo htmlspecialchars($attestation['title'] ?: $attestation['formation_title']); ?></h2>
                <p class="attestation-subtitle">Formation: <?php echo htmlspecialchars($attestation['formation_title']); ?></p>
                <?php 
                    $badge_class = '';
                    $badge_text = '';
                    
                    switch ($attestation['statut']) {
                        case 'valide':
                            $badge_class = 'status-success';
                            $badge_text = 'Valide';
                            break;
                        case 'bientot_expire':
                            $badge_class = 'status-warning';
                            $badge_text = 'Expire bientôt';
                            break;
                        case 'expire':
                            $badge_class = 'status-danger';
                            $badge_text = 'Expirée';
                            break;
                    }
                ?>
                <span class="status-badge <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
            </div>
            
            <div class="attestation-body">
                <div class="info-section">
                    <h3 class="section-title">Informations générales</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Type d'attestation</span>
                            <span class="info-value"><?php echo $attestation['type'] === 'interne' ? 'Interne' : 'Externe'; ?></span>
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
                        <div class="info-item">
                            <span class="info-label">Section</span>
                            <span class="info-value"><?php echo $attestation['section'] ? ucfirst(htmlspecialchars($attestation['section'])) : 'Non spécifiée'; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Fonction</span>
                            <span class="info-value"><?php echo $attestation['fonction'] ? htmlspecialchars($attestation['fonction']) : 'Non spécifiée'; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Durée de la formation</span>
                            <span class="info-value"><?php echo $attestation['duration']; ?> heures</span>
                        </div>
                    </div>
                </div>
                
                <?php if ($attestation['fichier']): ?>
                <div class="attestation-document">
                    <div class="document-icon">
                        <i class="fas fa-file-pdf"></i>
                    </div>
                    <div class="document-info">
                        <h4>Document d'attestation</h4>
                        <p>Ce document certifie que vous avez complété avec succès la formation.</p>
                    </div>
                    <div class="document-actions">
                        <a href="../uploads/attestations/<?php echo $attestation['fichier']; ?>" class="btn btn-primary" target="_blank">
                            <i class="fas fa-eye"></i> Voir le document
                        </a>
                        <a href="../uploads/attestations/<?php echo $attestation['fichier']; ?>" class="btn btn-outline-primary" download>
                            <i class="fas fa-download"></i> Télécharger
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($attestation['statut'] === 'bientot_expire'): ?>
                <div class="expiration-info">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div class="expiration-text">
                        <h4 class="expiration-title">Attestation expirant bientôt</h4>
                        <p class="expiration-details">
                            Cette attestation expirera le <?php echo date('d/m/Y', strtotime($attestation['date_expiration'])); ?>. 
                            Veuillez prévoir de renouveler votre formation avant cette date.
                        </p>
                    </div>
                </div>
                <?php elseif ($attestation['statut'] === 'expire'): ?>
                <div class="expiration-info" style="background-color: #f8d7da; color: #721c24;">
                    <i class="fas fa-exclamation-circle"></i>
                    <div class="expiration-text">
                        <h4 class="expiration-title">Attestation expirée</h4>
                        <p class="expiration-details">
                            Cette attestation a expiré le <?php echo date('d/m/Y', strtotime($attestation['date_expiration'])); ?>. 
                            Veuillez renouveler votre formation dès que possible.
                        </p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($result_exams && $result_exams->num_rows > 0): ?>
                <div class="info-section">
                    <h3 class="section-title">Examens réussis</h3>
                    <div class="exams-list">
                        <?php while ($exam = $result_exams->fetch_assoc()): ?>
                            <div class="exam-item">
                                <div class="exam-info">
                                    <div class="exam-title"><?php echo htmlspecialchars($exam['exam_title']); ?></div>
                                    <div class="exam-details">
                                        Passé le <?php echo date('d/m/Y', strtotime($exam['date_taken'])); ?> | 
                                        Score minimum requis: <?php echo $exam['passing_score']; ?>%
                                    </div>
                                </div>
                                <div class="exam-score"><?php echo round($exam['score'], 1); ?>%</div>
                                <a href="view_exam_result.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i> Détails
                                </a>
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
                    <?php if ($attestation['statut'] === 'expire'): ?>
                    <a href="formation_details.php?id=<?php echo $attestation['formation_id']; ?>" class="btn btn-success">
                        <i class="fas fa-sync-alt"></i> Renouveler la formation
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="../scripts/admin_dashboard.js"></script>
</body>
</html>
