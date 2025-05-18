<?php
session_start();
require '../includes/bd.php'; 

// Vérifier si l'utilisateur est connecté et est un instructeur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: ../login.php");
    exit();
}

$instructor_id = $_SESSION['user_id'];

// Récupérer les informations de l'instructeur
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'instructor'");
$stmt->execute([$instructor_id]);
$instructor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$instructor) {
    $_SESSION['error'] = "Instructeur non trouvé.";
    header('Location: dashboard.php');
    exit;
}

// Récupérer les formations associées à l'instructeur
$stmt = $pdo->prepare("
    SELECT f.* 
    FROM formations f
    JOIN formation_instructors if ON f.id = if.formation_id
    WHERE if.instructor_id = ?
");
$stmt->execute([$instructor_id]);
$formations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les examens créés par l'instructeur
$stmt = $pdo->prepare("
    SELECT e.*, f.name as formation_name
    FROM exams e
    JOIN formations f ON e.formation_id = f.id
    WHERE e.created_by = ?
    ORDER BY e.created_at DESC
");
$stmt->execute([$instructor_id]);
$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les attestations délivrées par l'instructeur
$stmt = $pdo->prepare("
    SELECT a.*, u.firstname, u.lastname, f.name as formation_name
    FROM attestations a
    JOIN users u ON a.pilot_id = u.id
    JOIN formations f ON a.formation_id = f.id
    WHERE a.instructor_id = ?
    ORDER BY a.issue_date DESC
");
$stmt->execute([$instructor_id]);
$attestations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les statistiques
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT pilot_id) as total_pilots
    FROM attestations
    WHERE instructor_id = ?
");
$stmt->execute([$instructor_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_exams
    FROM exams
    WHERE created_by = ?
");
$stmt->execute([$instructor_id]);
$exam_stats = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['total_exams'] = $exam_stats['total_exams'];

$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_attestations
    FROM attestations
    WHERE instructor_id = ?
");
$stmt->execute([$instructor_id]);
$attestation_stats = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['total_attestations'] = $attestation_stats['total_attestations'];

// Récupérer les pilotes associés à l'instructeur (via les attestations)
$stmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.firstname, u.lastname, u.email, 
           (SELECT COUNT(*) FROM attestations WHERE pilot_id = u.id AND instructor_id = ?) as attestation_count
    FROM users u
    JOIN attestations a ON u.id = a.pilot_id
    WHERE a.instructor_id = ? AND u.role = 'pilot'
    ORDER BY u.lastname, u.firstname
");
$stmt->execute([$instructor_id, $instructor_id]);
$pilots = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement du formulaire de mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $bio = trim($_POST['bio']);
    
    // Validation des données
    $errors = [];
    
    if (empty($firstname)) {
        $errors[] = "Le prénom est requis.";
    }
    
    if (empty($lastname)) {
        $errors[] = "Le nom est requis.";
    }
    
    if (empty($email)) {
        $errors[] = "L'email est requis.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format d'email invalide.";
    }
    
    // Vérifier si l'email existe déjà pour un autre utilisateur
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $instructor_id]);
    if ($stmt->rowCount() > 0) {
        $errors[] = "Cet email est déjà utilisé par un autre utilisateur.";
    }
    
    // Si pas d'erreurs, mettre à jour le profil
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET firstname = ?, lastname = ?, email = ?, phone = ?, bio = ?
                WHERE id = ?
            ");
            $stmt->execute([$firstname, $lastname, $email, $phone, $bio, $instructor_id]);
            
            $_SESSION['success'] = "Votre profil a été mis à jour avec succès.";
            header('Location: instructor_profile.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = "Erreur lors de la mise à jour du profil: " . $e->getMessage();
        }
    }
}

// Traitement du changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Vérifier le mot de passe actuel
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$instructor_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!password_verify($current_password, $user['password'])) {
        $errors[] = "Le mot de passe actuel est incorrect.";
    }
    
    if (strlen($new_password) < 8) {
        $errors[] = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = "Les nouveaux mots de passe ne correspondent pas.";
    }
    
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $instructor_id]);
            
            $_SESSION['success'] = "Votre mot de passe a été changé avec succès.";
            header('Location: instructor_profile.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = "Erreur lors du changement de mot de passe: " . $e->getMessage();
        }
    }
}

include 'includes/header.php'; // Chemin incorrect vers le fichier d'en-tête
?>

