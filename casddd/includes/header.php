<?php
/**
 * header.php  — session-aware top bar
 * Reads the logged-in user from $_SESSION and populates the profile modal.
 * Modal theme restyled to match the "Farmer Details" card design.
 */

$sessionName  = $_SESSION['full_name']    ?? 'Admin User';
$sessionUser  = $_SESSION['username']     ?? 'user';
$sessionEmail = $_SESSION['email']        ?? '';
$sessionPhone = $_SESSION['phone']        ?? '';
$sessionEmpId = $_SESSION['employee_id']  ?? '';
$sessionRole  = strtoupper($_SESSION['role'] ?? 'CASD');
$sessionPhoto = $_SESSION['photo']        ?? '';

$photoUrl = $sessionPhoto
    ? 'uploads/' . htmlspecialchars($sessionPhoto)
    : 'https://ui-avatars.com/api/?name=' . urlencode($sessionName) . '&background=065f46&color=fff&bold=true';

$nameParts = explode(' ', trim($sessionName));
$initials  = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
?>

<header class="h-16 sm:h-20 bg-white border-b flex items-center justify-between px-4 sm:px-6 md:px-10 flex-shrink-0">
    <h2 class="text-emerald-900 font-extrabold text-base sm:text-lg truncate pr-3">
        <?php echo htmlspecialchars($pageTitle ?? 'Portal'); ?>
    </h2>

    <div onclick="toggleProfileModal()" class="flex items-center gap-2 sm:gap-4 cursor-pointer hover:bg-slate-50 p-1.5 sm:p-2 rounded-xl transition-all shrink-0">
        <div class="text-right hidden sm:block">
            <p id="displayUserName" class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($sessionName); ?></p>
            <p class="text-[10px] text-emerald-600 font-bold uppercase"><?php echo $sessionRole; ?> Officer</p>
        </div>
        <?php if ($sessionPhoto): ?>
        <img src="<?php echo $photoUrl; ?>" alt="Profile"
             class="w-9 h-9 sm:w-10 sm:h-10 rounded-full object-cover border-2 border-emerald-200"
             onerror="this.style.display='none'; document.getElementById('headerInitials').style.display='flex';">
        <div id="headerInitials" class="w-9 h-9 sm:w-10 sm:h-10 bg-emerald-100 rounded-full items-center justify-center font-bold text-emerald-700 border border-emerald-200 hidden">
            <?php echo $initials; ?>
        </div>
        <?php else: ?>
        <div class="w-9 h-9 sm:w-10 sm:h-10 bg-emerald-100 rounded-full flex items-center justify-center font-bold text-emerald-700 border border-emerald-200">
            <?php echo $initials; ?>
        </div>
        <?php endif; ?>
    </div>
</header>

<!-- ═══════════════════════════════════════════
     PROFILE MODAL — themed like the Farmer Details card
