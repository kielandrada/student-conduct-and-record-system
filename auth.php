<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const STAFF_CATEGORIES = [
    'discipline_committee' => ['Discipline Committee', 'staff.php'],
    'guidance_counselor' => ['Guidance Councilor', 'staff.php'],
    'school_head' => ['School Head', 'admin.php'],
];

function dashboardFor(string $role, ?string $category): ?string
{
    if ($role === 'student') {
        return 'student.php';
    }

    if ($role === 'staff' && in_array($category, ['discipline_committee', 'guidance_counselor'], true)) {
        return 'staff.php';
    }
    if (in_array($role, ['staff', 'admin'], true) && $category === 'school_head') {
        return 'admin.php';
    }

    return null;
}

function currentDashboard(): ?string
{
    return dashboardFor(
        $_SESSION['role'] ?? '',
        $_SESSION['staff_category'] ?? null
    );
}

function loginUser(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['gmail'] = $user['gmail'];
    $_SESSION['role'] = $user['role'] === 'student' ? 'student' : $user['role'];
    $_SESSION['staff_category'] = $user['staff_category'];
}

function verifyUserPassword(string $storedPassword, string $providedPassword): bool
{
    if (password_verify($providedPassword, $storedPassword)) {
        return true;
    }

    return preg_match('/\A[a-f0-9]{32}\z/i', $storedPassword) === 1
        && hash_equals(strtolower($storedPassword), md5($providedPassword));
}

function requireLogin(string $role, array $categories = []): void
{
    $category = $_SESSION['staff_category'] ?? null;
    $sessionRole = $_SESSION['role'] ?? '';
    $roleMatches = $role === 'admin'
        ? in_array($sessionRole, ['staff', 'admin'], true) && $category === 'school_head'
        : $sessionRole === $role;
    $categoryMatches = $categories === []
        || (is_string($category) && in_array($category, $categories, true));

    if (!$roleMatches || !$categoryMatches) {
        header('Location: index.php');
        exit();
    }
}