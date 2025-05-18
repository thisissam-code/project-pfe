document.addEventListener("DOMContentLoaded", () => {
  // Récupérer les éléments du formulaire
  const roleSelect = document.getElementById("role")
  const sectionSelect = document.getElementById("section")
  const fonctionSelect = document.getElementById("fonction")
  const entrepriseInput = document.getElementById("entreprise")

  // Fonction pour mettre à jour les champs en fonction du rôle
  function updateFieldsBasedOnRole() {
    const selectedRole = roleSelect.value

    // Réinitialiser les champs
    sectionSelect.disabled = false
    fonctionSelect.disabled = false
    entrepriseInput.disabled = false

    // Appliquer la logique conditionnelle en fonction du rôle
    if (selectedRole === "admin_sol") {
      // Fixer la section à "sol"
      sectionSelect.value = "sol"
      sectionSelect.disabled = true

      // Bloquer entreprise et fonction
      entrepriseInput.value = ""
      entrepriseInput.disabled = true

      fonctionSelect.value = ""
      fonctionSelect.disabled = true
    } else if (selectedRole === "admin_vol") {
      // Fixer la section à "vol"
      sectionSelect.value = "vol"
      sectionSelect.disabled = true

      // Bloquer entreprise et fonction
      entrepriseInput.value = ""
      entrepriseInput.disabled = true

      fonctionSelect.value = ""
      fonctionSelect.disabled = true
    }
  }

  // Exécuter la fonction au chargement de la page
  updateFieldsBasedOnRole()

  // Ajouter un écouteur d'événement pour le changement de rôle
  roleSelect.addEventListener("change", updateFieldsBasedOnRole)
})