════════════════════════════════════════════ -->
<div id="profileModal" class="fixed inset-0 z-[60] hidden flex items-center justify-center p-0 sm:p-6"
     style="background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(12px);">
    <div class="relative bg-white rounded-t-[2rem] sm:rounded-[2rem] w-full max-w-xl shadow-2xl overflow-hidden flex flex-col max-h-[95vh] sm:max-h-[90vh] mt-auto sm:mt-0">

        <!-- Card Header — light, avatar + name + meta + status pill, circular close btn -->
        <div class="px-5 sm:px-6 pt-5 sm:pt-6 pb-4 sm:pb-5 flex items-start justify-between gap-3 shrink-0 border-b border-slate-100">
            <div class="flex items-center gap-3 sm:gap-4 min-w-0">
                <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl overflow-hidden bg-slate-100 border border-slate-200 shrink-0">
                    <img id="profilePreview" src="<?php echo $photoUrl; ?>" alt="Profile" class="w-full h-full object-cover">
                </div>
                <div class="min-w-0">
                    <p class="text-[9px] sm:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Account Profile</p>
                    <h3 id="viewFullName" class="text-lg sm:text-xl font-black text-slate-800 tracking-tight leading-tight truncate"><?php echo htmlspecialchars($sessionName); ?></h3>
                    <div class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-[11px] sm:text-xs text-slate-400 font-bold mt-1">
                        <span>#<?php echo htmlspecialchars($sessionEmpId ?: '—'); ?></span>
                        <span>·</span>
                        <span id="viewUsername">@<?php echo htmlspecialchars($sessionUser); ?></span>
                        <span class="hidden sm:inline">·</span>
                        <span class="hidden sm:inline"><?php echo $sessionRole; ?></span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded-full text-[10px] font-black uppercase tracking-wide">
                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span> Active
                        </span>
                    </div>
                </div>
            </div>
            <button onclick="toggleProfileModal()"
                    class="w-8 h-8 sm:w-9 sm:h-9 rounded-full border border-slate-200 text-slate-400 flex items-center justify-center hover:bg-slate-50 hover:text-slate-600 transition-all shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-5 sm:p-6 md:p-8 overflow-y-auto space-y-6">

            <!-- ══════════════════════
                 VIEW MODE — plain field rows w/ dividers, like Farmer Info tab
            ══════════════════════ -->
            <div id="viewMode" class="space-y-5">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-8">
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Full Name</p>
                        <p class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars($sessionName); ?></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Username</p>
                        <p class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars($sessionUser); ?></p>
                    </div>
                    <div class="pt-4 border-t border-slate-100 md:border-t-0 md:pt-0">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Email</p>
                        <p id="viewEmail" class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars($sessionEmail) ?: '—'; ?></p>
                    </div>
                    <div class="pt-4 border-t border-slate-100">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Phone Number</p>
                        <p id="viewPhone" class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars($sessionPhone) ?: '—'; ?></p>
                    </div>
                </div>

                <!-- LOGIN ACCOUNT section — dark navy card, matches reference -->
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 11-12 0 6 6 0 0112 0zM9 15l-4.5 4.5M9 15l1.5 1.5" />
                        </svg>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Login Account</p>
                        <span class="flex-1 h-px bg-slate-100"></span>
                    </div>

                    <div class="bg-slate-900 rounded-2xl p-4 sm:p-5 space-y-4">
                        <div class="grid grid-cols-1 xs:grid-cols-2 sm:grid-cols-2 gap-3 sm:gap-4">
                            <div class="bg-white/5 rounded-xl px-4 py-3 min-w-0">
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Username</p>
                                <p class="text-sm font-bold text-white truncate"><?php echo htmlspecialchars($sessionUser); ?></p>
                            </div>
                            <div class="bg-white/5 rounded-xl px-4 py-3 min-w-0">
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Status</p>
                                <p class="text-sm font-bold text-emerald-400">Active</p>
                            </div>
                        </div>
                        <div class="bg-white/5 rounded-xl px-4 py-3">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Password</p>
                            <p class="text-sm font-bold text-white tracking-widest">••••••••</p>
                        </div>
                    </div>
                </div>

                <p class="text-[10px] text-slate-400 text-center">Click <span class="font-black text-emerald-600">Edit Profile</span> below to update your information.</p>
            </div>

            <!-- ══════════════════════
                 EDIT MODE (hidden by default)
            ══════════════════════ -->
            <form id="editMode" class="hidden space-y-6" enctype="multipart/form-data">

                <!-- Photo upload row -->
                <div id="photoUploadLabel" class="hidden flex items-center gap-4 bg-slate-50 rounded-2xl p-4 border border-slate-100">
                    <div class="w-14 h-14 rounded-xl overflow-hidden bg-white border border-slate-200 shrink-0">
                        <img src="<?php echo $photoUrl; ?>" alt="" class="w-full h-full object-cover" id="editPhotoThumb">
                    </div>
                    <label class="flex-1 cursor-pointer">
                        <p class="text-xs font-black text-slate-600">Change Photo</p>
                        <p class="text-[10px] text-slate-400">Tap to upload a new profile picture</p>
                        <input type="file" id="inputPhoto" name="profile_photo" class="hidden" accept="image/*" onchange="previewImage(this)">
                    </label>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                    </svg>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] font-black text-slate-400 uppercase ml-1">Full Name</label>
                        <input type="text" id="inputFullName" name="full_name"
                               value="<?php echo htmlspecialchars($sessionName); ?>"
                               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-700 focus:bg-white focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-black text-slate-400 uppercase ml-1">Username</label>
                        <input type="text" id="inputUsername" name="username"
                               value="<?php echo htmlspecialchars($sessionUser); ?>"
                               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-700 focus:bg-white focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] font-black text-slate-400 uppercase ml-1">Email</label>
                        <input type="email" id="inputEmail" name="email"
                               value="<?php echo htmlspecialchars($sessionEmail); ?>"
                               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-700 focus:bg-white focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-black text-slate-400 uppercase ml-1">Phone Number</label>
                        <input type="text" id="inputPhone" name="phone_number"
                               value="<?php echo htmlspecialchars($sessionPhone); ?>"
                               placeholder="09XXXXXXXXX"
                               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-700 focus:bg-white focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
                    </div>
                </div>

                <!-- Password section — dark navy card to match Login Account theme -->
                <div>
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 11-12 0 6 6 0 0112 0zM9 15l-4.5 4.5M9 15l1.5 1.5" />
                        </svg>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Change Password</p>
                        <span class="flex-1 h-px bg-slate-100 min-w-[12px]"></span>
                        <span class="text-[9px] text-slate-300 font-bold normal-case whitespace-nowrap">leave blank to keep current</span>
                    </div>

                    <div class="bg-slate-900 rounded-2xl p-4 sm:p-5 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-[9px] font-black text-slate-400 uppercase ml-1">New Password</label>
                                <div class="relative">
                                    <input type="password" id="inputPassword" name="password"
                                           placeholder="Min. 8 characters"
                                           oninput="updatePasswordChecklist()"
                                           class="w-full px-4 py-3 pr-11 bg-white/5 border border-white/10 rounded-xl font-medium text-white placeholder-slate-500 outline-none focus:ring-2 focus:ring-emerald-500 transition-all">
                                    <button type="button" onclick="togglePasswordVisibility('inputPassword', this)"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="space-y-1">
                                <label class="text-[9px] font-black text-slate-400 uppercase ml-1">Confirm Password</label>
                                <div class="relative">
                                    <input type="password" id="inputRePassword" name="confirm_password"
                                           placeholder="Repeat new password"
                                           class="w-full px-4 py-3 pr-11 bg-white/5 border border-white/10 rounded-xl font-medium text-white placeholder-slate-500 outline-none focus:ring-2 focus:ring-emerald-500 transition-all">
                                    <button type="button" onclick="togglePasswordVisibility('inputRePassword', this)"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Live strength checklist -->
                        <div id="pwChecklist" class="grid grid-cols-2 sm:grid-cols-4 gap-x-3 gap-y-1.5 pt-1">
                            <span data-rule="len" class="pw-rule flex items-center gap-1.5 text-[10px] font-bold text-slate-500 transition-colors">
                                <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                8+ characters
                            </span>
                            <span data-rule="upperlower" class="pw-rule flex items-center gap-1.5 text-[10px] font-bold text-slate-500 transition-colors">
                                <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Upper &amp; lower case
                            </span>
                            <span data-rule="number" class="pw-rule flex items-center gap-1.5 text-[10px] font-bold text-slate-500 transition-colors">
                                <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                A number
                            </span>
                            <span data-rule="special" class="pw-rule flex items-center gap-1.5 text-[10px] font-bold text-slate-500 transition-colors">
                                <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Special character
                            </span>
                        </div>
                    </div>
                </div>

                <div id="profileMsg" class="hidden text-xs font-bold px-4 py-3 rounded-xl"></div>

                <!-- Edit mode action buttons -->
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="cancelEditMode()"
                            class="flex-1 border border-slate-200 text-slate-500 py-3 rounded-2xl font-black text-xs tracking-widest hover:bg-slate-50 transition-all flex items-center justify-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        CANCEL
                    </button>
                    <button type="button" onclick="requestConfirm()"
                            class="flex-[2] bg-slate-900 text-white py-3 rounded-2xl font-black text-xs tracking-[0.15em] hover:bg-slate-800 hover:shadow-xl hover:shadow-slate-300 transition-all active:scale-[0.98] flex items-center justify-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        SAVE CHANGES
                    </button>
                </div>
            </form>

            <!-- Footer actions — view mode only: Edit Profile (dark pill, bottom-left like "EDIT RECORD") + Logout -->
            <div id="viewFooterActions" class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-3 pt-2 border-t border-slate-100">
                <button id="editBtn" onclick="enterEditMode()"
                        class="flex items-center justify-center gap-2 px-5 py-3 bg-slate-900 text-white rounded-2xl font-black text-[11px] uppercase tracking-widest hover:bg-slate-800 transition-all active:scale-95">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Edit Profile
                </button>
                <a href="src/auth_handler.php?action=logout"
                   class="flex items-center justify-center gap-2 px-5 py-3 border border-rose-200 text-rose-500 rounded-2xl font-black text-[11px] uppercase tracking-widest hover:bg-rose-50 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    Logout
                </a>
            </div>

        </div><!-- end modal body -->

    </div>
