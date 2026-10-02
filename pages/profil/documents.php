<?php
// Controle d'acces : refuse les visiteurs non connectes.
defined('AUTH_RESPONSE') or define('AUTH_RESPONSE', 'fragment');
require_once __DIR__ . '/../../includes/check_session.php';
if (session_status() === PHP_SESSION_NONE) {
}
// Sécurité : si la session est vide, on peut arrêter le script ici
if (!isset($_SESSION['user_im'])) {
    die("Session expirée. Veuillez vous reconnecter.");
}
?>
<div class="p-6 max-w-4xl mx-auto">
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-8 text-center bg-slate-50 border-b border-slate-100">
            <div class="w-16 h-16 bg-sky-100 text-sky-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-print text-2xl"></i>
            </div>
            <h2 class="text-xl font-bold text-gray-800">Édition de Documents Administratifs</h2>
            <p class="text-gray-500 text-sm mt-2">Sélectionnez le type de document à générer pour votre dossier.</p>
        </div>
        
        <div class="p-8 flex justify-center">
            <button onclick="openModal('docGenerationModal')" class="bg-sky-600 text-white px-8 py-4 rounded-2xl font-bold shadow-lg shadow-sky-100 hover:bg-sky-700 transition flex items-center gap-3">
                <i class="fas fa-plus-circle"></i> PRÉPARER UN DOCUMENT
            </button>
        </div>
    </div>
</div>

<div id="docGenerationModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-[300]">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden animate-pop-in">
        <div class="p-6 bg-white border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-file-pdf text-red-500"></i> Nouveau document
            </h3>
            <button onclick="closeModal('docGenerationModal')" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        
        <form action='documents/carriere/generate_accessoires.php' method="POST" target="_blank" class="p-8">
            <div class="mb-6">
                <label class="block text-[10px] font-bold text-sky-700 uppercase mb-2">Choisir le type de document</label>
                <div class="relative">
                    <select name="type_document" required class="w-full  bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm appearance-none focus:outline-none focus:ring-2 focus:ring-sky-500 transition">
                        <option value="" disabled selected>Sélectionnez un modèle...</option>
                        <option value="certificat_administratif">Certificat Administratif</option>
                        <option value="attestation_non_interruption">Attestation de non interruption de service</option>
                    </select>
                    <i class="fas fa-chevron-down absolute right-4 top-4 text-gray-400 pointer-events-none text-xs"></i>
                </div>
            </div>
            <button type="submit" class="w-full bg-slate-900 text-white p-4 rounded-xl font-bold shadow-xl hover:bg-black transition flex items-center justify-center gap-3">
                <i class="fas fa-download"></i> GÉNÉRER ET IMPRIMER
            </button>
        </form>
    </div>
</div>

<script>
window.openModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }
};
window.closeModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = 'auto';
    }
};

window.onclick = function(event) {
    const modal = document.getElementById('docGenerationModal');
    if (event.target == modal) {
        closeModal('docGenerationModal');
    }
}
</script>