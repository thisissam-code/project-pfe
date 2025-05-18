document.addEventListener("DOMContentLoaded", () => {
  const splashScreen = document.getElementById("splashScreen")
  const logo = document.getElementById("logo")
  const loginForm = document.getElementById("loginForm")
  const togglePassword = document.getElementById("togglePassword")
  const password = document.getElementById("password")
  const mobileMenuBtn = document.getElementById("mobileMenuBtn")
  const navbarMenu = document.getElementById("navbarMenu")
  const footer = document.getElementById("footer")
  const footerColumns = document.querySelectorAll(".footer-column")
  const footerBottom = document.querySelector(".footer-bottom")

  // Add a slight animation to the logo
  setTimeout(() => {
    logo.style.transform = "scale(1.05)"

    setTimeout(() => {
      logo.style.transform = "scale(1)"
    }, 300)
  }, 500)

  // Start the transition after a delay
  setTimeout(() => {
    // Fade out the splash screen
    splashScreen.style.opacity = "0"

    // Show the login form
    loginForm.classList.add("visible")

    // Make footer elements visible
    footerColumns.forEach((column) => {
      column.classList.add("visible")
    })
    footerBottom.classList.add("visible")

    // Remove the splash screen from the flow after animation completes
    setTimeout(() => {
      splashScreen.style.display = "none"
    }, 1500)
  }, 2500) // Wait 2.5 seconds before starting the transition

  // Toggle password visibility
  if (togglePassword && password) {
    togglePassword.addEventListener("click", function () {
      const type = password.getAttribute("type") === "password" ? "text" : "password"
      password.setAttribute("type", type)

      // Toggle eye icon
      this.classList.toggle("fa-eye")
      this.classList.toggle("fa-eye-slash")
    })
  }

  // Mobile menu toggle
  if (mobileMenuBtn && navbarMenu) {
    mobileMenuBtn.addEventListener("click", () => {
      navbarMenu.classList.toggle("active")
    })
  }

  // Animate footer elements
  if (footerColumns.length > 0) {
    footerColumns.forEach((column, index) => {
      setTimeout(() => {
        column.classList.add("visible")
      }, 300 * index)
    })
  }

  if (footerBottom) {
    setTimeout(() => {
      footerBottom.classList.add("visible")
    }, 300 * footerColumns.length)
  }
})