</div>

<!-- ═══════════════════════════════════════════
     SAVE CONFIRMATION — standalone popup, centered on the full screen.
     Always its own natural size, never squeezed by the profile card's height.
════════════════════════════════════════════ -->
<div id="confirmMode" class="fixed inset-0 z-[70] hidden items-center justify-center p-4"
     style="background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(8px);">
    <div class="bg-slate-900 rounded-[2rem] w-full max-w-sm shadow-2xl p-8 flex flex-col items-center text-center gap-4">
        <div class="w-16 h-16 bg-emerald-500 rounded-2xl flex items-center justify-center shadow-xl shrink-0"
             style="box-shadow: 0 20px 40px -10px rgba(16,185,129,0.4);">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <div>
            <p class="text-white font-black text-xl sm:text-2xl tracking-tight mb-1.5">Save your changes?</p>
            <p class="text-slate-300 text-xs sm:text-sm font-medium">Your profile will be updated right away.</p>
        </div>
        <div class="flex gap-3 justify-center w-full">
            <button type="button" onclick="handleFormSubmission()"
                    class="flex-1 px-6 py-3 bg-emerald-500 hover:bg-emerald-400 text-white rounded-2xl font-black text-xs sm:text-sm uppercase tracking-widest transition-all active:scale-95 shadow-lg"
                    style="box-shadow: 0 8px 20px -6px rgba(16,185,129,0.5);">
                Yes, Save
            </button>
            <button type="button" onclick="backToEdit()"
                    class="flex-1 px-6 py-3 rounded-2xl font-black text-xs sm:text-sm uppercase tracking-widest transition-all active:scale-95 text-white border border-white/15 bg-white/10 hover:bg-white/20">
                Cancel
            </button>
        </div>
    </div>