<div class="container mt-4">
    <h1>Profil Instructeur</h1>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?= $_SESSION['success'] ?>
            <?php unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($errors) && !empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= $error ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Informations personnelles</h5>
                </div>
                <div class="card-body">
                    <h3><?= htmlspecialchars($instructor['firstname'] . ' ' . $instructor['lastname']) ?></h3>
                    <p><strong>Email:</strong> <?= htmlspecialchars($instructor['email']) ?></p>
                    <p><strong>Téléphone:</strong> <?= htmlspecialchars($instructor['phone'] ?? 'Non renseigné') ?></p>
                    <p><strong>Bio:</strong> <?= nl2br(htmlspecialchars($instructor['bio'] ?? 'Aucune biographie disponible.')) ?></p>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#editProfileModal">
                        Modifier le profil
                    </button>
                    <button type="button" class="btn btn-secondary mt-2" data-toggle="modal" data-target="#changePasswordModal">
                        Changer le mot de passe
                    </button>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Statistiques</h5>
                </div>
                <div class="card-body">
                    <p><strong>Pilotes formés:</strong> <?= $stats['total_pilots'] ?? 0 ?></p>
                    <p><strong>Examens créés:</strong> <?= $stats['total_exams'] ?? 0 ?></p>
                    <p><strong>Attestations délivrées:</strong> <?= $stats['total_attestations'] ?? 0 ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="formations-tab" data-toggle="tab" href="#formations" role="tab" aria-controls="formations" aria-selected="true">Mes Formations</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="exams-tab" data-toggle="tab" href="#exams" role="tab" aria-controls="exams" aria-selected="false">Mes Examens</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="attestations-tab" data-toggle="tab" href="#attestations" role="tab" aria-controls="attestations" aria-selected="false">Attestations Délivrées</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="pilots-tab" data-toggle="tab" href="#pilots" role="tab" aria-controls="pilots" aria-selected="false">Mes Pilotes</a>
                </li>
            </ul>
            
            <div class="tab-content" id="myTabContent">
                <!-- Onglet Formations -->
                <div class="tab-pane fade show active" id="formations" role="tabpanel" aria-labelledby="formations-tab">
                    <div class="card">
                        <div class="card-body">
                            <?php if (empty($formations)): ?>
                                <p>Vous n'êtes associé à aucune formation pour le moment.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Nom</th>
                                                <th>Description</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($formations as $formation): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($formation['name']) ?></td>
                                                    <td><?= htmlspecialchars(substr($formation['description'], 0, 100)) . (strlen($formation['description']) > 100 ? '...' : '') ?></td>
                                                    <td>
                                                        <a href="view_formation.php?id=<?= $formation['id'] ?>" class="btn btn-sm btn-info">Voir</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Onglet Examens -->
                <div class="tab-pane fade" id="exams" role="tabpanel" aria-labelledby="exams-tab">
                    <div class="card">
                        <div class="card-body">
                            <?php if (empty($exams)): ?>
                                <p>Vous n'avez créé aucun examen pour le moment.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Formation</th>
                                                <th>Titre</th>
                                                <th>Date de création</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($exams as $exam): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($exam['formation_name']) ?></td>
                                                    <td><?= htmlspecialchars($exam['title']) ?></td>
                                                    <td><?= date('d/m/Y H:i', strtotime($exam['created_at'])) ?></td>
                                                    <td>
                                                        <a href="view_exam.php?id=<?= $exam['id'] ?>" class="btn btn-sm btn-info">Voir</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Onglet Attestations -->
                <div class="tab-pane fade" id="attestations" role="tabpanel" aria-labelledby="attestations-tab">
                    <div class="card">
                        <div class="card-body">
                            <?php if (empty($attestations)): ?>
                                <p>Vous n'avez délivré aucune attestation pour le moment.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Pilote</th>
                                                <th>Formation</th>
                                                <th>Date de délivrance</th>
                                                <th>Date d'expiration</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($attestations as $attestation): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($attestation['firstname'] . ' ' . $attestation['lastname']) ?></td>
                                                    <td><?= htmlspecialchars($attestation['formation_name']) ?></td>
                                                    <td><?= date('d/m/Y', strtotime($attestation['issue_date'])) ?></td>
                                                    <td><?= date('d/m/Y', strtotime($attestation['expiry_date'])) ?></td>
                                                    <td>
                                                        <a href="view_attestation.php?id=<?= $attestation['id'] ?>" class="btn btn-sm btn-info">Voir</a>
                                                        <a href="edit_attestation.php?id=<?= $attestation['id'] ?>" class="btn btn-sm btn-warning">Modifier</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Onglet Pilotes -->
                <div class="tab-pane fade" id="pilots" role="tabpanel" aria-labelledby="pilots-tab">
                    <div class="card">
                        <div class="card-body">
                            <?php if (empty($pilots)): ?>
                                <p>Vous n'avez formé aucun pilote pour le moment.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Nom</th>
                                                <th>Email</th>
                                                <th>Attestations</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pilots as $pilot): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($pilot['firstname'] . ' ' . $pilot['lastname']) ?></td>
                                                    <td><?= htmlspecialchars($pilot['email']) ?></td>
                                                    <td><?= $pilot['attestation_count'] ?></td>
                                                    <td>
                                                        <a href="view_pilots.php?id=<?= $pilot['id'] ?>" class="btn btn-sm btn-info">Voir profil</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Modifier Profil -->
<div class="modal fade" id="editProfileModal" tabindex="-1" role="dialog" aria-labelledby="editProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editProfileModalLabel">Modifier le profil</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="firstname">Prénom</label>
                        <input type="text" class="form-control" id="firstname" name="firstname" value="<?= htmlspecialchars($instructor['firstname']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="lastname">Nom</label>
                        <input type="text" class="form-control" id="lastname" name="lastname" value="<?= htmlspecialchars($instructor['lastname']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($instructor['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Téléphone</label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($instructor['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="bio">Biographie</label>
                        <textarea class="form-control" id="bio" name="bio" rows="4"><?= htmlspecialchars($instructor['bio'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    <button type="submit" name="update_profile" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Changer Mot de Passe -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" role="dialog" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changePasswordModalLabel">Changer le mot de passe</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="current_password">Mot de passe actuel</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">Nouveau mot de passe</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <small class="form-text text-muted">Le mot de passe doit contenir au moins 8 caractères.</small>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirmer le nouveau mot de passe</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    <button type="submit" name="change_password" class="btn btn-primary">Changer le mot de passe</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; // Chemin incorrect vers le fichier de pied de page ?>
