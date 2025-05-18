<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est connecté et est un admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Vérifier si un ID de document est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: library.php");
    exit();
}

$document_id = intval($_GET['id']);

// Récupérer les informations du document
$query = $conn->prepare("
    SELECT * FROM documents WHERE id = ?
");
$query->bind_param("i", $document_id);
$query->execute();
$result = $query->get_result();

if ($result->num_rows === 0) {
    header("Location: library.php");
    exit();
}

$document = $result->fetch_assoc();

// Traitement du formulaire de modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    
    // Champ catégorie supprimé
    
    // Vérifier si un nouveau fichier a été téléchargé
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $file_name = $_FILES['document']['name'];
        $file_tmp = $_FILES['document']['tmp_name'];
        $file_size = $_FILES['document']['size'];
        
        // Extraire l'extension du fichier
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Vérifier l'extension du fichier
        $allowed_extensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar'];
        
        if (in_array($file_ext, $allowed_extensions)) {
            // Vérifier la taille du fichier (max 20MB)
            if ($file_size <= 20000000) {
                // Créer un nom de fichier unique
                $new_file_name = uniqid() . '_' . $file_name;
                
                // Définir le chemin de destination
                $upload_dir = 'uploads/documents/';
                $server_dir = '../' . $upload_dir;
                
                // Créer le répertoire s'il n'existe pas
                if (!is_dir($server_dir)) {
                    mkdir($server_dir, 0755, true);
                }
                
                $destination = $server_dir . $new_file_name;
                $db_path = $upload_dir . $new_file_name;
                
                // Déplacer le fichier téléchargé vers le répertoire de destination
                if (move_uploaded_file($file_tmp, $destination)) {
                    // Supprimer l'ancien fichier s'il existe
                    $old_file_path = '../' . $document['file_path'];
                    if (file_exists($old_file_path)) {
                        unlink($old_file_path);
                    }
                    
                    // Mettre à jour les informations du document dans la base de données avec le nouveau fichier
                    $stmt = $conn->prepare("
                        UPDATE documents 
                        SET title = ?, description = ?, file_path = ?, file_type = ?
                        WHERE id = ?
                    ");
                    $stmt->bind_param("ssssi", $title, $description, $db_path, $file_ext, $document_id);
                } else {
                    $message = "Erreur lors du téléchargement du fichier.";
                    $message_type = "error";
                }
            } else {
                $message = "Le fichier est trop volumineux. Taille maximale: 20MB.";
                $message_type = "error";
            }
        } else {
            $message = "Type de fichier non autorisé. Extensions autorisées: " . implode(', ', $allowed_extensions);
            $message_type = "error";
        }
    } else {
        // Mettre à jour uniquement les informations textuelles
        $stmt = $conn->prepare("
            UPDATE documents 
            SET title = ?, description = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssi", $title, $description, $document_id);
    }
    
    // Exécuter la requête de mise à jour
    if (isset($stmt) && $stmt->execute()) {
        $message = "Document mis à jour avec succès.";
        $message_type = "success";
        
        // Rediriger vers la bibliothèque après un court délai
        header("Refresh: 2; URL=library.php");
    } else {
        $message = "Erreur lors de la mise à jour du document.";
        $message_type = "error";
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un Document - TTA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_dashboard.css">
    <style>
        .form-container {
            background-color: #fff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .form-title {
            margin-top: 0;
            margin-bottom: 20px;
            color: #0a3d91;
            font-size: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            border-color: #0a3d91;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(10, 61, 145, 0.25);
        }
        
        textarea.form-control {
            min-height: 120px;
        }
        
        .form-text {
            display: block;
            margin-top: 5px;
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .btn-submit {
            background-color: #0a3d91;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.2s ease;
        }
        
        .btn-submit:hover {
            background-color: #072a66;
        }
        
        .btn-cancel {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
            transition: background-color 0.2s ease;
        }
        
        .btn-cancel:hover {
            background-color: #5a6268;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .file-upload {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .file-upload-label {
            display: block;
            padding: 30px;
            background-color: #f8f9fa;
            border: 2px dashed #ced4da;
            border-radius: 4px;
            text-align: center;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .file-upload-label:hover {
            background-color: #e9ecef;
        }
        
        .file-upload-input {
            position: absolute;
            left: 0;
            top: 0;
            right: 0;
            bottom: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        .file-upload-icon {
            font-size: 2rem;
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .file-upload-text {
            font-size: 1rem;
            color: #495057;
        }
        
        .file-upload-info {
            margin-top: 5px;
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .current-file {
            background-color: #e9ecef;
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        
        .current-file-icon {
            font-size: 1.5rem;
            margin-right: 10px;
        }
        
        .current-file-info {
            flex-grow: 1;
        }
        
        .current-file-name {
            font-weight: 500;
        }
        
        .current-file-type {
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .datalist-input {
            position: relative;
        }
        
        .datalist-input input {
            width: 100%;
        }
    </style>
</head>
<body>
    <!-- Mobile Navigation Toggle -->
    <button class="mobile-nav-toggle">
        <i class="fas fa-bars"></i>
    </button>

    <?php include '../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Modifier un Document</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="admin_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item"><a href="library.php" class="breadcrumb-link">Bibliothèque</a></li>
                <li class="breadcrumb-item">Modifier</li>
            </ul>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert <?php echo $message_type === 'success' ? 'alert-success' : 'alert-danger'; ?>">
                <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> 
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <h2 class="form-title">Modifier les informations du document</h2>
            <form action="edit_library.php?id=<?php echo $document_id; ?>" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title" class="form-label">Titre du document *</label>
                    <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($document['title']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" class="form-control"><?php echo htmlspecialchars($document['description'] ?? ''); ?></textarea>
                    <small class="form-text">Une brève description du contenu du document.</small>
                </div>
                
                
                
                
                
                <div class="form-group">
                    <label class="form-label">Fichier actuel</label>
                    <div class="current-file">
                        <?php
                        $icon_class = 'fa-file';
                        $file_type = strtolower($document['file_type']);
                        
                        switch ($file_type) {
                            case 'pdf':
                                $icon_class = 'fa-file-pdf';
                                break;
                            case 'docx':
                            case 'doc':
                                $icon_class = 'fa-file-word';
                                break;
                            case 'pptx':
                            case 'ppt':
                                $icon_class = 'fa-file-powerpoint';
                                break;
                            case 'xlsx':
                            case 'xls':
                                $icon_class = 'fa-file-excel';
                                break;
                            case 'jpg':
                            case 'jpeg':
                            case 'png':
                            case 'gif':
                                $icon_class = 'fa-file-image';
                                break;
                            case 'zip':
                            case 'rar':
                                $icon_class = 'fa-file-archive';
                                break;
                        }
                        ?>
                        <div class="current-file-icon">
                            <i class="fas <?php echo $icon_class; ?>"></i>
                        </div>
                        <div class="current-file-info">
                            <div class="current-file-name"><?php echo basename($document['file_path']); ?></div>
                            <div class="current-file-type"><?php echo strtoupper($document['file_type']); ?></div>
                        </div>
                        <a href="../<?php echo htmlspecialchars($document['file_path']); ?>" class="btn btn-primary" target="_blank">
                            <i class="fas fa-download"></i> Télécharger
                        </a>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="document" class="form-label">Nouveau fichier (optionnel)</label>
                    <div class="file-upload">
                        <label for="document" class="file-upload-label">
                            <div class="file-upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="file-upload-text">Cliquez ou glissez-déposez un fichier ici pour remplacer l'actuel</div>
                            <div class="file-upload-info">Formats acceptés: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, JPG, PNG, GIF, ZIP, RAR</div>
                        </label>
                        <input type="file" id="document" name="document" class="file-upload-input">
                    </div>
                    <small class="form-text">Taille maximale: 20MB. Laissez vide pour conserver le fichier actuel.</small>
                </div>
                
                <div>
                    <a href="library.php" class="btn-cancel">Annuler</a>
                    <button type="submit" class="btn-submit">Enregistrer les modifications</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../scripts/admin_dashboard.js"></script>
    <script>
        // Afficher le nom du fichier sélectionné
        document.getElementById('document').addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                const fileName = e.target.files[0].name;
                const fileSize = (e.target.files[0].size / 1024 / 1024).toFixed(2); // en MB
                
                const uploadText = document.querySelector('.file-upload-text');
                uploadText.textContent = `Nouveau fichier sélectionné: ${fileName} (${fileSize} MB)`;
            }
        });
    </script>
</body>
</html>