</div>


<script>
/* ── Mode helpers ─────────────────────────────── */
function setMode(mode) {
    document.getElementById('viewMode').classList.toggle('hidden', mode !== 'view');
    document.getElementById('editMode').classList.toggle('hidden', mode !== 'edit');
    document.getElementById('viewFooterActions').classList.toggle('hidden', mode !== 'view');
    document.getElementById('photoUploadLabel').classList.toggle('hidden', mode !== 'edit');

    // Confirm overlay — absolute dark overlay over the modal body
    const confirmEl = document.getElementById('confirmMode');
    if (mode === 'confirm') {
        confirmEl.classList.remove('hidden');
        confirmEl.classList.add('flex');
    } else {
        confirmEl.classList.add('hidden');
        confirmEl.classList.remove('flex');
    }

    // Hide any lingering error messages when switching modes
    document.getElementById('profileMsg').classList.add('hidden');
}

function enterEditMode()  { setMode('edit'); }
function cancelEditMode() { resetPasswordFields(); setMode('view'); }
function backToEdit()     { setMode('edit'); }

function resetPasswordFields() {
    const pass   = document.getElementById('inputPassword');
    const rePass = document.getElementById('inputRePassword');
    if (pass) pass.value = '';
    if (rePass) rePass.value = '';
    document.querySelectorAll('.pw-rule').forEach(el => {
        el.classList.remove('text-emerald-400');
        el.classList.add('text-slate-500');
    });
}

