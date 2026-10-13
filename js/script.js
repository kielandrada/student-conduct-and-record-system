// This runs once the page has loaded, and it makes the login form interactive.
document.addEventListener('DOMContentLoaded', function () {
  // Get all role options so we can detect which one is selected.
  const roleInputs = document.querySelectorAll('input[name="userType"]');

  // These elements will change depending on the selected role.
  const signInButton = document.getElementById('signInButton');
  const formTitle = document.querySelector('.form-title');
  const subtitle = document.querySelector('.subtitle');
  const brandIcon = document.querySelector('.brand-icon');
  const brandIconElement = brandIcon.querySelector('i');

  // Map each role to its label and icon.
  const roleText = {
    student: 'Student',
    staff: 'Staff',
    admin: 'Admin'
  };

  const roleIcons = {
    student: 'fas fa-user-graduate',
    staff: 'fas fa-user-tie',
    admin: 'fas fa-shield-halved'
  };

  // Update the system text and icon when the user picks a role.
  function setRole(role) {
    const label = roleText[role] || 'Student';
    const iconClass = roleIcons[role] || roleIcons.student;

    signInButton.textContent = `Sign In as ${label}`;
    formTitle.textContent = `${label} Portal`;
    subtitle.textContent = `Sign in as ${label.toLowerCase()} to continue`;

    brandIcon.classList.remove('student', 'staff', 'admin');
    brandIcon.classList.add(role);
    brandIconElement.className = iconClass;
  }

  // Listen for a role change and update the text right away.
  roleInputs.forEach((input) => {
    input.addEventListener('change', function () {
      setRole(this.value);
    });
  });

  // Apply the selected role when the page loads.
  const checkedRole = document.querySelector('input[name="userType"]:checked');
  if (checkedRole) {
    setRole(checkedRole.value);
  }
});


