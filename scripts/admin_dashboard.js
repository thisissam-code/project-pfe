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

      // Change the icon direction
      const icon = sidebarToggle.querySelector("i")
      if (sidebar.classList.contains("sidebar-collapsed")) {
        icon.classList.remove("fa-chevron-left")
        icon.classList.add("fa-chevron-right")
      } else {
        icon.classList.remove("fa-chevron-right")
        icon.classList.add("fa-chevron-left")
      }
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

  // Initialize tooltips
  const tooltipElements = document.querySelectorAll("[data-tooltip]")
  tooltipElements.forEach((element) => {
    const tooltip = document.createElement("div")
    tooltip.className = "tooltip"
    tooltip.textContent = element.getAttribute("data-tooltip")
    element.appendChild(tooltip)
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

  // Profile tabs
  const profileTabs = document.querySelectorAll(".profile-tab")
  const profileContents = document.querySelectorAll(".profile-content")

  profileTabs.forEach((tab) => {
    tab.addEventListener("click", function () {
      // Remove active class from all tabs and contents
      profileTabs.forEach((t) => t.classList.remove("active"))
      profileContents.forEach((c) => c.classList.remove("active"))

      // Add active class to clicked tab and corresponding content
      this.classList.add("active")
      const contentId = this.getAttribute("data-tab")
      document.getElementById(contentId)?.classList.add("active")
    })
  })

  // Add scroll animations
  function handleScrollAnimations() {
    const elements = document.querySelectorAll(".animate-on-scroll")

    elements.forEach((element) => {
      const elementTop = element.getBoundingClientRect().top
      const elementVisible = 150

      if (elementTop < window.innerHeight - elementVisible) {
        element.classList.add("animated")
      }
    })
  }

  // Run on initial load
  handleScrollAnimations()

  // Listen for scroll events
  window.addEventListener("scroll", handleScrollAnimations)

  // Modify the sidebar toggle button position
  const updateSidebarTogglePosition = () => {
    const sidebarToggle = document.querySelector(".sidebar-toggle")
    if (sidebarToggle) {
      const sidebar = document.querySelector(".sidebar")
      const sidebarHeight = sidebar.offsetHeight

      // Position the toggle button in the middle of the sidebar
      sidebarToggle.style.top = `${sidebarHeight / 2}px`
      sidebarToggle.style.transform = "translateY(-50%)"
    }
  }

  // Run on initial load and window resize
  updateSidebarTogglePosition()
  window.addEventListener("resize", updateSidebarTogglePosition)

  // Add decorative elements to the background
  const addBackgroundDecorations = () => {
    const mainContent = document.querySelector(".main-content")
    if (mainContent) {
      // Create decorative elements
      const createDecorativeElement = (className, size, position) => {
        const element = document.createElement("div")
        element.className = `decorative-element ${className}`
        element.style.width = `${size}px`
        element.style.height = `${size}px`
        element.style.position = "absolute"
        element.style.borderRadius = "50%"
        element.style.opacity = "0.05"
        element.style.zIndex = "-1"

        if (position.top) element.style.top = position.top
        if (position.bottom) element.style.bottom = position.bottom
        if (position.left) element.style.left = position.left
        if (position.right) element.style.right = position.right

        return element
      }

      // Add decorative circles
      const circle1 = createDecorativeElement("circle-1", 300, { top: "10%", right: "5%" })
      circle1.style.background = "radial-gradient(circle, #0a3d91 0%, transparent 70%)"

      const circle2 = createDecorativeElement("circle-2", 200, { bottom: "20%", left: "10%" })
      circle2.style.background = "radial-gradient(circle, #f39200 0%, transparent 70%)"

      const circle3 = createDecorativeElement("circle-3", 150, { top: "40%", left: "30%" })
      circle3.style.background = "radial-gradient(circle, #0a3d91 0%, transparent 70%)"

      mainContent.appendChild(circle1)
      mainContent.appendChild(circle2)
      mainContent.appendChild(circle3)
    }
  }

  // Add background decorations
  addBackgroundDecorations()
})

