<?php
/**
 * includes/layout.php
 * Master layout — included at the top of every protected page.
 * Starts session, enforces login, then opens the page shell.
 */
require_once __DIR__ . '/../src/session_guard.php';

// $pageTitle should be set by the including page BEFORE this include.
// Fallback just in case.
if (!isset($pageTitle)) $pageTitle = "Corn Portal";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> — CASD Corn Portal</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; overflow: hidden; }
        .sidebar-gradient { background: linear-gradient(180deg, #064e3b 0%, #022c22 100%); }
        .nav-link { transition: all 0.2s ease; border-left: 4px solid transparent; }
        .nav-link.active { background: rgba(251,192,45,0.1); color: #fbc02d; border-left: 4px solid #fbc02d; font-weight: 700; }
        .glass-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        main { display: flex; width: 100%; height: 100vh; }
        .content-area { flex-grow: 1; overflow-y: auto; overflow-x: hidden; background-color: #f8fafc; }
    </style>
</head>
<body class="flex h-screen w-screen">

    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="flex-1 flex flex-col overflow-hidden">

        <?php include __DIR__ . '/header.php'; ?>

        <div id="contentBody" class="p-10 overflow-y-auto bg-[#f8fafc] flex-1">