<?php $current_page = basename($_SERVER['PHP_SELF']); ?>

<nav class="w-80 sidebar-gradient text-slate-300 flex flex-col flex-shrink-0 shadow-2xl h-screen" style="perspective: 1000px;">
    
    <div class="p-8 border-b border-emerald-900/50">
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-[#FFD700] rounded-xl flex items-center justify-center shadow-[0_4px_20px_rgba(251,192,45,0.4)] border-2 border-emerald-500/20 flex-shrink-0">
            <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 3C10 3 9 5 9 8V12C9 15 10 17 12 17C14 17 15 15 15 12V8C15 5 14 3 12 3Z" fill="#F59E0B"/>
                <path d="M11 6H13M10.5 9H13.5M11 12H13" stroke="#B45309" stroke-width="1.2" stroke-linecap="round"/>
                <path d="M12 17C12 17 7 15 6 9C6 6 8 3 8 3C8 3 7 7 7 10C7 14 10 17 12 19V17Z" fill="#059669"/>
                <path d="M12 17C12 17 17 15 18 9C18 6 16 3 16 3C16 3 17 7 17 10C17 14 14 17 12 19V17Z" fill="#10B981"/>
                <rect x="11.5" y="18" width="1" height="3" rx="0.5" fill="#064E3B"/>
            </svg>
        </div>

        <div>
            <h1 class="text-white font-extrabold text-sm leading-tight tracking-tighter uppercase">CITY AGRICULTURAL SERVICES</h1>
            <p class="text-yellow-500 text-[11px] font-bold uppercase tracking-[0.2em] mt-1">Corn Team Portal</p>
        </div>
    </div>
</div>

    <div class="flex-1 py-8 px-4 space-y-4 overflow-y-auto custom-scrollbar">
        <p class="px-4 text-[10px] font-black text-emerald-500 uppercase tracking-widest mb-2 opacity-70">Main Menu</p>
        
        <a href="dashboard.php" class="nav-link group <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <div class="nav-icon-box">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A2 2 0 013 15.487V6.513a2 2 0 011.553-1.943L9 2l5.447 2.724A2 2 0 0116 6.513v8.974a2 2 0 01-1.553 1.943L9 20z" />
                </svg>
            </div>
            <span class="text-sm font-bold tracking-wide">Dashboard / Map</span>
        </a>

        <a href="reports.php" class="nav-link group <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
            <div class="nav-icon-box">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <span class="text-sm font-bold tracking-wide">View Reports</span>
        </a>

        <a href="analytics.php" class="nav-link group <?php echo ($current_page == 'analytics.php') ? 'active' : ''; ?>">
            <div class="nav-icon-box">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <span class="text-sm font-bold tracking-wide">Data Analysis</span>
        </a>

        <a href="farmers.php" class="nav-link group <?php echo ($current_page == 'farmers.php') ? 'active' : ''; ?>">
            <div class="nav-icon-box">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </div>
            <span class="text-sm font-bold tracking-wide">Farmer Records</span>
        </a>
        <!-- Account Manager — admin-only. Employees (role = 'casd') must never see this tab. -->
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
        <a href="accounts.php" class="nav-link group <?php echo ($current_page == 'accounts.php') ? 'active' : ''; ?>">
            <div class="nav-icon-box">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>
            <span class="text-sm font-bold tracking-wide">Account Manager</span>
        </a>
        <?php endif; ?>
    </div>

 <div class="p-6 bg-black/30 border-t border-emerald-900/50">
    <a href="logout.php" class="group relative flex items-center gap-4 px-6 py-4 bg-red-500/5 hover:bg-red-500 text-red-400 hover:text-white rounded-2xl transition-all duration-300 border border-red-500/20 hover:border-red-500 shadow-lg hover:shadow-red-500/40 overflow-hidden">
        
        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:animate-[shimmer_1.5s_infinite]"></div>
        
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 rotate-180 transition-transform group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
        </svg>
        
        <span class="font-black text-[11px] tracking-[0.2em] uppercase">
            Log Out
        </span>
    </a>
    </div>
</nav>

<style>
    /* 3D Link Styling */
    .nav-link {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        border-radius: 0.75rem;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        transform-style: preserve-3d;
        position: relative;
    }

    .nav-link:hover {
        background: rgba(255, 255, 255, 0.05);
        transform: translateZ(20px) translateX(10px) rotateY(-10deg);
        color: #fbc02d;
    }

    .nav-link.active {
        background: linear-gradient(90deg, rgba(251, 192, 45, 0.15) 0%, transparent 100%);
        color: #fbc02d;
        border-left: 4px solid #fbc02d;
        transform: translateZ(30px) translateX(5px);
    }

    .nav-icon-box {
        padding: 0.5rem;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 0.5rem;
        transition: all 0.3s ease;
    }

    .nav-link:hover .nav-icon-box {
        background: rgba(251, 192, 45, 0.2);
        transform: translateZ(10px);
    }

    /* Animation for the Logout Shine */
    @keyframes shimmer {
        100% { transform: translateX(100%); }
    }

    /* Custom scrollbar for a cleaner look */
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(16, 185, 129, 0.2); border-radius: 10px; }
</style>