/* ── Password strength ────────────────────────── */
const PW_SPECIAL_REGEX = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?~`]/;

function getPasswordChecks(pass) {
    return {
        len:        pass.length >= 8,
        upperlower: /[a-z]/.test(pass) && /[A-Z]/.test(pass),
        number:     /\d/.test(pass),
        special:    PW_SPECIAL_REGEX.test(pass)
    };
}

function updatePasswordChecklist() {
    const pass   = document.getElementById('inputPassword').value;
    const checks = getPasswordChecks(pass);

    Object.keys(checks).forEach(rule => {
        const el = document.querySelector(`.pw-rule[data-rule="${rule}"]`);
        if (!el) return;
        el.classList.toggle('text-emerald-400', checks[rule]);
        el.classList.toggle('text-slate-500', !checks[rule]);
    });
}

function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.classList.toggle('text-emerald-400', input.type === 'text');
}

function requestConfirm() {
    // Validate before showing confirm step
    const fullName = document.getElementById('inputFullName').value.trim();
    const email    = document.getElementById('inputEmail').value.trim();
    const pass     = document.getElementById('inputPassword').value;
    const rePass   = document.getElementById('inputRePassword').value;

    if (!fullName || !email) {
        showProfileMsg('Please fill in the required Name and Email fields.', true);
        return;
    }

    if (pass) {
        const checks = getPasswordChecks(pass);
        if (!checks.len) {
            showProfileMsg('New password must be at least 8 characters.', true);
            return;
        }
        if (!checks.upperlower) {
            showProfileMsg('New password must include both uppercase and lowercase letters.', true);
            return;
        }
        if (!checks.number) {
            showProfileMsg('New password must include at least one number.', true);
            return;
        }
        if (!checks.special) {
            showProfileMsg('New password must include at least one special character (e.g. ! @ # $ %).', true);
            return;
        }
    }
    if (pass !== rePass) {
        showProfileMsg('Passwords do not match.', true);
        return;
    }
    setMode('confirm');
}

/* ── Modal toggle ─────────────────────────────── */
function hideConfirm() {
    const confirmEl = document.getElementById('confirmMode');
    confirmEl.classList.add('hidden');
    confirmEl.classList.remove('flex');
}

function toggleProfileModal() {
    const modal = document.getElementById('profileModal');
    const isHidden = modal.classList.toggle('hidden');
    document.body.style.overflow = isHidden ? 'auto' : 'hidden';

    // The confirm popup lives outside #profileModal, so it needs to be
    // hidden explicitly whenever the profile modal opens or closes.
    hideConfirm();

    // Always reset to view mode when reopening
    if (!isHidden) {
        setMode('view');
        resetPasswordFields();
    }
}

/* ── Photo preview ────────────────────────────── */
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('profilePreview').src = e.target.result;
            const thumb = document.getElementById('editPhotoThumb');
            if (thumb) thumb.src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

/* ── Submit ───────────────────────────────────── */
async function handleFormSubmission() {
    const fullName    = document.getElementById('inputFullName').value.trim();
    const saveBtn     = document.querySelector('#confirmMode button[onclick="handleFormSubmission()"]');
    if (saveBtn) { saveBtn.disabled = true; saveBtn.classList.add('opacity-60', 'pointer-events-none'); }

    const formData = new FormData(document.getElementById('editMode'));
    formData.append('action', 'update_profile');

    try {
        const res  = await fetch('src/auth_handler.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            // Update visible values in view mode
            document.getElementById('viewFullName').textContent = fullName;
            document.getElementById('viewUsername').textContent = '@' + document.getElementById('inputUsername').value.trim();
            document.getElementById('viewEmail').textContent    = document.getElementById('inputEmail').value.trim();
            document.getElementById('viewPhone').textContent    = document.getElementById('inputPhone').value.trim() || '—';

            // Update header name
            const nameLabel = document.getElementById('displayUserName');
            if (nameLabel) nameLabel.textContent = fullName;

            // Update avatar if new photo returned
            if (data.photo) {
                document.getElementById('profilePreview').src = 'uploads/' + data.photo;
            }

            resetPasswordFields();
            hideConfirm();
            toggleProfileModal();
            showToast('✓ Profile updated successfully', false);
        } else {
            hideConfirm();
            setMode('edit');
            showProfileMsg(data.message || 'Update failed.', true);
        }
    } catch (err) {
        hideConfirm();
        setMode('edit');
        showProfileMsg('Network error. Please try again.', true);
    } finally {
        if (saveBtn) { saveBtn.disabled = false; saveBtn.classList.remove('opacity-60', 'pointer-events-none'); }
    }
}

/* ── Helpers ──────────────────────────────────── */
function showProfileMsg(msg, isError) {
    const box = document.getElementById('profileMsg');
    box.textContent = msg;
    box.className = `text-xs font-bold px-4 py-3 rounded-xl ${
        isError
            ? 'bg-rose-50 text-rose-600 border border-rose-200'
            : 'bg-emerald-50 text-emerald-700 border border-emerald-200'
    }`;
    box.classList.remove('hidden');
}

function showToast(msg, isError = false) {
    document.getElementById('globalToast')?.remove();
    const el = document.createElement('div');
    el.id = 'globalToast';
    el.className = `fixed bottom-6 right-6 z-[9999] px-5 py-3 rounded-2xl shadow-2xl font-black text-xs uppercase tracking-widest flex items-center gap-2 text-white ${isError ? 'bg-rose-600' : 'bg-emerald-600'}`;
    el.style.transition = 'transform 0.3s ease';
    el.style.transform  = 'translateY(80px)';
    el.innerHTML = `<span>${msg}</span>`;
    document.body.appendChild(el);
    setTimeout(() => el.style.transform = 'translateY(0)', 50);
    setTimeout(() => { el.style.transform = 'translateY(80px)'; setTimeout(() => el.remove(), 400); }, 3500);
}
</script>