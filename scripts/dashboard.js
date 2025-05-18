document.addEventListener("DOMContentLoaded", () => {
    // Sidebar toggle functionality
    const sidebarToggle = document.querySelector(".sidebar-toggle")
    const sidebar = document.querySelector(".sidebar")
    const mainContent = document.querySelector(".main-content")
    const mobileNavToggle = document.querySelector(".mobile-nav-toggle")
  
    if (sidebarToggle) {
      sidebarToggle.addEventListener("click", () => {
        sidebar.classList.toggle("sidebar-collapsed")
        mainContent.classList.toggle("expanded")
      })
    }
  
    if (mobileNavToggle) {
      mobileNavToggle.addEventListener("click", () => {
        sidebar.classList.toggle("mobile-visible")
      })
    }
  
    // Close sidebar when clicking outside on mobile
    document.addEventListener("click", (event) => {
      if (
        window.innerWidth < 768 &&
        sidebar.classList.contains("mobile-visible") &&
        !sidebar.contains(event.target) &&
        !mobileNavToggle.contains(event.target)
      ) {
        sidebar.classList.remove("mobile-visible")
      }
    })
  
    // Add fade-in animation to stat cards
    const statCards = document.querySelectorAll(".stat-card")
    statCards.forEach((card) => {
      card.classList.add("fade-in")
    })
  
    // Initialize tooltips if any
    const tooltips = document.querySelectorAll("[data-tooltip]")
    tooltips.forEach((tooltip) => {
      tooltip.addEventListener("mouseenter", function () {
        const tooltipText = this.getAttribute("data-tooltip")
        const tooltipEl = document.createElement("div")
        tooltipEl.className = "tooltip"
        tooltipEl.textContent = tooltipText
        document.body.appendChild(tooltipEl)
  
        const rect = this.getBoundingClientRect()
        tooltipEl.style.top = rect.top - tooltipEl.offsetHeight - 10 + "px"
        tooltipEl.style.left = rect.left + rect.width / 2 - tooltipEl.offsetWidth / 2 + "px"
        tooltipEl.style.opacity = "1"
      })
  
      tooltip.addEventListener("mouseleave", () => {
        const tooltipEl = document.querySelector(".tooltip")
        if (tooltipEl) {
          tooltipEl.remove()
        }
      })
    })
  
    // Form validation
    const forms = document.querySelectorAll("form")
    forms.forEach((form) => {
      form.addEventListener("submit", (event) => {
        const requiredFields = form.querySelectorAll("[required]")
        let isValid = true
  
        requiredFields.forEach((field) => {
          if (!field.value.trim()) {
            isValid = false
            field.classList.add("is-invalid")
          } else {
            field.classList.remove("is-invalid")
          }
        })
  
        if (!isValid) {
          event.preventDefault()
          const errorMessage = document.createElement("div")
          errorMessage.className = "alert alert-danger mt-3"
          errorMessage.textContent = "Veuillez remplir tous les champs obligatoires."
          form.appendChild(errorMessage)
  
          setTimeout(() => {
            errorMessage.remove()
          }, 3000)
        }
      })
    })
  
    // Password toggle
    const passwordToggles = document.querySelectorAll(".password-toggle")
    passwordToggles.forEach((toggle) => {
      toggle.addEventListener("click", function () {
        const passwordField = this.previousElementSibling
        const type = passwordField.getAttribute("type") === "password" ? "text" : "password"
        passwordField.setAttribute("type", type)
        this.innerHTML = type === "password" ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>'
      })
    })
  
    // Confirm delete
    const deleteButtons = document.querySelectorAll(".btn-delete")
    deleteButtons.forEach((button) => {
      button.addEventListener("click", (event) => {
        if (!confirm("Êtes-vous sûr de vouloir supprimer cet élément ?")) {
          event.preventDefault()
        }
      })
    })
  })
  
  