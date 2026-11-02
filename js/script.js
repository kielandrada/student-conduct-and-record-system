// This runs once the page has loaded, and it makes the login form interactive.
document.addEventListener('DOMContentLoaded', function () {
  // Get all role options so we can detect which one is selected.
  const roleInputs = document.querySelectorAll('input[name="userType"]');
  const staffCategoryField = document.getElementById('staffCategoryField');
  const staffCategory = document.getElementById('staffCategory');

  // These elements will change depending on the selected role.
  const signInButton = document.getElementById('signInButton');
  const formTitle = document.querySelector('.form-title');
  const subtitle = document.querySelector('.subtitle');
  const brandIcon = document.querySelector('.brand-icon');
  const brandIconElement = brandIcon.querySelector('i');

  // Map each role to its label and icon.
  const roleText = {
    student: 'Student',
    staff: 'Staff'
  };

  const roleIcons = {
    student: 'fas fa-user-graduate',
    staff: 'fas fa-user-tie'
  };

  // Update the system text and icon when the user picks a role.
  function setRole(role) {
    const label = roleText[role] || 'Student';
    const iconClass = roleIcons[role] || roleIcons.student;

    signInButton.textContent = `Sign In as ${label}`;
    formTitle.textContent = `${label} Portal`;
    subtitle.textContent = `Sign in as ${label.toLowerCase()} to continue`;

    brandIcon.classList.remove('student', 'staff');
    brandIcon.classList.add(role);
    brandIconElement.className = iconClass;
  }

  function updateStaffCategory() {
    const isStaff = document.querySelector('input[name="userType"]:checked')?.value === 'staff';
    staffCategoryField.hidden = !isStaff;
    staffCategory.required = isStaff;
    if (isStaff) {
      const selectedLabel = staffCategory.options[staffCategory.selectedIndex]?.text;
      signInButton.textContent = selectedLabel && staffCategory.value
        ? `Sign In as ${selectedLabel}`
        : 'Sign In as Staff';
    }
  }

  // Listen for a role change and update the text right away.
  roleInputs.forEach((input) => {
    input.addEventListener('change', function () {
      setRole(this.value);
      updateStaffCategory();
    });
  });
  staffCategory.addEventListener('change', updateStaffCategory);

  // Apply the selected role when the page loads.
  const checkedRole = document.querySelector('input[name="userType"]:checked');
  if (checkedRole) {
    setRole(checkedRole.value);
  }
  updateStaffCategory();
});
