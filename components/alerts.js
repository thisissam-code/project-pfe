/**
 * Professional alert system using SweetAlert2
 * This file contains functions for displaying various types of alerts
 */

// Import SweetAlert2 (if not already included in your project)
// import Swal from 'sweetalert2';

// Confirmation dialog for delete actions
function confirmDelete(title, text, url) {
  Swal.fire({
    title: title || "Êtes-vous sûr?",
    text: text || "Cette action ne peut pas être annulée!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#0a3d91",
    cancelButtonColor: "#dc3545",
    confirmButtonText: "Oui, supprimer!",
    cancelButtonText: "Annuler",
    backdrop: true,
    customClass: {
      confirmButton: "btn btn-primary",
      cancelButton: "btn btn-danger",
    },
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = url
    }
  })
}

// Confirmation dialog for edit actions
function confirmEdit(title, text, url) {
  Swal.fire({
    title: title || "Confirmation",
    text: text || "Voulez-vous continuer?",
    icon: "question",
    showCancelButton: true,
    confirmButtonColor: "#0a3d91",
    cancelButtonColor: "#6c757d",
    confirmButtonText: "Oui, continuer",
    cancelButtonText: "Annuler",
    backdrop: true,
    customClass: {
      confirmButton: "btn btn-primary",
      cancelButton: "btn btn-secondary",
    },
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = url
    }
  })
}

// Success message
function showSuccess(title, text) {
  Swal.fire({
    title: title || "Succès!",
    text: text || "Opération réussie.",
    icon: "success",
    confirmButtonColor: "#28a745",
    confirmButtonText: "OK",
    customClass: {
      confirmButton: "btn btn-success",
    },
  })
}

// Error message
function showError(title, text) {
  Swal.fire({
    title: title || "Erreur!",
    text: text || "Une erreur est survenue.",
    icon: "error",
    confirmButtonColor: "#dc3545",
    confirmButtonText: "OK",
    customClass: {
      confirmButton: "btn btn-danger",
    },
  })
}

// Information message
function showInfo(title, text) {
  Swal.fire({
    title: title || "Information",
    text: text || "",
    icon: "info",
    confirmButtonColor: "#17a2b8",
    confirmButtonText: "OK",
    customClass: {
      confirmButton: "btn btn-info",
    },
  })
}

// Warning message
function showWarning(title, text) {
  Swal.fire({
    title: title || "Attention!",
    text: text || "",
    icon: "warning",
    confirmButtonColor: "#ffc107",
    confirmButtonText: "OK",
    customClass: {
      confirmButton: "btn btn-warning",
    },
  })
}

// Form validation errors
function showValidationErrors(errors) {
  let errorHtml = '<ul style="text-align: left; margin-top: 10px; list-style-type: none; padding-left: 0;">'
  errors.forEach((error) => {
    errorHtml += `<li><i class="fas fa-exclamation-circle text-danger"></i> ${error}</li>`
  })
  errorHtml += "</ul>"

  Swal.fire({
    title: "Erreur de validation",
    html: errorHtml,
    icon: "error",
    confirmButtonColor: "#dc3545",
    confirmButtonText: "OK",
    customClass: {
      confirmButton: "btn btn-danger",
    },
  })
}

// Toast notification (small notification at the corner)
function showToast(title, icon = "success", position = "top-end") {
  const Toast = Swal.mixin({
    toast: true,
    position: position,
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
      toast.addEventListener("mouseenter", Swal.stopTimer)
      toast.addEventListener("mouseleave", Swal.resumeTimer)
    },
  })

  Toast.fire({
    icon: icon,
    title: title,
  })
}

// Loading indicator
function showLoading(title = "Chargement...") {
  Swal.fire({
    title: title,
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading()
    },
  })
}

// Close loading indicator
function closeLoading() {
  Swal.close()
}

// Prompt for input
function promptInput(title, inputPlaceholder, callback) {
  Swal.fire({
    title: title,
    input: "text",
    inputPlaceholder: inputPlaceholder,
    showCancelButton: true,
    confirmButtonColor: "#0a3d91",
    cancelButtonColor: "#6c757d",
    confirmButtonText: "Confirmer",
    cancelButtonText: "Annuler",
    inputValidator: (value) => {
      if (!value) {
        return "Vous devez entrer une valeur!"
      }
    },
  }).then((result) => {
    if (result.isConfirmed) {
      callback(result.value)
    }
  })
}

// Initialize all delete buttons on the page
function initDeleteButtons() {
  const deleteButtons = document.querySelectorAll(".btn-delete")
  deleteButtons.forEach((button) => {
    button.addEventListener("click", (e) => {
      e.preventDefault()
      const url = button.getAttribute("href")
      const itemName = button.getAttribute("data-item-name") || "cet élément"

      confirmDelete(
        "Confirmer la suppression",
        `Êtes-vous sûr de vouloir supprimer ${itemName}? Cette action ne peut pas être annulée.`,
        url,
      )
    })
  })
}

// Initialize all edit confirmation buttons
function initEditButtons() {
  const editConfirmButtons = document.querySelectorAll(".btn-edit-confirm")
  editConfirmButtons.forEach((button) => {
    button.addEventListener("click", (e) => {
      e.preventDefault()
      const url = button.getAttribute("href")
      const itemName = button.getAttribute("data-item-name") || "cet élément"

      confirmEdit("Confirmer la modification", `Voulez-vous modifier ${itemName}?`, url)
    })
  })
}

// Initialize on document ready
document.addEventListener("DOMContentLoaded", () => {
  initDeleteButtons()
  initEditButtons()

  // Show success message if present in URL
  const urlParams = new URLSearchParams(window.location.search)
  const successMsg = urlParams.get("success")
  const errorMsg = urlParams.get("error")

  if (successMsg) {
    showToast(decodeURIComponent(successMsg))
  }

  if (errorMsg) {
    showToast(decodeURIComponent(errorMsg), "error")
  }
})

